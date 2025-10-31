<?php
/**
 * Common background updater class.
 *
 * @package common-pro/
 * @since       8.64.0
 * @version     8.67.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SA_Manager_Pro_Background_Updater' ) ) {
	/**
	 * Class properties and methods will go here.
	 */
	class SA_Manager_Pro_Background_Updater {


		/**
		 * Prefix for various identifiers.
		 *
		 * @var string
		 */
		public static $prefix = 'wp';

		/**
		 * WordPress cron schedule for background updates.
		 *
		 * Defines the schedule for running background updates via WordPress cron.
		 *
		 * @var string
		 */
		const CORE_WP_CRON_SCHEDULE = 'every_5_seconds';

		/**
		 * Current action name.
		 *
		 * Identifies the current action being performed.
		 *
		 * @var string
		 */
		protected $action = '';

		/**
		 * Identifier.
		 *
		 * Identifies the current task or operation being performed.
		 *
		 * @var string
		 */
		protected $identifier = '';

		/**
		 * Instance of the class.
		 *
		 * Stores the single instance of the class, following the Singleton pattern.
		 *
		 * @var self|null
		 */
		protected static $instance = null;

		/**
		 * Batch start time.
		 *
		 * Stores the timestamp when the batch processing started.
		 *
		 * @var string
		 */
		protected $batch_start_time = '';

		/**
		 * Action name for background update.
		 *
		 * Identifies the action hook used for background updates.
		 *
		 * @var string
		 */
		public static $action_name = '_background_update';

		/**
		 * This array is used to store and manage data
		 * required across different functionalities of the plugin.
		 *
		 * @var array $sa_manager_common_params
		 */
		public $sa_manager_common_params = array();

		/**
		 * Returns the single instance of the class, creating it if it doesn't exist.
		 *
		 * Ensures only one instance of the class exists.
		 *
		 * @param array $plugin_data Plugin data.
		 * @return self|null self::$instance The single instance of the class
		 */
		public static function instance( $plugin_data = array() ) {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self( $plugin_data );
			}
			return self::$instance;
		}

		/**
		 * Hook name for batch handler.
		 *
		 * Identifies the action hook used for batch processing of updates.
		 *
		 * @var string
		 */
		public static $batch_handler_hook = 'storeapps_batch_handler';

		/**
		 * Retrieves the identifier for the current operation.
		 *
		 * This method returns the identifier associated with the current operation or task.
		 * It can be used to uniquely identify the task being performed.
		 *
		 * @return string The identifier for the current operation.
		 */
		public static function get_identifier() {
			return self::$prefix . self::$action_name;
		}

		/**
		 * Stores the plugin object data
		 *
		 * @var array
		 */
		public $plugin_obj_data = array();

		/**
		 * Stores the plugin object key
		 *
		 * @var string
		 */
		public $plugin_obj_key = '';

		/**
		 * Stores the plugin data
		 *
		 * @var string
		 */
		public $plugin_data = '';

		/**
		 * Stores the plugin sku
		 *
		 * @var string
		 */
		public $plugin_sku = '';

		/**
		 * Initiate new background process
		 *
		 * @param Array $plugin_data Array of plugin data.
		 *
		 * @return void
		 */
		public function __construct( $plugin_data = array() ) {
			$plugin_data      = ( is_array( $plugin_data ) && ( ! empty( $plugin_data ) ) ) ? $plugin_data : array();
			$this->identifier = self::get_identifier();
			if ( ! empty( $plugin_data ) && is_array( $plugin_data ) ) {
				$this->plugin_data = $plugin_data;
			} else {
				$batch_params      = get_option( $this->identifier . '_params', array() );
				$this->plugin_data = ( ! empty( $batch_params['plugin_data'] ) ) ? $batch_params['plugin_data'] : array();
			}
			$this->action = self::$action_name;
			add_action( 'storeapps_batch_handler', array( $this, 'storeapps_batch_handler' ) );
			add_action( 'action_scheduler_failed_action', array( $this, 'restart_failed_action' ) );
			add_action( 'admin_notices', array( $this, 'background_process_notice' ) );
			add_action( 'admin_head', array( $this, 'background_heartbeat' ) );
			add_filter('cron_schedules', array($this, 'cron_schedules'), 1000); // phpcs:ignore
			add_filter('action_scheduler_run_schedule', array($this, 'modify_action_scheduler_run_schedule'), 1000); // phpcs:ignore
			add_action( 'wp_ajax_sa_stop_background_process', array( $this, 'stop_background_process' ) );
			$plugin_sku = ! empty( $this->plugin_data['plugin_sku'] ) ? $this->plugin_data['plugin_sku'] : $this->plugin_sku;
			add_action( 'storeapps_' . $plugin_sku . '_scheduled_actions', array( $this, 'schedule_bulk_edit_actions' ) );
			add_action( 'action_scheduler_canceled_action', array( $this, 'delete_ids_option_on_cancelled_bulk_edit_scheduled_action' ), 1 );
		}

		/**
		 * Check if batch scheduled action is running
		 *
		 * @return boolean
		 */
		public function is_action_scheduled() {
			$is_scheduled = false;
			if ( function_exists( 'as_has_scheduled_action' ) ) {
				$is_scheduled = ( as_has_scheduled_action( self::$batch_handler_hook ) ) ? true : false;
			} elseif ( function_exists( 'as_next_scheduled_action' ) ) {
				$is_scheduled = ( as_next_scheduled_action( self::$batch_handler_hook ) ) ? true : false;
			}
			return $is_scheduled;
		}

		/**
		 * Stop all scheduled actions by this plugin
		 */
		public function stop_scheduled_actions() {
			if ( function_exists( 'as_unschedule_action' ) ) {
				as_unschedule_action( self::$batch_handler_hook );
			}
			$this->clean_scheduled_action_data( true );
		}

		/**
		 * Stop batch background process via AJAX
		 */
		public function stop_background_process() {
			$batch_params      = get_option( $this->identifier . '_params', array() );
			$this->plugin_data = ( ! empty( $batch_params['plugin_data'] ) ) ? $batch_params['plugin_data'] : array();
			$this->plugin_sku  = ( ! empty( $batch_params['plugin_data']['plugin_sku'] ) ) ? $batch_params['plugin_data']['plugin_sku'] : '';
			check_ajax_referer( 'sa-' . $this->plugin_sku . '-manager-security', 'security' );
			$this->stop_scheduled_actions();
			wp_send_json_success();
		}

		/**
		 * Clean scheduled action data
		 *
		 * @param  boolean $abort flag whether the process has been forcefully stopped or not.
		 */
		public function clean_scheduled_action_data( $abort = false ) {
			delete_option( $this->identifier . '_start_time' );
			delete_option( $this->identifier . '_current_time' );
			delete_option( $this->identifier . '_tot' );
			delete_option( $this->identifier . '_remaining' );
			delete_option( $this->identifier . '_initial_process' );
			delete_option( $this->identifier . '_last_batch_size' );
			delete_option( $this->identifier . '_last_batch_duration' );
			delete_option( $this->identifier . '_subscription_product_ids' );
			if ( ! empty( $abort ) ) {
				delete_option( $this->identifier . '_ids' );
				delete_option( $this->identifier . '_params' );
				delete_option( $this->identifier . '_is_background' );
			}
		}

		/**
		 * Memory exceeded
		 *
		 * Ensures the batch process never exceeds 90%
		 * of the maximum WordPress memory.
		 *
		 * @return bool
		 */
		protected function memory_exceeded() {
			$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory.
			$current_memory = memory_get_usage( true );
			return ( $current_memory >= $memory_limit ) ? true : false;
		}

		/**
		 * Get memory limit.
		 *
		 * @return int
		 */
		protected function get_memory_limit() {
			$memory_limit = ( function_exists( 'ini_get' ) ) ? ini_get( 'memory_limit' ) : '128M';
			$memory_limit = ( ! $memory_limit || ( -1 === intval( $memory_limit ) ) ) ? '32G' : $memory_limit;
			return wp_convert_hr_to_bytes( $memory_limit );
		}

		/**
		 * Time exceeded.
		 *
		 * Ensures the batch never exceeds a sensible time limit.
		 * A timeout limit of 30s is common on shared hosting.
		 *
		 * @return bool
		 */
		protected function time_exceeded() {
			$return = false;
			if ( empty( $this->batch_start_time ) ) {
				$return = false;
			} else {
				$finish = $this->batch_start_time + apply_filters( $this->identifier . '_batch_default_time_limit', 20 ); // 20 seconds.
				$return = ( time() >= $finish ) ? true : $return;
			}
			return apply_filters( $this->identifier . '_batch_time_exceeded', $return );
		}

		/**
		 * Function for handling completion of batch process.
		 *
		 * @return void
		 */
		public function complete() {
			SA_Manager_Pro_Base::batch_process_complete();
		}

		/**
		 * Checks if background process is running
		 *
		 * @return bool  $is_process_running
		 */
		public function is_process_running() {
			return ( ! empty( get_option( $this->identifier . '_params', array() ) ) ) ? true : false;
		}

		/**
		 * Restart scheduler after one minute if it fails
		 *
		 * @param  array $action_id id of failed action.
		 */
		public function restart_failed_action( $action_id = array() ) {
			if ( ! class_exists( 'ActionScheduler' ) || ! is_callable( array( 'ActionScheduler', 'store' ) ) || ! function_exists( 'as_schedule_single_action' ) || empty( $action_id ) ) {
				return;
			}
			$action      = ActionScheduler::store()->fetch_action( $action_id );
			$action_hook = $action->get_hook();
			if ( self::$batch_handler_hook === $action_hook ) {
				as_schedule_single_action( time() + MINUTE_IN_SECONDS, self::$batch_handler_hook );
			}
		}

		/**
		 * Function to modify the action sceduler run schedule
		 *
		 * @param string $wp_cron_schedule schedule interval key.
		 * @return string $wp_cron_schedule
		 */
		public function modify_action_scheduler_run_schedule( $wp_cron_schedule = '' ) {
			return self::CORE_WP_CRON_SCHEDULE;
		}

		/**
		 * Function to add entry to cron_schedules
		 *
		 * @param array $schedules schedules with interval and display.
		 * @return array $schedules
		 */
		public function cron_schedules( $schedules = array() ) {
			$schedules[ self::CORE_WP_CRON_SCHEDULE ] = array(
				'interval' => 5,
				'display'  => _x( 'Every 5 Seconds', 'background process notice', 'smart-manager-for-wp-e-commerce' ),
			);
			return $schedules;
		}

		/**
		 * Schedule bulk edit actions
		 *
		 * @param array $args arguments of bulk edit action.
		 * @return void
		 */
		public function schedule_bulk_edit_actions( $args = array() ) {
			if ( empty( $args ) || ! is_array( $args ) || empty( $args['callback']['class_path'] ) || empty( $args['dashboard_key'] ) ) {
				return;
			}
			$class_path           = ( function_exists( 'wc_clean' ) ) ? wc_clean( wp_unslash( $args['callback']['class_path'] ) ) : sanitize_text_field( wp_unslash( $args['callback']['class_path'] ) );
			$supported_post_types = is_callable( 'sa_get_supported_post_types' ) ? sa_get_supported_post_types() : array();
			$supported_classes    = array();
			if ( ( ! empty( $supported_post_types ) ) && is_array( $supported_post_types ) ) {
				$supported_classes = array_merge(
					array(
						'class-sa-manager-pro-background-updater.php',
						'class-sa-manager-pro-base.php',
					),
					array_map(
						function ( $post_type ) {
							return 'class-sa-manager-pro-' . $post_type . '.php';
						},
						$supported_post_types
					)
				);
			}
			if ( ! in_array( $class_path, $supported_classes, true ) ) {
				return false;
			}
			$plugin_sku         = ! empty( $this->plugin_data['plugin_sku'] ) ? $this->plugin_data['plugin_sku'] : '';
			$plugin_folder_flag = ! empty( $this->plugin_data['folder_flag'] ) ? $this->plugin_data['folder_flag'] : '';
			$plugin_folder_flag = ( '/lib' === $this->plugin_data['folder_flag'] ) ? $this->plugin_data['folder_flag'] : '';
			$constant_name      = strtoupper( $plugin_sku ) . '_PLUGIN_DIR_PATH';
			$plugin_dir         = defined( $constant_name ) ? constant( $constant_name ) . $plugin_folder_flag : '';
			include_once $plugin_dir . '/common-core/classes/class-sa-manager-base.php';

			// include all the supported_post_types class files.
			foreach ( $supported_classes as $class ) {
				$file = realpath( dirname( __FILE__ ) . '/' . $class );
				if ( is_file( $file ) ) {
					include_once $file;
				}
			}

			$args['scheduled_for'] = '0000-00-00 00:00:00';
			$is_process_running    = ( ! empty( get_option( $this->identifier . '_remaining', array() ) ) ) ? true : false;
			$obj                   = ( 'SA_Manager_Pro_' . ucfirst( str_replace( '-', '_', $args['dashboard_key'] ) ) )::instance( $this->plugin_data );
			if ( ! $is_process_running && is_callable( array( 'SA_Manager_Pro_Base', 'send_to_background_process' ) ) ) {
				SA_Manager_Pro_Base::send_to_background_process( $args );
			} else {
				$plugin_sku           = ( ( ! empty( $this->plugin_data['plugin_sku'] ) ) ) ? $this->plugin_data['plugin_sku'] : '';
				$rescheduled_interval = apply_filters( 'sa_' . $plugin_sku . '_bulk_edit_action_rescheduled_interval', intval( get_option( 'sa_' . $plugin_sku . 'bulk_edit_action_rescheduled_interval', 30 ) ) );
				as_schedule_single_action( strtotime( gmdate( 'Y-m-d H:i:s', strtotime( '+' . $rescheduled_interval . ' minutes' ) ) ), strtoupper( $plugin_sku ) . '_SCHEDULED_ACTIONS' );
			}
		}

		/**
		 * Initiate Batch Process
		 *
		 * Initiate batch process and pass control to batch_handler function
		 *
		 * @param array $update_ids array of ids to process.
		 */
		public function initiate_batch_process( $update_ids = array() ) {
			if ( empty( $update_ids ) || ( ! is_array( $update_ids ) ) ) {
				return;
			}
			update_option( $this->identifier . '_tot', count( $update_ids ), 'no' );
			update_option( $this->identifier . '_remaining', count( $update_ids ), 'no' );
			update_option( $this->identifier . '_start_time', time(), 'no' );
			update_option( $this->identifier . '_current_time', time(), 'no' );
			update_option( $this->identifier . '_initial_process', 1, 'no' );
			as_unschedule_action( self::$batch_handler_hook );
			if ( is_callable( array( $this, 'storeapps_batch_handler' ) ) ) {
				$this->storeapps_batch_handler();
			}
		}

		/**
		 * Batch Handler
		 *
		 * Pass each queue item to the task handler, while remaining
		 * within server memory and time limit constraints.
		 */
		public function storeapps_batch_handler() {
			$batch_params = get_option( $this->identifier . '_params', array() );
			$update_ids   = get_option( $this->identifier . '_ids', array() );
			if ( is_callable( 'sa_manager_log' ) ) {
				sa_manager_log( 'info', _x( 'Batch handler params ', 'batch handler params', 'smart-manager-for-wp-e-commerce' ) . wp_json_encode( $batch_params ) );
				sa_manager_log( 'info', _x( 'Batch handler update ids ', 'batch handler update ids', 'smart-manager-for-wp-e-commerce' ) . wp_json_encode( $update_ids ) );
			}
			if ( empty( $batch_params ) || empty( $update_ids ) || ( ! is_array( $batch_params ) ) || ( ! is_array( $update_ids ) ) || empty( $batch_params['process_name'] ) || empty( $batch_params['process_key'] ) ) {
				return;
			}
			$start_time = get_option( $this->identifier . '_start_time', false );
			if ( empty( $start_time ) ) {
				update_option( $this->identifier . '_start_time', time(), 'no' );
			}
			$this->batch_start_time = time();
			$batch_complete         = false;
			$update_remaining_count = get_option( $this->identifier . '_remaining', false );
			$update_tot_count       = get_option( $this->identifier . '_tot', false );
			$excluded_process_names = apply_filters( 'sa_excluded_process_names', array() );
			$params                 = array(
				'update_ids'             => $update_ids,
				'update_remaining_count' => ( ! empty( $update_remaining_count ) ) ? $update_remaining_count : 0,
				'batch_params'           => $batch_params,
				'identifier'             => $this->identifier,
			);
			if ( ! in_array( $batch_params['process_key'], $excluded_process_names, true ) ) {
				$this->batch_process( $params );
				return;
			}
			do_action( 'sa_handle_batch_process', $params );
		}

		/**
		 * Get background process progress via ajax
		 */
		public function get_background_progress() {
			$response          = array();
			$progress          = $this->calculate_background_process_progress();
			$percent           = ( ! empty( $progress['percent_completion'] ) ) ? $progress['percent_completion'] : 0;
			$remaining_seconds = ( ! empty( $progress['remaining_seconds'] ) ) ? $progress['remaining_seconds'] : 0;
			$response          = array(
				'ack'               => 'Success',
				'per'               => $percent,
				'remaining_seconds' => $remaining_seconds,
			);
			wp_send_json( $response );
		}

		/**
		 * Calculate progress of background process
		 *
		 * @return array $progress
		 */
		public function calculate_background_process_progress() {
			$progress              = array(
				'percent_completion' => 0,
				'remaining_seconds'  => 0,
			);
			$start_time            = get_option( $this->identifier . '_start_time', false );
			$current_time          = get_option( $this->identifier . '_current_time', false );
			$all_tasks_count       = get_option( $this->identifier . '_tot', false );
			$remaining_tasks_count = get_option( $this->identifier . '_remaining', false );
			if ( empty( $start_time ) && empty( $current_time ) && empty( $all_tasks_count ) && empty( $remaining_tasks_count ) ) {
				$progress = array(
					'percent_completion' => 100,
					'remaining_seconds'  => 0,
				);
			} else {
				$percent_completion = floatval( 0 );
				if ( false !== $all_tasks_count && false !== $remaining_tasks_count ) {
					$percent_completion             = ( ( intval( $all_tasks_count ) - intval( $remaining_tasks_count ) ) * 100 ) / intval( $all_tasks_count );
					$progress['percent_completion'] = floatval( $percent_completion );
				}
				if ( $percent_completion > 0 && false !== $start_time && false !== $current_time ) {
					$time_taken_in_seconds         = intval( $current_time ) - intval( $start_time );
					$time_remaining_in_seconds     = ( $time_taken_in_seconds / $percent_completion ) * ( 100 - $percent_completion );
					$progress['remaining_seconds'] = ceil( $time_remaining_in_seconds );
				}
				if ( $progress['percent_completion'] >= 100 ) { // on process completion.
					$this->clean_scheduled_action_data();
				}
			}
			return $progress;
		}

		/**
		 * Function to display admin notice in case of background process
		 */
		public function background_process_notice() {
			$batch_params       = get_option( $this->identifier . '_params', array() );
			$this->plugin_data  = ( ! empty( $batch_params['plugin_data'] ) ) ? $batch_params['plugin_data'] : array();
			$this->plugin_sku   = ( ! empty( $batch_params['plugin_data']['plugin_sku'] ) ) ? $batch_params['plugin_data']['plugin_sku'] : '';
			$param_for_ajax_url = apply_filters( 'sa_param_for_ajax_url', '' );
			$is_valid_page      = apply_filters( 'sa_' . $this->plugin_sku . '_validate_current_page', true );
			if ( ( ! is_admin() ) || ( ! ( $is_valid_page ) ) ) {
				return;
			}
			$params_for_background_progress = apply_filters( 'sa_background_process_heartbeat_params', array() );
			$request_data_params            = ( ! empty( $params_for_background_progress ) ) ? $params_for_background_progress : array();
			$initial_process                = get_option( $this->identifier . '_initial_process', false );
			if ( ! empty( $initial_process ) ) {
				do_action( 'sa_port_initial_process_option', $this->identifier );
				$progress = $this->calculate_background_process_progress();
				$percent  = ( ! empty( $progress['percent_completion'] ) ) ? $progress['percent_completion'] : 0;
				if ( $percent >= 100 ) {
					return;
				}
			}
			if ( ! $this->is_process_running() && empty( $initial_process ) ) {
				return;
			}
			update_option( $this->identifier . '_is_background', 1, 'no' );
			$process_name      = ( ! empty( $batch_params['process_name'] ) ) ? $batch_params['process_name'] : 'Batch';
			$current_dashboard = ( ! empty( $batch_params['active_dashboard'] ) ) ? $batch_params['active_dashboard'] : 'Products';
			$no_of_records     = ( ( ! empty( $batch_params['entire_store'] ) ) ? _x( 'All', 'entire store', 'smart-manager-for-wp-e-commerce' ) : $batch_params['id_count'] ) . ' ' . esc_html( $current_dashboard );
			$admin_email       = get_option( 'admin_email', false );
			$admin_email       = ( empty( $admin_email ) ) ? 'admin email' : $admin_email;
			?>
			<div id="sa_background_process_progress" class="error" style="display: none;">
				<?php
				if ( empty( $this->is_action_scheduled() ) && empty( $initial_process ) ) {
					$this->clean_scheduled_action_data( true );
					?>
					<p>
						<?php
						/* translators: 1. Error title 2. The bulk process */
						echo sprintf( esc_html__( '%1$s: The %2$s process has stopped. Please review the dashboard to check the status.', 'smart-manager-for-wp-e-commerce' ), '<strong>' . esc_html__( 'Error', 'smart-manager-for-wp-e-commerce' ) . '</strong>', '<strong>' . esc_html( strtolower( $process_name ) ) . '</strong>' );
						?>
					</p>
					<?php
				} else {
					?>
					<p>
						<?php
						echo '<strong>' . esc_html__( 'Important', 'smart-manager-for-wp-e-commerce' ) . '</strong>:';
						echo '&nbsp;' . esc_html( $process_name ) . '&nbsp;' . esc_html__( 'request is running', 'smart-manager-for-wp-e-commerce' ) . '&nbsp;';
						echo esc_html__( 'in the background. You will be notified on', 'smart-manager-for-wp-e-commerce' ) . '&nbsp; <code>' . esc_html( $admin_email ) . '</code>&nbsp; ' . esc_html__( 'when it is completed.', 'smart-manager-for-wp-e-commerce' ) . '&nbsp;';
						?>
					</p>
					<?php do_action( 'sa_background_process_after_admin_notice_heading', $batch_params ); ?>
					<p>
						<span id="sa_remaining_time_label">
							<?php echo esc_html__( 'Progress', 'smart-manager-for-wp-e-commerce' ); ?>:&nbsp;
							<strong><span id="sa_remaining_time"><?php echo esc_html__( '--:--:--', 'smart-manager-for-wp-e-commerce' ); ?></span></strong>&nbsp;&nbsp;
							<a id="sa-stop-batch-process" href="javascript:void(0);" style="color: #dc3232;"><?php echo esc_html__( 'Stop', 'smart-manager-for-wp-e-commerce' ); ?></a>
						</span>
					</p>
					<p class="<?php echo esc_attr( $batch_params['plugin_data']['plugin_obj_key'] ); ?>_background_note">
						<?php
						echo '<strong>' . esc_html__( 'NOTE', 'smart-manager-for-wp-e-commerce' ) . '</strong>:&nbsp;';
						echo wp_kses_post( $batch_params['backgroundProcessRunningMessage'] );
						?>
					</p>
			</div>
			<div id="sa_background_process_complete" class="updated" style="display: none;">
				<p>
					<strong><?php echo esc_html( $process_name ); ?></strong>
					<?php echo esc_html__( 'for', 'smart-manager-for-wp-e-commerce' ) . ' <strong>' . esc_html( $no_of_records ) . '</strong> ' . esc_html__( 'completed successfully', 'smart-manager-for-wp-e-commerce' ); ?>
				</p>
			</div>
			<script type="text/javascript">
				if (typeof sa_background_process_heartbeat === 'function') {
					sa_background_process_heartbeat(0, '<?php echo esc_html( $process_name ); ?>', '<?php echo esc_html( $batch_params['plugin_data']['plugin_obj_key'] ); ?>');
				}
				jQuery('body').on('click', '#sa-stop-batch-process', function(e) {
					e.preventDefault();
					<?php /* translators: 1. The bulk process */ ?>
					let admin_ajax_url = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
					admin_ajax_url = (admin_ajax_url.indexOf('?') !== -1) ? admin_ajax_url + '&action=sa_' + '<?php echo esc_js( $batch_params['plugin_data']['plugin_obj_key'] ); ?>' + '_manager_include_file' : admin_ajax_url + '?action=sa_' + '<?php echo esc_js( $batch_params['plugin_data']['plugin_obj_key'] ); ?>' + '_manager_include_file';
					let result = window.confirm(' <?php echo sprintf( /* translators: %s: process name */esc_html__( 'Are you sure you want to stop the %s process? Click OK to stop.', 'smart-manager-for-wp-e-commerce' ), esc_html( $process_name ) ); ?> ');
					if (result) {
						let requestData = {
							action: 'sa_stop_background_process',
							security: '<?php echo esc_attr( wp_create_nonce( 'sa-' . $batch_params['plugin_data']['plugin_sku'] . '-manager-security' ) ); ?>',
						};
						Object.assign(requestData, <?php echo wp_json_encode( $request_data_params ); ?>);
						jQuery.ajax({
							url: admin_ajax_url+<?php echo wp_json_encode( $param_for_ajax_url ); ?>,
							method: 'post',
							dataType: 'json',
							data: requestData,
							success: function(response) {
								location.reload();
							}
						});
					}
				});
			</script>
					<?php
				}
				?>
		<script type="text/javascript">
			jQuery('#sa_background_process_progress').fadeIn();
		</script>
		</div>
			<?php
		}

		/**
		 * Process the deletion/bulk edit/undo of records.
		 *
		 * @param array $params Required params array.
		 */
		public function batch_process( $params = array() ) {
			if ( empty( $params ) || ( ! is_array( $params ) ) || empty( $params['update_ids'] ) || empty( $params['update_remaining_count'] ) || empty( $params['batch_params'] ) ) {
				return;
			}
			$remaining_ids          = $params['update_ids']; // Initially remaining ids are equal to total ids.
			$update_remaining_count = $params['update_remaining_count'];
			$batch_params           = $params['batch_params'];
			$batch_complete         = false;
			// Get a slice of n records from $remaining_ids.
			$batch_size = $this->get_dynamic_batch_size();
			if ( empty( $batch_size ) ) {
				return;
			}
			// Process the current batch of IDs.
			while ( ( is_array( $remaining_ids ) ) && ( 0 !== $update_remaining_count ) ) {
				$batch_ids_to_process = ( ! empty( $remaining_ids ) ) ? array_slice( $remaining_ids, 0, $batch_size ) : array();
				if ( empty( $batch_ids_to_process ) || ( ! is_array( $batch_ids_to_process ) ) ) {
					break;
				}
				$batch_start_time = microtime( true );
				// Call your task with the current batch.
				$this->task(
					array(
						'callback' => $batch_params['callback'],
						'args'     => array(
							'batch_params'  => $batch_params,
							'selected_ids'  => $batch_ids_to_process,
							'dashboard_key' => $batch_params['dashboard_key'],
						),
					)
				);
				update_option( $this->identifier . '_last_batch_duration', round( microtime( true ) - $batch_start_time, 3 ) );
				update_option( $this->identifier . '_current_time', time(), 'no' );
				$batch_complete = $this->sa_batch_process_log( $params );
				// Check if we've processed all records.
				$remaining_ids          = array_slice( $remaining_ids, $batch_size );
				$update_remaining_count = count( $remaining_ids );
				if ( 0 === $update_remaining_count ) {
					// All records processed.
					do_action( 'sa_manager_background_process_complete', $this->identifier );
					delete_option( $this->identifier . '_ids' );
					( ! empty( get_option( $this->identifier . '_is_background', false ) ) ) ? $this->complete() : delete_option( $this->identifier . '_params' );
					delete_option( $this->identifier . '_is_background' );
					delete_option( $this->identifier . '_start_time' );
					delete_option( $this->identifier . '_current_time' );
					delete_option( $this->identifier . '_tot' );
					delete_option( $this->identifier . '_remaining' );
					delete_option( $this->identifier . '_last_batch_size' );
					delete_option( $this->identifier . '_last_batch_duration' );
					// Action hook after all records processed in batch.
					do_action( 'sa_manager_after_background_process_complete', $this->identifier, $batch_params );
					break;
				} elseif ( ! empty( $batch_complete ) ) { // Code for continuing the batch.
					update_option( $this->identifier . '_remaining', $update_remaining_count, 'no' );
					update_option( $this->identifier . '_ids', $remaining_ids, 'no' ); // remaining ids to update.
					// Schedule the next batch.
					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( time(), self::$batch_handler_hook );
					}
					break;
				}
			}
		}

		/**
		 * Background process task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param array $params Update callback function.
		 * @return mixed
		 */
		protected function task( $params = array() ) {
			if ( is_callable( 'sa_manager_log' ) ) {
				sa_manager_log('info', _x('Background process task params ', 'background process task params', 'smart-manager-for-wp-e-commerce') . print_r($params, true)); // phpcs:ignore
			}
			if ( ( empty( $params['callback'] ) || empty( $params['args'] ) ) || ( empty( $params['args']['batch_params']['plugin_data'] ) || ( ! is_array( $params['args']['batch_params']['plugin_data'] ) ) ) ) {
				return false;
			}
			try {
				$plugin_data        = $params['args']['batch_params']['plugin_data'];
				$plugin_sku         = $plugin_data['plugin_sku'];
				$plugin_folder_flag = $plugin_data['folder_flag'];
				$constant_name      = strtoupper( $plugin_sku ) . '_PLUGIN_DIR_PATH';
				$plugin_dir         = defined( $constant_name ) ? constant( $constant_name ) : '';
				// For ABE.
				if ( ( ! empty( $plugin_data['folder_flag'] ) ) && ( '/lib' === $plugin_data['folder_flag'] ) ) {
					$plugin_dir = $plugin_dir . $plugin_data['folder_flag'];
				}
				// Including base file.
				self::sa_manager_file_safe_include( $plugin_dir, '/common-core/classes/class-sa-manager-utils.php' );
				self::sa_manager_file_safe_include( $plugin_dir, '/common-core/classes/class-sa-manager-base.php' );
				// Including pro files.
				$plugin_dir = defined( $constant_name ) ? constant( $constant_name ) . $plugin_folder_flag : '';
				self::sa_manager_file_safe_include( $plugin_dir, 'common-pro/classes/class-sa-manager-pro-base.php' );
				$is_callback_file_includable = apply_filters(
					'sa_is_callback_file_includable',
					true,
					array(
						'process_key' => ( ! empty( $params['args']['batch_params']['process_key'] ) ) ? $params['args']['batch_params']['process_key'] : '',
						'is_common'   => true,
					)
				);
				if ( ! empty( $is_callback_file_includable ) && ! empty( $params['callback']['class_path'] ) ) {
					self::sa_manager_file_safe_include( $plugin_dir . '/common-pro/classes/', $params['callback']['class_path'] );
				}
				if ( ! empty( $params['args'] ) && is_array( $params['args'] ) ) {
					if ( ! empty( $params['args']['dashboard_key'] ) ) {
						$dashboard_key   = str_replace( '_', '-', $params['args']['dashboard_key'] );
						$relative_path   = 'class-sa-manager-pro-' . $dashboard_key . '.php';
						$common_pro_path = $plugin_dir . '/common-pro/classes/';
						$class_file_path = $common_pro_path . $relative_path;
						// Securely include file.
						self::sa_manager_file_safe_include( $common_pro_path, $relative_path );
						// Generate class name.
						$class_name = 'SA_Manager_Pro_' . ucfirst( str_replace( '-', '_', $dashboard_key ) );
						// Instantiate if possible.
						if ( class_exists( $class_name ) && is_callable( array( $class_name, 'instance' ) ) ) {
							$obj = $class_name::instance( $plugin_data );
						}
					} else {
						$obj = ( class_exists( 'SA_Manager_Pro_Base' ) && ( is_callable( array( 'SA_Manager_Pro_Base', 'instance' ) ) ) ) ? SA_Manager_Pro_Base::instance( $plugin_data ) : null;
					}
				}
				do_action( 'sa_' . $plugin_sku . '_manager_include_necessary_background_files', array_merge( $params, array( 'plugin_dir' => $plugin_dir ) ) ); // phpcs:ignore
				if ( is_callable( array( $params['callback']['func'][0], 'actions' ) ) ) {
					call_user_func( array( $params['callback']['func'][0], 'actions' ) );
				}
				call_user_func( $params['callback']['func'], $params['args'] );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					trigger_error(esc_html_x('Transactional email triggered fatal error for callback ', 'fatal error for callback', 'smart-manager-for-wp-e-commerce'), E_USER_WARNING); // phpcs:ignore
				}
			}
			return false;
		}

		/**
		 * Safely includes a PHP file from a specified base directory and relative path.
		 *
		 * @param string $base_dir      The base directory from which to resolve the file path.
		 * @param string $relative_path The relative path to the file to be included.
		 *
		 * @return void
		 */
		public static function sa_manager_file_safe_include( $base_dir = '', $relative_path = '' ) {
			$base_dir    = realpath( rtrim( $base_dir, '/' ) );
			$target_file = realpath( $base_dir . '/' . ltrim( $relative_path, '/' ) );
			if (
				$base_dir &&
				$target_file &&
				file_exists( $target_file ) &&
				0 === strpos( $target_file, $base_dir ) &&
				preg_match( '/\.php$/i', $target_file )
			) {
				if ( is_file( $target_file ) ) {
					include_once $target_file; // nosemgrep: audit.php.lang.security.file.inclusion-arg .
				}
			}
		}

		/**
		 * Adds a background heartbeat JavaScript snippet on admin pages.
		 *
		 * @return void
		 */
		public function background_heartbeat() {
			$batch_params                   = get_option( $this->identifier . '_params', array() );
			$this->plugin_data              = ( ! empty( $batch_params['plugin_data'] ) ) ? $batch_params['plugin_data'] : array();
			$this->plugin_sku               = ( ! empty( $batch_params['plugin_data']['plugin_sku'] ) ) ? $batch_params['plugin_data']['plugin_sku'] : '';
			$this->plugin_obj_key           = ( ! empty( $this->plugin_data['plugin_obj_key'] ) ) ? $this->plugin_data['plugin_obj_key'] : '';
			$params_for_background_progress = apply_filters( 'sa_background_process_heartbeat_params', array() );
			$param_for_ajax_url             = apply_filters( 'sa_param_for_ajax_url', '' );
			$request_data_params            = ( ! empty( $params_for_background_progress ) ) ? $params_for_background_progress : array();
			$is_valid_page                  = apply_filters( 'sa_' . $this->plugin_sku . '_validate_current_page', true, $batch_params );
			if ( ( ! is_admin() ) || ( ! ( $is_valid_page ) ) ) {
				return;
			}
			?>
			<script type="text/javascript">
				let sa_background_process_heartbeat = function(delay = 0, process = '', pluginKey){
					if(!pluginKey) {
						return;
					}
					let pluginSku = '';
					let security = '';
					if(window[pluginKey] && (window[pluginKey].hasOwnProperty('pluginSlug')) && (window[pluginKey].pluginSlug!=='undefined') && (window[pluginKey].hasOwnProperty('saCommonNonce')) &&(window[pluginKey].saCommonNonce!=='undefined')){
						pluginSku = window[pluginKey].pluginSlug;
						security  = window[pluginKey].saCommonNonce;
					} else{
						pluginKey = "<?php echo esc_attr( $this->plugin_obj_key ); ?>";
						pluginSku = (!pluginSku) ? "<?php echo esc_attr( $this->plugin_sku ); ?>" : pluginSku;
						security  = '<?php echo esc_attr( wp_create_nonce( 'sa-' . $this->plugin_sku . '-manager-security' ) ); ?>';
					}
					let admin_ajax_url = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
					admin_ajax_url = ((admin_ajax_url.indexOf('?') !== -1) ? admin_ajax_url + '&action=sa_' + pluginSku + '_manager_include_file' : admin_ajax_url + '?action=sa_' + pluginSku + '_manager_include_file');
					let requestData = {
						cmd: 'get_background_progress',
						active_module: 'Background_Updaters',
						security: security
					};
					Object.assign(requestData, <?php echo wp_json_encode( $request_data_params ); ?>);
					let ajaxParams = {
						url: admin_ajax_url+ <?php echo wp_json_encode( $param_for_ajax_url ); ?>,
						method: 'post',
						dataType: 'json',
						data: requestData,
						success: function(response) {
							let isBackground = false;
							if (jQuery('#sa_background_process_progress').length > 0 && jQuery('#sa_background_process_progress').is(":visible") === true) {
								isBackground = true;
							}
							if (response.ack == 'Success') {
								//Code for updating the progressbar
								let per = parseInt(response.per),
									remainingSeconds = response.remaining_seconds;
								if (isBackground) {
									jQuery('#sa_remaining_time').html(Math.round(parseInt(per)) + '<?php echo esc_html_x( '% completed', 'percentage progress', 'smart-manager-for-wp-e-commerce' ); ?>');
									let hours = 0,
										minutes = 0,
										seconds = 0;
									hours = Math.floor(remainingSeconds / 3600);
									remainingSeconds %= 3600;
									minutes = Math.floor(remainingSeconds / 60);
									seconds = remainingSeconds % 60;
									hours = hours < 10 ? "0" + hours : hours;
									minutes = minutes < 10 ? "0" + minutes : minutes;
									seconds = seconds < 10 ? "0" + seconds : seconds;
									jQuery('#sa_remaining_time').append(' [' + hours + ":" + minutes + ":" + seconds + ' left]')
								} else {
									if (jQuery('.sa_background_update_progressbar').html() == 'Initializing...') {
										jQuery('.sa_background_update_progressbar').html('');
									}
									jQuery('.sa_background_update_progressbar').progressbar({
										value: parseInt(per)
									}).children('.ui-progressbar-value').css({
										"background": "#508991",
										"height": "2.5em",
										"color": "#FFF"
									});
									jQuery('.sa_background_update_progressbar_text').html(Math.round(parseInt(per)) + '<?php echo esc_html__( '% Completed', 'smart-manager-for-wp-e-commerce' ); ?>');
								}
								if (per < 100) {
									setTimeout(function() {
										sa_background_process_heartbeat(0, process, pluginKey);
									}, 1000);
								} else {
									if (isBackground) {
										jQuery('#sa_background_process_progress').fadeOut();
										jQuery('#sa_background_process_complete').fadeIn();
										setTimeout(function() {
											jQuery('#sa_background_process_complete').fadeOut();
										}, 10000);
									} else {
										window[pluginKey].modal = {};
										if (typeof(window[pluginKey].getDefaultRoute) !== "undefined" && typeof(window[pluginKey].getDefaultRoute) === "function") {
											window[pluginKey].showPannelDialog('', window[pluginKey].getDefaultRoute(true))
										}
										jQuery('#sa_background_process_complete').fadeIn();
										if ('undefined' !== window[pluginKey].canShowLoader && window[pluginKey].canShowLoader) {
											window[pluginKey].showLoader();
										}
										let processName = process;
										if (processName) {
											processName = _x(processName.replace(/_/g, ' ').replace(/\b\w/g, function(match) {
												return match.toUpperCase();
											}), 'capitalized process name', 'smart-manager-for-wp-e-commerce');
										}
										let noOfRecords = ('undefined' !== typeof(window[pluginKey].selectedRows) && window[pluginKey].selectedRows && window[pluginKey].selectedRows.length > 0 && window[pluginKey].selectAll === false) ? window[pluginKey].selectedRows.length : (window[pluginKey].selectAll ? _x('All', 'all records', 'smart-manager-for-wp-e-commerce') : 0);
										setTimeout(function() {
											jQuery('#sa_background_process_complete').fadeOut();
											window[pluginKey].notification = {
												status: 'success',
												message: _x(`${processName} ${_x('for', 'success message', 'smart-manager-for-wp-e-commerce')} ${noOfRecords} ${_x(`${noOfRecords == 1 ? 'record' : 'records'}`, 'success notification', 'smart-manager-for-wp-e-commerce')} ${_x(' completed successfully!', 'success message', 'smart-manager-for-wp-e-commerce')}`, 'success notification', 'smart-manager-for-wp-e-commerce')
											}
											window[pluginKey].showNotification()
											if (process == 'bulk_edit') { //code to refresh all the pages for BE
												let p = 1;
												while (p <= window[pluginKey].page && ('undefined' !== typeof(window[pluginKey].getData))) {
													window[pluginKey].getData({
														refreshPage: p
													});
													p++;
												}
												if ('undefined' !== typeof(window[pluginKey].hot) && window[pluginKey].hot) {
													if (window[pluginKey].hot.selection) {
														if (window[pluginKey].hot.selection.highlight) {
															if (window[pluginKey].hot.selection.highlight.selectAll) {
																delete window[pluginKey].hot.selection.highlight.selectAll
															}
															window[pluginKey].hot.selection.highlight.selectedRows = []
														}
													}
													window[pluginKey].hot.render();
													window[pluginKey].addRecords_count = 0;
													window[pluginKey].dirtyRowColIds = {};
													window[pluginKey].editedData = {};
													window[pluginKey].updatedEditedData = {};
													window[pluginKey].processContent = '';
													window[pluginKey].updatedTitle = '';
													window[pluginKey].modifiedRows = new Array();
													window[pluginKey].isRefreshingLoadedPage = false;
													window[pluginKey].showLoader(false);
												} else {
													window[pluginKey].refresh();
												}
												window[pluginKey].selectedRows = [];
												window[pluginKey].selectAll = false;
											} else {
												window[pluginKey].refresh();
											}
										}, 1000);
									}
								}
							}
						}
					}
					setTimeout(function() {
						jQuery.ajax(ajaxParams);
					}, delay);
				}
			</script>
			<?php
		}

		/**
		 * Logs the batch process status and checks for time or memory exceedance and set the $batch_complete to true.
		 *
		 * @param array $batch_params The parameters for the batch process, including 'process_name'.
		 *
		 * @return boolean true if time or memory exceeds else false
		 */
		public function sa_batch_process_log( $batch_params = array() ) {
			if ( empty( $batch_params ) || ( ! is_array( $batch_params ) ) ) {
				return;
			}
			if ( $this->time_exceeded() || $this->memory_exceeded() ) { // Code for continuing the batch.
				if ( is_callable( array( 'SA_Manager_Base', 'log' ) ) && ( ! empty( $batch_params['process_name'] ) ) ) {
					if ( $this->time_exceeded() ) {
						/* translators: %s: process name */
						sa_manager_log( 'notice', sprintf( _x( 'Time is exceeded for %s', 'batch handler time exceed status', 'smart-manager-for-wp-e-commerce' ), $batch_params['process_name'] ) );
					}
					if ( $this->memory_exceeded() ) {
						/* translators: %s: process name */
						sa_manager_log( 'notice', sprintf( _x( 'Memory is exceeded for %s', 'batch handler memory exceed status', 'smart-manager-for-wp-e-commerce' ), $batch_params['process_name'] ) );
					}
				}
				$initial_process = get_option( $this->identifier . '_initial_process', false );
				if ( ! empty( $initial_process ) ) {
					delete_option( $this->identifier . '_initial_process' );
				}
				return true;
			}
			return false;
		}

		/**
		 * Delete stored option ids when Bulk Edit scheduled action is cancelled.
		 *
		 * @param int $action_id Action Scheduler action ID.
		 *
		 * @return void
		 */
		public function delete_ids_option_on_cancelled_bulk_edit_scheduled_action( $action_id = 0 ) {
			if ( empty( $action_id ) ) {
				return;
			}

			$store = ActionScheduler::store();
			if ( ! is_callable( array( $store, 'fetch_action' ) ) ) {
				return;
			}

			$action = $store->fetch_action( $action_id );
			if ( empty( $action ) || ! is_callable( array( $action, 'get_hook' ) ) ) {
				return;
			}

			$plugin_sku = ! empty( $this->plugin_data['plugin_sku'] ) ? $this->plugin_data['plugin_sku'] : '';
			if ( 'storeapps_' . $plugin_sku . '_scheduled_actions' !== $action->get_hook() ) {
				return;
			}

			// Fetch action args.
			$args = is_callable( array( $action, 'get_args' ) ) ? $action->get_args() : array();
			if ( empty( $args ) || ( ! is_array( $args ) ) || ( ! is_array( $args[0] ) ) || empty( $args[0]['selected_ids_option_key'] ) ) {
				return;
			}
			// Delete the option from database.
			delete_option( sanitize_key( $args[0]['selected_ids_option_key'] ) );
		}

		/**
		 * Dynamically determine the batch size based on last batch duration, unless user manually sets it.
		 *
		 * @return int Batch size.
		 */
		public function get_dynamic_batch_size() {
			$fixed_batch_size = absint( get_option( 'sa_manager_batch_size', 0 ) );
			// If fixed batch size is set by user, use it and skip throttling.
			if ( ! empty( $fixed_batch_size ) ) {
				return $fixed_batch_size;
			}
			// Default fallback and limits.
			$initial_batch_size = 5;

			// Retrieve last batch size and duration (stored in transient).
			$last_batch_size = absint( get_option( $this->identifier . '_last_batch_size', 0 ) );
			$last_duration   = floatval( get_option( $this->identifier . '_last_batch_duration', 0 ) );
			$batch_size      = ( $last_batch_size > 0 ) ? $last_batch_size : $initial_batch_size;
			if ( empty( $last_duration ) || ( $last_duration < 0 ) ) {
				return $batch_size;
			}
			// Define safe execution time limit.
			$safe_time = floatval( apply_filters( $this->identifier . '_batch_default_time_limit', 20 ) );

			// Case 1: Batch finished very fast (<10% of time).
			if ( ( $last_duration < ( $safe_time * 0.1 ) ) && ( ! $this->memory_exceeded() ) ) {
				$batch_size = ceil( $batch_size * ( $safe_time / $last_duration ) );

				// Case 2: Under safe threshold — slowly increase.
			} elseif ( ( $last_duration < $safe_time ) && ( ! $this->memory_exceeded() ) ) {
				$batch_size = absint( $batch_size + $initial_batch_size );

				// Case 3: Close to limits — slowly decrease.
			} elseif ( ( $last_duration >= $safe_time ) || ( $this->memory_exceeded() ) ) {
				$batch_size = ceil( $batch_size * ( $safe_time / $last_duration ) );
			}
			$batch_size = ( $initial_batch_size < $batch_size ) ? $batch_size : $initial_batch_size;
			// Save current batch size for reference in next run.
			update_option( $this->identifier . '_last_batch_size', $batch_size );
			return $batch_size;
		}
	}
}
SA_Manager_Pro_Background_Updater::instance();
