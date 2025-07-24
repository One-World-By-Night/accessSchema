<?php
/**
 * File: includes/core/permission-checks.php
 * * * @version 2.0.2
 * Author: greghacke
 */

defined('ABSPATH') || exit;

/**
 * Check if a user has a given role or any of its children.
 */
function accessSchema_check_permission($user_id, $target_path, $include_children = false, $log = true, $context = array(), $allow_wildcards = false) {
    $target_path = trim($target_path);
    
    // Validate inputs
    if (empty($user_id) || empty($target_path)) {
        return false;
    }
    
    // Check cache first
    $cache_key = sprintf('perm_%d_%s_%d_%d', $user_id, md5($target_path), $include_children ? 1 : 0, $allow_wildcards ? 1 : 0);
    $cached = wp_cache_get($cache_key, 'accessSchema');
    
    if ($cached !== false && !$log) {
        return (bool) $cached;
    }
    
    // Get user roles with caching
    $roles = accessSchema_get_user_roles($user_id);
    
    if (empty($roles)) {
        if ($log) {
            accessSchema_log_permission_check($user_id, $target_path, false, 'no_roles_assigned', $context);
        }
        wp_cache_set($cache_key, false, 'accessSchema', 300);
        return false;
    }
    
    $matched = false;
    $match_type = '';
    
    // Wildcard pattern check
    if ($allow_wildcards && (strpos($target_path, '*') !== false)) {
        $regex = accessSchema_pattern_to_regex($target_path);
        foreach ($roles as $role) {
            if (preg_match($regex, $role)) {
                $matched = true;
                $match_type = 'wildcard';
                break;
            }
        }
    } else {
        // Validate role exists
        if (!accessSchema_role_exists_cached($target_path)) {
            if ($log) {
                accessSchema_log_permission_check($user_id, $target_path, false, 'role_not_registered', $context);
            }
            wp_cache_set($cache_key, false, 'accessSchema', 300);
            return false;
        }
        
        // Direct match
        if (in_array($target_path, $roles, true)) {
            $matched = true;
            $match_type = 'exact';
        }
        
        // Children check
        if (!$matched && $include_children) {
            $target_prefix = $target_path . '/';
            foreach ($roles as $role) {
                if (strpos($role, $target_prefix) === 0) {
                    $matched = true;
                    $match_type = 'child';
                    break;
                }
            }
        }
        
        // Parent inheritance check
        if (!$matched) {
            $parts = explode('/', $target_path);
            while (count($parts) > 1) {
                array_pop($parts);
                $parent_path = implode('/', $parts);
                if (in_array($parent_path, $roles, true)) {
                    // Check if parent grants access
                    if (apply_filters('accessSchema_parent_grants_access', false, $parent_path, $target_path)) {
                        $matched = true;
                        $match_type = 'inherited';
                        break;
                    }
                }
            }
        }
    }
    
    // Cache result
    wp_cache_set($cache_key, $matched, 'accessSchema', 300);
    
    // Log if requested
    if ($log) {
        $context['match_type'] = $match_type;
        accessSchema_log_permission_check($user_id, $target_path, $matched, $matched ? 'access_granted' : 'access_denied', $context);
    }
    
    return $matched;
}

/**
 * Log permission check with enhanced context
 */
function accessSchema_log_permission_check($user_id, $target_path, $result, $reason, $context = array()) {
    $default_context = array(
        'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
        'http_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '',
        'ip' => accessSchema_get_client_ip(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
    );
    
    $context = array_merge($default_context, $context);
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
 * Check if a role exists with caching
 */
function accessSchema_role_exists_cached($role_path) {
    return accessSchema_cached_operation(
        'role_exists_' . md5($role_path),
        function() use ($role_path) {
            return accessSchema_role_exists($role_path);
        },
        3600
    );
}

/**
 * Check if user can access a role path (convenience function)
 */
function accessSchema_user_can($user_id, $pattern) {
    return accessSchema_check_permission($user_id, $pattern, false, false, array(), true);
}

/**
 * Check if current user can access a role path
 */
function accessSchema_current_user_can($pattern) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return false;
    }
    
    return accessSchema_user_can($user_id, $pattern);
}

/**
 * Batch permission check for performance
 */
