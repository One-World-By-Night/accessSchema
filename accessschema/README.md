> **Current Version**: 2.0.0 (Production Release - February 2026)
> **Status**: Production-ready, PHPCS clean, security hardened
> **Version History**: See [VERSION_HISTORY.md](VERSION_HISTORY.md) for complete changelog.

# AccessSchema - Hierarchical Role-Based Access Control for WordPress

A comprehensive WordPress plugin providing hierarchical RBAC (Role-Based Access Control) with REST API support, audit logging, and a distributable client library.

## Overview

AccessSchema enables granular permission management through hierarchical role paths (e.g., `Chronicles/MCKN/Storyteller`), REST API integration for remote access checks, and a client library for embedding in other WordPress plugins.

## Features

### Core Capabilities
- ✅ **Hierarchical Role Paths**: Organize roles in tree structures (e.g., `Organization/Chapter/Position`)
- ✅ **REST API**: Full-featured API for role management and permission checks
- ✅ **WordPress Integration**: Seamlessly integrates with WordPress capabilities system
- ✅ **Audit Logging**: Complete audit trail of all permission-related actions
- ✅ **User-Role Management**: Assign multiple roles to users with path-based organization

### Security Features (v2.0.0)
- ✅ **PHPCS Clean**: 0 errors, 0 warnings (WordPress Coding Standards)
- ✅ **Server-Side API Key Generation**: Secure key generation with wp_generate_password()
- ✅ **Nonce Verification**: All AJAX actions protected with WordPress nonces
- ✅ **Output Escaping**: All user-facing output properly escaped
- ✅ **Input Sanitization**: All inputs sanitized with WordPress functions
- ✅ **Safe Redirects**: Uses wp_safe_redirect() for all redirects

### Admin Features
- ✅ **Role Manager**: CRUD interface for managing role hierarchy
- ✅ **Batch Operations**: Import multiple role paths via textarea input
- ✅ **CSV Export**: Export role structure to CSV format
- ✅ **Edit Modal**: In-place role editing with AJAX save
- ✅ **Delete with Cascade**: Smart deletion that checks for child roles
- ✅ **Path Validation**: Automatic validation of role path integrity

### Client Library
- ✅ **Distributable Package**: Production-ready client at `accessschema-client/`
- ✅ **Multiple Modes**: Remote (REST API), Local (direct DB), None (disabled)
- ✅ **Caching**: User role cache with manual flush/refresh options
- ✅ **Shortcodes**: [access_schema_*] shortcodes for content protection
- ✅ **WordPress Hooks**: Integrates with user_has_cap filter for capability mapping

## Installation

1. Upload the `accessschema` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings > Access Schema** to configure API keys and settings
4. Go to **Access Schema > Role Manager** to create your role hierarchy

## Usage

### Creating Roles

Navigate to **Access Schema > Role Manager** to create roles:

**Individual Role Creation:**
1. Click "Add New Role"
2. Enter role name and full path (e.g., `Chronicles/MCKN/Storyteller`)
3. Click "Save Role"

**Batch Role Creation:**
1. Click "Batch Add Roles"
2. Enter one role path per line in the textarea
3. Click "Import Roles"

### Assigning Roles to Users

1. Go to **Users > All Users**
2. Click "Edit" on a user
3. Scroll to the "Access Schema Roles" section
4. Select roles from the dropdown (multiple selection supported)
5. Click "Update User"

### Using the REST API

**Check User Access:**
```http
POST /wp-json/accessSchema/v1/check-access
Content-Type: application/json

{
  "api_key": "as_your_api_key_here",
  "email": "user@example.com",
  "role_path": "Chronicles/MCKN/Storyteller",
  "include_children": true
}
```

**Response:**
```json
{
  "has_access": true,
  "matched_roles": ["Chronicles/MCKN/Storyteller"]
}
```

### Using the Client Library

The client library is available at `accessschema-client/` for distribution with other plugins.

**Integration Steps:**
1. Copy `accessschema-client/` into your plugin
2. Rename `prefix.php.example` to `prefix.php` or modify `prefix.php`:
   ```php
   define('ASC_PREFIX', 'myplugin'); // Unique prefix
   define('ASC_LABEL', 'My Plugin'); // Human-readable label
   ```
