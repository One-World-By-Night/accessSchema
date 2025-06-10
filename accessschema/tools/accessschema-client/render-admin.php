<?php

// File: accessschema-client/render-admin.php
// @version 1.2.0
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