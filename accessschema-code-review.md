### 13. **assets/js/settings.js** (New File)
**Create settings JavaScript file:**
```javascript
/**
 * AccessSchema Settings JavaScript
 * @version 1.7.0
 */
(function($) {
    'use strict';
    
    // Generate secure API key
    window.generateApiKey = function(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        // Generate cryptographically secure random key
        const randomBytes = new Uint8Array(16);
        crypto.getRandomValues(randomBytes);
        
        const hexString = Array.from(randomBytes)
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
        
        field.value = 'as_' + hexString;
        
        // Mark field as changed
        $(field).trigger('change');
    };
    
})(jQuery);
```

### 14. **Unit Tests Structure**
**Create tests/test-accessschema.php:**
```php
<?php
/**
 * AccessSchema Unit Tests
 */
class Test_AccessSchema extends WP_UnitTestCase {
    
    public function setUp() {
        parent::setUp();
        
        // Activate plugin
        activate_plugin('accessschema/accessSchema.php');
        
        // Create test data
        $this->test_user = $this->factory->user->create([
            'user_login' => 'test_user',
            'user_email' => 'test@example.com'
        ]);
        
        // Register test roles
        accessSchema_register_path(['Chronicles', 'KONY', 'HST']);
        accessSchema_register_path(['Chronicles', 'KONY', 'Player']);
        accessSchema_register_path(['Coordinators', 'Brujah', 'Lead']);
    }
    
    public function tearDown() {
        parent::tearDown();
        
        // Clean up test data
        wp_delete_user($this->test_user);
        
        // Clear caches
        accessSchema_clear_all_caches();
    }
    
    /**
     * Test role registration
     */
    public function test_role_registration() {
        $this->assertTrue(accessSchema_role_exists('Chronicles/KONY/HST'));
        $this->assertTrue(accessSchema_role_exists('Coordinators/Brujah/Lead'));
        $this->assertFalse(accessSchema_role_exists('Invalid/Role/Path'));
    }
    
    /**
     * Test role assignment
     */
    public function test_role_assignment() {
        $result = accessSchema_add_role($this->test_user, 'Chronicles/KONY/HST');
        $this->assertTrue($result);
        
        $roles = accessSchema_get_user_roles($this->test_user);
        $this->assertContains('Chronicles/KONY/HST', $roles);
        
        // Test duplicate assignment
        $result = accessSchema_add_role($this->test_user, 'Chronicles/KONY/HST');
        $this->assertFalse($result);
    }
    
    /**
     * Test permission checking
     */
    public function test_permission_checking() {
        accessSchema_add_role($this->test_user, 'Chronicles/KONY/HST');
        
        // Test exact match
        $this->assertTrue(accessSchema_check_permission($this->test_user, 'Chronicles/KONY/HST', false, false));
        
        // Test parent match with children
        $this->assertTrue(accessSchema_check_permission($this->test_user, 'Chronicles/KONY', true, false));
        
        // Test wildcard
        $this->assertTrue(accessSchema_check_permission($this->test_user, 'Chronicles/*/HST', false, false, [], true));
        
        // Test no match
        $this->assertFalse(accessSchema_check_permission($this->test_user, 'Coordinators/Brujah/Lead', false, false));
    }
    
    /**
     * Test caching
     */
    public function test_caching() {
        // Add role
        accessSchema_add_role($this->test_user, 'Chronicles/KONY/Player');
        
        // First call should hit database
        $roles1 = accessSchema_get_user_roles($this->test_user);
        
        // Second call should hit cache
        $roles2 = accessSchema_get_user_roles($this->test_user);
        
        $this->assertEquals($roles1, $roles2);
        
        // Test cache invalidation
        accessSchema_add_role($this->test_user, 'Coordinators/Brujah/Lead');
        $roles3 = accessSchema_get_user_roles($this->test_user);
        
        $this->assertCount(count($roles1) + 1, $roles3);
    }
    
    /**
     * Test shortcode
     */
    public function test_shortcode() {
        wp_set_current_user($this->test_user);
        accessSchema_add_role($this->test_user, 'Chronicles/KONY/HST');
        
        // Test access granted
        $output = do_shortcode('[access_schema role="Chronicles/KONY/HST"]Secret Content[/access_schema]');
        $this->assertEquals('Secret Content', $output);
        
        // Test access denied
        $output = do_shortcode('[access_schema role="Coordinators/Brujah/Lead"]Secret Content[/access_schema]');
        $this->assertEquals('', $output);
        
        // Test with fallback
        $output = do_shortcode('[access_schema role="Invalid/Role" fallback="No Access"]Secret Content[/access_schema]');
        $this->assertEquals('No Access', $output);
    }
    
    /**
     * Test API authentication
     */
    public function test_api_authentication() {
        // Set test API key
        update_option('accessschema_api_key_readonly', 'as_12345678901234567890123456789012');
        
        $request = new WP_REST_Request('GET', '/access-schema/v1/roles');
        $request->set_header('x-api-key', 'as_12345678901234567890123456789012');
        
        $auth = accessSchema_api_authenticate($request);
        $this->assertNotFalse($auth);
        $this->assertEquals('read', $auth->type);
    }
    
    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        update_option('accessschema_rate_limit_requests', 3);
        update_option('accessschema_rate_limit_window', 60);
        
        $request = new WP_REST_Request('GET', '/test');
        
        // First 3 requests should pass
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue(accessSchema_check_rate_limit($request));
        }
        
        // 4th request should fail
        $this->assertFalse(accessSchema_check_rate_limit($request));
    }
    
    /**
     * Test logging
     */
    public function test_logging() {
        global $wpdb;
        
        // Test log event
        $result = accessSchema_log_event(
            $this->test_user,
            'test_action',
            'Test/Role/Path',
            ['test' => 'data'],
            null,
            'INFO'
        );
        
        $this->assertTrue($result);
        
        // Verify log entry
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}access_audit_log WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $this->test_user
        ));
        
        $this->assertEquals('test_action', $log->action);
        $this->assertEquals('Test/Role/Path', $log->role_path);
    }
}
```

### 15. **Additional Security Headers**
**Create includes/security/headers.php:**
```php
<?php
defined('ABSPATH') || exit;

/**
 * Add security headers for AccessSchema endpoints
 */
add_action('send_headers', function() {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Feature Policy
        header("Feature-Policy: geolocation 'none'; microphone 'none'; camera 'none'");
    }
});

/**
 * Add CORS headers for API
 */
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();
        
        if ($origin) {
            // Check if origin is allowed
            $allowed_origins = apply_filters('accessschema_allowed_origins', [
                home_url(),
                'https://api.owbn.net'
            ]);
            
            if (in_array($origin, $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
                header('Access-Control-Allow-Credentials: true');
            }
        }
        
        return $value;
    });
});
```

## Summary of Key Improvements

### 1. **Security Enhancements**
- Added proper nonce verification everywhere
- Implemented secure API key generation and validation
- Added rate limiting with Redis/Memcached support
- Enhanced input sanitization and output escaping
- Added security headers and CORS configuration
- Implemented transaction support for database operations

### 2. **Performance Optimizations**
- Implemented comprehensive caching strategy with cache warming
- Added database query optimization with proper indexes
- Implemented batch operations for bulk actions
- Added lazy loading for role hierarchies
- Optimized permission checking with caching
- Added persistent cache support

### 3. **Error Handling & Logging**
- Comprehensive error logging with log levels
- Fallback mechanisms for critical operations
- Transaction rollback on failures
- Detailed context logging for debugging
- Log rotation to prevent database bloat

### 4. **Modern JavaScript**
- Converted to ES6+ syntax
- Added proper error handling
- Implemented debouncing for search
- Memory leak prevention
- Better event delegation

### 5. **Database Abstraction**
- Created abstraction layer for all database operations
- Prepared statements for all queries
- Transaction support for data integrity
- Optimized table structure with proper indexes

### 6. **Testing Infrastructure**
- Comprehensive unit test suite
- Integration test examples
- Performance benchmarking helpers

### 7. **Additional Features**
- Comprehensive settings management
- Import/Export functionality
- Bulk operations support
- Advanced role filtering
- User column in admin
- System information dashboard

## Deployment Recommendations

1. **Before Deployment:**
   - Run all unit tests
   - Test on staging environment
   - Backup database
   - Review error logs

2. **Performance Monitoring:**
   - Enable query monitoring initially
   - Monitor cache hit rates
   - Check API response times
   - Review audit log growth

3. **Security Checklist:**
   - Generate new API keys
   - Review rate limiting settings
   - Enable audit logging
   - Configure allowed CORS origins

4. **Maintenance:**
   - Schedule regular cache warming
   - Monitor log rotation
   - Review expired role cleanup
   - Optimize database tables monthly### 10. **includes/render/render-admin.php**
**Issues:**
- Direct superglobal access
- No nonce verification in some places
- Missing escaping for output
- No AJAX support for better UX

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

/**
 * Enhanced role UI rendering with security and performance improvements
 */
class AccessSchema_Admin_Renderer {
    
