<?php
/**
 * Static analysis engine — scans PHP and JS files in a plugin directory.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Performs static analysis on all PHP files in a plugin directory.
 */
class Scanner {

	/**
	 * Dangerous functions that must be flagged.
	 */
	private const DANGEROUS_FUNCTIONS = array(
		'eval',
		'exec',
		'shell_exec',
		'system',
		'passthru',
		'popen',
		'proc_open',
		'base64_decode',
		'base64_encode',
		'str_rot13',
		'gzinflate',
		'gzuncompress',
		'assert',
		'create_function',
		'call_user_func',
		'call_user_func_array',
	);

	/**
	 * Context-correct escape functions.
	 */
	private const ESCAPE_FUNCTIONS = array(
		'esc_html',
		'esc_attr',
		'esc_url',
		'esc_js',
		'esc_textarea',
		'esc_html__',
		'esc_attr__',
		'esc_html_e',
		'esc_attr_e',
		'wp_kses',
		'wp_kses_post',
		'absint',
		'intval',
		'floatval',
		'number_format',
		'sanitize_text_field',
		'sanitize_email',
		'sanitize_url',
		'sanitize_key',
		'sanitize_html_class',
		'sanitize_file_name',
	);

	/**
	 * Superglobal arrays that require sanitization.
	 */
	private const SUPERGLOBALS = array( '_GET', '_POST', '_REQUEST', '_COOKIE', '_SERVER', '_FILES' );

	/**
	 * Severity weights for score calculation.
	 */
	private const SEVERITY_WEIGHTS = array(
		'CRITICAL' => 25,
		'HIGH'     => 10,
		'MEDIUM'   => 4,
		'LOW'      => 1,
		'INFO'     => 0,
	);

	/**
	 * Deprecated WordPress functions mapped to the version they were deprecated in.
	 */
	private const DEPRECATED_FUNCTIONS = array(
		'get_currentuserinfo'               => '4.5',
		'wp_make_content_images_responsive' => '5.5',
		'attribute_escape'                  => '2.8',
		'wp_get_loading_attr_default'       => '6.3',
		'clean_pre'                         => '3.4',
	);

	/**
	 * GPL-compatible license identifiers (case-insensitive partial match).
	 */
	private const GPL_COMPATIBLE_LICENSES = array(
		'gpl-2.0',
		'gpl-2.0+',
		'gpl-2.0-or-later',
		'gpLv2',
		'gpl v2',
		'gpl-3.0',
		'gpl-3.0+',
		'gpl-3.0-or-later',
		'gpLv3',
		'gpl v3',
		'gnu gpl',
		'mit',
		'bsd',
		'bsd-2-clause',
		'bsd-3-clause',
		'apache-2.0',
		'apache 2.0',
		'lgpl-2.0',
		'lgpl-2.1',
		'lgpl-2.1+',
		'lgpl-3.0',
		'isc',
		'mpl-2.0',
		'cc0-1.0',
		'artistic-2.0',
	);

	/**
	 * Directories to skip when collecting files.
	 */
	private const SKIP_DIRS = array( 'vendor', 'node_modules', '.git', 'tests' );

	/**
	 * WordPress role names that must never be passed to current_user_can().
	 */
	private const WP_ROLE_NAMES = array(
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
	);

	/**
	 * Translation functions that must not be called before init.
	 */
	private const EARLY_TRANSLATION_FUNCTIONS = array(
		'__',
		'_e',
		'esc_html__',
		'esc_html_e',
		'esc_attr__',
		'esc_attr_e',
	);

	/**
	 * Returns the list of section keys that are classified as security checks.
	 * These contribute to the overall risk score.
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
			'direct_access',
			'debug_output',
			'redirects',
			'role_checks',
			'shortcodes',
			'option_writes',
			'wpdb_placeholders',
		);
	}

	/**
	 * Returns the list of section keys that are classified as code quality checks.
	 * These do NOT contribute to the risk score.
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
			'duplicate_hooks',
			'unnecessary_closures',
			'early_translations',
		);
	}

	/**
	 * Runs the full audit on all PHP files in a directory.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @return array<string, mixed> Findings keyed by section.
	 */
	public function scan( string $plugin_dir ): array {
		$scan_start   = microtime( true );
		$php_files    = $this->collect_php_files( $plugin_dir );
		$js_files     = $this->collect_js_files( $plugin_dir );
		$required_php = $this->get_required_php_version( $plugin_dir );

		$sections = array_merge( self::security_sections(), self::code_quality_sections() );
		$findings = array_fill_keys( $sections, array() );

		$total_lines    = 0;
		$all_hook_calls = array();

		foreach ( $php_files as $file ) {
			$lines = file( $file, FILE_IGNORE_NEW_LINES );
			if ( false === $lines ) {
				continue;
			}
			$total_lines += count( $lines );
			$content      = implode( "\n", $lines );
			$rel          = str_replace( $plugin_dir . '/', '', $file );

			$findings['dangerous']            = array_merge( $findings['dangerous'], $this->check_dangerous_functions( $rel, $lines ) );
			$findings['output']               = array_merge( $findings['output'], $this->check_output_escaping( $rel, $lines ) );
			$findings['input']                = array_merge( $findings['input'], $this->check_input_sanitization( $rel, $lines ) );
			$findings['nonces']               = array_merge( $findings['nonces'], $this->check_nonces( $rel, $lines, $content ) );
			$findings['capabilities']         = array_merge( $findings['capabilities'], $this->check_capabilities( $rel, $lines ) );
			$findings['database']             = array_merge( $findings['database'], $this->check_database( $rel, $lines ) );
			$findings['credentials']          = array_merge( $findings['credentials'], $this->check_credentials( $rel, $lines ) );
			$findings['requests']             = array_merge( $findings['requests'], $this->check_external_requests( $rel, $lines ) );
			$findings['assets']               = array_merge( $findings['assets'], $this->check_asset_versioning( $rel, $lines ) );
			$findings['errors']               = array_merge( $findings['errors'], $this->check_error_suppression( $rel, $lines ) );
			$findings['obfuscation']          = array_merge( $findings['obfuscation'], $this->check_obfuscation( $rel, $lines ) );
			$findings['direct_access']        = array_merge( $findings['direct_access'], $this->check_direct_access( $rel, $lines ) );
			$findings['debug_output']         = array_merge( $findings['debug_output'], $this->check_debug_output( $rel, $lines ) );
			$findings['deprecated']           = array_merge( $findings['deprecated'], $this->check_deprecated_functions( $rel, $lines ) );
			$findings['commented_code']       = array_merge( $findings['commented_code'], $this->check_commented_code( $rel, $lines ) );
			$findings['redirects']            = array_merge( $findings['redirects'], $this->check_redirect_without_exit( $rel, $lines ) );
			$findings['role_checks']          = array_merge( $findings['role_checks'], $this->check_role_checks( $rel, $lines ) );
			$findings['shortcodes']           = array_merge( $findings['shortcodes'], $this->check_shortcode_escaping( $rel, $lines, $content ) );
			$findings['option_writes']        = array_merge( $findings['option_writes'], $this->check_option_writes( $rel, $lines ) );
			$findings['wpdb_placeholders']    = array_merge( $findings['wpdb_placeholders'], $this->check_wpdb_placeholders( $rel, $lines ) );
			$findings['unnecessary_closures'] = array_merge( $findings['unnecessary_closures'], $this->check_unnecessary_closures( $rel, $lines ) );
			$findings['early_translations']   = array_merge( $findings['early_translations'], $this->check_early_translations( $rel, $lines ) );

			$all_hook_calls = array_merge( $all_hook_calls, $this->collect_hook_calls( $rel, $lines ) );

			if ( '' !== $required_php ) {
				$findings['php_compat'] = array_merge( $findings['php_compat'], $this->check_php_compat( $rel, $lines, $required_php ) );
			}
		}

		foreach ( $js_files as $file ) {
			$lines = file( $file, FILE_IGNORE_NEW_LINES );
			if ( false === $lines ) {
				continue;
			}
			$rel                      = str_replace( $plugin_dir . '/', '', $file );
			$findings['debug_output'] = array_merge( $findings['debug_output'], $this->check_debug_js( $rel, $lines ) );
		}

		$findings['duplicate_hooks'] = $this->check_duplicate_hooks( $all_hook_calls );
		$findings['permissions']     = $this->check_file_permissions( $plugin_dir );
		$findings['meta']            = $this->check_plugin_header( $plugin_dir );
		$findings['structure']       = $this->check_plugin_structure( $plugin_dir );
		$findings['licensing']       = $this->check_licensing( $plugin_dir );

		$findings['score']  = $this->calculate_score( $findings );
		$findings['rating'] = $this->calculate_rating( (int) $findings['score'] );
		$findings['stats']  = array(
			'files'    => count( $php_files ),
			'lines'    => $total_lines,
			'duration' => (int) round( ( microtime( true ) - $scan_start ) * 1000 ),
		);

		return $findings;
	}

