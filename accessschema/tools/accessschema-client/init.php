<?php
// File: accessschema-client/init.php
// @version 1.6.0
// Author: greghacke
// @tool accessschema-client

if (!defined('ABSPATH')) exit;

// First: include all required components before any usage
require_once __DIR__ . '/admin-ui.php';
require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/render-admin.php';
require_once __DIR__ . '/render-ui.php';
require_once __DIR__ . '/shortcode.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/webhook.php';

// Then: register plugin integration
function accessSchema_register_client_plugin($slug, $label) {
    add_filter('accessschema_registered_slugs', function ($slugs) use ($slug, $label) {
        if (!isset($slugs[$slug])) {
            $slugs[$slug] = $label;
        }
        return $slugs;
    });

    add_filter('accessschema_client_refresh_roles', function ($result, $user, $filter_slug) use ($slug) {
        if (!is_string($filter_slug)) {
            error_log("[AS] WARN: Non-string slug encountered in refresh_roles: " . print_r($filter_slug, true));
            return $result;
        }
        if ($filter_slug !== $slug) return $result;
        return accessSchema_refresh_roles_for_user($user, $slug);
    }, 10, 3);
}

$slug = 'owbn_board';
$label = 'OWBN Board';

accessSchema_register_client_plugin($slug, $label);

if (function_exists('accessSchema_client_register_render_admin')) {
    accessSchema_client_register_render_admin($slug, $label);
}