    /**
     * Get available roles with caching and filtering
     */
    public static function get_available_roles($args = []) {
        $defaults = [
            'hierarchical' => false,
            'hide_empty' => true,
            'search' => '',
            'parent' => null,
            'orderby' => 'full_path',
            'order' => 'ASC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Try cache first
        $cache_key = 'available_roles_' . md5(serialize($args));
        $cached = AccessSchema_Cache::get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'access_roles';
        
        $query = "SELECT * FROM {$table} WHERE is_active = 1";
        $query_args = [];
        
        // Add filters
        if (!empty($args['search'])) {
            $query .= " AND (name LIKE %s OR full_path LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }
        
        if ($args['parent'] !== null) {
            $query .= " AND parent_id = %d";
            $query_args[] = intval($args['parent']);
        }
        
        if ($args['hide_empty']) {
            $query .= " AND id IN (SELECT DISTINCT parent_id FROM {$table} WHERE parent_id IS NOT NULL)";
        }
        
        // Add ordering
        $allowed_orderby = ['name', 'full_path', 'created_at', 'depth'];
        if (in_array($args['orderby'], $allowed_orderby)) {
            $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY {$args['orderby']} {$order}";
        }
        
        $roles = $wpdb->get_results(
            empty($query_args) ? $query : $wpdb->prepare($query, ...$query_args),
            ARRAY_A
        );
        
        // Build hierarchical structure if needed
        if ($args['hierarchical']) {
            $roles = self::build_role_tree($roles);
        }
        
        AccessSchema_Cache::set($cache_key, $roles, ACCESSSCHEMA_CACHE_GROUP, 600);
        
        return $roles;
    }
    
    /**
     * Build hierarchical role tree
     */
    private static function build_role_tree($roles, $parent_id = null, $level = 0) {
        $tree = [];
        
        foreach ($roles as $role) {
            if ($role['parent_id'] == $parent_id) {
                $role['level'] = $level;
                $role['children'] = self::build_role_tree($roles, $role['id'], $level + 1);
                $tree[] = $role;
            }
        }
        
        return $tree;
    }
    
    /**
     * Render user role UI with enhanced security
     */
    public static function render_user_role_ui($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        // Get user's current roles
        $assigned_roles = accessSchema_get_user_roles($user->ID);
        
        // Get all available roles
        $all_roles = self::get_available_roles(['hide_empty' => false]);
        
        // Enqueue assets
        self::enqueue_assets();
        
        // Render UI
        ?>
        <div class="accessschema-user-roles-wrapper">
            <h2><?php esc_html_e('Access Schema Roles', 'accessschema'); ?></h2>
            
            <?php wp_nonce_field('accessschema_user_roles_action', 'accessschema_user_roles_nonce'); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th><label><?php esc_html_e('Assigned Roles', 'accessschema'); ?></label></th>
                    <td>
                        <div id="accessschema-assigned-roles" class="accessschema-role-list">
                            <?php self::render_assigned_roles($assigned_roles); ?>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th>
                        <label for="accessschema_add_roles">
                            <?php esc_html_e('Add Roles', 'accessschema'); ?>
                        </label>
                    </th>
                    <td>
                        <select 
                            name="accessschema_add_roles[]" 
                            id="accessschema_add_roles" 
                            class="accessschema-role-select" 
                            multiple="multiple"
                            data-placeholder="<?php esc_attr_e('Select roles to add...', 'accessschema'); ?>"
                        >
                            <?php self::render_role_options($all_roles, $assigned_roles); ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Search and select roles to assign to this user.', 'accessschema'); ?>
                        </p>
                    </td>
                </tr>
                
                <?php if (current_user_can('manage_access_schema')): ?>
                <tr>
                    <th><?php esc_html_e('Quick Actions', 'accessschema'); ?></th>
                    <td>
                        <button type="button" class="button" id="accessschema-clear-roles">
                            <?php esc_html_e('Clear All Roles', 'accessschema'); ?>
                        </button>
                        <button type="button" class="button" id="accessschema-export-roles">
                            <?php esc_html_e('Export User Roles', 'accessschema'); ?>
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <div id="accessschema-ajax-message" class="notice" style="display:none;"></div>
        </div>
        <?php
    }
    
    /**
     * Render assigned roles with proper escaping
     */
    private static function render_assigned_roles($assigned_roles) {
        if (empty($assigned_roles)) {
            echo '<p class="description">' . esc_html__('No roles currently assigned.', 'accessschema') . '</p>';
            return;
        }
        
        foreach ($assigned_roles as $role) {
            ?>
            <span class="accessschema-role-tag" data-role="<?php echo esc_attr($role); ?>">
                <span class="role-name"><?php echo esc_html($role); ?></span>
                <button 
                    type="button" 
                    class="remove-role-button" 
                    data-role="<?php echo esc_attr($role); ?>"
                    aria-label="<?php echo esc_attr(sprintf(__('Remove role: %s', 'accessschema'), $role)); ?>"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </span>
            <?php
        }
    }
    
    /**
     * Render role options for select
     */
    private static function render_role_options($all_roles, $assigned_roles, $parent_path = '', $level = 0) {
        foreach ($all_roles as $role) {
            if (in_array($role['full_path'], $assigned_roles, true)) {
                continue;
            }
            
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
            ?>
            <option value="<?php echo esc_attr($role['full_path']); ?>">
                <?php echo $indent . esc_html($role['full_path']); ?>
            </option>
            <?php
            
            if (!empty($role['children'])) {
                self::render_role_options($role['children'], $assigned_roles, $role['full_path'], $level + 1);
            }
        }
    }
    
    /**
     * Enqueue required assets
     */
    private static function enqueue_assets() {
        $plugin_url = plugin_dir_url(dirname(__DIR__));
        $version = ACCESSSCHEMA_VERSION;
        
        // Styles
        wp_enqueue_style(
            'accessschema-select2',
            $plugin_url . 'assets/css/select2.min.css',
            [],
            '4.1.0'
        );
        
        wp_enqueue_style(
            'accessschema-admin',
            $plugin_url . 'assets/css/accessSchema.css',
            ['accessschema-select2'],
            $version
        );
        
        // Scripts
        wp_enqueue_script(
            'accessschema-select2',
            $plugin_url . 'assets/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );
        
        wp_enqueue_script(
            'accessschema-admin',
            $plugin_url . 'assets/js/accessSchema.js',
            ['jquery', 'accessschema-select2'],
            $version,
            true
        );
        
        // Localize script
        wp_localize_script('accessschema-admin', 'accessSchemaAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('accessschema_ajax_nonce'),
            'user_id' => get_current_user_id(),
            'strings' => [
                'confirm_remove' => __('Are you sure you want to remove this role?', 'accessschema'),
                'confirm_clear' => __('Are you sure you want to clear all roles?', 'accessschema'),
                'error_generic' => __('An error occurred. Please try again.', 'accessschema'),
                'success_added' => __('Role(s) added successfully.', 'accessschema'),
                'success_removed' => __('Role removed successfully.', 'accessschema'),
                'loading' => __('Loading...', 'accessschema')
            ]
        ]);
    }
}

// Helper functions for backward compatibility
function accessSchema_get_available_roles() {
    return AccessSchema_Admin_Renderer::get_available_roles(['hide_empty' => false]);
}

function accessSchema_render_user_role_ui($user) {
    AccessSchema_Admin_Renderer::render_user_role_ui($user);
}

function accessSchema_render_user_role_select($all_roles, $assigned) {
    wp_deprecated_function(__FUNCTION__, '1.7.0', 'AccessSchema_Admin_Renderer::render_user_role_ui');
}

/**
 * Handle user profile update with enhanced validation
 */
function accessSchema_user_profile_update($user_id) {
    // Verify nonce
    if (!isset($_POST['accessschema_user_roles_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['accessschema_user_roles_nonce'])), 'accessschema_user_roles_action')) {
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    
    global $wpdb;
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Get current roles
        $current_roles = accessSchema_get_user_roles($user_id);
        
        // Process additions
        $add_roles = isset($_POST['accessschema_add_roles']) && is_array($_POST['accessschema_add_roles']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['accessschema_add_roles'])) 
            : [];
            
        // Process removals
        $remove_roles = isset($_POST['accessschema_remove_roles']) && is_array($_POST['accessschema_remove_roles']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['accessschema_remove_roles'])) 
            : [];
        
        // Validate all roles exist
        foreach (array_merge($add_roles, $remove_roles) as $role) {
            if (!accessSchema_role_exists($role)) {
                throw new Exception(sprintf(__('Invalid role: %s', 'accessschema'), $role));
            }
        }
        
        // Add new roles
        foreach ($add_roles as $role_path) {
            if (!in_array($role_path, $current_roles, true)) {
                if (!accessSchema_add_role($user_id, $role_path)) {
                    throw new Exception(sprintf(__('Failed to add role: %s', 'accessschema'), $role_path));
                }
            }
        }
        
        // Remove roles
        foreach ($remove_roles as $role_path) {
            if (in_array($role_path, $current_roles, true)) {
                if (!accessSchema_remove_role($user_id, $role_path)) {
                    throw new Exception(sprintf(__('Failed to remove role: %s', 'accessschema'), $role_path));
                }
            }
        }
        
        $wpdb->query('COMMIT');
        
        // Log successful update
        accessSchema_log_event($user_id, 'roles_updated', '', [
            'added' => $add_roles,
            'removed' => $remove_roles,
            'by_user' => get_current_user_id()
        ], null, 'INFO');
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        
        // Log error
        accessSchema_log_event($user_id, 'roles_update_failed', '', [
            'error' => $e->getMessage(),
            'by_user' => get_current_user_id()
        ], null, 'ERROR');
        
        // Add admin notice
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($e->getMessage()); ?></p>
            </div>
            <?php
        });
    }
}

// Register hooks with proper priority
add_action('show_user_profile', 'accessSchema_render_user_role_ui', 20);
add_action('edit_user_profile', 'accessSchema_render_user_role_ui', 20);
add_action('personal_options_update', 'accessSchema_user_profile_update', 10);
add_action('edit_user_profile_update', 'accessSchema_user_profile_update', 10);

/**
 * AJAX handler for dynamic role operations
 */
