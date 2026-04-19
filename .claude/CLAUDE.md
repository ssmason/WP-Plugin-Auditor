# WordPress Plugin Auditor — Claude Guidelines

## Non-Negotiable Rules

- Do not proceed if requirements are ambiguous on any security-critical behaviour
- Do not write partial implementations and note gaps as "good enough for now"
- Do not write code and then list its known gaps — resolve them first or stop and ask
- Do not skip any rule below "for brevity"
- If a complete, correct implementation is not possible within the current scope, say so

---

## Project Overview

A WordPress plugin that audits other installed plugins for security issues, coding standards violations, and permissions. Triggered from the Plugins page action row. Results displayed in an inline modal with a progress indicator while the audit runs in the background via AJAX. Reports stored as a Custom Post Type. Full report visible in modal. Downloadable as PDF via browser print. User can return to previous reports.

### Key Behaviours
- "Audit" action link appears in every plugin row on the Plugins page
- Clicking opens an inline modal immediately — audit runs in background via AJAX
- Progress indicator shown while audit is running
- Report covers everything — findings AND confirmed passes
- Report saved as `pla_report` CPT post on completion
- PDF download via dedicated print stylesheet and `window.print()`
- Previous reports accessible and returnable to

---

## PHP Standards

- WordPress Coding Standards (WPCS) at all times
- PHP >= 8.1
- `declare( strict_types=1 )` in every PHP file
- Namespaced: `PluginAuditor\` — PSR-4 autoloading via Composer
- Every file begins with `defined( 'ABSPATH' ) || exit;`
- No `@` error suppression
- No `extract()`
- No `eval()`, `exec()`, `shell_exec()`, `passthru()`, `system()`, `proc_open()`, `popen()`
- No `create_function()`
- No `base64_decode()` on dynamic input
- No `unserialize()` on untrusted data — use `maybe_unserialize()` only on data from WP storage
- Early returns over nested conditionals
- Methods do one thing
- No helper functions unless used 3+ times

---

## Security

### Input Sanitization
- Every `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, `$_FILES` access must be sanitized
- Unslash before sanitizing: `sanitize_text_field( wp_unslash( $_POST['field'] ) )`
- For arrays: `array_map( 'sanitize_text_field', wp_unslash( $_POST['arr'] ) )`
- Sanitize at the point of reading, not later
- Use the most specific sanitizer:
  - `sanitize_text_field()` — plain text
  - `sanitize_email()` — email addresses
  - `sanitize_url()` — URLs
  - `absint()` / `intval()` — integers
  - `sanitize_key()` — keys, slugs
  - `sanitize_html_class()` — CSS classes
  - `wp_kses_post()` — rich HTML content
  - `sanitize_file_name()` — file names

### Output Escaping — Escape Late, Escape at Output
- Escape at the point of output, never at assignment
- Use context-correct escaping:
  - HTML content → `esc_html()`
  - HTML attributes → `esc_attr()`
  - URLs (href, src) → `esc_url()`
  - JavaScript → `esc_js()`
  - SQL → `$wpdb->prepare()`
  - Textarea content → `esc_textarea()`
- Translation strings:
  - Never `echo __( 'string', 'domain' )`
  - Use `esc_html_e()`, `esc_attr_e()`, `esc_html__()`, `esc_attr__()`
- Never `echo $variable` without escaping
- Never `echo $wpdb->get_var(...)` directly

### Nonces — Forms
- Every form must output `wp_nonce_field( 'action_name', 'nonce_field_name' )`
- Every form handler must call `check_admin_referer( 'action_name', 'nonce_field_name' )` before anything else
- Nonce action names must be specific — never generic like `'save'`

### Nonces — AJAX
- Enqueue nonce via `wp_localize_script()` or `wp_add_inline_script()`
- Every AJAX handler verifies nonce with `wp_verify_nonce()` before processing
- Both `wp_ajax_{action}` and `wp_ajax_nopriv_{action}` must each verify independently

