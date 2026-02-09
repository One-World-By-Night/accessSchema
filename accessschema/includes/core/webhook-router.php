<?php

/**
 * File: includes/core/webhook-router.php
 *
 * @version 2.0.3
 * Author: greghacke
 *
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

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		// CORS preflight support
		register_rest_route(
			'access-schema/v1',
			'/.*',
			array(
				'methods'             => 'OPTIONS',
				'callback'            => function () {
					return new WP_REST_Response( null, 204 );
				},
				'permission_callback' => '__return_true',
			)
		);

		// Register a group of roles
		register_rest_route(
			'access-schema/v1',
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => 'accessSchema_api_register_roles',
				'permission_callback' => 'accessSchema_api_permission_check',
				'args'                => array(
					'paths' => array(
						'required'          => true,
						'type'              => 'array',
						'validate_callback' => 'accessSchema_validate_paths_array',
					),
				),
			)
		);

		// Get roles for a user
		register_rest_route(
			'access-schema/v1',
			'/roles',
			array(
				'methods'             => 'POST',
				'callback'            => 'accessSchema_api_get_roles',
				'permission_callback' => 'accessSchema_api_permission_check',
			)
		);

		// Get all registered roles
		register_rest_route(
			'access-schema/v1',
			'/roles/all',
			array(
				'methods'             => 'GET',
				'callback'            => 'accessSchema_api_get_all_roles',
				'permission_callback' => 'accessSchema_api_permission_check',
			)
		);

		// Grant a role to a user
		register_rest_route(
			'access-schema/v1',
			'/grant',
			array(
				'methods'             => 'POST',
				'callback'            => 'accessSchema_api_grant_role',
				'permission_callback' => 'accessSchema_api_permission_check',
				'args'                => array(
					'role_path' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Revoke a role from a user
		register_rest_route(
			'access-schema/v1',
			'/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => 'accessSchema_api_revoke_role',
				'permission_callback' => 'accessSchema_api_permission_check',
				'args'                => array(
					'role_path' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Check access
		register_rest_route(
			'access-schema/v1',
			'/check',
			array(
				'methods'             => 'POST',
				'callback'            => 'accessSchema_api_check_permission',
				'permission_callback' => 'accessSchema_api_permission_check',
				'args'                => array(
					'role_path'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include_children' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}
);

/**
 * Validate the paths array structure for the register endpoint.
 *
 * Ensures that the paths parameter is a non-empty array where each element
 * is an array of non-empty string segments.
 *
 * @since 1.0.0
 *
 * @param array           $paths   The paths value to validate.
 * @param WP_REST_Request $request The current REST request object.
 * @param string          $key     The parameter key being validated.
 * @return true|WP_Error True if valid, WP_Error on validation failure.
 */
function accessSchema_validate_paths_array( $paths, $request, $key ) {
	if ( ! is_array( $paths ) || empty( $paths ) ) {
		return new WP_Error( 'invalid_paths', 'Paths must be a non-empty array' );
	}

	foreach ( $paths as $path ) {
		if ( ! is_array( $path ) ) {
			return new WP_Error( 'invalid_path_format', 'Each path must be an array of segments' );
		}

		foreach ( $path as $segment ) {
			if ( ! is_string( $segment ) || empty( trim( $segment ) ) ) {
				return new WP_Error( 'invalid_segment', 'Path segments must be non-empty strings' );
			}
		}
	}

	return true;
}

/**
 * Verify API key authorization for a REST API request.
 *
 * Checks the `x-api-key` header against configured read-only and read-write
 * API keys. Enforces rate limiting and logs all access attempts. Read endpoints
 * accept either key type; write endpoints require the read-write key.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The current REST request object.
 * @return true|WP_Error True if authorized, WP_Error on failure (403 or 429).
 */
