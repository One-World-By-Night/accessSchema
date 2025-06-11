<?php
// File: includes/utils/access-utils.php
// @version 1.2.1
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