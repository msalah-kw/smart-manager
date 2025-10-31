<?php
/**
 * Common core controller class.
 *
 * @package common-core/
 * @since       8.64.0
 * @version     8.67.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SA_Manager_Controller' ) ) {
	/**
	 * Class properties and methods will go here.
	 */
	class SA_Manager_Controller {
		/**
		 * Current dashboard key
		 *
		 * @var string
		 */
		public $dashboard_key = '';

		/**
		 * Stores the plugin file path.
		 *
		 * @var string $plugin_file
		 */
		public $plugin_file = '';

		/**
		 * Stores the plugin's short name.
		 *
		 * @var string $plugin_short_name
		 */
		public $plugin_short_name = '';

		/**
		 * Stores the plugin SKU
		 *
		 * @var string
		 */
		public $plugin_sku = '';

		/**
		 * Stores the plugin prefix.
		 *
		 * @var string $prefix
		 */
		public $prefix = '';

		/**
		 * Indicates whether the plugin has pro folder.
		 *
		 * @var bool $folder_flag
		 */
		public $folder_flag = false;

		/**
		 * Stores the plugin directory path.
		 *
		 * @var string $plugin_dir_path
		 */
		public $plugin_dir_path = '';

		/**
		 * Stores data related to the plugin.
		 *
		 * @var array $plugin_data
		 */
		public $plugin_data = array();

		/**
		 *  Background updater instance
		 *
		 * @var object $background_updater
		 */
		public $background_updater = null;

		/**
		 *  Main class name of the plugin
		 *
		 * @var string $plugin_main_class_nm Main class name of the plugin
		 */
		public $plugin_main_class_nm = '';

		/**
		 * Array used to store and manage data required across different functionalities of the plugin.
		 *
		 * @var array $sa_manager_common_params
		 */
		public $sa_manager_common_params = array();

		/**
		 * Indicates whether to common pro module is available or not.
		 *
		 * @var bool $is_common_pro_module_available
		 */
		public $is_common_pro_module_available = false;

		/**
		 * This array is used to store and manage data required across different functionalities of the plugin.
		 *
		 * @var array $common_params
		 */
		public static $common_params = array();

		/**
		 * Stores the plugin directory.
		 *
		 * @var string $plugin_dir
		 */
		public $plugin_dir = '';

		/**
		 * Constructor is called when the class is instantiated
		 *
		 * @param Array $plugin_data Array of plugin data.
		 *
		 * @return void
		 */
		public function __construct( $plugin_data = array() ) {
			$this->plugin_short_name              = ( ! empty( $plugin_data['plugin_name'] ) ) ? $plugin_data['plugin_name'] : '';
			$this->plugin_main_class_nm           = ( ! empty( $plugin_data['plugin_main_class_nm'] ) ) ? $plugin_data['plugin_main_class_nm'] : '';
			$this->is_common_pro_module_available = ( ! empty( $this->folder_flag ) ) ? true : false;
			$this->plugin_dir_path                = dirname( $this->plugin_file );
			if ( is_admin() ) {
				add_action( 'wp_ajax_sa_' . $this->plugin_sku . '_manager_include_file', array( $this, 'request_handler' ) );
			}
		}

		/**
		 * Function to handle the wp-admin ajax request
		 *
		 * @return void
		 */
		public function request_handler() {
			$is_valid_page = apply_filters( 'sa_' . $this->plugin_sku . '_validate_current_page', true, $_REQUEST ); // phpcs:ignore
			if ( ( ! is_user_logged_in() ) || ( ! is_admin() ) || empty( $_REQUEST ) || empty( $_REQUEST['active_module'] ) || empty( $_REQUEST['cmd'] ) || ( ! $is_valid_page ) ) {
				return;
			}
			check_ajax_referer( 'sa-' . $this->plugin_sku . '-manager-security', 'security' );
			$this->dashboard_key            = ( ! empty( $_REQUEST['active_module'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['active_module'] ) ) : '';
			$is_common_module_available     = ( ! empty( $_REQUEST['cmd'] ) && ( 'get_background_progress' !== $_REQUEST['cmd'] ) ) ? apply_filters( 'sa_common_module_available', false, $this->dashboard_key ) : false;
			$this->sa_manager_common_params = ( ! empty( $this->dashboard_key ) ) ? array_merge( $this->sa_manager_common_params, array( 'dashboard_key' => $this->dashboard_key ) ) : $this->sa_manager_common_params;
			$this->folder_flag              = ( ! empty( $this->folder_flag ) ) ? $this->folder_flag : '';
			$func_nm                        = ( ! empty( $_REQUEST['cmd'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['cmd'] ) ) : '';
			if ( empty( $this->plugin_dir_path ) ) {
				return;
			}
			$common_core_path = apply_filters(
				'sa_' . $this->plugin_sku . '_manager_common_core_path',
				$this->plugin_dir_path . '/common-core/classes/',
				$this->plugin_dir_path
			);
			$common_pro_path  = $this->plugin_dir_path . $this->folder_flag . '/common-pro/classes/';
			$class_name       = 'SA_Manager_Base';
			$pro_class_nm     = 'class-sa-manager-pro-base.php';
			if ( ! empty( $this->is_common_pro_module_available ) ) {
				$class_name = 'SA_Manager_Pro_Base';
			}
			$files_to_include = array(
				$common_core_path . 'class-sa-manager-base.php',
				$common_pro_path . $pro_class_nm,
			);
			// Include dashboard specific files.
			if ( ! empty( $is_common_module_available ) ) {
				$class_name = 'SA_Manager_' . ucfirst( $this->dashboard_key );
				if ( ! empty( $this->is_common_pro_module_available ) ) {
					$class_name   = 'SA_Manager_Pro_' . ucfirst( $this->dashboard_key );
					$pro_class_nm = 'class-sa-manager-pro-' . $this->dashboard_key . '.php';
				}
				$files_to_include = array_merge(
					$files_to_include,
					array(
						$common_core_path . 'class-sa-manager-' . $this->dashboard_key . '.php',
						$common_pro_path . $pro_class_nm,
					)
				);
			}
			$allowed_dirs = apply_filters( 'sa_' . $this->plugin_sku . '_manager_request_handler_allowed_dir_path', array() );
			foreach ( $files_to_include as $file_to_include ) {
				$real_path = realpath( $file_to_include );
				$basename  = basename( $file_to_include );
				if (
					$real_path &&
					preg_match( '/^class-sa-manager(-pro)?(-[a-z0-9_]+)?\.php$/i', $basename ) &&
					file_exists( $real_path )
				) {
					$is_in_allowed_dir = false;
					foreach ( $allowed_dirs as $allowed_dir ) {
						if ( 0 === strpos( $real_path, $allowed_dir ) ) {
							$is_in_allowed_dir = true;
							break;
						}
					}
					if ( $is_in_allowed_dir && ( is_file( $real_path ) ) ) {
						include_once $real_path; // nosemgrep: audit.php.lang.security.file.inclusion-arg, scanner.php.lang.security.file.inclusion .
					}
				}
			}

			if ( ! empty( $_REQUEST['cmd'] ) && ( 'get_background_progress' === $_REQUEST['cmd'] ) ) {
				$class_name   = 'class-sa-manager-pro-background-updater.php';
				$pro_class_nm = 'SA_Manager_Pro_Background_Updater';
			}
			$handler_obj = null;
			// Include the class name and path for background processing.
			$_REQUEST['class_nm']   = $class_name;
			$_REQUEST['class_path'] = $pro_class_nm;
			$req_params             = $_REQUEST;
			$sa_manager_handler     = apply_filters(
				'sa_' . $this->plugin_sku . '_manager_handler',
				array(
					'handler_obj' => $handler_obj,
					'req_params'  => $req_params,
				),
				$req_params
			);
			if ( ! empty( $this->background_updater ) && ( ! empty( $_REQUEST['cmd'] ) ) && 'get_background_progress' === $_REQUEST['cmd'] ) {
				is_callable( array( $this->background_updater, $func_nm ) )
				? $this->background_updater->$func_nm()
				: sa_manager_log(
					'error',
					sprintf( // translators: 1: Method name, 2: Class name.
						_x( 'Method %1$s is not callable in class %2$s', 'Ajax request handler background progress', 'smart-manager-for-wp-e-commerce' ),
						$func_nm,
						$class_name
					)
				);
			} elseif ( class_exists( $class_name ) ) {
				$handler_obj = is_callable( array( $class_name, 'instance' ) ) ? $class_name::instance( $this->sa_manager_common_params ) : new $class_name( $this->sa_manager_common_params );
			}
			do_action(
				'sa_' . $this->plugin_sku . '_manager_func_handler',
				array(
					'handler_obj' => $sa_manager_handler['handler_obj'],
					'func_nm'     => $func_nm,
					'req_params'  => $req_params,
				)
			);
			if ( class_exists( $class_name ) && is_callable( array( $handler_obj, $func_nm ) ) ) {
				$handler_obj->$func_nm();
			} else {
				$log_class = $this->plugin_main_class_nm;
				if ( is_callable( 'sa_manager_log' ) ) {
					sa_manager_log(
						'error',
						sprintf(
							// Translators: %1$s is the method name, %2$s is the class name.
							_x(
								'Method %1$s is not callable in class %2$s.',
								'Error message for non-callable method in Ajax request handler',
								'smart-manager-for-wp-e-commerce'
							),
							$func_nm,
							$class_name
						)
					);
				}
			}
		}
		/**
		 * Function to call custom actions on admin_init
		 *
		 * @return void
		 */
		public function call_custom_actions() {
			do_action( $this->plugin_sku . '_admin_init' );
			add_action( 'edited_term', array( $this, 'terms_added' ), 10, 4 );
			add_action( 'created_term', array( $this, 'terms_added' ), 10, 4 );
			add_action( 'delete_term', array( $this, 'terms_deleted' ), 10, 5 );
			add_action( 'woocommerce_attribute_added', array( $this, 'woocommerce_attributes_updated' ) );
			add_action( 'woocommerce_attribute_updated', array( $this, 'woocommerce_attributes_updated' ) );
			add_action( 'woocommerce_attribute_deleted', array( $this, 'woocommerce_attributes_updated' ) );
			add_action( 'added_post_meta', array( $this, 'added_post_meta' ), 10, 4 );
			// for background updater.
			$this->folder_flag = ( ! empty( $this->folder_flag ) ) ? $this->folder_flag : '';
			if ( is_file( realpath( $this->plugin_dir_path . $this->folder_flag . '/common-pro/classes/class-sa-manager-pro-background-updater.php' ) ) ) {
				include_once $this->plugin_dir_path . $this->folder_flag . '/common-pro/classes/class-sa-manager-pro-background-updater.php';
				$this->background_updater = SA_Manager_Pro_Background_Updater::instance( $this->sa_manager_common_params );
			}
		}

		/**
		 * Handles actions to be performed after a term is added/edited to a taxonomy.
		 *
		 * This method is triggered when a new term is added/edited. It retrieves the post types
		 * associated with the given taxonomy and deletes related transients to ensure
		 * data consistency.
		 *
		 * @param int    $term_id   The ID of the term that was added. Default 0.
		 * @param int    $tt_id     The term taxonomy ID. Default 0.
		 * @param string $taxonomy  The taxonomy slug. Default empty string.
		 * @param array  $args      Optional. Additional arguments passed to the function. Default empty array.
		 */
		public function terms_added( $term_id = 0, $tt_id = 0, $taxonomy = '', $args = array() ) {
			global $wp_taxonomies;
			$post_types = ( ! empty( $wp_taxonomies[ $taxonomy ] ) ) ? $wp_taxonomies[ $taxonomy ]->object_type : array();
			$this->delete_transients( $post_types );
		}

		/**
		 * Handles actions to perform when a taxonomy term is deleted.
		 *
		 * This method is triggered when a term is deleted from a taxonomy. It retrieves the post types
		 * associated with the given taxonomy and deletes any related transients.
		 *
		 * @param int     $term         The term ID that was deleted.
		 * @param int     $tt_id        The term taxonomy ID.
		 * @param string  $taxonomy     The taxonomy slug.
		 * @param WP_Term $deleted_term The deleted term object.
		 * @param array   $object_ids   Array of object IDs from which the term was removed.
		 */
		public function terms_deleted( $term = 0, $tt_id = 0, $taxonomy = '', $deleted_term = null, $object_ids = array() ) {
			global $wp_taxonomies;
			$post_types = ( ! empty( $wp_taxonomies[ $taxonomy ] ) ) ? $wp_taxonomies[ $taxonomy ]->object_type : array();
			$this->delete_transients( $post_types );
		}

		/**
		 * Handles actions when WooCommerce attributes are updated
		 *
		 * This method is called when WooCommerce attributes are updated, allowing the plugin
		 * to perform necessary actions or updates in response to attribute changes.
		 *
		 * @return void
		 */
		public function woocommerce_attributes_updated() {
			$this->delete_transients( array( 'product' ) );
		}

		/**
		 * Callback function triggered when a post meta is added.
		 *
		 * @param int    $meta_id     ID of the added metadata entry.
		 * @param int    $object_id   ID of the object (post) the metadata is for.
		 * @param string $meta_key    Meta key.
		 * @param mixed  $_meta_value Meta value.
		 */
		public function added_post_meta( $meta_id = 0, $object_id = 0, $meta_key = '', $_meta_value = null ) {
			$post_type  = get_post_type( $object_id );
			$post_types = ( ! empty( $post_type ) ) ? array( $post_type ) : array();
			$this->delete_transients( $post_types );
		}

		/**
		 * Deletes transients associated with specific post types.
		 *
		 * This method deletes transients associated with the specified post types,
		 * allowing the plugin to remove cached data when necessary.
		 *
		 * @param array $post_types An array of post types for which transients should be deleted.
		 * @return bool True if the transient was deleted, false otherwise.
		 */
		public function delete_transients( $post_types = array() ) {
			if ( empty( $post_types ) || ( ! is_array( $post_types ) ) ) {
				return false;
			}
			foreach ( $post_types as $post_type ) {
				if ( get_transient( 'sa_' . $this->plugin_sku . '_' . $post_type ) ) {
					delete_transient( 'sa_' . $this->plugin_sku . '_' . $post_type );
				}
			}
			return true;
		}
	}
}
