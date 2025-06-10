=== AccessSchema Client (Embedded Module) ===
Contributors: greghacke  
Tags: access control, remote API, roles, permissions  
Requires at least: 5.0  
Tested up to: 6.5  
Stable tag: 1.1.0  
License: MIT  
License URI: https://opensource.org/licenses/MIT

An embeddable plugin module for querying and validating user access from an external WordPress AccessSchema server.

== Description ==

AccessSchema Client is **not a standalone plugin** — it is a modular component designed to be embedded inside another WordPress plugin that needs to verify user roles from a remote AccessSchema server.

This module:
- Queries a remote AccessSchema API for user roles.
- Checks if a user is granted a specific role or descendant.
- Provides shortcode and programmatic utilities.
- Uses a shared API key for secure access.

== How to Use ==

Place the `accessschema-client` folder **inside your own plugin** like so:
your-plugin/
├── your-plugin.php
├── includes/
│   └── accessschema-client/
│       ├── init.php
│       ├── hooks.php
│       ├── utils.php
│       └── …

In your plugin’s main file (typically your-plugin.php at the root level):

require_once plugin_dir_path(__FILE__) . 'includes/accessschema-client/init.php';

Edit the access_schema-client/admin-ui.php replacing:
        'AS YourPlugin',        // <-- Change this per plugin context
with your plugin name.

== Configuration ==

Once embedded and initialized, a new menu will appear under Users → AS YourPlugin where you can:
	•	Enter the Remote AccessSchema URL.
	•	Enter the Shared API Key.

These are stored in options:
	•	accessschema_client_url
	•	accessschema_client_key

== Developer API ==

Use the following utility functions in your code:

// Check access for a remote user
```php
accessSchema_client_remote_check_access( 'user@example.com', 'Chronicles/ABC/HST' );

// Grant a role to a user remotely
```php
accessSchema_remote_grant_role( 'user@example.com', 'Chronicles/ABC/AST' );

// Fetch all roles assigned to a user
```php
accessSchema_remote_get_roles_by_email( 'user@example.com' );

// Check access for current user (with wildcard support)
```php
accessSchema_access_granted( 'Chronicles/ABC/*' );

== Shortcode ==

Use [access_schema_client]...[/access_schema_client] to conditionally show content:

[access_schema_client role="Chronicles/ABC/HST"]
Welcome, Head Storyteller!
[/access_schema_client]

[access_schema_client any="Chronicles/ABC/HST, Chronicles/ABC/AST" wildcard="true" fallback="You do not have access."]
Only visible to staff.
[/access_schema_client]

== Shortcode Attributes ==
	•	role: Single role path.
	•	any: Comma-separated list of role paths or patterns.
	•	wildcard: Whether to treat the paths as wildcard-enabled (*, **).
	•	fallback: Optional content if no match.
	•	children: (Note: children applies only to exact role paths, not wildcard patterns like * or **.)

== Filters ==

Customize or log access results by hooking into the result:

```php
add_filter('accessSchema_access_granted', function($granted, $patterns, $user_id) {
    $user = get_userdata($user_id);
    error_log("[AccessSchema] {$user->user_email} matched patterns: " . implode(', ', $patterns));
    return $granted;
}, 10, 3);

== License ==

GPL-2.0-or-later