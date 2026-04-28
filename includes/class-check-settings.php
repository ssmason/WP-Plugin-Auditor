<?php
/**
 * Check settings — manages which scanner checks are enabled.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and retrieves the set of enabled scanner check slugs.
 */
class CheckSettings {

	/**
	 * WordPress option key.
	 */
	private const OPTION_KEY = 'pla_enabled_checks';

	/**
	 * All check slugs with their display label and category.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const CHECKS = array(
		// Security — ON by default.
		'dangerous_functions'  => array( 'Dangerous Functions', 'Security' ),
		'output_escaping'      => array( 'Output Escaping', 'Security' ),
		'input_sanitization'   => array( 'Input Sanitization', 'Security' ),
		'nonce_verification'   => array( 'Nonce Verification', 'Security' ),
		'capability_checks'    => array( 'Capability Checks', 'Security' ),
		'credentials'          => array( 'Hardcoded Credentials', 'Security' ),
		'obfuscation'          => array( 'Obfuscation', 'Security' ),
		'redirects'            => array( 'Redirect Without Exit', 'Security' ),
		'shortcodes'           => array( 'Shortcode Output Escaping', 'Security' ),
		// Security — OFF by default.
		'call_user_func'       => array( 'call_user_func / call_user_func_array', 'Security' ),
		'base64'               => array( 'base64_encode / base64_decode', 'Security' ),
		'nonces_post'          => array( 'File-level $_POST Nonce Check', 'Security' ),
		// Database — ON by default.
		'database'             => array( 'Database Queries', 'Database' ),
		'wpdb_placeholders'    => array( 'Wrong $wpdb->prepare() Placeholder Type', 'Database' ),
		'option_writes'        => array( 'Option Writes Without Capability Check', 'Database' ),
		// Code Quality — ON by default.
		'error_suppression'    => array( 'Error Suppression', 'Code Quality' ),
		'debug_php'            => array( 'Debug Output (PHP)', 'Code Quality' ),
		'debug_js'             => array( 'Debug Output (JS)', 'Code Quality' ),
		// Code Quality — OFF by default.
		'commented_code'       => array( 'Commented-Out Code', 'Code Quality' ),
		'unnecessary_closures' => array( 'Unnecessary Closures', 'Code Quality' ),
		'early_translations'   => array( 'Early Translations', 'Code Quality' ),
		'duplicate_hooks'      => array( 'Duplicate Hook Registrations', 'Code Quality' ),
		'debug_js_warn'        => array( 'console.warn / console.error', 'Code Quality' ),
		// Compatibility — ON by default.
		'php_compat'           => array( 'PHP Compatibility', 'Compatibility' ),
		'deprecated'           => array( 'Deprecated Functions', 'Compatibility' ),
		// Plugin Standards — ON by default.
		'plugin_structure'     => array( 'Plugin Structure (index.php sentinels)', 'Plugin Standards' ),
		'file_permissions'     => array( 'File Permissions', 'Plugin Standards' ),
		// Plugin Standards — OFF by default.
		'licensing'            => array( 'Licensing', 'Plugin Standards' ),
		'meta'                 => array( 'Plugin Header / Metadata', 'Plugin Standards' ),
		'readme'               => array( 'Readme.txt', 'Plugin Standards' ),
		'assets'               => array( 'Asset Version Strings', 'Plugin Standards' ),
		// Roles & Permissions — ON by default.
		'role_checks'          => array( 'Role Name in current_user_can()', 'Roles & Permissions' ),
		// External — OFF by default.
		'requests'             => array( 'Outbound HTTP Requests', 'External' ),
	);

	/**
	 * Slugs that are ON by default.
	 *
	 * @var string[]
	 */
	private const DEFAULT_ON = array(
		'dangerous_functions',
		'output_escaping',
		'input_sanitization',
		'nonce_verification',
		'capability_checks',
		'credentials',
		'obfuscation',
		'redirects',
		'shortcodes',
		'database',
		'wpdb_placeholders',
		'option_writes',
		'error_suppression',
		'debug_php',
		'debug_js',
		'php_compat',
		'deprecated',
		'plugin_structure',
		'file_permissions',
		'role_checks',
	);

	/**
	 * Returns all check slugs.
	 *
	 * @return string[]
	 */
	public static function all_slugs(): array {
		return array_keys( self::CHECKS );
	}

	/**
	 * Returns the default-ON slugs.
	 *
	 * @return string[]
	 */
	public static function defaults(): array {
		return self::DEFAULT_ON;
	}

	/**
	 * Returns checks grouped by category, each with label and default flag.
	 *
	 * @return array<string, array<string, array{label: string, default: bool}>>
	 */
	public static function grouped(): array {
		$grouped = array();
		foreach ( self::CHECKS as $slug => $meta ) {
			[ $label, $category ]          = $meta;
			$grouped[ $category ][ $slug ] = array(
				'label'   => $label,
				'default' => in_array( $slug, self::DEFAULT_ON, true ),
			);
		}
		return $grouped;
	}

	/**
	 * Returns the currently enabled check slugs (falls back to defaults).
	 *
	 * @return string[]
	 */
	public static function get_enabled(): array {
		$stored = get_option( self::OPTION_KEY );
		if ( false === $stored || ! is_array( $stored ) ) {
			return self::DEFAULT_ON;
		}
		return array_values(
			array_filter( $stored, static fn( $s ) => isset( self::CHECKS[ $s ] ) )
		);
	}

	/**
	 * Persists the given slugs as the enabled set.
	 *
	 * @param string[] $slugs Slugs to enable.
	 */
	public static function save( array $slugs ): void {
		$valid = array_values(
			array_filter( $slugs, static fn( $s ) => isset( self::CHECKS[ $s ] ) )
		);
		update_option( self::OPTION_KEY, $valid, false );
	}

	/**
	 * Returns the display label for a slug.
	 *
	 * @param string $slug Check slug.
	 */
	public static function label( string $slug ): string {
		return self::CHECKS[ $slug ][0] ?? $slug;
	}
}
