<?php
// File: accessschema-client/utils.php
// @version 1.6.0
// @tool accessschema-client

defined('ABSPATH') || exit;

if (!function_exists('accessSchema_client_access_granted')) {
    /**
     * Check if the current user has access to any of the provided role patterns, scoped by slug.
     *
     * @param string[]|string $patterns Role patterns to match.
     * @param string $slug Unique plugin slug.
     * @return bool
     */
    function accessSchema_client_access_granted($patterns, $slug) {
        if (!is_user_logged_in() || empty($slug)) {
            return apply_filters('accessSchema_client_access_granted', false, $patterns, 0, $slug);
        }

        $user = wp_get_current_user();
        $user_id = $user->ID;

        if (is_string($patterns)) {
            $patterns = array_map('trim', explode(',', $patterns));
        }

        if (empty($patterns)) {
            return apply_filters('accessSchema_client_access_granted', false, [], $user_id, $slug);
        }

        $result = accessSchema_client_remote_user_matches_any($user->user_email, $patterns, $slug);
        return apply_filters('accessSchema_client_access_granted', $result, $patterns, $user_id, $slug);
    }
}

if (!function_exists('accessSchema_client_access_denied')) {
    /**
     * Inverse of access_granted — true if denied.
     *
     * @param string[]|string $patterns
     * @param string $slug
     * @return bool
     */
    function accessSchema_client_access_denied($patterns, $slug) {
        return !accessSchema_client_access_granted($patterns, $slug);
    }
}

if (!function_exists('accessSchema_client_remote_user_matches_any')) {
    /**
     * Checks if the user's roles (retrieved remotely) match any of the provided patterns.
     *
     * @param string $email
     * @param string[] $patterns
     * @param string $slug
     * @return bool
     */
    function accessSchema_client_remote_user_matches_any($email, array $patterns, $slug) {
        $response = apply_filters('accessschema_get_roles_for_slug', null, $email, $slug);

        if (
            is_wp_error($response) ||
            !is_array($response) ||
            !isset($response['roles']) ||
            !is_array($response['roles'])
        ) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (accessSchema_client_roles_match_pattern($response['roles'], $pattern)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('accessSchema_client_roles_match_pattern')) {
    /**
     * Determines if any of the roles match the given wildcard pattern.
     *
     * @param string[] $roles
     * @param string $pattern
     * @return bool
     */
    function accessSchema_client_roles_match_pattern(array $roles, $pattern) {
        $regex = accessSchema_client_pattern_to_regex($pattern);
        foreach ($roles as $role) {
            if (preg_match($regex, $role)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('accessSchema_client_pattern_to_regex')) {
    /**
     * Converts a wildcard pattern (with * and **) into a regex.
     *
     * @param string $pattern
     * @return string
     */
    function accessSchema_client_pattern_to_regex($pattern) {
        $escaped = preg_quote($pattern, '#');
        $regex = str_replace(['\*\*', '\*'], ['.*', '[^/]+'], $escaped);
        return "#^{$regex}$#";
    }
}

if (!function_exists('accessSchema_client_roles_match_pattern_from_email')) {
    /**
     * Shortcut: Check if a user (by email) has a role matching a pattern, using the slug.
     *
     * @param string $email
     * @param string $pattern
     * @param string $slug
     * @return bool
     */
    function accessSchema_client_roles_match_pattern_from_email($email, $pattern, $slug) {
        $response = apply_filters('accessschema_get_roles_for_slug', null, $email, $slug);

        if (
            is_wp_error($response) ||
            !is_array($response) ||
            !isset($response['roles']) ||
            !is_array($response['roles'])
        ) {
            return false;
        }

        return accessSchema_client_roles_match_pattern($response['roles'], $pattern);
    }
}