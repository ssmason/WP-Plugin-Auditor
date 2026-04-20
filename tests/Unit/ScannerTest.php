<?php
/**
 * Unit tests for Scanner.
 *
 * @package PluginAuditor\Tests\Unit
 */

declare( strict_types=1 );

namespace PluginAuditor\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PluginAuditor\Scanner;

/**
 * Tests static analysis checks performed by Scanner.
 */
class ScannerTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Temp directory for fixture plugin files.
	 */
	private string $tmp_dir;

	/**
	 * System under test.
	 */
	private Scanner $scanner;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubTranslationFunctions();

		$this->tmp_dir = sys_get_temp_dir() . '/pla_test_' . uniqid( '', true );
		mkdir( $this->tmp_dir );

		$this->scanner = new Scanner();
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->tmp_dir );
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Dangerous functions
	// -------------------------------------------------------------------------

	public function test_flags_eval_usage(): void {
		$this->write_php( 'bad.php', '<?php eval( $code );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['dangerous'], 'CRITICAL', 'eval' );
	}

	public function test_flags_shell_exec_usage(): void {
		$this->write_php( 'bad.php', '<?php shell_exec( $cmd );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['dangerous'], 'CRITICAL', 'shell_exec' );
	}

	public function test_no_dangerous_functions_when_clean(): void {
		$this->write_php( 'clean.php', '<?php $a = sanitize_text_field( $_POST["field"] ?? "" );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['dangerous'] );
	}

	// -------------------------------------------------------------------------
	// Output escaping
	// -------------------------------------------------------------------------

	public function test_flags_unescaped_echo_on_variable(): void {
		$this->write_php( 'out.php', '<?php $x = get_option("foo"); echo $x;' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['output'] );
	}

	public function test_no_flag_when_echo_uses_esc_html(): void {
		$this->write_php( 'out.php', '<?php $x = get_option("foo"); echo esc_html( $x );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$output_issues = array_filter( $findings['output'], fn( $f ) => 'INFO' !== $f['severity'] );
		$this->assertEmpty( $output_issues );
	}

	public function test_flags_bare_e_translation(): void {
		$this->write_php( 'out.php', '<?php _e( "Hello", "domain" );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['output'] );
	}

	public function test_flags_php_self_without_esc_url(): void {
		$this->write_php( 'out.php', "<?php echo \$_SERVER['PHP_SELF'];" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['output'] );
	}

	// -------------------------------------------------------------------------
	// Input sanitization
	// -------------------------------------------------------------------------

	public function test_flags_unsanitized_post_access(): void {
		$this->write_php( 'in.php', '<?php $val = $_POST["field"];' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['input'] );
	}

	public function test_no_flag_when_post_is_sanitized(): void {
		$this->write_php( 'in.php', '<?php $val = sanitize_text_field( wp_unslash( $_POST["field"] ) );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$input_issues = array_filter( $findings['input'], fn( $f ) => 'HIGH' === $f['severity'] );
		$this->assertEmpty( $input_issues );
	}

	public function test_flags_missing_wp_unslash(): void {
		$this->write_php( 'in.php', '<?php $val = sanitize_text_field( $_POST["field"] );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['input'] );
	}

	// -------------------------------------------------------------------------
	// Nonces
	// -------------------------------------------------------------------------

	public function test_flags_post_handler_without_nonce(): void {
		$this->write_php( 'form.php', '<?php if ( isset( $_POST["save"] ) ) { update_option( "x", 1 ); }' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['nonces'] );
	}

	public function test_no_nonce_flag_when_nonce_present(): void {
		$code = '<?php check_admin_referer( "save_opts", "_wpnonce" ); $v = sanitize_text_field( wp_unslash( $_POST["x"] ) );';
		$this->write_php( 'form.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$nonce_criticals = array_filter( $findings['nonces'], fn( $f ) => 'CRITICAL' === $f['severity'] );
		$this->assertEmpty( $nonce_criticals );
	}

	// -------------------------------------------------------------------------
	// Database
	// -------------------------------------------------------------------------

	public function test_flags_unprepared_wpdb_query(): void {
		$this->write_php( 'db.php', '<?php global $wpdb; $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE ID=" . $id );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['database'] );
	}

	public function test_flags_raw_mysql_call(): void {
		$this->write_php( 'db.php', '<?php mysql_query( "SELECT 1" );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['database'], 'CRITICAL', 'mysql' );
	}

	// -------------------------------------------------------------------------
	// Obfuscation
	// -------------------------------------------------------------------------

	public function test_flags_variable_variable_callable(): void {
		$this->write_php( 'ob.php', '<?php $func = "eval"; $$func( $x );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['obfuscation'] );
	}

	public function test_flags_preg_replace_e_modifier(): void {
		$this->write_php( 'ob.php', "<?php preg_replace( '/.*/e', \$rep, \$str );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['obfuscation'] );
	}

	// -------------------------------------------------------------------------
	// Error suppression
	// -------------------------------------------------------------------------

	public function test_flags_error_reporting_zero(): void {
		$this->write_php( 'err.php', '<?php error_reporting( 0 );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['errors'] );
	}

	// -------------------------------------------------------------------------
	// Redirect without exit
	// -------------------------------------------------------------------------

	public function test_flags_wp_redirect_without_exit(): void {
		$this->write_php( 'redir.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nwp_redirect( home_url() );\ndo_something();" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['redirects'] );
	}

	public function test_flags_wp_safe_redirect_without_exit(): void {
		$this->write_php( 'redir.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nwp_safe_redirect( home_url() );\ndo_something();" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['redirects'] );
	}

	public function test_no_flag_wp_redirect_with_exit_same_line(): void {
		$this->write_php( 'redir.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nwp_redirect( home_url() ); exit;" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['redirects'] );
	}

	public function test_no_flag_wp_redirect_with_exit_next_line(): void {
		$this->write_php( 'redir.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nwp_redirect( home_url() );\nexit;" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['redirects'] );
	}

	// -------------------------------------------------------------------------
	// Role name in current_user_can()
	// -------------------------------------------------------------------------

	public function test_flags_role_name_administrator(): void {
		$this->write_php( 'cap.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nif ( current_user_can( 'administrator' ) ) { echo 'hi'; }" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['role_checks'] );
	}

	public function test_flags_role_name_editor(): void {
		$this->write_php( 'cap.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nif ( current_user_can( 'editor' ) ) { echo 'hi'; }" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['role_checks'] );
	}

	public function test_no_flag_capability_name(): void {
		$this->write_php( 'cap.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nif ( current_user_can( 'manage_options' ) ) { echo 'hi'; }" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['role_checks'] );
	}

	// -------------------------------------------------------------------------
	// Unescaped shortcode output
	// -------------------------------------------------------------------------

	public function test_flags_shortcode_returning_unescaped_variable(): void {
		$code = "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_shortcode( 'foo', function() {\n\$out = get_option('x');\nreturn \$out;\n} );";
		$this->write_php( 'sc.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['shortcodes'] );
	}

	public function test_no_flag_shortcode_returning_escaped_variable(): void {
		$code = "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_shortcode( 'foo', function() {\n\$out = get_option('x');\nreturn esc_html( \$out );\n} );";
		$this->write_php( 'sc.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['shortcodes'] );
	}

	// -------------------------------------------------------------------------
	// Option writes without capability check
	// -------------------------------------------------------------------------

	public function test_flags_update_option_without_capability(): void {
		$this->write_php( 'opt.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nupdate_option( 'foo', 'bar' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['option_writes'] );
	}

	public function test_no_flag_update_option_with_capability_check(): void {
		$code = "<?php\ndefined( 'ABSPATH' ) || exit;\nif ( current_user_can( 'manage_options' ) ) {\nupdate_option( 'foo', 'bar' );\n}";
		$this->write_php( 'opt.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['option_writes'] );
	}

	public function test_flags_delete_option_without_capability(): void {
		$this->write_php( 'opt.php', "<?php\ndefined( 'ABSPATH' ) || exit;\ndelete_option( 'foo' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['option_writes'] );
	}

	// -------------------------------------------------------------------------
	// Wrong wpdb placeholder
	// -------------------------------------------------------------------------

	public function test_flags_s_placeholder_for_integer(): void {
		$this->write_php( 'db.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nglobal \$wpdb;\n\$wpdb->prepare( 'SELECT * FROM t WHERE id = %s', absint( \$id ) );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['wpdb_placeholders'] );
	}

	public function test_flags_d_placeholder_for_string(): void {
		$this->write_php( 'db.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nglobal \$wpdb;\n\$wpdb->prepare( 'SELECT * FROM t WHERE slug = %d', 'my-slug' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['wpdb_placeholders'] );
	}

	public function test_no_flag_correct_placeholders(): void {
		$this->write_php( 'db.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nglobal \$wpdb;\n\$wpdb->prepare( 'SELECT * FROM t WHERE id = %d AND slug = %s', absint( \$id ), \$slug );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['wpdb_placeholders'] );
	}

	// -------------------------------------------------------------------------
	// Duplicate hook registrations
	// -------------------------------------------------------------------------

	public function test_flags_duplicate_hook_registration(): void {
		$code = "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_action( 'init', 'my_func', 10 );\nadd_action( 'init', 'my_func', 10 );";
		$this->write_php( 'hooks.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['duplicate_hooks'] );
	}

	public function test_no_flag_different_priority_hooks(): void {
		$code = "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_action( 'init', 'my_func', 10 );\nadd_action( 'init', 'my_func', 20 );";
		$this->write_php( 'hooks.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['duplicate_hooks'] );
	}

	public function test_no_flag_different_callbacks(): void {
		$code = "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_action( 'init', 'my_func', 10 );\nadd_action( 'init', 'other_func', 10 );";
		$this->write_php( 'hooks.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['duplicate_hooks'] );
	}

	// -------------------------------------------------------------------------
	// Unnecessary closures
	// -------------------------------------------------------------------------

	public function test_flags_closure_returning_true(): void {
		$this->write_php( 'hooks.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_filter( 'some_filter', function() { return true; } );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['unnecessary_closures'] );
	}

	public function test_flags_closure_returning_false(): void {
		$this->write_php( 'hooks.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_filter( 'some_filter', function() { return false; } );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['unnecessary_closures'] );
	}

	public function test_no_flag_closure_with_logic(): void {
		$this->write_php( 'hooks.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_filter( 'some_filter', function( \$val ) { return \$val . '_suffix'; } );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['unnecessary_closures'] );
	}

	// -------------------------------------------------------------------------
	// Early translation calls
	// -------------------------------------------------------------------------

	public function test_flags_translation_at_file_scope(): void {
		$this->write_php( 'trans.php', "<?php\ndefined( 'ABSPATH' ) || exit;\n\$label = __( 'Hello', 'my-plugin' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['early_translations'] );
	}

	public function test_no_flag_translation_inside_function(): void {
		$code = "<?php\ndefined( 'ABSPATH' ) || exit;\nfunction my_func() {\n\$label = __( 'Hello', 'my-plugin' );\nreturn \$label;\n}";
		$this->write_php( 'trans.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['early_translations'] );
	}

	// -------------------------------------------------------------------------
	// Stats header
	// -------------------------------------------------------------------------

	public function test_scan_returns_stats(): void {
		$this->write_php( 'clean.php', "<?php\ndefined( 'ABSPATH' ) || exit;\n// test" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertArrayHasKey( 'stats', $findings );
		$this->assertArrayHasKey( 'files', $findings['stats'] );
		$this->assertArrayHasKey( 'lines', $findings['stats'] );
		$this->assertArrayHasKey( 'duration', $findings['stats'] );
		$this->assertGreaterThan( 0, $findings['stats']['files'] );
		$this->assertGreaterThan( 0, $findings['stats']['lines'] );
		$this->assertIsInt( $findings['stats']['duration'] );
	}

	// -------------------------------------------------------------------------
	// Score / rating
	// -------------------------------------------------------------------------

	public function test_clean_plugin_has_clean_rating(): void {
		$this->write_php( 'clean.php', "<?php\ndefined( 'ABSPATH' ) || exit;\n// nothing dangerous here" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertSame( 'CLEAN', $findings['rating'] );
	}

	public function test_critical_finding_raises_score(): void {
		$this->write_php( 'bad.php', '<?php eval( $x );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertGreaterThan( 0, $findings['score'] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function write_php( string $name, string $content ): void {
		file_put_contents( $this->tmp_dir . '/' . $name, $content );
	}

	/**
	 * @param array<int, array<string, mixed>> $findings
	 */
	private function assertHasFinding( array $findings, string $severity, string $keyword ): void {
		foreach ( $findings as $finding ) {
			if ( $finding['severity'] === $severity && str_contains( strtolower( $finding['message'] ), strtolower( $keyword ) ) ) {
				$this->assertTrue( true );
				return;
			}
		}
		$this->fail( sprintf( 'No %s finding containing "%s" found.', $severity, $keyword ) );
	}

	private function remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->remove_directory( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
