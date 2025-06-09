=== accessSchema ===  
Contributors: greghacke  
Tags: roles, access control, permissions, REST API, audit log  
Requires at least: 6.0  
Tested up to: 6.8  
Requires PHP: 7.4  
Stable tag: 1.0.0  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

Manage role-based access hierarchies with logging, constraints, and REST API control. Part of the OWBN Chronicle infrastructure.

---

## == Description ==

The `accessSchema` plugin provides a centralized framework for defining and enforcing structured access roles across a WordPress site or external system. It is especially tailored for organizations that require nested roles and audit trails — such as OWBN Chronicle operations.

### Core Features

- **Hierarchical Role System**
  - Register and manage roles like `Chronicles/MCKN/HST`, supporting any depth
  - Dynamic parent-child registration ensures clean nesting without duplicates

- **Dynamic Role Registration**
  - Via UI and programmatically via:
    - `accessSchema_register_roles($group, $sub, $roles)`
    - `accessSchema_register_path(array $segments)`

- **Validation and Existence Checks**
  - Confirm if a role exists:
    - `accessSchema_role_exists($role_path)`
  - Retrieve or create nested roles:
    - `accessSchema_get_or_create_role_node($name, $parent_id)`

- **UI Integration**
  - Add full role paths via a user-friendly admin page
  - Automatically filters registered roles
  - Prevents duplicate insertion at all hierarchy levels

- **REST API Access**
  - Endpoint structure allows external systems to query or modify roles
  - API key enforcement with `ACCESS_SCHEMA_API_KEY`
  - Roles can be read, created, or deleted through RESTful calls

- **Audit Logging**
  - Every change is logged with role path, action type, and timestamp
  - Filterable by `DEBUG`, `INFO`, `WARN`, `ERROR`
  - Logs are saved to database or optional file sink

---

## == Shortcodes ==

The plugin provides several shortcodes to embed or utilize role data in the frontend or other plugin logic:

- `[accessSchema-role-exists path="Chronicles/MCKN/HST"]`  
  Returns `true` or `false`

- `[accessSchema-role-table filter="Chronicles"]`  
  Displays a filterable role table

---

## == Utilities (`includes/util/access-utils.php`) ==

The `access-utils.php` module provides helper functions used internally and externally by developers:

- `accessSchema_log($level, $message)`  
  Respect `ACCESS_SCHEMA_LOG_LEVEL` and write to log

- `accessSchema_user_can($user_id, $role_path)`  
  Check if a user holds a specific registered role

- `accessSchema_get_user_roles($user_id)`  
  Retrieve all hierarchical roles assigned to a user

- `accessSchema_register_rest_routes()`  
  Binds REST endpoints to the WordPress API router

---

## == Installation ==

1. Upload or clone the plugin to `wp-content/plugins/accessSchema`  
2. Activate via WordPress Admin under *Plugins*  
3. Add the following constants to your `wp-config.php`:

```php
define('ACCESS_SCHEMA_API_KEY', 'your-api-key-here');
define('ACCESS_SCHEMA_LOG_LEVEL', 'INFO'); // DEBUG | INFO | WARN | ERROR
```

4. Navigate to **Users > Access Schema Roles** to begin managing your hierarchy

---

## == Changelog ==

### = 1.0.0 =
- Initial release with:
  - Hierarchical role creation
  - REST API endpoints
  - Audit log integration
  - UI for role registry
  - Role validation utilities

---

## == Upgrade Notice ==

### = 1.0.0 =
Initial beta for accessSchema with full support for registration, validation, and REST-based integration.