### Nonces — GET Actions
- Any GET-based action must verify a nonce
- Never trust `$_GET` action parameters without nonce verification

### Capabilities
- Check `current_user_can()` before any privileged action
- Use the most restrictive capability applicable — `manage_options` for all auditor actions
- Never rely on `is_admin()` alone as a security check
- Admin pages must check capability in the page callback, not just at registration

### Database
- Never concatenate user input into SQL
- Always use `$wpdb->prepare()` for queries with any variable
- Use `$wpdb->insert()`, `$wpdb->update()`, `$wpdb->delete()` where possible
- Never use `$wpdb->query()` with raw user input
- No deprecated `mysql_*` or direct `mysqli_*` calls

### Redirects
- Always use `wp_safe_redirect()` — never `wp_redirect()` with user-supplied URLs
- Always call `exit` after any redirect
- Validate redirect URLs against an allowlist if dynamic

### File Handling
- Never include files from user-supplied paths
- Validate file types on upload — use `wp_check_filetype_and_ext()`
- Use `wp_handle_upload()` for all file uploads
- Never expose upload paths from user input

### Arbitrary File Inclusion
- Never use user input in `include`, `require`, `include_once`, `require_once`
- Never use `file_get_contents()` with user-supplied paths

### AJAX Endpoint Security
- Rate limit sensitive AJAX endpoints using transients
- Return `wp_die()` with appropriate HTTP status on failure
- Always use `wp_send_json_success()` / `wp_send_json_error()` for JSON responses

---

## REST API

- Every route must define a `permission_callback` — never `__return_true` on privileged routes
- Use `register_rest_route()` with full `args` schema: `type`, `required`, `sanitize_callback`, `validate_callback`
- Return `WP_Error` on failure
- Use `rest_ensure_response()` to wrap responses

---

## JavaScript Security

- Never use `innerHTML` with dynamic data — use `textContent` or `wp.escapeHtml()`
- Never use `eval()` or `new Function()` with dynamic strings
- No inline event handlers (`onclick=""`) — use `addEventListener`
- All AJAX requests must send the nonce in the request header or body
- Nonces passed via `wp_localize_script()` — never hardcoded in JS files
- Validate and sanitize any data received from REST or AJAX responses before rendering to DOM
- No direct `document.write()`

---

## Scanner — What Must Be Checked

The scanner performs static analysis on every PHP file in the audited plugin. Every check must report both findings AND confirmed passes.

### Dangerous Functions
Flag any use of: `eval`, `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `base64_decode`, `base64_encode`, `str_rot13`, `gzinflate`, `gzuncompress`, `assert`, `create_function`, `call_user_func`, `call_user_func_array`

### Obfuscated Function Calls
- Flag dynamic function calls where a variable is assigned a function name and then invoked: `$a = 'eval'; $a(...)`
- Flag variable variables used as callables: `$$func()`
- Flag `preg_replace` with `/e` modifier — executes matched content as PHP

### Output Escaping — Multi-Line Taint Tracking
- Track variables from assignment through to output across multiple lines
- Flag any variable echoed or printed without a context-correct escape function at the point of output
- Flag wrong escape function for context — e.g. `esc_html()` on a URL, `esc_attr()` on HTML content
- Flag `esc_html( $var )` assigned to `$x` then `echo $x` — escaping must be at output not assignment
- Flag `_e()` and `__()` used without escaping — require `esc_html_e()` / `esc_attr_e()`
- Flag `$_SERVER['PHP_SELF']` or `$_SERVER['REQUEST_URI']` used in form actions without `esc_url()`

### Input Sanitization — Multi-Line Taint Tracking
- Track `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, `$_FILES` from point of access through reassignment to use
- Flag any use of a superglobal-derived value without sanitization applied at point of reading
- Flag missing `wp_unslash()` before sanitization
- Flag array superglobal access without `array_map` sanitization

