# Plugin Auditor

A WordPress plugin that audits other installed plugins for security issues, coding standards violations, and file permissions. Triggered from the Plugins page action row. Results display in an inline modal with a live progress indicator while the audit runs via AJAX in the background. Reports are stored as a Custom Post Type and can be downloaded as PDF via the browser print dialog.

---

## Requirements

- PHP 8.1+
- WordPress 6.4+
- Composer
- Node.js + npm (for `@wordpress/env` and ESLint)

---

## Installation

```bash
git clone <repo-url> wp-content/plugins/plugin-auditor
cd wp-content/plugins/plugin-auditor
composer install --no-dev
```

Activate the plugin from the WordPress admin Plugins screen.

---

## Development Setup

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Start the local environment

Uses [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (Docker required).

```bash
npm run env:start
```

This starts two containers:

| Container | URL | Purpose |
|---|---|---|
| `wordpress` | http://localhost:8888 | Development site |
| `tests-wordpress` | http://localhost:8889 | Integration test site |

WordPress trunk is used with PHP 8.1. The plugin is automatically mounted.

### 3. Stop / reset the environment

```bash
npm run env:stop     # stop containers
npm run env:clean    # destroy and recreate (wipes data)
```

---

## Usage

1. Go to **Plugins** in the WordPress admin
2. Click **Audit** in the action row beneath any plugin
3. The modal opens immediately and shows a progress indicator while the audit runs
4. On completion the full report is displayed in the modal
5. Click **Download PDF** to print / save the report
6. Previous reports are accessible from **Tools → Plugin Auditor**

---

## What the Scanner Checks

Every check reports both findings **and** confirmed passes.

| # | Check | Severity |
|---|---|---|
| 1 | Dangerous functions (`eval`, `exec`, `shell_exec`, `base64_decode`, etc.) | CRITICAL |
| 2 | Output escaping  unescaped echo, wrong escape function, `_e()` / `__()` without escaping | HIGH / MEDIUM |
| 3 | Input sanitization  unsanitized superglobals, missing `wp_unslash()` | HIGH / MEDIUM |
| 4 | Nonce verification  forms, AJAX handlers, GET actions | CRITICAL / HIGH |
| 5 | Capability checks  admin pages, write operations | HIGH |
| 6 | Database queries  unprepared statements, raw `mysql_*` calls | CRITICAL |
| 7 | Hardcoded credentials  passwords, API keys, tokens (value redacted in report) | CRITICAL |
| 8 | Error suppression  `error_reporting(0)`, `ini_set` on error settings | HIGH / MEDIUM |
| 9 | Obfuscated calls  variable variables, dynamic function names, `preg_replace /e` | CRITICAL |
| 10 | Direct file access guard  missing `defined('ABSPATH') \|\| exit` | HIGH |
| 11 | Debug output PHP  `var_dump`, `print_r`, `var_export` | MEDIUM |
| 12 | Debug output JS  `console.log`, `console.warn`, etc. | LOW |
| 13 | File permissions  world-writable files, PHP files with execute bit | HIGH / MEDIUM |
| 14 | Deprecated WordPress functions | MEDIUM |
| 15 | Plugin structure  missing `index.php` sentinels, exposed readme files | LOW |
| 16 | Licensing  LICENSE file present, GPL-compatible license declared | LOW / MEDIUM |
| 17 | PHP compatibility  PHP 8.0/8.1 features vs declared minimum version | MEDIUM |
| 18 | Commented-out code  blocks of 5+ consecutive comment lines | LOW / MEDIUM |
| 19 | Plugin header / metadata  missing required headers | LOW |
| 20 | External HTTP requests  all outbound calls reported as informational | INFO |
| 21 | Asset versioning  hardcoded version strings or `false` version argument | LOW |
| 22 | Redirect without exit  `wp_redirect` / `wp_safe_redirect` not followed by `exit` | HIGH |
| 23 | Role name in `current_user_can()`  role names passed instead of capability names | HIGH |
| 24 | Shortcode output escaping  unescaped return values in shortcode callbacks | MEDIUM |
| 25 | Option writes without capability check  `update_option`, `add_option`, `delete_option` | HIGH |
| 26 | Wrong `$wpdb->prepare()` placeholder  `%s` for integers, `%d` for strings | MEDIUM |
| 27 | Duplicate hook registrations  same hook/callback/priority registered more than once | LOW |
| 28 | Unnecessary closures  `function() { return true; }` instead of `__return_true` | LOW |
| 29 | Early translation calls  translation functions called at file scope before `init` | LOW |

### Risk Rating

| Rating | Score |
|---|---|
| CLEAN | 0 |
| LOW | 1–7 |
| MEDIUM | 8–19 |
| HIGH | 20–49 |
| CRITICAL | 50+ |

Scores are weighted: CRITICAL = 25pts, HIGH = 10pts, MEDIUM = 4pts, LOW = 1pt.

---

## Running Tests

### Unit Tests

Unit tests use [Brain\Monkey](https://brain-wp.github.io/BrainMonkey/) for WordPress function mocking and run without WordPress or Docker.

```bash
npm run test:unit
# or directly:
./vendor/bin/phpunit --configuration phpunit.xml.dist --testsuite unit
```

### Integration Tests

Integration tests run inside the `tests-wordpress` wp-env container against a real WordPress + database. The environment must be started first.

```bash
npm run env:start
npm run test:integration
# or directly:
wp-env run tests-cli phpunit --configuration phpunit.xml.dist --testsuite integration
```

The `tests-cli` container has access to the WordPress test suite at `WP_TESTS_DIR` and can hit the real database. Tests extend `WP_UnitTestCase` and use WP factory methods.

### Run a specific test

```bash
# Unit  filter by test name
./vendor/bin/phpunit --configuration phpunit.xml.dist --testsuite unit --filter test_flags_eval_usage

# Integration  filter by test name
wp-env run tests-cli phpunit --configuration phpunit.xml.dist --testsuite integration --filter test_audit_stores_report_cpt
```

---

## Linting & Static Analysis

```bash
# PHP CodeSniffer (WordPress Coding Standards)
npm run lint:php

# Auto-fix fixable violations
npm run lint:fix

# PHPStan (level 8)
npm run analyze
```

PHPCS runs automatically as a post-edit hook in Claude Code  any violation blocks the edit until resolved.

---

## File Structure

```
plugin-auditor/
├── plugin-auditor.php              # Plugin header, constants, bootstrap
├── uninstall.php                   # Removes all plugin data on uninstall (multisite-aware)
├── readme.txt                      # WordPress.org plugin directory listing
├── LICENSE                         # GPL-2.0 full text
├── index.php                       # Directory listing sentinel
├── composer.json
├── composer.lock
├── package.json
├── phpcs.xml.dist                  # WordPress coding standards config
├── phpstan.neon.dist               # PHPStan level 8 config
├── phpunit.xml.dist
├── .wp-env.json
├── .eslintrc.json
├── includes/
│   ├── index.php
│   ├── class-admin.php             # Plugins page action link, admin menu, reports page
│   ├── class-ajax.php              # AJAX handlers: pla_run_audit, pla_get_report, pla_download_json, pla_delete_report
│   ├── class-cpt.php               # pla_report CPT registration
│   ├── class-file-collector.php    # Recursive PHP/JS file collection
│   ├── class-modal.php             # Asset enqueue, modal shell in admin footer
│   ├── class-plugin-validator.php  # Plugin file path resolution and metadata
│   ├── class-rate-limiter.php      # Transient-based concurrent audit guard
│   ├── class-report.php            # Chunked meta storage and JSON export
│   ├── class-report-renderer.php   # Report HTML rendering
│   ├── class-report-repository.php # Report CPT database queries
│   ├── class-scanner.php           # Static analysis engine
│   └── class-score-calculator.php  # Risk score and rating calculation
├── assets/
│   ├── index.php
│   ├── css/
│   │   ├── index.php
│   │   ├── modal.css               # Modal, report, severity badge styles
│   │   └── print.css               # A4 print stylesheet
│   └── js/
│       ├── index.php
│       └── auditor.js              # Modal open/close, focus trap, AJAX, retry, download
├── templates/
│   ├── index.php
│   ├── admin-reports.php           # Tools → Plugin Auditor reports list page
│   ├── modal.php                   # Modal shell with ARIA attributes
│   └── report.php                  # Full report HTML  sections, tables, pass badges
├── tests/
│   ├── bootstrap.php               # PHPUnit bootstrap
│   ├── Unit/
│   │   ├── ScannerTest.php
│   │   └── ReportTest.php
│   └── Integration/                # WP_UnitTestCase tests (requires wp-env)
└── languages/
    ├── index.php
    └── plugin-auditor.pot          # Translation template
```

---

## Architecture Notes

### Report Storage

Findings are stored in separate post meta keys per section (`_pla_findings_dangerous`, `_pla_findings_output`, etc.) to avoid hitting the MySQL `max_allowed_packet` limit on large plugins. Retrieved and reassembled by `Report::load()`.

A maximum of 20 reports are retained per audited plugin. The oldest is deleted when a new report is created (`ReportRepository::prune()`).

### AJAX Security

Every AJAX handler (`pla_run_audit`, `pla_get_report`) verifies:
1. `current_user_can( 'manage_options' )`
2. A per-plugin or per-report nonce
3. Rate limiting via transient (`pla_running_{hash}`)  blocks duplicate concurrent audits

### Modal Accessibility

The modal meets WCAG requirements:
- `role="dialog"`, `aria-modal="true"`, `aria-labelledby` wired to report title
- Focus trapped inside while open via `keydown` listener
- Closes on Escape key
- Focus returns to the triggering element on close

### Autoloading

Uses Composer classmap (not PSR-4) because WordPress-style `class-*.php` filenames are incompatible with PSR-4 class-to-file mapping.

---

## Security

- All `$_POST` / `$_GET` values are unslashed and sanitized at the point of reading
- All output is escaped with context-correct functions at the point of output
- Every privileged action checks `current_user_can( 'manage_options' )`
- All nonces are verified before processing
- No raw SQL  `$wpdb->prepare()` used throughout
- Hardcoded credential values are redacted in the report output
- `wp_safe_redirect()` + `exit` on all redirects
- Assets enqueued only on the Plugins page and the auditor admin page

---

## License

GPL-2.0-or-later  see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
