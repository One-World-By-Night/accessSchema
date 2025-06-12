<?php
// File: includes/shortcode/access.php
// @version 1.4.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

function accessSchema_shortcode_access($atts, $content = null) {
    if (!is_user_logged_in()) return '';

    $user_id = get_current_user_id();

    $atts = shortcode_atts([
        'role'     => '',       // Single role path (exact or pattern)
        'any'      => '',       // Comma-separated list of paths/patterns
        'children' => 'false',  // true/false for subtree check on role
        'wildcard' => 'false',  // true/false for wildcard/glob mode
        'fallback' => '',       // Optional fallback if user doesn't match
    ], $atts, 'access_schema');

    $children = filter_var($atts['children'], FILTER_VALIDATE_BOOLEAN);
    $wildcard = filter_var($atts['wildcard'], FILTER_VALIDATE_BOOLEAN);

    // If `any` is used, split it and match against patterns
    if (!empty($atts['any'])) {
        $patterns = array_map('trim', explode(',', $atts['any']));
        if (accessSchema_user_matches_any($user_id, $patterns)) {
            return do_shortcode($content);
        }
        return $atts['fallback'] ?? '';
    }

    // Else, use single `role` param
    $role = trim($atts['role']);
    if (!$role) return '';

    if ($wildcard) {
        if (accessSchema_user_matches_role_pattern($user_id, $role)) {
            return do_shortcode($content);
        }
    } else {
        if (accessSchema_check_permission($user_id, $role, $children, false)) {
            return do_shortcode($content);
        }
    }

    return $atts['fallback'] ?? '';
}
add_shortcode('access_schema', 'accessSchema_shortcode_access');