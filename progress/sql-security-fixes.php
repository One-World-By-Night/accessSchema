<?php
/**
 * Add this to includes/core/helpers.php to fix SQL security issues
 */

/**
 * Get safe table name
 */
function accessSchema_get_table($table_suffix) {
    global $wpdb;
    return "`{$wpdb->prefix}accessSchema_{$table_suffix}`";
}

/**
 * Safe query wrapper that handles table interpolation
 */
function accessSchema_safe_query($query_template, $params = array()) {
    global $wpdb;
    
    // Replace table placeholders
    $tables = array(
        '{{roles}}' => accessSchema_get_table('roles'),
        '{{audit_log}}' => accessSchema_get_table('audit_log'),
        '{{user_roles}}' => accessSchema_get_table('user_roles'),
        '{{permissions_cache}}' => accessSchema_get_table('permissions_cache'),
        '{{rate_limits}}' => accessSchema_get_table('rate_limits')
    );
    
    $query = str_replace(array_keys($tables), array_values($tables), $query_template);
    
    if (empty($params)) {
        return $wpdb->get_results($query);
    }
    
    return $wpdb->get_results($wpdb->prepare($query, ...$params));
}

/**
 * Sanitize and unslash input
 */
function accessSchema_sanitize_input($input, $type = 'text') {
    if (!isset($input)) {
        return '';
    }
    
    $unslashed = wp_unslash($input);
    
    switch ($type) {
        case 'textarea':
            return sanitize_textarea_field($unslashed);
        case 'email':
            return sanitize_email($unslashed);
        case 'url':
            return esc_url_raw($unslashed);
        case 'key':
            return sanitize_key($unslashed);
        case 'int':
            return absint($unslashed);
        default:
            return sanitize_text_field($unslashed);
    }
}

/**
 * Safe server variable access
 */
function accessSchema_server_value($key, $default = '') {
    if (!isset($_SERVER[$key])) {
        return $default;
    }
    
    return sanitize_text_field(wp_unslash($_SERVER[$key]));
}

/**
 * Replace error_log with debug-safe logging
 */
function accessSchema_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('accessSchema: ' . $message);
    }
}