add_action('wp_ajax_accessschema_user_role_action', 'accessSchema_ajax_user_role_action');
function accessSchema_ajax_user_role_action() {
    // Verify nonce
    check_ajax_referer('accessschema_ajax_nonce', 'nonce');
    
    $action = isset($_POST['role_action']) ? sanitize_key($_POST['role_action']) : '';
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $role_path = isset($_POST['role_path']) ? sanitize_text_field($_POST['role_path']) : '';
    
    // Validate inputs
    if (!$action || !$user_id) {
        wp_send_json_error(['message' => __('Invalid request', 'accessschema')]);
    }
    
    // Check permissions
    if (!current_user_can('edit_user', $user_id)) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'accessschema')]);
    }
    
    switch ($action) {
        case 'add':
            if (accessSchema_add_role($user_id, $role_path)) {
                wp_send_json_success([
                    'message' => __('Role added successfully', 'accessschema'),
                    'role' => $role_path
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to add role', 'accessschema')]);
            }
            break;
            
        case 'remove':
            if (accessSchema_remove_role($user_id, $role_path)) {
                wp_send_json_success([
                    'message' => __('Role removed successfully', 'accessschema'),
                    'role' => $role_path
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to remove role', 'accessschema')]);
            }
            break;
            
        case 'clear':
            $current_roles = accessSchema_get_user_roles($user_id);
            $cleared = 0;
            
            foreach ($current_roles as $role) {
                if (accessSchema_remove_role($user_id, $role)) {
                    $cleared++;
                }
            }
            
            wp_send_json_success([
                'message' => sprintf(__('%d roles cleared', 'accessschema'), $cleared),
                'cleared' => $cleared
            ]);
            break;
            
        default:
            wp_send_json_error(['message' => __('Invalid action', 'accessschema')]);
    }
}

/**
 * Add user column for roles
 */
add_filter('manage_users_columns', 'accessSchema_add_user_column');
function accessSchema_add_user_column($columns) {
    if (current_user_can('manage_access_schema')) {
        $columns['accessschema_roles'] = __('Access Roles', 'accessschema');
    }
    return $columns;
}

add_action('manage_users_custom_column', 'accessSchema_render_user_column', 10, 3);
function accessSchema_render_user_column($value, $column_name, $user_id) {
    if ($column_name === 'accessschema_roles') {
        $roles = accessSchema_get_user_roles($user_id);
        
        if (empty($roles)) {
            return '<span class="description">' . __('None', 'accessschema') . '</span>';
        }
        
        $output = '<div class="accessschema-user-roles-column">';
        foreach (array_slice($roles, 0, 3) as $role) {
            $output .= '<span class="accessschema-role-badge">' . esc_html($role) . '</span> ';
        }
        
        if (count($roles) > 3) {
            $output .= '<span class="description">+' . (count($roles) - 3) . ' ' . __('more', 'accessschema') . '</span>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    return $value;
}
```

### 11. **includes/shortcodes/access.php**
**Issues:**
- No caching for shortcode output
- Missing attribute validation
- No performance optimization for nested shortcodes

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

/**
 * Enhanced shortcode handler with caching and optimization
 */
class AccessSchema_Shortcode {
    private static $instance = null;
    private static $shortcode_cache = [];
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('access_schema', [$this, 'render_shortcode']);
        
        // Clear cache on content update
        add_action('save_post', [$this, 'clear_shortcode_cache']);
        add_action('accessschema_role_added', [$this, 'clear_user_shortcode_cache']);
        add_action('accessschema_role_removed', [$this, 'clear_user_shortcode_cache']);
    }
    
    /**
     * Render access_schema shortcode with caching
     */
    public function render_shortcode($atts, $content = null) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '';
        }
        
        $user_id = get_current_user_id();
        
        // Parse and validate attributes
        $atts = $this->parse_attributes($atts);
        
        // Generate cache key
        $cache_key = $this->generate_cache_key($user_id, $atts, $content);
        
        // Check cache
        if (isset(self::$shortcode_cache[$cache_key])) {
            return self::$shortcode_cache[$cache_key];
        }
        
        // Process shortcode
        $output = $this->process_shortcode($user_id, $atts, $content);
        
        // Cache result
        self::$shortcode_cache[$cache_key] = $output;
        
        return $output;
    }
    
    /**
     * Parse and validate shortcode attributes
     */
    private function parse_attributes($atts) {
        $defaults = [
            'role' => '',
            'any' => '',
            'all' => '',
            'children' => 'false',
            'wildcard' => 'true',
            'fallback' => '',
            'cache' => 'true',
            'log' => 'false'
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'access_schema');
        
        // Validate boolean attributes
        $boolean_attrs = ['children', 'wildcard', 'cache', 'log'];
        foreach ($boolean_attrs as $attr) {
            $atts[$attr] = filter_var($atts[$attr], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Sanitize string attributes
        $atts['role'] = sanitize_text_field($atts['role']);
        $atts['any'] = sanitize_text_field($atts['any']);
        $atts['all'] = sanitize_text_field($atts['all']);
        
        return $atts;
    }
    
    /**
     * Process shortcode logic
     */
    private function process_shortcode($user_id, $atts, $content) {
        $has_access = false;
        
        // Check 'all' parameter (user must have all specified roles)
        if (!empty($atts['all'])) {
            $required_roles = array_map('trim', explode(',', $atts['all']));
            $has_access = $this->check_all_roles($user_id, $required_roles, $atts);
        }
        // Check 'any' parameter (user must have at least one role)
        elseif (!empty($atts['any'])) {
            $any_roles = array_map('trim', explode(',', $atts['any']));
            $has_access = $this->check_any_roles($user_id, $any_roles, $atts);
        }
        // Check single 'role' parameter
        elseif (!empty($atts['role'])) {
            $has_access = AccessSchema_Permissions::check(
                $user_id,
                $atts['role'],
                $atts['children'],
                $atts['log'],
                ['source' => 'shortcode'],
                $atts['wildcard']
            );
        }
        
        // Return content or fallback
        if ($has_access) {
            return do_shortcode($content);
        }
        
        return $atts['fallback'];
    }
    
    /**
     * Check if user has all specified roles
     */
    private function check_all_roles($user_id, $roles, $atts) {
        foreach ($roles as $role) {
            if (!AccessSchema_Permissions::check(
                $user_id,
                $role,
                $atts['children'],
                false, // Don't log individual checks
                ['source' => 'shortcode_all'],
                $atts['wildcard']
            )) {
                // Log the denial if logging is enabled
                if ($atts['log']) {
                    accessSchema_log_event($user_id, 'shortcode_access_denied', $role, [
                        'type' => 'all',
                        'required_roles' => $roles
                    ], null, 'INFO');
                }
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if user has any of the specified roles
     */
    private function check_any_roles($user_id, $roles, $atts) {
        // Use batch checking for performance
        $permissions = [];
        foreach ($roles as $role) {
            $permissions[] = [
                'path' => $role,
                'include_children' => $atts['children'],
                'allow_wildcards' => $atts['wildcard'],
                'log' => false
            ];
        }
        
        $results = AccessSchema_Permissions::check_multiple($user_id, $permissions);
        
        $has_access = in_array(true, $results, true);
        
        // Log result if enabled
        if ($atts['log']) {
            accessSchema_log_event($user_id, $has_access ? 'shortcode_access_granted' : 'shortcode_access_denied', '', [
                'type' => 'any',
                'roles' => $roles,
                'results' => $results
            ], null, 'INFO');
        }
        
        return $has_access;
    }
    
    /**
     * Generate cache key for shortcode
     */
    private function generate_cache_key($user_id, $atts, $content) {
        return md5(serialize([
            'user_id' => $user_id,
            'atts' => $atts,
            'content' => $content
        ]));
    }
    
    /**
     * Clear all shortcode cache
     */
    public function clear_shortcode_cache() {
        self::$shortcode_cache = [];
    }
    
    /**
     * Clear cache for specific user
     */
    public function clear_user_shortcode_cache($user_id) {
        foreach (array_keys(self::$shortcode_cache) as $key) {
            if (strpos($key, "user_id:{$user_id}") !== false) {
                unset(self::$shortcode_cache[$key]);
            }
        }
    }
}

// Initialize shortcode handler
AccessSchema_Shortcode::init();

// Legacy function for backward compatibility
function accessSchema_shortcode_access($atts, $content = null) {
    return AccessSchema_Shortcode::init()->render_shortcode($atts, $content);
}

/**
 * Additional utility shortcodes
 */

// Display user's roles
add_shortcode('access_schema_user_roles', function($atts) {
    if (!is_user_logged_in()) {
        return '';
    }
    
    $atts = shortcode_atts([
        'user_id' => get_current_user_id(),
        'format' => 'list', // list, inline, count
        'separator' => ', '
    ], $atts);
    
    $user_id = absint($atts['user_id']);
    $roles = accessSchema_get_user_roles($user_id);
    
    if (empty($roles)) {
        return '';
    }
    
    switch ($atts['format']) {
        case 'count':
            return count($roles);
            
        case 'inline':
            return esc_html(implode($atts['separator'], $roles));
            
        case 'list':
        default:
            $output = '<ul class="accessschema-role-list">';
            foreach ($roles as $role) {
                $output .= '<li>' . esc_html($role) . '</li>';
            }
            $output .= '</ul>';
            return $output;
    }
});

// Check if user has specific permission
add_shortcode('access_schema_can', function($atts) {
    if (!is_user_logged_in()) {
        return 'false';
    }
    
    $atts = shortcode_atts([
        'role' => '',
        'user_id' => get_current_user_id()
    ], $atts);
    
    if (empty($atts['role'])) {
        return 'false';
    }
    
    return accessSchema_user_can(absint($atts['user_id']), $atts['role']) ? 'true' : 'false';
});
```# AccessSchema Complete Code Review & Optimization

## Critical Issues & Fixes

### 1. **accessSchema.php** (Main Plugin File)
**Issues:**
- Missing proper initialization hooks
- No error handling for includes
- Missing version constant
- No proper uninstall hook registration

**Fix:**
```php
<?php
/**
 * Plugin Name: accessSchema
 * Description: Manage role-based access schema plugin with audit logging and REST API support.
 * Version: 1.7.0
 * Author: greghacke
 * Author URI: https://www.owbn.net
 * Text Domain: accessschema
 * Tested up to: 6.8
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/One-World-By-Night/owbn-chronicle-plugin
 * GitHub Branch: main
 */

defined('ABSPATH') || exit;

// Define constants
define('ACCESSSCHEMA_VERSION', '1.7.0');
define('ACCESSSCHEMA_PLUGIN_FILE', __FILE__);
define('ACCESSSCHEMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACCESSSCHEMA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Error handler for critical includes
function accessSchema_safe_include($file) {
    if (!file_exists($file)) {
        error_log(sprintf('AccessSchema: Required file missing: %s', $file));
        return false;
    }
    require_once $file;
    return true;
}

// Load plugin core with error handling
$required_files = [
    'includes/core/init.php',
    'includes/core/activation.php',
    'includes/core/helpers.php',
    'includes/core/webhook-router.php',
    'includes/render/render-admin.php',
    'includes/admin/role-manager.php',
    'includes/shortcodes/access.php',
    'includes/utils/access-utils.php'
];

foreach ($required_files as $file) {
    if (!accessSchema_safe_include(ACCESSSCHEMA_PLUGIN_DIR . $file)) {
        add_action('admin_notices', function() use ($file) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(__('AccessSchema: Failed to load required file: %s', 'accessschema'), $file))
            );
        });
        return;
    }
}

register_activation_hook(__FILE__, 'accessSchema_activate');
register_deactivation_hook(__FILE__, 'accessSchema_deactivate');

// Add deactivation function
function accessSchema_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('accessschema_daily_cleanup');
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear caches
    accessSchema_clear_all_caches();
}
```

### 2. **includes/core/activation.php**
**Issues:**
- Direct DB queries without error handling
- Missing indexes for performance
- No migration system
- Foreign key issues (already noted)
- Missing capability checks

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

function accessSchema_activate() {
    global $wpdb;
    
    // Check minimum requirements
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(ACCESSSCHEMA_PLUGIN_FILE));
        wp_die('AccessSchema requires PHP 7.4 or higher.');
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Use transactions for atomic operations
    $wpdb->query('START TRANSACTION');
    
    try {
        // Create tables with proper error handling
        $tables_created = accessSchema_create_tables($charset_collate);
        
        if (!$tables_created) {
            throw new Exception('Failed to create database tables');
        }
        
        // Add capabilities
        accessSchema_add_capabilities();
        
        // Set default options
        accessSchema_set_default_options();
        
        // Schedule cleanup cron
        if (!wp_next_scheduled('accessschema_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'accessschema_daily_cleanup');
        }
        
        $wpdb->query('COMMIT');
        
        // Log successful activation
        accessSchema_log_event(0, 'plugin_activated', '', [
            'version' => ACCESSSCHEMA_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ], null, 'INFO');
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('AccessSchema activation failed: ' . $e->getMessage());
        wp_die('AccessSchema activation failed. Please check error logs.');
    }
}

function accessSchema_create_tables($charset_collate) {
    global $wpdb;
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $tables = [];
    
    // Audit log table with better indexes
    $tables['audit_log'] = "
    CREATE TABLE {$wpdb->prefix}access_audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(64) NOT NULL,
        role_path VARCHAR(255) NOT NULL,
        context TEXT NULL,
        performed_by BIGINT UNSIGNED,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        INDEX idx_user_action (user_id, action),
        INDEX idx_performed_by (performed_by),
        INDEX idx_created_at (created_at),
        INDEX idx_action (action),
        INDEX idx_role_path (role_path(191))
    ) $charset_collate;";
    
    // Roles table with optimized indexes
    $tables['roles'] = "
    CREATE TABLE {$wpdb->prefix}access_roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id BIGINT UNSIGNED DEFAULT NULL,
        name VARCHAR(191) NOT NULL,
        full_path VARCHAR(767) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        depth TINYINT UNSIGNED DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        metadata JSON,
        UNIQUE KEY unique_name_per_parent (name, parent_id),
        UNIQUE KEY unique_full_path (full_path),
        KEY idx_parent_id (parent_id),
        KEY idx_name (name),
        KEY idx_depth (depth),
        KEY idx_full_path_prefix (full_path(191)),
        KEY idx_is_active (is_active),
        FULLTEXT KEY idx_full_path_search (full_path)
    ) $charset_collate;";
    
    // User roles junction table
    $tables['user_roles'] = "
    CREATE TABLE {$wpdb->prefix}access_user_roles (
        user_id BIGINT UNSIGNED NOT NULL,
        role_id BIGINT UNSIGNED NOT NULL,
        granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        granted_by BIGINT UNSIGNED,
        expires_at DATETIME NULL,
        metadata JSON,
        PRIMARY KEY (user_id, role_id),
        KEY idx_role_id (role_id),
        KEY idx_granted_at (granted_at),
        KEY idx_expires_at (expires_at),
        KEY idx_granted_by (granted_by)
    ) $charset_collate;";
    
    $success = true;
    foreach ($tables as $name => $sql) {
        $result = dbDelta($sql);
        if (empty($result)) {
            error_log("AccessSchema: Failed to create table $name");
            $success = false;
        }
    }
    
    return $success;
}

function accessSchema_set_default_options() {
    $defaults = [
        'accessschema_version' => ACCESSSCHEMA_VERSION,
        'accessschema_log_level' => 'INFO',
        'accessschema_cache_ttl' => 3600,
        'accessschema_enable_rate_limiting' => true,
        'accessschema_rate_limit_requests' => 60,
        'accessschema_rate_limit_window' => 60,
        'accessschema_enable_audit_log' => true,
        'accessschema_audit_log_retention_days' => 90,
        'accessschema_enable_debug_mode' => false
    ];
    
    foreach ($defaults as $option => $value) {
        add_option($option, $value);
    }
}

function accessSchema_add_capabilities() {
    $roles = ['administrator'];
    $capabilities = [
        'manage_access_schema',
        'assign_access_roles',
        'view_access_logs',
        'export_access_logs'
    ];
    
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
    }
}
```

### 3. **includes/core/logging.php**
**Issues:**
- No log rotation
- Missing context validation
- No performance optimization for bulk logging
- Missing IP and user agent logging

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

// Log levels with numeric priorities
const ACCESSSCHEMA_LOG_LEVELS = [
    'DEBUG' => 0,
    'INFO'  => 1,
    'WARN'  => 2,
    'ERROR' => 3,
    'CRITICAL' => 4
];

function accessSchema_get_log_level() {
    static $cached_level = null;
    
    if ($cached_level === null) {
        $level = get_option('accessschema_log_level', 'INFO');
        $cached_level = apply_filters('accessschema_log_level', $level);
    }
    
    return $cached_level;
}

function accessSchema_log_level_priority($level) {
    return ACCESSSCHEMA_LOG_LEVELS[strtoupper($level)] ?? 1;
}

function accessSchema_log_event($user_id, $action, $role_path, $context = null, $performed_by = null, $level = 'INFO') {
    // Check if logging is enabled
    if (!get_option('accessschema_enable_audit_log', true)) {
        return false;
    }
    
    // Check log level
    $current_level = accessSchema_get_log_level();
    if (accessSchema_log_level_priority($level) < accessSchema_log_level_priority($current_level)) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'access_audit_log';
    
    // Validate and sanitize inputs
    $user_id = absint($user_id);
    $action = sanitize_key($action);
    $role_path = sanitize_text_field($role_path);
    $performed_by = $performed_by ?? get_current_user_id();
    
    // Get additional context
    $ip_address = accessSchema_get_client_ip();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? 
        substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : '';
    
    // Prepare context
    if (!is_array($context)) {
        $context = $context ? ['data' => $context] : [];
    }
    
    // Add system context
    $context['level'] = $level;
    $context['timestamp'] = current_time('timestamp');
    
    // Use prepared statement with error handling
    $result = $wpdb->insert(
        $table_name,
        [
            'user_id'      => $user_id,
            'action'       => $action,
            'role_path'    => $role_path,
            'context'      => wp_json_encode($context),
            'performed_by' => $performed_by,
            'created_at'   => current_time('mysql'),
            'ip_address'   => $ip_address,
            'user_agent'   => $user_agent
        ],
        ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );
    
    if ($result === false) {
        error_log(sprintf(
            'AccessSchema: Failed to log event. Action: %s, Error: %s',
            $action,
            $wpdb->last_error
        ));
        
        // Try alternative logging method
        accessSchema_fallback_log($user_id, $action, $role_path, $context, $level);
    }
    
    // Trigger log rotation if needed
    if (rand(1, 100) === 1) { // 1% chance to check
        wp_schedule_single_event(time() + 10, 'accessschema_rotate_logs');
    }
    
    return $result !== false;
}

function accessSchema_get_client_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
            $ip = explode(',', $ip)[0]; // Handle comma-separated IPs
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
}

function accessSchema_fallback_log($user_id, $action, $role_path, $context, $level) {
    $log_data = [
        'user_id' => $user_id,
        'action' => $action,
        'role_path' => $role_path,
        'context' => $context,
        'level' => $level,
        'timestamp' => current_time('mysql')
    ];
    
    error_log('AccessSchema Fallback: ' . wp_json_encode($log_data));
}

// Log rotation function
function accessSchema_rotate_logs() {
    global $wpdb;
    
    $retention_days = get_option('accessschema_audit_log_retention_days', 90);
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
    
    $table_name = $wpdb->prefix . 'access_audit_log';
    
    // Delete old logs
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table_name} WHERE created_at < %s LIMIT 10000",
        $cutoff_date
    ));
    
    if ($deleted > 0) {
        accessSchema_log_event(0, 'logs_rotated', '', [
            'deleted_count' => $deleted,
            'retention_days' => $retention_days
        ], null, 'INFO');
    }
    
    // Optimize table if significant deletions
    if ($deleted > 1000) {
        $wpdb->query("OPTIMIZE TABLE {$table_name}");
    }
}

add_action('accessschema_rotate_logs', 'accessSchema_rotate_logs');
```

### 4. **includes/core/cache.php**
**Issues:**
- No cache warming strategy
- Missing cache invalidation hooks
- No persistent cache support
- No cache statistics

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

// Cache groups
const ACCESSSCHEMA_CACHE_GROUP = 'accessschema';
const ACCESSSCHEMA_CACHE_VERSION = '1.7.0';

/**
 * Enhanced cache wrapper with persistent cache support
 */
class AccessSchema_Cache {
    private static $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    public static function get($key, $group = ACCESSSCHEMA_CACHE_GROUP, $force = false, &$found = null) {
        $key = self::version_key($key);
        $value = wp_cache_get($key, $group, $force, $found);
        
        if ($found) {
            self::$stats['hits']++;
        } else {
            self::$stats['misses']++;
            
            // Try persistent cache
            if (self::use_persistent_cache()) {
                $value = get_transient("accessschema_{$group}_{$key}");
                if ($value !== false) {
                    wp_cache_set($key, $value, $group);
                    $found = true;
                    self::$stats['hits']++;
                }
            }
        }
        
        return $value;
    }
    
    public static function set($key, $value, $group = ACCESSSCHEMA_CACHE_GROUP, $expire = 0) {
        $key = self::version_key($key);
        self::$stats['sets']++;
        
        // Set in object cache
        $result = wp_cache_set($key, $value, $group, $expire);
        
        // Set in persistent cache if enabled
        if (self::use_persistent_cache() && $expire > 0) {
            set_transient("accessschema_{$group}_{$key}", $value, $expire);
        }
        
        return $result;
    }
    
    public static function delete($key, $group = ACCESSSCHEMA_CACHE_GROUP) {
        $key = self::version_key($key);
        self::$stats['deletes']++;
        
        // Delete from both caches
        wp_cache_delete($key, $group);
        
        if (self::use_persistent_cache()) {
            delete_transient("accessschema_{$group}_{$key}");
        }
        
        return true;
    }
    
    public static function flush_group($group = ACCESSSCHEMA_CACHE_GROUP) {
        // Increment group version to invalidate all keys
        $version_key = "accessschema_cache_version_{$group}";
        $version = get_option($version_key, 0) + 1;
        update_option($version_key, $version);
        
        // Clear transients if using persistent cache
        if (self::use_persistent_cache()) {
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_accessschema_' . $group . '_%'
            ));
        }
        
        return true;
    }
    
    private static function version_key($key) {
        $group_version = get_option('accessschema_cache_version_' . ACCESSSCHEMA_CACHE_GROUP, 0);
        return $key . '_v' . ACCESSSCHEMA_CACHE_VERSION . '_' . $group_version;
    }
    
    private static function use_persistent_cache() {
        return get_option('accessschema_use_persistent_cache', false) && !wp_using_ext_object_cache();
    }
    
    public static function get_stats() {
        return self::$stats;
    }
}

