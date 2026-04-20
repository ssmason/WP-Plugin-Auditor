<?php
/**
 * Admin reports page template.
 *
 * Variables available from Admin::render_reports_page():
 *   $reports  \WP_Post[]  All report posts.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Plugin Audit Reports', 'plugin-auditor' ); ?></h1>

	<?php if ( empty( $reports ) ) : ?>
		<p><?php esc_html_e( 'No audit reports yet. Click "Audit" on any plugin to begin.', 'plugin-auditor' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plugin', 'plugin-auditor' ); ?></th>
					<th><?php esc_html_e( 'Risk', 'plugin-auditor' ); ?></th>
					<th><?php esc_html_e( 'Date', 'plugin-auditor' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'plugin-auditor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $reports as $report ) : ?>
					<?php
					$plugin_name    = (string) get_post_meta( $report->ID, '_pla_plugin_name', true );
					$risk           = (string) get_post_meta( $report->ID, '_pla_risk', true );
					$view_nonce     = wp_create_nonce( 'pla_view_report_' . $report->ID );
					$download_nonce = wp_create_nonce( 'pla_download_json_' . $report->ID );
					$delete_nonce   = wp_create_nonce( 'pla_delete_report_' . $report->ID );
					?>
					<tr>
						<td><?php echo esc_html( $plugin_name ); ?></td>
						<td>
							<span class="pla-risk pla-risk--<?php echo esc_attr( strtolower( $risk ) ); ?>">
								<?php echo esc_html( $risk ); ?>
							</span>
						</td>
						<td><?php echo esc_html( (string) get_the_date( 'Y-m-d H:i', $report ) ); ?></td>
						<td>
							<button
								type="button"
								class="button pla-view-report"
								data-report-id="<?php echo esc_attr( (string) $report->ID ); ?>"
								data-nonce="<?php echo esc_attr( $view_nonce ); ?>"
							><?php esc_html_e( 'View', 'plugin-auditor' ); ?></button>
							<button
								type="button"
								class="button pla-download-report"
								data-report-id="<?php echo esc_attr( (string) $report->ID ); ?>"
								data-nonce="<?php echo esc_attr( $download_nonce ); ?>"
							><?php esc_html_e( 'Download JSON', 'plugin-auditor' ); ?></button>
							<button
								type="button"
								class="button pla-delete-report"
								data-report-id="<?php echo esc_attr( (string) $report->ID ); ?>"
								data-nonce="<?php echo esc_attr( $delete_nonce ); ?>"
							><?php esc_html_e( 'Delete', 'plugin-auditor' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
