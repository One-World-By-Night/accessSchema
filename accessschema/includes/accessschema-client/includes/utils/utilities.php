<?php

/** File: includes/utils/utilities.php
 * Text Domain: accessschema-client
 * version 1.2.0
 * @author greghacke
 * Function: Utility functions for AccessSchema client plugin
 */

defined('ABSPATH') || exit;


if (!function_exists('accessSchema_client_access_granted')) {
    /**
     * Check if the current user has access to any of the provided role patterns, scoped by slug.
     *
     * @param string[]|string $patterns Role patterns to match.
     * @param string $client_id Unique plugin slug.
     * @return bool
     */
    function accessSchema_client_access_granted($patterns, $client_id)
    {
        if (!is_user_logged_in() || empty($client_id)) {
            return apply_filters('accessSchema_client_access_granted', false, $patterns, 0, $client_id);
        }

        $user = wp_get_current_user();
        $user_id = $user->ID;

        if (is_string($patterns)) {
            $patterns = array_map('trim', explode(',', $patterns));
        }

        if (empty($patterns)) {
            return apply_filters('accessSchema_client_access_granted', false, [], $user_id, $client_id);
        }

        $result = accessSchema_client_remote_user_matches_any($user->user_email, $patterns, $client_id);
        return apply_filters('accessSchema_client_access_granted', $result, $patterns, $user_id, $client_id);
    }
}

if (!function_exists('accessSchema_client_access_denied')) {
    /**
     * Inverse of access_granted — true if denied.
     *
     * @param string[]|string $patterns
     * @param string $client_id
     * @return bool
     */
    function accessSchema_client_access_denied($patterns, $client_id)
    {
        return !accessSchema_client_access_granted($patterns, $client_id);
    }
}

if (!function_exists('accessSchema_client_remote_user_matches_any')) {
    /**
     * Checks if the user's roles (retrieved remotely) match any of the provided patterns.
     *
     * @param string $email
     * @param string[] $patterns
     * @param string $client_id
     * @return bool
     */
    function accessSchema_client_remote_user_matches_any($email, array $patterns, $client_id)
    {
        $response = accessSchema_client_remote_get_roles_by_email($email, $client_id);

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
    function accessSchema_client_roles_match_pattern(array $roles, $pattern)
    {
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
    function accessSchema_client_pattern_to_regex($pattern)
    {
        $escaped = preg_quote($pattern, '#');
        $regex = str_replace(['\*\*', '\*'], ['.*', '[^/]+'], $escaped);
        return "#^{$regex}$#i";  // Add 'i' flag
    }
}

if (!function_exists('accessSchema_client_roles_match_pattern_from_email')) {
    /**
     * Shortcut: Check if a user (by email) has a role matching a pattern, using the slug.
     *
     * @param string $email
     * @param string $pattern
     * @param string $client_id
     * @return bool
     */
    function accessSchema_client_roles_match_pattern_from_email($email, $pattern, $client_id)
    {
        $response = accessSchema_client_remote_get_roles_by_email($email, $client_id);

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

if (!function_exists('accessSchema_access_granted')) {
    /**
     * Check if current user has access to any of the provided role patterns.
     * Wrapper for accessSchema_client_access_granted() that auto-detects client_id.
     *
     * @param string[]|string $patterns Role patterns to match (comma-separated string or array).
     * @return bool
     */
    function accessSchema_access_granted($patterns)
    {
        if (!defined('ASC_PREFIX')) {
            return false;
        }

        $client_id = strtolower(str_replace('_', '-', ASC_PREFIX));
        return accessSchema_client_access_granted($patterns, $client_id);
    }
}
