<?php

// File: accessschema-client/cache.php
// @version 1.5.0
// @tool accessschema-client

add_action('wp_login', function($user_login, $user) {
    if (!is_a($user, 'WP_User')) return;

    $roles_data = accessSchema_client_remote_get_roles_by_email($user->user_email);
    if (!is_wp_error($roles_data) && isset($roles_data['roles'])) {
        update_user_meta($user->ID, 'accessschema_cached_roles', $roles_data['roles']);
        update_user_meta($user->ID, 'accessschema_cached_roles_timestamp', time());
    }
}, 10, 2);