<?php
// File: accessschema-client/admin-ui.php
// @version 1.6.0

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_users_page(
        'AccessSchema Settings',   // Page title
        'AS OWBN Board',           // Menu label
        'manage_options',          // Capability
        'accessschema-client',     // Slug
        'accessSchema_render_admin_page' // Callback function
    );
});

/**
 * Renders the AccessSchema admin settings page.
 */
function accessSchema_render_admin_page() {
    $slug = 'accessschema-client';
    $label = 'OWBN Board';

    echo '<div class="wrap">';
    echo '<h1>AccessSchema Settings</h1>';

    // === SETTINGS FORM ===
    echo '<form method="post" action="options.php">';
    settings_fields('accessschema_client');           // Group from fields.php
    do_settings_sections('accessschema-client');      // Page from fields.php
    submit_button();
    echo '</form>';

    // === MANUAL CACHE CLEAR FORM ===
    echo '<hr>';
    echo '<h2>Manual Role Cache Clear</h2>';
    echo '<form method="post">';
    echo '<input type="email" name="clear_email_' . esc_attr($slug) . '" placeholder="User email" required class="regular-text" />';
    echo '<button type="submit" class="button button-secondary">Clear Cached Roles</button>';
    echo '</form>';

    if (!empty($_POST["clear_email_{$slug}"])) {
        $email = sanitize_email($_POST["clear_email_{$slug}"]);
        $user  = get_user_by('email', $email);
        $roles_key = "{$slug}_accessschema_cached_roles";
        $time_key  = "{$slug}_accessschema_cached_roles_timestamp";

        if ($user) {
            delete_user_meta($user->ID, $roles_key);
            delete_user_meta($user->ID, $time_key);
            echo '<p><strong>Cache cleared for ' . esc_html($user->user_email) . '</strong></p>';
        } else {
            echo '<p><strong>No user found with that email.</strong></p>';
        }
    }

    echo '</div>';
}