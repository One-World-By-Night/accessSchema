<?php
/**
 * File: includes/core/role-tree.php
 *
 * @version 2.0.4
 * Author: greghacke
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register roles hierarchically under a group and subkey.
 *
 * Creates a three-level hierarchy: group -> subkey -> roles. Uses a database
 * transaction to ensure all roles are registered atomically. Clears the role
 * tree cache upon success.
 *
 * @since 1.0.0
 *
 * @param string   $group The top-level group name (e.g., 'organization').
 * @param string   $sub   The subkey name under the group (e.g., 'council').
 * @param string[] $roles An array of role names to register under the subkey.
 * @return bool True on success, false on failure.
 */
function accessSchema_register_roles( $group, $sub, $roles ) {
	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$group = sanitize_text_field( $group );
	$sub   = sanitize_text_field( $sub );
	$roles = array_map( 'sanitize_text_field', $roles );

	// Use transaction for batch operation
	$wpdb->query( 'START TRANSACTION' );

	try {
		// Insert or get group-level ID
		$group_id = accessSchema_get_or_create_role_node( $group );

		// Insert or get subkey under group
		$sub_id = accessSchema_get_or_create_role_node( $sub, $group_id );

		// Insert roles under sub
		foreach ( $roles as $role ) {
			accessSchema_get_or_create_role_node( $role, $sub_id );
		}

		$wpdb->query( 'COMMIT' );

		// Clear cache
		wp_cache_delete( 'role_tree', 'accessSchema' );

	} catch ( Exception $e ) {
		$wpdb->query( 'ROLLBACK' );
		error_log( 'accessSchema: Failed to register roles - ' . $e->getMessage() );
		return false;
	}

	return true;
}

/**
 * Register a complete role path from an array of segments.
 *
 * Walks through each segment of the path, creating any missing nodes in the
 * database. Uses a static cache to minimize queries for repeated parent lookups.
 * Clears the role tree cache upon successful registration.
 *
 * @since 1.0.0
 *
 * @param string[] $segments An ordered array of path segment names (e.g., ['org', 'council', 'admin']).
 * @return int|false The ID of the final (deepest) node on success, or false on failure.
 */
function accessSchema_register_path( array $segments ) {
	if ( empty( $segments ) ) {
		return false;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$parent_id        = null;
	$accumulated_path = '';
	$depth            = 0;

	// Cache existing paths to reduce queries
	static $path_cache = array();

	foreach ( $segments as $i => $name ) {
		$name = trim( $name );
		if ( '' === $name ) {
			continue;
		}

		$slug             = sanitize_title( $name );
		$accumulated_path = '' === $accumulated_path ? $slug : $accumulated_path . '/' . $slug;

		// Check cache first
		$cache_key = $parent_id . ':' . $slug;
		if ( isset( $path_cache[ $cache_key ] ) ) {
			$parent_id = $path_cache[ $cache_key ];
			++$depth;
			continue;
		}

		// Check for existing node
		$existing = accessSchema_db_operation(
			function () use ( $wpdb, $table, $slug, $parent_id ) {
				if ( is_null( $parent_id ) ) {
					return $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$table} WHERE slug = %s AND parent_id IS NULL",
							$slug
						)
					);
				} else {
					return $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$table} WHERE slug = %s AND parent_id = %d",
							$slug,
							$parent_id
						)
					);
				}
			}
		);

		if ( $existing ) {
			$parent_id                = (int) $existing;
			$path_cache[ $cache_key ] = $parent_id;
			++$depth;
			continue;
		}

		// Insert new node
		$result = $wpdb->insert(
			$table,
			array(
				'parent_id'  => $parent_id,
				'name'       => $name,
				'slug'       => $slug,
				'full_path'  => $accumulated_path,
				'depth'      => $depth,
				'created_by' => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		$parent_id                = $wpdb->insert_id;
		$path_cache[ $cache_key ] = $parent_id;
		++$depth;
	}

	// Clear cache
	wp_cache_delete( 'role_tree', 'accessSchema' );

	return $parent_id;
}

