# Access Utilities – `access-utils.php`

This file provides utility functions to simplify role-based access checks using accessSchema. It is intended to be reusable across core, admin, and front-end logic.

---

## 🔒 `accessSchema_access_granted( $patterns )`

Check if the current user has access to any of the provided role patterns.

### Parameters

- **`$patterns`** (`string|array`) – A single pattern string (e.g. `'Chronicles/*/HST'`) or an array of patterns (e.g. `['Coordinators/**/Lead', 'Chronicles/MCKN/CM']`).

### Returns

- `true` if the user matches any of the role patterns.
- `false` if none match.

---

## 🌀 Wildcard Pattern Support

Patterns support the following:

| Pattern   | Meaning                                           |
|-----------|---------------------------------------------------|
| `*`       | Matches one segment (no slash)                    |
| `**`      | Matches zero or more segments (including slashes) |

Example:  
`Chronicles/*/HST` matches:
- `Chronicles/MCKN/HST`
- `Chronicles/KONY/HST`

`Coordinators/**/Lead` matches:
- `Coordinators/Tremere/Lead`
- `Coordinators/Tzimisce/Europe/Lead`

---

## ✅ Example Usage
```php
if ( ! accessSchema_access_granted( [
    'Chronicles/*/CM',
    'Coordinators/**/Lead'
] ) ) {
    return; // Block access
}
```

You can also pass a single pattern string:
```php
if ( accessSchema_access_granted( 'Chronicles/MCKN/HST' ) ) {
    echo 'You are an HST!';
}
```

You can also pass a single pattern string:

```php
if ( accessSchema_access_granted( get_current_user_id(), 'Chronicles/MCKN/HST' ) ) {
    echo 'You are an HST!';
}
```

---

## 🔄 Extending with Filters

Developers can override or intercept access logic using a filter hook:

```php
/**
 * Filter whether access is granted for the given user and patterns.
 *
 * @param bool       $granted  Whether access was granted.
 * @param int        $user_id  The user ID being checked.
 * @param array      $patterns The role patterns passed to the function.
 */
add_filter( 'accessSchema_access_granted', function( $granted, $user_id, $patterns ) {
    if ( user_can( $user_id, 'manage_options' ) ) {
        return true; // Always grant admins
    }
    return $granted;
}, 10, 3 );
```

---

## 📌 Best Practices

- Use `accessSchema_access_granted()` instead of manually checking roles.
- Use arrays for multiple patterns to keep logic readable.
- Use the filter to define global override rules (e.g., admin bypass).
- In future, patterns may be moved to config files or role groups.

---