<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   advanced-custom-fields-coder
 * @author    Grant Kinney <grant@verismo.io>
 * @license   MIT
 * @link      http://wordpress.org
 * @copyright 2014 Grant Kinney
 *
 * @wordpress-plugin
 * Plugin Name:       Advanced Custom Fields: Coder
 * Plugin URI:        http://wordpress.org
 * Description:       Changes the functionality of the ACF wp-admin pages to write field information directly to a php file, rather than in the database
 * Version:           1.0.0
 * Author:            Grant Kinney
 * Author URI:        http://verismo.io
 * Text Domain:       advanced-custom-fields-coder
 * License:           MIT
 * License URI:       http://mit-license.org/
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/creativecoder/acf-field-code
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Debugging
 *----------------------------------------------------------------------------*/

if ( 1 == WP_LOCAL_SERVER ) {
	if(!function_exists('log_me')){
		function log_me( $message ) {
			if( is_array( $message ) || is_object( $message ) ){
				error_log( print_r( $message, true ) );
			} else {
				error_log( $message );
			}
		}
	}
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'public/class-acf-field-coder.php' );

/*----------------------------------------------------------------------------*
 * Register acf fields programmatically
 *----------------------------------------------------------------------------*/

if ( file_exists( plugin_dir_path( __FILE__ ) . 'public/includes/acf-fields.php') )  {
	require_o nce( plugin_dir_path( __FILE__ ) . 'public/includes/acf-fields.php' );
}

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 *
 */
register_activation_hook( __FILE__, array( 'ACF_Field_Coder', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACF_Field_Coder', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'ACF_Field_Coder', 'get_instance' ) );

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

/*
 * If you want to include Ajax within the dashboard, change the following
 * conditional to:
 *
 * if ( is_admin() ) {
 *   ...
 * }
 *
 * The code below is intended to to give the lightest footprint possible.
 */
if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-acf-field-coder-admin.php' );
	add_action( 'plugins_loaded', array( 'ACF_Field_Coder_Admin', 'get_instance' ) );

}
