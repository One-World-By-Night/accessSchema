<?php
// File: includes/core/permission-checks.php
// @version 1.1.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

/* Check if a user has a given role or any of its children (if recursive).
 *
 * @param int       $user_id         The user ID being checked.
 * @param string    $target_path     The target role path (e.g. 'Chronicles/KONY').
 * @param bool      $include_children Whether to include subpaths in the check.
 * @param bool      $log             Whether to log the result of this check (default: true).
 * @param string[]  $context         Optional logging context (e.g. route, IP).
 *
 * @return bool True if match found, false otherwise.
 */
function accessSchema_check_permission( $user_id, $target_path, $include_children = false, $log = true, $context = [], $allow_wildcards = false ) {
    $target_path = trim( $target_path );

    $roles = get_user_meta( $user_id, 'accessSchema', true );
    if ( ! is_array( $roles ) ) {
        $roles = [];
    }

    $matched = false;

    // Wildcard pattern check
    if ( $allow_wildcards && ( strpos( $target_path, '*' ) !== false ) ) {
        $regex = accessSchema_pattern_to_regex( $target_path );
        foreach ( $roles as $role ) {
            if ( preg_match( $regex, $role ) ) {
                $matched = true;
                break;
            }
        }
    } else {
        // Optional: confirm it's a known role
        if ( ! accessSchema_role_exists( $target_path ) ) {
            if ( $log ) {
                accessSchema_log_event( $user_id, 'role_check_invalid', $target_path, [
                    'reason' => 'role_not_registered',
                ], null, 'ERROR' );
            }
            return false;
        }

        foreach ( $roles as $role ) {
            if ( $role === $target_path ) {
                $matched = true;
                break;
            }
            if ( $include_children && str_starts_with( $role, $target_path . '/' ) ) {
                $matched = true;
                break;
            }
        }
    }

    if ( $log ) {
        $context = array_merge( [
            'route' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            'ip'    => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
        ], $context );

        accessSchema_log_event(
            $user_id,
            $matched ? 'access_granted' : 'access_denied',
            $target_path,
            $context,
            null,
            $matched ? 'INFO' : 'WARN'
        );
    }

    return $matched;
}

/* Check to see if a user can access a specific role path.
 * This is a convenience function that defaults to not including children and logging the result.
 *
 * @param int    $user_id     The user ID being checked.
 * @param string $pattern     The role path pattern to check (e.g. 'Chronicles/KONY').
 *
 * @return bool True if the user can access the role, false otherwise.
 */
function accessSchema_user_can( $user_id, $pattern ) {
    return accessSchema_check_permission( $user_id, $pattern, false, false, [], true );
}

/* Check if a user matches a wildcard pattern.
 * Supports `*` (match one segment) and `**` (match any number of segments).
 */
function accessSchema_user_matches_role_pattern( $user_id, $pattern ) {
    $roles = get_user_meta( $user_id, 'accessSchema', true );
    if ( ! is_array( $roles ) || empty( $roles ) ) return false;

    $regex = accessSchema_pattern_to_regex( $pattern );

    foreach ( $roles as $role ) {
        if ( preg_match( $regex, $role ) ) {
            return true;
        }
    }

    return false;
}

/* Check if a user matches ANY of multiple patterns.
 */
function accessSchema_user_matches_any( $user_id, array $patterns ) {
    foreach ( $patterns as $pattern ) {
        if ( accessSchema_user_matches_role_pattern( $user_id, $pattern ) ) {
            return true;
        }
    }
    return false;
}

/* Convert wildcard path pattern to a regular expression.
 * - `*` matches a single segment (no slash)
 * - `**` matches zero or more segments (including slashes)
 */
function accessSchema_pattern_to_regex( $pattern ) {
    // Escape regex characters
    $escaped = preg_quote( $pattern, '#' );

    // Replace wildcards
    $regex = str_replace(
        ['\*\*', '\*'],
        ['.*', '[^/]+'],
        $escaped
    );

    // Ensure full path match
    return "#^{$regex}$#";
}