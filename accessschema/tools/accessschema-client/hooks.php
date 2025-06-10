<?php
// File: accessschema-client/hooks.php
// @version 1.1.0
// @tool accessschema-client

defined('ABSPATH') || exit;

/* Get the stored remote AccessSchema URL.
 */
function accessSchema_get_remote_url() {
    $url = trim(get_option('accessschema_client_url'));
    return rtrim($url, '/'); // sanitize trailing slash
}

/* Get the stored remote AccessSchema API key.
 */
function accessSchema_get_remote_key() {
    return trim(get_option('accessschema_client_key'));
}

/* Send a POST request to the accessSchema API endpoint.
 *
 * @param string $endpoint The API endpoint path (e.g., 'roles', 'grant', 'revoke').
 * @param array $body JSON body parameters.
 * @return array|WP_Error Response array or error.
 */
function accessSchema_remote_post($endpoint, array $body) {
    $url_base = accessSchema_get_remote_url();
    $key      = accessSchema_get_remote_key();

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
function accessSchema_remote_get_roles_by_email($email) {
    return accessSchema_remote_post('roles', [
        'email' => sanitize_email($email)
    ]);
}

/* Grant a role to a user on the remote system.
 */
function accessSchema_remote_grant_role($email, $role_path) {
    return accessSchema_remote_post('grant', [
        'email'     => sanitize_email($email),
        'role_path' => sanitize_text_field($role_path),
    ]);
}

/* Check if user has role or descendant.
 */
function accessSchema_remote_check_access($email, $role_path, $include_children = true) {
    $response = accessSchema_remote_post('check', [
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