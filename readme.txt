=== Plugin Auditor ===
Contributors: ssmason@gmail.com
Tags: security, plugin, audit, scanner, static-analysis
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audits installed WordPress plugins for security issues, coding standards violations, and file permissions.

== Description ==

Plugin Auditor performs static analysis on every PHP file in any installed plugin and produces a detailed security report directly inside the WordPress admin — no external service required.

**What the scanner checks:**

* Dangerous functions (`eval`, `exec`, `shell_exec`, `base64_decode`, and more)
* Obfuscated calls (variable variables, dynamic function names, `preg_replace /e`)
* Output escaping — multi-line taint tracking from superglobal to `echo`
* Input sanitization — missing `sanitize_*` and `wp_unslash()`
* Nonce verification — forms, AJAX handlers, and GET actions
* Capability checks — admin pages and write operations
* Database queries — unprepared statements and raw `mysql_*` calls
* Hardcoded credentials — passwords, API keys, tokens (value redacted in report)
* External HTTP requests (informational)
* Asset versioning — hardcoded version strings or `false` version argument
* Error suppression — `error_reporting(0)` and `ini_set` on error settings
* File permissions — world-writable files, PHP files with execute bit
* Plugin header / metadata — missing required headers
* Redirect without exit, role names in capability checks, option writes without capability, duplicate hooks, unnecessary closures, early translation calls, and more

Every check reports both findings **and** confirmed passes, so a clean report is just as meaningful as a failing one.

**Risk Rating**

Findings are weighted by severity (CRITICAL = 25 pts, HIGH = 10 pts, MEDIUM = 4 pts, LOW = 1 pt) and the plugin is assigned an overall rating: CLEAN, LOW, MEDIUM, HIGH, or CRITICAL.

**Reports**

Reports are stored as a Custom Post Type and are viewable at any time from **Tools → Plugin Auditor**. Reports can be downloaded as JSON for offline review.

== Installation ==

1. Upload the `plugin-auditor` folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen.
3. Go to the **Plugins** page and click **Audit** beneath any installed plugin.

== Frequently Asked Questions ==

= Does the audit send data anywhere? =

No. All analysis is performed locally on your server. No data leaves your WordPress installation.

= How long does an audit take? =

For most plugins it completes in under 10 seconds. Large plugins with hundreds of files may take up to 60–90 seconds.

= Can I audit this plugin itself? =

Yes. Plugin Auditor audits itself and reports its own findings — it is held to the same standards it applies to others.

= Where can I see previous reports? =

Go to **Tools → Plugin Auditor**. All reports are listed there and can be viewed or deleted.

= How many reports are kept? =

A maximum of 20 reports are retained per audited plugin. The oldest is automatically deleted when a new one is created.

== Screenshots ==

1. The Audit action link in the Plugins page action row.
2. The progress indicator while the audit runs.
3. A completed report showing risk rating, findings table, and section passes.
4. The Tools → Plugin Auditor reports list page.

== Changelog ==

= 1.0.1 =
* Initial public release.

== Upgrade Notice ==

= 1.0.1 =
Initial release.
