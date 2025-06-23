<?php

// File: includes/shortcode/init.php
// Text Domain: wp-plugin-name
// @vesion 1.0.0
// @author author
// Function: Init shortcode functionality for the plugin

defined( 'ABSPATH' ) || exit;

/** --- Require each shortcode file once --- */
require_once __DIR__ . '/detail.php';
require_once __DIR__ . '/form.php';
require_once __DIR__ . '/listing.php';