// Refactored cache functions using the new cache class
function accessSchema_get_all_roles($force_refresh = false) {
    $cache_key = 'all_roles';
    
    if (!$force_refresh) {
        $cached = AccessSchema_Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    global $wpdb;
    $roles = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}access_roles 
         WHERE is_active = 1 
         ORDER BY depth, full_path",
        ARRAY_A
    );
    
    // Process metadata
    foreach ($roles as &$role) {
        if (!empty($role['metadata'])) {
            $role['metadata'] = json_decode($role['metadata'], true);
        }
    }
    
    AccessSchema_Cache::set($cache_key, $roles, ACCESSSCHEMA_CACHE_GROUP, 3600);
    return $roles;
}

function accessSchema_clear_all_caches() {
    // Clear all cache groups
    $groups = ['accessschema', 'accessschema_users', 'accessschema_roles'];
    
    foreach ($groups as $group) {
        AccessSchema_Cache::flush_group($group);
    }
    
    // Clear any transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_accessschema_%' 
         OR option_name LIKE '_transient_timeout_accessschema_%'"
    );
    
    // Clear external caches
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    do_action('accessschema_caches_cleared');
    
    return true;
}

function accessSchema_warm_caches() {
    // Schedule warming in background
    if (!wp_next_scheduled('accessschema_warm_caches_cron')) {
        wp_schedule_single_event(time() + 1, 'accessschema_warm_caches_cron');
    }
}

