<?php
/**
 * Database queries for pla_report posts.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all database queries for pla_report CPT posts.
 */
class ReportRepository {

	/**
	 * Maximum reports retained per plugin.
	 */
	private const CAP = 20;

	/**
	 * Returns all reports ordered newest first.
	 *
	 * @param int $limit Maximum number of reports to return.
	 * @return \WP_Post[]
	 */
	public function all( int $limit = 50 ): array {
		return get_posts(
			array(
				'post_type'      => 'pla_report',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Returns reports for a specific plugin file, ordered newest first.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 * @param int    $limit       Maximum number of reports to return.
	 * @return \WP_Post[]
	 */
	public function for_plugin( string $plugin_file, int $limit = 20 ): array {
		return get_posts(
			array(
				'post_type'      => 'pla_report',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_pla_plugin_file',
						'value' => $plugin_file,
					),
				),
			)
		);
	}

	/**
	 * Returns true if the post exists and is a pla_report.
	 *
	 * @param int $report_id Post ID.
	 */
	public function exists( int $report_id ): bool {
		$post = get_post( $report_id );
		return $post instanceof \WP_Post && 'pla_report' === $post->post_type;
	}

	/**
	 * Deletes a single report permanently.
	 *
	 * @param int $report_id Post ID.
	 */
	public function delete( int $report_id ): void {
		wp_delete_post( $report_id, true );
	}

	/**
	 * Deletes oldest reports for a plugin once the cap is reached.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 */
	public function prune( string $plugin_file ): void {
		$ids = get_posts(
			array(
				'post_type'      => 'pla_report',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_pla_plugin_file',
						'value' => $plugin_file,
					),
				),
			)
		);

		if ( count( $ids ) < self::CAP ) {
			return;
		}

		$excess = array_slice( $ids, 0, count( $ids ) - ( self::CAP - 1 ) );
		foreach ( $excess as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}
}
