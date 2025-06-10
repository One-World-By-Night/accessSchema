<?php
// File: accessschema-client/hooks.php
// @version 1.2.0
// @tool accessschema-client

defined('ABSPATH') || exit;

/* Get the stored remote AccessSchema URL.
 */
function accessSchema_client_get_remote_url() {
    $url = trim(get_option('accessschema_client_url'));
    return rtrim($url, '/'); // sanitize trailing slash
}

/* Get the stored remote AccessSchema API key.
 */
function accessSchema_client_get_remote_key() {
    return trim(get_option('accessschema_client_key'));
}

/* Send a POST request to the accessSchema API endpoint.
 *
 * @param string $endpoint The API endpoint path (e.g., 'roles', 'grant', 'revoke').
 * @param array $body JSON body parameters.
 * @return array|WP_Error Response array or error.
 */
function accessSchema_client_remote_post($endpoint, array $body) {
    $url_base = accessSchema_client_get_remote_url();
    $key      = accessSchema_client_get_remote_key();

    if (!$url_base || !$key) {
        return new WP_Error('config_error', 'Remote URL or API key is not set.');
    }

    $url = trailingslashit($url_base) . ltrim($endpoint, '/');

    $response = wp_remote_post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key'    => $key,
        ],
        'body'    => wp_json_encode($body),
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $data   = json_decode(wp_remote_retrieve_body($response), true);

    if ($status !== 200 && $status !== 201) {
        return new WP_Error('api_error', 'Remote API returned HTTP ' . $status, $data);
    }

    return $data;
}

/* Get remote roles for a user by email.
 */
function accessSchema_client_remote_get_roles_by_email($email) {
    $user = get_user_by('email', $email);

    if ($user) {
        $cached = get_user_meta($user->ID, 'accessschema_cached_roles', true);
        if (!empty($cached) && is_array($cached)) {
            return ['roles' => $cached];
        }
    }

    // Fallback to remote call
    $response = accessSchema_client_remote_post('roles', [
        'email' => sanitize_email($email)
    ]);

    // If successful and user exists, cache it
    if (!is_wp_error($response) && isset($response['roles']) && $user) {
        update_user_meta($user->ID, 'accessschema_cached_roles', $response['roles']);
    }

    return $response;
}

/* Grant a role to a user on the remote system.
 */
function accessSchema_client_remote_grant_role($email, $role_path) {
    $result = accessSchemaclient_remote_post('grant', [
        'email'     => sanitize_email($email),
        'role_path' => sanitize_text_field($role_path),
    ]);

    // Invalidate cache
    $user = get_user_by('email', $email);
    if ($user) {
        delete_user_meta($user->ID, 'accessschema_cached_roles');
    }

    return $result;
}

/* Revoke a role to a user on the remote system.
 */
function accessSchema_client_remote_revoke_role($email, $role_path) {
    $result = accessSchema_client_remote_post('revoke', [
        'email'     => sanitize_email($email),
        'role_path' => sanitize_text_field($role_path),
    ]);

    $user = get_user_by('email', $email);
    if ($user) {
        delete_user_meta($user->ID, 'accessschema_cached_roles');
    }

    return $result;
}

/* Refresh roles for a user by fetching from remote.
 *
 * @param WP_User $user The user object.
 * @return array|WP_Error The roles array or error.
 */
function accessSchema_refresh_roles_for_user($user) {
    if ($user instanceof WP_User) {
        $email = $user->user_email;
        $roles = accessSchema_client_remote_post('roles', ['email' => sanitize_email($email)]);
        if (!is_wp_error($roles) && isset($roles['roles'])) {
            update_user_meta($user->ID, 'accessschema_cached_roles', $roles['roles']);
            return $roles;
        }
    }
    return new WP_Error('refresh_failed', 'Could not refresh roles.');
}

/* Check if user has role or descendant.
 */
function accessSchema_client_remote_check_access($email, $role_path, $include_children = true) {
    $response = accessSchema_client_remote_post('check', [
        'email'            => sanitize_email($email),
        'role_path'        => sanitize_text_field($role_path),
        'include_children' => $include_children,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    return !empty($response['granted']);
}

do_action('accessSchema_client_ready');