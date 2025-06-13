<?php
// File: accessschema-client/render-admin.php
// @version 1.6.0
// @tool accessschema-client

defined('ABSPATH') || exit;

if (!function_exists('accessSchema_client_register_render_admin')) {
    function accessSchema_client_register_render_admin($slug, $label) {
        add_action("accessschema_render_admin_{$slug}", function () use ($slug, $label) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html($label) . ' AccessSchema Client</h1>';
            accessSchema_render_slug_settings_form($slug);
            accessSchema_render_slug_cache_clear($slug, $label);
            echo '</div>';
        });

        add_action("show_user_profile", function ($user) use ($slug, $label) {
            accessSchema_render_user_cache_block($slug, $label, $user);
        }, 15);

        add_action("edit_user_profile", function ($user) use ($slug, $label) {
            accessSchema_render_user_cache_block($slug, $label, $user);
        }, 15);
    }
}

if (!function_exists('accessSchema_render_slug_settings_form')) {
    function accessSchema_render_slug_settings_form($slug) {
        echo '<form method="post" action="options.php">';
        settings_fields("{$slug}_accessschema_client");
        do_settings_sections("{$slug}_accessschema-client");
        submit_button();
        echo '</form>';
    }
}

if (!function_exists('accessSchema_render_slug_cache_clear')) {
    function accessSchema_render_slug_cache_clear($slug, $label) {
        if (!current_user_can('manage_options')) return;

        $key_roles = "{$slug}_accessschema_cached_roles";
        $key_time  = "{$slug}_accessschema_cached_roles_timestamp";

        echo '<hr>';
        echo '<h2>Manual Role Cache Clear</h2>';
        echo '<form method="post">';
        echo '<input type="email" name="' . esc_attr("clear_email_{$slug}") . '" placeholder="User email" required class="regular-text" />';
        echo '<button type="submit" class="button button-secondary">Clear Cached Roles</button>';
        echo '</form>';

        if (!empty($_POST["clear_email_{$slug}"])) {
            $email = sanitize_email($_POST["clear_email_{$slug}"]);
            $user  = get_user_by('email', $email);

            if ($user) {
                delete_user_meta($user->ID, $key_roles);
                delete_user_meta($user->ID, $key_time);
                echo '<p><strong>Cache cleared for ' . esc_html($user->user_email) . '</strong></p>';
            } else {
                echo '<p><strong>No user found with that email.</strong></p>';
            }
        }
    }
}

if (!function_exists('accessSchema_render_user_cache_block')) {
    function accessSchema_render_user_cache_block($slug, $label, $user) {
        if (!current_user_can('list_users')) return;

        $roles_key = "{$slug}_accessschema_cached_roles";
        $time_key  = "{$slug}_accessschema_cached_roles_timestamp";

        $roles     = get_user_meta($user->ID, $roles_key, true);
        $timestamp = get_user_meta($user->ID, $time_key, true);
        $display_time = $timestamp ? date_i18n('m/d/Y h:i a', intval($timestamp)) : '[Unknown]';

        $flush_url = wp_nonce_url(
            add_query_arg([
                'action'  => 'flush_accessschema_cache',
                'user_id' => $user->ID,
                'slug'    => $slug,
            ], admin_url('users.php')),
            "flush_accessschema_{$user->ID}_{$slug}"
        );

        $refresh_url = wp_nonce_url(
            add_query_arg([
                'action'  => 'refresh_accessschema_cache',
                'user_id' => $user->ID,
                'slug'    => $slug,
            ], admin_url('users.php')),
            "refresh_accessschema_{$user->ID}_{$slug}"
        );

        echo '<h2>' . esc_html($label) . ' AccessSchema (Client Cache)</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th><label>Cached Roles</label></th>';
        echo '<td>';

        if (is_array($roles) && !empty($roles)) {
            echo '<ul style="margin-bottom: 0;">';
            foreach ($roles as $role) {
                echo '<li>' . esc_html($role) . '</li>';
            }
            echo '</ul>';
            echo '<p style="margin-top: 4px;">';
            echo '<strong>Cached:</strong> ' . esc_html($display_time);
            echo ' <a href="' . esc_url($flush_url) . '" style="margin-left:8px;">[Flush]</a>';
            echo ' <a href="' . esc_url($refresh_url) . '" style="margin-left:4px;">[Refresh]</a>';
            echo '</p>';
        } else {
            echo '<p>[None] <a href="' . esc_url($refresh_url) . '" style="margin-left:8px;">[Request]</a></p>';
        }

        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }
}

add_action('admin_action_flush_accessschema_cache', function () {
    if (
        !isset($_GET['user_id'], $_GET['slug']) ||
        !current_user_can('edit_users') ||
        !wp_verify_nonce($_GET['_wpnonce'], "flush_accessschema_{$_GET['user_id']}_{$_GET['slug']}")
    ) {
        wp_die('Unauthorized request.');
    }

    $user_id = intval($_GET['user_id']);
    $slug = sanitize_key($_GET['slug']);

    delete_user_meta($user_id, "{$slug}_accessschema_cached_roles");
    delete_user_meta($user_id, "{$slug}_accessschema_cached_roles_timestamp");

    wp_safe_redirect(get_edit_user_link($user_id));
    exit;
});

add_action('admin_action_refresh_accessschema_cache', function () {
    if (
        !isset($_GET['user_id'], $_GET['slug']) ||
        !current_user_can('edit_users') ||
        !wp_verify_nonce($_GET['_wpnonce'], "refresh_accessschema_{$_GET['user_id']}_{$_GET['slug']}")
    ) {
        wp_die('Unauthorized request.');
    }

    $user_id = intval($_GET['user_id']);
    $slug = sanitize_key($_GET['slug']);

    $user = get_user_by('ID', $user_id);
    if ($user instanceof WP_User) {
        accessSchema_refresh_roles_for_user($user, $slug);
    }

    wp_safe_redirect(get_edit_user_link($user_id));
    exit;
});