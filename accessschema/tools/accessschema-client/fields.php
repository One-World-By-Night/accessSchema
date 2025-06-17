<?php
// File: accessschema-client/fields.php
// @version 1.6.0
// @tool accessschema-client

defined('ABSPATH') || exit;

add_action('admin_init', function () {
    $registered = apply_filters('accessschema_registered_slugs', []);
    if (is_wp_error($registered) || empty($registered)) return;

    // Register one global group for all slugs
    foreach ($registered as $slug => $label) {
        $mode_key = "{$slug}_accessschema_mode";
        $url_key  = "{$slug}_accessschema_client_url";
        $key_key  = "{$slug}_accessschema_client_key";

        // Register all three options under one settings group
        register_setting('accessschema_client', $mode_key);
        register_setting('accessschema_client', $url_key, ['sanitize_callback' => 'esc_url_raw']);
        register_setting('accessschema_client', $key_key, ['sanitize_callback' => 'sanitize_text_field']);

        // === Section: Mode ===
        add_settings_section(
            "{$slug}_accessschema_mode_section",
            "{$label} – AccessSchema Mode",
            '__return_null',
            'accessschema-client'
        );

        add_settings_field(
            $mode_key,
            'Connection Mode',
            function () use ($mode_key) {
                $mode = get_option($mode_key, 'remote');
                ?>
                <label><input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="remote" <?php checked($mode, 'remote'); ?> /> Remote</label><br>
                <label><input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="local" <?php checked($mode, 'local'); ?> /> Local</label>
                <?php
            },
            'accessschema-client',
            "{$slug}_accessschema_mode_section"
        );

        // === Section: Remote API ===
        add_settings_section(
            "{$slug}_accessschema_remote_section",
            "{$label} – Remote API Settings",
            '__return_null',
            'accessschema-client'
        );

        add_settings_field(
            $url_key,
            'Remote AccessSchema URL',
            function () use ($mode_key, $url_key) {
                $mode = get_option($mode_key, 'remote');
                $val  = esc_url(get_option($url_key));
                $style = $mode === 'remote' ? '' : 'style="display:none;"';

                echo "<div $style><input type='url' name='" . esc_attr($url_key) . "' value='" . esc_attr($val) . "' class='regular-text' /></div>";
            },
            'accessschema-client',
            "{$slug}_accessschema_remote_section"
        );

        add_settings_field(
            $key_key,
            'Remote API Key',
            function () use ($mode_key, $key_key) {
                $mode = get_option($mode_key, 'remote');
                $val  = get_option($key_key);
                $style = $mode === 'remote' ? '' : 'style="display:none;"';

                echo "<div $style><input type='text' name='" . esc_attr($key_key) . "' value='" . esc_attr($val) . "' class='regular-text' /></div>";
            },
            'accessschema-client',
            "{$slug}_accessschema_remote_section"
        );
    }
});