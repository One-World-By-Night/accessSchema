<?php
// File: accessschema-client/hooks.php
// @version 1.6.0
// @tool accessschema-client

defined('ABSPATH') || exit;

if (!function_exists('as_client_option_key')) {
    function as_client_option_key($slug, $key) {
        return "{$slug}_accessschema_{$key}";
    }
}

if (!function_exists('accessSchema_is_remote_mode')) {
    function accessSchema_is_remote_mode($slug) {
        return get_option(as_client_option_key($slug, 'mode'), 'remote') === 'remote';
    }
}

if (!function_exists('accessSchema_client_get_remote_url')) {
    function accessSchema_client_get_remote_url($slug) {
        $url = trim(get_option("{$slug}_accessschema_client_url"));
        return rtrim($url, '/');
    }
}

if (!function_exists('accessSchema_client_get_remote_key')) {
    function accessSchema_client_get_remote_key($slug) {
        return trim(get_option("{$slug}_accessschema_client_key"));
    }
}

if (!function_exists('accessSchema_client_remote_post')) {
    /**
     * Send a POST request to the AccessSchema API endpoint.
     *
     * @param string $slug     The unique plugin slug.
     * @param string $endpoint The API endpoint path (e.g., 'roles', 'grant', 'revoke').
     * @param array  $body     JSON body parameters.
     * @return array|WP_Error  Response array or error.
     */
    function accessSchema_client_remote_post($slug, $endpoint, array $body) {
        // Defensive logging
        if (!is_string($slug)) {
            error_log("[AS] FATAL: Non-string slug in accessSchema_client_remote_post: " . print_r($slug, true));

            // Get trace
            ob_start();
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $trace = ob_get_clean();
            error_log("[AS] Stack trace:\n" . $trace);

            return new WP_Error('invalid_slug', 'Plugin slug must be a string');
        }

        $url_base = accessSchema_client_get_remote_url($slug);
        $key      = accessSchema_client_get_remote_key($slug);

        if (!$url_base || !$key) {
            error_log("[AS] ERROR: Remote URL or API key is not set for plugin slug: " . print_r($slug, true));
            return new WP_Error('config_error', 'Remote URL or API key is not set for plugin: ' . esc_html($slug));
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
            error_log("[AS] HTTP POST ERROR: " . $response->get_error_message());
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data   = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data)) {
            error_log("[AS] ERROR: Invalid JSON response for slug {$slug}: " . wp_remote_retrieve_body($response));
            return new WP_Error('api_response_invalid', 'Invalid JSON from API.', ['slug' => $slug]);
        }

        if ($status !== 200 && $status !== 201) {
            error_log("[AS] ERROR: API returned HTTP $status for slug {$slug} with body: " . print_r($data, true));
            return new WP_Error('api_error', 'Remote API returned HTTP ' . $status, ['slug' => $slug, 'data' => $data]);
        }

        return $data;
    }
}

if (!function_exists('accessSchema_client_remote_get_roles_by_email')) {
    function accessSchema_client_remote_get_roles_by_email($email, $slug) {
        // error_log("[OWBN] Begin role lookup for {$email} in slug {$slug}");

        $user = get_user_by('email', $email);
        if (!$user) {
            error_log("[OWBN] No user found with email: {$email}");
        }

        $is_remote = accessSchema_is_remote_mode($slug);
        // error_log("[OWBN] accessSchema_is_remote_mode({$slug}) = " . ($is_remote ? 'true' : 'false'));

        if (!$is_remote) {
            if (!$user) {
                return new WP_Error('user_not_found', 'User not found.', ['status' => 404]);
            }

            $response = accessSchema_client_local_post('roles', ['email' => sanitize_email($email)]);
            // error_log("[OWBN] Local mode response for {$email}: " . print_r($response, true));
            return $response;
        }

        // Check cache first
        if ($user) {
            $cache_key = "{$slug}_accessschema_cached_roles";
            $cached = get_user_meta($user->ID, $cache_key, true);
            // error_log("[OWBN] Cached roles for {$email} → " . print_r($cached, true));

            if (is_array($cached) && !empty($cached)) {
                return ['roles' => $cached];
            }
        }

        error_log("[OWBN] No cache — requesting remote roles");

        $response = accessSchema_client_remote_post($slug, 'roles', ['email' => sanitize_email($email)]);

        // error_log("[OWBN] Remote response: " . print_r($response, true));

        if (
            !is_wp_error($response) &&
            is_array($response) &&
            isset($response['roles']) &&
            is_array($response['roles']) &&
            $user
        ) {
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles", $response['roles']);
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp", time());
        } else {
            error_log("[OWBN] Failed to retrieve roles remotely or response invalid");
        }

        return $response;
    }
}

