<?php
/**
 * Report HTML template.
 *
 * Variables available from Report::render():
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
	</header>

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

			<?php if ( empty( $section_findings ) ) : ?>
				<p class="pla-report__pass-note"><?php esc_html_e( 'No issues found.', 'plugin-auditor' ); ?></p>

			<?php else : ?>

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
							<td class="pla-report__line"><?php echo $finding['line'] ? esc_html( (string) $finding['line'] ) : '—'; ?></td>
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
								<td class="pla-report__line"><?php echo $finding['line'] ? esc_html( (string) $finding['line'] ) : '—'; ?></td>
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
