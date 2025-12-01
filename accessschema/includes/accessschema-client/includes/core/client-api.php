<?php

/** File: includes/core/client-api.php
 * Text Domain: accessschema-client
 * version 1.2.0
 * @author greghacke
 * Function: This file contains the core client API functions for AccessSchema.
 */

defined('ABSPATH') || exit;

if (!function_exists('asc_log')) {
    /**
     * Conditional logging based on ASC_DEBUG constant or option.
     *
     * @param string $message Log message.
     * @param string $level   Log level: DEBUG, INFO, WARN, ERROR.
     */
    function asc_log($message, $level = 'DEBUG')
    {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARN' => 2, 'ERROR' => 3];

        // Check for debug mode via constant or option
        $debug_enabled = defined('ASC_DEBUG') && ASC_DEBUG;
        if (!$debug_enabled && defined('ASC_PREFIX')) {
            $client_id = strtolower(str_replace('_', '-', ASC_PREFIX));
            $debug_enabled = get_option("{$client_id}_accessschema_debug", false);
        }

        // Always log ERROR, otherwise only if debug enabled
        if ($level === 'ERROR' || $debug_enabled) {
            error_log("[ASC][{$level}] {$message}");
        }
    }
}

if (!function_exists('as_client_option_key')) {
    function as_client_option_key($client_id, $key)
    {
        return "{$client_id}_accessschema_{$key}";
    }
}

if (!function_exists('accessSchema_is_remote_mode')) {
    function accessSchema_is_remote_mode($client_id)
    {
        return get_option(as_client_option_key($client_id, 'mode'), 'remote') === 'remote';
    }
}

if (!function_exists('accessSchema_client_get_remote_url')) {
    function accessSchema_client_get_remote_url($client_id)
    {
        $url = trim(get_option("{$client_id}_accessschema_client_url"));
        return rtrim($url, '/');
    }
}

if (!function_exists('accessSchema_client_get_remote_key')) {
    function accessSchema_client_get_remote_key($client_id)
    {
        return trim(get_option("{$client_id}_accessschema_client_key"));
    }
}

if (!function_exists('accessSchema_client_remote_post')) {
    /**
     * Send a POST request to the AccessSchema API endpoint.
     *
     * @param string $client_id     The unique plugin slug.
     * @param string $endpoint The API endpoint path (e.g., 'roles', 'grant', 'revoke').
     * @param array  $body     JSON body parameters.
     * @return array|WP_Error  Response array or error.
     */
    function accessSchema_client_remote_post($client_id, $endpoint, array $body)
    {
        // Defensive logging
        if (!is_string($client_id)) {
            asc_log("Non-string slug in accessSchema_client_remote_post: " . print_r($client_id, true), 'ERROR');
            return new WP_Error('invalid_slug', 'Plugin slug must be a string');
        }

        $url_base = accessSchema_client_get_remote_url($client_id);
        $key      = accessSchema_client_get_remote_key($client_id);

        if (!$url_base || !$key) {
            asc_log("Remote URL or API key is not set for slug: {$client_id}", 'ERROR');
            return new WP_Error('config_error', 'Remote URL or API key is not set for plugin: ' . esc_html($client_id));
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
            asc_log("HTTP POST ERROR: " . $response->get_error_message(), 'ERROR');
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data   = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data)) {
            asc_log("Invalid JSON response for slug {$client_id}", 'ERROR');
            return new WP_Error('api_response_invalid', 'Invalid JSON from API.', ['slug' => $client_id]);
        }

        if ($status !== 200 && $status !== 201) {
            asc_log("API returned HTTP {$status} for slug {$client_id}", 'WARN');
            return new WP_Error('api_error', 'Remote API returned HTTP ' . $status, ['slug' => $client_id, 'data' => $data]);
        }

        return $data;
    }
}

if (!function_exists('accessSchema_client_remote_get_roles_by_email')) {
    function accessSchema_client_remote_get_roles_by_email($email, $client_id)
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            asc_log("No local user found with email: {$email}", 'DEBUG');
        }

        $is_remote = accessSchema_is_remote_mode($client_id);

        if (!$is_remote) {
            if (!$user) {
                return new WP_Error('user_not_found', 'User not found.', ['status' => 404]);
            }

            $response = accessSchema_client_local_post('roles', ['email' => sanitize_email($email)]);
            return $response;
        }

        // Check cache first
        if ($user) {
            $cache_key = "{$client_id}_accessschema_cached_roles";
            $cached = get_user_meta($user->ID, $cache_key, true);

            if (is_array($cached) && !empty($cached)) {
                return ['roles' => $cached];
            }
        }

        asc_log("No cache for {$email} — requesting remote roles", 'DEBUG');

        $response = accessSchema_client_remote_post($client_id, 'roles', ['email' => sanitize_email($email)]);

        if (
            !is_wp_error($response) &&
            is_array($response) &&
            isset($response['roles']) &&
            is_array($response['roles']) &&
            $user
        ) {
            update_user_meta($user->ID, "{$client_id}_accessschema_cached_roles", $response['roles']);
            update_user_meta($user->ID, "{$client_id}_accessschema_cached_roles_timestamp", time());
        } else {
            asc_log("Failed to retrieve roles remotely for {$email}", 'WARN');
        }

        return $response;
    }
}

