 accessSchema   
Contributors: greghacke  
Tags: roles, access control, permissions, REST API, audit log  
Requires at least: 6.0  
Tested up to: 6.8  
Requires PHP: 7.4  
Stable tag: 1.0.1 
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

Manage role-based access hierarchies with logging, constraints, and REST API control. Part of the OWBN Chronicle infrastructure.

---

##   Description  

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

##   Shortcodes  

The plugin provides several shortcodes to embed or utilize role data in the frontend or other plugin logic:

- `[accessSchema-role-exists path="Chronicles/MCKN/HST"]`  
  Returns `true` or `false`

- `[accessSchema-role-table filter="Chronicles"]`  
  Displays a filterable role table

---

##   Utilities (`includes/util/access-utils.php`)  

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

## Client Integration

The AccessSchema Client enables any external WordPress plugin to interact with a centralized AccessSchema server for role management. This allows for consistent permission checks, centralized control, and cross-plugin role sharing.

### Setup

To use the client in your plugin:

1. **Include the client file**:

<pre>
   require_once plugin_dir_path(__FILE__) . 'includes/client/accessSchemaClient.php';
</pre>

2. **Define your host and API key** (usually in `wp-config.php` or early in your plugin):

<pre>
   define('ACCESS_SCHEMA_CLIENT_HOST', 'https://your-central-server.example.com');
   define('ACCESS_SCHEMA_CLIENT_API_KEY', 'your-api-key-goes-here');
</pre>

### API Functions

#### `accessSchema_client_register_paths(array $paths): array`

Register one or more role paths on the remote AccessSchema server.

<pre>
$paths = [
    ['Chronicles', 'KONY', 'HST'],
    ['Chronicles', 'BETA', 'CM'],
];
$response = accessSchema_client_register_paths($paths);
</pre>

#### `accessSchema_client_grant_role(int|string $user, string $role_path): bool`

Grant a specific role to a user.

<pre>
accessSchema_client_grant_role(42, 'Chronicles/KONY/CM');
accessSchema_client_grant_role('user@example.com', 'Chronicles/BETA/HST');
</pre>

#### `accessSchema_client_revoke_role(int|string $user, string $role_path): bool`

Revoke a previously granted role.

<pre>
accessSchema_client_revoke_role(42, 'Chronicles/KONY/HST');
</pre>

#### `accessSchema_client_get_roles(int|string $user): array`

Get all role paths for the user.

<pre>
$roles = accessSchema_client_get_roles('user@example.com');
</pre>

#### `accessSchema_client_check_access(int|string $user, string $role_path, bool $include_children = false): bool`

Check if a user has access to a given role path. If `$include_children` is true, child roles are matched as well.

<pre>
if ( accessSchema_client_check_access(42, 'Chronicles/KONY', true) ) {
    echo 'Access granted.';
} else {
    echo 'Access denied.';
}
</pre>

### Example: Display Section If Access Granted

<pre>
if ( function_exists('accessSchema_client_check_access') ) {
    if ( accessSchema_client_check_access(get_current_user_id(), 'Coordinators/Brujah/Subcoordinator') ) {
        echo '<p>You may configure clan-level access here.</p>';
    }
}
</pre>

### Security

- All requests use an `X-API-Key` header with the API key defined in your environment.
- Ensure HTTPS is used for all traffic.
- Do not expose or log the API key on the frontend.

---

##   Installation  

1. Upload or clone the plugin to `wp-content/plugins/accessSchema`  
2. Activate via WordPress Admin under *Plugins*  
3. Add the following constants to your `wp-config.php`:

<pre>
define('ACCESS_SCHEMA_API_KEY', 'your-api-key-here');
define('ACCESS_SCHEMA_LOG_LEVEL', 'INFO'); // DEBUG | INFO | WARN | ERROR
</pre>

4. Navigate to **Users > Access Schema Roles** to begin managing your hierarchy

---

##   Changelog  

### 1.0.0
- Initial release with:
  - Hierarchical role creation
  - REST API endpoints
  - Audit log integration
  - UI for role registry
  - Role validation utilities

---

##   Upgrade Notice  

### 1.0.0
Initial beta for accessSchema with full support for registration, validation, and REST-based integration.

### 1.0.1
Client agent tools
