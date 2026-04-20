<?php
/**
 * Uninstall routine  removes all plugin data.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes all plugin data for the current site.
 */
function pla_uninstall_current_site(): void {
	$reports = get_posts(
		array(
			'post_type'      => 'pla_report',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	foreach ( $reports as $report_id ) {
		wp_delete_post( (int) $report_id, true );
	}

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pla\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pla\_%' OR option_name LIKE '_transient_timeout_pla\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'number' => 0 ) );
	foreach ( $sites as $site ) {
		switch_to_blog( (int) $site->blog_id );
		pla_uninstall_current_site();
		restore_current_blog();
	}
} else {
	pla_uninstall_current_site();
}
