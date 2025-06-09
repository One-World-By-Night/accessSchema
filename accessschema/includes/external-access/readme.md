# AccessSchema External Client

A lightweight WordPress PHP client to interact with a remote site running the [accessSchema](https://github.com/your-org/accessSchema) role and permission management API. This client enables role registration, assignment, and permission checking on a centralized external WordPress instance.

---

## 📦 Installation

1. **Copy the file**  
   Place `client.php` into your plugin’s `includes/` directory or equivalent:

   ```
   your-plugin/
   └── includes/
       └── access-client.php
   ```

2. **Require it in your main plugin file**  
   ```php
   require_once plugin_dir_path(__FILE__) . 'includes/access-client.php';
   ```

3. **Configure constants**  
   In your plugin or theme (typically at the top of `functions.php` or your main plugin file):

   ```php
   define( 'REMOTE_ACCESS_SCHEMA_URL', 'https://your-remote-site.com/wp-json/access-schema/v1' );
   define( 'REMOTE_ACCESS_SCHEMA_KEY', 'your-shared-api-key' );
   ```

---

## 🚀 Usage

### ✅ Check if user has access remotely

```php
$email = 'user@example.com';
$role  = 'Chronicles/MCKN/Staff';

$has_access = accessSchema_remote_check_access( $email, $role );

if ( is_wp_error( $has_access ) ) {
    error_log( 'Access check failed: ' . $has_access->get_error_message() );
} elseif ( $has_access ) {
    // Grant feature access
} else {
    // Block or restrict access
}
```

---

### 🎁 Grant a role to a user

```php
$result = accessSchema_remote_grant_role( 'user@example.com', 'Organization/Group/Team' );

if ( is_wp_error( $result ) ) {
    // Handle error
}
```

---

### 🔄 Revoke a role from a user

```php
$result = accessSchema_remote_post( 'revoke', [
    'email' => 'user@example.com',
    'role_path' => 'Organization/Group/Team',
] );
```

---

### 📋 Get all remote roles for a user

```php
$roles = accessSchema_remote_get_roles_by_email( 'user@example.com' );

if ( is_wp_error( $roles ) ) {
    // Error
} else {
    print_r( $roles );
}
```

---

## 🛡️ Requirements

- Remote site must have the `accessSchema` plugin installed and active.
- The remote WordPress must define `ACCESS_SCHEMA_API_KEY` in its `wp-config.php`.
- HTTPS is strongly recommended to secure API key transmission.

---

## 🧩 API Endpoints Used

| Endpoint      | Description                        |
|---------------|------------------------------------|
| `/register`   | Bulk register hierarchical roles   |
| `/roles`      | Fetch user roles                   |
| `/grant`      | Assign a role                      |
| `/revoke`     | Remove a role                      |
| `/check`      | Check permission access            |

---

## 🧪 Development Tips

- You can mock the remote API using tools like [Postman](https://www.postman.com/) or `curl` for testing independently.
- If debugging, consider adding `error_log(print_r($result, true))` for better traceability in development.

---

## 📝 License

GPLv2 or later.