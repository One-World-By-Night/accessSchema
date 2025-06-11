<?php
// File: includes/core/activation.php
// @version 1.2.1
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
        UNIQUE KEY unique_name_per_parent (name, parent_id),
        UNIQUE KEY unique_full_path (full_path),
        KEY idx_parent_id (parent_id),
        KEY idx_name (name)
    ) $charset_collate;
    ";
    dbDelta( $sql2 );
}