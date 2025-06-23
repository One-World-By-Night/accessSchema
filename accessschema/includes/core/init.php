<?php
/**
 * File: includes/core/init.php
 * @version 1.7.0
 * Author: greghacke
 */

defined('ABSPATH') || exit;

// Plugin init logic
function accessSchema_init() {
    // Load text domain for translations
    load_plugin_textdomain('accessschema', false, dirname(plugin_basename(ACCESSSCHEMA_PLUGIN_FILE)) . '/languages');
    
    // Register custom post statuses if needed
    do_action('accessSchema_init');
    
    // Initialize caching
    if (!wp_cache_get('accessSchema_initialized', 'accessSchema')) {
        wp_cache_set('accessSchema_initialized', true, 'accessSchema', HOUR_IN_SECONDS);
    }
}
add_action('init', 'accessSchema_init');

// Admin assets enqueue with enhanced security
add_action('admin_enqueue_scripts', 'accessSchema_enqueue_role_manager_assets');

function accessSchema_enqueue_role_manager_assets($hook) {
    // Early return for non-admin or incorrect page
    if (!is_admin() || $hook !== 'users_page_accessSchema-roles') {
        return;
    }
    
    // Capability check
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Cache base URL
    static $base_url = null;
    if ($base_url === null) {
        $base_url = ACCESSSCHEMA_PLUGIN_URL . 'assets/';
    }
    
    // Asset versions for cache busting
    $asset_version = defined('WP_DEBUG') && WP_DEBUG ? time() : ACCESSSCHEMA_VERSION;
    
    // Enqueue styles
    wp_enqueue_style(
        'accessSchema-select2',
        $base_url . 'css/select2.min.css',
        array(),
        '4.1.0'
    );
    
    wp_enqueue_style(
        'accessSchema-style',
        $base_url . 'css/accessSchema.css',
        array('accessSchema-select2'),
        $asset_version
    );
    
    // Enqueue scripts
    wp_enqueue_script(
        'accessSchema-select2',
        $base_url . 'js/select2.min.js',
        array('jquery'),
        '4.1.0',
        true
    );
    
    wp_enqueue_script(
        'accessSchema',
        $base_url . 'js/accessSchema.js',
        array('jquery', 'accessSchema-select2', 'wp-util'),
        $asset_version,
        true
    );
    
    // Localize script with security nonces
    wp_localize_script('accessSchema', 'accessSchema_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('accessSchema_ajax_nonce'),
        'rest_url' => esc_url_raw(rest_url('accessSchema/v1/')),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'i18n' => array(
            'error' => __('An error occurred. Please try again.', 'accessschema'),
            'confirm_delete' => __('Are you sure you want to delete this?', 'accessschema'),
            'saving' => __('Saving...', 'accessschema'),
            'saved' => __('Saved successfully.', 'accessschema')
        ),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
    
    // Add inline script for initialization
    wp_add_inline_script('accessSchema', 'jQuery(document).ready(function($) { if (window.accessSchema_init) { accessSchema_init(); } });');
}

// Register AJAX handlers
add_action('wp_ajax_accessSchema_save_role', 'accessSchema_ajax_save_role');
add_action('wp_ajax_accessSchema_delete_role', 'accessSchema_ajax_delete_role');

function accessSchema_ajax_save_role() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'accessSchema_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'accessschema')));
    }
    
    // Check capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Insufficient permissions.', 'accessschema')));
    }
    
    // Process role save (implementation in role-manager.php)
    do_action('accessSchema_save_role', $_POST);
}

function accessSchema_ajax_delete_role() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'accessSchema_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'accessschema')));
    }
    
    // Check capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Insufficient permissions.', 'accessschema')));
    }
    
    // Process role deletion (implementation in role-manager.php)
    do_action('accessSchema_delete_role', $_POST);
}

// Schedule cleanup tasks
add_action('init', 'accessSchema_schedule_events');

function accessSchema_schedule_events() {
    if (!wp_next_scheduled('accessSchema_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'accessSchema_daily_cleanup');
    }
}

add_action('accessSchema_daily_cleanup', 'accessSchema_perform_cleanup');

function accessSchema_perform_cleanup() {
    // Clean expired cache
    wp_cache_flush_group('accessSchema');
    
    // Clean old audit logs (keep 90 days)
    global $wpdb;
    $table = $wpdb->prefix . 'accessSchema_audit_log';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
    }
    
    // Log cleanup completion
    error_log('accessSchema: Daily cleanup completed at ' . current_time('mysql'));
}