function accessSchema_api_permission_check( $request ) {
	$api_key = $request->get_header( 'x-api-key' );
	$route   = $request->get_route();

	// Get client IP
	$client_ip = accessSchema_get_client_ip();

	// Check rate limiting
	if ( ! accessSchema_check_rate_limit( $client_ip ) ) {
		accessSchema_log_event(
			0,
			'api_rate_limited',
			$route,
			array(
				'ip' => $client_ip,
			),
			null,
			'WARN'
		);
		return new WP_Error( 'rate_limited', 'Too many requests', array( 'status' => 429 ) );
	}

	// Get API keys
	$read_key = get_option( 'accessSchema_api_key_readonly' );
	if ( ! $read_key && defined( 'ACCESSSCHEMA_API_KEY_RO' ) ) {
		$read_key = ACCESSSCHEMA_API_KEY_RO;
	}

	$write_key = get_option( 'accessSchema_api_key_readwrite' );
	if ( ! $write_key && defined( 'ACCESSSCHEMA_API_KEY_RW' ) ) {
		$write_key = ACCESSSCHEMA_API_KEY_RW;
	}

	// Define endpoint permissions
	$read_endpoints = array(
		'/access-schema/v1/roles',
		'/access-schema/v1/roles/all',
		'/access-schema/v1/check',
	);

	$write_endpoints = array(
		'/access-schema/v1/register',
		'/access-schema/v1/grant',
		'/access-schema/v1/revoke',
	);

	$authorized = false;
	$key_type   = '';

	if ( in_array( $route, $read_endpoints, true ) ) {
		if ( $read_key === $api_key ) {
			$authorized = true;
			$key_type   = 'readonly';
		} elseif ( $write_key === $api_key ) {
			$authorized = true;
			$key_type   = 'readwrite';
		}
	} elseif ( in_array( $route, $write_endpoints, true ) ) {
		if ( $write_key === $api_key ) {
			$authorized = true;
			$key_type   = 'readwrite';
		}
	}

	// Log API access
	accessSchema_log_event(
		0,
		$authorized ? 'api_access' : 'api_denied',
		$route,
		array(
			'ip'       => $client_ip,
			'key_type' => $key_type,
			'method'   => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
		),
		null,
		$authorized ? 'INFO' : 'WARN'
	);

	if ( ! $authorized ) {
		return new WP_Error( 'unauthorized', 'Invalid or missing API key for this operation', array( 'status' => 403 ) );
	}

	// Add API version header
	add_filter(
		'rest_post_dispatch',
		function ( $response ) {
			$response->header( 'X-AccessSchema-Version', ACCESSSCHEMA_VERSION );
			return $response;
		},
		10,
		1
	);

	return true;
}

/**
 * Check and enforce rate limiting for an identifier.
 *
 * Uses a database-backed sliding window to track request counts. Creates the
 * rate limits table if it does not exist. Cleans up expired entries on each call.
 *
 * @since 1.0.0
 *
 * @param string $identifier The client identifier to rate limit (typically an IP address).
 * @return bool True if the request is within the rate limit, false if exceeded.
 */
function accessSchema_check_rate_limit( $identifier ) {
	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_rate_limits';

	// Create table if not exists
	if ( $table !== $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
            identifier VARCHAR(64) PRIMARY KEY,
            requests INT DEFAULT 1,
            window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_window_start (window_start)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	$hash   = md5( $identifier );
	$window = 60; // 1 minute
	/**
	 * Filters the maximum number of API requests allowed per rate limit window.
	 *
	 * @since 1.0.0
	 *
	 * @param int $max_requests The maximum number of requests per window. Default 60.
	 */
	$max_requests = apply_filters( 'accessSchema_api_rate_limit', 60 );

	// Clean old entries
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table} WHERE window_start < DATE_SUB(NOW(), INTERVAL %d SECOND)",
			$window
		)
	);

	// Check current rate
	$current = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT requests, window_start FROM {$table} 
         WHERE identifier = %s 
         AND window_start > DATE_SUB(NOW(), INTERVAL %d SECOND)",
			$hash,
			$window
		)
	);

	if ( ! $current ) {
		// New window
		$wpdb->insert(
			$table,
			array(
				'identifier' => $hash,
				'requests'   => 1,
			),
			array( '%s', '%d' )
		);
		return true;
	}

	if ( $current->requests >= $max_requests ) {
		return false;
	}

	// Increment counter
	$wpdb->update(
		$table,
		array( 'requests' => $current->requests + 1 ),
		array( 'identifier' => $hash ),
		array( '%d' ),
		array( '%s' )
	);

	return true;
}

/**
 * Resolve a WordPress user from request parameters.
 *
 * Looks up the user by 'id' or 'email' from the provided parameters. Returns
 * null if no user is found or if the user lacks the 'read' capability.
 *
 * @since 1.0.0
 *
 * @param array $params Associative array with optional 'id' (int) or 'email' (string) keys.
 * @return WP_User|null The resolved user object, or null if not found or invalid.
 */
function accessSchema_resolve_user( $params ) {
	if ( ! empty( $params['id'] ) ) {
		$user = get_user_by( 'id', absint( $params['id'] ) );
	} elseif ( ! empty( $params['email'] ) ) {
		$user = get_user_by( 'email', sanitize_email( $params['email'] ) );
	} else {
		return null;
	}

	// Validate user exists and has basic read capability
	if ( $user && user_can( $user->ID, 'read' ) ) {
		return $user;
	}

	return null;
}

