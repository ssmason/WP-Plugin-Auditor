<?php
/**
 * Admin hooks, action links, and menu registration.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin action links and the reports menu page.
 */
class Admin {

	/**
	 * @param ReportRepository $repository Report database queries.
	 */
	public function __construct( private ReportRepository $repository ) {}

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

		$reports = $this->repository->all( 50 );

		include PLUGIN_AUDITOR_DIR . 'templates/admin-reports.php';
	}
}
