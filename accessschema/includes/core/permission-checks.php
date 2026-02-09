<?php

/**
 * File: includes/core/permission-checks.php
 *
 * @version 2.0.3
 * Author: greghacke
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check if a user has a given role or any of its children.
 *
 * Performs a permission check for a user against a target role path. Supports
 * direct matching, child role matching, parent inheritance, and wildcard patterns.
 * Results are cached for performance.
 *
 * @since 1.0.0
 *
 * @param int    $user_id          The WordPress user ID to check.
 * @param string $target_path      The role path to check against.
 * @param bool   $include_children Optional. Whether to include child roles in the check. Default false.
 * @param bool   $log              Optional. Whether to log the permission check. Default true.
 * @param array  $context          Optional. Additional context for logging. Default empty array.
 * @param bool   $allow_wildcards  Optional. Whether to allow wildcard patterns in the target path. Default false.
 * @return bool True if the user has the specified permission, false otherwise.
 */
function accessSchema_check_permission( $user_id, $target_path, $include_children = false, $log = true, $context = array(), $allow_wildcards = false ) {
	$target_path = trim( $target_path );

	// Validate inputs
	if ( empty( $user_id ) || empty( $target_path ) ) {
		return false;
	}

	// Check cache first
	$cache_key = sprintf( 'perm_%d_%s_%d_%d', $user_id, md5( $target_path ), $include_children ? 1 : 0, $allow_wildcards ? 1 : 0 );
	$cached    = wp_cache_get( $cache_key, 'accessSchema' );

	if ( false !== $cached && ! $log ) {
		return (bool) $cached;
	}

	// Get user roles with caching
	$roles = accessSchema_get_user_roles( $user_id );

	if ( empty( $roles ) ) {
		if ( $log ) {
			accessSchema_log_permission_check( $user_id, $target_path, false, 'no_roles_assigned', $context );
		}
		wp_cache_set( $cache_key, false, 'accessSchema', 300 );
		return false;
	}

	$matched    = false;
	$match_type = '';

	// Wildcard pattern check
	if ( $allow_wildcards && ( false !== strpos( $target_path, '*' ) ) ) {
		$regex = accessSchema_pattern_to_regex( $target_path );
		foreach ( $roles as $role ) {
			if ( preg_match( $regex, $role ) ) {
				$matched    = true;
				$match_type = 'wildcard';
				break;
			}
		}
	} else {
		// Validate role exists
		if ( ! accessSchema_role_exists_cached( $target_path ) ) {
			if ( $log ) {
				accessSchema_log_permission_check( $user_id, $target_path, false, 'role_not_registered', $context );
			}
			wp_cache_set( $cache_key, false, 'accessSchema', 300 );
			return false;
		}

		// Direct match
		if ( in_array( $target_path, $roles, true ) ) {
			$matched    = true;
			$match_type = 'exact';
		}

		// Children check
		if ( ! $matched && $include_children ) {
			$target_prefix = $target_path . '/';
			foreach ( $roles as $role ) {
				if ( 0 === strpos( $role, $target_prefix ) ) {
					$matched    = true;
					$match_type = 'child';
					break;
				}
			}
		}

		// Parent inheritance check
		if ( ! $matched ) {
			$parts = explode( '/', $target_path );
			while ( count( $parts ) > 1 ) {
				array_pop( $parts );
				$parent_path = implode( '/', $parts );
				if ( in_array( $parent_path, $roles, true ) ) {
					/**
					 * Filters whether a parent role grants access to a child role path.
					 *
					 * Allows plugins to control whether having a parent role in the hierarchy
					 * automatically grants access to child role paths.
					 *
					 * @since 1.0.0
					 *
					 * @param bool   $grants_access Whether the parent role grants access. Default false.
					 * @param string $parent_path   The parent role path the user holds.
					 * @param string $target_path   The target child role path being checked.
					 */
					if ( apply_filters( 'accessSchema_parent_grants_access', false, $parent_path, $target_path ) ) {
						$matched    = true;
						$match_type = 'inherited';
						break;
					}
				}
			}
		}
	}

	// Cache result
	wp_cache_set( $cache_key, $matched, 'accessSchema', 300 );

	// Log if requested
	if ( $log ) {
		$context['match_type'] = $match_type;
		accessSchema_log_permission_check( $user_id, $target_path, $matched, $matched ? 'access_granted' : 'access_denied', $context );
	}

	return $matched;
}

