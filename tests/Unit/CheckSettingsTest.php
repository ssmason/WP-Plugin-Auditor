<?php
/**
 * Unit tests for CheckSettings.
 *
 * @package PluginAuditor\Tests\Unit
 */

declare( strict_types=1 );

namespace PluginAuditor\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PluginAuditor\CheckSettings;

/**
 * Tests for CheckSettings slug management.
 */
class CheckSettingsTest extends TestCase {

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

	// -------------------------------------------------------------------------
	// all_slugs
	// -------------------------------------------------------------------------

	public function test_all_slugs_returns_33_entries(): void {
		$this->assertCount( 33, CheckSettings::all_slugs() );
	}

	public function test_all_slugs_contains_known_slug(): void {
		$this->assertContains( 'dangerous_functions', CheckSettings::all_slugs() );
		$this->assertContains( 'call_user_func', CheckSettings::all_slugs() );
		$this->assertContains( 'requests', CheckSettings::all_slugs() );
	}

	// -------------------------------------------------------------------------
	// defaults
	// -------------------------------------------------------------------------

	public function test_defaults_returns_20_entries(): void {
		$this->assertCount( 20, CheckSettings::defaults() );
	}

	public function test_defaults_contains_required_on_slugs(): void {
		$defaults = CheckSettings::defaults();
		foreach (
			array(
				'dangerous_functions',
				'output_escaping',
				'input_sanitization',
				'nonce_verification',
				'capability_checks',
				'credentials',
				'obfuscation',
				'redirects',
				'shortcodes',
				'database',
				'wpdb_placeholders',
				'option_writes',
				'error_suppression',
				'debug_php',
				'debug_js',
				'php_compat',
				'deprecated',
				'plugin_structure',
				'file_permissions',
				'role_checks',
			) as $slug
		) {
			$this->assertContains( $slug, $defaults, "Expected '{$slug}' to be in defaults." );
		}
	}

	public function test_defaults_excludes_off_slugs(): void {
		$defaults = CheckSettings::defaults();
		foreach ( array( 'call_user_func', 'base64', 'nonces_post', 'commented_code', 'unnecessary_closures', 'early_translations', 'duplicate_hooks', 'debug_js_warn', 'licensing', 'meta', 'readme', 'assets', 'requests' ) as $slug ) {
			$this->assertNotContains( $slug, $defaults, "Expected '{$slug}' NOT to be in defaults." );
		}
	}

	// -------------------------------------------------------------------------
	// grouped
	// -------------------------------------------------------------------------

	public function test_grouped_returns_expected_categories(): void {
		$grouped = CheckSettings::grouped();
		foreach ( array( 'Security', 'Database', 'Code Quality', 'Compatibility', 'Plugin Standards', 'Roles & Permissions', 'External' ) as $cat ) {
			$this->assertArrayHasKey( $cat, $grouped, "Expected category '{$cat}'." );
		}
	}

	public function test_grouped_default_flag_is_true_for_on_slug(): void {
		$grouped = CheckSettings::grouped();
		$this->assertTrue( $grouped['Security']['dangerous_functions']['default'] );
	}

	public function test_grouped_default_flag_is_false_for_off_slug(): void {
		$grouped = CheckSettings::grouped();
		$this->assertFalse( $grouped['Security']['call_user_func']['default'] );
	}

	public function test_grouped_label_is_populated(): void {
		$grouped = CheckSettings::grouped();
		$this->assertSame( 'Dangerous Functions', $grouped['Security']['dangerous_functions']['label'] );
	}

	// -------------------------------------------------------------------------
	// get_enabled — no stored option
	// -------------------------------------------------------------------------

	public function test_get_enabled_returns_defaults_when_no_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'pla_enabled_checks' )
			->andReturn( false );

		$enabled = CheckSettings::get_enabled();
		$this->assertSame( CheckSettings::defaults(), $enabled );
	}

	public function test_get_enabled_returns_defaults_when_option_is_not_array(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'pla_enabled_checks' )
			->andReturn( 'not-an-array' );

		$enabled = CheckSettings::get_enabled();
		$this->assertSame( CheckSettings::defaults(), $enabled );
	}

	// -------------------------------------------------------------------------
	// get_enabled — stored option present
	// -------------------------------------------------------------------------

	public function test_get_enabled_returns_stored_slugs(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'pla_enabled_checks' )
			->andReturn( array( 'dangerous_functions', 'database' ) );

		$enabled = CheckSettings::get_enabled();
		$this->assertSame( array( 'dangerous_functions', 'database' ), $enabled );
	}

	public function test_get_enabled_filters_unknown_slugs(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'pla_enabled_checks' )
			->andReturn( array( 'dangerous_functions', 'unknown_slug', 'database' ) );

		$enabled = CheckSettings::get_enabled();
		$this->assertSame( array( 'dangerous_functions', 'database' ), $enabled );
	}

	public function test_get_enabled_returns_empty_when_stored_empty(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'pla_enabled_checks' )
			->andReturn( array() );

		$enabled = CheckSettings::get_enabled();
		$this->assertSame( array(), $enabled );
	}

	// -------------------------------------------------------------------------
	// save
	// -------------------------------------------------------------------------

	public function test_save_persists_valid_slugs(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( 'pla_enabled_checks', array( 'dangerous_functions', 'database' ), false );

		CheckSettings::save( array( 'dangerous_functions', 'database' ) );
	}

	public function test_save_strips_invalid_slugs(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( 'pla_enabled_checks', array( 'dangerous_functions' ), false );

		CheckSettings::save( array( 'dangerous_functions', 'totally_fake_slug' ) );
	}

	public function test_save_accepts_empty_array(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( 'pla_enabled_checks', array(), false );

		CheckSettings::save( array() );
	}

	// -------------------------------------------------------------------------
	// label
	// -------------------------------------------------------------------------

	public function test_label_returns_display_label(): void {
		$this->assertSame( 'Dangerous Functions', CheckSettings::label( 'dangerous_functions' ) );
		$this->assertSame( 'Outbound HTTP Requests', CheckSettings::label( 'requests' ) );
	}

	public function test_label_returns_slug_for_unknown(): void {
		$this->assertSame( 'unknown_slug', CheckSettings::label( 'unknown_slug' ) );
	}
}
