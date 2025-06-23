<?php
/**
 * WordPress Coding Standards Fixes for accessSchema Plugin
 * 
 * Apply these changes to fix security and coding standards issues
 */

// ===== ROLE-MANAGER.PHP FIXES =====

// Fix unescaped output (line 23)
wp_die( esc_html__('Insufficient permissions', 'accessschema') );

// Fix paginate_links output (line 163)
echo wp_kses_post( paginate_links($pagination_args) );

// Fix SQL preparation (lines 103-125)
$per_page = 25;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

// Build query with proper preparation
$where_conditions = array("is_active = 1");
$query_params = array();

if ($search) {
    $where_conditions[] = "(name LIKE %s OR full_path LIKE %s)";
    $like = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = $like;
    $query_params[] = $like;
}

$where = "WHERE " . implode(' AND ', $where_conditions);

// Count query
$count_sql = "SELECT COUNT(*) FROM `{$wpdb->prefix}accessSchema_roles` {$where}";
if (!empty($query_params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_sql, ...$query_params));
} else {
    $total_items = $wpdb->get_var($count_sql);
}

// Get roles
$roles_sql = "SELECT * FROM `{$wpdb->prefix}accessSchema_roles` {$where} ORDER BY full_path LIMIT %d OFFSET %d";
$final_params = array_merge($query_params, array($per_page, $offset));
$roles = $wpdb->get_results($wpdb->prepare($roles_sql, ...$final_params), ARRAY_A);

// Fix nonce checks (lines 73-75)
if (isset($_GET['confirm_delete']) && isset($_GET['nonce']) && 
    wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'confirm_delete_' . absint($_GET['confirm_delete']))) {

// Fix POST data handling (lines 261-268)
if (isset($_POST['submit']) && isset($_POST['accessSchema_add_role_nonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['accessSchema_add_role_nonce'])), 'accessSchema_add_role_action')) {
    
    $paths = isset($_POST['full_paths']) ? sanitize_textarea_field(wp_unslash($_POST['full_paths'])) : '';

// Fix _wpnonce verification (line 297)
$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
if (!wp_verify_nonce($nonce, 'delete_role_' . $role_id)) {
    return;
}

// Fix bulk actions (line 319)
$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
if (!wp_verify_nonce($nonce, 'bulk-roles')) {
    return;
}

// Fix action handling (line 327)
$action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

// Fix date function (line 367)
header('Content-Disposition: attachment; filename="accessSchema_roles_' . gmdate('Y-m-d') . '.csv"');

// Fix file operations (line 385) - Remove direct fclose()
// Output CSV without using fclose
echo chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM
foreach ($roles as $role) {
    echo '"' . str_replace('"', '""', $role['id']) . '",';
    echo '"' . str_replace('"', '""', $role['parent_id']) . '",';
    echo '"' . str_replace('"', '""', $role['name']) . '",';
    echo '"' . str_replace('"', '""', $role['slug']) . '",';
    echo '"' . str_replace('"', '""', $role['full_path']) . '",';
    echo '"' . str_replace('"', '""', $role['depth']) . '"' . PHP_EOL;
}
exit;

// ===== SETTINGS.PHP FIXES =====

// Fix nonce verification (lines 219, 229)
$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
if (wp_verify_nonce($nonce, 'clear_cache')) {
    // ...
}

// Fix date function (line 254)
header('Content-Disposition: attachment; filename="accessschema-settings-' . gmdate('Y-m-d') . '.json"');

// ===== CACHE.PHP FIXES =====

// Fix date function (line 255)
$cutoff = gmdate('Y-m-d H:i:s', time() - $ttl);

// Fix table check queries
$cache_table = $wpdb->prefix . 'accessSchema_permissions_cache';
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME,
    $cache_table
));

// ===== INIT.PHP FIXES =====

// Fix nonce checks (lines 105, 120)
$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
if (!wp_verify_nonce($nonce, 'accessSchema_ajax_nonce')) {
    wp_send_json_error(array('message' => __('Security check failed.', 'accessschema')));
}

// Fix date function (line 154)
$wpdb->query($wpdb->prepare(
    "DELETE FROM `{$wpdb->prefix}accessSchema_audit_log` WHERE created_at < %s",
    gmdate('Y-m-d H:i:s', strtotime('-90 days'))
));

// Remove error_log (line 159)
// Delete this line completely

// ===== LOGGING.PHP FIXES =====

// Fix server variables (lines 57, 58, 64, 117, 127)
$request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
$http_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

// Fix IP detection (line 148)
foreach ($ip_keys as $key) {
    if (array_key_exists($key, $_SERVER) === true) {
        $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])));
        foreach ($ips as $ip) {
            $ip = trim($ip);

// Fix REMOTE_ADDR (line 158)
return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';

// Fix date in cleanup (line 171)
$cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

// ===== WEBHOOK-ROUTER.PHP FIXES =====

// Fix date function (line 370)
$expires_at = gmdate('Y-m-d H:i:s', $expires_timestamp);

// Fix REQUEST_METHOD (line 180)
'method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : ''

// ===== PERMISSION-CHECKS.PHP FIXES =====

// Fix server variables (lines 116-119)
$default_context = array(
    'request_uri' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '',
    'http_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
    'ip' => accessSchema_get_client_ip(),
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : ''
);

// ===== RENDER-ADMIN.PHP FIXES =====

// Fix nonce verification (line 228)
$nonce = isset($_POST['accessSchema_user_roles_nonce']) ? sanitize_text_field(wp_unslash($_POST['accessSchema_user_roles_nonce'])) : '';
if (!wp_verify_nonce($nonce, 'accessSchema_user_roles_action')) {
    return;
}

// Fix POST data (line 248)
$removed_roles = isset($_POST['accessSchema_removed_roles']) ? 
    sanitize_text_field(wp_unslash($_POST['accessSchema_removed_roles'])) : '';

// Fix AJAX handler (line 286)
$role_path = isset($_POST['role_path']) ? sanitize_text_field(wp_unslash($_POST['role_path'])) : '';

// ===== REMOVE ALL error_log() CALLS =====
// Replace all instances of error_log() with:
if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    // Use WordPress debug logging instead
    // Example: trigger_error('accessSchema: ' . $message, E_USER_NOTICE);
}

// ===== FIX SQL TABLE INTERPOLATION =====
// For all queries using {$table}, use backticks:
$sql = "SELECT * FROM `{$wpdb->prefix}accessSchema_roles` WHERE ...";

// ===== FIX DROP TABLE QUERIES IN UNINSTALL.PHP =====
// Use IF EXISTS syntax properly:
$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}accessSchema_user_roles`");
$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}accessSchema_roles`");
$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}accessSchema_audit_log`");
$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}accessSchema_permissions_cache`");
$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}accessSchema_rate_limits`");