/**
 * Log a permission check with enhanced context.
 *
 * Records details about a permission check including request URI, HTTP method,
 * client IP, and user agent for audit trail purposes.
 *
 * @since 1.0.0
 *
 * @param int    $user_id     The WordPress user ID that was checked.
 * @param string $target_path The role path that was checked.
 * @param bool   $result      Whether access was granted.
 * @param string $reason      The reason for the result (e.g., 'access_granted', 'no_roles_assigned').
 * @param array  $context     Optional. Additional context data for the log entry. Default empty array.
 * @return void
 */
function accessSchema_log_permission_check( $user_id, $target_path, $result, $reason, $context = array() ) {
	$default_context = array(
		'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
		'http_method' => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
		'ip'          => accessSchema_get_client_ip(),
		'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
	);

	$context           = array_merge( $default_context, $context );
	$context['reason'] = $reason;

	accessSchema_log_event(
		$user_id,
		$result ? 'access_granted' : 'access_denied',
		$target_path,
		$context,
		null,
		$result ? 'INFO' : 'WARN'
	);
}

/**
 * Check if a role exists, with object caching.
 *
 * Wraps `accessSchema_role_exists()` with a cached operation layer to minimize
 * database queries for repeated existence checks.
 *
 * @since 1.0.0
 *
 * @param string $role_path The full role path to check for existence.
 * @return bool True if the role exists, false otherwise.
 */
function accessSchema_role_exists_cached( $role_path ) {
	return accessSchema_cached_operation(
		'role_exists_' . md5( $role_path ),
		function () use ( $role_path ) {
			return accessSchema_role_exists( $role_path );
		},
		3600
	);
}

/**
 * Check if a user can access a role path.
 *
 * Convenience wrapper around `accessSchema_check_permission()` that performs a
 * silent (non-logged) check with wildcard support enabled.
 *
 * @since 1.0.0
 *
 * @param int    $user_id The WordPress user ID to check.
 * @param string $pattern The role path or wildcard pattern to check against.
 * @return bool True if the user has access, false otherwise.
 */
function accessSchema_user_can( $user_id, $pattern ) {
	return accessSchema_check_permission( $user_id, $pattern, false, false, array(), true );
}

/**
 * Check if the current logged-in user can access a role path.
 *
 * Convenience wrapper that retrieves the current user ID and delegates
 * to `accessSchema_user_can()`. Returns false if no user is logged in.
 *
 * @since 1.0.0
 *
 * @param string $pattern The role path or wildcard pattern to check against.
 * @return bool True if the current user has access, false otherwise.
 */
function accessSchema_current_user_can( $pattern ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}

	return accessSchema_user_can( $user_id, $pattern );
}

/**
 * Perform a batch permission check for multiple paths at once.
 *
 * Checks multiple permission paths for a single user in an optimized manner,
 * leveraging the object cache to avoid redundant queries. Results are logged
 * as a single batch event at the DEBUG level.
 *
 * @since 1.0.0
 *
 * @param int   $user_id     The WordPress user ID to check.
 * @param array $permissions An array of permission paths (strings) or associative arrays
 *                           with keys 'path', 'include_children', and 'allow_wildcards'.
 * @return array Associative array of role paths to boolean results.
 */