/**
 * Insert or retrieve a role node by name and optional parent.
 *
 * Looks up an existing role node by slug and parent ID. If not found, creates
 * a new node with the computed full path and depth. Enforces the maximum
 * tree depth defined by the `accessSchema_max_depth` option.
 *
 * @since 1.0.0
 *
 * @param string   $name      The display name for the role node.
 * @param int|null $parent_id Optional. The parent node ID. Default null (root level).
 * @return int|false The role node ID on success, or false on failure or max depth exceeded.
 */
function accessSchema_get_or_create_role_node( $name, $parent_id = null ) {
	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$name = sanitize_text_field( $name );
	$slug = sanitize_title( $name );

	// Check for existing
	$existing_id = accessSchema_db_operation(
		function () use ( $wpdb, $table, $slug, $parent_id ) {
			if ( is_null( $parent_id ) ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE slug = %s AND parent_id IS NULL",
						$slug
					)
				);
			} else {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE slug = %s AND parent_id = %d",
						$slug,
						$parent_id
					)
				);
			}
		}
	);

	if ( $existing_id ) {
		return (int) $existing_id;
	}

	// Compute full path and depth
	$full_path = $slug;
	$depth     = 0;

	if ( ! is_null( $parent_id ) ) {
		$parent_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT full_path, depth FROM {$table} WHERE id = %d",
				$parent_id
			),
			ARRAY_A
		);

		if ( $parent_data ) {
			$full_path = $parent_data['full_path'] . '/' . $slug;
			$depth     = (int) $parent_data['depth'] + 1;
		}
	}

	// Check max depth
	$max_depth = (int) get_option( 'accessSchema_max_depth', 10 );
	if ( $depth > $max_depth ) {
		return false;
	}

	$result = $wpdb->insert(
		$table,
		array(
			'name'       => $name,
			'slug'       => $slug,
			'parent_id'  => $parent_id,
			'full_path'  => $full_path,
			'depth'      => $depth,
			'created_by' => get_current_user_id(),
		),
		array( '%s', '%s', '%d', '%s', '%d', '%d' )
	);

	if ( false === $result ) {
		return false;
	}

	// Clear cache
	wp_cache_delete( 'role_tree', 'accessSchema' );

	return (int) $wpdb->insert_id;
}

/**
 * Check if a role path exists in the registered roles.
 *
 * Performs a direct full_path lookup first, then falls back to segment-by-segment
 * validation for legacy support. Results are cached in the object cache.
 *
 * @since 1.0.0
 *
 * @param string $role_path The full role path to check (e.g., 'org/council/admin').
 * @return bool True if the role path exists and is active, false otherwise.
 */
function accessSchema_role_exists( $role_path ) {
	if ( empty( $role_path ) ) {
		return false;
	}

	// Check cache first
	$cache_key = 'role_exists_' . md5( $role_path );
	$cached    = wp_cache_get( $cache_key, 'accessSchema' );

	if ( false !== $cached ) {
		return (bool) $cached;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	// Direct path lookup first (most efficient)
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE full_path = %s AND is_active = 1 LIMIT 1",
			sanitize_text_field( $role_path )
		)
	);

	if ( $exists ) {
		wp_cache_set( $cache_key, true, 'accessSchema', 3600 );
		return true;
	}

	// Fallback to segment checking for legacy support
	$parts = explode( '/', trim( $role_path ) );
	if ( count( $parts ) < 1 ) {
		wp_cache_set( $cache_key, false, 'accessSchema', 3600 );
		return false;
	}

	$parent_id = null;
	foreach ( $parts as $part ) {
		$slug = sanitize_title( $part );

		if ( is_null( $parent_id ) ) {
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE slug = %s AND parent_id IS NULL AND is_active = 1",
					$slug
				)
			);
		} else {
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE slug = %s AND parent_id = %d AND is_active = 1",
					$slug,
					$parent_id
				)
			);
		}

		if ( ! $id ) {
			wp_cache_set( $cache_key, false, 'accessSchema', 3600 );
			return false;
		}

		$parent_id = $id;
	}

	wp_cache_set( $cache_key, true, 'accessSchema', 3600 );
	return true;
}

