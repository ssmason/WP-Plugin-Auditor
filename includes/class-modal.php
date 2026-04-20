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
 * Handles modal rendering and JS/CSS enqueue on the Plugins page.
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
	 * Enqueues modal CSS and JS only on the Plugins page.
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

		wp_localize_script(
			'pla-auditor',
			'plaAuditor',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'ajaxTimeout' => 120000,
				'i18n'        => array(
					'running'        => __( 'Audit running…', 'plugin-auditor' ),
					'complete'       => __( 'Audit complete', 'plugin-auditor' ),
					'error'          => __( 'Audit failed. Please try again.', 'plugin-auditor' ),
					'timeout'        => __( 'Audit timed out. The plugin may be too large. Please try again.', 'plugin-auditor' ),
					'alreadyRunning' => __( 'An audit for this plugin is already in progress.', 'plugin-auditor' ),
					'close'          => __( 'Close', 'plugin-auditor' ),
					'download'       => __( 'Download PDF', 'plugin-auditor' ),
					'retry'          => __( 'Retry', 'plugin-auditor' ),
				),
			)
		);
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
}
