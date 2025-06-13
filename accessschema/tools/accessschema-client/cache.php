<?php

// File: accessschema-client/cache.php
// @version 1.6.0
// @tool accessschema-client

defined('ABSPATH') || exit;

add_action('wp_login', function($user_login, $user) {
    if (!is_a($user, 'WP_User')) return;

    $registered_slugs = apply_filters('accessschema_registered_slugs', []);

    foreach ($registered_slugs as $slug => $label) {
        $result = apply_filters('accessschema_client_refresh_roles', null, $user, $slug);

        if (is_array($result) && isset($result['roles'])) {
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles", $result['roles']);
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp", time());
        }
    }
}, 10, 2);