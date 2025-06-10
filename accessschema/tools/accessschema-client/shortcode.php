<?php
// File: accessschema-client/shortcode.php
// @version 1.2.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

/**
 * [access_schema] shortcode for client-side remote validation.
 *
 * @param array $atts
 * @param string|null $content
 * @return string
 */
function accessSchema_client_shortcode_access($atts, $content = null) {
    if (!is_user_logged_in()) return '';

    $user = wp_get_current_user();

    $atts = shortcode_atts([
        'role'     => '',       // Single role path (exact or pattern)
        'any'      => '',       // Comma-separated list of paths/patterns
        'wildcard' => 'false',  // true/false for wildcard/glob mode
        'fallback' => '',       // Optional fallback if user doesn't match
    ], $atts, 'access_schema');

    $wildcard = filter_var($atts['wildcard'], FILTER_VALIDATE_BOOLEAN);

    // If `any` is used, split it and match against patterns
    if (!empty($atts['any'])) {
        $patterns = array_map('trim', explode(',', $atts['any']));
        if (accessSchema_remote_user_matches_any($user->user_email, $patterns)) {
            return do_shortcode($content);
        }
        return $atts['fallback'] ?? '';
    }

    $role = trim($atts['role']);
    if (!$role) return '';

    if ($wildcard) {
        if (accessSchema_roles_match_pattern_from_email($user->user_email, $role)) {
            return do_shortcode($content);
        }
    } else {
        // Use the remote 'check' API for exact match
        $granted = accessSchema_client_remote_check_access($user->user_email, $role, false);
        if (!is_wp_error($granted) && $granted) {
            return do_shortcode($content);
        }
    }

    return $atts['fallback'] ?? '';
}
add_shortcode('access_schema_client', 'accessSchema_client_shortcode_access');