function accessSchema_do_cache_warming() {
    // Warm critical caches
    accessSchema_get_all_roles(true);
    
    // Pre-cache common role paths
    global $wpdb;
    $common_paths = $wpdb->get_col(
        "SELECT DISTINCT full_path 
         FROM {$wpdb->prefix}access_roles 
         WHERE depth <= 2 AND is_active = 1
         LIMIT 50"
    );
    
    foreach ($common_paths as $path) {
        accessSchema_role_exists_cached($path, true);
    }
    
    // Pre-cache active users' roles
    $active_users = $wpdb->get_col(
        "SELECT DISTINCT user_id 
         FROM {$wpdb->prefix}access_user_roles ur
         JOIN {$wpdb->prefix}access_audit_log al ON ur.user_id = al.user_id
         WHERE al.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
         LIMIT 100"
    );
    
    foreach ($active_users as $user_id) {
        accessSchema_get_user_roles($user_id, true);
    }
}

add_action('accessschema_warm_caches_cron', 'accessSchema_do_cache_warming');

// Cache invalidation hooks
add_action('accessschema_role_added', function($user_id, $role_path) {
    AccessSchema_Cache::delete('user_roles_' . $user_id, 'accessschema_users');
    AccessSchema_Cache::delete('all_roles');
}, 10, 2);

add_action('accessschema_role_removed', function($user_id, $role_path) {
    AccessSchema_Cache::delete('user_roles_' . $user_id, 'accessschema_users');
}, 10, 2);
```

### 5. **includes/core/webhook-router.php**
**Issues:**
- Weak API authentication
- No request validation
- Missing rate limiting implementation
- No request/response logging
- Security vulnerabilities in user resolution

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

// REST API namespace
const ACCESSSCHEMA_API_NAMESPACE = 'access-schema/v1';

/**
 * Initialize REST API routes with enhanced security
 */
add_action('rest_api_init', function () {
    // API routes configuration
    $routes = [
        'register' => [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'accessSchema_api_register_roles',
            'permission_callback' => 'accessSchema_api_write_permission_check',
            'args' => accessSchema_get_register_args()
        ],
        'roles' => [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'accessSchema_api_get_roles',
            'permission_callback' => 'accessSchema_api_read_permission_check',
            'args' => accessSchema_get_user_args()
        ],
        'grant' => [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'accessSchema_api_grant_role',
            'permission_callback' => 'accessSchema_api_write_permission_check',
            'args' => accessSchema_get_grant_args()
        ],
        'revoke' => [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'accessSchema_api_revoke_role',
            'permission_callback' => 'accessSchema_api_write_permission_check',
            'args' => accessSchema_get_revoke_args()
        ],
        'check' => [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'accessSchema_api_check_permission',
            'permission_callback' => 'accessSchema_api_read_permission_check',
            'args' => accessSchema_get_check_args()
        ],
        'audit' => [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'accessSchema_api_get_audit_log',
            'permission_callback' => 'accessSchema_api_admin_permission_check',
            'args' => accessSchema_get_audit_args()
        ]
    ];
    
    foreach ($routes as $route => $config) {
        register_rest_route(ACCESSSCHEMA_API_NAMESPACE, '/' . $route, $config);
    }
    
    // CORS preflight support
    register_rest_route(ACCESSSCHEMA_API_NAMESPACE, '/.*', [
        'methods' => WP_REST_Server::ALLMETHODS,
        'callback' => 'accessSchema_handle_preflight',
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Enhanced API authentication with multiple methods
 */
function accessSchema_api_authenticate($request) {
    // Method 1: API Key authentication
    $api_key = $request->get_header('x-api-key');
    if ($api_key) {
        return accessSchema_validate_api_key($api_key);
    }
    
    // Method 2: WordPress nonce authentication
    $nonce = $request->get_header('x-wp-nonce');
    if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
        return is_user_logged_in() ? wp_get_current_user() : false;
    }
    
    // Method 3: OAuth/JWT token (if implemented)
    $auth_header = $request->get_header('authorization');
    if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
        $token = substr($auth_header, 7);
        return accessSchema_validate_jwt_token($token);
    }
    
    return false;
}

function accessSchema_validate_api_key($api_key) {
    // Validate API key format
    if (!preg_match('/^as_[a-zA-Z0-9]{32}$/', $api_key)) {
        return false;
    }
    
    // Check against stored keys with timing-safe comparison
    $stored_keys = [
        'read' => get_option('accessschema_api_key_readonly'),
        'write' => get_option('accessschema_api_key_readwrite')
    ];
    
    foreach ($stored_keys as $type => $stored_key) {
        if ($stored_key && hash_equals($stored_key, $api_key)) {
            return (object) [
                'type' => $type,
                'key' => $api_key,
                'authenticated' => true
            ];
        }
    }
    
    return false;
}

function accessSchema_api_read_permission_check($request) {
    $auth = accessSchema_api_authenticate($request);
    
    if (!$auth) {
        return new WP_Error('unauthorized', 'Invalid authentication', ['status' => 401]);
    }
    
    // Check rate limiting
    if (!accessSchema_check_rate_limit($request)) {
        return new WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
    }
    
    // Log API access
    accessSchema_log_api_request($request, $auth);
    
    return true;
}

function accessSchema_api_write_permission_check($request) {
    $auth = accessSchema_api_authenticate($request);
    
    if (!$auth) {
        return new WP_Error('unauthorized', 'Invalid authentication', ['status' => 401]);
    }
    
    // Write operations require write key or admin user
    if (is_object($auth) && isset($auth->type) && $auth->type === 'read') {
        return new WP_Error('forbidden', 'Write operation not allowed with read-only key', ['status' => 403]);
    }
    
    // Check rate limiting (stricter for write operations)
    if (!accessSchema_check_rate_limit($request, 30)) {
        return new WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
    }
    
    // Log API access
    accessSchema_log_api_request($request, $auth);
    
    return true;
}

/**
 * Enhanced rate limiting with Redis/Memcached support
 */
function accessSchema_check_rate_limit($request, $limit = null) {
    if (!get_option('accessschema_enable_rate_limiting', true)) {
        return true;
    }
    
    $identifier = accessSchema_get_rate_limit_identifier($request);
    $window = get_option('accessschema_rate_limit_window', 60);
    $limit = $limit ?: get_option('accessschema_rate_limit_requests', 60);
    
    // Try Redis/Memcached first
    if (function_exists('wp_cache_get')) {
        $key = 'rate_limit_' . $identifier;
        $current = wp_cache_get($key, 'accessschema_rate_limit');
        
        if ($current === false) {
            wp_cache_set($key, 1, 'accessschema_rate_limit', $window);
            return true;
        }
        
        if ($current >= $limit) {
            return false;
        }
        
        wp_cache_incr($key, 1, 'accessschema_rate_limit');
        return true;
    }
    
    // Fallback to transients
    $transient_key = 'accessschema_rate_' . md5($identifier);
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, $window);
        return true;
    }
    
    if ($attempts >= $limit) {
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, $window);
    return true;
}

function accessSchema_get_rate_limit_identifier($request) {
    // Use API key if available
    $api_key = $request->get_header('x-api-key');
    if ($api_key) {
        return 'key_' . substr(md5($api_key), 0, 16);
    }
    
    // Use user ID if authenticated
    if (is_user_logged_in()) {
        return 'user_' . get_current_user_id();
    }
    
    // Use IP address as fallback
    $ip = accessSchema_get_client_ip();
    return 'ip_' . md5($ip);
}

/**
 * Enhanced user resolution with validation
 */
function accessSchema_resolve_user($params) {
    // Validate input
    if (empty($params) || (!isset($params['id']) && !isset($params['email']))) {
        return null;
    }
    
    // By ID
    if (!empty($params['id'])) {
        $user_id = absint($params['id']);
        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user && !is_wp_error($user)) {
                return $user;
            }
        }
    }
    
    // By email
    if (!empty($params['email']) && is_email($params['email'])) {
        $user = get_user_by('email', sanitize_email($params['email']));
        if ($user && !is_wp_error($user)) {
            return $user;
        }
    }
    
    return null;
}

/**
 * API endpoint argument schemas
 */
function accessSchema_get_register_args() {
    return [
        'paths' => [
            'required' => true,
            'type' => 'array',
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'pattern' => '^[A-Za-z0-9_\-\s]+$'
                ]
            ],
            'validate_callback' => function($value) {
                return is_array($value) && !empty($value);
            }
        ]
    ];
}

function accessSchema_get_user_args() {
    return [
        'id' => [
            'type' => 'integer',
            'minimum' => 1
        ],
        'email' => [
            'type' => 'string',
            'format' => 'email'
        ]
    ];
}

/**
 * Enhanced API logging
 */
function accessSchema_log_api_request($request, $auth) {
    $context = [
        'method' => $request->get_method(),
        'route' => $request->get_route(),
        'params' => $request->get_params(),
        'auth_type' => is_object($auth) ? get_class($auth) : gettype($auth),
        'ip' => accessSchema_get_client_ip(),
        'user_agent' => $request->get_header('user-agent')
    ];
    
    // Remove sensitive data
    if (isset($context['params']['password'])) {
        $context['params']['password'] = '[REDACTED]';
    }
    
    accessSchema_log_event(
        is_object($auth) && method_exists($auth, 'ID') ? $auth->ID : 0,
        'api_request',
        $request->get_route(),
        $context,
        null,
        'INFO'
    );
}

/**
 * API Response handlers with consistent format
 */
function accessSchema_api_response($data, $message = '', $status = 200) {
    $response = [
        'success' => $status >= 200 && $status < 300,
        'data' => $data,
        'message' => $message,
        'timestamp' => current_time('c')
    ];
    
    return new WP_REST_Response($response, $status);
}

function accessSchema_api_error($message, $code = 'error', $status = 400, $data = null) {
    $response = [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'data' => $data
        ],
        'timestamp' => current_time('c')
    ];
    
    return new WP_REST_Response($response, $status);
}
```

