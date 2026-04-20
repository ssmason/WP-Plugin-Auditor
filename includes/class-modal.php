<?php
/**
 * Modal markup and asset enqueue.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues modal assets and renders the modal shell on relevant admin pages.
 */
class Modal {

	/**
	 * Registers WordPress hooks.
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 1 );
		add_action( 'admin_footer', array( $this, 'render_modal_shell' ), 10, 0 );
	}

	/**
	 * Enqueues modal CSS and JS on relevant admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'plugins.php' !== $hook_suffix && 'tools_page_plugin-auditor' !== $hook_suffix ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'pla-modal',
			PLUGIN_AUDITOR_URL . 'assets/css/modal.css',
			array(),
			PLUGIN_AUDITOR_VERSION
		);

		wp_enqueue_style(
			'pla-print',
			PLUGIN_AUDITOR_URL . 'assets/css/print.css',
			array( 'pla-modal' ),
			PLUGIN_AUDITOR_VERSION,
			'print'
		);

		wp_enqueue_script(
			'pla-auditor',
			PLUGIN_AUDITOR_URL . 'assets/js/auditor.js',
			array( 'jquery' ),
			PLUGIN_AUDITOR_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		wp_localize_script( 'pla-auditor', 'plaAuditor', $this->script_data() );
	}

	/**
	 * Renders the modal shell HTML in the admin footer.
	 */
	public function render_modal_shell(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( ! in_array( $screen->id, array( 'plugins', 'tools_page_plugin-auditor' ), true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include PLUGIN_AUDITOR_DIR . 'templates/modal.php';
	}

	/**
	 * Builds the JavaScript localisation data array.
	 *
	 * @return array<string, mixed>
	 */
	private function script_data(): array {
		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'ajaxTimeout' => 120000,
			'i18n'        => array(
				'running'        => esc_html__( 'Audit running…', 'plugin-auditor' ),
				'complete'       => esc_html__( 'Audit complete', 'plugin-auditor' ),
				'error'          => esc_html__( 'Audit failed. Please try again.', 'plugin-auditor' ),
				'timeout'        => esc_html__( 'Audit timed out. The plugin may be too large. Please try again.', 'plugin-auditor' ),
				'alreadyRunning' => esc_html__( 'An audit for this plugin is already in progress.', 'plugin-auditor' ),
				'close'          => esc_html__( 'Close', 'plugin-auditor' ),
				'download'       => esc_html__( 'Download PDF', 'plugin-auditor' ),
				'retry'          => esc_html__( 'Retry', 'plugin-auditor' ),
			),
		);
	}
}
