<?php
/**
 * ACF Field Code
 *
 * @package   advanced-custom-fields-coder
 * @author    Grant Kinney <grant@verismo.io>
 * @license   MIT
 * @link      http://wordpress.org
 * @copyright 2014 Grant Kinney
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `class-plugin-name.php`
 *
 * @package ACF_Field_Coder_Admin
 * @author  Grant Kinney <grant@verismo.io>
 */
class ACF_Field_Coder_Admin {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	public function __construct() {
		/*
		 * Call $plugin_slug from public plugin class.
		 */
		$plugin = ACF_Field_Coder::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		/*
		 * Define custom functionality.
		 *
		 * Read more about actions and filters:
		 * http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		add_action( 'save_post', array( $this, 'save_field_code' ), 5 );
		add_filter( '@TODO', array( $this, 'filter_method_name' ) );

		// global $acf_field_group;
		// remove_action( 'save_post', array($acf_field_group, 'save_post') );

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), ACF_Field_Coder::VERSION );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), ACF_Field_Coder::VERSION );
		}

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
		 *
		 *        Administration Menus: http://codex.wordpress.org/Administration_Menus
		 *
		 * @TODO:
		 *
		 * - Change 'Page Title' to the title of your plugin admin page
		 * - Change 'Menu Text' to the text for menu item for the plugin settings page
		 * - Change 'manage_options' to the capability you see fit
		 *   For reference: http://codex.wordpress.org/Roles_and_Capabilities
		 */
		$this->plugin_screen_hook_suffix = add_options_page(
			__( 'Page Title', $this->plugin_slug ),
			__( 'Menu Text', $this->plugin_slug ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		include_once( 'views/admin.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}

	/**
	 * [check_fields_file description]
	 * @return [type] [description]
	 * @since  1.0.0
	 * @todo   add admin notice if acf plugins file doesn't exist or isn't writable
	 */
	public function check_fields_file() {

	}

	/**
	 *
	 * @since    1.0.0
	 */
	public function save_field_code( $post_id ) {

		$fields = array();
		$location_rules = array();

		// do not save if this is an auto save routine
		if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		{
			return $post_id;
		}

		// verify nonce
		if( !isset($_POST['acf_nonce']) || !wp_verify_nonce($_POST['acf_nonce'], 'field_group') ) {
			return $post_id;
		}
		
		
		// only save once! WordPress save's a revision as well.
		if( wp_is_post_revision($post_id) ) {
			return $post_id;
		}

		// field data
		if( isset($_POST['fields']) && is_array($_POST['fields']) ) {
			$i = -1;

			// remove clone field
			unset( $_POST['fields']['field_clone'] );

			foreach( $_POST['fields'] as $field_key => $field_data ) {

				$i += 1;

				// set order + key
				$field['order_no'] = $i;
				$field['key'] = $field_key;

				$fields[] = $field_data;
			}

			// unset( $_POST['fields'] );
		}

		// location data
		if( isset($_POST['location']) && is_array($_POST['location']) ) {	
			// clean array keys
			$_POST['location'] = array_values( $_POST['location'] );
			foreach( $_POST['location'] as $group_id => $group ) {
				if( is_array($group) ) {
					// clean array keys
					$group = array_values( $group );
					foreach( $group as $rule_id => $rule ) {
						$rule['order_no'] = $rule_id;
						$rule['group_no'] = $group_id;
						
						$location_rules[] = $rule;
					}
				}
			}

			// unset( $_POST['location'] );
		}

		if ( $fields && $location_rules ) {
			$field_group = array(
				'id' => $_POST['post_name'],
				'title' => $_POST['post_title'],
				'fields' => $fields,
				'location' => $location_rules,
				'options' => $_POST['options'],
				'menu_order' => $_POST['menu_order'],
			);

			$output = $this->generate_field_php($field_group);

			log_me($output);

			// unset( $_POST['options'] );
		}
	}

	/**
	 * NOTE:     Filters are points of execution in which WordPress modifies data
	 *           before saving it or sending it to the browser.
	 *
	 *           Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    1.0.0
	 */
	public function filter_method_name() {
		// @TODO: Define your filter hook callback here
	}

	public function generate_field_php( $field_group = array() ) {

		// create written code
		$php_field_code = var_export($field_group, true);
		
		// change double spaces to tabs
		$php_field_code = str_replace("  ", "\t", $php_field_code);
		
		// correctly formats "=> array("
		$php_field_code = preg_replace('/([\t\r\n]+?)array/', 'array', $php_field_code);
		
		// Remove number keys from array
		$php_field_code = preg_replace('/[0-9]+ => array/', 'array', $php_field_code);
		
		// add extra tab at start of each line
		$php_field_code = str_replace("\n", "\n\t", $php_field_code);

		return 'register_field_group(' . $php_field_code . ');';
	}

}