	/**
	 * Collects all PHP files in the plugin directory recursively, skipping vendor/node_modules.
	 *
	 * @param string $dir Absolute path.
	 * @return string[] Absolute file paths.
	 */
	private function collect_php_files( string $dir ): array {
		return $this->collect_files( $dir, 'php' );
	}

	/**
	 * Collects all non-minified JS files in the plugin directory, skipping vendor/node_modules.
	 *
	 * @param string $dir Absolute path.
	 * @return string[] Absolute file paths.
	 */
	private function collect_js_files( string $dir ): array {
		$files = $this->collect_files( $dir, 'js' );
		return array_filter(
			$files,
			static fn( $f ) => ! str_ends_with( $f, '.min.js' )
		);
	}

	/**
	 * Collects files by extension, skipping vendor/node_modules/.git directories.
	 *
	 * @param string $dir       Absolute directory path.
	 * @param string $extension File extension without dot.
	 * @return string[]
	 */
	private function collect_files( string $dir, string $extension ): array {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		$files = array();
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $extension !== $file->getExtension() ) {
				continue;
			}
			$path = $file->getPathname();
			$skip = false;
			foreach ( self::SKIP_DIRS as $skip_dir ) {
				if ( str_contains( $path, '/' . $skip_dir . '/' ) ) {
					$skip = true;
					break;
				}
			}
			if ( ! $skip ) {
				$files[] = $path;
			}
		}

		return $files;
	}

	/**
	 * Finds the main plugin file (contains Plugin Name: header).
	 *
	 * @param string $plugin_dir Absolute plugin directory.
	 */
	private function find_main_plugin_file( string $plugin_dir ): ?string {
		$candidates = glob( $plugin_dir . '/*.php' );
		if ( false === $candidates ) {
			return null;
		}
		foreach ( $candidates as $f ) {
			$content = file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local filesystem read, not HTTP.
			if ( $content && str_contains( $content, 'Plugin Name:' ) ) {
				return $f;
			}
		}
		return null;
	}

	/**
	 * Extracts the declared Requires PHP version from the plugin header.
	 *
	 * @param string $plugin_dir Absolute plugin directory.
	 */
	private function get_required_php_version( string $plugin_dir ): string {
		$main_file = $this->find_main_plugin_file( $plugin_dir );
		if ( null === $main_file ) {
			return '';
		}
		$content = (string) file_get_contents( $main_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local filesystem read, not HTTP.
		if ( preg_match( '/^\s*\*\s*Requires PHP:\s*([\d.]+)/im', $content, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	/**
	 * Returns true if a line begins with a comment token.
	 *
	 * @param string $trimmed Already-trimmed line.
	 */
	private function is_comment_line( string $trimmed ): bool {
		return str_starts_with( $trimmed, '//' )
			|| str_starts_with( $trimmed, '#' )
			|| str_starts_with( $trimmed, '*' );
	}

	// -------------------------------------------------------------------------
	// Security checks
	// -------------------------------------------------------------------------

	/**
	 * Checks for usage of dangerous functions.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_dangerous_functions( string $file, array $lines ): array {
		$findings = array();
		$pattern  = '/\b(' . implode( '|', self::DANGEROUS_FUNCTIONS ) . ')\s*\(/i';

		foreach ( $lines as $i => $line ) {
			if ( preg_match( $pattern, $line, $matches ) ) {
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					$line,
					/* translators: %s: function name */
					sprintf( __( 'Dangerous function used: %s()', 'plugin-auditor' ), $matches[1] )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks for unescaped output.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_output_escaping( string $file, array $lines ): array {
		$findings     = array();
		$tainted_vars = array();

		foreach ( $lines as $i => $line ) {
			$trimmed = ltrim( $line );
			if ( str_starts_with( $trimmed, '//' ) || str_starts_with( $trimmed, '*' ) || str_starts_with( $trimmed, '#' ) ) {
				continue;
			}

			// Detect tainted variable assignments from superglobals.
			foreach ( self::SUPERGLOBALS as $global ) {
				if ( str_contains( $line, '$' . $global ) ) {
					if ( preg_match( '/\$(\w+)\s*=.*\$' . preg_quote( $global, '/' ) . '/i', $line, $m ) ) {
						$tainted_vars[ '$' . $m[1] ] = true;
					}
				}
			}

			// Flag _e() and __() used without escaping.
			if ( preg_match( '/\becho\s+__\s*\(/', $line ) && ! str_contains( $line, 'esc_' ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$file,
					$i + 1,
					$line,
					__( 'Use esc_html__() or esc_attr__() instead of __(). Never echo __() directly.', 'plugin-auditor' )
				);
			}

			if ( preg_match( '/\b_e\s*\(/', $line ) && ! str_contains( $line, 'esc_' ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$file,
					$i + 1,
					$line,
					__( 'Use esc_html_e() or esc_attr_e() instead of _e().', 'plugin-auditor' )
				);
			}

			// Flag PHP_SELF / REQUEST_URI in output without esc_url.
			if ( preg_match( '/\$_SERVER\[.*(PHP_SELF|REQUEST_URI)/', $line ) && ! str_contains( $line, 'esc_url' ) ) {
				$findings[] = $this->finding(
					'HIGH',
					$file,
					$i + 1,
					$line,
					__( '$_SERVER[\'PHP_SELF\'] or $_SERVER[\'REQUEST_URI\'] used in output without esc_url().', 'plugin-auditor' )
				);
			}

			// Flag direct echo of variables without escaping.
			if ( preg_match( '/\becho\s+(\$\w+)\s*;/', $line, $m ) ) {
				$var = $m[1];
				if ( ! $this->is_escaped( $line ) ) {
					$severity   = isset( $tainted_vars[ $var ] ) ? 'HIGH' : 'MEDIUM';
					$findings[] = $this->finding(
						$severity,
						$file,
						$i + 1,
						$line,
						/* translators: %s: variable name */
						sprintf( __( 'Unescaped output: %s echoed without a context-correct escape function.', 'plugin-auditor' ), $var )
					);
				}
			}

			// Flag echo with concatenation and no escaping.
			if ( preg_match( '/\becho\s+.*\..*\$/', $line ) && ! $this->is_escaped( $line ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$file,
					$i + 1,
					$line,
					__( 'Unescaped output: echo with concatenated variable lacks escaping.', 'plugin-auditor' )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks whether a line uses a context-correct escape function.
	 *
	 * @param string $line Source line.
	 */
	private function is_escaped( string $line ): bool {
		foreach ( self::ESCAPE_FUNCTIONS as $fn ) {
			if ( str_contains( $line, $fn . '(' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks for unsanitized superglobal access.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_input_sanitization( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			$trimmed_line = ltrim( $line );
			if ( str_starts_with( $trimmed_line, '//' ) || str_starts_with( $trimmed_line, '*' ) || str_starts_with( $trimmed_line, '#' ) ) {
				continue;
			}

			foreach ( self::SUPERGLOBALS as $global ) {
				if ( ! str_contains( $line, '$' . $global . '[' ) ) {
					continue;
				}

				// Skip if the superglobal appears inside a string literal.
				if ( str_contains( $line, "'\$" . $global ) || str_contains( $line, '"\$' . $global ) ) {
					continue;
				}

				$has_sanitize = $this->is_sanitized( $line );
				$has_unslash  = str_contains( $line, 'wp_unslash' );

				if ( $has_sanitize && $has_unslash ) {
					continue;
				}

				if ( $has_sanitize && ! $has_unslash ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: %s: superglobal name */
						sprintf( __( 'Missing wp_unslash() before sanitizing $%s input.', 'plugin-auditor' ), $global )
					);
					continue;
				}

				$findings[] = $this->finding(
					'HIGH',
					$file,
					$i + 1,
					$line,
					/* translators: %s: superglobal name */
					sprintf( __( 'Unsanitized input: $%s accessed without sanitization.', 'plugin-auditor' ), $global )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks whether a line applies a sanitize function.
	 *
	 * @param string $line Source line.
	 */
	private function is_sanitized( string $line ): bool {
		$sanitizers = array(
			'sanitize_text_field',
			'sanitize_email',
			'sanitize_url',
			'sanitize_key',
			'sanitize_html_class',
			'sanitize_file_name',
			'absint',
			'intval',
			'floatval',
			'wp_kses',
			'wp_kses_post',
			'array_map',
			'wp_verify_nonce',
			'check_admin_referer',
		);

		foreach ( $sanitizers as $fn ) {
			if ( str_contains( $line, $fn ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks nonce usage.
	 *
	 * @param string   $file    Relative file path.
	 * @param string[] $lines   File lines.
	 * @param string   $content Full file content.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_nonces( string $file, array $lines, string $content ): array {
		$findings = array();

		$has_post_handling = str_contains( $content, '$_POST' );
		$has_nonce_check   = str_contains( $content, 'wp_verify_nonce' ) || str_contains( $content, 'check_admin_referer' );
		$has_nonce_field   = str_contains( $content, 'wp_nonce_field' );
		$has_form          = str_contains( $content, '<form' );
		$has_get_action    = (bool) preg_match( '/\$_GET\[.action.\]/', $content );

		if ( $has_post_handling && ! $has_nonce_check ) {
			$findings[] = $this->finding(
				'CRITICAL',
				$file,
				0,
				'',
				__( 'File handles $_POST data but has no wp_verify_nonce() or check_admin_referer() call.', 'plugin-auditor' )
			);
		}

		if ( $has_form && ! $has_nonce_field ) {
			$findings[] = $this->finding(
				'HIGH',
				$file,
				0,
				'',
				__( 'HTML form found but no wp_nonce_field() call — form is not CSRF-protected.', 'plugin-auditor' )
			);
		}

		if ( $has_get_action && ! $has_nonce_check ) {
			$findings[] = $this->finding(
				'HIGH',
				$file,
				0,
				'',
				__( 'GET action parameter used without nonce verification.', 'plugin-auditor' )
			);
		}

		return $findings;
	}

	/**
	 * Checks capability checks.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_capabilities( string $file, array $lines ): array {
		$findings    = array();
		$info        = array();
		$cap_pattern = '/current_user_can\s*\(\s*[\'"]([^\'"]+)[\'"]/';

		$full_file = implode( "\n", $lines );

		foreach ( $lines as $i => $line ) {
			$trimmed_cap = ltrim( $line );
			if ( str_starts_with( $trimmed_cap, '//' ) || str_starts_with( $trimmed_cap, '*' ) || str_starts_with( $trimmed_cap, '#' ) ) {
				continue;
			}

			if ( preg_match( $cap_pattern, $line, $m ) ) {
				$info[] = $this->finding(
					'INFO',
					$file,
					$i + 1,
					$line,
					/* translators: %s: capability name */
					sprintf( __( 'Capability check: current_user_can( \'%s\' )', 'plugin-auditor' ), $m[1] )
				);
			}

			if ( preg_match( '/add_(menu|submenu|management|options|dashboard|posts|media|links|pages|comments|theme|plugins|users|tools|settings)_page\s*\(/', $line ) ) {
				$info[] = $this->finding(
					'INFO',
					$file,
					$i + 1,
					$line,
					__( 'Admin page registered — ensure callback checks current_user_can() before rendering.', 'plugin-auditor' )
				);
			}

			if ( preg_match( '/\b(update_option|add_option|delete_option)\s*\(/', $line ) ) {
				$start   = max( 0, $i - 30 );
				$context = implode( "\n", array_slice( $lines, $start, $i - $start + 1 ) );
				if ( ! str_contains( $context, 'current_user_can' )
					&& ! str_contains( $full_file, 'register_activation_hook' )
					&& ! str_contains( $full_file, 'register_deactivation_hook' )
				) {
					$findings[] = $this->finding(
						'HIGH',
						$file,
						$i + 1,
						$line,
						__( 'Write operation without a visible current_user_can() check in the preceding 30 lines.', 'plugin-auditor' )
					);
				}
			}
		}

		return array_merge( $findings, $info );
	}

	/**
	 * Checks database query safety.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_database( string $file, array $lines ): array {
		$findings     = array();
		$wpdb_methods = array( 'query', 'get_results', 'get_row', 'get_var', 'get_col' );
		$pattern      = '/\$wpdb->(' . implode( '|', $wpdb_methods ) . ')\s*\(/';

		foreach ( $lines as $i => $line ) {
			if ( preg_match( '/\b(mysql_query|mysqli_query|mysqli_fetch_|mysql_fetch_)\s*\(/', $line ) ) {
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					$line,
					__( 'Direct mysql_* or mysqli_* call — use $wpdb methods with prepare() instead.', 'plugin-auditor' )
				);
			}

			if ( ! preg_match( $pattern, $line, $m ) ) {
				continue;
			}

			if ( str_contains( $line, '$wpdb->prepare(' ) ) {
				continue;
			}

			$has_var_concat      = (bool) preg_match( '/\.\s*\$(?!wpdb)/', $line );
			$has_string_arg      = (bool) preg_match( '/\$wpdb->' . $m[1] . '\s*\(\s*["\']/', $line );
			$has_non_wpdb_interp = (bool) preg_match( '/\{\$(?!wpdb->)/', $line );

			if ( $has_var_concat || ( $has_string_arg && $has_non_wpdb_interp ) ) {
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					$line,
					/* translators: %s: method name */
					sprintf( __( 'Unprepared query: $wpdb->%s() called without $wpdb->prepare().', 'plugin-auditor' ), $m[1] )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks for hardcoded credentials.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_credentials( string $file, array $lines ): array {
		$findings = array();
		$pattern  = '/(?:password|passwd|pwd|secret|api_key|apikey|token|auth_key|access_key|private_key)\s*[=:]\s*[\'"][^\'"]{6,}[\'"]/i';

		foreach ( $lines as $i => $line ) {
			if ( preg_match( $pattern, $line, $m ) ) {
				$redacted   = preg_replace( '/([\'"])[^\'"]{3}[^\'"]*([\'"])/', '$1[REDACTED]$2', $m[0] );
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					'[REDACTED LINE]',
					/* translators: %s: redacted match */
					sprintf( __( 'Possible hardcoded credential: %s', 'plugin-auditor' ), (string) $redacted )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks for error reporting suppression.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_error_suppression( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			$trimmed_err = ltrim( $line );
			if ( str_starts_with( $trimmed_err, '//' ) || str_starts_with( $trimmed_err, '*' ) || str_starts_with( $trimmed_err, '#' ) ) {
				continue;
			}

			if ( preg_match( '/error_reporting\s*\(\s*(0|false)\s*\)/', $line )
				&& ! preg_match( '/[\'"].*error_reporting/', $line ) ) {
				$findings[] = $this->finding(
					'HIGH',
					$file,
					$i + 1,
					$line,
					__( 'error_reporting(0) suppresses all errors — can conceal malicious activity.', 'plugin-auditor' )
				);
			}

			if ( preg_match( '/ini_set\s*\(\s*[\'"](?:display_errors|error_reporting)[\'"]/', $line ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$file,
					$i + 1,
					$line,
					__( 'ini_set() used to manipulate error reporting — review intent.', 'plugin-auditor' )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks for obfuscated function calls.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_obfuscation( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			if ( preg_match( '/\$\$\w+\s*\(/', $line ) ) {
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					$line,
					__( 'Variable variable used as callable — potential code execution vector.', 'plugin-auditor' )
				);
			}

			if ( preg_match( '/\$\w+\s*=\s*[\'"](' . implode( '|', self::DANGEROUS_FUNCTIONS ) . ')[\'"]/', $line, $m ) ) {
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					$line,
					/* translators: %s: function name */
					sprintf( __( 'Dangerous function name \'%s\' assigned to a variable — likely obfuscated call.', 'plugin-auditor' ), $m[1] )
				);
			}

			if ( preg_match( '/preg_replace\s*\(.*\/e[\'"]/', $line ) ) {
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					$line,
					__( 'preg_replace() with /e modifier executes matched content as PHP code.', 'plugin-auditor' )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks each PHP file for a direct file access guard at the top.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_direct_access( string $file, array $lines ): array {
		$findings = array();

		$preamble = implode( "\n", array_slice( $lines, 0, 10 ) );

		$has_guard = (bool) preg_match( '/defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\|\|/', $preamble )
			|| (bool) preg_match( '/defined\s*\(\s*[\'"]WP_UNINSTALL_PLUGIN[\'"]\s*\)\s*\|\|/', $preamble );

		if ( ! $has_guard ) {
			$findings[] = $this->finding(
				'HIGH',
				$file,
				0,
				'',
				__( "Missing direct file access guard — add defined( 'ABSPATH' ) || exit; at the top of the file.", 'plugin-auditor' )
			);
		}

		return $findings;
	}

	/**
	 * Checks PHP files for debug output functions.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_debug_output( string $file, array $lines ): array {
		$findings = array();
		$pattern  = '/\b(var_dump|print_r|var_export)\s*\(/';

		foreach ( $lines as $i => $line ) {
			if ( $this->is_comment_line( trim( $line ) ) ) {
				continue;
			}
			if ( preg_match( $pattern, $line, $m ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$file,
					$i + 1,
					$line,
					/* translators: %s: function name */
					sprintf( __( 'Debug output function %s() found — remove before production.', 'plugin-auditor' ), $m[1] )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks JS files for console.log and related debug calls.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_debug_js( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			$trimmed = trim( $line );
			if ( str_starts_with( $trimmed, '//' ) || str_starts_with( $trimmed, '*' ) ) {
				continue;
			}
			if ( preg_match( '/\bconsole\.(log|warn|error|debug|info)\s*\(/', $line, $m ) ) {
				$findings[] = $this->finding(
					'LOW',
					$file,
					$i + 1,
					$line,
					/* translators: %s: method name */
					sprintf( __( 'console.%s() debug call found in JS — remove before production.', 'plugin-auditor' ), $m[1] )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks file permissions.
	 *
	 * @param string $plugin_dir Absolute plugin directory.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_file_permissions( string $plugin_dir ): array {
		$findings = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $plugin_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $item ) {
			$path     = $item->getPathname();
			$rel      = str_replace( $plugin_dir . '/', '', $path );
			$rel_parts = explode( DIRECTORY_SEPARATOR, $rel );
			if ( array_intersect( $rel_parts, self::SKIP_DIRS ) ) {
				continue;
			}

			$perms = fileperms( $path );

			if ( false === $perms ) {
				continue;
			}

			if ( $perms & 0x0002 ) {
				$findings[] = $this->finding(
					'HIGH',
					$rel,
					0,
					'',
					__( 'World-writable file or directory — anyone on the server can modify this.', 'plugin-auditor' )
				);
			}

			if ( $item->isFile() && 'php' === $item->getExtension() && ( $perms & 0x0040 ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$rel,
					0,
					'',
					__( 'PHP file has execute bit set — unnecessary and potentially dangerous.', 'plugin-auditor' )
				);
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Code quality checks
	// -------------------------------------------------------------------------

	/**
	 * Checks for usage of deprecated WordPress functions.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_deprecated_functions( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			if ( $this->is_comment_line( trim( $line ) ) ) {
				continue;
			}

			foreach ( self::DEPRECATED_FUNCTIONS as $func => $since ) {
				if ( preg_match( '/\b' . preg_quote( $func, '/' ) . '\s*\(/', $line ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: 1: function name, 2: WordPress version */
						sprintf( __( 'Deprecated WordPress function %1$s() — deprecated since WP %2$s.', 'plugin-auditor' ), $func, $since )
					);
				}
			}

			// wpdb::escape() is deprecated since WP 3.6.
			if ( preg_match( '/\$wpdb->escape\s*\(/', $line ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$file,
					$i + 1,
					$line,
					__( 'Deprecated method $wpdb->escape() — deprecated since WP 3.6. Use $wpdb->prepare() instead.', 'plugin-auditor' )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks plugin directory structure for index.php sentinels and readme files.
	 *
	 * @param string $plugin_dir Absolute plugin directory.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_plugin_structure( string $plugin_dir ): array {
		$findings = array();

		// Root index.php.
		if ( ! file_exists( $plugin_dir . '/index.php' ) ) {
			$findings[] = $this->finding(
				'LOW',
				'index.php',
				0,
				'',
				__( 'Missing index.php in plugin root — prevents directory listing.', 'plugin-auditor' )
			);
		}

		// Subdirectory index.php files.
		$dir_items = new \DirectoryIterator( $plugin_dir );
		foreach ( $dir_items as $item ) {
			if ( ! $item->isDir() || $item->isDot() ) {
				continue;
			}
			if ( in_array( $item->getFilename(), self::SKIP_DIRS, true ) ) {
				continue;
			}
			$subdir = $item->getPathname();
			$rel    = $item->getFilename();
			if ( ! file_exists( $subdir . '/index.php' ) ) {
				$findings[] = $this->finding(
					'LOW',
					$rel . '/index.php',
					0,
					'',
					/* translators: %s: directory name */
					sprintf( __( 'Missing index.php in %s/ — prevents directory listing.', 'plugin-auditor' ), $rel )
				);
			}
		}

		// Flag presence of readme files — may expose version info.
		foreach ( array( 'readme.txt', 'readme.md', 'README.md', 'README.txt' ) as $readme ) {
			if ( file_exists( $plugin_dir . '/' . $readme ) ) {
				$findings[] = $this->finding(
					'LOW',
					$readme,
					0,
					'',
					/* translators: %s: filename */
					sprintf( __( '%s present in plugin root — may expose version info or known issues.', 'plugin-auditor' ), $readme )
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks declared license for a LICENSE file and GPL compatibility.
	 *
	 * @param string $plugin_dir Absolute plugin directory.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_licensing( string $plugin_dir ): array {
		$findings  = array();
		$main_file = $this->find_main_plugin_file( $plugin_dir );

		if ( null === $main_file ) {
			return $findings;
		}

		$content = (string) file_get_contents( $main_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local filesystem read, not HTTP.

		if ( ! preg_match( '/^\s*\*\s*License:\s*(.+)$/im', $content, $m ) ) {
			return $findings;
		}

		$declared_license = trim( $m[1] );
		$rel              = basename( $main_file );

		$has_license_file = file_exists( $plugin_dir . '/LICENSE' )
			|| file_exists( $plugin_dir . '/LICENSE.txt' )
			|| file_exists( $plugin_dir . '/LICENSE.md' );

		if ( ! $has_license_file ) {
			$findings[] = $this->finding(
				'LOW',
				$rel,
				0,
				'',
				/* translators: %s: license name */
				sprintf( __( 'License "%s" declared in header but no LICENSE or LICENSE.txt file found in plugin root.', 'plugin-auditor' ), $declared_license )
			);
		}

		$lower_license     = strtolower( $declared_license );
		$is_gpl_compatible = false;
		foreach ( self::GPL_COMPATIBLE_LICENSES as $gpl ) {
			if ( str_contains( $lower_license, strtolower( $gpl ) ) ) {
				$is_gpl_compatible = true;
				break;
			}
		}

		if ( ! $is_gpl_compatible ) {
			$findings[] = $this->finding(
				'MEDIUM',
				$rel,
				0,
				'',
				/* translators: %s: license name */
				sprintf( __( 'License "%s" may not be GPL-compatible — WordPress requires plugins distributed via WordPress.org to use a GPL-compatible license.', 'plugin-auditor' ), $declared_license )
			);
		}

		return $findings;
	}

	/**
	 * Checks for PHP 8.x features used when a lower minimum is declared.
	 *
	 * @param string   $file         Relative file path.
	 * @param string[] $lines        File lines.
	 * @param string   $required_php Declared Requires PHP version string.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_php_compat( string $file, array $lines, string $required_php ): array {
		$findings       = array();
		$needs_80_check = version_compare( '8.0', $required_php, '>' );
		$needs_81_check = version_compare( '8.1', $required_php, '>' );

		if ( ! $needs_80_check && ! $needs_81_check ) {
			return $findings;
		}

		foreach ( $lines as $i => $line ) {
			if ( $this->is_comment_line( trim( $line ) ) ) {
				continue;
			}

			if ( $needs_80_check ) {
				if ( preg_match( '/\bmatch\s*\(/', $line ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: %s: declared PHP version */
						sprintf( __( 'match expression requires PHP 8.0 — declared minimum is PHP %s.', 'plugin-auditor' ), $required_php )
					);
				}

				if ( str_contains( $line, '?->' ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: %s: declared PHP version */
						sprintf( __( 'Nullsafe operator (?->) requires PHP 8.0 — declared minimum is PHP %s.', 'plugin-auditor' ), $required_php )
					);
				}

				if ( preg_match( '/\bstr_(contains|starts_with|ends_with)\s*\(/', $line ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: %s: declared PHP version */
						sprintf( __( 'str_contains / str_starts_with / str_ends_with require PHP 8.0 — declared minimum is PHP %s.', 'plugin-auditor' ), $required_php )
					);
				}
			}

			if ( $needs_81_check ) {
				if ( preg_match( '/^\s*enum\s+\w/', $line ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: %s: declared PHP version */
						sprintf( __( 'Enumerations (enum) require PHP 8.1 — declared minimum is PHP %s.', 'plugin-auditor' ), $required_php )
					);
				}

				if ( preg_match( '/\breadonly\s+(?:public|protected|private|string|int|float|bool|array)/', $line ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: %s: declared PHP version */
						sprintf( __( 'Readonly properties require PHP 8.1 — declared minimum is PHP %s.', 'plugin-auditor' ), $required_php )
					);
				}
			}
		}

		return $findings;
	}

	/**
	 * Flags blocks of 5 or more consecutive commented lines.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_commented_code( string $file, array $lines ): array {
		$findings    = array();
		$consecutive = 0;
		$block_start = 0;
		$block_lines = array();

		foreach ( $lines as $i => $line ) {
			if ( $this->is_comment_line( trim( $line ) ) ) {
				if ( 0 === $consecutive ) {
					$block_start = $i + 1;
					$block_lines = array();
				}
				$block_lines[] = trim( $line );
				++$consecutive;
			} else {
				if ( $consecutive >= 5 ) {
					$findings[] = $this->make_commented_block_finding( $file, $block_start, $consecutive, $block_lines );
				}
				$consecutive = 0;
				$block_lines = array();
			}
		}
		if ( $consecutive >= 5 ) {
			$findings[] = $this->make_commented_block_finding( $file, $block_start, $consecutive, $block_lines );
		}

		return $findings;
	}

	/**
	 * Builds a finding for a block of commented-out code.
	 *
	 * @param string   $file        Relative file path.
	 * @param int      $block_start Starting line number.
	 * @param int      $consecutive Number of consecutive comment lines.
	 * @param string[] $block_lines The comment lines.
	 * @return array<string, mixed>
	 */
	private function make_commented_block_finding( string $file, int $block_start, int $consecutive, array $block_lines ): array {
		$sensitive_keys = array( 'password', 'secret', 'api_key', 'select ', 'insert ', 'update ', 'delete ', 'drop ', 'token', 'credential' );
		$block_text     = strtolower( implode( "\n", $block_lines ) );
		$severity       = 'LOW';
		foreach ( $sensitive_keys as $key ) {
			if ( str_contains( $block_text, $key ) ) {
				$severity = 'MEDIUM';
				break;
			}
		}
		return $this->finding(
			$severity,
			$file,
			$block_start,
			'',
			/* translators: %d: number of lines */
			sprintf( __( 'Block of %d consecutive commented lines — review for dead code or sensitive content.', 'plugin-auditor' ), $consecutive )
		);
	}

	/**
	 * Checks the plugin header for required metadata.
	 *
	 * @param string $plugin_dir Absolute plugin directory.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_plugin_header( string $plugin_dir ): array {
		$findings  = array();
		$main_file = $this->find_main_plugin_file( $plugin_dir );

		if ( null === $main_file ) {
			$findings[] = $this->finding( 'HIGH', '', 0, '', __( 'No main plugin file with Plugin Name header found.', 'plugin-auditor' ) );
			return $findings;
		}

		$content  = (string) file_get_contents( $main_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local filesystem read, not HTTP.
		$rel      = basename( $main_file );
		$required = array(
			'Requires PHP'      => __( 'Missing "Requires PHP" header.', 'plugin-auditor' ),
			'Requires at least' => __( 'Missing "Requires at least" header.', 'plugin-auditor' ),
			'License'           => __( 'Missing "License" header.', 'plugin-auditor' ),
			'Author URI'        => __( 'Missing "Author URI" header.', 'plugin-auditor' ),
			'Plugin URI'        => __( 'Missing "Plugin URI" header.', 'plugin-auditor' ),
		);

		foreach ( $required as $header => $message ) {
			if ( ! preg_match( '/' . preg_quote( $header, '/' ) . '\s*:/i', $content ) ) {
				$findings[] = $this->finding( 'LOW', $rel, 0, '', $message );
			}
		}

		return $findings;
	}

	/**
	 * Checks for external HTTP requests.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_external_requests( string $file, array $lines ): array {
		$findings = array();
		$pattern  = '/\b(wp_remote_get|wp_remote_post|wp_remote_request|wp_remote_head|curl_exec|curl_init|file_get_contents)\s*\(/';

		foreach ( $lines as $i => $line ) {
			if ( preg_match( $pattern, $line, $m ) ) {
				// Skip file_get_contents() when it has no HTTP URL — local filesystem read.
				if ( 'file_get_contents' === $m[1] && ! preg_match( '/[\'"]https?:\/\//', $line ) ) {
					continue;
				}

				$url = '';
				if ( preg_match( '/[\'"]https?:\/\/[^\'"]+[\'"]/', $line, $um ) ) {
					$url = $um[0];
				}

				$findings[] = $this->finding(
					'INFO',
					$file,
					$i + 1,
					$line,
					sprintf(
						/* translators: 1: function name, 2: URL or empty */
						__( 'External HTTP request via %1$s%2$s', 'plugin-auditor' ),
						$m[1] . '()',
						$url ? ' — endpoint: ' . $url : ''
					)
				);
			}
		}

		return $findings;
	}

	/**
	 * Checks asset enqueue version arguments.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_asset_versioning( string $file, array $lines ): array {
		$findings = array();
		$pattern  = '/\b(wp_enqueue_script|wp_enqueue_style|wp_register_script|wp_register_style)\s*\(/';

		foreach ( $lines as $i => $line ) {
			if ( ! preg_match( $pattern, $line ) ) {
				continue;
			}

			if ( preg_match( '/,\s*false\s*[,)]/', $line ) ) {
				$findings[] = $this->finding(
					'LOW',
					$file,
					$i + 1,
					$line,
					__( 'Asset enqueued with false version — use a version constant or a file hash.', 'plugin-auditor' )
				);
			}

			if ( preg_match( '/,\s*[\'"][\d.]+[\'"]/', $line ) ) {
				$findings[] = $this->finding(
					'LOW',
					$file,
					$i + 1,
					$line,
					__( 'Asset enqueued with hardcoded version string — use a constant instead.', 'plugin-auditor' )
				);
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Redirect without exit
	// -------------------------------------------------------------------------

	/**
	 * Flags wp_redirect() / wp_safe_redirect() not immediately followed by exit or die.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_redirect_without_exit( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			if ( ! preg_match( '/\bwp_(safe_)?redirect\s*\(/', $line ) ) {
				continue;
			}

			$redirect_pos = (int) strpos( $line, 'redirect' );
			$after        = substr( $line, $redirect_pos );

			if ( preg_match( '/\b(exit|die)\s*[;(]/', $after ) ) {
				continue;
			}

			$next_trimmed = isset( $lines[ $i + 1 ] ) ? trim( $lines[ $i + 1 ] ) : '';
			if ( preg_match( '/^\s*(exit|die)\s*[;(]/', $next_trimmed ) ) {
				continue;
			}

			$findings[] = $this->finding(
				'HIGH',
				$file,
				$i + 1,
				$line,
				__( 'wp_redirect() / wp_safe_redirect() called without an immediately following exit or die — redirect may not terminate execution.', 'plugin-auditor' )
			);
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Role name passed to current_user_can()
	// -------------------------------------------------------------------------

	/**
	 * Flags current_user_can() called with a role name instead of a capability.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_role_checks( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			if ( ! str_contains( $line, 'current_user_can' ) ) {
				continue;
			}

			foreach ( self::WP_ROLE_NAMES as $role ) {
				if ( preg_match( '/current_user_can\s*\(\s*[\'"]' . preg_quote( $role, '/' ) . '[\'"]\s*\)/', $line ) ) {
					$findings[] = $this->finding(
						'HIGH',
						$file,
						$i + 1,
						$line,
						/* translators: %s: role name */
						sprintf( __( 'current_user_can() called with role name "%s" — pass a capability name instead (e.g. "manage_options").', 'plugin-auditor' ), $role )
					);
				}
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Unescaped shortcode output
	// -------------------------------------------------------------------------

	/**
	 * Flags add_shortcode() callbacks that return a variable without escaping.
	 *
	 * @param string   $file    Relative file path.
	 * @param string[] $lines   File lines.
	 * @param string   $content Full file content.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_shortcode_escaping( string $file, array $lines, string $content ): array {
		$findings = array();

		if ( ! str_contains( $content, 'add_shortcode' ) ) {
			return $findings;
		}

		foreach ( $lines as $i => $line ) {
			if ( ! preg_match( '/^\s*return\s+(\$\w+)\s*;/', $line, $m ) ) {
				continue;
			}

			$var = preg_quote( $m[1], '/' );

			$escaped = false;
			foreach ( self::ESCAPE_FUNCTIONS as $fn ) {
				if ( preg_match( '/return\s+' . preg_quote( $fn, '/' ) . '\s*\(\s*' . $var . '\s*\)/', $line ) ) {
					$escaped = true;
					break;
				}
			}

			if ( $escaped ) {
				continue;
			}

			$context_start = max( 0, $i - 20 );
			$context       = implode( "\n", array_slice( $lines, $context_start, $i - $context_start + 1 ) );

			if ( ! str_contains( $context, 'add_shortcode' ) && ! preg_match( '/function\s+\w+\s*\(/', $context ) ) {
				continue;
			}

			if ( str_contains( $context, 'add_shortcode' ) ) {
				$findings[] = $this->finding(
					'MEDIUM',
					$file,
					$i + 1,
					$line,
					/* translators: %s: variable name */
					sprintf( __( 'Shortcode callback returns %s without escaping — wrap in esc_html(), esc_attr(), or wp_kses_post() before returning.', 'plugin-auditor' ), $m[1] )
				);
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Option writes without capability check
	// -------------------------------------------------------------------------

	/**
	 * Flags update_option(), add_option(), delete_option() without a preceding capability check.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_option_writes( string $file, array $lines ): array {
		$findings    = array();
		$write_funcs = array( 'update_option', 'add_option', 'delete_option' );

		foreach ( $lines as $i => $line ) {
			$matched_func = '';
			foreach ( $write_funcs as $fn ) {
				if ( preg_match( '/\b' . $fn . '\s*\(/', $line ) ) {
					$matched_func = $fn;
					break;
				}
			}

			if ( '' === $matched_func ) {
				continue;
			}

			$context_start = max( 0, $i - 30 );
			$context       = implode( "\n", array_slice( $lines, $context_start, $i - $context_start + 1 ) );

			if ( str_contains( $context, 'current_user_can' ) ) {
				continue;
			}

			$findings[] = $this->finding(
				'HIGH',
				$file,
				$i + 1,
				$line,
				/* translators: %s: function name */
				sprintf( __( '%s() called without a preceding current_user_can() check in scope.', 'plugin-auditor' ), $matched_func )
			);
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Wrong wpdb placeholder type
	// -------------------------------------------------------------------------

	/**
	 * Flags $wpdb->prepare() using %s for integer values or %d for string values.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_wpdb_placeholders( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			if ( ! str_contains( $line, 'prepare' ) || ! str_contains( $line, 'wpdb' ) ) {
				continue;
			}

			if ( ! preg_match( '/\$wpdb\s*->\s*prepare\s*\(\s*([\'"].*?[\'"])\s*,(.+)\)/', $line, $m ) ) {
				continue;
			}

			$query        = $m[1];
			$args         = $m[2];
			$placeholders = array();
			preg_match_all( '/%[sd]/', $query, $placeholders );

			if ( empty( $placeholders[0] ) ) {
				continue;
			}

			$arg_list = preg_split( '/,(?![^(]*\))/', $args );
			if ( false === $arg_list ) {
				continue;
			}

			foreach ( $placeholders[0] as $idx => $placeholder ) {
				$arg = isset( $arg_list[ $idx ] ) ? trim( $arg_list[ $idx ] ) : '';

				if ( '%s' === $placeholder && preg_match( '/\babsint\s*\(|^\s*\(int\)|^\s*intval\s*\(|\d+$/', $arg ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: 1: %s placeholder, 2: %d placeholder */
						sprintf( __( '$wpdb->prepare() uses %1$s placeholder for an integer value — use %2$s instead.', 'plugin-auditor' ), '%s', '%d' )
					);
				}

				if ( '%d' === $placeholder && preg_match( '/[\'"]/', $arg ) ) {
					$findings[] = $this->finding(
						'MEDIUM',
						$file,
						$i + 1,
						$line,
						/* translators: 1: %d placeholder, 2: %s placeholder */
						sprintf( __( '$wpdb->prepare() uses %1$s placeholder for a string value — use %2$s instead.', 'plugin-auditor' ), '%d', '%s' )
					);
				}
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Duplicate hook registrations (cross-file, collected then checked)
	// -------------------------------------------------------------------------

	/**
	 * Collects all add_action() / add_filter() calls from a file for cross-file duplicate detection.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_hook_calls( string $file, array $lines ): array {
		$calls = array();

		foreach ( $lines as $i => $line ) {
			if ( ! preg_match( '/\b(add_action|add_filter)\s*\(\s*([\'"][^\'"]+[\'"])\s*,\s*([^,)]+)(?:,\s*(\d+))?/', $line, $m ) ) {
				continue;
			}

			$calls[] = array(
				'file'     => $file,
				'line'     => $i + 1,
				'snippet'  => $line,
				'function' => $m[1],
				'hook'     => trim( $m[2], '\'"' ),
				'callback' => trim( $m[3] ),
				'priority' => isset( $m[4] ) ? $m[4] : '10',
			);
		}

		return $calls;
	}

	/**
	 * Flags identical add_action/add_filter registrations (same hook, callback, priority) appearing more than once.
	 *
	 * @param array<int, array<string, mixed>> $all_calls Collected hook calls from all files.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_duplicate_hooks( array $all_calls ): array {
		$findings = array();
		$seen     = array();

		foreach ( $all_calls as $call ) {
			$key = $call['function'] . '|' . $call['hook'] . '|' . $call['callback'] . '|' . $call['priority'];
			if ( isset( $seen[ $key ] ) ) {
				$findings[] = $this->finding(
					'LOW',
					$call['file'],
					$call['line'],
					$call['snippet'],
					/* translators: 1: function name e.g. add_action, 2: hook name, 3: file path, 4: line number */
					sprintf(
						/* translators: 1: function, 2: hook, 3: file, 4: line */
						esc_html__( 'Duplicate %1$s() registration — hook "%2$s" with same callback and priority already registered in %3$s on line %4$s.', 'plugin-auditor' ),
						$call['function'],
						$call['hook'],
						$seen[ $key ]['file'],
						(string) $seen[ $key ]['line']
					)
				);
			} else {
				$seen[ $key ] = $call;
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Unnecessary closures
	// -------------------------------------------------------------------------

	/**
	 * Flags closures passed to add_action/add_filter that only return true or false.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_unnecessary_closures( string $file, array $lines ): array {
		$findings = array();

		foreach ( $lines as $i => $line ) {
			if ( ! preg_match( '/\b(add_action|add_filter)\s*\(/', $line ) ) {
				continue;
			}

			if ( ! str_contains( $line, 'function' ) && ! str_contains( $line, 'fn(' ) && ! str_contains( $line, 'fn (' ) ) {
				continue;
			}

			if ( preg_match( '/function\s*\(\s*\)\s*\{\s*return\s+(true|false)\s*;\s*\}/', $line, $m )
				|| preg_match( '/fn\s*\(\s*\)\s*=>\s*(true|false)\b/', $line, $m )
			) {
				$replacement = 'true' === $m[1] ? '__return_true' : '__return_false';
				$findings[]  = $this->finding(
					'LOW',
					$file,
					$i + 1,
					$line,
					/* translators: %s: recommended function name */
					sprintf( __( 'Unnecessary closure — replace with %s.', 'plugin-auditor' ), $replacement )
				);
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Early translation calls
	// -------------------------------------------------------------------------

	/**
	 * Flags translation functions called at file scope or before init fires.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_early_translations( string $file, array $lines ): array {
		$findings    = array();
		$in_function = 0;
		$in_class    = 0;
		$brace_depth = 0;

		foreach ( $lines as $i => $line ) {
			$opens  = substr_count( $line, '{' );
			$closes = substr_count( $line, '}' );

			if ( preg_match( '/^\s*class\s+\w+/', $line ) ) {
				++$in_class;
			}

			if ( preg_match( '/\bfunction\s+\w+\s*\(/', $line ) || preg_match( '/\bfunction\s*\(/', $line ) ) {
				++$in_function;
			}

			$brace_depth += $opens - $closes;

			if ( $brace_depth < 0 ) {
				$brace_depth = 0;
			}

			if ( 0 === $in_function && 0 === $in_class ) {
				foreach ( self::EARLY_TRANSLATION_FUNCTIONS as $fn ) {
					if ( preg_match( '/\b' . preg_quote( $fn, '/' ) . '\s*\(/', $line ) ) {
						$findings[] = $this->finding(
							'LOW',
							$file,
							$i + 1,
							$line,
							/* translators: %s: function name */
							sprintf( __( '%s() called at file scope before init — text domain may not be loaded yet. Wrap in an init or plugins_loaded hook callback.', 'plugin-auditor' ), $fn )
						);
					}
				}
			}

			if ( $closes > 0 && $brace_depth <= ( $in_class > 0 ? 1 : 0 ) ) {
				if ( $in_function > 0 ) {
					--$in_function;
				}
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------------
	// Scoring
	// -------------------------------------------------------------------------

	/**
	 * Builds a normalised finding array.
	 *
	 * @param string $severity CRITICAL|HIGH|MEDIUM|LOW|INFO.
	 * @param string $file     Relative file path.
	 * @param int    $line     Line number (0 = file-level).
	 * @param string $snippet  Code snippet.
	 * @param string $message  Human-readable message.
	 * @return array<string, mixed>
	 */
	private function finding( string $severity, string $file, int $line, string $snippet, string $message ): array {
		return array(
			'severity' => $severity,
			'file'     => $file,
			'line'     => $line,
			'snippet'  => trim( $snippet ),
			'message'  => $message,
		);
	}

	/**
	 * Calculates a numeric risk score from security section findings only.
	 *
	 * @param array<string, mixed> $findings Section findings.
	 */
	private function calculate_score( array $findings ): int {
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
	 * Converts a numeric score to a risk rating.
	 *
	 * @param int $score Numeric score.
	 */
	private function calculate_rating( int $score ): string {
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