/**
 * Get the complete role tree for display.
 *
 * Recursively builds a nested array of role nodes starting from the given
 * parent ID. Results are cached in the object cache for 5 minutes.
 *
 * @since 1.0.0
 *
 * @param int|null $parent_id Optional. The parent node ID to start from. Default null (root).
 * @param int|null $max_depth Optional. Maximum depth to retrieve. Default null (uses option value).
 * @return array An array of role node arrays, each containing a 'children' key with nested nodes.
 */
function accessSchema_get_role_tree( $parent_id = null, $max_depth = null ) {
	$cache_key = 'role_tree_' . ( $parent_id ? $parent_id : '0' );
	$cached    = wp_cache_get( $cache_key, 'accessSchema' );

	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$max_depth = $max_depth ? $max_depth : (int) get_option( 'accessSchema_max_depth', 10 );

	$query  = "SELECT * FROM {$table} WHERE is_active = 1";
	$params = array();

	if ( is_null( $parent_id ) ) {
		$query .= ' AND parent_id IS NULL';
	} else {
		$query   .= ' AND parent_id = %d';
		$params[] = $parent_id;
	}

	if ( $max_depth > 0 ) {
		$query   .= ' AND depth <= %d';
		$params[] = $max_depth;
	}

	$query .= ' ORDER BY name ASC';

	if ( ! empty( $params ) ) {
		$nodes = $wpdb->get_results( $wpdb->prepare( $query, ...$params ), ARRAY_A );
	} else {
		$nodes = $wpdb->get_results( $query, ARRAY_A );
	}

	// Build tree structure
	$tree = array();
	foreach ( $nodes as $node ) {
		$node['children'] = accessSchema_get_role_tree( $node['id'], $max_depth - 1 );
		$tree[]           = $node;
	}

	wp_cache_set( $cache_key, $tree, 'accessSchema', 300 ); // 5 minutes

	return $tree;
}

/**
 * Delete a role and optionally its children.
 *
 * When `$delete_children` is true, performs a hard delete of the role and all
 * descendants, including their user assignments. When false, performs a soft
 * delete by marking the role as inactive. Uses a database transaction.
 *
 * @since 1.0.0
 *
 * @param int  $role_id         The ID of the role to delete.
 * @param bool $delete_children Optional. Whether to also delete child roles. Default false.
 * @return bool True on success, false on failure.
 */
function accessSchema_delete_role( $role_id, $delete_children = false ) {
	global $wpdb;
	$roles_table      = $wpdb->prefix . 'accessSchema_roles';
	$user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';

	$wpdb->query( 'START TRANSACTION' );

	try {
		if ( $delete_children ) {
			// Get all descendant IDs
			$descendants = accessSchema_get_role_descendants( $role_id );
			$all_ids     = array_merge( array( $role_id ), $descendants );

			// Remove user assignments
			$placeholders = implode( ',', array_fill( 0, count( $all_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$user_roles_table} WHERE role_id IN ($placeholders)",
					...$all_ids
				)
			);

			// Delete roles
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$roles_table} WHERE id IN ($placeholders)",
					...$all_ids
				)
			);
		} else {
			// Soft delete - just mark as inactive
			$wpdb->update(
				$roles_table,
				array( 'is_active' => 0 ),
				array( 'id' => $role_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$wpdb->query( 'COMMIT' );

		// Clear cache
		wp_cache_flush_group( 'accessSchema' );

		return true;

	} catch ( Exception $e ) {
		$wpdb->query( 'ROLLBACK' );
		error_log( 'accessSchema: Failed to delete role - ' . $e->getMessage() );
		return false;
	}
}

/**
 * Get all descendant role IDs for a given role.
 *
 * Performs a breadth-first traversal of the role tree to collect all
 * descendant role IDs.
 *
 * @since 1.0.0
 *
 * @param int $role_id The parent role ID to find descendants for.
 * @return int[] An array of descendant role IDs.
 */
function accessSchema_get_role_descendants( $role_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$descendants = array();
	$queue       = array( $role_id );

	while ( ! empty( $queue ) ) {
		$current_id = array_shift( $queue );

		$children = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE parent_id = %d",
				$current_id
			)
		);

		if ( ! empty( $children ) ) {
			$descendants = array_merge( $descendants, $children );
			$queue       = array_merge( $queue, $children );
		}
	}

	return $descendants;
}
