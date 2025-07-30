<?php
/**
 * File: includes/admin/settings.php
 * @version 2.0.3
 * Author: greghacke
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'accessSchema_add_settings_page');

function accessSchema_add_settings_page() {
    add_options_page(
        __('Access Schema Settings', 'accessschema'),
        __('Access Schema', 'accessschema'),
        'manage_options',
        'accessschema-settings',
        'accessSchema_render_settings_page'
    );
}

function accessSchema_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Access Schema Settings', 'accessschema'); ?></h1>
        
        <?php settings_errors('accessschema_settings'); ?>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('accessschema_settings');
            do_settings_sections('accessschema_settings');
            ?>
            
            <h2 class="title"><?php esc_html_e('API Settings', 'accessschema'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Read-Only API Key', 'accessschema'); ?></th>
                    <td>
                        <input type="text" name="accessSchema_api_key_readonly" id="api_key_ro" 
                               value="<?php echo esc_attr(get_option('accessSchema_api_key_readonly', '')); ?>" 
                               class="regular-text code" readonly />
                        <button type="button" class="button" onclick="generateApiKey('api_key_ro')"><?php esc_html_e('Generate New', 'accessschema'); ?></button>
                        <p class="description"><?php esc_html_e('Use this key for read-only API access', 'accessschema'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Read-Write API Key', 'accessschema'); ?></th>
                    <td>
                        <input type="text" name="accessSchema_api_key_readwrite" id="api_key_rw" 
                               value="<?php echo esc_attr(get_option('accessSchema_api_key_readwrite', '')); ?>" 
                               class="regular-text code" readonly />
                        <button type="button" class="button" onclick="generateApiKey('api_key_rw')"><?php esc_html_e('Generate New', 'accessschema'); ?></button>
                        <p class="description"><?php esc_html_e('Use this key for full API access', 'accessschema'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title"><?php esc_html_e('Logging Settings', 'accessschema'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Log Level', 'accessschema'); ?></th>
                    <td>
                        <select name="accessSchema_log_level">
                            <?php
                            $current_level = get_option('accessSchema_log_level', 'INFO');
                            $levels = array('DEBUG', 'INFO', 'WARN', 'ERROR');
                            foreach ($levels as $level) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr($level),
                                    selected($current_level, $level, false),
                                    esc_html($level)
                                );
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Minimum level of events to log', 'accessschema'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Audit Logging', 'accessschema'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="accessSchema_enable_audit" value="1" 
                                   <?php checked(get_option('accessSchema_enable_audit', true)); ?> />
                            <?php esc_html_e('Log all access checks and role changes', 'accessschema'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Audit Log Retention', 'accessschema'); ?></th>
                    <td>
                        <input type="number" name="accessSchema_audit_retention_days" 
                               value="<?php echo esc_attr(get_option('accessSchema_audit_retention_days', 90)); ?>" 
                               min="1" max="365" /> <?php esc_html_e('days', 'accessschema'); ?>
                        <p class="description"><?php esc_html_e('Automatically delete logs older than this', 'accessschema'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title"><?php esc_html_e('Performance Settings', 'accessschema'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Cache TTL', 'accessschema'); ?></th>
                    <td>
                        <input type="number" name="accessSchema_cache_ttl" 
                               value="<?php echo esc_attr(get_option('accessSchema_cache_ttl', 3600)); ?>" 
                               min="0" /> <?php esc_html_e('seconds', 'accessschema'); ?>
                        <p class="description"><?php esc_html_e('How long to cache role data (0 to disable)', 'accessschema'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Maximum Role Depth', 'accessschema'); ?></th>
                    <td>
                        <input type="number" name="accessSchema_max_depth" 
                               value="<?php echo esc_attr(get_option('accessSchema_max_depth', 10)); ?>" 
                               min="1" max="20" />
                        <p class="description"><?php esc_html_e('Maximum nesting level for roles', 'accessschema'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title"><?php esc_html_e('Advanced Settings', 'accessschema'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable REST API', 'accessschema'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="accessSchema_enable_rest_api" value="1" 
                                   <?php checked(get_option('accessSchema_enable_rest_api', true)); ?> />
                            <?php esc_html_e('Allow external API access', 'accessschema'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Remove Data on Uninstall', 'accessschema'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="accessSchema_remove_data_on_uninstall" value="1" 
                                   <?php checked(get_option('accessSchema_remove_data_on_uninstall', false)); ?> />
                            <?php esc_html_e('Delete all plugin data when uninstalling', 'accessschema'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Warning: This cannot be undone!', 'accessschema'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <hr />
        
        <h2><?php esc_html_e('Maintenance', 'accessschema'); ?></h2>
        <p>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=accessschema-settings&action=clear_cache'), 'clear_cache')); ?>" 
               class="button"><?php esc_html_e('Clear All Caches', 'accessschema'); ?></a>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=accessschema-settings&action=export_settings'), 'export_settings')); ?>" 
               class="button"><?php esc_html_e('Export Settings', 'accessschema'); ?></a>
        </p>
        
        <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
            <hr />
            <h2><?php esc_html_e('Debug Information', 'accessschema'); ?></h2>
            <?php accessSchema_show_debug_info(); ?>
        <?php endif; ?>
    </div>
    
    <script>
    function generateApiKey(fieldId) {
        const field = document.getElementById(fieldId);
        const key = 'as_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        field.value = key;
        field.removeAttribute('readonly');
    }
    </script>
    <?php
}

// Register settings
add_action('admin_init', 'accessSchema_register_settings');

function accessSchema_register_settings() {
    $settings = array(
        'accessSchema_api_key_readonly',
        'accessSchema_api_key_readwrite',
        'accessSchema_log_level',
        'accessSchema_enable_audit',
        'accessSchema_audit_retention_days',
        'accessSchema_cache_ttl',
        'accessSchema_max_depth',
        'accessSchema_enable_rest_api',
        'accessSchema_remove_data_on_uninstall'
    );
    
    foreach ($settings as $setting) {
        register_setting('accessschema_settings', $setting, 'accessSchema_sanitize_setting');
    }
}

function accessSchema_sanitize_setting($value) {
    if (is_numeric($value)) {
        return absint($value);
    }
    return sanitize_text_field($value);
}

// Handle actions
add_action('admin_init', 'accessSchema_handle_settings_actions');

function accessSchema_handle_settings_actions() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'accessschema-settings') {
        return;
    }
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'clear_cache':
                if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'clear_cache')) {
                    accessSchema_clear_all_caches();
                    add_settings_error('accessschema_settings', 'cache_cleared', 
                        __('All caches cleared successfully.', 'accessschema'), 'success');
                    wp_safe_redirect(admin_url('options-general.php?page=accessschema-settings'));
                    exit;
                }
                break;
                
            case 'export_settings':
                if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'export_settings')) {
                    accessSchema_export_settings();
                    exit;
                }
                break;
        }
    }
}

function accessSchema_export_settings() {
    $settings = array();
    $option_names = array(
        'accessSchema_log_level',
        'accessSchema_enable_audit',
        'accessSchema_audit_retention_days',
        'accessSchema_cache_ttl',
        'accessSchema_max_depth',
        'accessSchema_enable_rest_api'
    );
    
    foreach ($option_names as $option) {
        $settings[$option] = get_option($option);
    }
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="accessschema-settings-' . date('Y-m-d') . '.json"');
    echo wp_json_encode($settings, JSON_PRETTY_PRINT);
}

function accessSchema_show_debug_info() {
    global $wpdb;
    
    $cache_stats = accessSchema_get_cache_stats();
    $role_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}accessSchema_roles");
    $user_role_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}accessSchema_user_roles");
    $log_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}accessSchema_audit_log");
    
    ?>
    <table class="widefat">
        <tr>
            <th><?php esc_html_e('Total Roles', 'accessschema'); ?></th>
            <td><?php echo esc_html($role_count); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('User-Role Assignments', 'accessschema'); ?></th>
            <td><?php echo esc_html($user_role_count); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Audit Log Entries', 'accessschema'); ?></th>
            <td><?php echo esc_html($log_count); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Cache Hit Rate', 'accessschema'); ?></th>
            <td><?php echo esc_html($cache_stats['ratio'] . '%'); ?></td>
        </tr>
    </table>
    <?php
}