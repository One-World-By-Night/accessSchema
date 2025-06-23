<?php
/**
 * File: includes/shortcodes/access.php
 * @version 1.7.0
 * Author: greghacke
 */

defined('ABSPATH') || exit;

/**
 * Access control shortcode
 */
function accessSchema_shortcode_access($atts, $content = null) {
    // Check login state
    if (!is_user_logged_in()) {
        return apply_filters('accessSchema_shortcode_not_logged_in', '', $atts, $content);
    }
    
    $user_id = get_current_user_id();
    
    // Parse attributes
    $atts = shortcode_atts(array(
        'role'     => '',       
        'any'      => '',       
        'all'      => '',       
        'not'      => '',       
        'children' => 'false',  
        'wildcard' => 'false',  
        'fallback' => '',       
        'cache'    => 'true',   
        'debug'    => 'false'   
    ), $atts, 'access_schema');
    
    $children = filter_var($atts['children'], FILTER_VALIDATE_BOOLEAN);
    $wildcard = filter_var($atts['wildcard'], FILTER_VALIDATE_BOOLEAN);
    $use_cache = filter_var($atts['cache'], FILTER_VALIDATE_BOOLEAN);
    $debug = filter_var($atts['debug'], FILTER_VALIDATE_BOOLEAN) && current_user_can('manage_options');
    
    // Generate cache key
    $cache_key = 'shortcode_' . md5(serialize($atts) . '_' . $user_id);
    
    if ($use_cache) {
        $cached = wp_cache_get($cache_key, 'accessSchema');
        if ($cached !== false) {
            return $cached ? do_shortcode($content) : wp_kses_post($atts['fallback']);
        }
    }
    
    $has_access = false;
    $debug_info = array();
    
    // Check NOT condition first
    if (!empty($atts['not'])) {
        $patterns = array_map('trim', explode(',', $atts['not']));
        if (accessSchema_user_matches_any($user_id, $patterns)) {
            $debug_info[] = 'Failed NOT condition';
            $has_access = false;
        } else {
            $has_access = true;
        }
    }
    
    // Check ALL condition
    if ($has_access !== false && !empty($atts['all'])) {
        $patterns = array_map('trim', explode(',', $atts['all']));
        $has_access = accessSchema_user_matches_all($user_id, $patterns);
        $debug_info[] = 'ALL check: ' . ($has_access ? 'passed' : 'failed');
    }
    
    // Check ANY condition
    if ($has_access !== false && !empty($atts['any'])) {
        $patterns = array_map('trim', explode(',', $atts['any']));
        $has_access = accessSchema_user_matches_any($user_id, $patterns);
        $debug_info[] = 'ANY check: ' . ($has_access ? 'passed' : 'failed');
    }
    
    // Check single role
    if ($has_access !== false && !empty($atts['role'])) {
        $role = trim($atts['role']);
        
        if ($wildcard) {
            $has_access = accessSchema_user_matches_role_pattern($user_id, $role);
            $debug_info[] = 'Wildcard check: ' . ($has_access ? 'passed' : 'failed');
        } else {
            $has_access = accessSchema_check_permission($user_id, $role, $children, false);
            $debug_info[] = 'Role check: ' . ($has_access ? 'passed' : 'failed');
        }
    }
    
    // Cache result
    if ($use_cache) {
        wp_cache_set($cache_key, $has_access, 'accessSchema', 300);
    }
    
    // Debug output
    if ($debug) {
        $debug_output = '<!-- AccessSchema Debug: ' . implode(', ', $debug_info) . ' -->';
        return $debug_output . ($has_access ? do_shortcode($content) : wp_kses_post($atts['fallback']));
    }
    
    // Return content or fallback
    return $has_access ? do_shortcode($content) : wp_kses_post($atts['fallback']);
}

add_shortcode('access_schema', 'accessSchema_shortcode_access');

/**
 * User info shortcode
 */
function accessSchema_shortcode_user_roles($atts) {
    if (!is_user_logged_in()) {
        return '';
    }
    
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(),
        'format'  => 'list',
        'separator' => ', '
    ), $atts, 'access_schema_user_roles');
    
    $user_id = absint($atts['user_id']);
    $roles = accessSchema_get_user_roles($user_id);
    
    if (empty($roles)) {
        return '';
    }
    
    switch ($atts['format']) {
        case 'count':
            return count($roles);
            
        case 'json':
            return esc_html(wp_json_encode($roles));
            
        case 'list':
            $output = '<ul class="accessSchema-role-list">';
            foreach ($roles as $role) {
                $output .= '<li>' . esc_html($role) . '</li>';
            }
            $output .= '</ul>';
            return $output;
            
        case 'inline':
        default:
            return esc_html(implode($atts['separator'], $roles));
    }
}

add_shortcode('access_schema_user_roles', 'accessSchema_shortcode_user_roles');

/**
 * Has access conditional shortcode
 */
function accessSchema_shortcode_has_access($atts) {
    $atts = shortcode_atts(array(
        'role' => '',
        'user_id' => get_current_user_id()
    ), $atts, 'access_schema_has_access');
    
    if (empty($atts['role'])) {
        return '';
    }
    
    $user_id = absint($atts['user_id']);
    return accessSchema_user_can($user_id, $atts['role']) ? '1' : '0';
}

add_shortcode('access_schema_has_access', 'accessSchema_shortcode_has_access');