/**
 * Handle the REST API request to register role paths.
 *
 * Accepts an array of path segment arrays and registers each one in the role
 * tree within a database transaction. Returns lists of successfully registered
 * and failed paths.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object containing a 'paths' parameter.
 * @return WP_REST_Response|WP_Error Response with 'registered' and 'failed' arrays, or error on transaction failure.
 */
function accessSchema_api_register_roles( $request ) {
	$params = $request->get_json_params();
	$paths  = $params['paths'] ?? array();

	$registered = array();
	$failed     = array();

	global $wpdb;
	$wpdb->query( 'START TRANSACTION' );

	try {
		foreach ( $paths as $path ) {
			if ( ! is_array( $path ) || empty( $path ) ) {
				$failed[] = 'Invalid path format';
				continue;
			}

			$sanitized = array_map( 'sanitize_text_field', $path );
			$result    = accessSchema_register_path( $sanitized );

			if ( $result ) {
				$registered[] = implode( '/', $sanitized );
			} else {
				$failed[] = implode( '/', $sanitized );
			}
		}

		$wpdb->query( 'COMMIT' );

		// Clear cache after bulk registration
		wp_cache_delete( 'all_roles', 'accessSchema' );
	} catch ( Exception $e ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'registration_failed', $e->getMessage(), array( 'status' => 500 ) );
	}

	$response = array( 'registered' => $registered );

	if ( ! empty( $failed ) ) {
		$response['failed'] = $failed;
	}

	return rest_ensure_response( $response );
}

/**
 * Handle the REST API request to get a user's roles.
 *
 * Resolves the user from the request body (by id or email) and returns their
 * assigned role paths. Results are cached for 5 minutes.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object with user identification parameters.
 * @return WP_REST_Response|WP_Error Response with 'email' and 'roles' keys, or error if user not found.
 */
function accessSchema_api_get_roles( $request ) {
	$params = $request->get_json_params();
	$user   = accessSchema_resolve_user( $params );

	if ( ! $user ) {
		return new WP_Error( 'user_not_found', 'User not found by id or email.', array( 'status' => 404 ) );
	}

	// Check cache
	$cache_key = 'api_user_roles_' . $user->ID;
	$cached    = wp_cache_get( $cache_key, 'accessSchema' );

	if ( false !== $cached ) {
		return rest_ensure_response( $cached );
	}

	$roles = accessSchema_get_user_roles( $user->ID );

	$response = array(
		'email' => $user->user_email,
		'roles' => is_array( $roles ) ? $roles : array(),
	);

	wp_cache_set( $cache_key, $response, 'accessSchema', 300 );

	return rest_ensure_response( $response );
}

/**
 * Handle the REST API request to grant a role to a user.
 *
 * Resolves the user, validates the role path and optional expiration date,
 * then assigns the role. Clears the API cache for the affected user.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object with 'role_path' and user identification.
 * @return WP_REST_Response|WP_Error Response with grant result, or error on invalid request.
 */
function accessSchema_api_grant_role( $request ) {
	$params     = $request->get_json_params();
	$user       = accessSchema_resolve_user( $params );
	$role_path  = sanitize_text_field( $params['role_path'] ?? '' );
	$expires_at = isset( $params['expires_at'] ) ? sanitize_text_field( $params['expires_at'] ) : null;

	if ( ! $user || ! $role_path ) {
		return new WP_Error( 'invalid_request', 'Missing user (id/email) or role_path.', array( 'status' => 400 ) );
	}

	// Validate expiration date if provided
	if ( $expires_at ) {
		$expires_timestamp = strtotime( $expires_at );
		if ( false === $expires_timestamp || $expires_timestamp <= time() ) {
			return new WP_Error( 'invalid_expiration', 'Invalid or past expiration date.', array( 'status' => 400 ) );
		}
		$expires_at = date( 'Y-m-d H:i:s', $expires_timestamp );
	}

	$result = accessSchema_add_role( $user->ID, $role_path, 0, $expires_at );

	// Clear cache
	wp_cache_delete( 'api_user_roles_' . $user->ID, 'accessSchema' );

	return rest_ensure_response(
		array(
			'email'      => $user->user_email,
			'granted'    => $result,
			'expires_at' => $expires_at,
		)
	);
}

/**
 * Handle the REST API request to revoke a role from a user.
 *
 * Resolves the user and removes the specified role assignment. Clears the
 * API cache for the affected user.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object with 'role_path' and user identification.
 * @return WP_REST_Response|WP_Error Response with revoke result, or error on invalid request.
 */
