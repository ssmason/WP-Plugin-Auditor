<?php
/**
 * Admin reports page template.
 *
 * Variables available from Admin::render_reports_page():
 *   $reports  \WP_Post[]                                         All report posts.
 *   $grouped  array<string, array<string, array{label, default}>> Checks grouped by category.
 *   $enabled  string[]                                            Currently enabled check slugs.
 *   $saved    bool                                               True if settings were just saved.
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

$sources = array(
	'dangerous_functions'  => 'https://owasp.org/www-community/attacks/Code_Injection',
	'output_escaping'      => 'https://developer.wordpress.org/apis/security/escaping/',
	'input_sanitization'   => 'https://developer.wordpress.org/apis/security/sanitizing/',
	'nonce_verification'   => 'https://developer.wordpress.org/apis/security/nonces/',
	'capability_checks'    => 'https://developer.wordpress.org/apis/security/current-user-can/',
	'credentials'          => 'https://owasp.org/www-community/vulnerabilities/Use_of_hard-coded_credentials',
	'obfuscation'          => 'https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/07-Input_Validation_Testing/18-Testing_for_Server_Side_Template_Injection',
	'redirects'            => 'https://developer.wordpress.org/reference/functions/wp_redirect/',
	'shortcodes'           => 'https://developer.wordpress.org/apis/security/escaping/',
	'call_user_func'       => 'https://owasp.org/www-community/attacks/Code_Injection',
	'base64'               => 'https://owasp.org/www-community/attacks/Code_Injection',
	'nonces_post'          => 'https://developer.wordpress.org/apis/security/nonces/',
	'database'             => 'https://developer.wordpress.org/apis/security/sql-injection/',
	'wpdb_placeholders'    => 'https://developer.wordpress.org/reference/classes/wpdb/prepare/',
	'option_writes'        => 'https://developer.wordpress.org/apis/security/current-user-can/',
	'error_suppression'    => 'https://owasp.org/www-community/Improper_Error_Handling',
	'debug_php'            => 'https://owasp.org/www-community/vulnerabilities/Information_exposure_through_query_strings_in_url',
	'debug_js'             => 'https://owasp.org/www-community/vulnerabilities/Information_exposure_through_query_strings_in_url',
	'debug_js_warn'        => 'https://owasp.org/www-community/vulnerabilities/Information_exposure_through_query_strings_in_url',
	'commented_code'       => 'https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/',
	'unnecessary_closures' => 'https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/',
	'early_translations'   => 'https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/',
	'duplicate_hooks'      => 'https://developer.wordpress.org/reference/functions/add_action/',
	'php_compat'           => 'https://github.com/PHPCompatibility/PHPCompatibilityWP',
	'deprecated'           => 'https://developer.wordpress.org/plugins/security/',
	'plugin_structure'     => 'https://developer.wordpress.org/plugins/plugin-basics/best-practices/',
	'file_permissions'     => 'https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/02-Configuration_and_Deployment_Management_Testing/09-Test_File_Permission',
	'licensing'            => 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/',
	'meta'                 => 'https://developer.wordpress.org/plugins/plugin-basics/header-requirements/',
	'readme'               => 'https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/',
	'assets'               => 'https://developer.wordpress.org/reference/functions/wp_enqueue_script/',
	'role_checks'          => 'https://developer.wordpress.org/plugins/users/roles-and-capabilities/',
	'requests'             => 'https://developer.wordpress.org/plugins/http-api/',
);

$descriptions = array(
	'dangerous_functions'  => 'Flags use of functions like eval, exec, shell_exec, system, passthru which allow arbitrary code execution on the server.',
	'output_escaping'      => 'Flags output sent to the browser without escaping functions like esc_html, esc_attr, esc_url. Unescaped output is a direct XSS vector.',
	'input_sanitization'   => 'Flags superglobals $_POST, $_GET, $_REQUEST, $_SERVER accessed without sanitization functions like sanitize_text_field, absint.',
	'nonce_verification'   => 'Flags form submissions and AJAX handlers that do not verify a nonce via wp_verify_nonce or check_admin_referer. Prevents CSRF attacks.',
	'capability_checks'    => 'Flags privileged operations performed without a preceding current_user_can() check. Prevents privilege escalation.',
	'credentials'          => 'Flags API keys, passwords, tokens and secrets hardcoded directly in source files. These get exposed when code is committed to version control.',
	'obfuscation'          => 'Flags patterns associated with malware such as variable-variable callables and preg_replace with the /e modifier which executes matched content as PHP.',
	'redirects'            => 'Flags calls to wp_redirect or wp_safe_redirect not followed by exit or die. Without exit, PHP continues executing after the redirect header is sent.',
	'shortcodes'           => 'Flags shortcode callbacks that return unescaped output. Shortcodes render directly into post content making them a direct XSS vector.',
	'call_user_func'       => 'Flags dynamic function calls where the callable argument is a variable. Dangerous when the callable can be influenced by user input.',
	'base64'               => 'Flags base64 functions when combined with eval or assigned to a variable for later execution. A common pattern in injected malware.',
	'nonces_post'          => 'Flags files that access $_POST anywhere in the file without any nonce verification present in the same file.',
	'database'             => 'Flags raw SQL queries passed to $wpdb methods without going through $wpdb->prepare(). Direct SQL injection risk.',
	'wpdb_placeholders'    => 'Flags mismatched placeholder types in $wpdb->prepare() such as using %s for an integer or %d for a string. Can cause type coercion vulnerabilities.',
	'option_writes'        => 'Flags update_option and delete_option calls without a preceding current_user_can() check in scope. Any authenticated user could modify site options.',
	'error_suppression'    => 'Flags error_reporting(0) and the @ operator. Suppressing errors hides malicious activity and makes forensic investigation impossible.',
	'debug_php'            => 'Flags var_dump, print_r, var_export left in production code. These expose internal data structures and variable contents to any visitor.',
	'debug_js'             => 'Flags console.log, console.debug, console.info in production JS files. Exposes application state to anyone with browser developer tools open.',
	'commented_code'       => 'Flags large blocks of commented-out code. Dead code in production can contain outdated security logic or credentials.',
	'unnecessary_closures' => 'Flags anonymous functions passed to add_action and add_filter that could be named functions. Closures registered as hooks cannot be removed with remove_action or remove_filter.',
	'early_translations'   => 'Flags __() and _e() calls made before the init hook fires. Translation strings loaded too early may not be localised correctly.',
	'duplicate_hooks'      => 'Flags the same hook registered more than once across files. Can cause functions to fire multiple times producing duplicate output or unexpected behaviour.',
	'debug_js_warn'        => 'Flags console.warn and console.error in production JS files. These expose internal error states and application logic to end users.',
	'php_compat'           => 'Flags PHP 8.x syntax used in plugins that declare a lower minimum PHP version. Code using named arguments or match expressions on PHP 7.4 will fatal error.',
	'deprecated'           => 'Flags WordPress functions marked as deprecated. These may be removed in future WordPress versions and often have known issues that led to their deprecation.',
	'plugin_structure'     => 'Flags plugin subdirectories missing an index.php file. Without it the web server may display a directory listing exposing the plugin file structure.',
	'file_permissions'     => 'Flags PHP files with the execute bit set and world-writable files. Unnecessary execute permissions allow files to be run directly by the server.',
	'licensing'            => 'Flags plugins missing a GPL-compatible license declaration. WordPress.org requires GPL v2 or later compatibility for all distributed plugins.',
	'meta'                 => 'Flags missing or incomplete plugin header fields such as Plugin Name, Description, Author, Version. Incomplete headers can cause WordPress to fail to load the plugin.',
	'readme'               => 'Flags a missing readme.txt in the plugin root. Required for WordPress.org distribution and contains important information including tested versions and changelog.',
	'assets'               => 'Flags hardcoded version strings on enqueued scripts and styles. The version should reference the plugin version constant so caches are busted on update.',
	'role_checks'          => 'Flags role names such as administrator or editor passed to current_user_can(). Roles are not capabilities — they can be renamed or removed. Always pass a capability name.',
	'requests'             => 'Flags wp_remote_post() and wp_remote_get() calls. Outbound requests can leak data, introduce latency and represent a dependency on external services.',
);
?>
<div class="wrap">

	<div class="pla-page-banner">
		<img src="<?php echo esc_url( PLUGIN_AUDITOR_URL . 'assets/images/satori-logo.png' ); ?>" alt="" aria-hidden="true" class="pla-page-banner__logo" />
		<h1><?php esc_html_e( 'Satori Plugin Auditor', 'plugin-auditor' ); ?></h1>
	</div>

	<div class="pla-hero-banner" style="background-image:url('<?php echo esc_url( PLUGIN_AUDITOR_URL . 'assets/images/plugin-cover.png' ); ?>')">
		<h2><?php esc_html_e( '"Every plugin, fully exposed."', 'plugin-auditor' ); ?></h2>
		<p><?php esc_html_e( 'Instant security audits, straight from your Plugins page.', 'plugin-auditor' ); ?></p>
		<ul class="pla-hero-list">
			<li><?php esc_html_e( 'No tools', 'plugin-auditor' ); ?></li>
			<li><?php esc_html_e( 'No terminal', 'plugin-auditor' ); ?></li>
			<li><?php esc_html_e( 'No guesswork', 'plugin-auditor' ); ?></li>
		</ul>
	</div>

	<nav class="pla-tabs" aria-label="<?php esc_attr_e( 'Plugin Auditor sections', 'plugin-auditor' ); ?>">
		<a href="#" class="pla-tab pla-tab--active" data-tab="reports"><?php esc_html_e( 'Reports', 'plugin-auditor' ); ?></a>
		<a href="#" class="pla-tab" data-tab="test-info"><?php esc_html_e( 'Test Info', 'plugin-auditor' ); ?></a>
	</nav>

	<div id="pla-panel-reports" class="pla-panel">
	<div class="pla-page-columns">

		<div class="pla-col-main">

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
									<td><?php echo esc_html( (string) get_the_date( 'd-m-Y H:i', $report ) ); ?></td>
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
										><?php esc_html_e( 'Download', 'plugin-auditor' ); ?></button>
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
			<p class="pla-sidebar-intro"><?php esc_html_e( 'A detailed security audit across 33 checks covering known WordPress vulnerability patterns, coding standards, and plugin best practices. Each check is mapped to an official source — WordPress Developer Handbook, OWASP, or PHPCompatibility. Findings are grouped by severity, scored, and graded so you know exactly what needs fixing and how urgently.', 'plugin-auditor' ); ?></p>

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
						<button type="button" class="pla-accordion-toggle" aria-expanded="false">
							<span class="pla-accordion-title"><?php echo esc_html( $category ); ?></span>
							<span class="pla-accordion-icon" aria-hidden="true"></span>
						</button>
						<div class="pla-accordion-body" hidden>
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
											<?php if ( isset( $sources[ $slug ] ) ) : ?>
												<a href="<?php echo esc_url( $sources[ $slug ] ); ?>" target="_blank" rel="noopener noreferrer" class="pla-source-link"><?php esc_html_e( 'source', 'plugin-auditor' ); ?></a>
											<?php endif; ?>
										</td>
										<td class="pla-check-default">
											<?php if ( $check['default'] ) : ?>
												<span class="pla-badge pla-badge--on"><?php esc_html_e( 'ON', 'plugin-auditor' ); ?></span>
											<?php else : ?>
												<span class="pla-badge pla-badge--off"><?php esc_html_e( 'OFF', 'plugin-auditor' ); ?></span>
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
						<?php esc_html_e( 'Save', 'plugin-auditor' ); ?>
					</button>
					<button type="button" class="button pla-reset-defaults">
						<?php esc_html_e( 'Reset to Defaults', 'plugin-auditor' ); ?>
					</button>
				</div>

			</form>
		</div>

	</div>
	</div><!-- /#pla-panel-reports -->

	<div id="pla-panel-test-info" class="pla-panel" hidden>
		<div class="pla-page-columns">

			<div class="pla-col-main">
				<div class="pla-admin-section">
					<h3><?php esc_html_e( 'Test Reference', 'plugin-auditor' ); ?></h3>
					<table class="pla-info-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Test', 'plugin-auditor' ); ?></th>
								<th class="pla-info-col-fit"><?php esc_html_e( 'Category', 'plugin-auditor' ); ?></th>
								<th><?php esc_html_e( 'Description', 'plugin-auditor' ); ?></th>
								<th class="pla-info-col-fit"><?php esc_html_e( 'Source', 'plugin-auditor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $grouped as $category => $checks ) : ?>
								<?php foreach ( $checks as $slug => $check ) : ?>
									<tr>
										<td><?php echo esc_html( $check['label'] ); ?></td>
										<td class="pla-info-col-fit"><?php echo esc_html( $category ); ?></td>
										<td><?php echo esc_html( $descriptions[ $slug ] ?? '' ); ?></td>
										<td class="pla-info-col-fit">
											<?php if ( isset( $sources[ $slug ] ) ) : ?>
												<a href="<?php echo esc_url( $sources[ $slug ] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'source', 'plugin-auditor' ); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="pla-col-sidebar">
				<h2><?php esc_html_e( 'Test Reference & Standards', 'plugin-auditor' ); ?></h2>
				<p class="pla-sidebar-intro"><?php esc_html_e( 'The Tests Reference page documents every check the auditor runs against your plugin. Each test is listed with its category, a plain-English description of what it detects and why it matters, and a link to the official source that defines the standard — whether that is the WordPress Developer Handbook, OWASP, or PHPCompatibility. Nothing in this auditor is arbitrary; every check maps to a documented vulnerability pattern or coding standard.', 'plugin-auditor' ); ?></p>
				<p class="pla-sidebar-intro"><?php esc_html_e( 'Use this page as a reference when reviewing audit findings or deciding which optional tests to enable. If a finding appears in your report and you are unsure why it was flagged, the description here will tell you exactly what the check looks for and the source link will give you the full context needed to understand and resolve it.', 'plugin-auditor' ); ?></p>

				
			</div>

		</div>
	</div><!-- /#pla-panel-test-info -->

</div>
