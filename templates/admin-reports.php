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
		<table class="wp-list-table widefat fixed striped pla-reports-table">
			<thead>
				<tr>
					<td class="pla-col-cb manage-column column-cb check-column">
						<input type="checkbox" id="pla-select-all" />
					</td>
					<th class="pla-col-plugin"><?php esc_html_e( 'Plugin', 'plugin-auditor' ); ?></th>
					<th class="pla-col-risk"><?php esc_html_e( 'Risk', 'plugin-auditor' ); ?></th>
					<th class="pla-col-count pla-col-high"><?php esc_html_e( 'High', 'plugin-auditor' ); ?></th>
					<th class="pla-col-count pla-col-medium"><?php esc_html_e( 'Medium', 'plugin-auditor' ); ?></th>
					<th class="pla-col-count pla-col-low"><?php esc_html_e( 'Low', 'plugin-auditor' ); ?></th>
					<th class="pla-col-date"><?php esc_html_e( 'Date', 'plugin-auditor' ); ?></th>
					<th class="pla-col-actions"><?php esc_html_e( 'Actions', 'plugin-auditor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $reports as $report ) : ?>
					<?php
					$plugin_name    = (string) get_post_meta( $report->ID, '_pla_plugin_name', true );
					$risk           = (string) get_post_meta( $report->ID, '_pla_risk', true );
					$raw_counts     = get_post_meta( $report->ID, '_pla_counts', true );
					$counts         = is_array( $raw_counts ) ? $raw_counts : array( 'high' => '—', 'medium' => '—', 'low' => '—' );
					$view_nonce     = wp_create_nonce( 'pla_view_report_' . $report->ID );
					$download_nonce = wp_create_nonce( 'pla_download_json_' . $report->ID );
					$delete_nonce   = wp_create_nonce( 'pla_delete_report_' . $report->ID );
					?>
					<tr>
						<th class="check-column">
							<input
								type="checkbox"
								class="pla-report-cb"
								value="<?php echo esc_attr( (string) $report->ID ); ?>"
								data-delete-nonce="<?php echo esc_attr( $delete_nonce ); ?>"
							/>
						</th>
						<td class="pla-col-plugin">
							<?php echo esc_html( $plugin_name ); ?>
							<div class="row-actions">
								<span class="delete">
									<button
										type="button"
										class="button-link pla-delete-report pla-delete-link"
										data-report-id="<?php echo esc_attr( (string) $report->ID ); ?>"
										data-nonce="<?php echo esc_attr( $delete_nonce ); ?>"
									><?php esc_html_e( 'Delete', 'plugin-auditor' ); ?></button>
								</span>
							</div>
						</td>
						<td>
							<span class="pla-risk pla-risk--<?php echo esc_attr( strtolower( $risk ) ); ?>">
								<?php echo esc_html( $risk ); ?>
							</span>
						</td>
						<td class="pla-col-count">
							<span class="pla-count pla-count--high"><?php echo esc_html( (string) ( $counts['high'] ?? '—' ) ); ?></span>
						</td>
						<td class="pla-col-count">
							<span class="pla-count pla-count--medium"><?php echo esc_html( (string) ( $counts['medium'] ?? '—' ) ); ?></span>
						</td>
						<td class="pla-col-count">
							<span class="pla-count pla-count--low"><?php echo esc_html( (string) ( $counts['low'] ?? '—' ) ); ?></span>
						</td>
						<td><?php echo esc_html( (string) get_the_date( 'Y-m-d H:i', $report ) ); ?></td>
						<td class="pla-col-actions">
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
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="pla-bulk-actions">
			<button type="button" id="pla-bulk-delete" class="button pla-bulk-delete-btn" disabled>
				<?php esc_html_e( 'Delete Selected', 'plugin-auditor' ); ?>
			</button>
			<span class="pla-bulk-count"></span>
		</div>
	<?php endif; ?>
</div>
