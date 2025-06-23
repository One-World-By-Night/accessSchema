<?php
/**
 * File: includes/core/cache.php
 * @version 1.7.0
 * Author: greghacke
 */

defined('ABSPATH') || exit;

/**
 * Initialize cache system
 */
function accessSchema_init_cache() {
    // Register cache group
    wp_cache_add_global_groups(array('accessSchema'));
    
    // Set up cache invalidation hooks
    add_action('accessSchema_role_added', 'accessSchema_invalidate_user_cache', 10, 2);
    add_action('accessSchema_role_removed', 'accessSchema_invalidate_user_cache', 10, 2);
    add_action('accessSchema_role_created', 'accessSchema_invalidate_role_cache');
    add_action('accessSchema_role_deleted', 'accessSchema_invalidate_role_cache');
}

/**
 * Get all roles with caching
 */
function accessSchema_get_all_roles($force_refresh = false) {
    $cache_key = 'all_roles';
    
    if (!$force_refresh) {
        $cached = wp_cache_get($cache_key, 'accessSchema');
        if ($cached !== false) {
            return $cached;
        }
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'accessSchema_roles';
    
    $roles = accessSchema_db_operation(function() use ($wpdb, $table) {
        return $wpdb->get_results(
            "SELECT * FROM {$table} 
             WHERE is_active = 1 
             ORDER BY depth, full_path",
            ARRAY_A
        );
    });
    
    if (is_wp_error($roles)) {
        return array();
    }
    
    // Build hierarchical structure
    $structured = accessSchema_build_role_hierarchy($roles);
    
    wp_cache_set($cache_key, $structured, 'accessSchema', 3600);
    
    // Update cache stats
    accessSchema_update_cache_stats('all_roles', count($roles));
    
    return $structured;
}

/**
 * Build hierarchical role structure
 */
function accessSchema_build_role_hierarchy($flat_roles) {
    $hierarchy = array();
    $role_map = array();
    
    // First pass: create map
    foreach ($flat_roles as $role) {
        $role_map[$role['id']] = $role;
        $role_map[$role['id']]['children'] = array();
    }
    
    // Second pass: build hierarchy
    foreach ($flat_roles as $role) {
        if (empty($role['parent_id'])) {
            $hierarchy[] = &$role_map[$role['id']];
        } else if (isset($role_map[$role['parent_id']])) {
            $role_map[$role['parent_id']]['children'][] = &$role_map[$role['id']];
        }
    }
    
    return $hierarchy;
}

/**
 * Clear all accessSchema caches
 */
function accessSchema_clear_all_caches() {
    global $wpdb;
    
    // Clear object cache group
    wp_cache_flush_group('accessSchema');
    
    // Clear transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_accessSchema_%' 
         OR option_name LIKE '_transient_timeout_accessSchema_%'"
    );
    
    // Clear permissions cache table
    $cache_table = $wpdb->prefix . 'accessSchema_permissions_cache';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$cache_table}'") === $cache_table) {
        $wpdb->query("TRUNCATE TABLE {$cache_table}");
    }
    
    // Log cache clear
    accessSchema_log_event(0, 'cache_cleared', '', array(
        'cleared_by' => get_current_user_id()
    ), null, 'INFO');
}

/**
 * Invalidate user-specific caches
 */
function accessSchema_invalidate_user_cache($user_id, $role_path = '') {
    $cache_keys = array(
        'user_roles_' . $user_id,
        'user_roles_' . $user_id . '_all',
        'user_permissions_' . $user_id,
        'recent_logs_' . $user_id
    );
    
    foreach ($cache_keys as $key) {
        wp_cache_delete($key, 'accessSchema');
    }
    
    // Clear permissions cache in database
    global $wpdb;
    $cache_table = $wpdb->prefix . 'accessSchema_permissions_cache';
    $wpdb->delete($cache_table, array('user_id' => $user_id), array('%d'));
}

/**
 * Invalidate role-specific caches
 */
