<?php
// File: includes/core/user-roles.php
// @version 1.6.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

/* Add a role path to a user’s accessSchema meta with validation.
 *
 * @param int       $user_id      The user to assign the role to.
 * @param string    $role_path    The role path to add (e.g. 'Chronicles/MCKN/HST').
 * @param int|null  $performed_by Optional — who performed the action.
 *
 * @return bool True if added, false if rejected or duplicate.
 */
function accessSchema_add_role( $user_id, $role_path, $performed_by = null ) {
    $role_path = trim( $role_path );

    // Validate that the role is actually registered in the DB
    if ( ! accessSchema_role_exists( $role_path ) ) {
        accessSchema_log_event( $user_id, 'role_add_invalid', $role_path, [
            'reason' => 'role_not_registered'
        ], $performed_by, 'ERROR' );
        return false;
    }

    // Retrieve existing roles assigned to this user
    $roles = get_user_meta( $user_id, 'accessSchema', true );
    if ( ! is_array( $roles ) ) {
        $roles = [];
    }

    // Prevent duplicate role assignment
    if ( in_array( $role_path, $roles, true ) ) {
        accessSchema_log_event( $user_id, 'role_add_skipped', $role_path, [
            'reason' => 'already_assigned'
        ], $performed_by, 'DEBUG' );
        return false;
    }

    // Custom rule hook — can be overridden to prevent conflicting roles
    if ( ! accessSchema_validate_role_assignment( $user_id, $role_path, $roles ) ) {
        accessSchema_log_event( $user_id, 'role_add_blocked', $role_path, [
            'reason' => 'validation_failed',
            'existing_roles' => $roles,
        ], $performed_by, 'WARN' );
        return false;
    }

    // Add role and persist to usermeta
    $roles[] = $role_path;
    update_user_meta( $user_id, 'accessSchema', $roles );

    accessSchema_log_event( $user_id, 'role_added', $role_path, null, $performed_by, 'INFO' );
    return true;
}

/* Remove a role path from a user’s accessSchema meta.
 *
 * @param int       $user_id      The user to remove the role from.
 * @param string    $role_path    The role path to remove (e.g. 'Chronicles/MCKN/HST').
 * @param int|null  $performed_by Optional — who performed the action.
 *
 * @return bool True if removed, false if not found or invalid.
 */
function accessSchema_remove_role( $user_id, $role_path, $performed_by = null ) {
    $role_path = trim( $role_path );

    // Validate that the role is officially registered
    if ( ! accessSchema_role_exists( $role_path ) ) {
        accessSchema_log_event( $user_id, 'role_remove_invalid', $role_path, [
            'reason' => 'role_not_registered'
        ], $performed_by, 'ERROR' );
        return false;
    }

    $roles = get_user_meta( $user_id, 'accessSchema', true );
    if ( ! is_array( $roles ) ) {
        $roles = [];
    }

    // If user doesn't actually have this role
    if ( ! in_array( $role_path, $roles, true ) ) {
        accessSchema_log_event( $user_id, 'role_remove_skipped', $role_path, [
            'reason' => 'not_assigned'
        ], $performed_by, 'DEBUG' );
        return false;
    }

    // Remove the role and update user meta
    $updated_roles = array_values( array_diff( $roles, [ $role_path ] ) );
    update_user_meta( $user_id, 'accessSchema', $updated_roles );

    accessSchema_log_event( $user_id, 'role_removed', $role_path, null, $performed_by, 'INFO' );
    return true;
}

/*  Validate whether a role can be assigned to a user.
 *
 * Extend this to enforce custom constraints (e.g., only one CM role per user).
 *
 * @param int    $user_id        The user ID being assigned a role.
 * @param string $new_role       The role path being assigned (e.g., 'Chronicles/MCKN/CM').
 * @param array  $existing_roles An array of the user's current role paths.
 *
 * @return bool True if the role can be assigned; false otherwise.
 */
