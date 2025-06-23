<?php
/**
 * File: includes/core/logging.php
 * @version 2.0.0
 * Author: greghacke
 */

defined('ABSPATH') || exit;

// Get configured log level
function accessSchema_get_log_level() {
    static $level = null;
    
    if ($level === null) {
        $level = get_option('accessSchema_log_level', 'INFO');
    }
    
    return apply_filters('accessSchema_log_level', $level);
}

// Get log level priority
function accessSchema_log_level_priority($level) {
    static $levels = array(
        'DEBUG' => 0,
        'INFO'  => 1,
        'WARN'  => 2,
        'ERROR' => 3,
    );
    
    return isset($levels[strtoupper($level)]) ? $levels[strtoupper($level)] : 1;
}

// Main logging function
function accessSchema_log_event($user_id, $action, $role_path, $context = null, $performed_by = null, $level = 'INFO') {
    // Check if we should log this level
    $current_level = accessSchema_get_log_level();
    if (accessSchema_log_level_priority($level) < accessSchema_log_level_priority($current_level)) {
        return false;
    }
    
    // Check if logging is enabled
    if (!get_option('accessSchema_enable_audit', true)) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'accessSchema_audit_log';
    
    // Prepare context data
    $context_data = array();
    if (is_array($context)) {
        $context_data = wp_parse_args($context, array());
    }
    
    // Add request metadata
    $context_data['log_level'] = $level;
    $context_data['request_uri'] = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    $context_data['http_method'] = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '';
    
    // Get IP address
    $ip_address = accessSchema_get_client_ip();
    
    // Get user agent
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    
    // Use database operation wrapper
    $result = accessSchema_db_operation(function() use ($wpdb, $table_name, $user_id, $action, $role_path, $context_data, $performed_by, $ip_address, $user_agent) {
        return $wpdb->insert(
            $table_name,
            array(
                'user_id'      => absint($user_id),
                'action'       => sanitize_key($action),
                'role_path'    => sanitize_text_field($role_path),
                'context'      => wp_json_encode($context_data),
                'performed_by' => absint($performed_by ?: get_current_user_id()),
                'ip_address'   => $ip_address,
                'user_agent'   => $user_agent,
                'created_at'   => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    });
    
    // Cache invalidation
    if (!is_wp_error($result) && $result !== false) {
        wp_cache_delete('recent_logs_' . $user_id, 'accessSchema');
    }
    
    return !is_wp_error($result) && $result !== false;
}

// Batch logging for performance
function accessSchema_log_batch($events) {
    if (!is_array($events) || empty($events)) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'accessSchema_audit_log';
    
    $values = array();
    $placeholders = array();
    
    foreach ($events as $event) {
        $event = wp_parse_args($event, array(
            'user_id' => 0,
            'action' => '',
            'role_path' => '',
            'context' => null,
            'performed_by' => get_current_user_id(),
            'level' => 'INFO'
        ));
        
        // Skip if level too low
        if (accessSchema_log_level_priority($event['level']) < accessSchema_log_level_priority(accessSchema_get_log_level())) {
            continue;
        }
        
        $context_json = is_array($event['context']) ? wp_json_encode($event['context']) : '{}';
        
        $values[] = absint($event['user_id']);
        $values[] = sanitize_key($event['action']);
        $values[] = sanitize_text_field($event['role_path']);
        $values[] = $context_json;
        $values[] = absint($event['performed_by']);
        $values[] = accessSchema_get_client_ip();
        $values[] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $values[] = current_time('mysql');
        
        $placeholders[] = '(%d, %s, %s, %s, %d, %s, %s, %s)';
    }
    
    if (empty($placeholders)) {
        return true;
    }
    
    $query = "INSERT INTO $table_name (user_id, action, role_path, context, performed_by, ip_address, user_agent, created_at) VALUES " . implode(', ', $placeholders);
    
    return $wpdb->query($wpdb->prepare($query, $values)) !== false;
}

// Get client IP address
function accessSchema_get_client_ip() {
    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

// Cleanup old logs
function accessSchema_cleanup_logs() {
    global $wpdb;
    
    $retention_days = absint(get_option('accessSchema_audit_retention_days', 90));
    if ($retention_days <= 0) {
        return;
    }
    
    $table_name = $wpdb->prefix . 'accessSchema_audit_log';
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
    
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE created_at < %s LIMIT 1000",
        $cutoff_date
    ));
    
    if ($deleted > 0) {
        // Log cleanup event
        accessSchema_log_event(0, 'logs_cleaned', '', array(
            'deleted_count' => $deleted,
            'retention_days' => $retention_days
        ), 0, 'INFO');
    }
    
    return $deleted;
}

// Get recent logs for a user
function accessSchema_get_user_logs($user_id, $limit = 50) {
    $cache_key = 'recent_logs_' . $user_id;
    $cached = wp_cache_get($cache_key, 'accessSchema');
    
    if ($cached !== false) {
        return $cached;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'accessSchema_audit_log';
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
         WHERE user_id = %d 
         ORDER BY created_at DESC 
         LIMIT %d",
        $user_id,
        $limit
    ), ARRAY_A);
    
    // Decode context for each log
    foreach ($logs as &$log) {
        $log['context'] = json_decode($log['context'], true) ?: array();
    }
    
    wp_cache_set($cache_key, $logs, 'accessSchema', 300); // 5 minutes
    
    return $logs;
}