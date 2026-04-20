<?php
/**
 * Report HTML rendering.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Renders audit report HTML from stored findings.
 */
class ReportRenderer {

	/**
	 * @param Report $report Report storage instance.
	 */
	public function __construct( private Report $report ) {}

	/**
	 * Renders the full HTML report for a given report post ID.
	 *
	 * @param int $report_id Post ID.
	 * @return string Rendered HTML, or empty string if the report does not exist.
	 */
	public function render( int $report_id ): string {
		$post = get_post( $report_id );
		if ( ! $post ) {
			return '';
		}

		$findings    = $this->report->load( $report_id );
		$plugin_name = (string) get_post_meta( $report_id, '_pla_plugin_name', true );
		$rating      = $findings['rating'];
		$score       = $findings['score'];

		ob_start();

		include PLUGIN_AUDITOR_DIR . 'templates/report.php';

		return (string) ob_get_clean();
	}
}
