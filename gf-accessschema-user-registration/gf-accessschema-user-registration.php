<?php
/**
 * Plugin Name: Gravity Forms AccessSchema User Registration
 * Plugin URI: https://github.com/One-World-By-Night/gf-accessschema-user-registration
 * Description: Creates WordPress users from Gravity Forms submissions with AccessSchema role assignment.
 * Version: 1.0.2
 * Author: One World By Night
 * Author URI: https://www.owbn.net
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gf-asc-user-registration
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || die();

define( 'GF_ASC_UR_VERSION', '1.0.2' );
define( 'GF_ASC_UR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GF_ASC_UR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Bootstrap when Gravity Forms is loaded.
add_action( 'gform_loaded', array( 'GF_ASC_User_Registration_Bootstrap', 'load' ), 5 );

/**
 * Bootstrap class for the AccessSchema User Registration add-on.
 *
 * @since 1.0.0
 */
class GF_ASC_User_Registration_Bootstrap {

	/**
	 * Load the add-on if the Feed Add-On Framework is available.
	 *
	 * @since 1.0.0
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once GF_ASC_UR_PLUGIN_DIR . 'class-gf-asc-user-registration.php';
		require_once GF_ASC_UR_PLUGIN_DIR . 'includes/class-gf-field-asc-roles.php';

		GFAddOn::register( 'GF_ASC_User_Registration' );
	}
}

/**
 * Returns an instance of the GF_ASC_User_Registration class.
 *
 * @since 1.0.0
 *
 * @return GF_ASC_User_Registration
 */
function gf_asc_user_registration() {
	return GF_ASC_User_Registration::get_instance();
}
