<?php
// File: accessschema-client/admin-users.php
// @version 1.3.0
// @tool accessschema-client

/* * Add AccessSchema roles column to the Users admin page.
 * This will display the roles fetched from the remote AccessSchema API.
 */
add_filter('manage_users_columns', function ($columns) {
    $columns['accessschema_roles'] = 'AccessSchema Roles';
    return $columns;
});

/* * Display AccessSchema roles in the custom column.
 * This will show the cached roles and a link to flush the cache.
 */
add_filter('manage_users_custom_column', function ($output, $column_name, $user_id) {
    if ($column_name !== 'accessschema_roles') return $output;

    $roles     = get_user_meta($user_id, 'accessschema_cached_roles', true);
    $timestamp = get_user_meta($user_id, 'accessschema_cached_roles_timestamp', true);

    // Base admin URL
    $base_url = admin_url('users.php');

    if (!is_array($roles) || empty($roles)) {
        // No roles → show [Request] link
        $request_url = wp_nonce_url(
            add_query_arg([
                'action'  => 'refresh_accessschema_cache',
                'user_id' => $user_id,
            ], $base_url),
            'refresh_accessschema_' . $user_id
        );

        return '[None] <a href="' . esc_url($request_url) . '" style="margin-left:4px;">[Request]</a>';
    }

    // If roles exist → show timestamp and flush/refresh links
    $time_display = $timestamp
        ? date_i18n('m/d/Y h:i a', intval($timestamp))
        : '[Unknown]';

    $flush_url = wp_nonce_url(
        add_query_arg([
            'action'  => 'flush_accessschema_cache',
            'user_id' => $user_id,
        ], $base_url),
        'flush_accessschema_' . $user_id
    );

    $refresh_url = wp_nonce_url(
        add_query_arg([
            'action'  => 'refresh_accessschema_cache',
            'user_id' => $user_id,
        ], $base_url),
        'refresh_accessschema_' . $user_id
    );

    return esc_html($time_display) .
        ' <a href="' . esc_url($flush_url) . '" style="margin-left:4px;">[Flush]</a>' .
        ' <a href="' . esc_url($refresh_url) . '" style="margin-left:4px;">[Refresh]</a>';
}, 10, 3);

/* * Handle the actions for flushing and refreshing AccessSchema roles cache.
 * This will process the requests from the custom links in the roles column.
 */
add_action('admin_init', function () {
    if (
        isset($_GET['action'], $_GET['user_id']) &&
        current_user_can('manage_options')
    ) {
        $user_id = intval($_GET['user_id']);

        if ($_GET['action'] === 'flush_accessschema_cache') {
            check_admin_referer('flush_accessschema_' . $user_id);
            delete_user_meta($user_id, 'accessschema_cached_roles');
            delete_user_meta($user_id, 'accessschema_cached_roles_timestamp');
            wp_redirect(add_query_arg(['message' => 'accessschema_cache_flushed'], admin_url('users.php')));
            exit;
        }

        if ($_GET['action'] === 'refresh_accessschema_cache') {
            check_admin_referer('refresh_accessschema_' . $user_id);

            $user = get_user_by('ID', $user_id);
            if ($user) {
                $roles_data = accessSchema_client_remote_get_roles_by_email($user->user_email);
                if (!is_wp_error($roles_data) && isset($roles_data['roles'])) {
                    update_user_meta($user_id, 'accessschema_cached_roles', $roles_data['roles']);
                    update_user_meta($user_id, 'accessschema_cached_roles_timestamp', time());
                }
            }

            wp_redirect(add_query_arg(['message' => 'accessschema_cache_refreshed'], admin_url('users.php')));
            exit;
        }
    }