function accessSchema_api_revoke_role( $request ) {
	$params    = $request->get_json_params();
	$user      = accessSchema_resolve_user( $params );
	$role_path = sanitize_text_field( $params['role_path'] ?? '' );

	if ( ! $user || ! $role_path ) {
		return new WP_Error( 'invalid_request', 'Missing user (id/email) or role_path.', array( 'status' => 400 ) );
	}

	$result = accessSchema_remove_role( $user->ID, $role_path );

	// Clear cache
	wp_cache_delete( 'api_user_roles_' . $user->ID, 'accessSchema' );

	return rest_ensure_response(
		array(
			'email'   => $user->user_email,
			'revoked' => $result,
		)
	);
}

/**
 * Handle the REST API request to check a user's permission.
 *
 * Resolves the user and checks whether they have access to the specified
 * role path. Supports optional child role inclusion. Results are cached
 * for 5 minutes.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object with 'role_path', optional 'include_children', and user identification.
 * @return WP_REST_Response|WP_Error Response with 'granted' boolean, or error on invalid request.
 */
function accessSchema_api_check_permission( $request ) {
	$params           = $request->get_json_params();
	$user             = accessSchema_resolve_user( $params );
	$role_path        = sanitize_text_field( $params['role_path'] ?? '' );
	$include_children = ! empty( $params['include_children'] );

	if ( ! $user || ! $role_path ) {
		return new WP_Error( 'invalid_request', 'Missing user (id/email) or role_path.', array( 'status' => 400 ) );
	}

	// Use cached permission check
	$cache_key = sprintf( 'api_perm_%d_%s_%d', $user->ID, md5( $role_path ), $include_children ? 1 : 0 );
	$cached    = wp_cache_get( $cache_key, 'accessSchema' );

	if ( false !== $cached ) {
		return rest_ensure_response(
			array(
				'email'   => $user->user_email,
				'granted' => (bool) $cached,
			)
		);
	}

	$has_access = accessSchema_check_permission( $user->ID, $role_path, $include_children, false );

	wp_cache_set( $cache_key, $has_access, 'accessSchema', 300 );

	return rest_ensure_response(
		array(
			'email'   => $user->user_email,
			'granted' => $has_access,
		)
	);
}

/**
 * Handle the REST API request to get all registered roles.
 *
 * Returns both a flat list and the hierarchical tree of all registered roles.
 * The flat list includes id, name, path, depth, and parent_id for each role.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_REST_Response Response with 'total', 'roles' (flat list), and 'hierarchy' (tree) keys.
 */
function accessSchema_api_get_all_roles( $request ) {
	// Get all roles with hierarchy
	$roles = accessSchema_get_all_roles();

	// Flatten for API response
	$response = array(
		'total'     => 0,
		'roles'     => array(),
		'hierarchy' => $roles,
	);

	// Create flat list with full details
	$flat_roles = array();
	accessSchema_flatten_roles( $roles, $flat_roles );

	$response['roles'] = $flat_roles;
	$response['total'] = count( $flat_roles );

	return rest_ensure_response( $response );
}

/**
 * Recursively flatten a hierarchical role tree into a flat array.
 *
 * Walks through each node and its children, appending a simplified
 * representation (id, name, path, depth, parent_id) to the result array.
 *
 * @since 1.0.0
 *
 * @param array $nodes  An array of role node arrays, each with optional 'children' key.
 * @param array $result Reference to the flat result array to append to.
 * @return void
 */
function accessSchema_flatten_roles( $nodes, &$result ) {
	foreach ( $nodes as $node ) {
		$result[] = array(
			'id'        => $node['id'],
			'name'      => $node['name'],
			'path'      => $node['full_path'],
			'depth'     => $node['depth'],
			'parent_id' => $node['parent_id'],
		);

		if ( ! empty( $node['children'] ) ) {
			accessSchema_flatten_roles( $node['children'], $result );
		}
	}
}

// Add CORS headers
add_action(
	'rest_api_init',
	function () {
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter(
			'rest_pre_serve_request',
			function ( $value ) {
				$origin = get_http_origin();
				/**
				 * Filters the list of allowed CORS origins for REST API requests.
				 *
				 * Allows plugins to add or modify the origins permitted to make
				 * cross-origin requests to the Access Schema REST API.
				 *
				 * @since 1.0.0
				 *
				 * @param string[] $allowed_origins List of allowed origin URLs. Default contains the requesting origin.
				 */
				$allowed_origins = apply_filters( 'accessSchema_cors_origins', array( $origin ) );

				if ( in_array( $origin, $allowed_origins, true ) ) {
					header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
				}

				header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
				header( 'Access-Control-Allow-Headers: x-api-key, Content-Type' );
				header( 'Access-Control-Max-Age: 3600' );

				return $value;
			}
		);
	},
	15
);
