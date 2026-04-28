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

	public function test_flags_form_without_nonce_field(): void {
		$this->write_php( 'form.php', "<?php\ndefined( 'ABSPATH' ) || exit;\necho '<form method=\"post\"><input type=\"submit\"></form>';" );
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
	// Capability checks
	// -------------------------------------------------------------------------

	public function test_capability_check_reports_current_user_can_as_info(): void {
		$this->write_php( 'cap.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nif ( current_user_can( 'manage_options' ) ) { echo 'ok'; }" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$info     = array_filter( $findings['capabilities'], fn( $f ) => 'INFO' === $f['severity'] );
		$this->assertNotEmpty( $info );
	}

	public function test_capability_check_reports_add_menu_page_as_info(): void {
		$this->write_php( 'cap.php', "<?php\ndefined( 'ABSPATH' ) || exit;\nadd_menu_page( 'Title', 'Menu', 'manage_options', 'slug', 'cb' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$info     = array_filter( $findings['capabilities'], fn( $f ) => 'INFO' === $f['severity'] );
		$this->assertNotEmpty( $info );
	}

	public function test_no_capabilities_info_when_no_capability_calls(): void {
		$this->write_php( 'cap.php', '<?php $x = 1;' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['capabilities'] );
	}

	// -------------------------------------------------------------------------
	// Hardcoded credentials
	// -------------------------------------------------------------------------

	public function test_flags_hardcoded_password(): void {
		$this->write_php( 'cred.php', "<?php \$password = 'supersecret123';" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['credentials'], 'CRITICAL', 'credential' );
	}

	public function test_flags_hardcoded_api_key(): void {
		$this->write_php( 'cred.php', "<?php \$api_key = 'abc123def456ghi';" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['credentials'], 'CRITICAL', 'credential' );
	}

	public function test_no_flag_credentials_when_clean(): void {
		$this->write_php( 'cred.php', "<?php \$val = get_option( 'db_host' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['credentials'] );
	}

	// -------------------------------------------------------------------------
	// Debug output — PHP
	// -------------------------------------------------------------------------

	public function test_flags_var_dump(): void {
		$this->write_php( 'debug.php', '<?php var_dump( $x );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	public function test_flags_print_r(): void {
		$this->write_php( 'debug.php', '<?php print_r( $x );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	public function test_flags_var_export(): void {
		$this->write_php( 'debug.php', '<?php var_export( $x );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	// -------------------------------------------------------------------------
	// Debug output — JS
	// -------------------------------------------------------------------------

	public function test_flags_console_log(): void {
		$this->write_js( 'debug.js', "console.log( 'test' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	public function test_flags_console_debug(): void {
		$this->write_js( 'debug.js', "console.debug( 'test' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	public function test_flags_console_info(): void {
		$this->write_js( 'debug.js', "console.info( 'test' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	public function test_flags_console_warn(): void {
		$this->write_js( 'debug.js', "console.warn( 'something went wrong' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	public function test_flags_console_error(): void {
		$this->write_js( 'debug.js', "console.error( 'an error occurred' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['debug_output'] );
	}

	// -------------------------------------------------------------------------
	// File permissions
	// -------------------------------------------------------------------------

	public function test_flags_world_writable_file(): void {
		$this->write_php( 'perm.php', '<?php // test' );
		chmod( $this->tmp_dir . '/perm.php', 0666 );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['permissions'] );
	}

	public function test_flags_php_file_with_execute_bit(): void {
		$this->write_php( 'exec.php', '<?php // test' );
		chmod( $this->tmp_dir . '/exec.php', 0744 );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['permissions'] );
	}

	public function test_no_flag_normal_file_permissions(): void {
		$this->write_php( 'normal.php', '<?php // test' );
		chmod( $this->tmp_dir . '/normal.php', 0644 );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['permissions'] );
	}

	// -------------------------------------------------------------------------
	// Deprecated functions
	// -------------------------------------------------------------------------

	public function test_flags_deprecated_attribute_escape(): void {
		$this->write_php( 'dep.php', "<?php echo attribute_escape( \$val );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['deprecated'] );
	}

	public function test_flags_deprecated_wpdb_escape(): void {
		$this->write_php( 'dep.php', "<?php global \$wpdb; \$wpdb->escape( \$val );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['deprecated'] );
	}

	public function test_no_flag_deprecated_when_clean(): void {
		$this->write_php( 'dep.php', '<?php esc_attr( $val );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['deprecated'] );
	}

	// -------------------------------------------------------------------------
	// Plugin structure
	// -------------------------------------------------------------------------

	public function test_flags_missing_root_index_php(): void {
		$this->write_php( 'plugin.php', '<?php // Plugin Name: Test' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$missing  = array_filter( $findings['structure'], fn( $f ) => str_contains( $f['message'], 'root' ) );
		$this->assertNotEmpty( $missing );
	}

	public function test_flags_missing_subdir_index_php(): void {
		$this->write_php( 'plugin.php', '<?php // Plugin Name: Test' );
		mkdir( $this->tmp_dir . '/includes' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$missing  = array_filter( $findings['structure'], fn( $f ) => str_contains( $f['message'], 'includes' ) );
		$this->assertNotEmpty( $missing );
	}

	public function test_flags_missing_readme_txt(): void {
		$this->write_php( 'plugin.php', '<?php // Plugin Name: Test' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$missing  = array_filter( $findings['structure'], fn( $f ) => str_contains( strtolower( $f['message'] ), 'missing readme' ) );
		$this->assertNotEmpty( $missing );
	}

	public function test_no_flag_structure_with_readme_txt(): void {
		$this->write_php( 'plugin.php', '<?php // Plugin Name: Test' );
		file_put_contents( $this->tmp_dir . '/readme.txt', 'Stable tag: 1.0.0' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$missing  = array_filter( $findings['structure'], fn( $f ) => str_contains( strtolower( $f['message'] ), 'missing readme' ) );
		$this->assertEmpty( $missing );
	}

	public function test_no_flag_structure_with_root_index_php(): void {
		file_put_contents( $this->tmp_dir . '/index.php', '<?php // Silence is golden.' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$missing  = array_filter( $findings['structure'], fn( $f ) => str_contains( $f['message'], 'Missing index.php in plugin root' ) );
		$this->assertEmpty( $missing );
	}

	// -------------------------------------------------------------------------
	// PHP compatibility
	// -------------------------------------------------------------------------

	public function test_flags_match_expression_below_php_8(): void {
		$this->write_plugin_header( '7.4' );
		$this->write_php( 'code.php', "<?php \$r = match( \$x ) { 1 => 'a', default => 'b' };" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['php_compat'] );
	}

	public function test_flags_nullsafe_operator_below_php_8(): void {
		$this->write_plugin_header( '7.4' );
		$this->write_php( 'code.php', "<?php \$val = \$obj?->method();" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['php_compat'] );
	}

	public function test_flags_str_contains_below_php_8(): void {
		$this->write_plugin_header( '7.4' );
		$this->write_php( 'code.php', "<?php \$f = str_contains( \$str, 'needle' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['php_compat'] );
	}

	public function test_flags_enum_below_php_81(): void {
		$this->write_plugin_header( '8.0' );
		$this->write_php( 'code.php', "<?php\nenum Status { case Active; case Inactive; }" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['php_compat'] );
	}

	public function test_no_flag_php_compat_when_version_meets_requirement(): void {
		$this->write_plugin_header( '8.1' );
		$this->write_php( 'code.php', "<?php \$r = match( \$x ) { 1 => 'a', default => 'b' };" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['php_compat'] );
	}

	// -------------------------------------------------------------------------
	// call_user_func / call_user_func_array
	// -------------------------------------------------------------------------

	public function test_flags_call_user_func_with_variable_callback(): void {
		$this->write_php( 'dyn.php', '<?php call_user_func( $fn, $arg );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['dangerous'], 'HIGH', 'call_user_func' );
	}

	public function test_flags_call_user_func_array_with_variable_callback(): void {
		$this->write_php( 'dyn.php', '<?php call_user_func_array( $fn, array( $arg ) );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['dangerous'], 'HIGH', 'call_user_func' );
	}

	public function test_no_flag_call_user_func_with_string_callback(): void {
		$this->write_php( 'dyn.php', "<?php call_user_func( 'sanitize_text_field', \$val );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$high     = array_filter( $findings['dangerous'], fn( $f ) => 'HIGH' === $f['severity'] );
		$this->assertEmpty( $high );
	}

	// -------------------------------------------------------------------------
	// base64 obfuscation
	// -------------------------------------------------------------------------

	public function test_flags_eval_base64_decode_combo(): void {
		$this->write_php( 'b64.php', '<?php eval( base64_decode( $payload ) );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['obfuscation'], 'CRITICAL', 'base64_decode' );
	}

	public function test_flags_base64_decode_assigned_to_variable(): void {
		$this->write_php( 'b64.php', '<?php $code = base64_decode( $payload );' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['obfuscation'], 'HIGH', 'base64_decode' );
	}

	public function test_no_flag_base64_on_string_literal(): void {
		$this->write_php( 'b64.php', "<?php \$x = base64_encode( 'hello' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$b64      = array_filter( $findings['obfuscation'], fn( $f ) => str_contains( $f['message'], 'base64' ) );
		$this->assertEmpty( $b64 );
	}

	// -------------------------------------------------------------------------
	// File-level $_POST nonce check
	// -------------------------------------------------------------------------

	public function test_flags_post_access_without_nonce(): void {
		$this->write_php( 'handler.php', "<?php \$val = sanitize_text_field( wp_unslash( \$_POST['x'] ) );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$high     = array_filter( $findings['nonces'], fn( $f ) => 'HIGH' === $f['severity'] && str_contains( $f['message'], 'nonce' ) );
		$this->assertNotEmpty( $high );
	}

	public function test_no_flag_post_with_ajax_nonce(): void {
		$code = "<?php\ncheck_ajax_referer( 'my_action', 'nonce' );\n\$val = sanitize_text_field( wp_unslash( \$_POST['x'] ) );";
		$this->write_php( 'handler.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$post_nonce = array_filter(
			$findings['nonces'],
			fn( $f ) => str_contains( $f['message'], 'no nonce verification' )
		);
		$this->assertEmpty( $post_nonce );
	}

	// -------------------------------------------------------------------------
	// Commented-out code
	// -------------------------------------------------------------------------

	public function test_flags_excessive_commented_code(): void {
		$block  = "<?php\n";
		$block .= "// \$a = foo();\n";
		$block .= "// \$b = bar( \$a );\n";
		$block .= "// if ( \$b ) {\n";
		$block .= "//     do_something();\n";
		$block .= "// }\n";
		$this->write_php( 'old.php', $block );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['commented_code'] );
	}

	public function test_no_flag_few_commented_lines(): void {
		$this->write_php( 'old.php', "<?php\n// \$a = foo();\n// \$b = bar();" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['commented_code'] );
	}

	// -------------------------------------------------------------------------
	// Early translation calls
	// -------------------------------------------------------------------------

	public function test_flags_translation_at_file_scope(): void {
		$this->write_php( 'early.php', "<?php\n\$msg = __( 'Hello', 'domain' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['early_translations'] );
	}

	public function test_no_flag_translation_inside_function(): void {
		$code = "<?php\nfunction foo() {\n\$msg = __( 'Hello', 'domain' );\nreturn \$msg;\n}";
		$this->write_php( 'fn.php', $code );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['early_translations'] );
	}

	// -------------------------------------------------------------------------
	// Duplicate hook registrations
	// -------------------------------------------------------------------------

	public function test_flags_duplicate_hook_registration(): void {
		$this->write_php( 'hooks-a.php', "<?php\nadd_action( 'init', 'my_callback' );" );
		$this->write_php( 'hooks-b.php', "<?php\nadd_action( 'init', 'my_callback' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['duplicate_hooks'] );
	}

	public function test_no_flag_unique_hooks(): void {
		$this->write_php( 'hooks-a.php', "<?php\nadd_action( 'init', 'callback_a' );" );
		$this->write_php( 'hooks-b.php', "<?php\nadd_action( 'init', 'callback_b' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['duplicate_hooks'] );
	}

	// -------------------------------------------------------------------------
	// Plugin header meta
	// -------------------------------------------------------------------------

	public function test_flags_missing_plugin_header_fields(): void {
		$this->write_php( 'plugin.php', "<?php\n/**\n * Plugin Name: Test\n * Version: 1.0.0\n */\n" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['meta'] );
	}

	public function test_no_flag_complete_plugin_header(): void {
		$header = "<?php\n/**\n * Plugin Name: Test\n * Description: A test plugin.\n * Version: 1.0.0\n * Author: Test Author\n * Text Domain: test\n */\n";
		$this->write_php( 'plugin.php', $header );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['meta'] );
	}

	// -------------------------------------------------------------------------
	// Asset version strings
	// -------------------------------------------------------------------------

	public function test_flags_hardcoded_version_in_enqueue(): void {
		$this->write_php( 'assets.php', "<?php\nwp_enqueue_script( 'my-script', plugins_url( 'js/app.js', __FILE__ ), array(), '1.2.3', true );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['assets'] );
	}

	public function test_no_flag_constant_version_in_enqueue(): void {
		$this->write_php( 'assets.php', "<?php\nwp_enqueue_script( 'my-script', plugins_url( 'js/app.js', __FILE__ ), array(), PLUGIN_VERSION, true );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['assets'] );
	}

	// -------------------------------------------------------------------------
	// Outbound HTTP requests
	// -------------------------------------------------------------------------

	public function test_flags_wp_remote_post(): void {
		$this->write_php( 'req.php', "<?php\n\$r = wp_remote_post( 'https://api.example.com', array( 'body' => \$data ) );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['requests'], 'INFO', 'wp_remote_post' );
	}

	public function test_flags_wp_remote_get(): void {
		$this->write_php( 'req.php', "<?php\n\$r = wp_remote_get( 'https://api.example.com/data' );" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertHasFinding( $findings['requests'], 'INFO', 'wp_remote_get' );
	}

	// -------------------------------------------------------------------------
	// Licensing
	// -------------------------------------------------------------------------

	public function test_flags_missing_license_file(): void {
		$this->write_php( 'plugin.php', "<?php\n/**\n * Plugin Name: Test\n * License: GPL-2.0\n */\n" );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['licensing'] );
	}

	public function test_flags_non_gpl_compatible_license(): void {
		$this->write_php( 'plugin.php', "<?php\n/**\n * Plugin Name: Test\n * License: Proprietary\n */\n" );
		file_put_contents( $this->tmp_dir . '/LICENSE', 'Proprietary license text.' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertNotEmpty( $findings['licensing'] );
	}

	public function test_no_flag_gpl_license_with_license_file(): void {
		$this->write_php( 'plugin.php', "<?php\n/**\n * Plugin Name: Test\n * License: GPL-2.0\n */\n" );
		file_put_contents( $this->tmp_dir . '/LICENSE', 'GNU General Public License v2.0' );
		$findings = $this->scanner->scan( $this->tmp_dir );
		$this->assertEmpty( $findings['licensing'] );
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

	private function write_js( string $name, string $content ): void {
		file_put_contents( $this->tmp_dir . '/' . $name, $content );
	}

	private function write_plugin_header( string $php_version ): void {
		$content = "<?php\n/**\n * Plugin Name: Test Plugin\n * Requires PHP: {$php_version}\n */\n";
		file_put_contents( $this->tmp_dir . '/test-plugin.php', $content );
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
