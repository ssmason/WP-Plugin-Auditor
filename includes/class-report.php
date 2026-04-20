<?php
/**
 * Report storage and rendering.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Handles saving and rendering audit reports.
 */
class Report {

	/**
	 * Meta keys for each findings section.
	 */
	private const SECTION_META_KEYS = array(
		'dangerous'    => '_pla_findings_dangerous',
		'output'       => '_pla_findings_output',
		'input'        => '_pla_findings_input',
		'nonces'       => '_pla_findings_nonces',
		'capabilities' => '_pla_findings_capabilities',
		'database'     => '_pla_findings_database',
		'credentials'  => '_pla_findings_credentials',
		'requests'     => '_pla_findings_requests',
		'permissions'  => '_pla_findings_permissions',
		'meta'         => '_pla_findings_meta',
		'assets'       => '_pla_findings_assets',
		'errors'       => '_pla_findings_errors',
		'obfuscation'  => '_pla_findings_obfuscation',
	);

	/**
	 * Section display labels.
	 */
	private const SECTION_LABELS = array(
		'dangerous'    => 'Dangerous Functions',
		'output'       => 'Output Escaping',
		'input'        => 'Input Sanitization',
		'nonces'       => 'Nonce Verification',
		'capabilities' => 'Capability Checks',
		'database'     => 'Database Queries',
		'credentials'  => 'Hardcoded Credentials',
		'requests'     => 'External HTTP Requests',
		'permissions'  => 'File Permissions',
		'meta'         => 'Plugin Header / Metadata',
		'assets'       => 'Asset Versioning',
		'errors'       => 'Error Suppression',
		'obfuscation'  => 'Obfuscated Calls',
	);

	/**
	 * Saves findings as a pla_report CPT post.
	 *
	 * @param string               $plugin_file Plugin file path relative to plugins dir.
	 * @param string               $plugin_name Plugin display name.
	 * @param array<string, mixed> $findings    Findings from Scanner::scan().
	 * @return int|\WP_Error Post ID or WP_Error.
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

		return $post_id;
	}

	/**
	 * Loads findings from post meta for a given report ID.
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

		$findings['rating'] = (string) get_post_meta( $report_id, '_pla_risk', true );
		$findings['score']  = (int) get_post_meta( $report_id, '_pla_score', true );

		return $findings;
	}

	/**
	 * Renders a full HTML report for a given report post ID.
	 *
	 * @param int $report_id Post ID.
	 * @return string HTML output.
	 */
	public function render( int $report_id ): string {
		$post = get_post( $report_id );
		if ( ! $post ) {
			return '';
		}

		$findings    = $this->load( $report_id );
		$plugin_name = (string) get_post_meta( $report_id, '_pla_plugin_name', true );
		$rating      = $findings['rating'];
		$score       = $findings['score'];

		ob_start();

		include PLUGIN_AUDITOR_DIR . 'templates/report.php';

		return (string) ob_get_clean();
	}

	/**
	 * Returns previous reports for a given plugin file.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 * @return \WP_Post[]
	 */
	public function get_previous_reports( string $plugin_file ): array {
		return get_posts(
			array(
				'post_type'      => 'pla_report',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_pla_plugin_file',
						'value' => $plugin_file,
					),
				),
			)
		);
	}

	/**
	 * Returns the section labels map.
	 *
	 * @return array<string, string>
	 */
	public static function section_labels(): array {
		return self::SECTION_LABELS;
	}
}
