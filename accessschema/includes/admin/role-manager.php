<?php
/**
 * File: includes/admin/role-manager.php
 * @version 2.0.3
 * Author: greghacke
 */

defined('ABSPATH') || exit;

function accessSchema_register_admin_menu() {
    add_users_page(
        __('Access Schema Roles', 'accessschema'),
        __('accessSchema', 'accessschema'),
        'manage_access_schema',
        'accessSchema-roles',
        'accessSchema_render_role_manager_page'
    );
}
add_action('admin_menu', 'accessSchema_register_admin_menu');

function accessSchema_render_role_manager_page() {
    if (!current_user_can('manage_access_schema')) {
        wp_die(__('Insufficient permissions', 'accessschema'));
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Access Schema Role Registry', 'accessschema'); ?></h1>
        
        <?php accessSchema_show_admin_notices(); ?>
        
        <form method="post" id="accessSchema-add-role-form">
            <?php wp_nonce_field('accessSchema_add_role_action', 'accessSchema_add_role_nonce'); ?>
            <h2><?php esc_html_e('Add Role(s)', 'accessschema'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="full_paths"><?php esc_html_e('Full Role Path(s)', 'accessschema'); ?></label></th>
                    <td>
                        <textarea name="full_paths" id="full_paths" rows="6" style="width: 100%;" placeholder="Example: Coordinators/Brujah/Subcoordinator"></textarea>
                        <p class="description">
                            <?php esc_html_e('Enter one full role path per line using / between each level.', 'accessschema'); ?><br>
                            <?php esc_html_e('Example:', 'accessschema'); ?> <code>Chronicles/KONY/HST</code>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Add Role(s)', 'accessschema'), 'primary', 'submit'); ?>
        </form>
        
        <hr />
        
        <?php accessSchema_render_registered_roles_table(); ?>
    </div>
    
    <div id="accessSchema-edit-modal" style="display:none;">
        <div class="accessSchema-modal-content">
            <h3><?php esc_html_e('Edit Role', 'accessschema'); ?></h3>
            <form id="accessSchema-edit-form">
                <?php wp_nonce_field('accessSchema_edit_role', 'edit_nonce'); ?>
                <input type="hidden" id="edit_role_id" name="role_id">
                <label><?php esc_html_e('Role Name:', 'accessschema'); ?></label>
                <input type="text" id="edit_role_name" name="role_name" class="regular-text">
                <div class="modal-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save', 'accessschema'); ?></button>
                    <button type="button" class="button" onclick="closeEditModal()"><?php esc_html_e('Cancel', 'accessschema'); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function accessSchema_show_admin_notices() {
    if (isset($_GET['confirm_delete']) && isset($_GET['nonce']) && 
        wp_verify_nonce($_GET['nonce'], 'confirm_delete_' . $_GET['confirm_delete'])) {
        
        $role_id = absint($_GET['confirm_delete']);
        ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('This role has child roles. Deleting it will also delete all child roles.', 'accessschema'); ?>
                <br><br>
                <a href="<?php echo esc_url(wp_nonce_url(
                    add_query_arg(array(
                        'page' => 'accessSchema-roles',
                        'action' => 'delete',
                        'role_id' => $role_id,
                        'cascade' => 1
                    ), admin_url('users.php')),
                    'delete_role_' . $role_id
                )); ?>" class="button button-primary"><?php esc_html_e('Yes, Delete All', 'accessschema'); ?></a>
                <a href="<?php echo esc_url(admin_url('users.php?page=accessSchema-roles')); ?>" class="button"><?php esc_html_e('Cancel', 'accessschema'); ?></a>
            </p>
        </div>
        <?php
    }
}

function accessSchema_render_registered_roles_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'accessSchema_roles';
    
    $per_page = 25;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Build query
    $where = "WHERE is_active = 1";
    $params = array();
    
    if ($search) {
        $where .= " AND (name LIKE %s OR full_path LIKE %s)";
        $like = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $like;
        $params[] = $like;
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM {$table} {$where}";
    $total_items = $params ? $wpdb->get_var($wpdb->prepare($count_query, ...$params)) : $wpdb->get_var($count_query);
    
    // Get roles
    $query = "SELECT * FROM {$table} {$where} ORDER BY full_path LIMIT %d OFFSET %d";
    $query_params = array_merge($params, array($per_page, $offset));
    $roles = $wpdb->get_results($wpdb->prepare($query, ...$query_params), ARRAY_A);
    
    ?>
    <h2><?php esc_html_e('Registered Roles', 'accessschema'); ?></h2>
    
    <form method="get" class="search-form">
        <input type="hidden" name="page" value="accessSchema-roles">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search roles...', 'accessschema'); ?>">
        <input type="submit" class="button" value="<?php esc_attr_e('Search', 'accessschema'); ?>">
    </form>
    
    <?php if (empty($roles)) : ?>
        <p><?php esc_html_e('No roles found.', 'accessschema'); ?></p>
    <?php else : ?>
        <form method="post" id="roles-form">
            <?php wp_nonce_field('bulk-roles'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1"><?php esc_html_e('Bulk Actions', 'accessschema'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'accessschema'); ?></option>
                        <option value="export"><?php esc_html_e('Export', 'accessschema'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'accessschema'); ?>">
                </div>
                
                <?php 
                $pagination_args = array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => ceil($total_items / $per_page),
                    'current' => $current_page
                );
                
                echo '<div class="tablenav-pages">';
                echo paginate_links($pagination_args);
                echo '</div>';
                ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th><?php esc_html_e('Name', 'accessschema'); ?></th>
                        <th><?php esc_html_e('Full Path', 'accessschema'); ?></th>
                        <th><?php esc_html_e('Depth', 'accessschema'); ?></th>
                        <th><?php esc_html_e('Actions', 'accessschema'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role) : ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="role_ids[]" value="<?php echo esc_attr($role['id']); ?>">
                            </th>
                            <td><?php echo esc_html($role['name']); ?></td>
                            <td><?php echo esc_html($role['full_path']); ?></td>
                            <td><?php echo esc_html($role['depth']); ?></td>
                            <td>
                                <?php
                                $has_children = $wpdb->get_var($wpdb->prepare(
                                    "SELECT 1 FROM {$table} WHERE parent_id = %d LIMIT 1",
                                    $role['id']
                                ));
                                
                                if ($has_children) {
                                    $confirm_url = add_query_arg(array(
                                        'page' => 'accessSchema-roles',
                                        'confirm_delete' => $role['id'],
                                        'nonce' => wp_create_nonce('confirm_delete_' . $role['id'])
                                    ), admin_url('users.php'));
                                    ?>
                                    <a href="<?php echo esc_url($confirm_url); ?>" class="button button-small">
                                        <?php esc_html_e('Delete', 'accessschema'); ?>
                                    </a>
                                <?php } else { ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(
                                        add_query_arg(array(
                                            'page' => 'accessSchema-roles',
                                            'action' => 'delete',
                                            'role_id' => $role['id']
                                        ), admin_url('users.php')),
                                        'delete_role_' . $role['id']
                                    )); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Delete this role?', 'accessschema'); ?>')">
                                        <?php esc_html_e('Delete', 'accessschema'); ?>
                                    </a>
                                <?php } ?>
                                
                                <button type="button" class="button button-small accessSchema-edit-role" 
                                    data-id="<?php echo esc_attr($role['id']); ?>" 
                                    data-name="<?php echo esc_attr($role['name']); ?>">
                                    <?php esc_html_e('Edit', 'accessschema'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
    
    <script>
    jQuery(document).ready(function($) {
        $('.accessSchema-edit-role').on('click', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            $('#edit_role_id').val(id);
            $('#edit_role_name').val(name);
            $('#accessSchema-edit-modal').show();
        });
        
        $('#accessSchema-edit-form').on('submit', function(e) {
            e.preventDefault();
            // AJAX save implementation
            closeEditModal();
        });
    });
    
    function closeEditModal() {
        document.getElementById('accessSchema-edit-modal').style.display = 'none';
    }
    </script>
    <?php
}

