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
	protected $plugin_screen_hook_suffix = array();

	public $field_group_post_ids = array();

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
		$this->acf_file_path = $plugin->acf_file_path;

		$this->plugin_screen_hook_suffix = array( 'acf', 'edit-acf' );

		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-acf-node-visitor.php' );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		add_filter( 'manage_edit-acf_columns', array($this,'acf_edit_columns'), 20, 1 );
		add_action( 'manage_acf_posts_custom_column' , array($this,'acf_columns_display'), 10, 2 );

		add_action( 'admin_notices', array($this, 'admin_notice') );
		/*
		 * Override built-in acf methods for loading fields in the admin by using a lower filter priority, which runs after the official acf functions (built-in functions use "5")
		 */
		add_filter( 'acf/field_group/get_fields', array($this, 'get_fields'), 10, 2 );
		add_filter('acf/field_group/get_location', array($this, 'get_location'), 10, 2);
		add_filter('acf/field_group/get_options', array($this, 'get_options'), 10, 2);

		add_action( 'admin_init', array( $this, 'sync_acf_posts'), 1 );

		add_action( 'save_post_acf', array($this, 'save_field_code'), 5 );
		add_action( 'wp_insert_post', array($this, 'add_placeholder_meta'), 10, 3 );
		add_action( 'before_delete_post', array($this, 'delete_acf_post') );

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
	 * @TODO:
	 *
	 * - Rename "Plugin_Name" to the name your plugin
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
		if ( in_array($screen->id, $this->plugin_screen_hook_suffix, true) ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), ACF_Field_Coder::VERSION );
		}

	}

	/**
	 * admin_notice()
	 * 
	 * @return [type] [description]
	 * @todo  display an admin notice in the edit.php for acf posts that explains fields are being written programmatically
	 */
	public function admin_notice() {
		$screen = get_current_screen();
		if ( in_array($screen->id, $this->plugin_screen_hook_suffix, true) ) { ?>
			<div class="update-nag">
				<p><?php _e( 'The Advanced Custom Fields Coder plugin has been activated. All field information will be saved to a file, and not in the database.' ); ?>
			</div>
	<?php }
	}

	/**
	 * [check_fields_file description]
	 * @return [type] [description]
	 * @since  1.0.0
	 * @todo   add admin notice if acf plugins file doesn't exist or isn't writable
	 */
	public function check_file() {

	}

	public function acf_edit_columns( $columns ) {
		// unset acf built-in fields column and add our own, instead
		unset($columns['fields']);
		$columns['field_no'] = __('Fields', $this->plugin_slug);
		return $columns;
	}

	public function acf_columns_display( $column, $post_id ) {
			// vars
			switch ($column) {
				case "field_no":
					// vars
					$count = 0;
					$fields = $this->get_field_group_by_post_id($post_id)['fields'];
					if ( is_array($fields) ) {
						$count = count($fields);
					}

				echo $count;
				break;
			}
	}

	/**
	 * get_coded_field_group
	 *
	 * Retrieve the coded field group from the acf_register_field_group global
	 *
	 * @param  [type] $name [description]
	 * @return [type]       [description]
	 */
	public function get_coded_field_group( $name ) {
		foreach ( $GLOBALS['acf_register_field_group'] as $field_group ) {
			if ( $field_group['id'] === $name ){
				return $field_group;
			}
		}
		return false;
	}

	/**
	 * get_field_group_by_post_id()
	 *
	 * Search fields registered by code, translating post id to field group name and return the field group array
	 * 
	 * @param  [type] $post_id [description]
	 * @return [type]          [description]
	 */
	public function get_field_group_by_post_id( $post_id ) {
		if ( ! isset($this->field_group_post_ids[$post_id]) ) return false;
		
		$field_name = $this->field_group_post_ids[$post_id];

		return $this->get_coded_field_group( $field_name );
	}

	/**
	 * get_fields()
	 *
	 * Returns acf field information from the acf_register_field_group global instead of the database
	 *
	 * Run with a filter at lower priority than the built-in method, to override it
	 * 
	 * @param  [type] $fields  [description]
	 * @param  [type] $post_id [description]
	 * @return [type]          [description]
	 */
	public function get_fields( $fields, $post_id ) {
		$i = 0;

		// Copy fields from global variable
		$coded_fields = $this->get_field_group_by_post_id($post_id)['fields'];
		// Set up fields for display
		if ($coded_fields) {
			unset($fields);
			foreach ( $coded_fields as $field ) {
				$field['order_no'] = $i;
				$field = apply_filters( 'acf/load_field', false, $field['key'], $post_id );
				$fields[] = $field;
				$i += 1;
			}
		}
		return $fields;
	}

	/**
	 * get_location()
	 *
	 * Returns the field group locations from the acf_register_field_group global instead of the database
	 * 
	 * Run with a filter at lower priority than the built-in method, to override it
	 *
	 * @param  [type] $location [description]
	 * @param  [type] $post_id  [description]
	 * @return [type]           [description]
	 */
	public function get_location(	$location, $post_id ) {
		$groups = array();

		$rules = $this->get_field_group_by_post_id($post_id)['location'];

		if( is_array($rules) ) {
			foreach( $rules as $rule_set ){

					// add rules to group in an multi-dimensional array by group number and order number
					$groups[ $rule_set['group_no'] ][ $rule_set['order_no'] ] = $rule_set;
				
					// sort rules
					ksort( $groups[ $rule_set['group_no'] ] );
			}

			// sort groups
			ksort( $groups );
		}

		return $groups;
	}

	/**
	 * get_options()
	 *
	 * Returns the field group locations from the acf_register_field_group global instead of the database
	 *
	 * Run with a filter at lower priority than the built-in method, to override it
	 *
	 * @param  [type] $options [description]
	 * @param  [type] $post_id [description]
	 * @return [type]          [description]
	 */
	public function get_options( $options, $post_id ) {

		return $this->get_field_group_by_post_id($post_id)['options'];
	}

	/**
	 * sync_acf_posts()
	 * 
	 * Write an acf post to the database for each field registered programmatically, if one doesn't already exist.
	 * This is needed to be able to use the admin interface for editing field groups
	 * Also sets the property $field_group_post_ids for this class
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function sync_acf_posts() {
		$db_field_group_names = array();
		
		// Get all of the current acf fields stored in the database
		$db_field_groups = get_posts( array(
			'numberposts'      => -1,
			'post_type'        => 'acf',
			'post_status'      => array('publish', 'pending', 'draft', 'future', 'private', 'trash'),
			'orderby'          => 'menu_order title',
			'order'            => 'asc',
			// 'suppress_filters' => false,
		));
		if ( $db_field_groups ) {
			foreach ( $db_field_groups as $db_field_group ) {
				$db_field_group_names[$db_field_group->post_name] = $db_field_group->ID;
			} // endforeach
		} // endif

		// Loop through the acf fields registered with code and create an acf post in the database if one doesn't exist
		foreach ( $GLOBALS['acf_register_field_group'] as $coded_field_group ) {
			if ( $db_field_group_names && isset($db_field_group_names[$coded_field_group['id']]) ) {
				$this->field_group_post_ids[$db_field_group_names[$coded_field_group['id']]] = $coded_field_group['id'];
			} else {
				$new_id = wp_insert_post( array(
					'post_title'     => $coded_field_group['title'],
					'post_status'    => 'publish',
					'ping_status'    => 'closed',
					'comment_status' => 'closed',
					'post_name'      => $coded_field_group['id'],
					'post_type'      => 'acf',
				));
				$this->field_group_post_ids[$new_id] = $coded_field_group['id'];
			} // endif
		} //endforeach
	}

	/**
	 * add_placeholder_meta
	 *
	 * Add post meta to placeholder posts, so they can be deleted on plugin deactivation
	 *
	 * @param [type] $post_id [description]
	 */
	public function add_placeholder_meta( $post_id, $post, $update ) {
		log_me($post_id);
		log_me($post);
		log_me($update);
		if ( 'acf' === $post->post_type ) {
			update_post_meta( $post_id = $post_id, $meta_key = 'acf_field_coder_placeholder', $meta_value = 1 );
		}
	}

	/**
	 * save_field_code()
	 *
	 * Upon saving an acf post, save the form data and output to a php file, instead of the database
	 *
	 * Run with an action set to higher priority than the built-in class, and unsets fields, so that fields are not saved to the database
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
				$field_data['order_no'] = $i;
				$field_data['key'] = $field_key;

				$fields[] = $field_data;
			}

			unset( $_POST['fields'] );
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

			unset( $_POST['location'] );
		}

		if ( $fields && $location_rules ) {

			// Check post_title
			if ( !$_POST['post_title'] ) {
				$_POST['post_title'] = 'Unnamed Field Group';
			}

			// Check post_name
			if ( !$_POST['post_name'] ) {
				$_POST['post_name'] = 'acf_' . sanitize_title($_POST['post_title']);
			}

			$field_group = array(
				'id' => $_POST['post_name'],
				'title' => $_POST['post_title'],
				'fields' => $fields,
				'location' => $location_rules,
				'options' => $_POST['options'],
				'menu_order' => $_POST['menu_order'],
			);

			unset( $_POST['options'] );

			$this->update_php_file( $field_group );
		}
	}

	/**
	 *	update_php_file()
	 *
	 * Opens, reads, and updates the php file that registers acf fields programmatically
	 * 
	 * @since  1.0.0
	 * @param  string $code [description]
	 * @return [type]       [description]
	 */
	public function update_php_file( $field_group = array(), $delete = false ) {
		global $parser;
		global $traverser;
		global $prettyPrinter;

		$acf_file = fopen( $this->acf_file_path, 'r+b');
		if ( flock($acf_file, LOCK_SH) ) {
			$code = fread( $acf_file, filesize($this->acf_file_path) );
			// Set the file pointer back to the beginning
			rewind( $acf_file );
			flock( $acf_file, LOCK_UN );
		} else {
			error_log( "ACF field file not readable" );
		}

		$new_field_code = $this->generate_field_php($field_group);

		if ( $this->get_coded_field_group($field_group['id']) ) {
			$field_key = $field_group['id'];
		} else {
			$field_key = 'new';
		}

		try {
			// parse
			$stmts = $parser->parse($code);

			// Set up to traverse the node structure and update with the new settings
			$traverser->addVisitor( new ACF_Node_Visitor($field_key, $parser->parse($new_field_code), $delete) );
			// traverse and update node
			$stmts = $traverser->traverse($stmts);

			// pretty print
			$code = $prettyPrinter->prettyPrintFile($stmts);

		} catch (PhpParser\Error $e) {
			error_log( 'Parse Error: ', $e->getMessage() );
		}

		if( flock($acf_file, LOCK_EX) ) {
			ftruncate($acf_file, 0);
			fwrite( $acf_file, $code );
			fflush( $acf_file );
			flock( $acf_file, LOCK_UN );
		} else {
			error_log( "Unable to write to file." );
		}
		fclose( $acf_file );
	}

	/**
	 * generate_field_php()
	 *
	 * Generates php code that can register an acf field programmatically
	 *
	 * Uses code directly from acf_export->html_php()
	 *
	 * @since  1.0.0
	 * @param  array  $field_group [description]
	 * @return [type]              [description]
	 */
	public function generate_field_php( $field_group = array() ) {

		// create written code
		$php_field_code = var_export($field_group, true);

		// Remove number keys from array
		$php_field_code = preg_replace('/[0-9]+ => array/', 'array', $php_field_code);

		return "<?php register_field_group( " . $php_field_code . ");";
	}

	/**
	 * [delete_acf_post description]
	 * @param  [type] $postid [description]
	 * @return [type]         [description]
	 */
	function delete_acf_post( $postid ) {
		global $post_type;

		if ( 'acf' === $post_type ) {
			$field_name = get_post_field( 'post_name', $postid, 'raw' );
			if ( $this->get_coded_field_group( $field_name ) ) {
				// Provide a field_group array, just as if we were creating or updating a field group
				$field_group['id'] = $field_name;
				$this->update_php_file( $field_group, $delete = true );
			}
		}
	}

} // end of class
