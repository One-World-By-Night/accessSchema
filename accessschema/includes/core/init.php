<?php
// File: includes/core/init.php
// @version 1.2.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

// Plugin init logic
function accessSchema_init() {
    // Init hooks if needed later
}
add_action( 'init', 'accessSchema_init' );

add_action( 'admin_enqueue_scripts', 'accessSchema_enqueue_role_manager_assets' );

function accessSchema_enqueue_role_manager_assets( $hook ) {
    if ( $hook !== 'users_page_accessSchema-roles' ) {
        return;
    }

    $base_url = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/';

    wp_enqueue_style(
        'accessSchema-select2',
        $base_url . 'css/select2.min.css',
        [],
        '4.1.0'
    );

    wp_enqueue_style(
        'accessSchema-style',
        $base_url . 'css/accessSchema.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'accessSchema-select2',
        $base_url . 'js/select2.min.js',
        [ 'jquery' ],
        '4.1.0',
        true
    );

    wp_enqueue_script(
        'accessSchema',
        $base_url . 'js/accessSchema.js',
        [ 'jquery', 'accessSchema-select2' ],
        '1.0.0',
        true
    );
}