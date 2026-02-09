<?php

/** File: includes/admin/settings.php
 * Text Domain: accessschema-client
 * version 1.2.0
 *
 * @author greghacke
 * Function: Define admin settings page for AccessSchema client
 */

defined( 'ABSPATH' ) || exit;

/**  Add a custom admin menu page for AccessSchema client settings.
 * This function adds a new page under the Users menu in the WordPress admin dashboard.
 * It uses the 'manage_options' capability to restrict access to administrators.
 */
add_action(
	'admin_menu',
	function () {
		if ( ! defined( 'ASC_PREFIX' ) || ! defined( 'ASC_LABEL' ) ) {
			return;
		}

		$client_id = strtolower( ASC_PREFIX ) . '_client';
		$prefix    = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', ASC_PREFIX ) );
		$label     = ASC_LABEL;

		add_users_page(
			$label . ' accessSchema Client Settings',   // Page title
			$prefix . ' ASC',                           // Menu label
			'manage_options',                           // Capability
			$client_id,                                      // Slug
			'accessSchema_render_admin_page'            // Callback
		);
	}
);

/** Renders the AccessSchema admin settings page.
 * This function displays the settings form, handles form submissions,
 * and provides a manual cache clear option for user roles.
 */
if ( ! function_exists( 'accessSchema_render_admin_page' ) ) {
	function accessSchema_render_admin_page() {
		if ( ! defined( 'ASC_PREFIX' ) || ! defined( 'ASC_LABEL' ) ) {
			return;
		}

		$client_id = strtolower( ASC_PREFIX ) . '_client';        // Group
		$page      = $client_id . '_settings';                       // Page
		$label     = ASC_LABEL;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $label . ' Client Settings' ) . '</h1>';

		echo '<form method="post" action="options.php">';
		settings_fields( $client_id );      // ← Group name used in register_setting()
		do_settings_sections( $page ); // ← Page name used in add_settings_section/field
		submit_button();
		echo '</form>';

		echo '<hr>';
		echo '<h2>Manual Role Cache Clear</h2>';
		echo '<form method="post">';
		wp_nonce_field( "{$client_id}_clear_roles", "{$client_id}_nonce" );
		echo '<input type="email" name="clear_email_' . esc_attr( $client_id ) . '" placeholder="User email" required class="regular-text" />';
		echo '<button type="submit" class="button button-secondary">Clear Cached Roles</button>';
		echo '</form>';

		if (
			isset( $_POST[ "clear_email_{$client_id}" ], $_POST[ "{$client_id}_nonce" ] ) &&
			current_user_can( 'manage_options' ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ "{$client_id}_nonce" ] ) ), "{$client_id}_clear_roles" )
		) {
			$email = sanitize_email( wp_unslash( $_POST[ "clear_email_{$client_id}" ] ) );
			if ( empty( $email ) ) {
				echo '<p><strong>Please enter a valid email address.</strong></p>';
			} else {
				$user      = get_user_by( 'email', $email );
				$roles_key = "{$client_id}_accessschema_cached_roles";
				$time_key  = "{$client_id}_accessschema_cached_roles_timestamp";

				if ( $user ) {
					delete_user_meta( $user->ID, $roles_key );
					delete_user_meta( $user->ID, $time_key );
					echo '<p><strong>Cache cleared for ' . esc_html( $user->user_email ) . '</strong></p>';
				} else {
					echo '<p><strong>No user found with that email.</strong></p>';
				}
			}
		}

		echo '</div>';
	}
}
