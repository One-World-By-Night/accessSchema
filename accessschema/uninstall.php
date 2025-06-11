<?php
// Exit if accessed directly or not via WP uninstall process
defined('WP_UNINSTALL_PLUGIN') || exit;

// Optional: Only run cleanup if the user explicitly opted in
$delete_on_uninstall = get_option('accessschema_delete_on_uninstall', true);

if ( ! $delete_on_uninstall ) {
    return;
}

// Clean up options
delete_option('accessschema_delete_on_uninstall');
delete_option('accessschema_role_index');
delete_option('accessschema_meta_cache');
delete_option('accessschema_config');
delete_option('accessschema_last_sync');
delete_option('accessschema_remote_cache');

// Drop custom tables
global $wpdb;

$roles_table = $wpdb->prefix . 'access_roles';
$audit_table = $wpdb->prefix . 'acces_audit_logs';

$wpdb->query("DROP TABLE IF EXISTS $roles_table");
$wpdb->query("DROP TABLE IF EXISTS $audit_table");