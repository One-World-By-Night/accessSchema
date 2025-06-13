<?php

// File: accessschema-client/fields.php
// @version 1.5.0
// @tool accessschema-client

defined('ABSPATH') || exit;

add_action('admin_init', function () {

    // Register mode setting (remote/local)
    register_setting('accessschema_client', 'accessschema_mode');

    add_settings_section(
        'accessschema_mode_section',
        'AccessSchema Client Mode',
        '__return_null',
        'accessschema-client'
    );

    add_settings_field(
        'accessschema_mode',
        'Connection Mode',
        function () {
            $mode = get_option('accessschema_mode', 'remote');
            ?>
            <label>
                <input type="radio" name="accessschema_mode" value="remote" <?php checked($mode, 'remote'); ?> />
                Remote
            </label><br>
            <label>
                <input type="radio" name="accessschema_mode" value="local" <?php checked($mode, 'local'); ?> />
                Local
            </label>
            <?php
        },
        'accessschema-client',
        'accessschema_mode_section'
    );

    // Only register these if mode is "remote"
    if (get_option('accessschema_mode', 'remote') === 'remote') {

        register_setting(
            'accessschema_client',
            'accessschema_client_url',
            [
                'sanitize_callback' => 'esc_url_raw',
            ]
        );

        register_setting(
            'accessschema_client',
            'accessschema_client_key',
            [
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        add_settings_section(
            'accessschema_client_section',
            'Remote API Settings',
            '__return_null',
            'accessschema-client'
        );

        add_settings_field(
            'accessschema_client_url',
            'Remote AccessSchema URL',
            function () {
                $val = esc_url(get_option('accessschema_client_url'));
                echo "<input type='url' name='accessschema_client_url' value='" . esc_attr($val) . "' class='regular-text' />";
            },
            'accessschema-client',
            'accessschema_client_section'
        );

        add_settings_field(
            'accessschema_client_key',
            'Remote API Key',
            function () {
                $val = get_option('accessschema_client_key');
                echo "<input type='text' name='accessschema_client_key' value='" . esc_attr($val) . "' class='regular-text' />";
            },
            'accessschema-client',
            'accessschema_client_section'
        );
    }
});