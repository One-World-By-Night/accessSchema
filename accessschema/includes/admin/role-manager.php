<?php

// File: includes/admin/role-manager.php
// @version 1.4.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

function accessSchema_register_admin_menu() {
    add_users_page(
        'Access Schema Roles',
        'accessSchema',
        'manage_options',
        'accessSchema-roles',
        'accessSchema_render_role_manager_page'
    );
}
add_action( 'admin_menu', 'accessSchema_register_admin_menu' );

function accessSchema_render_role_manager_page() {
    ?>
    <div class="wrap">
        <h1>Access Schema Role Registry</h1>

        <?php
        // Show confirmation warning if needed
        if (
            isset($_GET['confirm_delete'], $_GET['has_children'], $_GET['accessSchema_confirm_delete_nonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['accessSchema_confirm_delete_nonce'])), 'accessSchema_confirm_delete_action') &&
            intval($_GET['has_children']) === 1
        ) {
            $confirm_id = (int) $_GET['confirm_delete'];
            ?>
            <div class="notice notice-warning">
                <p>
                    This role has child roles. Are you sure you want to delete it?<br><br>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="accessSchema_delete_role_id" value="<?php echo esc_attr( $confirm_id ); ?>">
                        <input type="hidden" name="accessSchema_force_delete" value="1">
                        <?php wp_nonce_field( 'accessSchema_delete_role_action', 'accessSchema_delete_role_nonce' ); ?>
                        <button type="submit" class="button button-danger">Yes, Delete Anyway</button>
                        <a href="<?php echo esc_url( admin_url( 'users.php?page=accessSchema-roles' ) ); ?>" class="button">Cancel</a>
                    </form>
                </p>
            </div>
            <?php
        }
        ?>

        <form method="post">
            <?php wp_nonce_field( 'accessSchema_add_role_action', 'accessSchema_add_role_nonce' ); ?>
            <h2>Add Role(s)</h2>
            <table class="form-table">
                <tr>
                    <th><label for="full_paths">Full Role Path(s)</label></th>
                    <td>
                        <textarea name="full_paths" id="full_paths" rows="6" style="width: 100%;" placeholder="Example: Coordinators/Brujah/Subcoordinator"><?php echo isset( $_POST['full_paths'] ) ? esc_textarea( sanitize_textarea_field( wp_unslash( $_POST['full_paths'] ) ) ) : ''; ?></textarea>
                        <p class="description">Enter one full role path per line using <code>/</code> between each level.<br>
                        Example: <code>Chronicles/KONY/HST</code></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Add Role(s)', 'primary', 'full_path_add_submit' ); ?>
        </form>

        <hr />

        <?php accessSchema_render_registered_roles_table(); ?>
    </div>
    <?php
}

function accessSchema_render_registered_roles_table() {
    global $wpdb;

    $table      = $wpdb->prefix . 'access_roles';
    $per_page   = 25;
    $page       = isset($_GET['paged']) ? max(1, intval(sanitize_text_field(wp_unslash($_GET['paged'])))) : 1;
    $offset     = ($page - 1) * $per_page;
    $filter     = isset($_GET['filter']) ? sanitize_text_field(wp_unslash($_GET['filter'])) : '';
    $like       = '%' . $wpdb->esc_like($filter) . '%';

    // Count total rows
    if ($filter) {
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `" . esc_sql($table) . "` WHERE full_path LIKE %s",
                $like
            )
        );
    } else {
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($table) . "`");
    }

    $total_pages = ceil($total / $per_page);

    // Fetch rows with optional filter
    if ($filter) {
        $roles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, full_path FROM `" . esc_sql($table) . "` WHERE full_path LIKE %s ORDER BY full_path LIMIT %d OFFSET %d",
                $like,
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    } else {
        $roles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, full_path FROM `" . esc_sql($table) . "` ORDER BY full_path LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }

    echo '<h2>Registered Roles</h2>';
    echo '<form method="get" style="margin-bottom:10px;">';
    echo '<input type="hidden" name="page" value="accessSchema-roles">';
    echo '<input type="text" name="filter" placeholder="Filter by Full Path..." value="' . esc_attr($filter) . '" style="width:100%;padding:6px;">';
    echo '</form>';

    if (empty($roles)) {
        echo '<p>No roles registered.</p>';
        return;
    }

    echo '<table class="widefat striped" id="accessSchema-roles-table">';
    echo '<thead><tr><th>Name</th><th>Full Path</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    foreach ($roles as $role) {
        echo '<tr>';
        echo '<td>' . esc_html($role['name']) . '</td>';
        echo '<td class="role-path">' . esc_html($role['full_path']) . '</td>';
        echo '<td>';
        $child_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `" . esc_sql($table) . "` WHERE parent_id = %d",
                $role['id']
            )
        );

        if ($child_count > 0) {
            $confirm_url = add_query_arg([
                'page' => 'accessSchema-roles',
                'confirm_delete' => $role['id'],
                'has_children' => 1,
                'accessSchema_confirm_delete_nonce' => wp_create_nonce('accessSchema_confirm_delete_action'),
                'filter' => $filter,
                'paged' => $page,
            ], admin_url('users.php'));

            echo '<a href="' . esc_url($confirm_url) . '" class="button button-small button-warning">Delete (Has Children)</a>';
        } else {
            echo '<form method="POST" style="display:inline;">';
            echo '<input type="hidden" name="accessSchema_delete_role_id" value="' . esc_attr($role['id']) . '">';
            wp_nonce_field('accessSchema_delete_role_action', 'accessSchema_delete_role_nonce');
            echo '<button type="submit" class="button button-small button-danger">Delete</button>';
            echo '</form>';
        }

        echo ' <button class="button button-small accessSchema-edit-role" data-id="' . esc_attr($role['id']) . '" data-name="' . esc_attr($role['name']) . '" data-path="' . esc_attr($role['full_path']) . '">Edit</button>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Pagination links
    echo '<div style="margin-top: 1em;">';
    echo '<div class="tablenav-pages">';
    if ($total_pages > 1) {
        for ($i = 1; $i <= $total_pages; $i++) {
            $current = $i === $page ? ' style="font-weight:bold;"' : '';
            printf(
                '<a href="%s"%s class="page-numbers">%s</a> ',
                esc_url(add_query_arg([
                    'paged' => $i,
                    'filter' => $filter,
                ])),
                esc_attr($current),
                esc_html($i)
            );
        }
    }
    echo '</div>';
    echo '</div>';
}

