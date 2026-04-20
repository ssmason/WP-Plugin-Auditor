<?php
/**
 * Report storage — saving and loading pla_report CPT data.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Saves and loads audit report findings from CPT post meta.
 */
class Report {

	/**
	 * Meta keys for each findings section.
	 */
	private const SECTION_META_KEYS = array(
		'dangerous'             => '_pla_findings_dangerous',
		'output'                => '_pla_findings_output',
		'input'                 => '_pla_findings_input',
		'nonces'                => '_pla_findings_nonces',
		'capabilities'          => '_pla_findings_capabilities',
		'database'              => '_pla_findings_database',
		'credentials'           => '_pla_findings_credentials',
		'requests'              => '_pla_findings_requests',
		'permissions'           => '_pla_findings_permissions',
		'meta'                  => '_pla_findings_meta',
		'assets'                => '_pla_findings_assets',
		'errors'                => '_pla_findings_errors',
		'obfuscation'           => '_pla_findings_obfuscation',
		'direct_access'         => '_pla_findings_direct_access',
		'debug_output'          => '_pla_findings_debug_output',
		'redirects'             => '_pla_findings_redirects',
		'role_checks'           => '_pla_findings_role_checks',
		'shortcodes'            => '_pla_findings_shortcodes',
		'option_writes'         => '_pla_findings_option_writes',
		'wpdb_placeholders'     => '_pla_findings_wpdb_placeholders',
		'deprecated'            => '_pla_findings_deprecated',
		'duplicate_hooks'       => '_pla_findings_duplicate_hooks',
		'unnecessary_closures'  => '_pla_findings_unnecessary_closures',
		'early_translations'    => '_pla_findings_early_translations',
	);

	/**
	 * Section display labels.
	 */
	private const SECTION_LABELS = array(
		'dangerous'            => 'Dangerous Functions',
		'output'               => 'Output Escaping',
		'input'                => 'Input Sanitization',
		'nonces'               => 'Nonce Verification',
		'capabilities'         => 'Capability Checks',
		'database'             => 'Database Queries',
		'credentials'          => 'Hardcoded Credentials',
		'requests'             => 'External HTTP Requests',
		'permissions'          => 'File Permissions',
		'meta'                 => 'Plugin Header / Metadata',
		'assets'               => 'Asset Versioning',
		'errors'               => 'Error Suppression',
		'obfuscation'          => 'Obfuscated Calls',
		'direct_access'        => 'Direct File Access Guard',
		'debug_output'         => 'Debug Output',
		'redirects'            => 'Redirect Without Exit',
		'role_checks'          => 'Role Name in Capability Checks',
		'shortcodes'           => 'Shortcode Output Escaping',
		'option_writes'        => 'Option Writes Without Capability',
		'wpdb_placeholders'    => 'Database Placeholder Types',
		'deprecated'           => 'Deprecated Functions',
		'duplicate_hooks'      => 'Duplicate Hook Registrations',
		'unnecessary_closures' => 'Unnecessary Closures',
		'early_translations'   => 'Early Translation Calls',
	);

	/**
	 * Saves findings as a pla_report CPT post.
	 *
	 * @param string               $plugin_file Relative plugin file path.
	 * @param string               $plugin_name Plugin display name.
	 * @param array<string, mixed> $findings    Findings from Scanner::scan().
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function save( string $plugin_file, string $plugin_name, array $findings ): int|\WP_Error {
		$title = sprintf(
			/* translators: 1: plugin name, 2: datetime */
			__( '%1$s — Audit %2$s', 'plugin-auditor' ),
			$plugin_name,
			current_time( 'Y-m-d H:i:s' )
		);

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'pla_report',
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_pla_plugin_file', $plugin_file );
		update_post_meta( $post_id, '_pla_plugin_name', $plugin_name );
		update_post_meta( $post_id, '_pla_risk', $findings['rating'] ?? 'UNKNOWN' );
		update_post_meta( $post_id, '_pla_score', $findings['score'] ?? 0 );
		update_post_meta( $post_id, '_pla_version', PLUGIN_AUDITOR_VERSION );

		foreach ( self::SECTION_META_KEYS as $section => $meta_key ) {
			if ( isset( $findings[ $section ] ) && is_array( $findings[ $section ] ) ) {
				update_post_meta( $post_id, $meta_key, $findings[ $section ] );
			}
		}

		$this->save_json_export( $post_id, $plugin_file, $plugin_name, $findings );

		return $post_id;
	}

	/**
	 * Loads findings from post meta for a given report ID.
	 *
	 * Score and rating are recalculated from stored findings on every load.
	 *
	 * @param int $report_id Post ID.
	 * @return array<string, mixed>
	 */
	public function load( int $report_id ): array {
		$findings = array();

		foreach ( self::SECTION_META_KEYS as $section => $meta_key ) {
			$value                = get_post_meta( $report_id, $meta_key, true );
			$findings[ $section ] = is_array( $value ) ? $value : array();
		}

		$findings['score']  = ScoreCalculator::score( $findings );
		$findings['rating'] = ScoreCalculator::rating( (int) $findings['score'] );

		return $findings;
	}

	/**
	 * Returns the pre-built JSON export payload for a report, or builds it on demand.
	 *
	 * @param int $report_id Post ID.
	 * @return array<string, mixed>
	 */
	public function json_export( int $report_id ): array {
		$stored = get_post_meta( $report_id, '_pla_json', true );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return $stored;
		}

		$findings    = $this->load( $report_id );
		$plugin_name = (string) get_post_meta( $report_id, '_pla_plugin_name', true );
		$plugin_file = (string) get_post_meta( $report_id, '_pla_plugin_file', true );
		$post        = get_post( $report_id );
		$sections    = $findings;
		unset( $sections['rating'], $sections['score'] );

		return array(
			'filename'    => sanitize_file_name( 'pla-' . $plugin_name . '-' . gmdate( 'Y-m-d' ) . '.json' ),
			'plugin_name' => $plugin_name,
			'plugin_file' => $plugin_file,
			'risk'        => $findings['rating'],
			'score'       => $findings['score'],
			'generated'   => $post instanceof \WP_Post ? get_the_date( 'Y-m-d H:i:s', $post ) : '',
			'findings'    => $sections,
		);
	}

	/**
	 * Returns the section display labels map.
	 *
	 * @return array<string, string>
	 */
	public static function section_labels(): array {
		return self::SECTION_LABELS;
	}

	/**
	 * Builds and stores the JSON export payload as post meta at save time.
	 *
	 * @param int                  $post_id     Post ID.
	 * @param string               $plugin_file Relative plugin file path.
	 * @param string               $plugin_name Plugin display name.
	 * @param array<string, mixed> $findings    Scan findings.
	 */
	private function save_json_export( int $post_id, string $plugin_file, string $plugin_name, array $findings ): void {
		$sections = $findings;
		unset( $sections['rating'], $sections['score'] );

		update_post_meta(
			$post_id,
			'_pla_json',
			array(
				'filename'    => sanitize_file_name( 'pla-' . $plugin_name . '-' . gmdate( 'Y-m-d' ) . '.json' ),
				'plugin_name' => $plugin_name,
				'plugin_file' => $plugin_file,
				'risk'        => $findings['rating'] ?? 'UNKNOWN',
				'score'       => $findings['score'] ?? 0,
				'generated'   => current_time( 'Y-m-d H:i:s' ),
				'findings'    => $sections,
			)
		);
	}
}
