# accessSchema

WordPress plugin suite for hierarchical role-based access control with audit logging, REST API, and multi-instance client support.

**Includes:**
- `accessschema/` — Server plugin (role management, REST API, audit logging)
- `accessschema/accessschema-client/` — Embeddable client for remote or local role checks
- `gf-accessschema-user-registration/` — Gravity Forms addon for user creation with role assignment

## Installation

1. Copy `accessschema/` into `/wp-content/plugins/`
2. Activate in WordPress admin
3. Add API keys to `wp-config.php`:

```php
define('ACCESSSCHEMA_API_KEY_RO', 'your-read-key');
define('ACCESSSCHEMA_API_KEY_RW', 'your-read-write-key');
```

4. Manage roles at **Users > accessSchema**

## Versions

### accessSchema 2.3.0

- Stripped comment bloat and redundant PHPDoc
- Fixed version constant mismatch

### accessSchema Client 2.5.0

- Stripped comment bloat, renamed wppluginname_loaded action
- Cleaned plugin headers

### GF Addon 1.1.0

- Stripped file-level bloat

### 2.2.0

- Fixed duplicate role display with multiple client instances
- Shared cache key architecture

### 2.1.0

- REST API and role tree improvements

### 1.0.0

- Initial release

## Contributing

[github.com/One-World-By-Night/accessSchema](https://github.com/One-World-By-Night/accessSchema)
