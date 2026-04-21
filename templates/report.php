<?php
/**
 * Report HTML template.
 *
 * Variables available from ReportRenderer::render():
 *   $findings    array   All findings keyed by section.
 *   $plugin_name string  Audited plugin display name.
 *   $rating      string  Overall risk rating.
 *   $score       int     Numeric risk score.
 *   $report_id   int     Post ID.
 *   $post        WP_Post Report post object.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$section_labels = \PluginAuditor\Report::section_labels();

$rating_class = 'pla-rating--' . strtolower( esc_attr( $rating ) );

$severity_counts = array( 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0 );
foreach ( $section_labels as $section => $label ) {
	if ( ! isset( $findings[ $section ] ) || ! is_array( $findings[ $section ] ) ) {
		continue;
	}
	foreach ( $findings[ $section ] as $finding ) {
		$sev = strtolower( $finding['severity'] ?? '' );
		if ( isset( $severity_counts[ $sev ] ) ) {
			++$severity_counts[ $sev ];
		}
	}
}
?>
<article class="pla-report">

	<header class="pla-report__header">
		<h3 id="pla-modal-title" class="pla-report__plugin-name">
			<?php echo esc_html( $plugin_name ); ?>
		</h3>
		<div class="pla-report__meta">
			<span class="pla-report__date">
				<?php
				/* translators: %s: audit datetime */
				printf( esc_html__( 'Audited: %s', 'plugin-auditor' ), esc_html( get_the_date( 'Y-m-d H:i', $post ) ) );
				?>
			</span>
		</div>
		<div class="pla-report__risk <?php echo esc_attr( $rating_class ); ?>">
			<span class="pla-report__risk-label"><?php esc_html_e( 'Risk:', 'plugin-auditor' ); ?></span>
			<strong class="pla-report__risk-value"><?php echo esc_html( $rating ); ?></strong>
			<span class="pla-report__risk-score">
				<?php
				/* translators: %d: numeric score */
				printf( esc_html__( '(Score: %d)', 'plugin-auditor' ), (int) $score );
				?>
			</span>
		</div>

		<?php
		$c = $severity_counts['critical'];
		$h = $severity_counts['high'];
		$m = $severity_counts['medium'];
		$l = $severity_counts['low'];

		if ( 'CLEAN' === $rating ) {
			$summary_text = __( 'No significant issues found. This plugin passed all security and code quality checks — no action required.', 'plugin-auditor' );
		} elseif ( 'LOW' === $rating ) {
			$summary_text = sprintf(
				/* translators: 1: low count */
				__( 'This plugin is largely clean with %1$d minor issue(s) noted. No critical or high severity problems were found. Review the low severity findings at your convenience.', 'plugin-auditor' ),
				$l
			);
		} elseif ( 'MEDIUM' === $rating ) {
			$parts = array();
			if ( $h > 0 ) {
				/* translators: %d: count */
				$parts[] = sprintf( __( '%d high', 'plugin-auditor' ), $h );
			}
			if ( $m > 0 ) {
				/* translators: %d: count */
				$parts[] = sprintf( __( '%d medium', 'plugin-auditor' ), $m );
			}
			$summary_text = sprintf(
				/* translators: 1: issue list e.g. "2 high and 3 medium" */
				__( 'This plugin has %1$s severity issue(s) that should be addressed. No critical vulnerabilities were found, but the items below warrant attention before deploying to production.', 'plugin-auditor' ),
				implode( __( ' and ', 'plugin-auditor' ), $parts )
			);
		} elseif ( 'HIGH' === $rating ) {
			$parts = array();
			if ( $c > 0 ) {
				/* translators: %d: count */
				$parts[] = sprintf( __( '%d critical', 'plugin-auditor' ), $c );
			}
			if ( $h > 0 ) {
				/* translators: %d: count */
				$parts[] = sprintf( __( '%d high', 'plugin-auditor' ), $h );
			}
			$summary_text = sprintf(
				/* translators: 1: issue list */
				__( 'This plugin has %1$s severity issue(s) that require prompt attention. Address the high severity findings as a priority before this plugin is used in a production environment.', 'plugin-auditor' ),
				implode( __( ' and ', 'plugin-auditor' ), $parts )
			);
		} else {
			$summary_text = sprintf(
				/* translators: 1: critical count, 2: high count, 3: score */
				__( 'This plugin has %1$d critical and %2$d high severity issue(s) that require immediate action. With a risk score of %3$d, this plugin should not be used in production until the critical and high findings below have been resolved.', 'plugin-auditor' ),
				$c,
				$h,
				(int) $score
			);
		}
		?>
		<p class="pla-report__summary-text pla-report__summary-text--<?php echo esc_attr( strtolower( $rating ) ); ?>">
			<?php echo esc_html( $summary_text ); ?>
		</p>
	</header>

	<div class="pla-report__summary">
		<table class="pla-summary-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Severity', 'plugin-auditor' ); ?></th>
					<th><?php esc_html_e( 'Count', 'plugin-auditor' ); ?></th>
					<th><?php esc_html_e( 'Guidance', 'plugin-auditor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$summary_items = array(
					'critical' => array(
						'label' => __( 'Critical', 'plugin-auditor' ),
						'note'  => __( 'Fix immediately', 'plugin-auditor' ),
						'count' => $severity_counts['critical'],
					),
					'high'     => array(
						'label' => __( 'High', 'plugin-auditor' ),
						'note'  => __( 'Address urgently', 'plugin-auditor' ),
						'count' => $severity_counts['high'],
					),
					'medium'   => array(
						'label' => __( 'Medium', 'plugin-auditor' ),
						'note'  => __( 'Address when possible', 'plugin-auditor' ),
						'count' => $severity_counts['medium'],
					),
					'low'      => array(
						'label' => __( 'Low', 'plugin-auditor' ),
						'note'  => __( 'Review and consider', 'plugin-auditor' ),
						'count' => $severity_counts['low'],
					),
					'info'     => array(
						'label' => __( 'Info', 'plugin-auditor' ),
						'note'  => __( 'Informational only', 'plugin-auditor' ),
						'count' => $severity_counts['info'],
					),
				);
				foreach ( $summary_items as $sev => $item ) :
				?>
				<tr>
					<td>
						<span class="pla-severity pla-severity--<?php echo esc_attr( $sev ); ?>">
							<?php echo esc_html( $item['label'] ); ?>
						</span>
					</td>
					<td class="pla-summary-count"><?php echo esc_html( (string) $item['count'] ); ?></td>
					<td class="pla-summary-note"><?php echo esc_html( $item['note'] ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php foreach ( $section_labels as $section => $label ) : ?>

		<?php
		$section_findings = $findings[ $section ] ?? array();
		$issues           = array_filter(
			$section_findings,
			static fn( $f ) => 'INFO' !== $f['severity']
		);
		$info             = array_filter(
			$section_findings,
			static fn( $f ) => 'INFO' === $f['severity']
		);
		$has_issues       = ! empty( $issues );
		?>

		<section class="pla-report__section <?php echo $has_issues ? 'pla-report__section--issues' : 'pla-report__section--pass'; ?>">

			<h4 class="pla-report__section-title">
				<?php echo esc_html( $label ); ?>
				<?php if ( ! $has_issues ) : ?>
					<span class="pla-report__pass-badge"><?php esc_html_e( 'PASS', 'plugin-auditor' ); ?></span>
				<?php endif; ?>
			</h4>

			<?php if ( ! empty( $section_findings ) ) : ?>

				<?php if ( $has_issues ) : ?>
				<table class="pla-report__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Severity', 'plugin-auditor' ); ?></th>
							<th><?php esc_html_e( 'File', 'plugin-auditor' ); ?></th>
							<th><?php esc_html_e( 'Line', 'plugin-auditor' ); ?></th>
							<th><?php esc_html_e( 'Message', 'plugin-auditor' ); ?></th>
							<th><?php esc_html_e( 'Snippet', 'plugin-auditor' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $issues as $finding ) : ?>
						<tr class="pla-report__row pla-report__row--<?php echo esc_attr( strtolower( $finding['severity'] ) ); ?>">
							<td>
								<span class="pla-severity pla-severity--<?php echo esc_attr( strtolower( $finding['severity'] ) ); ?>">
									<?php echo esc_html( $finding['severity'] ); ?>
								</span>
							</td>
							<td class="pla-report__file"><?php echo esc_html( $finding['file'] ); ?></td>
							<td class="pla-report__line"><?php echo $finding['line'] ? esc_html( (string) $finding['line'] ) : ''; ?></td>
							<td class="pla-report__message"><?php echo esc_html( $finding['message'] ); ?></td>
							<td class="pla-report__snippet">
								<?php if ( $finding['snippet'] ) : ?>
									<code><?php echo esc_html( $finding['snippet'] ); ?></code>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>

				<?php if ( ! empty( $info ) ) : ?>
				<details class="pla-report__info-details">
					<summary><?php esc_html_e( 'Informational findings', 'plugin-auditor' ); ?></summary>
					<table class="pla-report__table pla-report__table--info">
						<tbody>
						<?php foreach ( $info as $finding ) : ?>
							<tr>
								<td class="pla-report__file"><?php echo esc_html( $finding['file'] ); ?></td>
								<td class="pla-report__line"><?php echo $finding['line'] ? esc_html( (string) $finding['line'] ) : ''; ?></td>
								<td class="pla-report__message"><?php echo esc_html( $finding['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</details>
				<?php endif; ?>

			<?php endif; ?>

		</section>

	<?php endforeach; ?>

</article>
