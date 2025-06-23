<?php
// File: includes/utils/access-utils.php
// @version 1.7.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

/**
 * Check if the current user is granted access via any of the provided patterns.
 * Always assumes the current user is logged in.
 *
 * @param string|string[] $patterns A single string or array of wildcard patterns.
 * @return bool True if user matches at least one pattern.
 */
function accessSchema_access_granted( $patterns ) {
    if ( ! is_user_logged_in() ) {
        /**
         * Allow external logic to override default access check behavior for non-logged-in users.
         */
        return apply_filters( 'accessSchema_access_granted', false, $patterns, 0 );
    }

    $user_id = get_current_user_id();

    // Normalize to array
    if ( is_string( $patterns ) ) {
        $patterns = array_map( 'trim', explode( ',', $patterns ) );
    }

    if ( ! is_array( $patterns ) || empty( $patterns ) ) {
        return apply_filters( 'accessSchema_access_granted', false, $patterns, $user_id );
    }

    $result = accessSchema_user_matches_any( $user_id, $patterns );

    /**
     * Filter the access result based on role path patterns.
     *
     * @param bool        $result   Whether the user is considered granted access.
     * @param string[]    $patterns The array of role patterns to test against.
     * @param int         $user_id  The user ID being checked.
     */
    return apply_filters( 'accessSchema_access_granted', $result, $patterns, $user_id );
}

/**
 * Optional wrapper: deny logic as inversion of access_granted
 *
 * @param string|string[] $patterns A single string or array of wildcard patterns.
 * @return bool True if user does *not* match any pattern.
 */
function accessSchema_access_denied( $patterns ) {
    return ! accessSchema_access_granted( $patterns );
}

/** accessSchema_can_manage_roles
 * Check if the current user can manage roles.
 */
function accessSchema_can_manage_roles($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    return user_can($user_id, 'manage_access_schema');
}

/** accessSchema_can_assign_roles
 * Check if the current user can assign roles, optionally to a specific target user.
 */
function accessSchema_can_assign_roles($user_id = null, $target_user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    
    // Can manage all roles
    if (user_can($user_id, 'manage_access_schema')) {
        return true;
    }
    
    // Can only assign to users they can edit
    if ($target_user_id && user_can($user_id, 'edit_user', $target_user_id)) {
        return user_can($user_id, 'assign_access_roles');
    }
    
    return false;
}