function accessSchema_validate_role_assignment( $user_id, $new_role, $existing_roles ) {
    // Example rule (disabled for now):
    // - Block assigning more than one CM role
    /*
    if ( preg_match( '#^Chronicles/[^/]+/CM$#', $new_role ) ) {
        foreach ( $existing_roles as $role ) {
            if ( preg_match( '#^Chronicles/[^/]+/CM$#', $role ) ) {
                return false;
            }
        }
    }
    */

    // Default: allow all
    return true;
}

/* Save user roles from the profile update.
 *
 * This function is called when a user profile is updated to save the accessSchema roles.
 * It compares the new roles with existing ones and updates accordingly.
 *
 * @param int   $user_id    The ID of the user being updated.
 * @param array $new_roles  The new roles to assign to the user.
 *
 * @return bool True on success, false on failure.
 */
function accessSchema_save_user_roles( $user_id, $new_roles ) {
    $existing_roles = get_user_meta( $user_id, 'accessSchema', true );
    if ( ! is_array( $existing_roles ) ) {
        $existing_roles = [];
    }

    $to_add = array_diff( $new_roles, $existing_roles );
    $to_remove = array_diff( $existing_roles, $new_roles );

    foreach ( $to_add as $role ) {
        accessSchema_add_role( $user_id, $role );
    }

    foreach ( $to_remove as $role ) {
        accessSchema_remove_role( $user_id, $role );
    }

    return true;
}

/** accessSchema_add_role_optimized
 * Add a role to a user using the junction table for better performance.
 */
function accessSchema_add_role_optimized( $user_id, $role_path, $performed_by = null ) {
    global $wpdb;
    
    // Get role ID
    $role_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}access_roles WHERE full_path = %s",
        $role_path
    ));
    
    if (!$role_id) {
        accessSchema_log_event( $user_id, 'role_add_invalid', $role_path, [
            'reason' => 'role_not_registered'
        ], $performed_by, 'ERROR' );
        return false;
    }
    
    // Insert into junction table
    $result = $wpdb->insert(
        $wpdb->prefix . 'access_user_roles',
        [
            'user_id' => $user_id,
            'role_id' => $role_id,
            'granted_by' => $performed_by ?? get_current_user_id()
        ],
        ['%d', '%d', '%d']
    );
    
    if ($result) {
        accessSchema_log_event( $user_id, 'role_added', $role_path, null, $performed_by, 'INFO' );
        wp_cache_delete( 'user_roles_' . $user_id, 'accessSchema' );
    }
    
    return $result !== false;
}

/** accessSchema_remove_role_optimized 
 * Remove a role from a user using the junction table for better performance.
 */
function accessSchema_remove_role_optimized( $user_id, $role_path, $performed_by = null ) {
    global $wpdb;
    
    // Get role ID
    $role_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}access_roles WHERE full_path = %s",
        $role_path
    ));
    
    if (!$role_id) {
        return false;
    }
    
    // Remove from junction table
    $result = $wpdb->delete(
        $wpdb->prefix . 'access_user_roles',
        [
            'user_id' => $user_id,
            'role_id' => $role_id
        ],
        ['%d', '%d']
    );
    
    if ($result) {
        accessSchema_log_event( $user_id, 'role_removed', $role_path, null, $performed_by, 'INFO' );
        wp_cache_delete( 'user_roles_' . $user_id, 'accessSchema' );
    }
    
    return $result !== false;
}

/** accessSchema_get_user_roles
 * Retrieve all roles assigned to a user using the junction table.
 */
function accessSchema_get_user_roles( $user_id, $use_cache = true ) {
    $cache_key = 'user_roles_' . $user_id;
    
    if ($use_cache) {
        $cached = wp_cache_get($cache_key, 'accessSchema');
        if ($cached !== false) {
            return $cached;
        }
    }
    
    global $wpdb;
    $roles = $wpdb->get_col($wpdb->prepare(
        "SELECT r.full_path 
         FROM {$wpdb->prefix}access_user_roles ur
         JOIN {$wpdb->prefix}access_roles r ON ur.role_id = r.id
         WHERE ur.user_id = %d
         ORDER BY r.full_path",
        $user_id
    ));
    
    wp_cache_set($cache_key, $roles, 'accessSchema', 3600); // 1 hour cache
    
    return $roles;
}