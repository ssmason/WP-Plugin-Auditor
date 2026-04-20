<?php
/**
 * Plugin file validation and metadata resolution.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and validates a plugin file path, exposing its directory and display name.
 */
class PluginValidator {

	/**
	 * Absolute path to the plugin directory.
	 */
	public readonly string $dir;

	/**
	 * Plugin display name.
	 */
	public readonly string $name;

	private function __construct() {}

	/**
	 * Resolves plugin metadata from a relative plugin file path.
	 *
	 * Returns a populated instance on success, or WP_Error if the file does not exist.
	 *
	 * @param string $plugin_file Relative plugin file path (e.g. my-plugin/my-plugin.php).
	 * @return static|\WP_Error
	 */
	public static function resolve( string $plugin_file ): static|\WP_Error {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$dir  = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! is_dir( $dir ) || ! file_exists( $path ) ) {
			return new \WP_Error( 'not_found', __( 'Plugin directory not found.', 'plugin-auditor' ) );
		}

		$data = get_plugin_data( $path );

		$instance       = new static();
		$instance->dir  = $dir;
		$instance->name = '' !== $data['Name'] ? $data['Name'] : basename( dirname( $plugin_file ) );

		return $instance;
	}
}