// Handle form submissions
add_action('admin_init', 'accessSchema_handle_role_actions');

function accessSchema_handle_role_actions() {
    // Add roles
    if (isset($_POST['submit']) && isset($_POST['accessSchema_add_role_nonce']) &&
        wp_verify_nonce($_POST['accessSchema_add_role_nonce'], 'accessSchema_add_role_action')) {
        
        if (!current_user_can('manage_access_schema')) {
            return;
        }
        
        $paths = sanitize_textarea_field($_POST['full_paths'] ?? '');
        $lines = array_filter(array_map('trim', explode("\n", $paths)));
        
        $added = 0;
        $failed = 0;
        
        foreach ($lines as $path) {
            $segments = array_map('trim', explode('/', $path));
            if (accessSchema_register_path($segments)) {
                $added++;
            } else {
                $failed++;
            }
        }
        
        $redirect = add_query_arg(array(
            'page' => 'accessSchema-roles',
            'added' => $added,
            'failed' => $failed
        ), admin_url('users.php'));
        
        wp_safe_redirect($redirect);
        exit;
    }
    
    // Delete single role
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['role_id'])) {
        $role_id = absint($_GET['role_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_role_' . $role_id)) {
            return;
        }
        
        if (!current_user_can('manage_access_schema')) {
            return;
        }
        
        $cascade = !empty($_GET['cascade']);
        $result = accessSchema_delete_role($role_id, $cascade);
        
        $redirect = add_query_arg(array(
            'page' => 'accessSchema-roles',
            'deleted' => $result ? 1 : 0
        ), admin_url('users.php'));
        
        wp_safe_redirect($redirect);
        exit;
    }
    
    // Bulk actions
    if (isset($_POST['action']) && $_POST['action'] !== '-1' && isset($_POST['role_ids'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bulk-roles')) {
            return;
        }
        
        if (!current_user_can('manage_access_schema')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $role_ids = array_map('absint', $_POST['role_ids']);
        
        switch ($action) {
            case 'delete':
                $deleted = 0;
                foreach ($role_ids as $id) {
                    if (accessSchema_delete_role($id, true)) {
                        $deleted++;
                    }
                }
                $redirect_args = array('deleted_bulk' => $deleted);
                break;
                
            case 'export':
                accessSchema_export_roles($role_ids);
                exit;
        }
        
        $redirect = add_query_arg(array_merge(
            array('page' => 'accessSchema-roles'),
            $redirect_args ?? array()
        ), admin_url('users.php'));
        
        wp_safe_redirect($redirect);
        exit;
    }
}

function accessSchema_export_roles($role_ids) {
    global $wpdb;
    $table = $wpdb->prefix . 'accessSchema_roles';
    
    $placeholders = implode(',', array_fill(0, count($role_ids), '%d'));
    $roles = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id IN ({$placeholders})",
        ...$role_ids
    ), ARRAY_A);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="accessSchema_roles_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, array('ID', 'Parent ID', 'Name', 'Slug', 'Full Path', 'Depth'));
    
    foreach ($roles as $role) {
        fputcsv($output, array(
            $role['id'],
            $role['parent_id'],
            $role['name'],
            $role['slug'],
            $role['full_path'],
            $role['depth']
        ));
    }
    
    fclose($output);
}