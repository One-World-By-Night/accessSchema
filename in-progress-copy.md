# AccessSchema Complete Code Review & Optimization

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
 * Version: 2.0.0
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
define('ACCESSSCHEMA_VERSION', '2.0.0');
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
const ACCESSSCHEMA_CACHE_VERSION = '2.0.0';

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
 * @version 2.0.0
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