function accessSchema_invalidate_role_cache($role_id = null) {
    // Clear general role caches
    wp_cache_delete('all_roles', 'accessSchema');
    wp_cache_delete('role_tree_0', 'accessSchema');
    
    if ($role_id) {
        wp_cache_delete('role_tree_' . $role_id, 'accessSchema');
    }
    
    // Clear all role existence caches
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_accessSchema_role_exists_%'"
    );
}

/**
 * Warm up caches after changes
 */
function accessSchema_warm_caches() {
    // Pre-load all roles
    accessSchema_get_all_roles(true);
    
    // Pre-cache common role paths
    global $wpdb;
    $table = $wpdb->prefix . 'accessSchema_roles';
    
    $common_paths = $wpdb->get_col(
        "SELECT DISTINCT full_path 
         FROM {$table} 
         WHERE depth <= 2 
         AND is_active = 1
         LIMIT 50"
    );
    
    foreach ($common_paths as $path) {
        $cache_key = 'role_exists_' . md5($path);
        wp_cache_set($cache_key, true, 'accessSchema', 3600);
    }
    
    // Pre-cache active users' roles
    $active_users = $wpdb->get_col(
        "SELECT DISTINCT user_id 
         FROM {$wpdb->prefix}accessSchema_audit_log 
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
         LIMIT 100"
    );
    
    foreach ($active_users as $user_id) {
        accessSchema_get_user_roles($user_id);
    }
}

/**
 * Get cache statistics
 */
function accessSchema_get_cache_stats() {
    $stats = get_transient('accessSchema_cache_stats');
    
    if (!$stats) {
        $stats = array(
            'hits' => 0,
            'misses' => 0,
            'ratio' => 0,
            'size' => 0,
            'items' => array()
        );
    }
    
    return $stats;
}

/**
 * Update cache statistics
 */
function accessSchema_update_cache_stats($key, $size = 0, $hit = true) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $stats = accessSchema_get_cache_stats();
    
    if ($hit) {
        $stats['hits']++;
    } else {
        $stats['misses']++;
    }
    
    $total = $stats['hits'] + $stats['misses'];
    $stats['ratio'] = $total > 0 ? round(($stats['hits'] / $total) * 100, 2) : 0;
    
    if (!isset($stats['items'][$key])) {
        $stats['items'][$key] = array(
            'hits' => 0,
            'size' => 0
        );
    }
    
    $stats['items'][$key]['hits']++;
    $stats['items'][$key]['size'] = $size;
    $stats['size'] = array_sum(array_column($stats['items'], 'size'));
    
    set_transient('accessSchema_cache_stats', $stats, DAY_IN_SECONDS);
}

/**
 * Clean up old cache entries
 */
function accessSchema_cleanup_cache() {
    global $wpdb;
    
    // Clean permissions cache older than TTL
    $ttl = (int) get_option('accessSchema_cache_ttl', 3600);
    $cutoff = date('Y-m-d H:i:s', time() - $ttl);
    
    $cache_table = $wpdb->prefix . 'accessSchema_permissions_cache';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$cache_table}'") === $cache_table) {
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$cache_table} WHERE calculated_at < %s",
            $cutoff
        ));
        
        if ($deleted > 0) {
            accessSchema_log_event(0, 'cache_cleanup', '', array(
                'deleted_entries' => $deleted
            ), 0, 'DEBUG');
        }
    }
    
    // Clear old transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_timeout_accessSchema_%' 
         AND option_value < UNIX_TIMESTAMP()"
    );
}

// Schedule cache cleanup
add_action('accessSchema_daily_cleanup', 'accessSchema_cleanup_cache');

/**
 * Cache wrapper for expensive operations
 */
function accessSchema_cached_operation($cache_key, $callback, $expiration = 3600) {
    $cached = wp_cache_get($cache_key, 'accessSchema');
    
    if ($cached !== false) {
        accessSchema_update_cache_stats($cache_key, 0, true);
        return $cached;
    }
    
    $result = call_user_func($callback);
    
    if ($result !== false && !is_wp_error($result)) {
        wp_cache_set($cache_key, $result, 'accessSchema', $expiration);
        accessSchema_update_cache_stats($cache_key, strlen(serialize($result)), false);
    }
    
    return $result;
}