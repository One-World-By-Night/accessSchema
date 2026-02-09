<?php

/** File: includes/init.php
 * Text Domain: accessschema-client
 * version 1.2.0
 *
 * @author greghacke
 * Function:  Porvide a single entry point to load all plugin components in standard and class-based structure
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ASC_PREFIX' ) ) {
	error_log( '[AS] ERROR: ASC_PREFIX not defined.' );
	return;
}

// === Build Slug and Label ===
$client_id = strtolower( str_replace( '_', '-', ASC_PREFIX ) );         // e.g., 'OWBNBOARD' => 'owbnboard'
$label     = ucwords( strtolower( str_replace( '_', ' ', ASC_PREFIX ) ) ); // e.g., 'OWBNBOARD' => 'Owbnboard'

// === Register ===
function accessSchema_register_client_plugin( $client_id, $label ) {
	add_filter(
		'accessschema_registered_slugs',
		function ( $client_ids ) use ( $client_id, $label ) {
			if ( ! isset( $client_ids[ $client_id ] ) ) {
				$client_ids[ $client_id ] = $label;
			}
			return $client_ids;
		}
	);

	add_filter(
		'accessschema_client_refresh_roles',
		function ( $result, $user, $filter_slug ) use ( $client_id ) {
			if ( ! is_string( $filter_slug ) ) {
				error_log( '[AS] WARN: Non-string slug encountered in refresh_roles: ' . print_r( $filter_slug, true ) );
				return $result;
			}
			if ( $client_id !== $filter_slug ) {
				return $result;
			}
			return accessSchema_refresh_roles_for_user( $user, $client_id );
		},
		10,
		3
	);
}

accessSchema_register_client_plugin( $client_id, $label );

if ( function_exists( 'accessSchema_client_register_render_admin' ) ) {
	accessSchema_client_register_render_admin( $client_id, $label );
}
