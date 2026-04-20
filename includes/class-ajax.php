<?php
/**
 * AJAX handlers for audit triggering and report retrieval.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Handles AJAX requests.
 */
class Ajax {

	/**
	 * Scanner instance.
	 *
	 * @var Scanner
	 */
	private Scanner $scanner;

	/**
	 * Report instance.
	 *
	 * @var Report
	 */
	private Report $report;

	/**
	 * Constructor.
	 *
	 * @param Scanner $scanner Scanner instance.
	 * @param Report  $report  Report instance.
	 */
	public function __construct( Scanner $scanner, Report $report ) {
		$this->scanner = $scanner;
		$this->report  = $report;
	}

	/**
	 * Registers WordPress hooks.
	 */
	public function init(): void {
		add_action( 'wp_ajax_pla_run_audit', array( $this, 'handle_run_audit' ), 10, 0 );
		add_action( 'wp_ajax_pla_get_report', array( $this, 'handle_get_report' ), 10, 0 );
	}

	/**
	 * Handles the pla_run_audit AJAX action.
	 */
	public function handle_run_audit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'plugin-auditor' ) ), 403 );
		}

		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ?? '' ) );

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( array( 'message' => __( 'No plugin specified.', 'plugin-auditor' ) ), 400 );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pla_audit_' . $plugin_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'plugin-auditor' ) ), 403 );
		}

		// Rate limiting — one audit per plugin per 30 seconds.
		$rate_key = 'pla_running_' . md5( $plugin_file );
		if ( get_transient( $rate_key ) ) {
			wp_send_json_error( array( 'message' => __( 'An audit for this plugin is already in progress.', 'plugin-auditor' ) ), 429 );
		}

		set_transient( $rate_key, true, 120 );

		// get_plugin_data() is not available in AJAX context without this include.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_dir  = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! is_dir( $plugin_dir ) || ! file_exists( $plugin_path ) ) {
			delete_transient( $rate_key );
			wp_send_json_error( array( 'message' => __( 'Plugin directory not found.', 'plugin-auditor' ) ), 404 );
		}

		$plugin_data = get_plugin_data( $plugin_path );
		$plugin_name = '' !== $plugin_data['Name'] ? $plugin_data['Name'] : basename( dirname( $plugin_file ) );

		try {
			$findings = $this->scanner->scan( $plugin_dir );
		} catch ( \Throwable $e ) {
			delete_transient( $rate_key );
			wp_send_json_error( array( 'message' => __( 'Audit failed during scan.', 'plugin-auditor' ) ), 500 );
		}

		$cpt = new Cpt();
		$cpt->enforce_report_cap( $plugin_file );

		$report_id = $this->report->save( $plugin_file, $plugin_name, $findings );

		delete_transient( $rate_key );

		if ( is_wp_error( $report_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save report.', 'plugin-auditor' ) ), 500 );
		}

		$html = $this->report->render( $report_id );

		wp_send_json_success(
			array(
				'report_id' => $report_id,
				'html'      => $html,
			)
		);
	}

	/**
	 * Handles the pla_get_report AJAX action (retrieve a previous report).
	 */
	public function handle_get_report(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'plugin-auditor' ) ), 403 );
		}

		$report_id = absint( $_POST['report_id'] ?? 0 );

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'No report specified.', 'plugin-auditor' ) ), 400 );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pla_view_report_' . $report_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'plugin-auditor' ) ), 403 );
		}

		$post = get_post( $report_id );

		if ( ! $post || 'pla_report' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'plugin-auditor' ) ), 404 );
		}

		$html = $this->report->render( $report_id );

		wp_send_json_success( array( 'html' => $html ) );
	}
}
