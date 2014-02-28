<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   acf-field-code
 * @author    Grant Kinney <grant@verismo.io>
 * @license   MIT
 * @link      http://wordpress.org
 * @copyright 2014 Grant Kinney
 *
 * @wordpress-plugin
 * Plugin Name:       ACF Field Code
 * Plugin URI:        http://wordpress.org
 * Description:       Changes the functionality of the ACF wp-admin pages to write field information directly to a php file, rather than in the database
 * Version:           1.0.0
 * Author:            Grant Kinney
 * Author URI:        http://verismo.io
 * Text Domain:       acf-field-code
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
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/


require_once( plugin_dir_path( __FILE__ ) . 'public/class-acf-field-code.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 *
 */
register_activation_hook( __FILE__, array( 'ACF_Field_Code', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACF_Field_Code', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'ACF_Field_Code', 'get_instance' ) );

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

	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-acf-field-code-admin.php' );
	add_action( 'plugins_loaded', array( 'ACF_Field_Code_Admin', 'get_instance' ) );

}
