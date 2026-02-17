<?php
/**
 * File: includes/core/helpers.php
 *
 * @version 2.0.4
 * Author: greghacke
 */

defined( 'ABSPATH' ) || exit;

// Define helper files to load
$helper_files = array(
	'logging.php',
	'role-tree.php',
	'user-roles.php',
	'permission-checks.php',
	'cache.php',
);

// Load helper files with error handling
foreach ( $helper_files as $file ) {
	$file_path = ACCESSSCHEMA_PLUGIN_DIR . 'includes/core/' . $file;

	if ( ! file_exists( $file_path ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'accessSchema: Missing helper file: ' . $file );
		}
		continue;
	}

	require_once $file_path;
}

// Initialize helper systems
add_action( 'init', 'accessSchema_init_helpers', 5 );

function accessSchema_init_helpers() {
	// Initialize cache system
	if ( function_exists( 'accessSchema_init_cache' ) ) {
		accessSchema_init_cache();
	}

	// Register cleanup hooks
	add_action( 'accessSchema_daily_cleanup', 'accessSchema_cleanup_helpers' );
}

function accessSchema_cleanup_helpers() {
	// Clean expired cache entries
	if ( function_exists( 'accessSchema_cleanup_cache' ) ) {
		accessSchema_cleanup_cache();
	}

	// Clean old logs
	if ( function_exists( 'accessSchema_cleanup_logs' ) ) {
		accessSchema_cleanup_logs();
	}
}
