<?php

// File: includes/templates/init.php
// Text Domain: wp-plugin-name
// @vesion 1.0.0
// @author author
// Function: Init teamplates functionality for the plugin

defined( 'ABSPATH' ) || exit;

/** --- Require each render file once --- */
require_once __DIR__ . '/detail.php';
require_once __DIR__ . '/form.php';
require_once __DIR__ . '/listing.php';
