<?php
/**
 * Unit tests for Report.
 *
 * @package PluginAuditor\Tests\Unit
 */

declare( strict_types=1 );

namespace PluginAuditor\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PluginAuditor\Report;

/**
 * Tests report storage helpers.
 */
class ReportTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_section_labels_returns_all_expected_keys(): void {
		$labels = Report::section_labels();
		$expected = array(
			'dangerous',
			'output',
			'input',
			'nonces',
			'capabilities',
			'database',
			'credentials',
			'requests',
			'permissions',
			'meta',
			'assets',
			'errors',
			'obfuscation',
		);
		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $labels );
		}
	}

	public function test_section_labels_values_are_non_empty_strings(): void {
		foreach ( Report::section_labels() as $label ) {
			$this->assertIsString( $label );
			$this->assertNotEmpty( $label );
		}
	}
}
