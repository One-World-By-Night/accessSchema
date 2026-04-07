# accessSchema — Server Plugin

Hierarchical role-based access control for WordPress with REST API, audit logging, and admin UI.

**Version:** 2.5.0 (header) / 2.3.0 (constant — mismatch, needs fix)
**Deployed to:** sso.owbn.net

## Features

- **Hierarchical role paths** — tree-structured roles (e.g., `chronicle/mckn/hst`) with parent-child inheritance
- **REST API** — check access, list roles, manage assignments. API key authenticated.
- **Audit logging** — complete trail of all permission changes
- **Role manager** — admin CRUD with batch import, CSV export, edit modal, cascade delete
- **Users table integration** — ASC Roles column with color-coded category badges, Select2 filter, wildcard pattern matching
- **Rules engine** — grant validation with exclusion rules and admin UI
- **Shortcodes** — `[accessschema_roles]` (display user's roles), `[accessschema_check role="..."]` (conditional content)
- **i18n ready** — localized JS strings with fallbacks

## REST API

```
POST /wp-json/accessSchema/v1/check-access
{ "api_key": "...", "email": "...", "role_path": "...", "include_children": true }
→ { "has_access": true, "matched_roles": [...] }
```

## Client Library

The embeddable client (`accessschema-client/`) can be distributed with other plugins. Configure via `prefix.php` (set `ASC_PREFIX` and `ASC_LABEL`). Supports remote (REST API), local (direct DB), and none (WP caps fallback) modes. Shared cache key architecture prevents conflicts when multiple clients are loaded.

The canonical deployment of the client is now in owbn-core (`includes/accessschema/`).

## Hooks

### Actions
- `accessSchema_role_added` / `accessSchema_role_removed` — user role changes
- `accessSchema_role_created` / `accessSchema_role_deleted` — role tree changes

### Filters
- `accessSchema_parent_grants_access` — modify inheritance logic
- `accessSchema_role_conflicts` — define conflicting role pairs
- `accessSchema_role_capabilities` — map roles to WP capabilities
- `accessSchema_max_roles_per_user` — limit per user (default: 50)
- `accessSchema_validate_role_assignment` — validate before save

## Database Tables

- `{prefix}_accessSchema_roles` — role hierarchy
- `{prefix}_accessSchema_permissions` — user-role assignments
- `{prefix}_accessSchema_audit_log` — audit trail

## Requirements

- WordPress 5.0+, PHP 7.4+, MySQL 5.6+

## Changelog

See [VERSION_HISTORY.md](VERSION_HISTORY.md) for complete history.
