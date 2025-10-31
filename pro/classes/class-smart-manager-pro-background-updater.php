<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ActionScheduler' ) && file_exists( SM_PLUGIN_DIR_PATH. '/pro/libraries/action-scheduler/action-scheduler.php' ) ) {
	include_once SM_PLUGIN_DIR_PATH. '/pro/libraries/action-scheduler/action-scheduler.php';
}

/**
 * SM_Background_Updater Class.
 */
if ( ! class_exists( 'Smart_Manager_Pro_Background_Updater' ) ) {
	class Smart_Manager_Pro_Background_Updater extends SA_Manager_Pro_Background_Updater {

		protected $identifier = '';

		protected static $_instance = null;

		protected $batch_start_time = '';

		public static function instance( $plugin_data = array() ) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self( $plugin_data );
			}
			return self::$_instance;
		}

		/**
		 * Initiate new background process
		 */
		public function __construct( $plugin_data = array() ) {
			$this->identifier = self::get_identifier();
			add_action( 'sm_schedule_tasks_cleanup', array( $this, 'schedule_tasks_cleanup_cron' ) ); // For handling deletion of tasks those are more than x number of days.
			add_filter( 'sa_excluded_process_names', function( $process_names = array() ) { // For duplicate and non_post type dashboards.
				return array(
					'duplicate_records', 'delete_non_post_type_records'
				);
			} );
			add_action( 'sa_handle_batch_process', array( $this, 'handle_duplicate_batch_process' ) );
			add_action( 'sa_port_initial_process_option', array( $this, 'port_initial_process_option' ) );
			add_filter( 'sa_sm_validate_current_page', array( $this, 'is_valid_page' ) );
			add_filter( 'sa_background_process_heartbeat_params', function( $params = array() ) { // For duplicate and non_post type dashboards.
				$params['pro'] = ( defined('SMPRO') && true === SMPRO ) ? true : false;
				return $params;
			} );
			add_action( 'sa_sm_manager_include_necessary_background_files', array( $this, 'include_necessary_background_files' ) );
			add_action('storeapps_smart_manager_scheduled_export_actions', array(&$this, 'scheduled_export_actions'));
			add_action('storeapps_smart_manager_scheduled_export_cleanup', array(&$this, 'scheduled_exports_cleanup_cron')); // For handling deletion of scheduled export files those are more than x number of days.
			add_filter( 'sa_is_callback_file_includable', function ( $flag = true, $params = array() ) {
				if ( empty( $params['process_key'] ) ) {
					return false;
				}
				return (
					( 'bulk_edit' === $params['process_key'] && ( ! empty( $params['is_common'] ) ) ) ||
					( 'bulk_edit' !== $params['process_key'] && empty( $params['is_common'] ) )
				) ? true : false;
			}, 10, 2 );
			add_action( 'sa_background_process_after_admin_notice_heading', array( __CLASS__, 'after_background_process_admin_notice_heading' ), 10, 1 );
			add_action( 'sa_manager_after_background_process_complete', array( __CLASS__, 'after_background_process_complete' ), 10, 2 );
		}

		/**
		 * Delete tasks from tasks table those are more than x number of days
		 *
		 * @return void
		 */
		public function schedule_tasks_cleanup_cron() {
			$tasks_cleanup_interval_days = get_option( 'sa_sm_tasks_cleanup_interval_days' );
			if ( empty( $tasks_cleanup_interval_days ) ) {
				return;
			}
			include_once( SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-base.php' );
			include_once dirname( __FILE__ ) . '/class-smart-manager-pro-base.php';
			include_once dirname( __FILE__ ) . '/class-smart-manager-pro-task.php';
			if ( is_callable( array( 'Smart_Manager_Pro_Task', 'delete_tasks' ) ) && is_callable( array( 'Smart_Manager_Pro_Task', 'get_task_ids' ) ) ) {
				Smart_Manager_Pro_Task::delete_tasks( Smart_Manager_Pro_Task::get_task_ids( date( 'Y-m-d H:i:s', strtotime( "-" . $tasks_cleanup_interval_days . " Days" ) ) ) );
			}
		    if ( is_callable( array( 'Smart_Manager_Pro_Task', 'schedule_task_deletion' ) ) ) {
				Smart_Manager_Pro_Task::schedule_task_deletion();
			}
		}

		/**
		 * Logs the batch process status and checks for time or memory exceedance and set the $batch_complete to true.
		 *
		 *
		 * @param array $params The parameters for the batch process.
		 *
		 * @return boolean true if time or memory exceeds else false
		 */
		public function sm_batch_process_log( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) || empty( $params['batch_params'] ) || ( ! is_array( $params['batch_params'] ) ) ) {
				return;
			}
			if ( $this->time_exceeded() || $this->memory_exceeded() ) { // Code for continuing the batch
				if ( is_callable( 'sa_manager_log' ) && ( ! empty( $batch_params['process_name'] ) ) ) {
					if ( $this->time_exceeded() ) {
						/* translators: %s: process name */
						sa_manager_log( 'notice', sprintf( _x('Time is exceeded for %s', 'batch handler time exceed status', 'smart-manager-for-wp-e-commerce' ), $batch_params['process_name'] ) );
					}
					if ( $this->memory_exceeded() ) {
						/* translators: %s: process name */
						sa_manager_log( 'notice', sprintf( _x( 'Memory is exceeded for %s', 'batch handler memory exceed status', 'smart-manager-for-wp-e-commerce' ), $batch_params['process_name'] ) );
					}
				}
				$initial_process = get_option( $params['identifier'] . '_initial_process', false );
				if ( ! empty( $initial_process ) ) {
					delete_option( $params['identifier'] . '_initial_process' );
				}
				return true;
			}
			return false;
		}

		/**
		 * Handles the batch processing for duplicating items.
		 *
		 * @param array $params {
		 *     Array of parameters required for batch processing.
		 *
		 *     @type array  $batch_params          Parameters for the batch process, including dashboard key and callback details.
		 *     @type array  $update_ids            List of IDs to be processed in the current batch.
		 *     @type int    $update_remaining_count The remaining count of items to be processed.
		 * }
		 *
		 * @return void
		 */
		public function handle_duplicate_batch_process( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) ) {
				return;
			}
			$batch_params = $params[ 'batch_params' ];
			$update_ids = $params[ 'update_ids' ];
			$update_remaining_count = $params[ 'update_remaining_count' ];
			foreach ( $update_ids as $key => $update_id ) {
			   $args = array( 'dashboard_key' => $batch_params['dashboard_key'], 'id' => $update_id, 'batch_params' => $batch_params );
			   $args = ( ! empty( $batch_params['callback_params'] ) && is_array( $batch_params['callback_params'] ) ) ? array_merge( $args, $batch_params['callback_params'] ) : $args;
			   $this->task( array( 'callback' => $batch_params['callback'], 'args' => $args ) );
			   update_option( $params['identifier'].'_current_time', time(), 'no' );
			   $batch_complete = $this->sm_batch_process_log( $params );
			   //Code for post update
			   $update_remaining_count = $update_remaining_count - 1;
			   update_option( $params['identifier'].'_remaining', $update_remaining_count, 'no' );
			   if ( 0 === $update_remaining_count ) { // Code for handling when the batch has completed.
				   do_action( 'sa_background_process_complete', $params['identifier'] ); // For triggering task deletion after successfully completing undo task/deleting task.
				   delete_option( $params['identifier'].'_ids' );
				   ( ! empty( get_option( $params['identifier'].'_is_background', false ) ) ) ? $this->complete() : delete_option( $params['identifier'].'_params' );
				   delete_option( $params['identifier'].'_is_background' );
			   } elseif ( ! empty( $batch_complete ) ) { //Code for continuing the batch
				   $update_ids = array_slice( $update_ids, $key+1 );
				   update_option( $params['identifier'].'_remaining', $update_remaining_count, 'no' );
				   update_option( $params['identifier'].'_ids', $update_ids, 'no' );
				   if ( function_exists( 'as_schedule_single_action' ) ) {
					   as_schedule_single_action( time(), $params['identifier'] );
				   }
				   break;
			   }
		   }
		}

		/**
		 * Checks if the current screen is associated with a valid post type.
		 *
		 * @return bool True if the current screen is associated with a valid post type, otherwise false.
		 */
		public function is_valid_page() {
			return ( ( ( ! empty( $_GET['page'] ) ) && ( 'smart-manager' === $_GET['page'] ) ) || ( wp_doing_ajax() ) ) ? true : false;
		}

		/**
		 * Ports the initial process option for the given identifier.
		 *
		 * @param string $identifier The unique identifier for the process. Defaults to an empty string.
		 *
		 * @return void
		 */
		public function port_initial_process_option( $identifier = '' ) {
			if ( empty( $identifier ) ) {
				return;
			}
			if ( false !== get_option( '_sm_update_42191', false ) ) {
				return;
			}
			delete_option( $this->identifier.'_initial_process' );
			update_option( '_sm_update_42191', 1, 'no' );
		}

		/**
		 * Includes necessary files and executes a callback function with provided parameters.
		 *
		 * @param array $params An array of parameters required for the background process task.
		 * @throws Exception If an error occurs during file inclusion or callback execution.
		 */
		public function include_necessary_background_files( $params = array() ) {
			if ( is_callable( 'sa_manager_log' ) ) {
				sa_manager_log( 'info', _x( 'Background process task params ', 'background process task params', 'smart-manager-for-wp-e-commerce' ) . print_r( $params, true ) );
			}
			if ( ! empty($params['callback']) && !empty($params['args']) ) {
				try {
					include_once dirname( __FILE__ ) .'/class-smart-manager-pro-utils.php';
					include_once( SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-base.php' );
					include_once dirname( __FILE__ ) .'/class-smart-manager-pro-base.php';
					$is_callback_file_includable = apply_filters(
						'sa_is_callback_file_includable', 
						true, 
						array( 
							'process_key' => ( ! empty( $params['args']['batch_params']['process_key'] ) ) ? $params['args']['batch_params']['process_key'] : '', 
							'is_common' => false 
						) 
					);
					if ( ! empty( $is_callback_file_includable ) ) {
						include_once dirname(__FILE__) . '/' . $params['callback']['class_path'];
					}

					if( ! class_exists( 'Smart_Manager_Task' ) && file_exists( SM_PLUGIN_DIR_PATH .'/classes/class-smart-manager-task.php' ) ){
						include_once SM_PLUGIN_DIR_PATH .'/classes/class-smart-manager-task.php';
					}
					if( ! class_exists( 'Smart_Manager_Pro_Task' ) && file_exists( dirname( __FILE__ ) .'/class-smart-manager-pro-task.php' ) ){
						include_once dirname( __FILE__ ) .'/class-smart-manager-pro-task.php';
					}

					if( !empty($params['args']) && is_array($params['args']) ) {
						if( !empty($params['args']['dashboard_key']) && file_exists(dirname( __FILE__ ) . '/class-smart-manager-pro-'. str_replace( '_', '-', $params['args']['dashboard_key'] ) .'.php')) {
							include_once dirname( __FILE__ ) . '/class-smart-manager-pro-'. str_replace( '_', '-', $params['args']['dashboard_key'] ) .'.php';
							$class_name = 'Smart_Manager_Pro_'.ucfirst( str_replace( '-', '_', $params['args']['dashboard_key'] ) );
							$obj = $class_name::instance($params['args']['dashboard_key']);
						}
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						trigger_error( 'Transactional email triggered fatal error for callback ' . $callback['filter'], E_USER_WARNING );
					}
				}
			}
		}

		/**
		 * Schedule export actions
		 *
		 * @param array $args arguments for schedule export action.
		 * @return void
		 */
		public function scheduled_export_actions($args = array())
		{
			if (empty($args) || ! is_array($args) || empty($args['class_path']) || empty($args['dashboard_key']) || empty($args['scheduled_export_params']) || empty($args['scheduled_export_params']['schedule_export_interval'])) {
				return;
			}
			$file_paths = array(
				SM_PLUGIN_DIR_PATH . '/common-core/classes/class-sa-manager-base.php',
				SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-base.php',
				SM_PLUGIN_DIR_PATH . '/pro/common-pro/classes/class-sa-manager-pro-base.php',
				dirname(__FILE__) . '/class-smart-manager-pro-base.php',
				dirname(__FILE__) . '/' . $args['class_path']
			);
			foreach ($file_paths as $file_path) {
				if (file_exists($file_path)) {
					include_once $file_path;
				}
			}
			// Validate if the class exists and method is callable before proceeding.
			if (! class_exists($args['class_nm']) || ! is_callable(array($args['class_nm'], 'instance')) || ! is_callable(array('SA_Manager_Pro_Base', 'get_scheduled_exports_advanced_search_query'))) {
				return;
			}
			$table_data = (empty(Smart_Manager::$sm_is_wc_hpos_tables_exists)) ? array('table_nm' => 'posts', 'status_col' => 'post_status', 'date_col' => 'post_date') : (! empty($args['table_model']['wc_orders']) ? array('table_nm' => 'wc_orders', 'status_col' => 'status', 'date_col' => 'date_created_gmt') : array());
			if (empty($table_data)) {
				return;
			}
			// Get the advanced search query.
			$args['advanced_search_query'] = SA_Manager_Pro_Base::get_scheduled_exports_advanced_search_query(
				array(
					'interval_days'  => absint($args['scheduled_export_params']['schedule_export_interval']),
					'order_statuses' => (! empty($args['scheduled_export_params']['schedule_export_order_statuses'])) ? $args['scheduled_export_params']['schedule_export_order_statuses'] : array(),
					'table_nm' => (! empty($table_data['table_nm'])) ? sanitize_key($table_data['table_nm']) : '',
					'status_col' => (! empty($table_data['status_col'])) ? sanitize_key($table_data['status_col']) : '',
					'date_col' => (! empty($table_data['date_col'])) ? sanitize_key($table_data['date_col']) : ''
				)
			);
			// Get class instance safely.
			$class_instance = call_user_func(array($args['class_nm'], 'instance'), $args['dashboard_key']);
			if ((! is_object($class_instance)) || (! is_callable(array($class_instance, 'get_export_csv')))) {
				return;
			}
			$class_instance->get_export_csv($args); //convert to static function.
		}

		/**
		 * Deletes scheduled export attachments older than a specified number of days.
		 *
		 * The default expiration is 30 days, but this can be overridden using the
		 * 'sm_scheduled_export_file_expiration_days' filter.
		 * @return void
		 */
		public function scheduled_exports_cleanup_cron()
		{
			$expiration_days = absint(get_option('sa_sm_scheduled_export_file_expiration_days'));
			if (empty($expiration_days)) {
				return;
			}
			global $wpdb;
			// Calculate expiration date.
			$expiration_date = gmdate('Y-m-d', time() - ($expiration_days * DAY_IN_SECONDS)) . ' 00:00:00';
			// Prepare query to get expired attachment IDs with meta key.
			$attachment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID 
					FROM {$wpdb->postmeta} pm 
					JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
					WHERE pm.meta_key = %s 
					  AND pm.meta_value = %s 
					  AND p.post_type = %s 
					  AND p.post_status = %s 
					  AND p.post_date < %s
					",
					'sa_sm_is_scheduled_export_file',
					'1',
					'attachment',
					'inherit',
					$expiration_date
				)
			);
			if ((is_wp_error($attachment_ids)) || (empty($attachment_ids)) || (! is_array($attachment_ids))) {
				return;
			}
			// Loop and delete each attachment permanently.
			foreach ($attachment_ids as $attachment_id) {
				if (empty($attachment_id)) {
					continue;
				}
				wp_delete_attachment((int) $attachment_id, true);
			}
		}

		/**
		 * Appends an background process admin notice heading message.
		 *
		 * @param array $args Arguments to customize the admin notice.
		 * 
		 * @return void.
		 */
		public static function after_background_process_admin_notice_heading( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty ( $args['update_product_subscriptions_price'] ) ) ) {
				return;
			}
			echo '<p>' . _x( 'After that, Smart Manager will automatically update all existing subscriptions related to the affected products.', 'admin notice after background process', 'smart-manager-for-wp-e-commerce' ) . '</p>';
		}

		/**
		 * Callback function executed after a background process completes.
		 *
		 * @param string $identifier    Unique identifier for the background process.
		 * @param array  $batch_params Parameters associated with the completed batch process.
		 *
		 * @return void
		 */
		public static function after_background_process_complete( $identifier = '', $batch_params = array() ) {
			if ( ( empty( $identifier ) ) || ( empty( $batch_params ) ) || ( ! is_array( $batch_params ) ) || ( ! class_exists( 'SA_Manager_Pro_Base' ) ) || ( ! class_exists( 'Smart_Manager_Pro_Base' ) ) || ( ! is_callable( array( 'Smart_Manager_Pro_Base', 'get_products_subscriptions' ) ) ) || ( ! is_callable( array( 'SA_Manager_Pro_Base', 'send_to_background_process' ) ) ) ) {
				return;
			}
			$product_subscriptions = Smart_Manager_Pro_Base::get_products_subscriptions( get_option( $identifier . '_subscription_product_ids', array() ) );
			if ( ( empty( $product_subscriptions ) ) || ( ! is_array( $product_subscriptions ) ) ) {
				return;
			}
			//Start the batch to update subscriptions related to products.
			if ( ( isset( $batch_params['update_product_subscriptions_price'] ) ) && ( false === $batch_params['update_product_subscriptions_price'] ) ) {
				delete_option( $identifier . '_subscription_product_ids' );
				return;
			}
			$batch_params['update_product_subscriptions_price'] = false;
			$batch_params['updating_product_subscriptions_price'] = true;
			$batch_params['active_dashboard']                   = _x( 'Subscriptions' , 'dashboard name', 'smart-manager-for-wp-e-commerce' );
			$batch_params['process_name']                       = _x( 'Subscription line items price update' , 'background update process name', 'smart-manager-for-wp-e-commerce' );
			$batch_params['selected_ids']                       = $product_subscriptions;
			$batch_params['id_count']                           = count( $batch_params['selected_ids'] );
			$batch_params['dashboard_key']                      = 'shop-subscription';
			$batch_params['class_path']                         = 'class-sa-manager-pro-subscription.php';
			$batch_params['class_nm']                           = 'Smart_Manager_Pro_Shop_Subscription';
			$batch_params['callback']['func']                   = array( $batch_params['class_nm'], 'sync_subscription_line_item_prices' );
			SA_Manager_Pro_Base::send_to_background_process( $batch_params );
		}
	}
}
Smart_Manager_Pro_Background_Updater::instance();
