<?php

// File: accessschema-client/admin-ui.php
// @version 1.3.0
// @author greghacke
// @tool accessschema-client

defined( 'ABSPATH' ) || exit;

add_action('admin_menu', function () {
    add_users_page(
        'AccessSchema Settings',
        'AS Your Plugin',        // <-- Change this per plugin context
        'manage_options',
        'accessschema-client',
        'accessSchema_client_render_admin_page'
    );
});