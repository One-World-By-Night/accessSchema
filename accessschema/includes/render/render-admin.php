<?php
/**
 * File: includes/render/render-admin.php
 *
 * @version 2.0.3
 * Author: greghacke
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get all available roles with hierarchy
 */
function accessSchema_get_available_roles() {
	$cache_key = 'accessSchema_all_roles_hierarchy';
	$cached    = wp_cache_get( $cache_key, 'accessSchema' );

	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_roles';

	$results = $wpdb->get_results(
		"SELECT id, parent_id, name, full_path, depth 
         FROM {$table} 
         WHERE is_active = 1 
         ORDER BY full_path",
		ARRAY_A
	);

	if ( empty( $results ) ) {
		return array();
	}

	// Build hierarchy for display
	$hierarchy = array();
	$flat_list = array();

	foreach ( $results as $role ) {
		$flat_list[] = $role['full_path'];

		// Add indentation for hierarchy display
		$role['display_name']            = str_repeat( '— ', $role['depth'] ) . $role['name'];
		$hierarchy[ $role['full_path'] ] = $role;
	}

	$result = array(
		'flat'      => $flat_list,
		'hierarchy' => $hierarchy,
	);

	wp_cache_set( $cache_key, $result, 'accessSchema', 3600 );

	return $result;
}

/**
 * Render user role management UI
 */
function accessSchema_render_user_role_ui( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) || ! current_user_can( 'assign_access_roles' ) ) {
		return;
	}

	$all_roles = accessSchema_get_available_roles();
	$assigned  = accessSchema_get_user_roles( $user->ID );

	// Filter to only show leaf roles (deepest in hierarchy)
	$leaf_roles = array();
	foreach ( $assigned as $role ) {
		$is_leaf = true;
		foreach ( $assigned as $other_role ) {
			if ( $other_role !== $role && 0 === strpos( $other_role, $role . '/' ) ) {
				$is_leaf = false;
				break;
			}
		}
		if ( $is_leaf ) {
			$leaf_roles[] = $role;
		}
	}
	$assigned = $leaf_roles;

	// Enqueue assets
	$base_url = ACCESSSCHEMA_PLUGIN_URL . 'assets/';

	wp_enqueue_style( 'accessSchema-select2', $base_url . 'css/select2.min.css', array(), '4.1.0' );
	wp_enqueue_style( 'accessSchema-style', $base_url . 'css/accessSchema.css', array(), ACCESSSCHEMA_VERSION );

	wp_enqueue_script( 'accessSchema-select2', $base_url . 'js/select2.min.js', array( 'jquery' ), '4.1.0', true );
	wp_enqueue_script( 'accessSchema-admin', $base_url . 'js/accessSchema.js', array( 'jquery', 'accessSchema-select2' ), ACCESSSCHEMA_VERSION, true );

	// Localize script
	wp_localize_script(
		'accessSchema-admin',
		'accessSchema_admin',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'accessSchema_admin_nonce' ),
			'i18n'     => array(
				'confirm_remove' => __( 'Remove this role?', 'accessschema' ),
				'error'          => __( 'An error occurred', 'accessschema' ),
				'no_roles'       => __( 'No roles assigned', 'accessschema' ),
			),
		)
	);

	accessSchema_render_user_role_select( $all_roles, $assigned, $user->ID );
}

/**
 * Render role selection UI
 */
function accessSchema_render_user_role_select( $all_roles, $assigned, $user_id ) {
	?>
	<h2><?php esc_html_e( 'Access Schema Roles', 'accessschema' ); ?></h2>
	<?php wp_nonce_field( 'accessSchema_user_roles_action', 'accessSchema_user_roles_nonce' ); ?>
	
	<table class="form-table" role="presentation">
		<tr>
			<th><label><?php esc_html_e( 'Assigned Roles', 'accessschema' ); ?></label></th>
			<td>
				<div id="accessSchema-assigned-roles" data-user-id="<?php echo esc_attr( $user_id ); ?>">
					<?php if ( ! empty( $assigned ) ) : ?>
						<?php foreach ( $assigned as $role ) : ?>
							<?php
							$role_info = isset( $all_roles['hierarchy'][ $role ] ) ? $all_roles['hierarchy'][ $role ] : null;
							// Show full path for clarity
							$display_name = $role;
							?>
							<span class="access-role-tag" data-role="<?php echo esc_attr( $role ); ?>">
								<span class="role-name" title="<?php echo esc_attr( $role_info ? $role_info['name'] : $role ); ?>">
									<?php echo esc_html( $display_name ); ?>
								</span>
								<button type="button" class="remove-role-button" aria-label="<?php esc_attr_e( 'Remove role', 'accessschema' ); ?>">
									<span aria-hidden="true">×</span>
								</button>
								<input type="hidden" name="accessSchema_current_roles[]" value="<?php echo esc_attr( $role ); ?>">
							</span>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="no-roles-message"><em><?php esc_html_e( 'No roles currently assigned.', 'accessschema' ); ?></em></p>
					<?php endif; ?>
				</div>
				
				<div id="accessSchema-role-errors" class="notice notice-error" style="display:none;"></div>
			</td>
		</tr>
		
		<tr>
			<th><label for="accessSchema_add_roles"><?php esc_html_e( 'Add Roles', 'accessschema' ); ?></label></th>
			<td>
				<select name="accessSchema_add_roles[]" id="accessSchema_add_roles" multiple="multiple" style="width: 100%; max-width: 600px;">
					<?php
					foreach ( $all_roles['hierarchy'] as $path => $role ) :
						if ( ! in_array( $path, $assigned, true ) ) :
							?>
						<option value="<?php echo esc_attr( $path ); ?>" data-depth="<?php echo esc_attr( $role['depth'] ); ?>">
							<?php echo esc_html( $role['display_name'] ); ?>
						</option>
							<?php
						endif;
					endforeach;
					?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Select roles to add. Roles are organized hierarchically.', 'accessschema' ); ?>
				</p>
			</td>
		</tr>
		
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<tr>
			<th><?php esc_html_e( 'Role Information', 'accessschema' ); ?></th>
			<td>
				<details>
					<summary><?php esc_html_e( 'View role hierarchy', 'accessschema' ); ?></summary>
					<div class="accessSchema-role-tree">
						<?php accessSchema_render_role_tree(); ?>
					</div>
				</details>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	
	<input type="hidden" id="accessSchema_removed_roles" name="accessSchema_removed_roles" value="">
	<?php
}

