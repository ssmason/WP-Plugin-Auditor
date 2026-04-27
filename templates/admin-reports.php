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

$active_plugins    = (array) get_option( 'active_plugins', array() );
$installed_plugins = get_plugins();

$report_map = array();
foreach ( $reports as $rpt ) {
	$rpt_file = (string) get_post_meta( $rpt->ID, '_pla_plugin_file', true );
	if ( '' !== $rpt_file && ! isset( $report_map[ $rpt_file ] ) ) {
		$report_map[ $rpt_file ] = $rpt;
	}
}

$avatar_palettes = array(
	array( '#dbeafe', '#1e3a8a' ),
	array( '#ede9fe', '#4c1d95' ),
	array( '#fce7f3', '#831843' ),
	array( '#fef3c7', '#78350f' ),
	array( '#d1fae5', '#064e3b' ),
	array( '#fee2e2', '#7f1d1d' ),
	array( '#e0f2fe', '#0c4a6e' ),
	array( '#ccfbf1', '#134e4a' ),
);

$checks = array(
	array( __( 'Dangerous functions', 'plugin-auditor' ), 'https://owasp.org/www-community/attacks/Code_Injection', __( 'OWASP — Code Injection', 'plugin-auditor' ) ),
	array( __( 'Output escaping', 'plugin-auditor' ), 'https://developer.wordpress.org/apis/security/escaping/', __( 'WP Handbook — Escaping', 'plugin-auditor' ) ),
	array( __( 'Input sanitization', 'plugin-auditor' ), 'https://developer.wordpress.org/apis/security/sanitizing/', __( 'WP Handbook — Sanitizing', 'plugin-auditor' ) ),
	array( __( 'Nonce verification', 'plugin-auditor' ), 'https://developer.wordpress.org/apis/security/nonces/', __( 'WP Handbook — Nonces', 'plugin-auditor' ) ),
	array( __( 'Capability checks', 'plugin-auditor' ), 'https://developer.wordpress.org/apis/security/current-user-can/', __( 'WP Handbook — current_user_can()', 'plugin-auditor' ) ),
	array( __( 'Database queries', 'plugin-auditor' ), 'https://developer.wordpress.org/apis/security/sql-injection/', __( 'WP Handbook — SQL Injection', 'plugin-auditor' ) ),
	array( __( 'Hardcoded credentials', 'plugin-auditor' ), 'https://owasp.org/www-community/vulnerabilities/Use_of_hard-coded_credentials', __( 'OWASP — Hard-coded Credentials', 'plugin-auditor' ) ),
	array( __( 'Error suppression', 'plugin-auditor' ), 'https://owasp.org/www-community/Improper_Error_Handling', __( 'OWASP — Improper Error Handling', 'plugin-auditor' ) ),
	array( __( 'Obfuscation', 'plugin-auditor' ), 'https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/07-Input_Validation_Testing/18-Testing_for_Server_Side_Template_Injection', __( 'OWASP — Malicious Code', 'plugin-auditor' ) ),
	array( __( 'Debug output — PHP', 'plugin-auditor' ), 'https://owasp.org/www-community/vulnerabilities/Information_exposure_through_query_strings_in_url', __( 'OWASP — Information Exposure', 'plugin-auditor' ) ),
	array( __( 'Debug output — JS', 'plugin-auditor' ), 'https://owasp.org/www-community/vulnerabilities/Information_exposure_through_query_strings_in_url', __( 'OWASP — Information Exposure', 'plugin-auditor' ) ),
	array( __( 'File permissions', 'plugin-auditor' ), 'https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/02-Configuration_and_Deployment_Management_Testing/09-Test_File_Permission', __( 'OWASP — Insecure File Permissions', 'plugin-auditor' ) ),
	array( __( 'Deprecated functions', 'plugin-auditor' ), 'https://developer.wordpress.org/plugins/security/', __( 'WP Handbook — Plugin Security', 'plugin-auditor' ) ),
	array( __( 'Plugin structure', 'plugin-auditor' ), 'https://developer.wordpress.org/plugins/plugin-basics/best-practices/', __( 'WP Plugin Handbook — Best Practices', 'plugin-auditor' ) ),
	array( __( 'Licensing', 'plugin-auditor' ), 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/', __( 'WordPress.org Plugin Guidelines', 'plugin-auditor' ) ),
	array( __( 'PHP compatibility', 'plugin-auditor' ), 'https://github.com/PHPCompatibility/PHPCompatibilityWP', __( 'PHPCompatibilityWP', 'plugin-auditor' ) ),
	array( __( 'Redirect without exit', 'plugin-auditor' ), 'https://developer.wordpress.org/reference/functions/wp_redirect/', __( 'WP Handbook — wp_redirect()', 'plugin-auditor' ) ),
	array( __( 'Role name in current_user_can()', 'plugin-auditor' ), 'https://developer.wordpress.org/plugins/users/roles-and-capabilities/', __( 'WP Handbook — Roles vs Capabilities', 'plugin-auditor' ) ),
	array( __( 'Shortcode output escaping', 'plugin-auditor' ), 'https://developer.wordpress.org/apis/security/escaping/', __( 'WP Handbook — Escaping', 'plugin-auditor' ) ),
	array( __( 'Option writes without capability', 'plugin-auditor' ), 'https://developer.wordpress.org/apis/security/current-user-can/', __( 'WP Handbook — current_user_can()', 'plugin-auditor' ) ),
	array( __( 'Wrong $wpdb placeholder type', 'plugin-auditor' ), 'https://developer.wordpress.org/reference/classes/wpdb/prepare/', __( 'WP Handbook — $wpdb->prepare()', 'plugin-auditor' ) ),
	array( __( 'Unnecessary closures', 'plugin-auditor' ), 'https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/', __( 'WordPress Coding Standards', 'plugin-auditor' ) ),
);
?>
<div class="wrap">

	<div class="pla-page-banner">
		<img src="<?php echo esc_url( PLUGIN_AUDITOR_URL . 'assets/images/satori-logo.png' ); ?>" alt="" aria-hidden="true" class="pla-page-banner__logo" />
		<h1><?php esc_html_e( 'Satori Plugin Auditor', 'plugin-auditor' ); ?></h1>
	</div>

	<div class="pla-page-columns">

		<div class="pla-col-main">

			<div class="pla-hero-banner">
				<div class="pla-hero-banner__content">
					<h2><?php esc_html_e( '"Every plugin, fully exposed."', 'plugin-auditor' ); ?></h2>
					<p><?php esc_html_e( 'Instant security audits, straight from your Plugins page.', 'plugin-auditor' ); ?></p>
					<ul class="pla-hero-list">
						<li><?php esc_html_e( 'No tools', 'plugin-auditor' ); ?></li>
						<li><?php esc_html_e( 'No terminal', 'plugin-auditor' ); ?></li>
						<li><?php esc_html_e( 'No guesswork', 'plugin-auditor' ); ?></li>
					</ul>
				</div>
				<div class="pla-hero-banner__graphic" aria-hidden="true">
					<svg width="130" height="170" viewBox="0 0 130 170" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="8" y="8" width="108" height="144" rx="8" fill="white" fill-opacity="0.1" stroke="white" stroke-opacity="0.2" stroke-width="1.5"/>
						<rect x="8" y="8" width="108" height="38" rx="8" fill="white" fill-opacity="0.15"/>
						<rect x="8" y="34" width="108" height="12" fill="white" fill-opacity="0.05"/>
						<circle cx="28" cy="27" r="5" fill="white" fill-opacity="0.55"/>
						<circle cx="44" cy="27" r="5" fill="white" fill-opacity="0.4"/>
						<circle cx="60" cy="27" r="5" fill="white" fill-opacity="0.28"/>
						<rect x="24" y="64" width="58" height="6" rx="3" fill="white" fill-opacity="0.55"/>
						<rect x="24" y="79" width="76" height="6" rx="3" fill="white" fill-opacity="0.38"/>
						<rect x="24" y="94" width="44" height="6" rx="3" fill="white" fill-opacity="0.28"/>
						<rect x="24" y="109" width="66" height="6" rx="3" fill="white" fill-opacity="0.38"/>
						<rect x="24" y="124" width="38" height="6" rx="3" fill="white" fill-opacity="0.22"/>
						<path d="M80 106 L104 114 L104 129 C104 140 93 147 80 149 C67 147 56 140 56 129 L56 114 Z" fill="white" fill-opacity="0.92"/>
						<path d="M71 129 L77 135 L91 119" stroke="#0891b2" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>

			<div class="pla-admin-section">
				<h3>
					<?php
					printf(
						/* translators: %d: number of installed plugins */
						esc_html__( 'Installed Plugins (%d)', 'plugin-auditor' ),
						count( $installed_plugins )
					);
					?>
				</h3>
				<?php if ( empty( $installed_plugins ) ) : ?>
					<p><?php esc_html_e( 'No plugins found.', 'plugin-auditor' ); ?></p>
				<?php else : ?>
					<div class="pla-plugin-grid">
						<?php foreach ( $installed_plugins as $plugin_file => $plugin_data ) : ?>
							<?php
							$palette         = $avatar_palettes[ abs( crc32( $plugin_data['Name'] ) ) % count( $avatar_palettes ) ];
							$words           = preg_split( '/\s+/', $plugin_data['Name'], -1, PREG_SPLIT_NO_EMPTY );
							$words           = false !== $words ? $words : array( $plugin_data['Name'] );
							$avatar_initials = '';
							foreach ( $words as $word ) {
								$avatar_initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
								if ( mb_strlen( $avatar_initials ) >= 3 ) {
									break;
								}
							}
							$card_report = $report_map[ $plugin_file ] ?? null;
							$card_nonce  = wp_create_nonce( 'pla_audit_' . $plugin_file );
							?>
							<div class="pla-plugin-card">
								<div class="pla-plugin-card__top">
									<div class="pla-plugin-avatar" style="background:<?php echo esc_attr( $palette[0] ); ?>;color:<?php echo esc_attr( $palette[1] ); ?>">
										<?php echo esc_html( $avatar_initials ); ?>
									</div>
									<div class="pla-plugin-card__meta">
										<div class="pla-plugin-card__name"><?php echo esc_html( $plugin_data['Name'] ); ?></div>
										<div class="pla-plugin-card__version">v<?php echo esc_html( $plugin_data['Version'] ); ?></div>
									</div>
								</div>
								<div class="pla-plugin-card__bottom">
									<?php if ( null !== $card_report ) : ?>
										<?php
										$card_risk  = strtolower( (string) get_post_meta( $card_report->ID, '_pla_risk', true ) );
										$card_score = (string) get_post_meta( $card_report->ID, '_pla_score', true );
										?>
										<span class="pla-score-badge pla-score-badge--<?php echo esc_attr( $card_risk ); ?>">
											<span class="pla-score-badge__dot"></span>
											<?php /* translators: %s: numeric risk score */ ?>
										<?php echo esc_html( sprintf( __( 'Score %s', 'plugin-auditor' ), $card_score ) ); ?>
										</span>
									<?php else : ?>
										<span></span>
									<?php endif; ?>
									<button
										type="button"
										class="pla-audit-card-btn pla-audit-trigger"
										data-plugin="<?php echo esc_attr( $plugin_file ); ?>"
										data-nonce="<?php echo esc_attr( $card_nonce ); ?>"
									><?php esc_html_e( 'Audit', 'plugin-auditor' ); ?></button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="pla-admin-section pla-admin-section--flush">
				<h3><?php esc_html_e( 'Audit Reports', 'plugin-auditor' ); ?></h3>

				<?php if ( empty( $reports ) ) : ?>
					<p><?php esc_html_e( 'No audit reports yet. Click "Audit" on any plugin to begin.', 'plugin-auditor' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped pla-reports-table">
						<thead>
							<tr>
								<td class="pla-col-cb manage-column column-cb check-column">
									<input type="checkbox" id="pla-select-all" />
								</td>
								<th class="pla-col-plugin">
									<span class="pla-th-inner">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="14" y="7" width="4" height="3" rx="1" stroke="currentColor" stroke-width="1.5" fill="white"/><rect x="14" y="13" width="4" height="3" rx="1" stroke="currentColor" stroke-width="1.5" fill="white"/><rect x="7" y="14" width="3" height="4" rx="1" stroke="currentColor" stroke-width="1.5" fill="white"/></svg>
										<?php esc_html_e( 'Plugin', 'plugin-auditor' ); ?>
									</span>
								</th>
								<th class="pla-col-risk">
									<span class="pla-th-inner">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2 L22 6 L22 13 C22 18 17 22 12 23 C7 22 2 18 2 13 L2 6 Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="8" x2="12" y2="14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
										<?php esc_html_e( 'Risk', 'plugin-auditor' ); ?>
									</span>
								</th>
								<th class="pla-col-count pla-col-high"><?php esc_html_e( 'High', 'plugin-auditor' ); ?></th>
								<th class="pla-col-count pla-col-medium"><?php esc_html_e( 'Medium', 'plugin-auditor' ); ?></th>
								<th class="pla-col-count pla-col-low"><?php esc_html_e( 'Low', 'plugin-auditor' ); ?></th>
								<th class="pla-col-date">
									<span class="pla-th-inner">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="1.5"/><line x1="8" y1="3" x2="8" y2="7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="16" y1="3" x2="16" y2="7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="7" y="13" width="3" height="3" rx="0.5" fill="currentColor" opacity="0.6"/><rect x="11" y="13" width="3" height="3" rx="0.5" fill="currentColor" opacity="0.6"/><rect x="15" y="13" width="3" height="3" rx="0.5" fill="currentColor" opacity="0.6"/></svg>
										<?php esc_html_e( 'Date', 'plugin-auditor' ); ?>
									</span>
								</th>
								<th class="pla-col-actions">
									<span class="pla-th-inner">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="16" cy="12" r="1.5" fill="currentColor"/></svg>
										<?php esc_html_e( 'Actions', 'plugin-auditor' ); ?>
									</span>
								</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $reports as $report ) : ?>
								<?php
								$plugin_name    = (string) get_post_meta( $report->ID, '_pla_plugin_name', true );
								$risk           = (string) get_post_meta( $report->ID, '_pla_risk', true );
								$raw_counts     = get_post_meta( $report->ID, '_pla_counts', true );
								$counts         = is_array( $raw_counts ) ? $raw_counts : array(
									'high'   => '—',
									'medium' => '—',
									'low'    => '—',
								);
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

		</div>

		<div class="pla-col-sidebar">
			<h2><?php esc_html_e( 'What the report includes', 'plugin-auditor' ); ?></h2>
			<p class="pla-sidebar-intro"><?php esc_html_e( '22 checks covering the security and code quality issues most likely to put a WordPress site at risk, drawn from OWASP, the WordPress Developer Handbook, and WordPress Coding Standards.', 'plugin-auditor' ); ?></p>
			<table class="pla-checks-table">
				<thead>
					<tr>
						<th class="pla-check-num">#</th>
						<th><?php esc_html_e( 'Check', 'plugin-auditor' ); ?></th>
						<th><?php esc_html_e( 'Official Source', 'plugin-auditor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $checks as $i => $check ) : ?>
						<tr>
							<td class="pla-check-num"><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
							<td><?php echo esc_html( $check[0] ); ?></td>
							<td>
								<a href="<?php echo esc_url( $check[1] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $check[2] ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	</div>

</div>
