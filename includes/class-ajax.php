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
 * Routes AJAX requests to the appropriate service.
 */
class Ajax {

	/**
	 * @param Scanner          $scanner      File analysis engine.
	 * @param Report           $report       Report storage.
	 * @param ReportRenderer   $renderer     Report HTML renderer.
	 * @param ReportRepository $repository   Report database queries.
	 * @param RateLimiter      $rate_limiter Concurrent audit guard.
	 */
	public function __construct(
		private Scanner          $scanner,
		private Report           $report,
		private ReportRenderer   $renderer,
		private ReportRepository $repository,
		private RateLimiter      $rate_limiter
	) {}

	/**
	 * Registers WordPress AJAX hooks.
	 */
	public function init(): void {
		add_action( 'wp_ajax_pla_run_audit',      array( $this, 'handle_run_audit' ),      10, 0 );
		add_action( 'wp_ajax_pla_get_report',     array( $this, 'handle_get_report' ),     10, 0 );
		add_action( 'wp_ajax_pla_download_json',  array( $this, 'handle_download_json' ),  10, 0 );
		add_action( 'wp_ajax_pla_delete_report',  array( $this, 'handle_delete_report' ),  10, 0 );
		add_action( 'wp_ajax_pla_bulk_delete',    array( $this, 'handle_bulk_delete' ),    10, 0 );
	}

	/**
	 * Handles the pla_run_audit AJAX action.
	 */
	public function handle_run_audit(): void {
		$this->require_capability();
		$plugin_file = $this->validated_plugin_file();

		if ( $this->rate_limiter->is_locked( $plugin_file ) ) {
			wp_send_json_error( array( 'message' => __( 'An audit for this plugin is already in progress.', 'plugin-auditor' ) ), 429 );
		}

		$plugin = PluginValidator::resolve( $plugin_file );
		if ( is_wp_error( $plugin ) ) {
			wp_send_json_error( array( 'message' => $plugin->get_error_message() ), 404 );
		}

		$this->rate_limiter->lock( $plugin_file );

		try {
			$findings = $this->scanner->scan( $plugin->dir, CheckSettings::get_enabled() );
		} catch ( \Throwable $e ) {
			$this->rate_limiter->release( $plugin_file );
			wp_send_json_error( array( 'message' => __( 'Audit failed during scan.', 'plugin-auditor' ) ), 500 );
		}

		$this->repository->prune( $plugin_file );
		$report_id = $this->report->save( $plugin_file, $plugin->name, $findings );
		$this->rate_limiter->release( $plugin_file );

		if ( is_wp_error( $report_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save report.', 'plugin-auditor' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'report_id'      => $report_id,
				'html'           => $this->renderer->render( $report_id ),
				'download_nonce' => wp_create_nonce( 'pla_download_json_' . $report_id ),
			)
		);
	}

	/**
	 * Handles the pla_get_report AJAX action.
	 */
	public function handle_get_report(): void {
		$this->require_capability();
		$report_id = $this->validated_report_id( 'pla_view_report_' );

		wp_send_json_success(
			array(
				'report_id'      => $report_id,
				'html'           => $this->renderer->render( $report_id ),
				'download_nonce' => wp_create_nonce( 'pla_download_json_' . $report_id ),
			)
		);
	}

	/**
	 * Handles the pla_delete_report AJAX action.
	 */
	public function handle_delete_report(): void {
		$this->require_capability();
		$report_id = $this->validated_report_id( 'pla_delete_report_' );
		$this->repository->delete( $report_id );
		wp_send_json_success();
	}

	/**
	 * Handles the pla_bulk_delete AJAX action.
	 */
	public function handle_bulk_delete(): void {
		$this->require_capability();

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pla_bulk_delete' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'plugin-auditor' ) ), 403 );
		}

		$raw_ids = isset( $_POST['report_ids'] ) && is_array( $_POST['report_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['report_ids'] ) )
			: array();

		$deleted = 0;
		foreach ( $raw_ids as $raw_id ) {
			$report_id = absint( $raw_id );
			if ( $report_id && $this->repository->exists( $report_id ) ) {
				$this->repository->delete( $report_id );
				++$deleted;
			}
		}

		wp_send_json_success( array( 'deleted' => $deleted ) );
	}

	/**
	 * Handles the pla_download_json AJAX action.
	 */
	public function handle_download_json(): void {
		$this->require_capability();
		$report_id = $this->validated_report_id( 'pla_download_json_' );
		wp_send_json_success( $this->report->json_export( $report_id ) );
	}

	/**
	 * Terminates with a 403 JSON error if the current user lacks manage_options.
	 */
	private function require_capability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'plugin-auditor' ) ), 403 );
		}
	}

	/**
	 * Reads, sanitizes, and nonce-verifies the plugin_file POST parameter.
	 *
	 * Terminates with a JSON error on any validation failure.
	 */
	private function validated_plugin_file(): string {
		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ?? '' ) );

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( array( 'message' => __( 'No plugin specified.', 'plugin-auditor' ) ), 400 );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pla_audit_' . $plugin_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'plugin-auditor' ) ), 403 );
		}

		return $plugin_file;
	}

	/**
	 * Reads, sanitizes, nonce-verifies, and existence-checks the report_id POST parameter.
	 *
	 * Terminates with a JSON error on any validation failure.
	 *
	 * @param string $nonce_action_prefix Nonce action prefix; report ID is appended.
	 */
	private function validated_report_id( string $nonce_action_prefix ): int {
		$report_id = absint( wp_unslash( $_POST['report_id'] ?? 0 ) );

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'No report specified.', 'plugin-auditor' ) ), 400 );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $nonce_action_prefix . $report_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'plugin-auditor' ) ), 403 );
		}

		if ( ! $this->repository->exists( $report_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'plugin-auditor' ) ), 404 );
		}

		return $report_id;
	}
}
