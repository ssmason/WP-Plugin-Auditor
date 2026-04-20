<?php
/**
 * Plugin Name:       Plugin Auditor
 * Plugin URI:        https://github.com/plugin-auditor/plugin-auditor
 * Description:       Audits WordPress plugins for security issues, coding standards violations, and permissions.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Plugin Auditor
 * Author URI:        https://github.com/plugin-auditor
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plugin-auditor
 * Domain Path:       /languages
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'PLUGIN_AUDITOR_VERSION', '1.0.0' );
define( 'PLUGIN_AUDITOR_FILE', __FILE__ );
define( 'PLUGIN_AUDITOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLUGIN_AUDITOR_URL', plugin_dir_url( __FILE__ ) );
define( 'PLUGIN_AUDITOR_DB_VERSION', '1.0.0' );

require_once PLUGIN_AUDITOR_DIR . 'vendor/autoload.php';

/**
 * Bootstraps the plugin.
 */
function pla_boot(): void {
	$cpt   = new \PluginAuditor\Cpt();
	$admin = new \PluginAuditor\Admin();
	$ajax  = new \PluginAuditor\Ajax( new \PluginAuditor\Scanner(), new \PluginAuditor\Report() );
	$modal = new \PluginAuditor\Modal();

	$cpt->init();
	$admin->init();
	$ajax->init();
	$modal->init();
}
add_action( 'plugins_loaded', 'pla_boot' );

register_activation_hook( __FILE__, 'pla_activate' );
register_deactivation_hook( __FILE__, 'pla_deactivate' );

/**
 * Plugin activation.
 */
function pla_activate(): void {
	$cpt = new \PluginAuditor\Cpt();
	$cpt->register();
	flush_rewrite_rules();
	update_option( 'pla_db_version', PLUGIN_AUDITOR_DB_VERSION );
}

/**
 * Plugin deactivation.
 */
function pla_deactivate(): void {
	flush_rewrite_rules();
	wp_clear_scheduled_hook( 'pla_cleanup' );
}
