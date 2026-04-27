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

	private function __construct( string $dir, string $name ) {
		$this->dir  = $dir;
		$this->name = $name;
	}

	/**
	 * Resolves plugin metadata from a relative plugin file path.
	 *
	 * Returns a populated instance on success, or WP_Error if the file does not exist.
	 *
	 * @param string $plugin_file Relative plugin file path (e.g. my-plugin/my-plugin.php).
	 * @return self|\WP_Error
	 */
	public static function resolve( string $plugin_file ): self|\WP_Error {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$dir  = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! is_dir( $dir ) || ! file_exists( $path ) ) {
			return new \WP_Error( 'not_found', __( 'Plugin directory not found.', 'plugin-auditor' ) );
		}

		$data = get_plugin_data( $path );
		$name = '' !== $data['Name'] ? $data['Name'] : basename( dirname( $plugin_file ) );

		return new self( $dir, $name );
	}
}
