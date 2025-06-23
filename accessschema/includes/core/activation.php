<?php
/**
 * File: includes/core/activation.php
 * @version 2.0.0
 * Author: greghacke
 */

defined('ABSPATH') || exit;

function accessSchema_activate() {
    global $wpdb;
    
    // Start transaction support
    $wpdb->query('START TRANSACTION');
    
    try {
        // Check minimum requirements
        if (version_compare(PHP_VERSION, '7.2', '<')) {
            throw new Exception('accessSchema requires PHP 7.2 or higher');
        }
        
        if (version_compare($GLOBALS['wp_version'], '5.0', '<')) {
            throw new Exception('accessSchema requires WordPress 5.0 or higher');
        }
        
        // Create or update tables
        $result = accessSchema_create_tables();
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        // Add capabilities
        accessSchema_add_capabilities();
        
        // Set default options
        accessSchema_set_default_options();
        
        // Log activation
        if (function_exists('accessSchema_log_event')) {
            accessSchema_log_event(0, 'plugin_activated', '', array(
                'version' => ACCESSSCHEMA_VERSION,
                'php_version' => PHP_VERSION,
                'wp_version' => $GLOBALS['wp_version']
            ));
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Schedule initial cleanup
        wp_schedule_single_event(time() + 300, 'accessSchema_post_activation_setup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        
        // Log error
        error_log('accessSchema activation failed: ' . $e->getMessage());
        
        // Deactivate plugin
        deactivate_plugins(plugin_basename(ACCESSSCHEMA_PLUGIN_FILE));
        
        // Show error to admin
        wp_die(
            esc_html__('Plugin activation failed: ', 'accessschema') . esc_html($e->getMessage()),
            esc_html__('Activation Error', 'accessschema'),
            array('back_link' => true)
        );
    }
}

function accessSchema_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $errors = array();
    
    // Audit log table
    $audit_log_table = $wpdb->prefix . 'accessSchema_audit_log';
    $sql1 = "CREATE TABLE $audit_log_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        action varchar(64) NOT NULL,
        role_path varchar(255) NOT NULL,
        context text NULL,
        performed_by bigint(20) unsigned DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        user_agent text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_user_action (user_id, action),
        KEY idx_performed_by (performed_by),
        KEY idx_created_at (created_at),
        KEY idx_ip_address (ip_address)
    ) $charset_collate";
    
    dbDelta($sql1);
    
    if ($wpdb->last_error) {
        $errors[] = 'Failed to create audit log table: ' . $wpdb->last_error;
    }
    
    // Roles table
    $roles_table = $wpdb->prefix . 'accessSchema_roles';
    $sql2 = "CREATE TABLE $roles_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        parent_id bigint(20) unsigned DEFAULT NULL,
        name varchar(191) NOT NULL,
        slug varchar(191) NOT NULL,
        full_path varchar(500) NOT NULL,
        capabilities text DEFAULT NULL,
        meta text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) unsigned DEFAULT NULL,
        depth tinyint(3) unsigned DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        PRIMARY KEY  (id),
        UNIQUE KEY unique_slug_per_parent (slug, parent_id),
        UNIQUE KEY unique_full_path (full_path),
        KEY idx_parent_id (parent_id),
        KEY idx_name (name),
        KEY idx_slug (slug),
        KEY idx_depth (depth),
        KEY idx_is_active (is_active),
        KEY idx_full_path_prefix (full_path(191))
    ) $charset_collate";
    
    dbDelta($sql2);
    
    if ($wpdb->last_error) {
        $errors[] = 'Failed to create roles table: ' . $wpdb->last_error;
    }
    
    // User roles junction table
    $user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';
    $sql3 = "CREATE TABLE $user_roles_table (
        user_id bigint(20) unsigned NOT NULL,
        role_id bigint(20) unsigned NOT NULL,
        granted_at datetime DEFAULT CURRENT_TIMESTAMP,
        granted_by bigint(20) unsigned DEFAULT NULL,
        expires_at datetime DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        PRIMARY KEY  (user_id, role_id),
        KEY idx_role_id (role_id),
        KEY idx_granted_at (granted_at),
        KEY idx_expires_at (expires_at),
        KEY idx_is_active (is_active)
    ) $charset_collate";
    
    dbDelta($sql3);
    
    if ($wpdb->last_error) {
        $errors[] = 'Failed to create user roles table: ' . $wpdb->last_error;
    }
    
    // Permissions cache table
    $permissions_cache = $wpdb->prefix . 'accessSchema_permissions_cache';
    $sql4 = "CREATE TABLE $permissions_cache (
        user_id bigint(20) unsigned NOT NULL,
        permissions text NOT NULL,
        calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (user_id),
        KEY idx_calculated_at (calculated_at)
    ) $charset_collate";
    
    dbDelta($sql4);
    
    if ($wpdb->last_error) {
        $errors[] = 'Failed to create permissions cache table: ' . $wpdb->last_error;
    }
    
    // Return errors if any
    if (!empty($errors)) {
        return new WP_Error('table_creation_failed', implode(', ', $errors));
    }
    
    // Store installed version
    update_option('accessSchema_db_version', ACCESSSCHEMA_VERSION);
    
    return true;
}