if (!function_exists('accessSchema_client_remote_grant_role')) {
    function accessSchema_client_remote_grant_role($email, $role_path, $slug) {
        $user = get_user_by('email', $email);

        $payload = [
            'email'     => sanitize_email($email),
            'role_path' => sanitize_text_field($role_path),
        ];

        $result = accessSchema_is_remote_mode($slug)
            ? accessSchema_client_remote_post($slug, 'grant', $payload)
            : accessSchema_client_local_post('grant', $payload);

        if ($user) {
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles");
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp");
        }

        return $result;
    }
}

if (!function_exists('accessSchema_client_remote_revoke_role')) {
    function accessSchema_client_remote_revoke_role($email, $role_path, $slug) {
        $user = get_user_by('email', $email);

        $payload = [
            'email'     => sanitize_email($email),
            'role_path' => sanitize_text_field($role_path),
        ];

        $result = accessSchema_is_remote_mode($slug)
            ? accessSchema_client_remote_post($slug, 'revoke', $payload)
            : accessSchema_client_local_post('revoke', $payload);

        if ($user) {
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles");
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp");
        }

        return $result;
    }
}

if (!function_exists('accessSchema_refresh_roles_for_user')) {
    function accessSchema_refresh_roles_for_user($user, $slug) {
        if (!($user instanceof WP_User)) {
            return new WP_Error('invalid_user', 'User object is invalid.');
        }

        $email = $user->user_email;
        $response = accessSchema_client_remote_get_roles_by_email($email, $slug);

        if (
            !is_wp_error($response) &&
            isset($response['roles']) &&
            is_array($response['roles'])
        ) {
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles", $response['roles']);
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp", time());
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
     * @param string $slug             The plugin slug (e.g., 'owbn_board').
     * @param bool   $include_children Whether to check subroles.
     *
     * @return bool|WP_Error True if access granted, false if not, or WP_Error on failure.
     */
    function accessSchema_client_remote_check_access($email, $role_path, $slug, $include_children = true) {
        // Validate and sanitize inputs
        $email = sanitize_email($email);
        if (!is_email($email)) {
            // error_log("[AS] FATAL: Invalid email provided to check_access: " . print_r($email, true));
            return new WP_Error('invalid_email', 'Invalid email address.');
        }

        if (!is_string($role_path) || trim($role_path) === '') {
            // error_log("[AS] FATAL: Invalid role_path provided to check_access: " . print_r($role_path, true));
            return new WP_Error('invalid_role_path', 'Role path must be a non-empty string.');
        }

        if (!is_string($slug) || trim($slug) === '') {
            // error_log("[AS] FATAL: Invalid or missing plugin slug in check_access: " . print_r($slug, true));
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
        $data = accessSchema_is_remote_mode($slug)
            ? accessSchema_client_remote_post($slug, 'check', $payload)
            : accessSchema_client_local_post('check', $payload);

        // Handle error responses
        if (is_wp_error($data)) {
            // error_log("[AS] ERROR: access check failed for {$email} / {$role_path} in slug {$slug}: " . $data->get_error_message());
            return $data;
        }

        if (!is_array($data) || !array_key_exists('granted', $data)) {
            // error_log("[AS] ERROR: Malformed response from access check for {$email} / {$role_path}: " . print_r($data, true));
            return new WP_Error('invalid_response', 'Invalid response from access check.');
        }

        // Return true/false based on 'granted' key
        return (bool) $data['granted'];
    }
}

if (!function_exists('accessSchema_client_local_post')) {
    function accessSchema_client_local_post($endpoint, array $body) {
        $request = new WP_REST_Request('POST', '/access-schema/v1/' . ltrim($endpoint, '/'));
        $request->set_body_params($body);

        $function_map = [
            'roles'  => 'accessSchema_api_get_roles',
            'grant'  => 'accessSchema_api_grant_role',
            'revoke' => 'accessSchema_api_revoke_role',
            'check'  => 'accessSchema_api_check_permission',
        ];

        if (!isset($function_map[$endpoint])) {
            return new WP_Error('invalid_local_endpoint', 'Unrecognized local endpoint.');
        }

        $response = call_user_func($function_map[$endpoint], $request);
        return ($response instanceof WP_Error) ? $response : $response->get_data();
    }
}

do_action('accessSchema_client_ready');