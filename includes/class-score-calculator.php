<?php
/**
 * Risk score and rating calculation.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates risk scores and ratings from scan findings.
 */
class ScoreCalculator {

	/**
	 * Severity point weights.
	 */
	private const SEVERITY_WEIGHTS = array(
		'CRITICAL' => 25,
		'HIGH'     => 10,
		'MEDIUM'   => 4,
		'LOW'      => 1,
		'INFO'     => 0,
	);

	/**
	 * Returns section keys whose findings contribute to the risk score.
	 *
	 * @return string[]
	 */
	public static function security_sections(): array {
		return array(
			'dangerous',
			'output',
			'input',
			'nonces',
			'capabilities',
			'database',
			'credentials',
			'errors',
			'obfuscation',
			'permissions',
			'debug_output',
			'redirects',
			'role_checks',
			'shortcodes',
			'option_writes',
			'wpdb_placeholders',
		);
	}

	/**
	 * Returns section keys whose findings do not contribute to the risk score.
	 *
	 * @return string[]
	 */
	public static function code_quality_sections(): array {
		return array(
			'requests',
			'meta',
			'assets',
			'deprecated',
			'structure',
			'licensing',
			'php_compat',
			'commented_code',
			'direct_access',
			'duplicate_hooks',
			'unnecessary_closures',
			'early_translations',
		);
	}

	/**
	 * Calculates a numeric risk score from security section findings.
	 *
	 * @param array<string, mixed> $findings Section findings keyed by section name.
	 */
	public static function score( array $findings ): int {
		$score = 0;
		foreach ( self::security_sections() as $section ) {
			if ( ! isset( $findings[ $section ] ) || ! is_array( $findings[ $section ] ) ) {
				continue;
			}
			foreach ( $findings[ $section ] as $finding ) {
				$score += self::SEVERITY_WEIGHTS[ $finding['severity'] ] ?? 0;
			}
		}
		return $score;
	}

	/**
	 * Converts a numeric score to a risk rating string.
	 *
	 * @param int $score Numeric score.
	 */
	public static function rating( int $score ): string {
		if ( $score >= 50 ) {
			return 'CRITICAL';
		}
		if ( $score >= 20 ) {
			return 'HIGH';
		}
		if ( $score >= 8 ) {
			return 'MEDIUM';
		}
		if ( $score >= 1 ) {
			return 'LOW';
		}
		return 'CLEAN';
	}
}
