<?php

// File: accessschema-client/render-admin.php
// @version 1.1.0
// @author greghacke
// @tool accessschema-client

defined( 'ABSPATH' ) || exit;

function accessSchema_client_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>AccessSchema Remote Client</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('accessschema_client');
            do_settings_sections('accessschema-client');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}