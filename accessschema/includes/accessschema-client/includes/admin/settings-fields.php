<?php

defined('ABSPATH') || exit;

if (!function_exists('asc_sanitize_capability_map')) {
    /**
     * Sanitize capability map array from settings form.
     * Converts textarea input (newline-separated paths) into arrays of sanitized paths.
     *
     * @param array $input Raw input from form.
     * @return array Sanitized capability map.
     */
    function asc_sanitize_capability_map($input)
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];

        foreach ($input as $cap => $paths_raw) {
            $cap = sanitize_key($cap);
            if (empty($cap)) {
                continue;
            }

            // Handle textarea input (newline-separated)
            if (is_string($paths_raw)) {
                $paths = array_filter(array_map('trim', explode("\n", $paths_raw)));
            } elseif (is_array($paths_raw)) {
                $paths = array_filter(array_map('trim', $paths_raw));
            } else {
                continue;
            }

            // Sanitize each path
            $paths = array_map('sanitize_text_field', $paths);
            $paths = array_filter($paths); // Remove empty

            if (!empty($paths)) {
                $sanitized[$cap] = array_values($paths);
            }
        }

        return $sanitized;
    }
}

add_action('admin_init', function () {
    $registered = apply_filters('accessschema_registered_slugs', []);
    if (is_wp_error($registered) || empty($registered)) return;

    foreach ($registered as $client_id => $label) {
        $group = "{$client_id}_client";              // settings_fields()
        $page  = "{$client_id}_client_settings";     // do_settings_sections()

        $mode_key = "{$client_id}_accessschema_mode";
        $url_key  = "{$client_id}_accessschema_client_url";
        $key_key  = "{$client_id}_accessschema_client_key";
        $cap_key  = "{$client_id}_capability_map";

        // Register settings
        register_setting($group, $mode_key);
        register_setting($group, $url_key, ['sanitize_callback' => 'esc_url_raw']);
        register_setting($group, $key_key, ['sanitize_callback' => 'sanitize_text_field']);
        register_setting($group, $cap_key, ['sanitize_callback' => 'asc_sanitize_capability_map']);

        // === Mode Section ===
        add_settings_section(
            "{$client_id}_accessschema_mode_section",
            "{$label} – AccessSchema Mode",
            '__return_null',
            $page
        );

        add_settings_field(
            $mode_key,
            'Connection Mode',
            function () use ($mode_key) {
                $mode = get_option($mode_key, 'remote');
?>
            <label><input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="remote" <?php checked($mode, 'remote'); ?> /> Remote</label><br>
            <label><input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="local" <?php checked($mode, 'local'); ?> /> Local</label><br>
            <label><input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="none" <?php checked($mode, 'none'); ?> /> None – use WP Permissions</label>
<?php
            },
            $page,
            "{$client_id}_accessschema_mode_section"
        );

        // === Remote API Config Section ===
        add_settings_section(
            "{$client_id}_accessschema_remote_section",
            "{$label} – Remote API Settings",
            '__return_null',
            $page
        );

        add_settings_field(
            $url_key,
            'Remote AccessSchema URL',
            function () use ($mode_key, $url_key) {
                $mode  = get_option($mode_key, 'remote');
                $value = esc_url(get_option($url_key));
                $style = $mode === 'remote' ? '' : 'style="display:none;"';
                echo "<div $style><input type='url' name='" . esc_attr($url_key) . "' value='" . esc_attr($value) . "' class='regular-text' /></div>";
            },
            $page,
            "{$client_id}_accessschema_remote_section"
        );

        add_settings_field(
            $key_key,
            'Remote API Key',
            function () use ($mode_key, $key_key) {
                $mode  = get_option($mode_key, 'remote');
                $value = get_option($key_key);
                $style = $mode === 'remote' ? '' : 'style="display:none;"';
                echo "<div $style><input type='text' name='" . esc_attr($key_key) . "' value='" . esc_attr($value) . "' class='regular-text' /></div>";
            },
            $page,
            "{$client_id}_accessschema_remote_section"
        );

        // === Capability Map Section ===
        add_settings_section(
            "{$client_id}_accessschema_cap_map_section",
            "{$label} – Capability to Role Mapping",
            function () {
                echo '<p>Enter AccessSchema role paths per capability. One path per line.<br />';
                echo 'Supports <code>$slug</code>, <code>**</code>, and wildcards.</p>';
            },
            $page
        );

        // Define core capabilities to support
        $capabilities = [
            'edit_posts',
            'edit_others_posts',
            'edit_published_posts',
            'publish_posts',
            'delete_posts',
            'delete_others_posts',
            'delete_published_posts',
            'read_private_posts',
            'upload_files',
        ];

        $existing_map = get_option($cap_key, []);
        foreach ($capabilities as $cap) {
            add_settings_field(
                "{$cap_key}_{$cap}",
                $cap,
                function () use ($cap, $cap_key, $existing_map, $mode_key) {
                    $submitted_mode = $_POST[$mode_key] ?? null;
                    $mode = $submitted_mode ?: get_option($mode_key, 'remote');
                    $style = ($mode === 'none') ? 'style="display:none;"' : '';

                    $text = isset($existing_map[$cap]) && is_array($existing_map[$cap])
                        ? esc_textarea(implode("\n", $existing_map[$cap]))
                        : '';

                    echo "<div $style>";
                    echo "<textarea name='" . esc_attr("{$cap_key}[{$cap}]") . "' rows='3' class='large-text code'>{$text}</textarea>";
                    echo "</div>";
                },
                $page,
                "{$client_id}_accessschema_cap_map_section"
            );
        }
    }
});
