<?php

/** File: includes/admin/users.php
 * Text Domain: accessschema-client
 * version 2.0.4
 *
 * @author greghacke
 * Function: Define admin users page for AccessSchema client
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add a single column to the Users table for all AccessSchema-client instances.
 */
add_filter(
	'manage_users_columns',
	function ( $columns ) {
		$columns['accessschema_roles'] = 'AccessSchema Roles';
		return $columns;
	}
);

/**
 * Populate the AccessSchema Roles column with multiple plugin slugs.
 */
add_filter(
	'manage_users_custom_column',
	function ( $output, $column_name, $user_id ) {
		if ( 'accessschema_roles' !== $column_name ) {
			return $output;
		}

		$registered = apply_filters( 'accessschema_registered_slugs', array() );
		if ( is_wp_error( $registered ) || empty( $registered ) ) {
			return '[No AccessSchema Instances Registered]';
		}

		$base_url = admin_url( 'users.php' );
		$output   = '<div class="accessschema-role-column">';

		foreach ( $registered as $client_id => $label ) {
			$cache_key     = "{$client_id}_accessschema_cached_roles";
			$timestamp_key = "{$client_id}_accessschema_cached_roles_timestamp";

			$roles     = get_user_meta( $user_id, $cache_key, true );
			$timestamp = get_user_meta( $user_id, $timestamp_key, true );

			$flush_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'flush_accessschema_cache',
						'user_id' => $user_id,
						'slug'    => $client_id,
					),
					$base_url
				),
				"flush_accessschema_{$user_id}_{$client_id}"
			);

			$refresh_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'refresh_accessschema_cache',
						'user_id' => $user_id,
						'slug'    => $client_id,
					),
					$base_url
				),
				"refresh_accessschema_{$user_id}_{$client_id}"
			);

			$output .= '<div><strong>' . esc_html( $label ) . ' ASC:</strong> ';

			if ( ! is_array( $roles ) || empty( $roles ) ) {
				$output .= '[None] <a href="' . esc_url( $refresh_url ) . '">[Request]</a>';
			} else {
				$time_display = $timestamp
				? date_i18n( 'm/d/Y h:i a', intval( $timestamp ) )
				: '[Unknown]';

				$output .= esc_html( $time_display )
				. ' <a href="' . esc_url( $flush_url ) . '">[Flush]</a>'
				. ' <a href="' . esc_url( $refresh_url ) . '">[Refresh]</a>';
			}

			$output .= '</div>';
		}

		$output .= '</div>';
		return $output;
	},
	10,
	3
);

/**
 * Handle flush and refresh actions scoped per-plugin instance.
 */
add_action(
	'admin_init',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified via check_admin_referer in each action branch below
		if (
		isset( $_GET['action'], $_GET['user_id'], $_GET['slug'] ) &&
		current_user_can( 'manage_options' )
		) {
			$user_id   = intval( wp_unslash( $_GET['user_id'] ) );
			$action    = sanitize_key( wp_unslash( $_GET['action'] ) );
			$client_id = sanitize_key( wp_unslash( $_GET['slug'] ) );

			$registered = apply_filters( 'accessschema_registered_slugs', array() );
			if ( ! isset( $registered[ $client_id ] ) ) {
				return;
			}

			$cache_key     = "{$client_id}_accessschema_cached_roles";
			$timestamp_key = "{$client_id}_accessschema_cached_roles_timestamp";

			if ( 'flush_accessschema_cache' === $action ) {
				check_admin_referer( "flush_accessschema_{$user_id}_{$client_id}" );

				delete_user_meta( $user_id, $cache_key );
				delete_user_meta( $user_id, $timestamp_key );

				wp_safe_redirect( add_query_arg( array( 'message' => 'accessschema_cache_flushed' ), admin_url( 'users.php' ) ) );
				exit;
			}

			if ( 'refresh_accessschema_cache' === $action ) {
				check_admin_referer( "refresh_accessschema_{$user_id}_{$client_id}" );

				$user = get_user_by( 'ID', $user_id );
				if ( $user ) {
					$result = apply_filters( 'accessschema_client_refresh_roles', null, $user, $client_id );

					if ( is_array( $result ) && isset( $result['roles'] ) ) {
						update_user_meta( $user_id, $cache_key, $result['roles'] );
						update_user_meta( $user_id, $timestamp_key, time() );

						wp_safe_redirect( add_query_arg( array( 'message' => 'accessschema_cache_refreshed' ), admin_url( 'users.php' ) ) );
						exit;
					}
				}

				wp_safe_redirect( add_query_arg( array( 'message' => 'accessschema_cache_failed' ), admin_url( 'users.php' ) ) );
				exit;
			}
		}
	}
);

/**
 * Show admin notices after flush or refresh.
 */
add_action(
	'admin_notices',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of admin notice based on query parameter
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of admin notice based on query parameter
		$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		switch ( $message ) {
			case 'accessschema_cache_flushed':
				$notice = 'AccessSchema role cache flushed.';
				break;
			case 'accessschema_cache_refreshed':
				$notice = 'AccessSchema role cache refreshed.';
				break;
			case 'accessschema_cache_failed':
				$notice = 'Failed to refresh AccessSchema roles. Check plugin hook or API response.';
				break;
			default:
				$notice = '';
		}

		if ( $notice ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}
	}
);