function accessSchema_check_permissions_batch( $user_id, array $permissions ) {
	$results = array();
	$roles   = accessSchema_get_user_roles( $user_id );

	foreach ( $permissions as $permission ) {
		$target_path      = is_array( $permission ) ? $permission['path'] : $permission;
		$include_children = is_array( $permission ) ? ! empty( $permission['include_children'] ) : false;
		$allow_wildcards  = is_array( $permission ) ? ! empty( $permission['allow_wildcards'] ) : false;

		$cache_key = sprintf( 'perm_%d_%s_%d_%d', $user_id, md5( $target_path ), $include_children ? 1 : 0, $allow_wildcards ? 1 : 0 );
		$cached    = wp_cache_get( $cache_key, 'accessSchema' );

		if ( false !== $cached ) {
			$results[ $target_path ] = (bool) $cached;
			continue;
		}

		// Perform check without logging for batch
		$result                  = accessSchema_check_permission( $user_id, $target_path, $include_children, false, array(), $allow_wildcards );
		$results[ $target_path ] = $result;
	}

	// Log batch check
	accessSchema_log_event(
		$user_id,
		'batch_permission_check',
		'',
		array(
			'permissions' => array_keys( $results ),
			'granted'     => array_keys( array_filter( $results ) ),
		),
		null,
		'DEBUG'
	);

	return $results;
}

/**
 * Check if a user matches a wildcard role pattern.
 *
 * Convenience wrapper around `accessSchema_check_permission()` with wildcard
 * support enabled and logging disabled.
 *
 * @since 1.0.0
 *
 * @param int    $user_id The WordPress user ID to check.
 * @param string $pattern The wildcard role pattern to match against.
 * @return bool True if the user matches the pattern, false otherwise.
 */
function accessSchema_user_matches_role_pattern( $user_id, $pattern ) {
	return accessSchema_check_permission( $user_id, $pattern, false, false, array(), true );
}

/**
 * Check if a user matches ANY of the given role patterns.
 *
 * Iterates through the patterns and returns true as soon as any match is found.
 * Results are cached for performance.
 *
 * @since 1.0.0
 *
 * @param int      $user_id  The WordPress user ID to check.
 * @param string[] $patterns An array of role paths or wildcard patterns.
 * @return bool True if the user matches at least one pattern, false otherwise.
 */
function accessSchema_user_matches_any( $user_id, array $patterns ) {
	// Check cache for common pattern sets
	$cache_key = 'matches_any_' . $user_id . '_' . md5( serialize( $patterns ) );
	$cached    = wp_cache_get( $cache_key, 'accessSchema' );

	if ( false !== $cached ) {
		return (bool) $cached;
	}

	foreach ( $patterns as $pattern ) {
		if ( accessSchema_user_matches_role_pattern( $user_id, $pattern ) ) {
			wp_cache_set( $cache_key, true, 'accessSchema', 300 );
			return true;
		}
	}

	wp_cache_set( $cache_key, false, 'accessSchema', 300 );
	return false;
}

/**
 * Check if a user matches ALL of the given role patterns.
 *
 * Iterates through the patterns and returns false as soon as any pattern
 * does not match.
 *
 * @since 1.0.0
 *
 * @param int      $user_id  The WordPress user ID to check.
 * @param string[] $patterns An array of role paths or wildcard patterns.
 * @return bool True if the user matches every pattern, false otherwise.
 */
