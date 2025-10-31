<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Controller' ) ) {
	class Smart_Manager_Controller extends SA_Manager_Controller {
		public $dashboard_key = '',
				$plugin_path = '',
				$sm_beta_pro_background_updater = null;
		/**
		 *  Plugin prefix
		 *
		 * @var string
		 */
		public $plugin_prefix = '';

		/**
		 *  Plugin name
		 *
		 * @var string
		 */
		public $plugin_name = '';

		/**
		 * Stores plugin folder flag.
		 *
		 * @var string
		 */
		public $folder_flag = '';

		/**
		 * Stores the plugin directory path.
		 *
		 * @var string $plugin_dir_path
		 */
		public $plugin_dir_path = '';
		function __construct() {
			$this->plugin_path              = untrailingslashit( plugin_dir_path( __FILE__ ) );
			$this->plugin_file              = defined( 'SM_PLUGIN_FILE' ) ? SM_PLUGIN_FILE : '';
			$this->plugin_sku               = defined( 'SM_SKU' ) ? SM_SKU : '';
			$this->plugin_prefix            = defined( 'SM_PREFIX' ) ? SM_PREFIX : '';
			$this->plugin_name              = defined( 'SM_PLUGIN_NAME' ) ? SM_PLUGIN_NAME : '';
			$this->folder_flag              = '/pro';
			$this->plugin_main_class_nm     = 'Smart_Manager';
			$this->plugin_dir               = defined( 'SM_PLUGIN_DIR_PATH' ) ? SM_PLUGIN_DIR_PATH : '';
			$this->sa_manager_common_params = array(
				'plugin_file'          => $this->plugin_file,
				'plugin_sku'           => $this->plugin_sku,
				'plugin_prefix'        => $this->plugin_prefix,
				'plugin_name'          => $this->plugin_name,
				'folder_flag'          => $this->folder_flag,
				'plugin_main_class_nm' => $this->plugin_main_class_nm,
				'plugin_dir'           => $this->plugin_dir,
				'plugin_obj_key'       => 'smart_manager',
			);
			parent::__construct( $this->sa_manager_common_params );

			add_filter( 'sa_sm_manager_handler', array( $this, 'req_handler' ), 10, 2 );
			add_action( 'sa_sm_manager_func_handler', array( $this, 'func_handler' ) );
			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );
			add_action('admin_init',array(&$this,'call_custom_actions'),11);
			add_action('admin_footer',array(&$this,'sm_footer'));
			//Filter for setting the wp_editor default tab
			add_filter( 'wp_default_editor', array(&$this,'sm_wp_default_editor'),10, 1 );

			// Code for resetting the 'Shop_Order' and 'Shop_Subscription' col models on WC setting update
			add_action( 'woocommerce_update_options_advanced_custom_data_stores', array( &$this, 'migrate_wc_orders_subscriptions_col_model' ) );
			add_action( 'woocommerce_update_options_advanced_features', array( &$this, 'migrate_wc_orders_subscriptions_col_model' ) );
			add_filter( 'sa_common_module_available', array( $this, 'is_common_module_available' ), 10, 2 );
		}

		/**
		 * Checks if the common module is available for the given dashboard.
		 *
		 * @param bool   $is_common_module_available Optional. Default value indicating if the common module is available. Default false.
		 * @param string $current_dashboard          Optional. The current dashboard identifier. Default 'posts'.
		 *
		 * @return bool True if the current dashboard is 'product', false otherwise.
		 */
		public function is_common_module_available( $is_common_module_available = false, $current_dashboard = 'posts' ) {
			return ( 'product' === $current_dashboard ) ? true :false;
		}

		/**
		 * Handles dynamic method invocation on a given handler object.
		 *
		 * @param array $params {
		 *     Optional. Parameters for the handler.
		 *
		 *     @type object $handler_obj The object on which the method should be called.
		 *     @type string $func_nm     The name of the method to call.
		 * }
		 * @return void
		 */
		public function func_handler( $params = array() ) {
			if ( empty( $params ) || empty( $params['handler_obj'] ) || empty( $params['func_nm'] ) ) {
				return;
			}
			$func_nm = $params['func_nm'];
			if ( is_object( $params['handler_obj'] ) && method_exists( $params['handler_obj'], $func_nm ) && is_callable( [$params['handler_obj'], $func_nm] ) ) {
				$params['handler_obj']->$func_nm();
			}
		}

		public function sm_wp_default_editor( $tab ) {
			if ( !empty($_GET['page']) && 'smart-manager' === $_GET['page'] ) {
				$tab = "html";
			}
			return $tab;
		}

		public function sm_footer() {
			if( !empty($_GET['page']) && 'smart-manager' === $_GET['page'] && !( !empty( $_GET['sm_old'] ) && ( 'woo' === $_GET['sm_old'] || 'wpsc' === $_GET['sm_old'] ) ) ) {
				echo '<div id="sm_wp_editor" style="display:none;">';
				wp_editor( '', 'sm_inline_wp_editor', array('default_editor' => 'html') );
				echo '</div>';
			}
		}

		//Function to call custom actions on admin_init
		public function call_custom_actions() {
			if ( is_callable( array( 'SA_Manager_Controller', 'call_custom_actions' ) ) ) {
				SA_Manager_Controller::call_custom_actions();
			}
			//for background updater
			if( defined('SMPRO') && SMPRO === true && file_exists(SM_PRO_URL . 'classes/class-smart-manager-pro-background-updater.php') ) {
				include_once SM_PRO_URL . 'classes/class-smart-manager-pro-background-updater.php';
				$this->sm_beta_pro_background_updater = Smart_Manager_Pro_Background_Updater::instance();
			}
			// Code for scheduling action for deleting older tasks and export CSV file after x no. of days.
			if ( defined('SMPRO') && SMPRO === true && function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_next_scheduled_action' ) && ( ! as_has_scheduled_action( 'sm_schedule_tasks_cleanup' ) || ! as_next_scheduled_action( 'storeapps_smart_manager_scheduled_export_cleanup' ) ) && ( file_exists( SM_PRO_URL . 'classes/class-smart-manager-pro-base.php' ) ) && ( file_exists( $this->plugin_path . '/class-smart-manager-base.php' ) ) ) {
				include_once SM_PLUGIN_DIR_PATH . '/common-core/classes/class-sa-manager-base.php';
				include_once SM_PLUGIN_DIR_PATH . '/pro/common-pro/classes/class-sa-manager-pro-base.php';
				include_once $this->plugin_path . '/class-smart-manager-base.php';
				include_once SM_PRO_URL . 'classes/class-smart-manager-pro-base.php';
				if ( ! as_has_scheduled_action( 'sm_schedule_tasks_cleanup' ) && file_exists( SM_PRO_URL . 'classes/class-smart-manager-pro-task.php' ) ) {
					include_once SM_PRO_URL . 'classes/class-smart-manager-pro-task.php';
					( is_callable( array( 'Smart_Manager_Pro_Task', 'schedule_task_deletion' ) ) ) ? Smart_Manager_Pro_Task::schedule_task_deletion() : '';
				}
				if ( ! as_next_scheduled_action( 'storeapps_smart_manager_scheduled_export_cleanup' ) ) {
					( is_callable( array( 'Smart_Manager_Pro_Base', 'schedule_scheduled_exports_cleanup' ) ) ) ? Smart_Manager_Pro_Base::schedule_scheduled_exports_cleanup() : '';
				}
			}
		}

		/**
		 * Handles AJAX and internal requests.
		 *
		 * @param array $params      Optional. Additional parameters to pass to the handler. Default empty array.
		 * @param array $req_params  Required. Request parameters, must include 'active_module' and 'cmd'.
		 *
		 * @return mixed Returns an array with handler object and request parameters on success,
		 *               sends JSON response for settings update,
		 *               or returns null if request is invalid or not handled.
		 */
		public function req_handler( $params = array(), $req_params = array()) {
			if ( empty( $req_params ) || empty( $req_params['active_module'] ) || empty($req_params['cmd'] ) || ( ! is_user_logged_in() ) || ( ! is_admin() ) ) {
				return;
			}
			$pro_flag_class_path = $pro_flag_class_nm = $sm_pro_class_nm = '';
			if( defined('SMPRO') && SMPRO === true ) {
				$plugin_path = SM_PRO_URL .'classes';
				$pro_flag_class_path = 'pro-';
				$pro_flag_class_nm = 'Pro_';
			} else {
				$plugin_path = $this->plugin_path;
			}
			//Including the common utility functions class
			include_once $plugin_path . '/class-smart-manager-'.$pro_flag_class_path.'utils.php';
			$func_nm = $req_params['cmd'];
			if( !empty( $req_params['module'] ) && 'custom_views' === $req_params['module'] ){
				if( class_exists( 'Smart_Manager_Pro_Views' ) ){
					$views_obj = Smart_Manager_Pro_Views::get_instance();
					if( is_callable( array( $views_obj, $func_nm ) ) ) {
						$views_obj->$func_nm();
					}
				}
				return;
			}

			// Code to handle saving of settings
			if( 'smart_manager_settings' === $req_params['active_module'] && is_callable( 'Smart_Manager_Settings', 'update' ) ){
				$settings = ( ! empty( $req_params['settings'] ) ) ? json_decode( stripslashes( $req_params['settings'] ), true ) : array();
				//Validate API key settings.
				$ai_integration_settings = ( ( ! empty( $settings ) ) && ( is_array( $settings ) ) && ( ! empty( $settings['general']['select']['ai_integration_settings'] ) ) ) ? $settings['general']['select']['ai_integration_settings'] : array();
				if ( ( ! empty( $ai_integration_settings ) ) && ( is_array( $ai_integration_settings ) ) && ( ! empty( $ai_integration_settings['selectedModal'] ) ) && ( defined('SMPRO') && SMPRO === true ) && ( file_exists( $plugin_path . '/class-smart-manager-'.$pro_flag_class_path.'ai-connector.php' ) ) ) {
					//Include required files.
					include_once $plugin_path . '/class-smart-manager-'.$pro_flag_class_path.'ai-connector.php';
					if ( ( class_exists( 'Smart_Manager_Pro_AI_Connector' ) ) && ( is_callable( array( 'Smart_Manager_Pro_AI_Connector', 'verify_cohere_key' ) ) ) ) {
						Smart_Manager_Pro_AI_Connector::verify_AI_integration_settings( $ai_integration_settings );
					}
				}
				$result = Smart_Manager_Settings::update( $settings );
				wp_send_json( array( 'ACK'=> ( ( ! empty( $result ) ) ? 'Success' : 'Failure' ) ) );
			}

			include_once $this->plugin_path . '/class-smart-manager-base.php';

			$this->dashboard_key = $req_params['active_module'];
			$is_taxonomy_dashboard = ( ! empty( $req_params['is_taxonomy'] ) && ! empty( intval( $req_params['is_taxonomy'] ) ) ) ? true : false;

			$llms_file = $plugin_path . '/'. 'class-smart-manager-'.$pro_flag_class_path.'llms-base.php';
			$tasks_file = $plugin_path . '/' . 'class-smart-manager-' . $pro_flag_class_path . 'task.php';

			if( defined('SMPRO') && SMPRO === true ) {
				$sm_pro_class_nm = 'class-smart-manager-'.$pro_flag_class_path.'base.php';
				include_once $plugin_path . '/'. $sm_pro_class_nm;

				if( is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ){
					$acf_file = $plugin_path . '/'. 'class-smart-manager-'.$pro_flag_class_path.'acf-base.php';
					if( file_exists( $acf_file ) ){
						include_once $acf_file;
						$acf_class = 'Smart_Manager_'.$pro_flag_class_nm.'ACF_Base';
						$acf_class::instance($this->dashboard_key);
					}
				}

				// Code to include the base class for taxonomy dashboards
				if( ! empty( $is_taxonomy_dashboard ) ){
					$sm_pro_class_nm = 'class-smart-manager-'.$pro_flag_class_path.'taxonomy-base.php';
					include_once $plugin_path . '/'. $sm_pro_class_nm;
				}

				if( is_plugin_active( 'lifterlms/lifterlms.php' ) && file_exists( $llms_file ) ){
					include_once $llms_file;
				}
				if ( isset( $req_params['isTasks'] ) && file_exists( $tasks_file ) ) {
				    include_once $tasks_file;
				}
			}
			if ( file_exists( $this->plugin_path . '/class-smart-manager-task.php' ) ) {
				include_once $this->plugin_path . '/' . 'class-smart-manager-task.php';
			}
			if ( file_exists( SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-product-stock-log.php' ) ) {
         		include_once( SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-product-stock-log.php' );
        	}
			//Code for initializing the specific dashboard class

			$file_nm = ( ( ! empty( $is_taxonomy_dashboard ) && ( 'access-privilege' !== $this->dashboard_key ) ) ? 'taxonomy-' : '' ) . str_replace('_', '-', $this->dashboard_key);
			$class_name = '';
			$pro_flag_class_nm .= ( ( ! empty( $is_taxonomy_dashboard ) ) && ( 'access-privilege' !== $this->dashboard_key ) ) ? 'Taxonomy_' : '';

			if (file_exists($plugin_path . '/class-smart-manager-'.$pro_flag_class_path.''.$file_nm.'.php')) {

				$key_array = explode( "_", str_replace( '-', '_', $this->dashboard_key ) );
				$formatted_dashboard_key = array();
				foreach( $key_array as $value ) {
					$formatted_dashboard_key[] = ucwords($value);
				}

				$class_name = 'Smart_Manager_'.$pro_flag_class_nm.''.implode("_",$formatted_dashboard_key);

				if( file_exists( $this->plugin_path . '/class-smart-manager-'.$file_nm.'.php' ) ) {
					include_once $this->plugin_path . '/class-smart-manager-'.$file_nm.'.php';
				}

				if( defined('SMPRO') && SMPRO === true ) {
					$sm_pro_class_nm = 'class-smart-manager-'.$pro_flag_class_path.''.$file_nm.'.php';
					include_once $plugin_path .'/'. $sm_pro_class_nm;
				}
			} else {
				$class_name = (!empty($pro_flag_class_nm)) ? 'Smart_Manager_'.$pro_flag_class_nm.'Base' : 'Smart_Manager_Base';
				if( is_plugin_active( 'lifterlms/lifterlms.php' ) && class_exists( 'Smart_Manager_Pro_LLMS_Base' ) && in_array( $this->dashboard_key, Smart_Manager_Pro_LLMS_Base::$post_types ) ){
					$class_name = 'Smart_Manager_Pro_LLMS_Base';
				}
			}
			if( !empty( $req_params['cmd'] ) && $req_params['cmd'] == 'get_background_progress' ) {
				$class_name = 'class-smart-manager-pro-background-updater.php';
				$sm_pro_class_nm =  'Smart_Manager_Pro_Background_Updater';
			} elseif ( isset( $req_params['isTasks'] ) && ( ( ! empty( $req_params['cmd'] ) && ( 'save_state' === $req_params['cmd'] ) ) ) || ( ! empty( $req_params['isTasks'] ) ) ) {
				if ( ! empty( $is_taxonomy_dashboard ) && is_callable( $class_name, 'actions' ) ) {
					$class_name::actions();
				}
				$class_name = 'Smart_Manager_Task';
			}
			if ( ! empty( $req_params['isTasks'] ) ) {
				$class_name = 'Smart_Manager_Pro_Task';
			} elseif ( 'product_stock_log' === $this->dashboard_key ) {
				$class_name = 'Smart_Manager_Product_Stock_Log';
			}
			if ( 'batch_update' !== $req_params['cmd'] ) {
				$_REQUEST['class_nm'] = $class_name;
				$_REQUEST['class_path'] = $sm_pro_class_nm;
			}
			if( !empty( $this->sm_beta_pro_background_updater ) && !empty( $req_params['cmd'] ) && $req_params['cmd'] == 'get_background_progress' ) {
				is_callable( array( $this->sm_beta_pro_background_updater, $func_nm ) ) ? $this->sm_beta_pro_background_updater->$func_nm() : sa_manager_log( 'error', _x( "Method $func_nm is not callable in class Smart_Manager_Pro_Background_Updater.", 'Smart Manager - Ajax request handler background progress', 'smart-manager-for-wp-e-commerce' ) );
				is_callable(array($this->background_updater, $func_nm)) ? $this->background_updater->$func_nm() : sa_manager_log('error', _x("Method $func_nm is not callable in class Smart_Manager_Pro_Background_Updater.", 'Smart Manager - Ajax request handler background progress', 'smart-manager-for-wp-e-commerce'));
			} else if( class_exists( $class_name ) ) {
				$handler_obj = is_callable( array( $class_name, 'get_instance' ) ) ? $class_name::get_instance( $this->dashboard_key ) : new $class_name( $this->dashboard_key );
				$params['handler_obj'] = $handler_obj;
				$params['req_params']  = $req_params;
				return $params;
			}
		}

		/**
		 * Function to re-generate the column model for 'Shop_Order' and 'Shop_Subscription' on WC settings update.
		 */
		public function migrate_wc_orders_subscriptions_col_model() {

			global $wpdb;

			$user_id = get_current_user_id();

			if( empty( $user_id ) ){
				return;
			}

			$order_column_model = get_user_meta( $user_id, 'sa_sm_shop_order', true );
			$subscription_column_model = get_user_meta( $user_id, 'sa_sm_shop_subscription', true );

			if( empty( $order_column_model ) && empty( $subscription_column_model ) ){
				return;
			}

			if( ! class_exists( 'Smart_Manager_Shop_Order' ) && file_exists( $this->plugin_path . '/class-smart-manager-shop-order.php' ) ){
				if (! class_exists('SA_Manager_Base') && file_exists($this->plugin_path . '/../common-core/classes/class-sa-manager-base.php')) {
					include_once $this->plugin_path . '/../common-core/classes/class-sa-manager-base.php';
				}
				if( ! class_exists( 'Smart_Manager_Base' ) && file_exists( $this->plugin_path . '/class-smart-manager-base.php' ) ){
					include_once $this->plugin_path . '/class-smart-manager-base.php';
				}
				include_once $this->plugin_path . '/class-smart-manager-shop-order.php';
			}

			if( ! is_callable( array( 'Smart_Manager_Shop_Order', 'migrate_col_model' ) ) ){
				return;
			}

			if( ! empty( $order_column_model ) ) {
				delete_transient( 'sa_sm_shop_order' );
				update_user_meta( $user_id, 'sa_sm_shop_order' , Smart_Manager_Shop_Order::migrate_col_model( $order_column_model ) );
			}

			if( ! empty( $subscription_column_model ) ) {
				delete_transient( 'sa_sm_shop_subscription' );
				update_user_meta( $user_id, 'sa_sm_shop_subscription' , Smart_Manager_Shop_Order::migrate_col_model( $subscription_column_model ) );
			}

			// Code to update custom views
			if( ! ( defined('SMPRO') && true === SMPRO ) ) {
				return;
			}

			if ( $wpdb->prefix. 'sm_views' !== $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->prefix. 'sm_views' ) ) ) {
				return;
			}

			$views = $wpdb->get_results(
				$wpdb->prepare(
								"SELECT id,
										params
									FROM {$wpdb->prefix}sm_views
									WHERE post_type=%s
										OR post_type=%s",
								'shop_order',
								'shop_subscription'
				),
				'ARRAY_A'
			);

			if( empty( $views ) || ! is_array( $views ) ){
				return;
			}

			$view_update_clauses = array();
			foreach( $views as $view ){
				if( empty( $view['id'] ) || empty( $view['params'] ) ){
					continue;
				}

				$view['params'] = json_decode( $view['params'], true );

				if( empty( $view['params'] ) || ! is_array( $view['params'] ) ){
					continue;
				}

				$updated_col_model = Smart_Manager_Shop_Order::migrate_col_model( $view['params'] );
				if( empty( $updated_col_model ) ){
					continue;
				}
				$view_update_clauses[$view['id']] = "WHEN id={$view['id']} THEN '". wp_json_encode($updated_col_model) ."'";
			}

			if( empty( $view_update_clauses ) ){
				return;
			}

			$query = "UPDATE {$wpdb->prefix}sm_views
			SET params = CASE ". implode( ",", $view_update_clauses ) . " END
			WHERE id IN (". implode( ",", array_keys( $view_update_clauses ) ) .")";

			$wpdb->query(
				"UPDATE {$wpdb->prefix}sm_views
				SET params = CASE ". implode( " ", $view_update_clauses ) . " END
				WHERE id IN (". implode( ",", array_keys( $view_update_clauses ) ) .")"
			);
		}
	}
}
