<?php
// File: accessschema-client/init.php
// @version 1.1.0
// Author: greghacke
// @tool accessschema-client

if (!defined('ABSPATH')) exit;

// Include required components
require_once __DIR__ . '/admin-ui.php';
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/render-admin.php';
require_once __DIR__ . '/render-ui.php';
require_once __DIR__ . '/shortcode.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/webhook.php';