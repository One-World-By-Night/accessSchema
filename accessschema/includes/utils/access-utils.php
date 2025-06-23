<?php
/**
 * File: includes/utils/access-utils.php
 * @version 2.0.0
 * Author: greghacke
 */

defined('ABSPATH') || exit;

/**
 * Check if current user has access via patterns
 */
function accessSchema_access_granted($patterns) {
    if (!is_user_logged_in()) {
        return apply_filters('accessSchema_access_granted', false, $patterns, 0);
    }
    
    $user_id = get_current_user_id();
    
    // Normalize to array
    if (is_string($patterns)) {
        $patterns = array_map('trim', explode(',', $patterns));
    }
    
    if (!is_array($patterns) || empty($patterns)) {
        return apply_filters('accessSchema_access_granted', false, $patterns, $user_id);
    }
    
    // Cache check
    $cache_key = 'access_granted_' . $user_id . '_' . md5(serialize($patterns));
    $cached = wp_cache_get($cache_key, 'accessSchema');
    
    if ($cached !== false) {
        return (bool) $cached;
    }
    
    $result = accessSchema_user_matches_any($user_id, $patterns);
    
    wp_cache_set($cache_key, $result, 'accessSchema', 300);
    
    return apply_filters('accessSchema_access_granted', $result, $patterns, $user_id);
}

/**
 * Inverse of access_granted
 */
function accessSchema_access_denied($patterns) {
    return !accessSchema_access_granted($patterns);
}

/**
 * Check if user can manage roles
 */
function accessSchema_can_manage_roles($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    return user_can($user_id, 'manage_access_schema');
}

/**
 * Check if user can assign roles
 */
function accessSchema_can_assign_roles($user_id = null, $target_user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    
    if (user_can($user_id, 'manage_access_schema')) {
        return true;
    }
    
    if ($target_user_id && user_can($user_id, 'edit_user', $target_user_id)) {
        return user_can($user_id, 'assign_access_roles');
    }
    
    return false;
}

/**
 * Get role hierarchy for a user
 */
function accessSchema_get_user_role_hierarchy($user_id) {
    $roles = accessSchema_get_user_roles($user_id);
    $hierarchy = array();
    
    foreach ($roles as $role) {
        $parts = explode('/', $role);
        $current = &$hierarchy;
        
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = array();
            }
            $current = &$current[$part];
        }
    }
    
    return $hierarchy;
}

/**
 * Check role conflicts
 */
function accessSchema_check_role_conflicts($role_path, $existing_roles) {
    $conflicts = apply_filters('accessSchema_role_conflicts', array(
        'Chronicles/*/CM' => array('Chronicles/*/CM'),
        'Admin/*' => array('User/*')
    ));
    
    foreach ($conflicts as $pattern => $conflicting_patterns) {
        if (fnmatch($pattern, $role_path)) {
            foreach ($conflicting_patterns as $conflict_pattern) {
                foreach ($existing_roles as $existing) {
                    if (fnmatch($conflict_pattern, $existing)) {
                        return $existing;
                    }
                }
            }
        }
    }
    
    return false;
}

/**
 * Map roles to WordPress capabilities
 */
function accessSchema_get_role_capabilities($role_path) {
    $capabilities = array();
    
    // Base capabilities for all roles
    $capabilities[] = 'read';
    
    // Map based on role patterns
    $mappings = apply_filters('accessSchema_capability_mappings', array(
        'Admin/*' => array('manage_options', 'edit_users'),
        'Editor/*' => array('edit_posts', 'edit_others_posts', 'publish_posts'),
        'Moderator/*' => array('moderate_comments', 'edit_posts'),
        '*/CM' => array('edit_others_posts'),
        '*/HST' => array('edit_posts', 'upload_files')
    ));
    
    foreach ($mappings as $pattern => $caps) {
        if (fnmatch($pattern, $role_path)) {
            $capabilities = array_merge($capabilities, $caps);
        }
    }
    
    return array_unique($capabilities);
}

/**
 * Sync user capabilities based on accessSchema roles
 */
function accessSchema_sync_user_capabilities($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    $roles = accessSchema_get_user_roles($user_id);
    $all_caps = array();
    
    foreach ($roles as $role) {
        $caps = accessSchema_get_role_capabilities($role);
        $all_caps = array_merge($all_caps, $caps);
    }
    
    $all_caps = array_unique($all_caps);
    
    // Update user capabilities
    foreach ($all_caps as $cap) {
        $user->add_cap($cap);
    }
    
    return true;
}

/**
 * Get users who can access a specific role
 */
function accessSchema_get_users_with_access($role_path, $include_children = false) {
    global $wpdb;
    
    $cache_key = 'users_with_access_' . md5($role_path . '_' . ($include_children ? '1' : '0'));
    $cached = wp_cache_get($cache_key, 'accessSchema');
    
    if ($cached !== false) {
        return $cached;
    }
    
    $users = accessSchema_get_users_by_role($role_path, array(
        'include_children' => $include_children
    ));
    
    $user_ids = wp_list_pluck($users, 'ID');
    
    wp_cache_set($cache_key, $user_ids, 'accessSchema', 3600);
    
    return $user_ids;
}

/**
 * Bulk check permissions
 */
function accessSchema_bulk_check_permissions($user_ids, $role_path) {
    $results = array();
    
    foreach ($user_ids as $user_id) {
        $results[$user_id] = accessSchema_user_can($user_id, $role_path);
    }
    
    return $results;
}

/**
 * Get role statistics
 */
function accessSchema_get_role_stats() {
    global $wpdb;
    
    $cache_key = 'role_stats';
    $cached = wp_cache_get($cache_key, 'accessSchema');
    
    if ($cached !== false) {
        return $cached;
    }
    
    $stats = array(
        'total_roles' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}accessSchema_roles WHERE is_active = 1"),
        'total_assignments' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}accessSchema_user_roles WHERE is_active = 1"),
        'users_with_roles' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}accessSchema_user_roles WHERE is_active = 1"),
        'max_depth' => $wpdb->get_var("SELECT MAX(depth) FROM {$wpdb->prefix}accessSchema_roles WHERE is_active = 1")
    );
    
    wp_cache_set($cache_key, $stats, 'accessSchema', 3600);
    
    return $stats;
}