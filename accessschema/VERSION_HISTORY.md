# accessSchema — Version History

## 2.5.0

- Fixed role colors: category colors are now fixed per category instead of rotating index

## 2.4.0 (Server) / 2.3.0 (Client)

- Rules engine: grant validation with exclusion rules and admin UI
- New tables: `accessSchema_rules` for rule storage
- Docblock parse error fix (comment-end inside wildcard pattern)

## 2.3.0 (Server) / 2.4.0 (Client)

- Stripped comment bloat and redundant PHPDoc across server, client, and GF addon
- i18n: localized remaining JS strings, filter.js placeholder fallback

## 2.2.0

- Fixed duplicate role display with multiple client instances
- Updated role path conventions
- Multi-instance safety for client (shared cache key architecture)
- Variable-based multi-instance refactor in client (`prefix.php` config)

## 2.1.2

- Added `function_exists()` guard to prevent fatal on duplicate client load

## 2.1.1

- Grouped role display in Users table — roles grouped by top-level category with color-coded borders
- Client Users table column matches grouped layout
- Client local-mode fixes: column hidden when local, refresh routes through local post, missing functions added, JSON params fixed

## 2.1.0

- Users table integration: ASC Roles column with color-coded badges, Select2 role filter, wildcard pattern matching
- Batch-loaded role data (no N+1 queries)
- PHP 7.4 fix: replaced `match` expression in client with `switch`

## 2.0.0

- Security hardening: server-side API key generation, PHPCS clean (0 errors, 0 warnings)
- Feature completion: AJAX handlers, edit modal, batch validation, cascade delete
- Client cleanup: debug code removed, function bugs fixed, distributable package
- PHPDoc complete across server and client

## 1.x (Legacy)

Pre-cleanup versions. Known issues: client-side API key generation, stub-only AJAX handlers, debug code in production, 11K+ PHPCS violations.

---

## GF AccessSchema User Registration

### 1.2.0

- Fix Gravity Flow approval via `process_assignee_status`
- Auto-approve Gravity Flow step on account creation
- Render ASC User Registration panel in Gravity Flow inbox

### 1.1.0

- Stripped comment bloat

### 1.0.2

- Initial tracked release
