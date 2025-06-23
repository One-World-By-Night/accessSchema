<?php
/**
 * File: includes/core/user-roles.php
 * @version 2.0.0
 * Author: greghacke
 */

defined('ABSPATH') || exit;

/**
 * Add a role path to a user with validation.
 */
function accessSchema_add_role($user_id, $role_path, $performed_by = null, $expires_at = null) {
    $role_path = trim($role_path);
    
    // Validate role exists
    if (!accessSchema_role_exists($role_path)) {
        accessSchema_log_event($user_id, 'role_add_invalid', $role_path, array(
            'reason' => 'role_not_registered'
        ), $performed_by, 'ERROR');
        return false;
    }
    
    global $wpdb;
    $roles_table = $wpdb->prefix . 'accessSchema_roles';
    $user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';
    
    // Get role ID
    $role_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$roles_table} WHERE full_path = %s AND is_active = 1",
        $role_path
    ));
    
    if (!$role_id) {
        return false;
    }
    
    // Check if already assigned
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$user_roles_table} WHERE user_id = %d AND role_id = %d",
        $user_id,
        $role_id
    ));
    
    if ($existing) {
        accessSchema_log_event($user_id, 'role_add_skipped', $role_path, array(
            'reason' => 'already_assigned'
        ), $performed_by, 'DEBUG');
        return false;
    }
    
    // Validate assignment
    $current_roles = accessSchema_get_user_roles($user_id);
    if (!accessSchema_validate_role_assignment($user_id, $role_path, $current_roles)) {
        accessSchema_log_event($user_id, 'role_add_blocked', $role_path, array(
            'reason' => 'validation_failed',
            'existing_roles' => $current_roles
        ), $performed_by, 'WARN');
        return false;
    }
    
    // Insert role assignment
    $result = $wpdb->insert(
        $user_roles_table,
        array(
            'user_id' => $user_id,
            'role_id' => $role_id,
            'granted_by' => $performed_by ?: get_current_user_id(),
            'expires_at' => $expires_at
        ),
        array('%d', '%d', '%d', '%s')
    );
    
    if ($result) {
        accessSchema_log_event($user_id, 'role_added', $role_path, array(
            'expires_at' => $expires_at
        ), $performed_by, 'INFO');
        
        // Clear cache
        wp_cache_delete('user_roles_' . $user_id, 'accessSchema');
        wp_cache_delete('user_permissions_' . $user_id, 'accessSchema');
        
        // Trigger action
        do_action('accessSchema_role_added', $user_id, $role_path, $performed_by);
    }
    
    return $result !== false;
}

/**
 * Remove a role path from a user.
 */
function accessSchema_remove_role($user_id, $role_path, $performed_by = null) {
    $role_path = trim($role_path);
    
    global $wpdb;
    $roles_table = $wpdb->prefix . 'accessSchema_roles';
    $user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';
    
    // Get role ID
    $role_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$roles_table} WHERE full_path = %s",
        $role_path
    ));
    
    if (!$role_id) {
        accessSchema_log_event($user_id, 'role_remove_invalid', $role_path, array(
            'reason' => 'role_not_found'
        ), $performed_by, 'ERROR');
        return false;
    }
    
    // Remove assignment
    $result = $wpdb->delete(
        $user_roles_table,
        array(
            'user_id' => $user_id,
            'role_id' => $role_id
        ),
        array('%d', '%d')
    );
    
    if ($result) {
        accessSchema_log_event($user_id, 'role_removed', $role_path, null, $performed_by, 'INFO');
        
        // Clear cache
        wp_cache_delete('user_roles_' . $user_id, 'accessSchema');
        wp_cache_delete('user_permissions_' . $user_id, 'accessSchema');
        
        // Trigger action
        do_action('accessSchema_role_removed', $user_id, $role_path, $performed_by);
    }
    
    return $result !== false;
}

/**
 * Validate whether a role can be assigned to a user.
 */
function accessSchema_validate_role_assignment($user_id, $new_role, $existing_roles) {
    // Check for role conflicts
    $conflicts = apply_filters('accessSchema_role_conflicts', array(), $new_role);
    
    foreach ($conflicts as $conflict_pattern) {
        foreach ($existing_roles as $role) {
            if (fnmatch($conflict_pattern, $role)) {
                return false;
            }
        }
    }
    
    // Check max roles per user
    $max_roles = apply_filters('accessSchema_max_roles_per_user', 50, $user_id);
    if (count($existing_roles) >= $max_roles) {
        return false;
    }
    
    // Allow custom validation
    return apply_filters('accessSchema_validate_role_assignment', true, $user_id, $new_role, $existing_roles);
}

/**
 * Get all roles assigned to a user.
 * NOTE: This now returns ONLY the exact roles assigned, not parent roles.
 */
function accessSchema_get_user_roles($user_id, $include_expired = false, $use_cache = true) {
    $cache_key = 'user_roles_' . $user_id . ($include_expired ? '_all' : '');
    
    if ($use_cache) {
        $cached = wp_cache_get($cache_key, 'accessSchema');
        if ($cached !== false) {
            return $cached;
        }
    }
    
    global $wpdb;
    $roles_table = $wpdb->prefix . 'accessSchema_roles';
    $user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';
    
    $query = "SELECT r.full_path 
              FROM {$user_roles_table} ur
              JOIN {$roles_table} r ON ur.role_id = r.id
              WHERE ur.user_id = %d 
              AND ur.is_active = 1 
              AND r.is_active = 1";
    
    $params = array($user_id);
    
    if (!$include_expired) {
        $query .= " AND (ur.expires_at IS NULL OR ur.expires_at > %s)";
        $params[] = current_time('mysql');
    }
    
    $query .= " ORDER BY r.full_path";
    
    $roles = $wpdb->get_col($wpdb->prepare($query, ...$params));
    
    // Just return the exact roles without parent inheritance
    $roles = array_unique($roles);
    sort($roles);
    
    wp_cache_set($cache_key, $roles, 'accessSchema', 3600);
    
    return $roles;
}

