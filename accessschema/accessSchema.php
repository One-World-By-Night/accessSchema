<?php
/**
 * Plugin Name: accessSchema
 * Description: Manage Role-based access schema plugin with audit logging and REST API support.
 * Version: 1.3.0
 * Author: greghacke
 * Author URI: https://www.owbn.net
 * Text Domain: accessschema
 * Tested up to: 6.8
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/One-World-By-Night/owbn-chronicle-plugin
 * GitHub Branch: main
 */

defined('ABSPATH') || exit;

// Load your plugin core
require_once plugin_dir_path(__FILE__) . 'includes/core/init.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/activation.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/webhook-router.php';

require_once plugin_dir_path(__FILE__) . 'includes/render/render-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin/role-manager.php';

require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/access.php';
require_once plugin_dir_path(__FILE__) . 'includes/utils/access-utils.php';

register_activation_hook(__FILE__, 'accessSchema_activate');