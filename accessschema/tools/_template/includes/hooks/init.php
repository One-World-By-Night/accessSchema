<?php

// File: includes/hooks/init.php
// Text Domain: wp-plugin-name
// @vesion 1.0.0
// @author author
// Function: Init hooks functionality for the plugin

defined( 'ABSPATH' ) || exit;

/** --- Require each hooks file once --- */
require_once __DIR__ . '/save.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/webhooks.php';
