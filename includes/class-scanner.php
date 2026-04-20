<?php
/**
 * Static analysis engine — scans PHP files in a plugin directory.
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
	 * Functions that constitute output without escaping.
	 */
	private const OUTPUT_FUNCTIONS = array( 'echo', 'print', 'printf', 'vprintf', 'print_r', 'var_dump', 'var_export' );

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
	 * Runs the full audit on all PHP files in a directory.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @return array<string, mixed> Findings keyed by section.
	 */
	public function scan( string $plugin_dir ): array {
		$php_files = $this->collect_php_files( $plugin_dir );

		$findings = array(
			'dangerous'    => array(),
			'output'       => array(),
			'input'        => array(),
			'nonces'       => array(),
			'capabilities' => array(),
			'database'     => array(),
			'credentials'  => array(),
			'requests'     => array(),
			'permissions'  => array(),
			'meta'         => array(),
			'assets'       => array(),
			'errors'       => array(),
			'obfuscation'  => array(),
		);

		foreach ( $php_files as $file ) {
			$lines   = file( $file, FILE_IGNORE_NEW_LINES );
			$content = implode( "\n", $lines );
			$rel     = str_replace( $plugin_dir . '/', '', $file );

			$findings['dangerous']    = array_merge( $findings['dangerous'], $this->check_dangerous_functions( $rel, $lines ) );
			$findings['output']       = array_merge( $findings['output'], $this->check_output_escaping( $rel, $lines ) );
			$findings['input']        = array_merge( $findings['input'], $this->check_input_sanitization( $rel, $lines ) );
			$findings['nonces']       = array_merge( $findings['nonces'], $this->check_nonces( $rel, $lines, $content ) );
			$findings['capabilities'] = array_merge( $findings['capabilities'], $this->check_capabilities( $rel, $lines ) );
			$findings['database']     = array_merge( $findings['database'], $this->check_database( $rel, $lines ) );
			$findings['credentials']  = array_merge( $findings['credentials'], $this->check_credentials( $rel, $lines ) );
			$findings['requests']     = array_merge( $findings['requests'], $this->check_external_requests( $rel, $lines ) );
			$findings['assets']       = array_merge( $findings['assets'], $this->check_asset_versioning( $rel, $lines ) );
			$findings['errors']       = array_merge( $findings['errors'], $this->check_error_suppression( $rel, $lines ) );
			$findings['obfuscation']  = array_merge( $findings['obfuscation'], $this->check_obfuscation( $rel, $lines ) );
		}

		$findings['permissions'] = $this->check_file_permissions( $plugin_dir );
		$findings['meta']        = $this->check_plugin_header( $plugin_dir );

		$findings['score']  = $this->calculate_score( $findings );
		$findings['rating'] = $this->calculate_rating( (int) $findings['score'] );

		return $findings;
	}

	/**
	 * Collects all PHP files in the plugin directory recursively.
	 *
	 * @param string $dir Absolute path.
	 * @return string[] Absolute file paths.
	 */
	private function collect_php_files( string $dir ): array {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		$files = array();
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

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
		$findings = array();

		// Track assigned variables for multi-line taint detection.
		$tainted_vars = array();

		foreach ( $lines as $i => $line ) {
			$trimmed = trim( $line );

			// Detect tainted variable assignments from superglobals.
			foreach ( self::SUPERGLOBALS as $global ) {
				if ( str_contains( $line, '$' . $global ) ) {
					if ( preg_match( '/\$(\w+)\s*=.*\$' . preg_quote( $global, '/' ) . '/i', $line, $m ) ) {
						$tainted_vars[ '$' . $m[1] ] = true;
					}
				}
			}

			// Flag _e() and __() used without escaping.
			if ( preg_match( '/\b(echo\s+)?__\s*\(/', $line ) && ! str_contains( $line, 'esc_' ) ) {
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

			// Flag PHP_SELF / REQUEST_URI in form actions without esc_url.
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
			foreach ( self::SUPERGLOBALS as $global ) {
				if ( ! str_contains( $line, '$' . $global . '[' ) ) {
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
		$has_ajax_handler  = str_contains( $content, 'wp_ajax_' );
		$has_get_action    = preg_match( '/\$_GET\[.action.\]/', $content );

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

		foreach ( $lines as $i => $line ) {
			// Report capability in use.
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

			// Flag add_menu_page / add_submenu_page registered with no capability check in callback (best effort).
			if ( preg_match( '/add_(menu|submenu|management|options|dashboard|posts|media|links|pages|comments|theme|plugins|users|tools|settings)_page\s*\(/', $line ) ) {
				$info[] = $this->finding(
					'INFO',
					$file,
					$i + 1,
					$line,
					__( 'Admin page registered — ensure callback checks current_user_can() before rendering.', 'plugin-auditor' )
				);
			}

			// Flag option saves, post saves without preceding capability checks.
			if ( preg_match( '/\b(update_option|add_option|wp_insert_post|wp_update_post|delete_option)\s*\(/', $line ) ) {
				// Check nearby lines (within 10 lines above) for a capability check.
				$start   = max( 0, $i - 10 );
				$context = implode( "\n", array_slice( $lines, $start, $i - $start + 1 ) );
				if ( ! str_contains( $context, 'current_user_can' ) ) {
					$findings[] = $this->finding(
						'HIGH',
						$file,
						$i + 1,
						$line,
						__( 'Write operation without a visible current_user_can() check in the preceding 10 lines.', 'plugin-auditor' )
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
		$findings = array();

		$wpdb_methods = array( 'query', 'get_results', 'get_row', 'get_var', 'get_col' );
		$pattern      = '/\$wpdb->(' . implode( '|', $wpdb_methods ) . ')\s*\(/';

		foreach ( $lines as $i => $line ) {
			// Flag raw mysql_* / mysqli_* calls.
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

			// Allow if the argument is wrapped in prepare().
			if ( str_contains( $line, '$wpdb->prepare(' ) ) {
				continue;
			}

			// Flag string concatenation into query.
			if ( preg_match( '/\.\s*\$/', $line ) || preg_match( '/\$wpdb->' . $m[1] . '\s*\(\s*["\']/', $line ) ) {
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

		$pattern = '/(?:password|passwd|pwd|secret|api_key|apikey|token|auth_key|access_key|private_key)\s*[=:]\s*[\'"][^\'"]{6,}[\'"]/i';

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
	 * Checks for external HTTP requests.
	 *
	 * @param string   $file  Relative file path.
	 * @param string[] $lines File lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_external_requests( string $file, array $lines ): array {
		$findings = array();

		$pattern = '/\b(wp_remote_get|wp_remote_post|wp_remote_request|wp_remote_head|curl_exec|curl_init|file_get_contents)\s*\(/';

		foreach ( $lines as $i => $line ) {
			if ( preg_match( $pattern, $line, $m ) ) {
				// Extract URL if visible.
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

		$pattern = '/\b(wp_enqueue_script|wp_enqueue_style|wp_register_script|wp_register_style)\s*\(/';

		foreach ( $lines as $i => $line ) {
			if ( ! preg_match( $pattern, $line ) ) {
				continue;
			}

			// Flag false as version.
			if ( preg_match( '/,\s*false\s*[,)]/', $line ) ) {
				$findings[] = $this->finding(
					'LOW',
					$file,
					$i + 1,
					$line,
					__( 'Asset enqueued with false version — use PLUGIN_AUDITOR_VERSION constant or a file hash.', 'plugin-auditor' )
				);
			}

			// Flag hardcoded version string like '1.2.3'.
			if ( preg_match( '/,\s*[\'"][\d\.]+[\'"]/', $line ) ) {
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
			if ( preg_match( '/error_reporting\s*\(\s*(0|false)\s*\)/', $line ) ) {
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
			// Variable variable callables: $$func().
			if ( preg_match( '/\$\$\w+\s*\(/', $line ) ) {
				$findings[] = $this->finding(
					'CRITICAL',
					$file,
					$i + 1,
					$line,
					__( 'Variable variable used as callable — potential code execution vector.', 'plugin-auditor' )
				);
			}

			// Detect dangerous function name assigned to a variable for later invocation.
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

			// preg_replace with /e modifier.
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
			$path  = $item->getPathname();
			$rel   = str_replace( $plugin_dir . '/', '', $path );
			$perms = fileperms( $path );

			if ( false === $perms ) {
				continue;
			}

			// World-writable.
			if ( $perms & 0x0002 ) {
				$findings[] = $this->finding(
					'HIGH',
					$rel,
					0,
					'',
					__( 'World-writable file or directory — anyone on the server can modify this.', 'plugin-auditor' )
				);
			}

			// PHP file with execute bit.
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

	/**
	 * Checks the plugin header for required metadata.
	 *
	 * @param string $plugin_dir Absolute plugin directory.
	 * @return array<int, array<string, mixed>>
	 */
	private function check_plugin_header( string $plugin_dir ): array {
		$findings = array();

		// Find the main plugin file.
		$main_file = null;
		foreach ( glob( $plugin_dir . '/*.php' ) as $f ) {
			$content = file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local filesystem read, not HTTP.
			if ( $content && str_contains( $content, 'Plugin Name:' ) ) {
				$main_file = $f;
				break;
			}
		}

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
	 * Calculates a numeric risk score from weighted findings.
	 *
	 * @param array<string, mixed> $findings Section findings.
	 */
	private function calculate_score( array $findings ): int {
		$score    = 0;
		$sections = array( 'dangerous', 'output', 'input', 'nonces', 'capabilities', 'database', 'credentials', 'permissions', 'errors', 'obfuscation' );

		foreach ( $sections as $section ) {
			if ( ! is_array( $findings[ $section ] ) ) {
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
