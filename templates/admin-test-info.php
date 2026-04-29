<?php
/**
 * Admin test info page template.
 *
 * Variables available from Admin::render_reports_page():
 *   $grouped  array   Checks grouped by category.
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
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'plugin-auditor', 'tab' => 'reports' ), admin_url( 'tools.php' ) ) ); ?>" class="pla-tab">
			<?php esc_html_e( 'Reports', 'plugin-auditor' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'plugin-auditor', 'tab' => 'test-info' ), admin_url( 'tools.php' ) ) ); ?>" class="pla-tab pla-tab--active">
			<?php esc_html_e( 'Test Info', 'plugin-auditor' ); ?>
		</a>
	</nav>

	<div class="pla-admin-section">
		<h3><?php esc_html_e( 'Test Reference', 'plugin-auditor' ); ?></h3>
		<table class="pla-info-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Test', 'plugin-auditor' ); ?></th>
					<th><?php esc_html_e( 'Details', 'plugin-auditor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $grouped as $category => $checks ) : ?>
					<?php foreach ( $checks as $slug => $check ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $check['label'] ); ?></strong>
								<span class="pla-info-category"><?php echo esc_html( $category ); ?></span>
							</td>
							<td></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

</div>
