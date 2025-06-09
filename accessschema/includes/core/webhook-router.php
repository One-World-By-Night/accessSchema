<?php
// File: includes/core/webhook-router.php
// @version 0.2.0
// Author: greghacke

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    // CORS preflight support
    register_rest_route('access-schema/v1', '/.*', [
        'methods' => ['OPTIONS'],
        'callback' => fn() => new WP_REST_Response(null, 204),
        'permission_callback' => '__return_true',
    ]);

    // Register a group of roles
    register_rest_route('access-schema/v1', '/register', [
        'methods' => 'POST',
        'callback' => 'accessSchema_api_register_roles',
        'permission_callback' => 'accessSchema_api_permission_check',
    ]);

    // Get roles for a user
    register_rest_route('access-schema/v1', '/roles', [
        'methods' => 'POST',
        'callback' => 'accessSchema_api_get_roles',
        'permission_callback' => 'accessSchema_api_permission_check',
    ]);

    // Grant a role to a user
    register_rest_route('access-schema/v1', '/grant', [
        'methods' => 'POST',
        'callback' => 'accessSchema_api_grant_role',
        'permission_callback' => 'accessSchema_api_permission_check',
    ]);

    // Revoke a role from a user
    register_rest_route('access-schema/v1', '/revoke', [
        'methods' => 'POST',
        'callback' => 'accessSchema_api_revoke_role',
        'permission_callback' => 'accessSchema_api_permission_check',
    ]);

    // Check access
    register_rest_route('access-schema/v1', '/check', [
        'methods' => 'POST',
        'callback' => 'accessSchema_api_check_permission',
        'permission_callback' => 'accessSchema_api_permission_check',
    ]);
});

// --- Shared API Key Auth ---
function accessSchema_api_permission_check($request) {
    $api_key = $request->get_header('x-api-key');
    $expected_key = defined('ACCESSSCHEMA_API_KEY') ? ACCESSSCHEMA_API_KEY : '';

    if (!$api_key || $api_key !== $expected_key) {
        return new WP_Error('unauthorized', 'Invalid or missing API key', ['status' => 403]);
    }
    return true;
}

// --- Helper: Resolve User ---
function accessSchema_resolve_user( $params ) {
    if ( ! empty( $params['id'] ) ) {
        return get_user_by( 'id', intval( $params['id'] ) );
    }
    if ( ! empty( $params['email'] ) ) {
        return get_user_by( 'email', sanitize_email( $params['email'] ) );
    }
    return null;
}

// --- Register Roles in Bulk ---
function accessSchema_api_register_roles($request) {
    $params = $request->get_json_params();
    $paths = $params['paths'] ?? [];

    if (empty($paths) || !is_array($paths)) {
        return new WP_Error('invalid_request', 'Missing or invalid "paths" array.', ['status' => 400]);
    }

    $registered = [];

    foreach ($paths as $path) {
        if (!is_array($path) || empty($path)) {
            continue;
        }

        $sanitized = array_map('sanitize_text_field', $path);
        accessSchema_register_path($sanitized);
        $registered[] = implode('/', $sanitized);
    }

    return rest_ensure_response([
        'registered' => $registered
    ]);
}

// --- GET Roles for User ---
function accessSchema_api_get_roles($request) {
    $params = $request->get_json_params();
    $user = accessSchema_resolve_user($params);

    if (!$user) {
        return new WP_Error('user_not_found', 'User not found by id or email.', ['status' => 404]);
    }

    $roles = get_user_meta($user->ID, 'accessSchema', true);
    return rest_ensure_response([
        'email' => $user->user_email,
        'roles' => is_array($roles) ? $roles : [],
    ]);
}

// --- GRANT Role to User ---
function accessSchema_api_grant_role($request) {
    $params = $request->get_json_params();
    $user = accessSchema_resolve_user($params);
    $role_path = sanitize_text_field($params['role_path'] ?? '');

    if (!$user || !$role_path) {
        return new WP_Error('invalid_request', 'Missing user (id/email) or role_path.', ['status' => 400]);
    }

    $result = accessSchema_add_role($user->ID, $role_path);
    return rest_ensure_response([
        'email' => $user->user_email,
        'granted' => $result,
    ]);
}

// --- REVOKE Role from User ---
function accessSchema_api_revoke_role($request) {
    $params = $request->get_json_params();
    $user = accessSchema_resolve_user($params);
    $role_path = sanitize_text_field($params['role_path'] ?? '');

    if (!$user || !$role_path) {
        return new WP_Error('invalid_request', 'Missing user (id/email) or role_path.', ['status' => 400]);
    }

    $result = accessSchema_remove_role($user->ID, $role_path);
    return rest_ensure_response([
        'email' => $user->user_email,
        'revoked' => $result,
    ]);
}

// --- CHECK Permission for User ---
function accessSchema_api_check_permission($request) {
    $params = $request->get_json_params();
    $user = accessSchema_resolve_user($params);
    $role_path = sanitize_text_field($params['role_path'] ?? '');
    $include_children = !empty($params['include_children']);

    if (!$user || !$role_path) {
        return new WP_Error('invalid_request', 'Missing user (id/email) or role_path.', ['status' => 400]);
    }

    $has_access = accessSchema_check_permission($user->ID, $role_path, $include_children);
    return rest_ensure_response([
        'email' => $user->user_email,
        'granted' => $has_access,
    ]);
}