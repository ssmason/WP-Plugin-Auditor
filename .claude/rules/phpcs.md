---
globs: ["web/app/themes/vanilla/**/*.php"]
---

# PHP & WordPress Coding Standards

- All PHP must pass PHPCS with WordPress coding standards ruleset
- Strict types on every file: `declare(strict_types=1);`
- Type hints on all function parameters and return types
- Yoda conditions: `if ( true === $var )`
- Proper sanitization on all input: `sanitize_text_field()`, `absint()`, etc.
- Proper escaping on all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Nonces on all forms and AJAX requests
- Prepared statements for all direct DB queries: `$wpdb->prepare()`
- No shorthand PHP tags — always `<?php`
- Space inside parentheses: `if ( $condition )`

