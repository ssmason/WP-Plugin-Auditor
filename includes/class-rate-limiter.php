<?php
/**
 * Transient-based rate limiting for audit requests.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Prevents concurrent duplicate audit requests via transients.
 */
class RateLimiter {

	/**
	 * Lock TTL in seconds.
	 */
	private const TTL = 120;

	/**
	 * Returns true if a lock exists for the given plugin file.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 */
	public function is_locked( string $plugin_file ): bool {
		return (bool) get_transient( $this->key( $plugin_file ) );
	}

	/**
	 * Acquires the lock for the given plugin file.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 */
	public function lock( string $plugin_file ): void {
		set_transient( $this->key( $plugin_file ), true, self::TTL );
	}

	/**
	 * Releases the lock for the given plugin file.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 */
	public function release( string $plugin_file ): void {
		delete_transient( $this->key( $plugin_file ) );
	}

	/**
	 * Builds the transient key for a plugin file.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 */
	private function key( string $plugin_file ): string {
		return 'pla_running_' . md5( $plugin_file );
	}
}