function accessSchema_add_capabilities() {
    $capabilities = array(
        'manage_access_schema',
        'assign_access_roles',
        'view_access_logs',
        'edit_access_roles',
        'delete_access_roles',
        'export_access_data'
    );
    
    // Add to administrator
    $admin = get_role('administrator');
    if ($admin) {
        foreach ($capabilities as $cap) {
            $admin->add_cap($cap);
        }
    }
    
    // Create custom role
    add_role('access_manager', __('Access Manager', 'accessschema'), array(
        'read' => true,
        'manage_access_schema' => true,
        'assign_access_roles' => true,
        'view_access_logs' => true,
        'edit_access_roles' => true
    ));
}

function accessSchema_set_default_options() {
    $defaults = array(
        'accessSchema_db_version' => ACCESSSCHEMA_VERSION,
        'accessSchema_enable_audit' => true,
        'accessSchema_audit_retention_days' => 90,
        'accessSchema_cache_ttl' => 3600,
        'accessSchema_max_depth' => 10,
        'accessSchema_enable_rest_api' => true,
        'accessSchema_enable_webhooks' => false,
        'accessSchema_remove_data_on_uninstall' => false
    );
    
    foreach ($defaults as $option => $value) {
        if (get_option($option) === false) {
            add_option($option, $value, '', 'no');
        }
    }
}

// Database operation wrapper
function accessSchema_db_operation($callback, $error_message = 'Database operation failed') {
    global $wpdb;
    
    // Suppress errors temporarily
    $suppress = $wpdb->suppress_errors();
    
    try {
        $result = $callback();
        
        if ($result === false) {
            $error = $wpdb->last_error ?: $error_message;
            
            if (function_exists('accessSchema_log_event')) {
                accessSchema_log_event(0, 'db_error', '', array(
                    'error' => $error,
                    'query' => $wpdb->last_query
                ));
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('accessSchema DB Error: ' . $error);
            }
            
            return new WP_Error('db_error', $error);
        }
        
        return $result;
        
    } finally {
        $wpdb->suppress_errors($suppress);
    }
}

// Post activation setup
add_action('accessSchema_post_activation_setup', 'accessSchema_post_activation_tasks');

function accessSchema_post_activation_tasks() {
    // Create default roles
    global $wpdb;
    $table = $wpdb->prefix . 'accessSchema_roles';
    
    $default_roles = array(
        array(
            'name' => 'Organization',
            'slug' => 'organization',
            'full_path' => 'organization',
            'depth' => 0
        )
    );
    
    foreach ($default_roles as $role) {
        $wpdb->insert($table, $role, array('%s', '%s', '%s', '%d'));
    }
    
    // Clear any existing cache
    wp_cache_flush_group('accessSchema');
}

// Upgrade routine
function accessSchema_check_version() {
    $installed_version = get_option('accessSchema_db_version');
    
    if ($installed_version !== ACCESSSCHEMA_VERSION) {
        accessSchema_activate();
    }
}
add_action('plugins_loaded', 'accessSchema_check_version');