### Nonce Verification
- Flag files handling `$_POST` or AJAX without any `wp_verify_nonce()` or `check_admin_referer()`
- Flag GET-based action handlers without nonce verification
- Flag forms rendered without `wp_nonce_field()`

### Capability Checks
- Flag admin pages registered without `current_user_can()` in the callback
- Flag any write operation (option save, post save, file write) without a preceding capability check
- Report all capabilities used — `current_user_can()` calls — as informational

### Database Queries
- Flag any `$wpdb->query()`, `$wpdb->get_results()`, `$wpdb->get_row()`, `$wpdb->get_var()`, `$wpdb->get_col()` called with a string that is not wrapped in `$wpdb->prepare()`
- Flag any raw `mysql_*` or `mysqli_*` calls
- Flag direct string concatenation into any query

### Hardcoded Credentials
- Flag patterns matching hardcoded passwords, API keys, tokens, secrets, private keys
- Redact the value in the report — never expose the actual credential

### External HTTP Requests
- Flag all outbound calls: `wp_remote_get`, `wp_remote_post`, `wp_remote_request`, `wp_remote_head`, `curl_exec`, `curl_init`, `file_get_contents` used as HTTP
- Report URL/endpoint where visible — informational, not necessarily a violation

### Asset Versioning
- Flag `wp_enqueue_script()` or `wp_enqueue_style()` calls with hardcoded version strings or `false` as version — potential cache busting failure and version info exposure

### Error Suppression / Reporting Manipulation
- Flag `error_reporting(0)` or `error_reporting( false )`
- Flag `ini_set( 'display_errors', ... )` or `ini_set( 'error_reporting', ... )`
- These can be used to suppress evidence of malicious activity

### File Permissions
- Flag world-writable files or directories
- Flag PHP files with execute bit set

### Plugin Header / Metadata
- Flag missing: `Requires PHP`, `Requires at least`, `License`, `Author URI`, `Plugin URI`

### Confirmed Passes
- For every check above, if no issues found, report explicitly as passed — do not omit

---

## Report

- Every finding includes: severity, file, line number, code snippet (credentials redacted), message
- Every section includes a pass confirmation if no issues found
- Severity levels: `CRITICAL`, `HIGH`, `MEDIUM`, `LOW`, `INFO`
- Overall risk score calculated from weighted severity counts
- Overall risk rating: `CRITICAL`, `HIGH`, `MEDIUM`, `LOW`, `CLEAN`
- Report stored as `pla_report` CPT with full findings serialized as post meta
- Report rendered in inline modal on Plugins page
- PDF download via `window.print()` with dedicated print stylesheet

### Large Findings — Meta Size Strategy
- Chunk findings by section into separate meta keys: `_pla_findings_dangerous`, `_pla_findings_output`, `_pla_findings_input`, etc.
- Never store all findings in a single meta key
- On retrieval, reassemble from individual section keys

---

## Custom Post Type — `pla_report`

- Post type: `pla_report`
- Not public, not shown in UI nav — admin only
- Capability: `manage_options` required to create, read, and delete reports
- Post title: audited plugin name + datetime
- Post meta — stored in separate keys per section (see Large Findings strategy above):
  - `_pla_plugin_file` — relative plugin file path
  - `_pla_plugin_name` — plugin display name
  - `_pla_findings_dangerous` — dangerous function findings
  - `_pla_findings_output` — output escaping findings
  - `_pla_findings_input` — input sanitization findings
  - `_pla_findings_nonces` — nonce findings
  - `_pla_findings_capabilities` — capability findings
  - `_pla_findings_database` — database query findings
  - `_pla_findings_credentials` — credential findings
  - `_pla_findings_requests` — external request findings
  - `_pla_findings_permissions` — file permission findings
  - `_pla_findings_meta` — plugin header findings
  - `_pla_findings_assets` — asset versioning findings
  - `_pla_findings_errors` — error suppression findings
  - `_pla_findings_obfuscation` — obfuscated call findings
  - `_pla_risk` — overall risk rating
  - `_pla_score` — numeric risk score
  - `_pla_version` — auditor version that generated the report