function accessSchema_check_permissions_batch($user_id, array $permissions) {
    $results = array();
    $roles = accessSchema_get_user_roles($user_id);
    
    foreach ($permissions as $permission) {
        $target_path = is_array($permission) ? $permission['path'] : $permission;
        $include_children = is_array($permission) ? !empty($permission['include_children']) : false;
        $allow_wildcards = is_array($permission) ? !empty($permission['allow_wildcards']) : false;
        
        $cache_key = sprintf('perm_%d_%s_%d_%d', $user_id, md5($target_path), $include_children ? 1 : 0, $allow_wildcards ? 1 : 0);
        $cached = wp_cache_get($cache_key, 'accessSchema');
        
        if ($cached !== false) {
            $results[$target_path] = (bool) $cached;
            continue;
        }
        
        // Perform check without logging for batch
        $result = accessSchema_check_permission($user_id, $target_path, $include_children, false, array(), $allow_wildcards);
        $results[$target_path] = $result;
    }
    
    // Log batch check
    accessSchema_log_event($user_id, 'batch_permission_check', '', array(
        'permissions' => array_keys($results),
        'granted' => array_keys(array_filter($results))
    ), null, 'DEBUG');
    
    return $results;
}

/**
 * Check if user matches wildcard pattern
 */
function accessSchema_user_matches_role_pattern($user_id, $pattern) {
    return accessSchema_check_permission($user_id, $pattern, false, false, array(), true);
}

/**
 * Check if user matches ANY of multiple patterns
 */
function accessSchema_user_matches_any($user_id, array $patterns) {
    // Check cache for common pattern sets
    $cache_key = 'matches_any_' . $user_id . '_' . md5(serialize($patterns));
    $cached = wp_cache_get($cache_key, 'accessSchema');
    
    if ($cached !== false) {
        return (bool) $cached;
    }
    
    foreach ($patterns as $pattern) {
        if (accessSchema_user_matches_role_pattern($user_id, $pattern)) {
            wp_cache_set($cache_key, true, 'accessSchema', 300);
            return true;
        }
    }
    
    wp_cache_set($cache_key, false, 'accessSchema', 300);
    return false;
}

/**
 * Check if user matches ALL of multiple patterns
 */
function accessSchema_user_matches_all($user_id, array $patterns) {
    foreach ($patterns as $pattern) {
        if (!accessSchema_user_matches_role_pattern($user_id, $pattern)) {
            return false;
        }
    }
    return true;
}

/**
 * Convert wildcard path pattern to regex
 */
function accessSchema_pattern_to_regex($pattern) {
    static $regex_cache = array();
    
    if (isset($regex_cache[$pattern])) {
        return $regex_cache[$pattern];
    }
    
    // Escape regex characters except * and **
    $escaped = preg_quote($pattern, '#');
    
    // Replace wildcards
    $regex = strtr($escaped, array(
        '\*\*' => '.*',        // ** matches any number of segments
        '\*' => '[^/]+'        // * matches single segment
    ));
    
    // Ensure full path match
    $regex = "#^{$regex}$#";
    
    // Cache regex (limit cache size)
    if (count($regex_cache) > 100) {
        $regex_cache = array_slice($regex_cache, -50, null, true);
    }
    $regex_cache[$pattern] = $regex;
    
    return $regex;
}

/**
 * Get all permissions for a user
 */
function accessSchema_get_user_permissions($user_id) {
    global $wpdb;
    $cache_table = $wpdb->prefix . 'accessSchema_permissions_cache';
    
    // Check database cache first
    $cached = $wpdb->get_var($wpdb->prepare(
        "SELECT permissions FROM {$cache_table} 
         WHERE user_id = %d 
         AND calculated_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
        $user_id,
        get_option('accessSchema_cache_ttl', 3600)
    ));
    
    if ($cached) {
        return json_decode($cached, true);
    }
    
    // Calculate permissions
    $roles = accessSchema_get_user_roles($user_id);
    $permissions = array(
        'roles' => $roles,
        'capabilities' => array(),
        'restrictions' => array()
    );
    
    // Get capabilities from roles
    foreach ($roles as $role_path) {
        $role_caps = apply_filters('accessSchema_role_capabilities', array(), $role_path);
        $permissions['capabilities'] = array_merge($permissions['capabilities'], $role_caps);
    }
    
    $permissions['capabilities'] = array_unique($permissions['capabilities']);
    
    // Apply restrictions
    $permissions['restrictions'] = apply_filters('accessSchema_user_restrictions', array(), $user_id, $roles);
    
    // Cache in database
    $wpdb->replace(
        $cache_table,
        array(
            'user_id' => $user_id,
            'permissions' => wp_json_encode($permissions),
            'calculated_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s')
    );
    
    return $permissions;
}

/**
 * Check if user has specific capability
 */
function accessSchema_user_has_cap($user_id, $capability) {
    $permissions = accessSchema_get_user_permissions($user_id);
    return in_array($capability, $permissions['capabilities'], true);
}