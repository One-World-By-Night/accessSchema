<?php

// File: includes/admin/init.php
// Text Domain: wp-plugin-name
// @vesion 1.0.0
// @author author
// Function: Init admin functionality for the plugin

defined( 'ABSPATH' ) || exit;

/** --- Require each admin file once --- */
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/enqueue.php';