if (!function_exists('accessSchema_client_remote_grant_role')) {
    function accessSchema_client_remote_grant_role($email, $role_path, $client_id)
    {
        $user = get_user_by('email', $email);

        $payload = [
            'email'     => sanitize_email($email),
            'role_path' => sanitize_text_field($role_path),
        ];

        $result = accessSchema_is_remote_mode($client_id)
            ? accessSchema_client_remote_post($client_id, 'grant', $payload)
            : accessSchema_client_local_post('grant', $payload);

        if ($user) {
            delete_user_meta($user->ID, "{$client_id}_accessschema_cached_roles");
            delete_user_meta($user->ID, "{$client_id}_accessschema_cached_roles_timestamp");
        }

        return $result;
    }
}

if (!function_exists('accessSchema_client_remote_revoke_role')) {
    function accessSchema_client_remote_revoke_role($email, $role_path, $client_id)
    {
        $user = get_user_by('email', $email);

        $payload = [
            'email'     => sanitize_email($email),
            'role_path' => sanitize_text_field($role_path),
        ];

        $result = accessSchema_is_remote_mode($client_id)
            ? accessSchema_client_remote_post($client_id, 'revoke', $payload)
            : accessSchema_client_local_post('revoke', $payload);

        if ($user) {
            delete_user_meta($user->ID, "{$client_id}_accessschema_cached_roles");
            delete_user_meta($user->ID, "{$client_id}_accessschema_cached_roles_timestamp");
        }

        return $result;
    }
}

if (!function_exists('accessSchema_refresh_roles_for_user')) {
    function accessSchema_refresh_roles_for_user($user, $client_id)
    {
        if (!($user instanceof WP_User)) {
            return new WP_Error('invalid_user', 'User object is invalid.');
        }

        $email = $user->user_email;
        $response = accessSchema_client_remote_get_roles_by_email($email, $client_id);

        if (
            !is_wp_error($response) &&
            isset($response['roles']) &&
            is_array($response['roles'])
        ) {
            update_user_meta($user->ID, "{$client_id}_accessschema_cached_roles", $response['roles']);
            update_user_meta($user->ID, "{$client_id}_accessschema_cached_roles_timestamp", time());
            return $response;
        }

        return new WP_Error('refresh_failed', 'Could not refresh roles.');
    }
}

if (!function_exists('accessSchema_client_remote_check_access')) {
    /**
     * Check if the given user email has access to a specific role path in a plugin slug.
     *
     * @param string $email            The user's email address.
     * @param string $role_path        The role path to check (e.g., "Chronicle/KONY/HST").
     * @param string $client_id             The plugin slug (e.g., 'owbn_board').
     * @param bool   $include_children Whether to check subroles.
     *
     * @return bool|WP_Error True if access granted, false if not, or WP_Error on failure.
     */
    function accessSchema_client_remote_check_access($email, $role_path, $client_id, $include_children = true)
    {
        // Validate and sanitize inputs
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address.');
        }

        if (!is_string($role_path) || trim($role_path) === '') {
            return new WP_Error('invalid_role_path', 'Role path must be a non-empty string.');
        }

        if (!is_string($client_id) || trim($client_id) === '') {
            return new WP_Error('invalid_slug', 'Plugin slug must be a non-empty string.');
        }

        // Build payload
        $payload = [
            'email'            => $email,
            'role_path'        => sanitize_text_field($role_path),
            'include_children' => (bool) $include_children,
        ];

        // Decide whether to use local or remote check
        if (!function_exists('accessSchema_is_remote_mode')) {
            return new WP_Error('missing_dependency', 'accessSchema_is_remote_mode() is not available.');
        }

        if (!function_exists('accessSchema_client_local_post') || !function_exists('accessSchema_client_remote_post')) {
            return new WP_Error('missing_dependency', 'Required AccessSchema client functions are not available.');
        }

        // Call appropriate API
        $data = accessSchema_is_remote_mode($client_id)
            ? accessSchema_client_remote_post($client_id, 'check', $payload)
            : accessSchema_client_local_post('check', $payload);

        // Handle error responses
        if (is_wp_error($data)) {
            return $data;
        }

        if (!is_array($data) || !array_key_exists('granted', $data)) {
            return new WP_Error('invalid_response', 'Invalid response from access check.');
        }

        // Return true/false based on 'granted' key
        return (bool) $data['granted'];
    }
}