/**
 * Get parent roles for a given role path.
 * This is now a utility function, not automatically included in user roles.
 */
function accessSchema_get_parent_roles($role_path) {
    $parents = array();
    $parts = explode('/', $role_path);
    
    for ($i = count($parts) - 1; $i > 0; $i--) {
        $parent_path = implode('/', array_slice($parts, 0, $i));
        if (accessSchema_role_exists($parent_path)) {
            $parents[] = $parent_path;
        }
    }
    
    return $parents;
}

/**
 * Get all roles including inherited parent roles.
 * Use this when you need the full hierarchy.
 */
function accessSchema_get_user_roles_with_inheritance($user_id, $include_expired = false) {
    $direct_roles = accessSchema_get_user_roles($user_id, $include_expired);
    $all_roles = array();
    
    foreach ($direct_roles as $role) {
        $all_roles[] = $role;
        $all_roles = array_merge($all_roles, accessSchema_get_parent_roles($role));
    }
    
    $all_roles = array_unique($all_roles);
    sort($all_roles);
    
    return $all_roles;
}

/**
 * Bulk update user roles.
 */
function accessSchema_save_user_roles($user_id, $new_roles, $performed_by = null) {
    $existing_roles = accessSchema_get_user_roles($user_id, true, false);
    
    $to_add = array_diff($new_roles, $existing_roles);
    $to_remove = array_diff($existing_roles, $new_roles);
    
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    
    try {
        foreach ($to_add as $role) {
            if (!accessSchema_add_role($user_id, $role, $performed_by)) {
                throw new Exception("Failed to add role: $role");
            }
        }
        
        foreach ($to_remove as $role) {
            if (!accessSchema_remove_role($user_id, $role, $performed_by)) {
                throw new Exception("Failed to remove role: $role");
            }
        }
        
        $wpdb->query('COMMIT');
        
        // Log bulk update
        accessSchema_log_event($user_id, 'roles_bulk_updated', '', array(
            'added' => $to_add,
            'removed' => $to_remove
        ), $performed_by, 'INFO');
        
        return true;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('accessSchema: Bulk role update failed - ' . $e->getMessage());
        return false;
    }
}

/**
 * Check and remove expired roles.
 */
function accessSchema_cleanup_expired_roles() {
    global $wpdb;
    $user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';
    
    // Get expired assignments
    $expired = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, role_id FROM {$user_roles_table} 
         WHERE expires_at IS NOT NULL 
         AND expires_at < %s 
         AND is_active = 1
         LIMIT 100",
        current_time('mysql')
    ), ARRAY_A);
    
    if (empty($expired)) {
        return 0;
    }
    
    $count = 0;
    foreach ($expired as $assignment) {
        $result = $wpdb->update(
            $user_roles_table,
            array('is_active' => 0),
            array(
                'user_id' => $assignment['user_id'],
                'role_id' => $assignment['role_id']
            ),
            array('%d'),
            array('%d', '%d')
        );
        
        if ($result) {
            $count++;
            // Clear user cache
            wp_cache_delete('user_roles_' . $assignment['user_id'], 'accessSchema');
            wp_cache_delete('user_permissions_' . $assignment['user_id'], 'accessSchema');
        }
    }
    
    if ($count > 0) {
        accessSchema_log_event(0, 'expired_roles_cleaned', '', array(
            'count' => $count
        ), 0, 'INFO');
    }
    
    return $count;
}

// Schedule cleanup
add_action('accessSchema_daily_cleanup', 'accessSchema_cleanup_expired_roles');

/**
 * Get users by role.
 */
function accessSchema_get_users_by_role($role_path, $args = array()) {
    global $wpdb;
    $roles_table = $wpdb->prefix . 'accessSchema_roles';
    $user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';
    
    $defaults = array(
        'number' => -1,
        'offset' => 0,
        'orderby' => 'display_name',
        'order' => 'ASC',
        'include_children' => false
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Get role ID(s)
    $role_ids = array();
    
    $role_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$roles_table} WHERE full_path = %s AND is_active = 1",
        $role_path
    ));
    
    if (!$role_id) {
        return array();
    }
    
    $role_ids[] = $role_id;
    
    if ($args['include_children']) {
        $children = accessSchema_get_role_descendants($role_id);
        $role_ids = array_merge($role_ids, $children);
    }
    
    // Build query
    $placeholders = implode(',', array_fill(0, count($role_ids), '%d'));
    
    $query = "SELECT DISTINCT u.* 
              FROM {$wpdb->users} u
              JOIN {$user_roles_table} ur ON u.ID = ur.user_id
              WHERE ur.role_id IN ($placeholders)
              AND ur.is_active = 1
              AND (ur.expires_at IS NULL OR ur.expires_at > %s)";
    
    $query_args = array_merge($role_ids, array(current_time('mysql')));
    
    // Add ordering
    $allowed_orderby = array('ID', 'display_name', 'user_login', 'user_registered');
    if (in_array($args['orderby'], $allowed_orderby)) {
        $query .= " ORDER BY u.{$args['orderby']} {$args['order']}";
    }
    
    // Add limit
    if ($args['number'] > 0) {
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['number'], $args['offset']);
    }
    
    return $wpdb->get_results($wpdb->prepare($query, ...$query_args));
}