<?php
/**
 * Plugin Name: accessSchema
 * Plugin URI: https://github.com/One-World-By-Night/accessSchema
 * Description: Hierarchical role-based access control with audit logging and REST API.
 * Version: 2.3.0
 * Author: greghacke
 * License: GPL-2.0-or-later
 * Text Domain: accessschema
 */

defined( 'ABSPATH' ) || exit;

define( 'ACCESSSCHEMA_VERSION', '2.3.0' );
define( 'ACCESSSCHEMA_PLUGIN_FILE', __FILE__ );
define( 'ACCESSSCHEMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACCESSSCHEMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACCESSSCHEMA_BASENAME', plugin_basename( __FILE__ ) );

$required_files = array(
	'includes/core/init.php',
	'includes/core/activation.php',
	'includes/core/helpers.php',
	'includes/core/webhook-router.php',
	'includes/render/render-admin.php',
	'includes/render/render-functions.php',
	'includes/admin/role-manager.php',
	'includes/admin/settings.php',
	'includes/admin/users-table.php',
	'includes/shortcodes/access.php',
	'includes/utils/access-utils.php',
);

foreach ( $required_files as $file ) {
	$full_path = ACCESSSCHEMA_PLUGIN_DIR . $file;
	if ( file_exists( $full_path ) ) {
		require_once $full_path;
	} else {
		error_log( 'accessSchema: Required file not found: ' . $file );
	}
}

register_activation_hook( __FILE__, 'accessSchema_activate' );
register_deactivation_hook( __FILE__, 'accessSchema_deactivate' );
register_uninstall_hook( __FILE__, 'accessSchema_uninstall' );

function accessSchema_deactivate() {
	// Clear any scheduled events
	wp_clear_scheduled_hook( 'accessSchema_daily_cleanup' );

	// Flush rewrite rules
	flush_rewrite_rules();
}

function accessSchema_uninstall() {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		return;
	}

	// Only remove data if option is set
	if ( get_option( 'accessSchema_remove_data_on_uninstall' ) ) {
		global $wpdb;

		// Remove custom tables
		$tables = array(
			$wpdb->prefix . 'accessSchema_audit_log',
			$wpdb->prefix . 'accessSchema_permissions',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Remove options
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'accessSchema_%'" );

		// Remove user meta
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'accessSchema_%'" );
	}
}