function accessSchema_user_matches_all( $user_id, array $patterns ) {
	foreach ( $patterns as $pattern ) {
		if ( ! accessSchema_user_matches_role_pattern( $user_id, $pattern ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Convert a wildcard path pattern to a regular expression.
 *
 * Supports `*` for matching a single path segment and `**` for matching
 * any number of segments. Results are cached in a static variable.
 *
 * @since 1.0.0
 *
 * @param string $pattern The wildcard pattern (e.g., 'org/* /admin' or 'org/**').
 * @return string The compiled regular expression string.
 */
function accessSchema_pattern_to_regex( $pattern ) {
	static $regex_cache = array();

	if ( isset( $regex_cache[ $pattern ] ) ) {
		return $regex_cache[ $pattern ];
	}

	// Escape regex characters except * and **
	$escaped = preg_quote( $pattern, '#' );

	// Replace wildcards
	$regex = strtr(
		$escaped,
		array(
			'\*\*' => '.*',        // ** matches any number of segments
			'\*'   => '[^/]+',        // * matches single segment
		)
	);

	// Ensure full path match
	$regex = "#^{$regex}$#i";

	// Cache regex (limit cache size)
	if ( count( $regex_cache ) > 100 ) {
		$regex_cache = array_slice( $regex_cache, -50, null, true );
	}
	$regex_cache[ $pattern ] = $regex;

	return $regex;
}

/**
 * Get all permissions for a user.
 *
 * Retrieves the full set of roles, capabilities, and restrictions for a user.
 * Results are cached in the database-backed permissions cache table.
 *
 * @since 1.0.0
 *
 * @param int $user_id The WordPress user ID.
 * @return array {
 *     Associative array of user permissions.
 *
 *     @type string[] $roles        List of role paths assigned to the user.
 *     @type string[] $capabilities List of capabilities derived from assigned roles.
 *     @type array    $restrictions List of restrictions applied to the user.
 * }
 */
function accessSchema_get_user_permissions( $user_id ) {
	global $wpdb;
	$cache_table = $wpdb->prefix . 'accessSchema_permissions_cache';

	// Check database cache first
	$cached = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT permissions FROM {$cache_table} 
         WHERE user_id = %d 
         AND calculated_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
			$user_id,
			get_option( 'accessSchema_cache_ttl', 3600 )
		)
	);

	if ( $cached ) {
		return json_decode( $cached, true );
	}

	// Calculate permissions
	$roles       = accessSchema_get_user_roles( $user_id );
	$permissions = array(
		'roles'        => $roles,
		'capabilities' => array(),
		'restrictions' => array(),
	);

	// Get capabilities from roles
	foreach ( $roles as $role_path ) {
		/**
		 * Filters the capabilities associated with a specific role path.
		 *
		 * Allows plugins to define or modify the capabilities that a given
		 * role path provides to users who hold that role.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $capabilities List of capability strings for this role. Default empty array.
		 * @param string   $role_path    The full role path being queried.
		 */
		$role_caps                   = apply_filters( 'accessSchema_role_capabilities', array(), $role_path );
		$permissions['capabilities'] = array_merge( $permissions['capabilities'], $role_caps );
	}

	$permissions['capabilities'] = array_unique( $permissions['capabilities'] );

	/**
	 * Filters the restrictions applied to a user based on their roles.
	 *
	 * Allows plugins to define or modify restrictions that limit what a user
	 * can do, even if they hold certain roles.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $restrictions List of restriction definitions. Default empty array.
	 * @param int      $user_id     The WordPress user ID.
	 * @param string[] $roles       The user's assigned role paths.
	 */
	$permissions['restrictions'] = apply_filters( 'accessSchema_user_restrictions', array(), $user_id, $roles );

	// Cache in database
	$wpdb->replace(
		$cache_table,
		array(
			'user_id'       => $user_id,
			'permissions'   => wp_json_encode( $permissions ),
			'calculated_at' => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s' )
	);

	return $permissions;
}

/**
 * Check if a user has a specific capability.
 *
 * Retrieves the user's full permissions and checks whether the given
 * capability is present in the capabilities list.
 *
 * @since 1.0.0
 *
 * @param int    $user_id    The WordPress user ID.
 * @param string $capability The capability identifier to check for.
 * @return bool True if the user has the capability, false otherwise.
 */
function accessSchema_user_has_cap( $user_id, $capability ) {
	$permissions = accessSchema_get_user_permissions( $user_id );
	return in_array( $capability, $permissions['capabilities'], true );
}
