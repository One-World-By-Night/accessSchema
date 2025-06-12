<?php

// File: accessschema-client/render-admin.php
// @version 1.4.0
// @author greghacke
// @tool accessschema-client

defined( 'ABSPATH' ) || exit;

function accessSchema_client_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>AccessSchema Remote Client</h1>

        <!-- Settings Form -->
        <form method="post" action="options.php">
            <?php
            settings_fields('accessschema_client');
            do_settings_sections('accessschema-client');
            submit_button();
            ?>
        </form>

        <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <hr>
            <h2>Manual Role Cache Clear</h2>
            <form method="post">
                <input type="email" name="accessschema_clear_user_email" placeholder="User email" required class="regular-text" />
                <button type="submit" class="button button-secondary">Clear Cached Roles</button>
            </form>

            <?php
            if ( ! empty( $_POST['accessschema_clear_user_email'] ) ) {
                $email = sanitize_email( $_POST['accessschema_clear_user_email'] );
                $user  = get_user_by( 'email', $email );

                if ( $user ) {
                    delete_user_meta( $user->ID, 'accessschema_cached_roles' );
                    echo '<p><strong>Cache cleared for ' . esc_html( $user->user_email ) . '</strong></p>';
                } else {
                    echo '<p><strong>No user found with that email.</strong></p>';
                }
            }
            ?>
        <?php endif; ?>
    </div>
    <?php
}

function accessSchema_client_render_user_cache_view( $user ) {
    if ( ! current_user_can( 'list_users' ) ) {
        return;
    }

    $roles     = get_user_meta( $user->ID, 'accessschema_cached_roles', true );
    $timestamp = get_user_meta( $user->ID, 'accessschema_cached_roles_timestamp', true );

    $timestamp_display = $timestamp
        ? date_i18n( 'm/d/Y h:i a', intval( $timestamp ) )
        : '[Unknown]';

    $flush_url = wp_nonce_url(
        add_query_arg([
            'action'  => 'flush_accessschema_cache',
            'user_id' => $user->ID,
        ], admin_url( 'users.php' )),
        'flush_accessschema_' . $user->ID
    );

    $refresh_url = wp_nonce_url(
        add_query_arg([
            'action'  => 'refresh_accessschema_cache',
            'user_id' => $user->ID,
        ], admin_url( 'users.php' )),
        'refresh_accessschema_' . $user->ID
    );

    if ( isset($_GET['message']) && $_GET['message'] === 'accessschema_cache_flushed' ) {
        echo '<div class="notice notice-success is-dismissible"><p>AccessSchema cache flushed.</p></div>';
    } elseif ( isset($_GET['message']) && $_GET['message'] === 'accessschema_cache_refreshed' ) {
        echo '<div class="notice notice-success is-dismissible"><p>AccessSchema cache refreshed.</p></div>';
    }

    ?>
    <h2>AccessSchema (Client Cache)</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label>Cached Roles</label></th>
            <td>
                <?php if ( is_array( $roles ) && ! empty( $roles ) ) : ?>
                    <ul style="margin-bottom: 0;">
                        <?php foreach ( $roles as $r ) : ?>
                            <li><?php echo esc_html( $r ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top: 4px;">
                        <strong>Cached:</strong> <?php echo esc_html( $timestamp_display ); ?>
                        <a href="<?php echo esc_url( $flush_url ); ?>" style="margin-left:8px;">[Flush]</a>
                        <a href="<?php echo esc_url( $refresh_url ); ?>" style="margin-left:4px;">[Refresh]</a>
                    </p>
                <?php else : ?>
                    <p>[None]
                        <a href="<?php echo esc_url( $refresh_url ); ?>" style="margin-left:8px;">[Request]</a>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'show_user_profile', 'accessSchema_client_render_user_cache_view', 15 );
add_action( 'edit_user_profile', 'accessSchema_client_render_user_cache_view', 15 );