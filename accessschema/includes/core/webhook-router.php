<?php
// File: includes/core/webhook-router.php
// @version 1.3.0
// Author: greghacke
// Required for REST API routes to handle access schema operations
/**
 * Access Schema REST API Routes
 *
 * This file defines the REST API endpoints for managing access schema roles and permissions.
 * It includes endpoints for registering roles, granting/revoking roles to/from users,
 * checking user permissions, and handling CORS preflight requests.
 * 
 * Use of the API required a shared API key defined in the wp-config.php file:
 * define('ACCESSSCHEMA_API_KEY_RO', 'your_api_ro_key_here');
 * define('ACCESSSCHEMA_API_KEY_RW', 'your_api_rw_key_here'); // Optional, for read-write access
 */


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
    $route   = $request->get_route();

    $read_key  = defined('ACCESSSCHEMA_API_KEY_READONLY') ? ACCESSSCHEMA_API_KEY_READONLY : '';
    $write_key = defined('ACCESSSCHEMA_API_KEY_READWRITE') ? ACCESSSCHEMA_API_KEY_READWRITE : '';

    // Read-only endpoints
    $read_endpoints = [
        '/access-schema/v1/roles',
        '/access-schema/v1/check'
    ];

    // Read-write endpoints
    $write_endpoints = [
        '/access-schema/v1/register',
        '/access-schema/v1/grant',
        '/access-schema/v1/revoke',
    ];

    if (in_array($route, $read_endpoints)) {
        if ($api_key === $read_key || $api_key === $write_key) {
            return true;
        }
    } elseif (in_array($route, $write_endpoints)) {
        if ($api_key === $write_key) {
            return true;
        }
    }

    return new WP_Error('unauthorized', 'Invalid or missing API key for this operation', ['status' => 403]);
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