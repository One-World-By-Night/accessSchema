<?php
/**
 * File: includes/core/init.php
 *
 * @version 2.0.3
 * Author: greghacke
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initialize the Access Schema plugin.
 *
 * Loads the text domain for translations, fires the plugin initialization
 * action, and sets the initialization flag in the object cache.
 *
 * @since 1.0.0
 *
 * @return void
 */
function accessSchema_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'accessschema', false, dirname( plugin_basename( ACCESSSCHEMA_PLUGIN_FILE ) ) . '/languages' );

	/**
	 * Fires after the Access Schema plugin has been initialized.
	 *
	 * Use this action to register custom roles, add integration hooks,
	 * or perform setup tasks that depend on Access Schema being loaded.
	 *
	 * @since 1.0.0
	 */
	do_action( 'accessSchema_init' );

	// Initialize caching
	if ( ! wp_cache_get( 'accessSchema_initialized', 'accessSchema' ) ) {
		wp_cache_set( 'accessSchema_initialized', true, 'accessSchema', HOUR_IN_SECONDS );
	}
}
add_action( 'init', 'accessSchema_init' );

// Admin assets enqueue with enhanced security
add_action( 'admin_enqueue_scripts', 'accessSchema_enqueue_role_manager_assets' );

function accessSchema_enqueue_role_manager_assets( $hook ) {
	// Early return for non-admin or incorrect page
	if ( ! is_admin() || 'users_page_accessSchema-roles' !== $hook ) {
		return;
	}

	// Capability check
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Cache base URL
	static $base_url = null;
	if ( null === $base_url ) {
		$base_url = ACCESSSCHEMA_PLUGIN_URL . 'assets/';
	}

	// Asset versions for cache busting
	$asset_version = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : ACCESSSCHEMA_VERSION;

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
		array( 'accessSchema-select2' ),
		$asset_version
	);

	// Enqueue scripts
	wp_enqueue_script(
		'accessSchema-select2',
		$base_url . 'js/select2.min.js',
		array( 'jquery' ),
		'4.1.0',
		true
	);

	wp_enqueue_script(
		'accessSchema',
		$base_url . 'js/accessSchema.js',
		array( 'jquery', 'accessSchema-select2', 'wp-util' ),
		$asset_version,
		true
	);

	// Localize script with security nonces
	wp_localize_script(
		'accessSchema',
		'accessSchema_ajax',
		array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'accessSchema_ajax_nonce' ),
			'rest_url'   => esc_url_raw( rest_url( 'accessSchema/v1/' ) ),
			'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'       => array(
				'error'          => __( 'An error occurred. Please try again.', 'accessschema' ),
				'confirm_delete' => __( 'Are you sure you want to delete this?', 'accessschema' ),
				'saving'         => __( 'Saving...', 'accessschema' ),
				'saved'          => __( 'Saved successfully.', 'accessschema' ),
			),
			'debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
		)
	);

	// Add inline script for initialization
	wp_add_inline_script( 'accessSchema', 'jQuery(document).ready(function($) { if (window.accessSchema_init) { accessSchema_init(); } });' );
}

// Register AJAX handlers
add_action( 'wp_ajax_accessSchema_save_role', 'accessSchema_ajax_save_role' );
add_action( 'wp_ajax_accessSchema_delete_role', 'accessSchema_ajax_delete_role' );

