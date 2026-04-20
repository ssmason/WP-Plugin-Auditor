<?php
/**
 * Modal shell template.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div
	id="pla-modal"
	class="pla-modal"
	role="dialog"
	aria-modal="true"
	aria-labelledby="pla-modal-title"
	aria-describedby="pla-modal-body"
	hidden
>
	<div class="pla-modal__backdrop" aria-hidden="true"></div>
	<div class="pla-modal__dialog">
		<div class="pla-modal__header">
			<h2 id="pla-modal-title" class="pla-modal__title">
				<?php esc_html_e( 'Plugin Audit', 'plugin-auditor' ); ?>
			</h2>
			<button
				type="button"
				class="pla-modal__close"
				aria-label="<?php esc_attr_e( 'Close audit modal', 'plugin-auditor' ); ?>"
			>
				<span aria-hidden="true">&times;</span>
			</button>
		</div>

		<div id="pla-modal-body" class="pla-modal__body">

			<div class="pla-modal__progress" hidden>
				<p class="pla-modal__progress-text">
					<?php esc_html_e( 'Audit running…', 'plugin-auditor' ); ?>
				</p>
				<div class="pla-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Audit progress', 'plugin-auditor' ); ?>">
					<div class="pla-progress-bar__fill"></div>
				</div>
				<p class="pla-modal__progress-hint">
					<?php esc_html_e( 'Scanning plugin files for security issues…', 'plugin-auditor' ); ?>
				</p>
			</div>

			<div class="pla-modal__error" hidden>
				<p class="pla-modal__error-text"></p>
				<button type="button" class="button pla-modal__retry">
					<?php esc_html_e( 'Retry', 'plugin-auditor' ); ?>
				</button>
			</div>

			<div class="pla-modal__report" hidden></div>

		</div>

		<div class="pla-modal__footer" hidden>
			<button type="button" class="button button-primary pla-modal__print">
				<?php esc_html_e( 'Download PDF', 'plugin-auditor' ); ?>
			</button>
		</div>
	</div>
</div>
