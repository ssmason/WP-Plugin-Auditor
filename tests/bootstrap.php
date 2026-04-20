<?php
/**
 * PHPUnit bootstrap.
 *
 * @package PluginAuditor\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/brain/monkey/inc/patchwork-loader.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	// Unit tests — define ABSPATH so class files pass their guard, mock WP via Brain\Monkey.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}
	return;
}

// Integration tests — load the WP test suite.
require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/plugin-auditor.php';
	}
);

require $wp_tests_dir . '/includes/bootstrap.php';
