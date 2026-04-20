<?php
/**
 * PHPStan bootstrap — defines plugin constants so analysis can proceed without WordPress loaded.
 */

define( 'PLUGIN_AUDITOR_VERSION', '1.0.0' );
define( 'PLUGIN_AUDITOR_FILE', dirname( __DIR__ ) . '/plugin-auditor.php' );
define( 'PLUGIN_AUDITOR_DIR', dirname( __DIR__ ) . '/' );
define( 'PLUGIN_AUDITOR_URL', 'https://example.com/wp-content/plugins/plugin-auditor/' );
define( 'PLUGIN_AUDITOR_DB_VERSION', '1.0.0' );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