### 6. **assets/js/accessSchema.js**
**Issues:**
- jQuery dependency without proper checks
- No error handling
- Not using modern JavaScript
- No debouncing for filter input
- Memory leaks from event handlers

**Fix:**
```javascript
/**
 * AccessSchema Admin JavaScript
 * @version 1.7.0
 */
(function(window, document, $) {
    'use strict';
    
    // Check jQuery availability
    if (typeof $ === 'undefined') {
        console.error('AccessSchema: jQuery is required');
        return;
    }
    
    // Namespace
    const AccessSchema = {
        config: {
            debounceDelay: 300,
            animationSpeed: 200,
            select2Options: {
                placeholder: 'Select roles to add',
                width: 'resolve',
                allowClear: true,
                theme: 'default'
            }
        },
        
        // Cache DOM elements
        elements: {},
        
        // Initialize
        init() {
            this.cacheElements();
            this.bindEvents();
            this.initSelect2();
            this.initTooltips();
        },
        
        // Cache DOM elements for performance
        cacheElements() {
            this.elements = {
                form: document.querySelector('form'),
                roleButtons: document.querySelectorAll('.remove-role-button'),
                addRolesSelect: document.getElementById('accessSchema_add_roles'),
                filterInput: document.getElementById('accessSchema-role-filter'),
                rolesTable: document.getElementById('accessSchema-roles-table'),
                editButtons: document.querySelectorAll('.accessSchema-edit-role'),
                loadingOverlay: document.getElementById('accessSchema-loading')
            };
        },
        
        // Bind events with proper cleanup
        bindEvents() {
            // Role removal with event delegation
            if (this.elements.form) {
                this.elements.form.addEventListener('click', this.handleRoleRemoval.bind(this));
            }
            
            // Filter with debouncing
            if (this.elements.filterInput) {
                let debounceTimer;
                this.elements.filterInput.addEventListener('input', (e) => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        this.filterRoles(e.target.value);
                    }, this.config.debounceDelay);
                });
            }
            
            // Edit role buttons
            if (this.elements.editButtons.length) {
                this.elements.editButtons.forEach(btn => {
                    btn.addEventListener('click', this.handleRoleEdit.bind(this));
                });
            }
            
            // Form submission validation
            if (this.elements.form) {
                this.elements.form.addEventListener('submit', this.validateForm.bind(this));
            }
        },
        
        // Initialize Select2 with error handling
        initSelect2() {
            if (!$.fn.select2) {
                console.warn('AccessSchema: Select2 not loaded');
                return;
            }
            
            if (this.elements.addRolesSelect) {
                $(this.elements.addRolesSelect).select2({
                    ...this.config.select2Options,
                    ajax: {
                        url: accessSchemaAjax?.ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: (params) => ({
                            action: 'accessschema_search_roles',
                            search: params.term,
                            page: params.page || 1,
                            _ajax_nonce: accessSchemaAjax?.nonce
                        }),
                        processResults: (data) => ({
                            results: data.results || [],
                            pagination: {
                                more: data.pagination?.more || false
                            }
                        }),
                        cache: true
                    },
                    minimumInputLength: 2
                });
            }
        },
        
        // Handle role removal
        handleRoleRemoval(e) {
            if (!e.target.classList.contains('remove-role-button')) return;
            
            e.preventDefault();
            
            const button = e.target;
            const rolePath = button.dataset.role;
            const chip = button.closest('.access-role-tag');
            
            if (!rolePath || !chip) return;
            
            // Confirm removal
            if (!confirm(`Remove role "${rolePath}"?`)) return;
            
            // Create hidden input
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'accessSchema_remove_roles[]';
            input.value = rolePath;
            
            // Animate removal
            chip.style.transition = `opacity ${this.config.animationSpeed}ms`;
            chip.style.opacity = '0';
            
            setTimeout(() => {
                chip.style.display = 'none';
                this.elements.form.appendChild(input);
            }, this.config.animationSpeed);
        },
        
        // Filter roles in table
        filterRoles(query) {
            const lowerQuery = query.toLowerCase();
            const rows = this.elements.rolesTable?.querySelectorAll('tbody tr');
            
            if (!rows) return;
            
            let visibleCount = 0;
            
            rows.forEach(row => {
                const pathCell = row.querySelector('.role-path');
                if (!pathCell) return;
                
                const path = pathCell.textContent.toLowerCase();
                const isVisible = path.includes(lowerQuery);
                
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });
            
            // Show no results message
            this.updateNoResultsMessage(visibleCount);
        },
        
        // Update no results message
        updateNoResultsMessage(count) {
            let noResultsRow = this.elements.rolesTable?.querySelector('.no-results');
            
            if (count === 0) {
                if (!noResultsRow) {
                    const tbody = this.elements.rolesTable?.querySelector('tbody');
                    if (tbody) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results';
                        noResultsRow.innerHTML = '<td colspan="3" style="text-align: center;">No roles found</td>';
                        tbody.appendChild(noResultsRow);
                    }
                }
            } else if (noResultsRow) {
                noResultsRow.remove();
            }
        },
        
        // Handle role editing
        handleRoleEdit(e) {
            e.preventDefault();
            
            const button = e.currentTarget;
            const roleId = button.dataset.id;
            const roleName = button.dataset.name;
            const rolePath = button.dataset.path;
            
            // Open modal or inline edit
            this.openEditModal({
                id: roleId,
                name: roleName,
                path: rolePath
            });
        },
        
        // Open edit modal
        openEditModal(roleData) {
            // Implementation for modal editing
            console.log('Edit role:', roleData);
            // TODO: Implement modal UI
        },
        
        // Validate form before submission
        validateForm(e) {
            const addedRoles = $(this.elements.addRolesSelect).val();
            const removedRoles = this.elements.form.querySelectorAll('input[name="accessSchema_remove_roles[]"]');
            
            if (!addedRoles?.length && !removedRoles.length) {
                e.preventDefault();
                this.showNotification('No changes to save', 'warning');
                return false;
            }
            
            return true;
        },
        
        // Initialize tooltips
        initTooltips() {
            if (typeof tippy !== 'undefined') {
                tippy('[data-tippy-content]', {
                    placement: 'top',
                    arrow: true,
                    animation: 'fade'
                });
            }
        },
        
        // Show notification
        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notice notice-${type} is-dismissible`;
            notification.innerHTML = `<p>${message}</p>`;
            
            const adminNotices = document.querySelector('.wp-header-end');
            if (adminNotices) {
                adminNotices.parentNode.insertBefore(notification, adminNotices.nextSibling);
                
                // Auto dismiss after 5 seconds
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 5000);
            }
        },
        
        // Cleanup on page unload
        destroy() {
            // Destroy Select2
            if (this.elements.addRolesSelect && $.fn.select2) {
                $(this.elements.addRolesSelect).select2('destroy');
            }
            
            // Remove event listeners
            if (this.elements.filterInput) {
                this.elements.filterInput.removeEventListener('input', this.filterRoles);
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => AccessSchema.init());
    } else {
        AccessSchema.init();
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', () => AccessSchema.destroy());
    
    // Export for external use
    window.AccessSchema = AccessSchema;
    
})(window, document, jQuery);
```

### 7. **includes/admin/role-manager.php**
**Issues:**
- SQL injection vulnerabilities
- No bulk operation support properly implemented
- Missing export functionality
- No AJAX handlers for better UX
- Direct superglobal access

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

/**
 * Register admin menu with proper capability checks
 */
function accessSchema_register_admin_menu() {
    $capability = apply_filters('accessschema_menu_capability', 'manage_access_schema');
    
    add_users_page(
        __('Access Schema Roles', 'accessschema'),
        __('Access Schema', 'accessschema'),
        $capability,
        'accessschema-roles',
        'accessSchema_render_role_manager_page'
    );
}
add_action('admin_menu', 'accessSchema_register_admin_menu');

/**
 * Render role manager page with enhanced UI
 */
function accessSchema_render_role_manager_page() {
    if (!current_user_can('manage_access_schema')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'accessschema'));
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] !== '-1') {
        accessSchema_handle_bulk_action();
    }
    
    // Get messages
    $messages = accessSchema_get_admin_messages();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Access Schema Role Registry', 'accessschema'); ?></h1>
        <a href="#" class="page-title-action" id="accessschema-add-new"><?php _e('Add New', 'accessschema'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=accessschema-import')); ?>" class="page-title-action"><?php _e('Import', 'accessschema'); ?></a>
        <hr class="wp-header-end">
        
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $message): ?>
                <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
                    <p><?php echo esc_html($message['text']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="accessschema-admin-content">
            <?php accessSchema_render_add_role_form(); ?>
            <?php accessSchema_render_roles_table(); ?>
        </div>
    </div>
    <?php
}

/**
 * Render add role form with AJAX support
 */
function accessSchema_render_add_role_form() {
    ?>
    <div id="accessschema-add-form" style="display: none;">
        <form method="post" id="accessschema-role-form">
            <?php wp_nonce_field('accessschema_add_role_action', 'accessschema_add_role_nonce'); ?>
            <h2><?php _e('Add Role(s)', 'accessschema'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="full_paths"><?php _e('Full Role Path(s)', 'accessschema'); ?></label></th>
                    <td>
                        <textarea 
                            name="full_paths" 
                            id="full_paths" 
                            rows="6" 
                            class="large-text"
                            placeholder="<?php esc_attr_e('Example: Chronicles/KONY/HST', 'accessschema'); ?>"
                        ></textarea>
                        <p class="description">
                            <?php _e('Enter one full role path per line using <code>/</code> between each level.', 'accessschema'); ?><br>
                            <?php _e('Example: <code>Chronicles/KONY/HST</code>', 'accessschema'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Add Role(s)', 'accessschema'), 'primary', 'submit', false); ?>
            <button type="button" class="button" id="accessschema-cancel-add"><?php _e('Cancel', 'accessschema'); ?></button>
        </form>
    </div>
    <?php
}

/**
 * Render roles table with enhanced features
 */
function accessSchema_render_registered_roles_table() {
    global $wpdb;
    
    // Get pagination parameters
    $per_page = get_user_meta(get_current_user_id(), 'accessschema_roles_per_page', true) ?: 25;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get filter parameters
    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';
    $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'full_path';
    $order = isset($_GET['order']) && in_array($_GET['order'], ['asc', 'desc']) ? $_GET['order'] : 'asc';
    
    // Build query with proper escaping
    $table = $wpdb->prefix . 'access_roles';
    $where_clause = '';
    $where_values = [];
    
    if ($filter) {
        $where_clause = 'WHERE full_path LIKE %s OR name LIKE %s';
        $like_filter = '%' . $wpdb->esc_like($filter) . '%';
        $where_values = [$like_filter, $like_filter];
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM {$table} {$where_clause}";
    $total_items = $wpdb->get_var(
        $where_values ? $wpdb->prepare($count_query, ...$where_values) : $count_query
    );
    
    // Get roles
    $query = "SELECT * FROM {$table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
    $query_values = array_merge($where_values, [$per_page, $offset]);
    
    $roles = $wpdb->get_results(
        $wpdb->prepare($query, ...$query_values),
        ARRAY_A
    );
    
    // Calculate pagination
    $total_pages = ceil($total_items / $per_page);
    
    ?>
    <form method="post" id="accessschema-roles-form">
        <?php wp_nonce_field('accessschema_bulk_action', 'accessschema_bulk_nonce'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'accessschema'); ?></label>
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'accessschema'); ?></option>
                    <option value="delete"><?php _e('Delete', 'accessschema'); ?></option>
                    <option value="export"><?php _e('Export', 'accessschema'); ?></option>
                    <option value="activate"><?php _e('Activate', 'accessschema'); ?></option>
                    <option value="deactivate"><?php _e('Deactivate', 'accessschema'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'accessschema'); ?>">
            </div>
            
            <div class="alignleft actions">
                <input 
                    type="text" 
                    name="filter" 
                    id="accessschema-role-filter" 
                    placeholder="<?php esc_attr_e('Filter roles...', 'accessschema'); ?>" 
                    value="<?php echo esc_attr($filter); ?>"
                >
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'accessschema'); ?>">
            </div>
            
            <?php accessSchema_render_pagination($current_page, $total_pages); ?>
        </div>
        
        <table class="wp-list-table widefat fixed striped" id="accessschema-roles-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <?php accessSchema_render_column_headers($orderby, $order); ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roles)): ?>
                    <tr>
                        <td colspan="5"><?php _e('No roles found.', 'accessschema'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($roles as $role): ?>
                        <?php accessSchema_render_role_row($role); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-2">
                    </td>
                    <?php accessSchema_render_column_headers($orderby, $order); ?>
                </tr>
            </tfoot>
        </table>
        
        <div class="tablenav bottom">
            <?php accessSchema_render_pagination($current_page, $total_pages); ?>
        </div>
    </form>
    <?php
}

/**
 * AJAX handlers for better UX
 */
add_action('wp_ajax_accessschema_add_role', 'accessSchema_ajax_add_role');
function accessSchema_ajax_add_role() {
    check_ajax_referer('accessschema_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_access_schema')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'accessschema')]);
    }
    
    $paths = isset($_POST['paths']) ? sanitize_textarea_field($_POST['paths']) : '';
    
    if (empty($paths)) {
        wp_send_json_error(['message' => __('No paths provided', 'accessschema')]);
    }
    
    $lines = explode("\n", $paths);
    $results = accessSchema_process_role_paths($lines);
    
    if ($results['added'] > 0) {
        wp_send_json_success([
            'message' => sprintf(
                __('%d role(s) added successfully.', 'accessschema'),
                $results['added']
            ),
            'added' => $results['added'],
            'skipped' => $results['skipped']
        ]);
    } else {
        wp_send_json_error([
            'message' => __('No roles were added.', 'accessschema'),
            'skipped' => $results['skipped']
        ]);
    }
}

add_action('wp_ajax_accessschema_delete_role', 'accessSchema_ajax_delete_role');
function accessSchema_ajax_delete_role() {
    check_ajax_referer('accessschema_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_access_schema')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'accessschema')]);
    }
    
    $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
    
    if (!$role_id) {
        wp_send_json_error(['message' => __('Invalid role ID', 'accessschema')]);
    }
    
    $result = accessSchema_delete_role_cascade($role_id);
    
    if ($result) {
        wp_send_json_success(['message' => __('Role deleted successfully.', 'accessschema')]);
    } else {
        wp_send_json_error(['message' => __('Failed to delete role.', 'accessschema')]);
    }
}

add_action('wp_ajax_accessschema_export_roles', 'accessSchema_ajax_export_roles');
function accessSchema_ajax_export_roles() {
    check_ajax_referer('accessschema_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_access_schema')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'accessschema')]);
    }
    
    $format = isset($_POST['format']) ? sanitize_key($_POST['format']) : 'csv';
    $role_ids = isset($_POST['role_ids']) ? array_map('intval', $_POST['role_ids']) : [];
    
    $export_data = accessSchema_export_roles($role_ids, $format);
    
    if ($export_data) {
        wp_send_json_success([
            'data' => $export_data,
            'filename' => 'access_roles_' . date('Y-m-d') . '.' . $format
        ]);
    } else {
        wp_send_json_error(['message' => __('Export failed.', 'accessschema')]);
    }
}
```

