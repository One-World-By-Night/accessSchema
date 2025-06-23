<?php
// File: includes/core/activation.php
// @version 1.6.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

function accessSchema_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $audit_log_table = $wpdb->prefix . 'access_audit_log';
    $roles_table     = $wpdb->prefix . 'access_roles';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Create audit log table
    $sql1 = "
    CREATE TABLE $audit_log_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(64) NOT NULL,
        role_path VARCHAR(255) NOT NULL,
        context TEXT NULL,
        performed_by BIGINT UNSIGNED,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_performed_by (performed_by),
        INDEX idx_created_at (created_at)
    ) $charset_collate;
    ";
    dbDelta( $sql1 );

    // Create access roles table — OMIT FOREIGN KEY
    $sql2 = "
    CREATE TABLE $roles_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id BIGINT UNSIGNED DEFAULT NULL,
        name VARCHAR(191) NOT NULL,
        full_path VARCHAR(767) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        depth TINYINT UNSIGNED DEFAULT 0,
        UNIQUE KEY unique_name_per_parent (name, parent_id),
        UNIQUE KEY unique_full_path (full_path),
        KEY idx_parent_id (parent_id),
        KEY idx_name (name),
        KEY idx_depth (depth),
        KEY idx_full_path_prefix (full_path(191))
    ) $charset_collate;
    ";
    dbDelta( $sql2 );

    // Also add user roles junction table
    $user_roles_table = $wpdb->prefix . 'access_user_roles';
    $sql3 = "
    CREATE TABLE $user_roles_table (
        user_id BIGINT UNSIGNED NOT NULL,
        role_id BIGINT UNSIGNED NOT NULL,
        granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        granted_by BIGINT UNSIGNED,
        PRIMARY KEY (user_id, role_id),
        KEY idx_role_id (role_id),
        KEY idx_granted_at (granted_at)
    ) $charset_collate;
    ";
    dbDelta($sql3);    
}

/** accessSchema_db_operation 
 * Wrapper for database operations with error handling and logging.
 */
function accessSchema_db_operation($callback, $error_message = 'Database operation failed') {
    global $wpdb;
    
    $result = $callback();
    
    if ($result === false) {
        $error = $wpdb->last_error ?: $error_message;
        accessSchema_log_event(0, 'db_error', '', ['error' => $error], null, 'ERROR');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AccessSchema DB Error: ' . $error);
        }
        
        return new WP_Error('db_error', $error);
    }
    
    return $result;
}

/** accessSchema_add_capabilities 
 * Add custom capabilities to administrator role.
 */
function accessSchema_add_capabilities() {
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_access_schema');
        $admin->add_cap('assign_access_roles');
        $admin->add_cap('view_access_logs');
    }
}