- Report retention: cap stored reports at 20 per audited plugin — delete oldest on creation of new
- Previous reports listed and accessible from the modal and the auditor admin page
- Multisite: auditor only audits plugins available on the current site — no cross-site auditing

---

## Modal Behaviour

- Opens immediately on "Audit" link click — no page navigation
- Shows progress indicator while AJAX audit runs
- On completion replaces progress indicator with full report
- Report scrollable within modal
- Modal closeable — report retrievable from CPT afterwards
- PDF download button visible in modal — triggers `window.print()`
- Print stylesheet hides modal chrome, outputs report only
- AJAX timeout defined explicitly — if audit exceeds timeout, show error message with retry option
- If audit is triggered on a plugin already being audited, block the second request and inform the user
- Modal element must have `role="dialog"`, `aria-modal="true"`, `aria-labelledby` referencing the report title
- Focus must be trapped inside modal while open
- Modal must close on Escape key
- Focus must return to the triggering element on close

### Print Stylesheet — print.css
- `@page` rule must define margins and `size: A4`
- Hide modal chrome, close button, progress indicator, PDF download button
- Page break rules: avoid breaking inside finding rows, section headings always start on same page as first row
- Ensure all text is black on white — remove background colours

---

## WordPress Patterns

- Hook registration inside `init()` method or constructor — never at file scope
- Prefix all: global functions, options, transients, post meta keys with `pla_`
- `add_action` / `add_filter` — always specify `$priority` and `$accepted_args` explicitly when non-default
- Transients for caching — always set expiry, never indefinite
- HTTP requests: always `wp_remote_*` — never `curl_*` or `file_get_contents()` for HTTP
- `wp_die()` with correct HTTP status codes on failure

---

## Performance

- Never run queries inside loops
- Use `'no_found_rows' => true` on WP_Query when pagination not needed
- Use `'fields' => 'ids'` when only IDs required
- Cache expensive operations with transients — define clear expiry strategy

---

## Enqueue Standards

- Never enqueue assets globally — only on relevant admin screens
- Always provide version argument — use `PLUGIN_AUDITOR_VERSION` constant
- Use loading strategy (`defer`) where appropriate
- Never bundle jQuery — use WP-bundled version via dependency array
- No inline `<script>` or `<style>` tags — use `wp_add_inline_script()` / `wp_add_inline_style()`

---

## Internationalization

- Text domain: `plugin-auditor`
- Every user-facing string wrapped for translation
- Never concatenate translated strings — use `sprintf()` with placeholders
- Translator comments required for strings with placeholders
- Never use variables as text domain

---

## Multisite

- Use `switch_to_blog()` / `restore_current_blog()` in `finally` block when accessing other site data
- `is_multisite()` checks before any multisite-specific code

---

## Plugin Lifecycle

- Activation: create CPT, set default options, flush rewrite rules
- Deactivation: flush rewrite rules, clear scheduled events
- Uninstall: delete all `pla_report` posts and meta, all `pla_` options and transients
- Database upgrades: compare stored `pla_db_version`, run migrations, update version

---

## Accessibility

- All form fields must have associated `<label>` elements
- Use `aria-describedby` for field descriptions and error messages
- Modal must be keyboard navigable and focus-trapped while open
- Modal must be closeable via Escape key
- All interactive elements keyboard accessible
- Colour never the sole means of conveying information

---

## Dependencies / Third-Party Libraries

- Vet every third-party library — check maintenance status, CVE history, license
- GPL-compatible licenses only
- Vendor via Composer — never copy-paste library code
- Never ship dev dependencies — `composer install --no-dev` for production
- Pin versions in `composer.lock` — commit the lockfile
- Never bundle libraries already provided by WordPress core

---

## File Structure

