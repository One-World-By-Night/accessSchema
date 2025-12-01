=== AccessSchema Client (Embedded Module) ===
Contributors: greghacke  
Tags: access control, remote API, roles, permissions  
Requires at least: 5.0  
Tested up to: 6.5  
Stable tag: 1.2.0  
License: MIT  
License URI: https://opensource.org/licenses/MIT  

An embeddable plugin module for querying and validating user access from an external WordPress AccessSchema server.

== Description ==

AccessSchema Client is not a standalone plugin — it is a modular component designed to be embedded inside another WordPress plugin that needs to verify user roles from a remote AccessSchema server.

This module:  
- Queries a remote AccessSchema API for user roles.  
- Checks if a user is granted a specific role or descendant.  
- Provides shortcode and programmatic utilities.  
- Uses a shared API key for secure access.

== How to Use ==

Place the accessschema-client folder inside your own plugin like so:

your-plugin/  
├── your-plugin.php  
└── includes/  
    └── accessschema-client/  
        ├── accessschema-client.php  
        ├── prefix.php  
        ├── includes/
            └── . . .

In your plugin’s main file:

require_once plugin_dir_path(__FILE__) . 'includes/accessschema-client/accessschema-client.php';

Edit the prefix.php file and replace:  
define('ASC_PREFIX', 'YPP');  
define('ASC_LABEL', 'Your Plugin Label');  
to match your plugin.

== Configuration ==

Once initialized, a menu appears under "Users" as "{ASC_PREFIX} ASC" where you can configure:  
- Connection Mode (remote, local, none)  
- Remote API URL and key  
- Capability-to-role mapping for WP core caps

These are stored in options like (where client_id is the lowercase, hyphen-normalized ASC_PREFIX):  
- {client_id}_accessschema_mode  
- {client_id}_accessschema_client_url  
- {client_id}_accessschema_client_key  
- {client_id}_capability_map

Example: If ASC_PREFIX is 'YPP', options would be:  
- ypp_accessschema_mode  
- ypp_accessschema_client_url  
- ypp_accessschema_client_key  
- ypp_capability_map

== Developer API ==

Use in plugin code (where $client_id is your lowercase, hyphen-normalized ASC_PREFIX, e.g., 'ypp'):

accessSchema_client_remote_check_access( 'user@example.com', 'Chronicle/KONY/HST', $client_id, true );  
accessSchema_client_remote_grant_role( 'user@example.com', 'Coordinator/Tzimisce/Player', $client_id );  
accessSchema_client_remote_revoke_role( 'user@example.com', 'Coordinator/Tzimisce/Player', $client_id );  
accessSchema_client_remote_get_roles_by_email( 'user@example.com', $client_id );  
accessSchema_access_granted( 'Chronicle/KONY/*' );  // Uses ASC_PREFIX automatically

== Usage Examples ==

Example 1: Use asc_has_access_to_group to verify access by group path.

$has_group_access = current_user_can( 'asc_has_access_to_group', "Chronicle/{$group}" );

if ( ! $has_group_access && ! current_user_can('administrator') ) {
    echo '<p>You do not have access to this Chronicle: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
    return;
}

Example 2: Map core capabilities like edit_post for a Coordinator group.

$has_group_access = current_user_can( 'edit_post', "Coordinator/{$group}/Coordinator" );

if ( ! $has_group_access && ! current_user_can('administrator') ) {
    echo '<p>You cannot edit this: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
    return;
}

Capability mapping (in settings or via update_option):

'edit_post' => ['Coordinator/$slug/Coordinator']

Example 3: Map core capabilities like edit_post for a Chronicle senior staff groups.

$has_group_access = current_user_can( 'edit_post', "Chronicle/{$group}/CM" )
                 || current_user_can( 'edit_post', "Chronicle/{$group}/ST" );

if ( ! $has_group_access && ! current_user_can('administrator') ) {
    echo '<p>You cannot edit this: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
    return;
}

Capability mapping (in settings or via update_option):

'edit_post' => [
    'Chronicle/$slug/CM',
    'Chronicle/$slug/ST'
]

== Shortcode ==

Use [access_schema_{client_id}]...[/access_schema_{client_id}] to conditionally show content.

The shortcode name is dynamic based on your ASC_PREFIX. For example:
- If ASC_PREFIX is 'YPP', use [access_schema_ypp]
- If ASC_PREFIX is 'OWBN_BOARD', use [access_schema_owbn-board]

[access_schema_ypp role="Chronicles/ABC/HST"]  
Welcome, Head Storyteller!  
[/access_schema_ypp]

[access_schema_ypp any="Chronicles/ABC/HST, Chronicles/ABC/AST" wildcard="true" fallback="You do not have access."]  
Only visible to staff.  
[/access_schema_ypp]

== Shortcode Attributes ==

- role: Single role path  
- any: Comma-separated list of paths  
- wildcard: true to enable wildcard logic  
- fallback: Shown when access denied  
- children: Only applies to exact path matches

== Filters ==

Use the accessSchema_access_granted filter to monitor access:

add_filter('accessSchema_access_granted', function($granted, $patterns, $user_id) {
    $user = get_userdata($user_id);
    error_log("[AccessSchema] {$user->user_email} matched: " . implode(', ', $patterns));
    return $granted;
}, 10, 3);

== Changelog ==

= 1.2.0 =  
- Switched from slug to client_id everywhere  
- Added login caching and refresh logic  
- Added remote + local fallback  
- Improved error and access logs  
- Clarified shortcode behavior and fallback

== License ==

GPL-2.0-or-later