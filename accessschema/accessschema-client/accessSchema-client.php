<?php
/**
 * Plugin Name: accessSchema Client
 * Text Domain: accessschema-client
 * Plugin URI: https://www.github.com/One-World-By-Night/accessschema-client
 * Description: Leveraging a hosted accessSchema instance, this plugin provides a WordPress client for the accessSchema API.
 * Version: 2.1.1
 * Author: greghacke
 * Contributors: list, of, contributors, separated, by, commas
 * Author URI: https://www.github.com/One-World-By-Night
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /includes/languages
 * GitHub Branch: main
 */

defined( 'ABSPATH' ) || exit;

/**
 * -----------------------------------------------------------------------------
 * LOAD INSTANCE-SPECIFIC PREFIX BASE
 * File must define: define('ASC_PREFIX', 'YOURPLUGIN');
 * File must define: define('ASC_LABEL', 'Your Plugin Label');
 * This will be transformed into YOURPLUGIN_ASC_ internally
 * Location: accessschema-client/prefix.php
 * -----------------------------------------------------------------------------
 */
$prefix_file = __DIR__ . '/prefix.php';

if ( ! file_exists( $prefix_file ) ) {
	wp_die(
		esc_html__( 'accessSchema-client requires a prefix.php file that defines ASC_PREFIX.', 'accessschema-client' ),
		esc_html__( 'Missing File: prefix.php', 'accessschema-client' ),
		array( 'response' => 500 )
	);
}

require_once $prefix_file;

if ( ! defined( 'ASC_PREFIX' ) ) {
	wp_die(
		esc_html__( 'accessSchema-client requires ASC_PREFIX to be defined in prefix.php.', 'accessschema-client' ),
		esc_html__( 'Missing Constant: ASC_PREFIX', 'accessschema-client' ),
		array( 'response' => 500 )
	);
}

if ( ! defined( 'ASC_LABEL' ) ) {
	wp_die(
		esc_html__( 'accessSchema-client requires ASC_LABEL to be defined in prefix.php.', 'accessschema-client' ),
		esc_html__( 'Missing Constant: ASC_LABEL', 'accessschema-client' ),
		array( 'response' => 500 )
	);
}

// Final computed constant prefix: e.g., 'ANOTHERPLUGIN_ASC_'
$prefix = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', ASC_PREFIX ) ) . '_ASC_';

// Define path-related constants if not already defined
if ( ! defined( $prefix . 'FILE' ) ) {
	define( $prefix . 'FILE', __FILE__ );
}
if ( ! defined( $prefix . 'DIR' ) ) {
	define( $prefix . 'DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( $prefix . 'URL' ) ) {
	define( $prefix . 'URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( $prefix . 'VERSION' ) ) {
	define( $prefix . 'VERSION', '2.1.1' );
}
if ( ! defined( $prefix . 'TEXTDOMAIN' ) ) {
	define( $prefix . 'TEXTDOMAIN', 'accessschema-client' );
}
if ( ! defined( $prefix . 'ASSETS_URL' ) ) {
	define( $prefix . 'ASSETS_URL', constant( $prefix . 'URL' ) . 'includes/assets/' );
}
if ( ! defined( $prefix . 'CSS_URL' ) ) {
	define( $prefix . 'CSS_URL', constant( $prefix . 'ASSETS_URL' ) . 'css/' );
}
if ( ! defined( $prefix . 'JS_URL' ) ) {
	define( $prefix . 'JS_URL', constant( $prefix . 'ASSETS_URL' ) . 'js/' );
}

// Bootstrap the plugin/module
require_once constant( $prefix . 'DIR' ) . 'includes/init.php';