function accessSchema_ajax_save_role() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'accessSchema_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'accessschema' ) ) );
	}

	if ( ! current_user_can( 'manage_access_schema' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'accessschema' ) ) );
	}

	$role_id   = absint( $_POST['role_id'] ?? 0 );
	$role_name = sanitize_text_field( wp_unslash( $_POST['role_name'] ?? '' ) );

	if ( ! $role_id || empty( $role_name ) ) {
		wp_send_json_error( array( 'message' => __( 'Role ID and name are required.', 'accessschema' ) ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, full_path FROM {$table} WHERE id = %d AND is_active = 1",
			$role_id
		)
	);

	if ( ! $existing ) {
		wp_send_json_error( array( 'message' => __( 'Role not found.', 'accessschema' ) ) );
	}

	$updated = $wpdb->update(
		$table,
		array(
			'name'       => $role_name,
			'slug'       => sanitize_title( $role_name ),
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $role_id ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		wp_send_json_error( array( 'message' => __( 'Database update failed.', 'accessschema' ) ) );
	}

	wp_cache_delete( 'all_roles', 'accessSchema' );
	wp_cache_flush_group( 'accessSchema' );

	if ( function_exists( 'accessSchema_log_event' ) ) {
		accessSchema_log_event(
			get_current_user_id(),
			'role_updated',
			$existing->full_path,
			array( 'new_name' => $role_name )
		);
	}

	do_action( 'accessSchema_save_role', $_POST );

	wp_send_json_success(
		array(
			'message' => __( 'Role updated successfully.', 'accessschema' ),
			'name'    => $role_name,
			'slug'    => sanitize_title( $role_name ),
		)
	);
}

function accessSchema_ajax_delete_role() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'accessSchema_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'accessschema' ) ) );
	}

	if ( ! current_user_can( 'manage_access_schema' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'accessschema' ) ) );
	}

	$role_id = absint( $_POST['role_id'] ?? 0 );
	$cascade = ! empty( $_POST['cascade'] );

	if ( ! $role_id ) {
		wp_send_json_error( array( 'message' => __( 'Role ID is required.', 'accessschema' ) ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, full_path FROM {$table} WHERE id = %d AND is_active = 1",
			$role_id
		)
	);

	if ( ! $existing ) {
		wp_send_json_error( array( 'message' => __( 'Role not found.', 'accessschema' ) ) );
	}

	$has_children = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE parent_id = %d AND is_active = 1 LIMIT 1",
			$role_id
		)
	);

	if ( $has_children && ! $cascade ) {
		wp_send_json_error(
			array(
				'message'      => __( 'This role has child roles. Confirm cascade to delete all.', 'accessschema' ),
				'has_children' => true,
			)
		);
	}

	if ( function_exists( 'accessSchema_delete_role' ) ) {
		$result = accessSchema_delete_role( $role_id, $cascade );
	} else {
		$result = $wpdb->update(
			$table,
			array( 'is_active' => 0 ),
			array( 'id' => $role_id ),
			array( '%d' ),
			array( '%d' )
		);

		$ur_table = $wpdb->prefix . 'accessSchema_user_roles';
		$wpdb->update(
			$ur_table,
			array( 'is_active' => 0 ),
			array( 'role_id' => $role_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	wp_cache_delete( 'all_roles', 'accessSchema' );
	wp_cache_flush_group( 'accessSchema' );

	if ( function_exists( 'accessSchema_log_event' ) ) {
		accessSchema_log_event(
			get_current_user_id(),
			'role_deleted',
			$existing->full_path,
			array( 'cascade' => $cascade )
		);
	}

	do_action( 'accessSchema_delete_role', $_POST );

	wp_send_json_success(
		array(
			'message' => __( 'Role deleted successfully.', 'accessschema' ),
		)
	);
}

// Schedule cleanup tasks
add_action( 'init', 'accessSchema_schedule_events' );

function accessSchema_schedule_events() {
	if ( ! wp_next_scheduled( 'accessSchema_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'accessSchema_daily_cleanup' );
	}
}

add_action( 'accessSchema_daily_cleanup', 'accessSchema_perform_cleanup' );

function accessSchema_perform_cleanup() {
	// Clean expired cache
	wp_cache_flush_group( 'accessSchema' );

	// Clean old audit logs (keep 90 days)
	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_audit_log';
	if ( $table === $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				date( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
			)
		);
	}

	// Log cleanup completion
	error_log( 'accessSchema: Daily cleanup completed at ' . current_time( 'mysql' ) );
}