3. Require the client:
   ```php
   require_once plugin_dir_path(__FILE__) . 'accessschema-client/accessSchema-client.php';
   ```

**Client Functions:**
```php
// Check if user has access to a role path
$has_access = accessSchema_client_remote_check_access(
    $email,
    $role_path,
    $client_id,
    $include_children
);

// Check if user matches any role in a list
$matches = accessSchema_client_remote_user_matches_any(
    $email,
    $role_list,
    $client_id
);
```

**Client Modes:**
- **Remote**: Connects to accessSchema server via REST API
- **Local**: Direct database access (requires accessSchema server plugin installed)
- **None**: Disabled (falls back to WordPress capabilities)

## Shortcodes

### Server Plugin Shortcodes

**[accessschema_roles]** - Display current user's roles
```
[accessschema_roles]
```

**[accessschema_check]** - Conditional content display
```
[accessschema_check role="Chronicles/MCKN/Storyteller"]
  Content only visible to MCKN Storytellers
[/accessschema_check]
```

### Client Library Shortcodes

**[access_schema_protected]** - Protect content by role
```
[access_schema_protected role="Chronicles/MCKN"]
  Content for MCKN members
[/access_schema_protected]
```

**[access_schema_roles]** - Display user's roles
```
[access_schema_roles]
```

## Database Schema

### Tables Created
- `{prefix}_accessSchema_roles` - Role hierarchy storage
- `{prefix}_accessSchema_permissions` - User-role assignments
- `{prefix}_accessSchema_audit_log` - Audit trail

### Options Created
- `accessSchema_api_key` - REST API authentication key
- `accessSchema_version` - Current plugin version
- `accessSchema_remove_data_on_uninstall` - Data retention setting

## Hooks & Filters

### Actions
- `accessSchema_role_added` - Fires when a role is assigned to a user
- `accessSchema_role_removed` - Fires when a role is removed from a user
- `accessSchema_role_created` - Fires when a new role is created
- `accessSchema_role_deleted` - Fires when a role is deleted

### Filters
- `accessSchema_parent_grants_access` - Modify parent role inheritance logic
- `accessSchema_role_conflicts` - Define conflicting role pairs
- `accessSchema_role_capabilities` - Map roles to WordPress capabilities
- `accessSchema_max_roles_per_user` - Set maximum roles per user (default: 50)
- `accessSchema_validate_role_assignment` - Validate role assignments before save

See [includes/core/permission-checks.php](includes/core/permission-checks.php) for complete hook documentation.

## Code Quality

### v2.0.0 Standards
- ✅ **PHPCS**: WordPress Coding Standards (0 errors, 0 warnings)
- ✅ **Security**: All inputs sanitized, outputs escaped, nonces verified
- ✅ **Documentation**: PHPDoc blocks on all public functions
- ✅ **WordPress Standards**: Yoda conditions, wp_unslash, wp_safe_redirect
- ✅ **No Debug Code**: All debug statements removed from production

### PHPCS Configuration
Custom rulesets at `.phpcs.xml.dist` with intentional exclusions:
- camelCase function/hook names (legacy compatibility)
- Direct DB queries (required for performance)
- Custom capabilities (manage_access_schema, assign_access_roles, view_access_logs)

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6+ or MariaDB 10.0+
- **PHP Extensions**: mysqli, json

## Changelog

See [VERSION_HISTORY.md](VERSION_HISTORY.md) for detailed version history.

### Version 2.0.0 (February 2026)
- **Security hardening**: Server-side API key generation, PHPCS clean
- **Feature completion**: AJAX handlers, edit modal, batch validation
- **Code quality**: 11,313+ PHPCS violations fixed, PHPDoc complete
- **Client improvements**: Debug code removed, function bugs fixed, distributable package

## Support

- **Repository**: https://github.com/One-World-By-Night/owbn-chronicle-plugin
- **Issues**: https://github.com/One-World-By-Night/owbn-chronicle-plugin/issues
- **Author**: One World By Night
- **License**: GPL-2.0-or-later

## License

This plugin is licensed under the GPL-2.0-or-later license. See [LICENSE](LICENSE) for details.