```
plugin-auditor/
├── plugin-auditor.php          # Header, constants, bootstrap only
├── CLAUDE.md
├── README.md
├── .wp-env.json
├── phpunit.xml.dist
├── phpcs.xml.dist
├── phpstan.neon.dist           # Level 8
├── composer.json
├── composer.lock
├── package.json
├── package-lock.json
├── includes/
│   ├── class-admin.php         # Hooks, action links, menu
│   ├── class-ajax.php          # AJAX handlers
│   ├── class-cpt.php           # pla_report CPT registration
│   ├── class-modal.php         # Modal markup and JS enqueue
│   ├── class-scanner.php       # File analysis engine
│   └── class-report.php        # Report renderer
├── assets/
│   ├── css/
│   │   ├── modal.css
│   │   └── print.css           # Print/PDF stylesheet
│   └── js/
│       └── auditor.js          # Modal, AJAX, progress, print
├── templates/
│   ├── modal.php
│   └── report.php
├── languages/
│   └── plugin-auditor.pot
└── tests/
    ├── bootstrap.php
    ├── Unit/
    │   ├── ScannerTest.php
    │   ├── ReportTest.php
    │   └── ...
    └── Integration/
        ├── CptTest.php
        ├── AjaxTest.php
        └── ...
```

---

## Composer

```json
{
  "require": {},
  "require-dev": {
    "squizlabs/php_codesniffer": "^3",
    "wp-coding-standards/wpcs": "^3",
    "phpcompatibility/phpcompatibility-wp": "*",
    "phpstan/phpstan": "^1",
    "szepeviktor/phpstan-wordpress": "^1",
    "phpunit/phpunit": "^9",
    "brain/monkey": "^2",
    "mockery/mockery": "^1"
  },
  "autoload": {
    "psr-4": { "PluginAuditor\\": "includes/" }
  },
  "autoload-dev": {
    "psr-4": { "PluginAuditor\\Tests\\": "tests/" }
  }
}
```

---

## PHPCS — phpcs.xml.dist

- Extends `WordPress-Core`, `WordPress-Docs`, `WordPress-Extra`
- `PHPCompatibilityWP` with `testVersion` set to `8.1-`
- No blanket exclusions — exclude only with documented justification

---

## PHPStan — phpstan.neon.dist

- Level 8
- Include `szepeviktor/phpstan-wordpress` extension
- Baseline only for unavoidable third-party issues — never to suppress own code

---

## Testing

### General Rules
- Every class must have a corresponding test class
- Tests must be independent — no shared mutable state
- No production database, filesystem, or network calls in unit tests
- Test names describe behaviour: `test_flags_unescaped_echo_on_variable()`
- Every scanner check must have tests for: finding present, finding absent, edge cases

### Unit Tests — `tests/Unit/`
- Brain Monkey for WP function mocking
- Mockery for class/interface mocking
- Bootstrap Brain Monkey in `setUp()`, tear down in `tearDown()`
- One behaviour per test method
- Cover: happy path, boundary conditions, invalid input, missing input, multi-line taint cases

### Integration Tests — `tests/Integration/`
- Extend `WP_UnitTestCase`
- Use WP factory methods for test data
- Test actual DB interactions, AJAX handlers, CPT storage, hook firing
- Clean up all created data in `tearDown()`

### wp-env — `.wp-env.json`
```json
{
  "core": "WordPress/WordPress#trunk",
  "phpVersion": "8.1",
  "plugins": [ "." ],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "SCRIPT_DEBUG": true
  },
  "testsConfig": {
    "WP_DEBUG": false
  }
}
```

### package.json Scripts
```json
{
  "scripts": {
    "env:start": "wp-env start",
    "env:stop": "wp-env stop",
    "env:clean": "wp-env clean",
    "test:unit": "phpunit --configuration phpunit.xml.dist --testsuite unit",
    "test:integration": "wp-env run tests-cli phpunit --configuration phpunit.xml.dist --testsuite integration",
    "lint:php": "phpcs",
    "lint:fix": "phpcbf",
    "analyze": "phpstan analyse"
  }
}
```