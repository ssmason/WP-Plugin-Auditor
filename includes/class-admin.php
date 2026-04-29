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
	private string $page_hook = '';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 1 );
		add_action( 'admin_post_pla_save_checks', array( $this, 'handle_save_checks' ), 10, 0 );
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
		$hook = add_management_page(
			esc_html__( 'Plugin Audit Reports', 'plugin-auditor' ),
			esc_html__( 'Plugin Auditor', 'plugin-auditor' ),
			'manage_options',
			'plugin-auditor',
			array( $this, 'render_reports_page' ),
			10
		);

		if ( false !== $hook ) {
			$this->page_hook = $hook;
		}
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $this->page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'pla-admin',
			PLUGIN_AUDITOR_URL . 'assets/css/admin.css',
			array(),
			PLUGIN_AUDITOR_VERSION
		);

		wp_enqueue_script(
			'pla-admin-tools',
			PLUGIN_AUDITOR_URL . 'assets/js/admin-tools.js',
			array(),
			PLUGIN_AUDITOR_VERSION,
			true
		);
	}

	/**
	 * Renders the admin reports page.
	 */
	public function render_reports_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'plugin-auditor' ), '', array( 'response' => 403 ) );
		}

		$grouped = CheckSettings::grouped();
		$enabled = CheckSettings::get_enabled();
		$saved   = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reports = $this->repository->all( 50 );
		include PLUGIN_AUDITOR_DIR . 'templates/admin-reports.php';
	}

	/**
	 * Handles the pla_save_checks form POST.
	 */
	public function handle_save_checks(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'plugin-auditor' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'pla_save_checks', 'pla_checks_nonce' );

		$posted = isset( $_POST['pla_checks'] ) && is_array( $_POST['pla_checks'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['pla_checks'] ) )
			: array();

		CheckSettings::save( $posted );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'plugin-auditor',
					'saved' => '1',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
