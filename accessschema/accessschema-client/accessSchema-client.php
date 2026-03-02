<?php
/**
 * Plugin Name: accessSchema Client
 * Plugin URI: https://github.com/One-World-By-Night/accessSchema
 * Description: WordPress client for a hosted accessSchema instance.
 * Version: 2.5.0
 * Author: greghacke
 * License: GPL-2.0-or-later
 * Text Domain: accessschema-client
 */

defined( 'ABSPATH' ) || exit;

$prefix_file = __DIR__ . '/prefix.php';

if ( ! file_exists( $prefix_file ) ) {
	wp_die(
		esc_html__( 'accessSchema-client requires a prefix.php file.', 'accessschema-client' ),
		esc_html__( 'Missing File: prefix.php', 'accessschema-client' ),
		array( 'response' => 500 )
	);
}

// Use require (not require_once) so each embedded copy loads its own prefix.php.
require $prefix_file;

// Validate that prefix.php set the required variables.
if ( empty( $asc_instance_prefix ) ) {
	// Backward compatibility: fall back to constants if set by an old-style prefix.php.
	if ( defined( 'ASC_PREFIX' ) ) {
		$asc_instance_prefix = ASC_PREFIX;
	} else {
		wp_die(
			esc_html__( 'accessSchema-client requires $asc_instance_prefix in prefix.php.', 'accessschema-client' ),
			esc_html__( 'Missing Variable: $asc_instance_prefix', 'accessschema-client' ),
			array( 'response' => 500 )
		);
	}
}

if ( empty( $asc_instance_label ) ) {
	if ( defined( 'ASC_LABEL' ) ) {
		$asc_instance_label = ASC_LABEL;
	} else {
		$asc_instance_label = $asc_instance_prefix;
	}
}

// Backward compatibility: define global constants if not already set.
// First plugin to load wins — other plugins use their own $asc_instance_* variables.
if ( ! defined( 'ASC_PREFIX' ) ) {
	define( 'ASC_PREFIX', $asc_instance_prefix );
}
if ( ! defined( 'ASC_LABEL' ) ) {
	define( 'ASC_LABEL', $asc_instance_label );
}

// Compute the constant prefix for this instance (e.g., 'OWBNBOARD_ASC_').
$prefix = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $asc_instance_prefix ) ) . '_ASC_';

// Define path-related constants if not already defined.
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
	define( $prefix . 'VERSION', '2.4.0' );
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

// Bootstrap the plugin/module.
// $asc_instance_prefix and $asc_instance_label propagate to all included files
// via PHP scope inheritance (require_once inherits the calling scope).
require_once constant( $prefix . 'DIR' ) . 'includes/init.php';