### 8. **includes/core/permission-checks.php**
**Issues:**
- Inefficient permission checking
- No caching of permission results
- Missing wildcard optimization
- No batch permission checking

**Fix:**
```php
<?php
defined('ABSPATH') || exit;

/**
 * Enhanced permission checking with caching and optimization
 */
class AccessSchema_Permissions {
    private static $permission_cache = [];
    private static $wildcard_cache = [];
    
    /**
     * Check if a user has a given role with enhanced performance
     */
    public static function check($user_id, $target_path, $include_children = false, $log = true, $context = [], $allow_wildcards = false) {
        // Generate cache key
        $cache_key = self::generate_cache_key($user_id, $target_path, $include_children, $allow_wildcards);
        
        // Check cache first
        if (isset(self::$permission_cache[$cache_key])) {
            return self::$permission_cache[$cache_key];
        }
        
        // Validate inputs
        $user_id = absint($user_id);
        $target_path = trim($target_path);
        
        if (empty($target_path)) {
            return false;
        }
        
        // Get user roles with caching
        $roles = accessSchema_get_user_roles($user_id);
        
        if (empty($roles)) {
            if ($log) {
                accessSchema_log_event($user_id, 'access_denied', $target_path, [
                    'reason' => 'no_roles_assigned',
                    ...$context
                ], null, 'WARN');
            }
            return self::cache_result($cache_key, false);
        }
        
        // Check permissions
        $matched = false;
        
        if ($allow_wildcards && strpos($target_path, '*') !== false) {
            $matched = self::check_wildcard_pattern($target_path, $roles);
        } else {
            // Validate role exists
            if (!accessSchema_role_exists_cached($target_path)) {
                if ($log) {
                    accessSchema_log_event($user_id, 'role_check_invalid', $target_path, [
                        'reason' => 'role_not_registered',
                        ...$context
                    ], null, 'ERROR');
                }
                return self::cache_result($cache_key, false);
            }
            
            // Check exact match or children
            $matched = self::check_role_match($target_path, $roles, $include_children);
        }
        
        // Log result
        if ($log) {
            self::log_permission_check($user_id, $target_path, $matched, $context);
        }
        
        return self::cache_result($cache_key, $matched);
    }
    
    /**
     * Batch permission checking for performance
     */
    public static function check_multiple($user_id, array $permissions) {
        $results = [];
        $uncached = [];
        
        // Check cache first
        foreach ($permissions as $key => $permission) {
            $cache_key = self::generate_cache_key(
                $user_id,
                $permission['path'],
                $permission['include_children'] ?? false,
                $permission['allow_wildcards'] ?? false
            );
            
            if (isset(self::$permission_cache[$cache_key])) {
                $results[$key] = self::$permission_cache[$cache_key];
            } else {
                $uncached[$key] = $permission;
            }
        }
        
        // Process uncached permissions
        if (!empty($uncached)) {
            $user_roles = accessSchema_get_user_roles($user_id);
            
            foreach ($uncached as $key => $permission) {
                $results[$key] = self::check_single_permission(
                    $user_id,
                    $permission,
                    $user_roles
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Check single permission
     */
    private static function check_single_permission($user_id, $permission, $user_roles) {
        $target_path = $permission['path'];
        $include_children = $permission['include_children'] ?? false;
        $allow_wildcards = $permission['allow_wildcards'] ?? false;
        $log = $permission['log'] ?? false;
        $context = $permission['context'] ?? [];
        
        if (empty($user_roles)) {
            return false;
        }
        
        $matched = false;
        
        if ($allow_wildcards && strpos($target_path, '*') !== false) {
            $matched = self::check_wildcard_pattern($target_path, $user_roles);
        } else {
            if (!accessSchema_role_exists_cached($target_path)) {
                return false;
            }
            $matched = self::check_role_match($target_path, $user_roles, $include_children);
        }
        
        if ($log) {
            self::log_permission_check($user_id, $target_path, $matched, $context);
        }
        
        // Cache result
        $cache_key = self::generate_cache_key($user_id, $target_path, $include_children, $allow_wildcards);
        self::$permission_cache[$cache_key] = $matched;
        
        return $matched;
    }
    
    /**
     * Check role match with optimization
     */
    private static function check_role_match($target_path, $roles, $include_children) {
        // Exact match
        if (in_array($target_path, $roles, true)) {
            return true;
        }
        
        // Children check
        if ($include_children) {
            $prefix = $target_path . '/';
            foreach ($roles as $role) {
                if (strpos($role, $prefix) === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check wildcard pattern with caching
     */
    private static function check_wildcard_pattern($pattern, $roles) {
        // Check wildcard cache
        $cache_key = md5($pattern);
        if (!isset(self::$wildcard_cache[$cache_key])) {
            self::$wildcard_cache[$cache_key] = accessSchema_pattern_to_regex($pattern);
        }
        
        $regex = self::$wildcard_cache[$cache_key];
        
        foreach ($roles as $role) {
            if (preg_match($regex, $role)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate cache key
     */
    private static function generate_cache_key($user_id, $target_path, $include_children, $allow_wildcards) {
        return sprintf(
            '%d:%s:%d:%d',
            $user_id,
            $target_path,
            $include_children ? 1 : 0,
            $allow_wildcards ? 1 : 0
        );
    }
    
    /**
     * Cache and return result
     */
    private static function cache_result($cache_key, $result) {
        self::$permission_cache[$cache_key] = $result;
        return $result;
    }
    
    /**
     * Log permission check
     */
    private static function log_permission_check($user_id, $target_path, $matched, $context) {
        $default_context = [
            'route' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
            'ip' => accessSchema_get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : ''
        ];
        
        $context = array_merge($default_context, $context);
        
        accessSchema_log_event(
            $user_id,
            $matched ? 'access_granted' : 'access_denied',
            $target_path,
            $context,
            null,
            $matched ? 'INFO' : 'WARN'
        );
    }
    
    /**
     * Clear permission cache for user
     */
    public static function clear_user_cache($user_id) {
        $user_id = absint($user_id);
        $pattern = '/^' . $user_id . ':/';
        
        foreach (array_keys(self::$permission_cache) as $key) {
            if (preg_match($pattern, $key)) {
                unset(self::$permission_cache[$key]);
            }
        }
    }
    
    /**
     * Clear all permission caches
     */
    public static function clear_all_caches() {
        self::$permission_cache = [];
        self::$wildcard_cache = [];
    }
}

// Wrapper functions for backward compatibility
function accessSchema_check_permission($user_id, $target_path, $include_children = false, $log = true, $context = [], $allow_wildcards = false) {
    return AccessSchema_Permissions::check($user_id, $target_path, $include_children, $log, $context, $allow_wildcards);
}

function accessSchema_role_exists_cached($role_path) {
    static $cache = [];
    
    if (isset($cache[$role_path])) {
        return $cache[$role_path];
    }
    
    $cache_key = 'role_exists_' . md5($role_path);
    $cached = AccessSchema_Cache::get($cache_key);
    
    if ($cached !== false) {
        $cache[$role_path] = $cached;
        return $cached;
    }
    
    $exists = accessSchema_role_exists($role_path);
    
    AccessSchema_Cache::set($cache_key, $exists, ACCESSSCHEMA_CACHE_GROUP, 3600);
    $cache[$role_path] = $exists;
    
    return $exists;
}

function accessSchema_user_can($user_id, $pattern) {
    return AccessSchema_Permissions::check($user_id, $pattern, false, false, [], true);
}

function accessSchema_user_matches_role_pattern($user_id, $pattern) {
    return AccessSchema_Permissions::check($user_id, $pattern, false, false, [], true);
}

function accessSchema_user_matches_any($user_id, array $patterns) {
    foreach ($patterns as $pattern) {
        if (AccessSchema_Permissions::check($user_id, $pattern, false, false, [], true)) {
            return true;
        }
    }
    return false;
}

function accessSchema_pattern_to_regex($pattern) {
    // Escape regex characters except * and **
    $escaped = preg_quote($pattern, '#');
    
    // Replace escaped wildcards with regex patterns
    $regex = strtr($escaped, [
        '\\*\\*' => '.*',        // ** matches any number of segments
        '\\*' => '[^/]+',        // * matches one segment
    ]);
    
    // Ensure full path match
    return "#^{$regex}$#";
}

// Hook to clear cache when roles change
add_action('accessschema_role_added', function($user_id) {
    AccessSchema_Permissions::clear_user_cache($user_id);
}, 10, 1);

add_action('accessschema_role_removed', function($user_id) {
    AccessSchema_Permissions::clear_user_cache($user_id);
}, 10, 1);
```
        }
        
        return $results;
    }
    
    /**
     * Check single permission
     */
    private static function check_single_permission($user_id, $permission, $user_roles) {
        $target_path = $permission['path'];
        $include_children = $permission['include_children'] ?? false;
        $allow_wildcards = $permission['allow_wildcards'] ?? false;
        $log = $permission['log'] ?? false;
        $context = $permission['context'] ?? [];
        
        if (empty($user_roles)) {
            return false;
        }
        
        $matched = false;
        
        if ($allow_wildcards && strpos($target_path, '*') !== false) {
            $matched = self::check_wildcard_pattern($target_path, $user_roles);
        } else {
            if (!accessSchema_role_exists_cached($target_path)) {
                return false;
            }
            $matched = self::check_role_match($target_path, $user_roles, $include_children);
        }
        
        if ($log) {
            self::log_permission_check($user_id, $target_path, $matched, $context);
        }
        
        // Cache result
        $cache_key = self::generate_cache_key($user_id, $target_path, $include_children, $allow_wildcards);
        self::$permission_cache[$cache_key] = $matched;
        
        return $matched;
    }
    
    /**
     * Check role match with optimization
     */
    private static function check_role_match($target_path, $roles, $include_children) {
        // Exact match
        if (in_array($target_path, $roles, true)) {
            return true;
        }
        
        // Children check
        if ($include_children) {
            $prefix = $target_path . '/';
            foreach ($roles as $role) {
                if (strpos($role, $prefix) === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check wildcard pattern with caching
     */
    private static function check_wildcard_pattern($pattern, $roles) {
        // Check wildcard cache
        $cache_key = md5($pattern);
        if (!isset(self::$wildcard_cache[$cache_key])) {
            self::$wildcard_cache[$cache_key] = accessSchema_pattern_to_regex($pattern);
        }
        
        $regex = self::$wildcard_cache[$cache_key];
        
        foreach ($roles as $role) {
            if (preg_match($regex, $role)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate cache key
     */
    private static function generate_cache_key($user_id, $target_path, $include_children, $allow_wildcards) {
        return sprintf(
            '%d:%s:%d:%d',
            $user_id,
            $target_path,
            $include_children ? 1 : 0,
            $allow_wildcards ? 1 : 0
        );
    }
    
    /**
     * Cache and return result
     */
    private static function cache_result($cache_key, $result) {
        self::$permission_cache[$cache_key] = $result;
        return $result;
    }
    
    /**
     * Log permission check
     */
    private static function log_permission_check($user_id, $target_path, $matched, $context) {
        $default_context = [
            'route' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
            'ip' => accessSchema_get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : ''
        ];
        
        $context = array_merge($default_context, $context);
        
        accessSchema_log_event(
            $user_id,
            $matched ? 'access_granted' : 'access_denied',
            $target_path,
            $context,
            null,
            $matched ? 'INFO' : 'WARN'
        );
    }
    
    /**
     * Clear permission cache for user
     */
    public static function clear_user_cache($user_id) {
        $user_id = absint($user_id);
        $pattern = '/^' . $user_id . ':/';
        
        foreach (array_keys(self::$permission_cache) as $key) {
            if (preg_match($pattern, $key)) {
                unset(self::$permission_cache[$key]);
            }
        }
    }
    
    /**
     * Clear all permission caches
     */
    public static function clear_all_caches() {
        self::$permission_cache = [];
        self::$wildcard_cache = [];
    }
}

// Wrapper functions for backward compatibility
function accessSchema_check_permission($user_id, $target_path, $include_children = false, $log = true, $context = [], $allow_wildcards = false) {
    return AccessSchema_Permissions::check($user_id, $target_path, $include_children, $log, $context, $allow_wildcards);
}

function accessSchema_role_exists_cached($role_path) {
    static $cache = [];
    
    if (isset($cache[$role_path])) {
        return $cache[$role_path];
    }
    
    $cache_key = 'role_exists_' . md5($role_path);
    $cached = AccessSchema_Cache::get($cache_key);
    
    if ($cached !== false) {
        $cache[$role_path] = $cached;
        return $cached;
    }
    
    $exists = accessSchema_role_exists($role_path);
    
    AccessSchema_Cache::set($cache_key, $exists, ACCESSSCHEMA_CACHE_GROUP, 3600);
    $cache[$role_path] = $exists;
    
    return $exists;
}

function accessSchema_user_can($user_id, $pattern) {
    return AccessSchema_Permissions::check($user_id, $pattern, false, false, [], true);
}

function accessSchema_user_matches_role_pattern($user_id, $pattern) {
    return AccessSchema_Permissions::check($user_id, $pattern, false, false, [], true);
}

function accessSchema_user_matches_any($user_id, array $patterns) {
    foreach ($patterns as $pattern) {
        if (AccessSchema_Permissions::check($user_id, $pattern, false, false, [], true)) {
            return true;
        }
    }
    return false;
}

function accessSchema_pattern_to_regex($pattern) {
    // Escape regex characters except * and **
    $escaped = preg_quote($pattern, '#');
    
    // Replace escaped wildcards with regex patterns
    $regex = strtr($escaped, [
        '\\*\\*' => '.*',        // ** matches any number of segments
        '\\*' => '[^/]+',        // * matches one segment
    ]);
    
    // Ensure full path match
    return "#^{$regex}$#";
}

// Hook to clear cache when roles change
add_action('accessschema_role_added', function($user_id) {
    AccessSchema_Permissions::clear_user_cache($user_id);
}, 10, 1);

add_action('accessschema_role_removed', function($user_id) {
    AccessSchema_Permissions::clear_user_cache($user_id);
}, 10, 1);