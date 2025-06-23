<?php
// File: includes/admin/settings.php
// @version 1.6.0
// @author greghacke

defined( 'ABSPATH' ) || exit;

// Add settings page
add_action('admin_menu', 'accessSchema_add_settings_page');
function accessSchema_add_settings_page() {
    add_options_page(
        'Access Schema Settings',
        'Access Schema',
        'manage_options',
        'accessschema-settings',
        'accessSchema_render_settings_page'
    );
}

// Render settings page
function accessSchema_render_settings_page() {
    if (isset($_POST['submit'])) {
        accessSchema_save_settings();
    }
    
    $api_key_ro = get_option('accessschema_api_key_readonly', '');
    $api_key_rw = get_option('accessschema_api_key_readwrite', '');
    $log_level = get_option('accessschema_log_level', 'INFO');
    $cache_ttl = get_option('accessschema_cache_ttl', 3600);
    ?>
    <div class="wrap">
        <h1>Access Schema Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('accessschema_settings', 'accessschema_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Read-Only API Key</th>
                    <td>
                        <input type="text" name="api_key_readonly" value="<?php echo esc_attr($api_key_ro); ?>" class="regular-text" />
                        <button type="button" class="button" onclick="generateApiKey('api_key_readonly')">Generate</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Read-Write API Key</th>
                    <td>
                        <input type="text" name="api_key_readwrite" value="<?php echo esc_attr($api_key_rw); ?>" class="regular-text" />
                        <button type="button" class="button" onclick="generateApiKey('api_key_readwrite')">Generate</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Log Level</th>
                    <td>
                        <select name="log_level">
                            <option value="DEBUG" <?php selected($log_level, 'DEBUG'); ?>>DEBUG</option>
                            <option value="INFO" <?php selected($log_level, 'INFO'); ?>>INFO</option>
                            <option value="WARN" <?php selected($log_level, 'WARN'); ?>>WARN</option>
                            <option value="ERROR" <?php selected($log_level, 'ERROR'); ?>>ERROR</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cache TTL (seconds)</th>
                    <td>
                        <input type="number" name="cache_ttl" value="<?php echo esc_attr($cache_ttl); ?>" min="0" />
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Save Settings" />
                <button type="button" class="button" onclick="clearCaches()">Clear All Caches</button>
            </p>
        </form>
        
        <script>
        function generateApiKey(fieldName) {
            const key = 'as_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            document.querySelector(`input[name="${fieldName}"]`).value = key;
        }
        
        function clearCaches() {
            if (confirm('Clear all accessSchema caches?')) {
                // Add AJAX call to clear caches
                window.location.href = '<?php echo wp_nonce_url(admin_url('options-general.php?page=accessschema-settings&action=clear_cache'), 'clear_cache'); ?>';
            }
        }
        </script>
    </div>
    <?php
}

// Save settings
function accessSchema_save_settings() {
    if (!wp_verify_nonce($_POST['accessschema_settings_nonce'], 'accessschema_settings')) {
        return;
    }
    
    update_option('accessschema_api_key_readonly', sanitize_text_field($_POST['api_key_readonly']));
    update_option('accessschema_api_key_readwrite', sanitize_text_field($_POST['api_key_readwrite']));
    update_option('accessschema_log_level', sanitize_text_field($_POST['log_level']));
    update_option('accessschema_cache_ttl', intval($_POST['cache_ttl']));
    
    add_settings_error('accessschema_settings', 'settings_saved', 'Settings saved successfully!', 'success');
}

// Handle cache clearing
add_action('admin_init', 'accessSchema_handle_cache_clear');
function accessSchema_handle_cache_clear() {
    if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && wp_verify_nonce($_GET['_wpnonce'], 'clear_cache')) {
        accessSchema_clear_all_caches();
        wp_redirect(add_query_arg('cache_cleared', '1', wp_get_referer()));
        exit;
    }
}