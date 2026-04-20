<?php
/**
 * Registers the pla_report Custom Post Type.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CPT registration for pla_report.
 */
class Cpt {

	/**
	 * Registers WordPress hooks.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register' ), 10, 0 );
	}

	/**
	 * Registers the pla_report post type.
	 */
	public function register(): void {
		register_post_type(
			'pla_report',
			array(
				'labels'            => array(
					'name'          => esc_html__( 'Audit Reports', 'plugin-auditor' ),
					'singular_name' => esc_html__( 'Audit Report', 'plugin-auditor' ),
				),
				'public'            => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => false,
				'capability_type'   => 'post',
				'capabilities'      => array(
					'create_posts'       => 'manage_options',
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options',
					'delete_posts'       => 'manage_options',
				),
				'map_meta_cap'      => false,
				'hierarchical'      => false,
				'supports'          => array( 'title' ),
				'has_archive'       => false,
				'rewrite'           => false,
				'query_var'         => false,
				'delete_with_user'  => false,
			)
		);
	}

	/**
	 * Enforces the 20-report cap per plugin, deleting oldest first.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 */
	public function enforce_report_cap( string $plugin_file ): void {
		$reports = get_posts(
			array(
				'post_type'      => 'pla_report',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_pla_plugin_file',
						'value' => $plugin_file,
					),
				),
			)
		);

		if ( count( $reports ) >= 20 ) {
			$to_delete = array_slice( $reports, 0, count( $reports ) - 19 );
			foreach ( $to_delete as $report_id ) {
				wp_delete_post( (int) $report_id, true );
			}
		}
	}
}
