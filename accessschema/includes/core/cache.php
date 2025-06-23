<?php

/** 
 * File: includes/core/cache.php
 * @version 1.6.0
 * Author: greghacke
 */

defined( 'ABSPATH' ) || exit;

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
    $roles = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}access_roles ORDER BY full_path",
        ARRAY_A
    );
    
    wp_cache_set($cache_key, $roles, 'accessSchema', 3600);
    return $roles;
}

/**
 * Clear all accessSchema caches
 */
function accessSchema_clear_all_caches() {
    global $wpdb;
    
    // Clear object cache
    wp_cache_flush();
    
    // Clear any transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_accessschema_%' 
         OR option_name LIKE '_transient_timeout_accessschema_%'"
    );
}

/**
 * Warm up caches after changes
 */
function accessSchema_warm_caches() {
    accessSchema_get_all_roles(true);
    
    // Pre-cache common role paths
    global $wpdb;
    $common_paths = $wpdb->get_col(
        "SELECT DISTINCT full_path 
         FROM {$wpdb->prefix}access_roles 
         WHERE depth <= 2"
    );
    
    foreach ($common_paths as $path) {
        accessSchema_role_exists_cached($path);
    }
}