<?php
/**
 * Plugin Name: accessSchema
 * Description: Manage Role-based access schema plugin with audit logging and REST API support.
 * Version: 2.0.0
 * Author: greghacke
 * Author URI: https://www.owbn.net
 * Text Domain: accessschema
 * Tested up to: 6.8
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/One-World-By-Night/owbn-chronicle-plugin
 * GitHub Branch: main
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'ACCESSSCHEMA_VERSION', '2.0.0' );
define( 'ACCESSSCHEMA_PLUGIN_FILE', __FILE__ );
define( 'ACCESSSCHEMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACCESSSCHEMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACCESSSCHEMA_BASENAME', plugin_basename( __FILE__ ) );

// Load dependencies with file existence checks
$required_files = array(
	'includes/core/init.php',
	'includes/core/activation.php',
	'includes/core/helpers.php',
	'includes/core/webhook-router.php',
	'includes/render/render-admin.php',
	'includes/render/render-functions.php',
	'includes/admin/role-manager.php',
	'includes/admin/settings.php',
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

// Add deactivation handler
function accessSchema_deactivate() {
	// Clear any scheduled events
	wp_clear_scheduled_hook( 'accessSchema_daily_cleanup' );

	// Flush rewrite rules
	flush_rewrite_rules();
}

// Add uninstall handler
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