add_action( 'admin_init', 'accessSchema_handle_add_role_form' );
function accessSchema_handle_add_role_form() {
    global $wpdb;
    $table = $wpdb->prefix . 'access_roles';

    if (
        isset( $_POST['full_path_add_submit'], $_POST['full_paths'], $_POST['accessSchema_add_role_nonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['accessSchema_add_role_nonce'] ) ), 'accessSchema_add_role_action' )
    ) {
        $raw_input = isset($_POST['full_paths']) ? sanitize_textarea_field(wp_unslash($_POST['full_paths'])) : '';
        $lines = explode("\n", $raw_input);
        $added = 0;
        $skipped = 0;

        foreach ( $lines as $line ) {
            $path = trim( preg_replace( '#[^A-Za-z0-9 _/\-]#', '', $line ) );
            if ( $path === '' ) {
                continue;
            }

            $segments = explode( '/', $path );
            if ( count( $segments ) < 1 ) {
                $skipped++;
                continue;
            }

            $parent_id = null;
            $accumulated_path = '';

            foreach ( $segments as $index => $name ) {
                $name = trim( $name );
                $accumulated_path = $accumulated_path === '' ? $name : $accumulated_path . '/' . $name;

                $existing = is_null( $parent_id )
                    ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `" . esc_sql( $table ) . "` WHERE name = %s AND parent_id IS NULL", $name ) )
                    : $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `" . esc_sql( $table ) . "` WHERE name = %s AND parent_id = %d", $name, $parent_id ) );

                if ( $existing ) {
                    $parent_id = (int) $existing;
                    continue;
                }

                $result = $wpdb->insert( $table, [
                    'parent_id' => $parent_id,
                    'name'      => $name,
                    'full_path' => $accumulated_path,
                ], [ '%d', '%s', '%s' ] );

                if ( $result ) {
                    $parent_id = $wpdb->insert_id;
                    if ( $index === count( $segments ) - 1 ) {
                        $added++;
                    }
                } else {
                    $skipped++;
                    break;
                }
            }
        }

        if ( $added ) {
            add_action( 'admin_notices', fn() =>
                print '<div class="notice notice-success is-dismissible"><p>' . esc_html( $added ) . ' role(s) added successfully.</p></div>'
            );
        }

        if ( $skipped ) {
            add_action( 'admin_notices', fn() =>
                print '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $skipped ) . ' path(s) skipped (may already exist or invalid).</p></div>'
            );
        }

        wp_redirect( admin_url( 'users.php?page=accessSchema-roles' ) );
        exit;
    }
}

add_action( 'admin_init', 'accessSchema_handle_delete_role_form' );
function accessSchema_handle_delete_role_form() {
    global $wpdb;
    $table = $wpdb->prefix . 'access_roles';

    if (
        isset( $_POST['accessSchema_delete_role_id'], $_POST['accessSchema_delete_role_nonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['accessSchema_delete_role_nonce'] ) ), 'accessSchema_delete_role_action' )
    ) {
        $role_id = (int) $_POST['accessSchema_delete_role_id'];
        $force   = isset($_POST['accessSchema_force_delete']) && $_POST['accessSchema_force_delete'] === '1';

        $child_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `" . esc_sql( $table ) . "` WHERE parent_id = %d",
            $role_id
        ) );

        if ( $child_count > 0 && ! $force ) {
            $redirect_url = add_query_arg([
                'page' => 'accessSchema-roles',
                'confirm_delete' => $role_id,
                'has_children' => 1
            ], admin_url( 'users.php' ));

            wp_redirect( $redirect_url );
            exit;
        }

        $deleted = $wpdb->delete( $table, [ 'id' => $role_id ], [ '%d' ] );

        if ( $deleted ) {
            add_action( 'admin_notices', fn() =>
                print '<div class="notice notice-success is-dismissible"><p>Role deleted successfully.</p></div>'
            );
        } else {
            add_action( 'admin_notices', fn() =>
                print '<div class="notice notice-error is-dismissible"><p>Failed to delete role.</p></div>'
            );
        }

        wp_redirect( admin_url( 'users.php?page=accessSchema-roles' ) );
        exit;
    }
}