/**
 * Render role tree for reference
 */
function accessSchema_render_role_tree() {
	$tree = accessSchema_get_role_tree();

	echo '<ul class="role-tree">';
	foreach ( $tree as $node ) {
		accessSchema_render_tree_node( $node );
	}
	echo '</ul>';
}

/**
 * Render individual tree node
 */
function accessSchema_render_tree_node( $node ) {
	echo '<li>';
	echo '<span class="role-node" data-path="' . esc_attr( $node['full_path'] ) . '">';
	echo esc_html( $node['name'] );
	echo '</span>';

	if ( ! empty( $node['children'] ) ) {
		echo '<ul>';
		foreach ( $node['children'] as $child ) {
			accessSchema_render_tree_node( $child );
		}
		echo '</ul>';
	}

	echo '</li>';
}

/**
 * Handle user profile update
 */
function accessSchema_user_profile_update( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ||
		! current_user_can( 'assign_access_roles' ) ||
		! isset( $_POST['accessSchema_user_roles_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['accessSchema_user_roles_nonce'] ) ), 'accessSchema_user_roles_action' )
	) {
		return;
	}

	// Get current roles
	$current_roles = accessSchema_get_user_roles( $user_id );

	// Get submitted roles
	$submitted_roles = isset( $_POST['accessSchema_current_roles'] ) ?
		array_map( 'sanitize_text_field', wp_unslash( $_POST['accessSchema_current_roles'] ) ) :
		array();

	// Get added roles
	$add_roles = isset( $_POST['accessSchema_add_roles'] ) ?
		array_map( 'sanitize_text_field', wp_unslash( $_POST['accessSchema_add_roles'] ) ) :
		array();

	// Get removed roles from hidden field
	$removed_roles = isset( $_POST['accessSchema_removed_roles'] ) ?
		array_filter( explode( ',', sanitize_text_field( wp_unslash( $_POST['accessSchema_removed_roles'] ) ) ) ) :
		array();

	// Calculate final roles
	$final_roles = array_unique(
		array_merge(
			array_diff( $submitted_roles, $removed_roles ),
			$add_roles
		)
	);

	// Update roles
	$result = accessSchema_save_user_roles( $user_id, $final_roles, get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		add_action(
			'admin_notices',
			function () use ( $result ) {
				echo '<div class="notice notice-error"><p>';
				echo esc_html( $result->get_error_message() );
				echo '</p></div>';
			}
		);
	}
}

/**
 * Display user's assigned roles in profile (read-only)
 */
function accessSchema_display_user_roles( $user ) {
	// Get assigned roles
	$roles = accessSchema_get_user_roles( $user->ID );

	if ( empty( $roles ) ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Access Schema', 'accessschema' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Assigned Roles', 'accessschema' ); ?></th>
			<td>
				<div class="accessSchema-role-display">
					<?php foreach ( $roles as $role ) : ?>
						<div class="accessSchema-role-path"><?php echo esc_html( $role ); ?></div>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
	</table>
	<?php
}

// Register hooks
add_action( 'show_user_profile', 'accessSchema_display_user_roles', 5 );
add_action( 'edit_user_profile', 'accessSchema_display_user_roles', 5 );
add_action( 'show_user_profile', 'accessSchema_render_user_role_ui' );
add_action( 'edit_user_profile', 'accessSchema_render_user_role_ui' );
add_action( 'personal_options_update', 'accessSchema_user_profile_update' );
add_action( 'edit_user_profile_update', 'accessSchema_user_profile_update' );

// AJAX handlers
add_action( 'wp_ajax_accessSchema_validate_role', 'accessSchema_ajax_validate_role' );

function accessSchema_ajax_validate_role() {
	check_ajax_referer( 'accessSchema_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'assign_access_roles' ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}

	$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$role_path = isset( $_POST['role_path'] ) ? sanitize_text_field( wp_unslash( $_POST['role_path'] ) ) : '';

	if ( ! $user_id || ! $role_path ) {
		wp_send_json_error( 'Invalid parameters' );
	}

	$current_roles = accessSchema_get_user_roles( $user_id );
	$is_valid      = accessSchema_validate_role_assignment( $user_id, $role_path, $current_roles );

	if ( $is_valid ) {
		wp_send_json_success();
	} else {
		wp_send_json_error( __( 'This role cannot be assigned due to conflicts or restrictions.', 'accessschema' ) );
	}
}