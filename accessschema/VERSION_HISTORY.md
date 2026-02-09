# AccessSchema - Version History

## Version 2.0.0 (Current - February 2026)

**Production-ready release with complete security hardening, feature completion, and code quality improvements.**

### Location
- **Server Plugin**: `/accessSchema/accessschema/`
- **Client Library**: `/accessSchema/accessschema/accessschema-client/`

### Major Changes

#### Security Hardening
- ✅ **Server-side API key generation**: Replaced client-side Math.random() with wp_generate_password(48)
- ✅ **PHPCS clean**: 0 errors, 0 warnings (WordPress Coding Standards)
- ✅ **Security standards**: Yoda conditions, wp_unslash, nonces, output escaping, wp_safe_redirect
- ✅ **Debug output gating**: Protected behind ACCESSSCHEMA_DEBUG constant
- ✅ **Error logging cleanup**: Removed print_r() in logs, sanitized all error messages

#### Feature Completion
- ✅ **AJAX handlers implemented**: accessSchema_ajax_save_role and accessSchema_ajax_delete_role now fully functional
- ✅ **Edit modal wired**: Role editing UI connected to backend AJAX handlers
- ✅ **Batch role validation**: Empty path segments (e.g., Chronicles//MCKN) now rejected with error messages
- ✅ **CSV export error handling**: Proper validation and error checking for export functionality
- ✅ **Cascade delete support**: Delete handler checks for child roles and supports cascade operations

#### Code Quality
- ✅ **PHPDoc complete**: All functions documented with @param, @return, @since tags
- ✅ **Hook documentation**: 15+ custom hooks documented inline with @hook annotations
- ✅ **11,313+ PHPCS violations fixed**: Across server and client codebases
- ✅ **WordPress standards**: Full compliance with WordPress Plugin Directory requirements

#### Client Library Improvements
- ✅ **CSS debug banner removed**: No more red "Bylaw Clause CSS Loaded!" overlay
- ✅ **Function name bugs fixed**: 3 broken function calls corrected (missing client_ prefix, parameter order)
- ✅ **Debug code removed**: Eliminated debug_print_backtrace(), 16+ commented error_log statements
- ✅ **Log prefix normalization**: All logs use consistent [AccessSchema Client] prefix
- ✅ **Empty files removed**: Deleted placeholder files (tests/core.php, tests/utils.php, tools/functions.php)
- ✅ **Distributable package**: Clean, production-ready client library with DISTRIBUTION.md

### Architecture

#### Server Plugin
```
accessSchema.php                        # Bootstrap (Version: 2.0.0)
├── includes/
│   ├── core/
│   │   ├── init.php                    # AJAX handlers (save/delete roles)
│   │   ├── activation.php              # Database schema management
│   │   ├── helpers.php                 # Utility functions
│   │   ├── webhook-router.php          # REST API endpoints (clean error handling)
│   │   ├── permission-checks.php       # Permission validation + hook docs
│   │   ├── role-tree.php               # Hierarchical role management
│   │   ├── user-roles.php              # User-role assignment
│   │   ├── logging.php                 # Audit trail
│   │   └── cache.php                   # Role cache management
│   ├── admin/
│   │   ├── role-manager.php            # Role CRUD UI (edit modal, batch validation, CSV)
│   │   └── settings.php                # API key generation (server-side)
│   ├── render/
│   │   ├── render-admin.php            # Admin UI rendering
│   │   └── render-functions.php        # Helper render functions
│   ├── shortcodes/
│   │   └── access.php                  # [accessschema_*] shortcodes (debug gating)
│   └── utils/
│       └── access-utils.php            # Access check utilities
└── accessschema-client/                # Distributable client library
```

#### Client Library
```
accessschema-client/
├── accessSchema-client.php             # Bootstrap (Version: 2.0.0)
├── prefix.php                          # Client-specific config (ASC_PREFIX, ASC_LABEL)
├── DISTRIBUTION.md                     # Distribution documentation
├── .phpcs.xml.dist                     # PHPCS ruleset
└── includes/
    ├── init.php                        # Module loader
    ├── admin/
    │   └── settings-page.php           # Settings UI (mode: remote/local/none)
    ├── core/
    │   └── client-api.php              # REST API client (clean, no debug code)
    ├── render/
    │   └── render-functions.php        # UI rendering helpers
    ├── shortcodes/
    │   └── shortcodes.php              # [access_schema_*] shortcodes (fixed function calls)
    └── assets/
        └── css/style.css               # Clean CSS (debug banner removed)
```

### Code Quality Metrics

#### Server Plugin
- **PHPCS violations**: 8,435 → 0
- **Files processed**: 17 PHP files
- **Fixes applied**:
  - 38 Yoda conditions
  - 8 short ternary operators
  - 23 wp_unslash() calls
  - 2 output escaping fixes
  - 15+ hook documentation blocks
  - 40+ PHPDoc blocks added

#### Client Library
- **PHPCS violations**: 1,771 → 0
- **Files processed**: 24 PHP files (after deleting 3 empty files)
- **Fixes applied**:
  - 14 Yoda conditions
  - 2 short ternary operators
  - 7 wp_unslash() calls
  - 4 nonce verifications
  - 4 output escaping fixes
  - 3 wp_safe_redirect() implementations
  - CSS debug banner removed
  - 3 function name/parameter bugs fixed

### Breaking Changes
**None** - All API endpoints, request/response formats, and external behavior remain unchanged.

### Migration Notes
This is a cleanup and hardening release. No data migration required. Simply update the plugin files.

### System Requirements
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+ or MariaDB 10.0+

---

## Version 1.x (Legacy)

**Pre-cleanup versions with incomplete features, debug code, and security issues.**

### Known Issues (v1.x)
- Client-side API key generation (security risk)
- AJAX handlers stub-only (non-functional save/delete)
- Debug code in production (backtrace, CSS banner)
- Function name bugs in client shortcodes
- 11,313+ PHPCS violations
- Missing PHPDoc blocks
- Inconsistent error logging
- Empty placeholder files

### Why v2.0 Was Built
The v1.x codebase had accumulated debug artifacts, incomplete features, and security weaknesses. Version 2.0 represents a comprehensive cleanup to:
1. Complete all unimplemented features (AJAX handlers, edit modal)
2. Remove all debug code from production files
3. Implement proper security (server-side key gen, nonce verification)
4. Meet WordPress Plugin Directory standards (PHPCS clean)
5. Provide production-ready distributable client library

---

## Support & Development

- **Repository**: https://github.com/One-World-By-Night/owbn-chronicle-plugin
- **Author**: One World By Night
- **License**: GPL-2.0-or-later
- **Requires**: WordPress 5.0+, PHP 7.4+