if (!function_exists('asc_hook_user_has_cap_filter')) {
    /**
     * Map WordPress capabilities to AccessSchema roles, or allow group-level access.
     *
     * @param array    $allcaps All capabilities for the user.
     * @param string[] $caps    Requested capabilities.
     * @param array    $args    [0] => requested cap, [1] => object_id (optional), etc.
     * @param WP_User  $user    WP_User object.
     * @return array            Modified capabilities.
     */
    function asc_hook_user_has_cap_filter($allcaps, $caps, $args, $user)
    {
        $requested_cap = $caps[0] ?? null;
        if (!$requested_cap || !$user instanceof WP_User) {
            return $allcaps;
        }

        // Build client_id the same way client-init.php does
        $client_id = defined('ASC_PREFIX')
            ? strtolower(str_replace('_', '-', ASC_PREFIX))
            : 'accessschema-client';

        $mode  = get_option("{$client_id}_accessschema_mode", 'remote');
        $email = $user->user_email;

        if ($mode === 'none') {
            return $allcaps;
        }

        if (!is_email($email)) {
            return $allcaps;
        }

        // Group-level check for asc_has_access_to_group
        if ($requested_cap === 'asc_has_access_to_group') {
            // $args[0] is the capability, $args[1] is the first extra argument (group path)
            $group_path = $args[1] ?? null;
            if (!$group_path) {
                return $allcaps;
            }

            // Use existing function - it handles both local and remote modes internally
            $roles_data = accessSchema_client_remote_get_roles_by_email($email, $client_id);
            $roles = $roles_data['roles'] ?? [];

            $has_access = in_array($group_path, $roles, true) ||
                !empty(preg_grep('#^' . preg_quote($group_path, '#') . '/#', $roles));

            if ($has_access) {
                $allcaps[$requested_cap] = true;
            }

            return $allcaps;
        }

        // Mapped capability check
        $role_map = get_option("{$client_id}_capability_map", []);
        if (empty($role_map[$requested_cap])) {
            return $allcaps;
        }

        foreach ((array) $role_map[$requested_cap] as $raw_path) {
            $role_path = asc_expand_role_path($raw_path);

            // Use existing function - it handles both local and remote modes internally
            $granted = accessSchema_client_remote_check_access($email, $role_path, $client_id, true);

            if (is_wp_error($granted)) {
                continue;
            }

            if ($granted === true) {
                $allcaps[$requested_cap] = true;
                break;
            }
        }

        return $allcaps;
    }
}

if (!function_exists('asc_expand_role_path')) {
    /**
     * Expand dynamic role path placeholders like `$slug`.
     */
    function asc_expand_role_path($raw_path)
    {
        $slug = get_query_var('slug') ?: '';
        return str_replace('$slug', sanitize_key($slug), $raw_path);
    }
}

add_filter('user_has_cap', 'asc_hook_user_has_cap_filter', 10, 4);


if (!function_exists('accessSchema_client_local_post')) {
    function accessSchema_client_local_post($endpoint, array $body)
    {
        $function_map = [
            'roles'    => 'accessSchema_api_get_roles',
            'grant'    => 'accessSchema_api_grant_role',
            'revoke'   => 'accessSchema_api_revoke_role',
            'check'    => 'accessSchema_api_check_permission',
            'register' => 'accessSchema_api_register_roles',
        ];

        if (!isset($function_map[$endpoint])) {
            return new WP_Error('invalid_local_endpoint', 'Unrecognized local endpoint.');
        }

        $target_function = $function_map[$endpoint];

        // Check if main AccessSchema plugin is active
        if (!function_exists($target_function)) {
            return new WP_Error(
                'missing_dependency',
                'Local mode requires the AccessSchema plugin to be installed and active on this site.',
                ['status' => 500]
            );
        }

        $request = new WP_REST_Request('POST', '/access-schema/v1/' . ltrim($endpoint, '/'));
        $request->set_body_params($body);

        $response = call_user_func($target_function, $request);
        return ($response instanceof WP_Error) ? $response : $response->get_data();
    }
}

do_action('accessSchema_client_ready');
