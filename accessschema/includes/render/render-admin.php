<?php
// File: includes/render/render-admin.php
// @version 1.7.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

/* Retrieves all available roles from the database, specifically those that are leaf nodes
 * (i.e., roles that do not have any child roles).
 *
 * @return array An array of role full paths.
 */
function accessSchema_get_available_roles() {
    global $wpdb;
    $table = $wpdb->prefix . 'access_roles';

    // Cache key for persistent storage
    $cache_key = 'accessSchema_leaf_roles';
    $cached = wp_cache_get( $cache_key, 'accessSchema' );
    if ( $cached !== false ) {
        return $cached;
    }

    // Safely build the SQL using wpdb->prepare() and table name escaping
    $table_escaped = esc_sql( $table );

    $query = "
        SELECT r1.full_path 
        FROM {$table_escaped} r1
        LEFT JOIN {$table_escaped} r2 ON r2.parent_id = r1.id
        WHERE r2.id IS NULL
        ORDER BY r1.full_path
    ";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query has no user input or dynamic values beyond escaped table name
    $results = $wpdb->get_results( $query, ARRAY_A );

    $roles = array_map(
        fn( $row ) => $row['full_path'],
        $results
    );

    wp_cache_set( $cache_key, $roles, 'accessSchema' );

    return $roles;
}

/* Renders the user role management UI on the user profile page.
 *
 * @param WP_User $user The user object for whom roles are being managed.
 */
function accessSchema_render_user_role_ui( $user ) {
    if ( ! current_user_can( 'edit_user', $user->ID ) ) {
        return;
    }

    $assigned   = get_user_meta( $user->ID, 'accessSchema', true );
    $all_roles  = accessSchema_get_available_roles();
    $assigned = accessSchema_get_user_roles( $user->ID );

    // Enqueue styles and scripts
    $base = dirname( __DIR__, 2 ) . '/accessSchema.php';
    wp_enqueue_style( 'accessSchema-select2', plugins_url( '/assets/css/select2.min.css', $base ), [], '4.1.0' );
    wp_enqueue_style( 'accessSchema-style', plugins_url( '/assets/css/accessSchema.css', $base ), [], '1.0.1' );

    wp_enqueue_script( 'accessSchema-select2', plugins_url( '/assets/js/select2.min.js', $base ), [ 'jquery' ], '4.1.0', true );
    wp_enqueue_script( 'accessSchema-init', plugins_url( '/assets/js/accessSchema.js', $base ), [ 'jquery', 'accessSchema-select2' ], '1.0.1', true );

    accessSchema_render_user_role_select( $all_roles, $assigned );
}

/* Renders the user role selection UI, including assigned roles and a dropdown for adding new roles.
 *
 * @param array $all_roles An array of all available roles (full_path strings).
 * @param array $assigned An array of roles currently assigned to the user.
 */
function accessSchema_render_user_role_select( $all_roles, $assigned ) {
    ?>
    <h2>Access Schema Roles</h2>
    <?php wp_nonce_field( 'accessSchema_user_roles_action', 'accessSchema_user_roles_nonce' ); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th><label>Assigned Roles</label></th>
            <td>
                <div id="accessSchema-assigned-roles">
                    <?php if ( ! empty( $assigned ) ) : ?>
                        <?php foreach ( $assigned as $role ) : ?>
                            <span class="access-role-tag">
                                <?php echo esc_html( $role ); ?>
                                <button type="button" class="remove-role-button" data-role="<?php echo esc_attr( $role ); ?>">×</button>
                            </span>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p><em>No roles currently assigned.</em></p>
                    <?php endif; ?>
                </div>
                <!-- Hidden inputs added dynamically by JS -->
            </td>
        </tr>

        <tr>
            <th><label for="accessSchema_add_roles">Add Roles</label></th>
            <td>
                <select name="accessSchema_add_roles[]" id="accessSchema_add_roles" multiple="multiple" style="width: 400px;">
                    <?php foreach ( $all_roles as $role_path ) :
                        if ( ! in_array( $role_path, $assigned, true ) ) : ?>
                            <option value="<?php echo esc_attr( $role_path ); ?>">
                                <?php echo esc_html( $role_path ); ?>
                            </option>
                        <?php endif;
                    endforeach; ?>
                </select>
                <p class="description">Use the dropdown to add new roles. Assigned roles are managed above.</p>
            </td>
        </tr>
    </table>
    <?php
}

/* Handles saving accessSchema roles when a user profile is updated.
 *
 * @param int $user_id The ID of the user being updated.
 */
function accessSchema_user_profile_update( $user_id ) {
    if (
        ! current_user_can( 'edit_user', $user_id )
        || ! isset( $_POST['accessSchema_user_roles_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['accessSchema_user_roles_nonce'] ) ), 'accessSchema_user_roles_action' )
    ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'access_user_roles';
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Get current roles from junction table
        $current_roles = $wpdb->get_col($wpdb->prepare(
            "SELECT r.full_path FROM {$table} ur 
             JOIN {$wpdb->prefix}access_roles r ON ur.role_id = r.id 
             WHERE ur.user_id = %d",
            $user_id
        ));

        // Process additions and removals
        $add_roles = isset( $_POST['accessSchema_add_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['accessSchema_add_roles'] ) ) : [];
        $remove_roles = isset( $_POST['accessSchema_remove_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['accessSchema_remove_roles'] ) ) : [];

        // Add new roles using junction table
        foreach ( $add_roles as $role_path ) {
            if ( ! in_array( $role_path, $current_roles, true ) ) {
                accessSchema_add_role_optimized( $user_id, $role_path );
            }
        }

        // Remove roles
        foreach ( $remove_roles as $role_path ) {
            if ( in_array( $role_path, $current_roles, true ) ) {
                accessSchema_remove_role_optimized( $user_id, $role_path );
            }
        }
        
        $wpdb->query('COMMIT');
        
        // Clear caches
        wp_cache_delete( 'user_roles_' . $user_id, 'accessSchema' );
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('AccessSchema role update failed: ' . $e->getMessage());
    }
}

// Register hooks for user profile display and update
add_action( 'show_user_profile', 'accessSchema_render_user_role_ui' );
add_action( 'edit_user_profile', 'accessSchema_render_user_role_ui' );
add_action( 'personal_options_update', 'accessSchema_user_profile_update' );
add_action( 'edit_user_profile_update', 'accessSchema_user_profile_update' );