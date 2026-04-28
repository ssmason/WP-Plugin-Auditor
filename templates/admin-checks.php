<?php
/**
 * Admin test settings page template.
 *
 * Variables available from Admin::render_reports_page():
 *   $grouped  array   Checks grouped by category.
 *   $enabled  array   Currently enabled check slugs.
 *   $saved    bool    True if settings were just saved.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">

	<div class="pla-page-banner">
		<img src="<?php echo esc_url( PLUGIN_AUDITOR_URL . 'assets/images/satori-logo.png' ); ?>" alt="" aria-hidden="true" class="pla-page-banner__logo" />
		<h1><?php esc_html_e( 'Satori Plugin Auditor', 'plugin-auditor' ); ?></h1>
	</div>

	<nav class="pla-tabs" aria-label="<?php esc_attr_e( 'Plugin Auditor sections', 'plugin-auditor' ); ?>">
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'plugin-auditor',
					'tab'  => 'reports',
				),
				admin_url( 'tools.php' )
			)
		);
		?>
		" class="pla-tab">
			<?php esc_html_e( 'Reports', 'plugin-auditor' ); ?>
		</a>
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'plugin-auditor',
					'tab'  => 'checks',
				),
				admin_url( 'tools.php' )
			)
		);
		?>
		" class="pla-tab pla-tab--active">
			<?php esc_html_e( 'Test Settings', 'plugin-auditor' ); ?>
		</a>
	</nav>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible pla-notice">
			<p><?php esc_html_e( 'Test settings saved.', 'plugin-auditor' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pla-checks-form">
		<input type="hidden" name="action" value="pla_save_checks" />
		<?php wp_nonce_field( 'pla_save_checks', 'pla_checks_nonce' ); ?>

		<div class="pla-checks-accordion">

		<?php foreach ( $grouped as $category => $checks ) : ?>
			<div class="pla-accordion-item">
				<button type="button" class="pla-accordion-toggle" aria-expanded="true">
					<span class="pla-accordion-title"><?php echo esc_html( $category ); ?></span>
					<span class="pla-accordion-icon" aria-hidden="true"></span>
				</button>
				<div class="pla-accordion-body">
					<table class="pla-checks-table">
						<tbody>
						<?php foreach ( $checks as $slug => $check ) : ?>
							<tr class="pla-check-row">
								<td class="pla-check-cell">
									<label class="pla-check-label" for="pla-check-<?php echo esc_attr( $slug ); ?>">
										<input
											type="checkbox"
											id="pla-check-<?php echo esc_attr( $slug ); ?>"
											name="pla_checks[]"
											value="<?php echo esc_attr( $slug ); ?>"
											<?php checked( in_array( $slug, $enabled, true ) ); ?>
										/>
										<?php echo esc_html( $check['label'] ); ?>
									</label>
								</td>
								<td class="pla-check-default">
									<?php if ( $check['default'] ) : ?>
										<span class="pla-badge pla-badge--on"><?php esc_html_e( 'Default ON', 'plugin-auditor' ); ?></span>
									<?php else : ?>
										<span class="pla-badge pla-badge--off"><?php esc_html_e( 'Default OFF', 'plugin-auditor' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endforeach; ?>

		</div>

		<div class="pla-checks-actions">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Test Settings', 'plugin-auditor' ); ?>
			</button>
			<button type="button" class="button pla-reset-defaults">
				<?php esc_html_e( 'Reset to Defaults', 'plugin-auditor' ); ?>
			</button>
		</div>

	</form>

</div>
