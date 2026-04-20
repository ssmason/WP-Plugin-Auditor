<?php
/**
 * Admin hooks, action links, and admin menu.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin integration.
 */
class Admin {

	/**
	 * Registers WordPress hooks.
	 */
	public function init(): void {
		add_filter( 'plugin_action_links', array( $this, 'add_audit_link' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10, 0 );
	}

	/**
	 * Adds the "Audit" action link to each plugin row.
	 *
	 * @param string[] $actions     Existing action links.
	 * @param string   $plugin_file Plugin file path relative to plugins directory.
	 * @return string[]
	 */
	public function add_audit_link( array $actions, string $plugin_file ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$nonce = wp_create_nonce( 'pla_audit_' . $plugin_file );

		$actions['pla_audit'] = sprintf(
			'<a href="#" class="pla-audit-trigger" data-plugin="%1$s" data-nonce="%2$s">%3$s</a>',
			esc_attr( $plugin_file ),
			esc_attr( $nonce ),
			esc_html__( 'Audit', 'plugin-auditor' )
		);

		return $actions;
	}

	/**
	 * Registers the auditor admin menu page.
	 */
	public function register_menu(): void {
		add_management_page(
			esc_html__( 'Plugin Audit Reports', 'plugin-auditor' ),
			esc_html__( 'Plugin Auditor', 'plugin-auditor' ),
			'manage_options',
			'plugin-auditor',
			array( $this, 'render_reports_page' ),
			10
		);
	}

	/**
	 * Renders the admin reports page.
	 */
	public function render_reports_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'plugin-auditor' ), 403 );
		}

		$reports = get_posts(
			array(
				'post_type'      => 'pla_report',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Plugin Audit Reports', 'plugin-auditor' ) . '</h1>';

		if ( empty( $reports ) ) {
			echo '<p>' . esc_html__( 'No audit reports yet. Click "Audit" on any plugin to begin.', 'plugin-auditor' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Plugin', 'plugin-auditor' ) . '</th>';
		echo '<th>' . esc_html__( 'Risk', 'plugin-auditor' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'plugin-auditor' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'plugin-auditor' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $reports as $report ) {
			$plugin_name    = get_post_meta( $report->ID, '_pla_plugin_name', true );
			$risk           = get_post_meta( $report->ID, '_pla_risk', true );
			$view_nonce     = wp_create_nonce( 'pla_view_report_' . $report->ID );
			$download_nonce = wp_create_nonce( 'pla_download_json_' . $report->ID );

			echo '<tr>';
			echo '<td>' . esc_html( (string) $plugin_name ) . '</td>';
			echo '<td><span class="pla-risk pla-risk--' . esc_attr( strtolower( (string) $risk ) ) . '">' . esc_html( (string) $risk ) . '</span></td>';
			echo '<td>' . esc_html( (string) get_the_date( 'Y-m-d H:i', $report ) ) . '</td>';
			echo '<td>';
			printf(
				'<button type="button" class="button pla-view-report" data-report-id="%1$s" data-nonce="%2$s">%3$s</button> ',
				esc_attr( (string) $report->ID ),
				esc_attr( $view_nonce ),
				esc_html__( 'View', 'plugin-auditor' )
			);
			printf(
				'<button type="button" class="button pla-download-report" data-report-id="%1$s" data-nonce="%2$s">%3$s</button>',
				esc_attr( (string) $report->ID ),
				esc_attr( $download_nonce ),
				esc_html__( 'Download JSON', 'plugin-auditor' )
			);
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}
