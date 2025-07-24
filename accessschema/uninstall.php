<?php
/**
 * File: uninstall.php
 * * * @version 2.0.2
 * Author: greghacke
 */

// Exit if accessed directly or not via WP uninstall process
defined('WP_UNINSTALL_PLUGIN') || exit;

// Optional: Only run cleanup if the user explicitly opted in
$delete_on_uninstall = get_option('accessSchema_remove_data_on_uninstall', false);

if ( ! $delete_on_uninstall ) {
    return;
}

// Clean up options
delete_option('accessSchema_db_version');
delete_option('accessSchema_api_key_readonly');
delete_option('accessSchema_api_key_readwrite');
delete_option('accessSchema_log_level');
delete_option('accessSchema_enable_audit');
delete_option('accessSchema_audit_retention_days');
delete_option('accessSchema_cache_ttl');
delete_option('accessSchema_max_depth');
delete_option('accessSchema_enable_rest_api');
delete_option('accessSchema_enable_webhooks');
delete_option('accessSchema_remove_data_on_uninstall');

// Drop custom tables
global $wpdb;

$roles_table = $wpdb->prefix . 'accessSchema_roles';
$audit_table = $wpdb->prefix . 'accessSchema_audit_log';
$user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';
$permissions_cache_table = $wpdb->prefix . 'accessSchema_permissions_cache';
$rate_limits_table = $wpdb->prefix . 'accessSchema_rate_limits';

// Clean up transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_accessSchema_%' OR option_name LIKE '_transient_timeout_accessSchema_%'");

// Clean up user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'accessSchema_%'");

// Remove custom capabilities from roles
$wp_roles = wp_roles();
$capabilities = array(
    'manage_access_schema',
    'assign_access_roles',
    'view_access_logs',
    'edit_access_roles',
    'delete_access_roles',
    'export_access_data'
);

foreach ($wp_roles->roles as $role_name => $role_info) {
    $role = get_role($role_name);
    if ($role) {
        foreach ($capabilities as $cap) {
            $role->remove_cap($cap);
        }
    }
}

// Remove custom role
remove_role('access_manager');

// Drop custom tables
$wpdb->query("DROP TABLE IF EXISTS $user_roles_table");
$wpdb->query("DROP TABLE IF EXISTS $roles_table");
$wpdb->query("DROP TABLE IF EXISTS $audit_table");
$wpdb->query("DROP TABLE IF EXISTS $permissions_cache_table");
$wpdb->query("DROP TABLE IF EXISTS $rate_limits_table");