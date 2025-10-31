<?php
/**
 * Common base class.
 *
 * @package common-pro/
 * @since       8.64.0
 * @version     8.67.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SA_Manager_Pro_Base' ) ) {
	/**
	 * Class properties and methods will go here.
	 */
	class SA_Manager_Pro_Base extends SA_Manager_Base {
		/**
		 * Current dashboard key
		 *
		 * @var string
		 */
		public $dashboard_key = '';

		/**
		 * Stores the plugin SKU
		 *
		 * @var string
		 */
		public $plugin_sku = '';

		/**
		 * Stores the plugin prefix
		 *
		 * @var string
		 */
		public static $plugin_prefix = '';

		/**
		 * Current post type
		 *
		 * @var string
		 */
		public $post_type = '';

		/**
		 * An array containing required parameters for the operation.
		 *
		 * @var array
		 */
		public $req_params = array();

		/**
		 * Current dashboard title
		 *
		 * @var string
		 */
		public $dashboard_title = '';

		/**
		 * Name of the transient option used for store column model data.
		 *
		 * @var string
		 */
		public $store_col_model_transient_option_nm = '';

		/**
		 *  Plugin data.
		 *
		 * @var string $plugin_data Plugin specific data.
		 */
		public static $plugin_data = '';
		/**
		 *  Main class name of the plugin
		 *
		 * @var string $plugin_main_class_nm Main class name of the plugin
		 */
		public static $plugin_main_class_nm = '';

		/**
		 * Holds a list of actions for the manager.
		 *
		 * @var array $actions Static array to store actions.
		 */
		public static $actions = array();

		/**
		 * Holds the single instance of the class.
		 *
		 * Ensures only one instance of the class exists.
		 *
		 * @var self|null
		 */
		protected static $instance = null;

		/**
		 * Holds the WPML Term Translation Utils instance.
		 *
		 * @var WPML_Term_Translation_Utils|null
		 */
		public static $term_translation_utils = null;

		/**
		 * Returns the single instance of the class, creating it if it doesn't exist.
		 *
		 * This method implements the Singleton pattern. It ensures that only one
		 * instance of the class is created, using the provided dashboard key.
		 *
		 * @param string $plugin_data Plugin data.
		 * @return self|null self::$instance The single instance of the class
		 */
		public static function instance( $plugin_data = null ) {
			if ( is_null( self::$instance ) && ! empty( $plugin_data ) ) {
				self::$instance = new self( $plugin_data );
			}
			return self::$instance;
		}

		/**
		 * Constructor is called when the class is instantiated
		 *
		 * @param array $plugin_data $plugin_data Current plugin data array.
		 * @return void
		 */
		public function __construct( $plugin_data = array() ) {
			$plugin_data         = ( ! empty( $plugin_data ) && is_array( $plugin_data ) ) ? $plugin_data : array();
			self::$plugin_data   = $plugin_data;
			self::$plugin_prefix = ( ( ! empty( $plugin_data ) ) && is_array( $plugin_data ) ) ? $plugin_data['plugin_sku'] : '';
			parent::__construct( $plugin_data );
			add_filter( 'get_selected_ids_and_entire_store_flag', array( $this, 'get_selected_ids_and_entire_store_flag' ), 10, 2 );
			remove_action( 'transition_post_status', '_update_term_count_on_transition_post_status', 10 ); // removed because taking time in bulk edit, when assign terms to post.
		}

		/**
		 * Get batch update copy from record ids.
		 *
		 * @param  array $args args array.
		 * @return mixed $data record ids.
		 */
		public function get_batch_update_copy_from_record_ids( $args = array() ) {
			global $wpdb;
			$data          = array();
			$dashboard_key = ( ! empty( $args['dashboard_key'] ) ) ? $args['dashboard_key'] : $this->dashboard_key;
			$is_ajax       = ( isset( $args['is_ajax'] ) ) ? $args['is_ajax'] : true;
			if ( ! empty( $dashboard_key ) || ! empty( $this->req_params['table_model']['posts']['where']['post_type'] ) ) {
				$dashboards  = ( ! empty( $this->req_params['table_model']['posts']['where']['post_type'] ) && empty( $args['dashboard_key'] ) ) ? $this->req_params['table_model']['posts']['where']['post_type'] : $dashboard_key;
				$dashboards  = ( is_array( $dashboards ) ) ? $dashboards : array( $dashboards );
				$search_term = ( ! empty( $this->req_params['search_term'] ) ) ? $this->req_params['search_term'] : ( ( ! empty( $args['search_term'] ) ) ? $args['search_term'] : '' );
				if ( ( ! empty( $this->req_params['load_variations'] ) ) && ( 'true' === $this->req_params['load_variations'] ) ) {
					array_push( $dashboards, 'product_variation' );
				}
				$results     = array();
				if ( ! empty( $search_term ) && count( $dashboards ) > 0 ) {
					$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT ID AS id, post_title AS title 
							FROM {$wpdb->prefix}posts 
							WHERE post_status != 'trash' 
							AND ( id LIKE %s OR post_title LIKE %s OR post_excerpt LIKE %s )
							AND post_type IN (" . implode( ',', array_fill( 0, count( $dashboards ), '%s' ) ) . ')',
							array_merge(
								array(
									'%' . $wpdb->esc_like( $search_term ) . '%',
									'%' . $wpdb->esc_like( $search_term ) . '%',
									'%' . $wpdb->esc_like( $search_term ) . '%',
								),
								$dashboards
							)
						),
						'ARRAY_A'
					);
					$results = apply_filters(
						$this->plugin_sku . '_batch_update_copy_from_ids_query_result',
						$results,
						array(
							'search_term' => $search_term,
							'args'        => $args,
							'dashboards'  => $dashboards,
						)
					);
				} elseif ( empty( $search_term ) && empty( $args['search_ids'] ) ) {
					$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT ID AS id, post_title AS title 
							FROM {$wpdb->prefix}posts 
							WHERE post_status != 'trash'
							AND post_type IN (" . implode( ',', array_fill( 0, count( $dashboards ), '%s' ) ) . ')',
							$dashboards
						),
						'ARRAY_A'
					);
				}
				if ( is_array( $results ) && ! empty( $results ) && count( $results ) > 0 ) {
					foreach ( $results as $result ) {
						if ( empty( $result ) || ! is_array( $result ) || empty( $result['id'] ) || empty( $result['title'] ) ) {
							continue;
						}
						if ( ( ( ! empty( $this->req_params['load_variations'] ) ) && ( 'true' === $this->req_params['load_variations'] ) ) && ( ( ! empty( $this->req_params['skip_variation_parent'] ) ) && ( 'true' === $this->req_params['skip_variation_parent'] ) ) ) {
							$product_object = wc_get_product( absint( $result['id'] ) );
							if ( ( ! is_object( $product_object ) ) || ( ! is_a( $product_object, 'WC_Product' ) ) || ( is_callable( array( $product_object, 'get_type' ) ) && 'variable' === $product_object->get_type() ) ) {
								continue;
							}
							$formatted_name = is_callable( array( $product_object, 'get_formatted_name' ) ) ? $product_object->get_formatted_name() : '';
							if ( is_callable( array( $product_object, 'managing_stock' ) ) && ( ! empty( $product_object->managing_stock() ) ) ) {
								$stock_amount = is_callable( array( $product_object, 'get_stock_quantity' ) ) ? $product_object->get_stock_quantity() : 0;
								/* translators: %d: the stock quantity for the product */
								$formatted_name .= ' - ' . sprintf( __( 'Stock: %d', 'woocommerce' ), wc_format_stock_quantity_for_display( $stock_amount, $product_object ) );
							}
							if ( ( empty( $formatted_name ) ) ) {
								continue;
							}
							$result['title'] = rawurldecode( wp_strip_all_tags( $formatted_name ) );
						}
						$data[ $result['id'] ] = trim( $result['title'] );
					}
				}
				$data = apply_filters( $this->plugin_sku . '_batch_update_copy_from_ids', $data );
			}
			if ( $is_ajax ) {
				wp_send_json( $data );
			} else {
				return $data;
			}
		}

		/**
		 * Handle batch update request in background
		 *
		 * @param array $params params array.
		 * @return void
		 */
		public static function send_to_background_process( $params = array() ) {
			if ( empty( $params ) || ! is_array( $params ) ) {
				return;
			}
			if ( ( ! empty( $params['is_scheduled'] ) ) && ( ! empty( $params['scheduled_for'] && '0000-00-00 00:00:00' === $params['scheduled_for'] ) ) && ( ! empty( $params['scheduled_action_admin_url'] ) ) ) {
				$params['selected_ids'] = get_option( sanitize_key( $params['selected_ids_option_key'] ), array() );
				delete_option( sanitize_key( $params['selected_ids_option_key'] ) );
			}
			$identifier = '';
			$params     = apply_filters( self::$plugin_prefix . '_update_params_before_processing_batch_update', $params );
			if ( is_callable( array( 'SA_Manager_Pro_Background_Updater', 'get_identifier' ) ) ) {
				$identifier = SA_Manager_Pro_Background_Updater::get_identifier();
			}
			if ( ! empty( $identifier ) && ( ! empty( $params['selected_ids'] ) ) ) {
				$default_params = array(
					'process_name'     => _x( 'Bulk edit / Batch update', 'process name', 'smart-manager-for-wp-e-commerce' ),
					'process_key'      => 'bulk_edit',
					'callback'         => array(
						'class_path' => $params['class_path'],
						'func'       => array( $params['class_nm'], 'process_batch_update' ),
					),
					'id_count'         => count( $params['selected_ids'] ),
					'active_dashboard' => $params['dashboard_title'],
					'plugin_data'      => self::$plugin_data,
				);
				$params         = ( ! empty( $params ) ) ? array_merge( $default_params, $params ) : $default_params;
				update_option( $identifier . '_params', $params, 'no' );
				update_option( $identifier . '_ids', $params['selected_ids'], 'no' );
				update_option( $identifier . '_initial_process', 1, 'no' );
			}
			if ( ( isset( $params['is_scheduled'] ) && ! empty( $params['is_scheduled'] ) ) && ( isset( $params['scheduled_for'] ) && ! empty( $params['scheduled_for'] && '0000-00-00 00:00:00' !== $params['scheduled_for'] ) ) && ( isset( $params['scheduled_action_admin_url'] ) && ! empty( $params['scheduled_action_admin_url'] ) ) ) {
				$timestamp              = strtotime( gmdate( $params['scheduled_for'] ) );
				$params['selected_ids'] = array(); // remove the selected ids from the prams in case of scheduling the bulk edits.
				as_schedule_single_action( $timestamp, 'storeapps_' . self::$plugin_prefix . '_scheduled_actions', array( $params ) );
				echo sprintf(
					wp_kses_post(
						/* translators: %s: URL for scheduled action admin page */
						_x( "Bulk Edit action scheduled successfully. Check all your scheduled actions <a target='_blank' href='%s'>here</a>.", 'smart-manager-for-wp-e-commerce' )
					),
					esc_url( $params['scheduled_action_admin_url'] )
				);
				exit;
			}
			if ( ! empty( $identifier ) && ( ! empty( $params['selected_ids'] ) ) ) {
				// Calling the initiate_batch_process function to initiaite the batch process.
				if ( is_callable( array( SA_Manager_Pro_Background_Updater::instance(), 'initiate_batch_process' ) ) ) {
					SA_Manager_Pro_Background_Updater::instance()->initiate_batch_process( $params['selected_ids'] );
				}
			}
		}

		/**
		 * Handle batch update request
		 *
		 * @return void
		 */
		public function batch_update() {
			global $wpdb, $current_user;
			$col_data_type                          = self::get_column_data_type( $this->dashboard_key ); // For fetching column data type.
			$batch_update_actions                   = ( ! empty( $this->req_params['batchUpdateActions'] ) ) ? json_decode( stripslashes( $this->req_params['batchUpdateActions'] ), true ) : array();
			$dashboard_key                          = $this->dashboard_key; // fix for PHP 5.3 or earlier.
			$batch_update_actions                   = array_map(
				function( $batch_update_action ) use ( $dashboard_key, $col_data_type ) {
					$batch_update_action['dashboard_key'] = $dashboard_key;
					$batch_update_action['date_type']     = ( ! empty( $col_data_type[ $batch_update_action['type'] ] ) ) ? $col_data_type[ $batch_update_action['type'] ] : 'text';
					// data type for handling copy_from_field operator.
					if ( 'copy_from_field' === $batch_update_action['operator'] ) {
						$batch_update_action['copy_field_data_type'] = ( ! empty( $col_data_type[ $batch_update_action['value'] ] ) ) ? $col_data_type[ $batch_update_action['value'] ] : 'text';
					}
					return $batch_update_action;
				},
				$batch_update_actions
			);
			$get_selected_ids_and_entire_store_flag = apply_filters(
				'get_selected_ids_and_entire_store_flag',
				array(),
				''
			);
			$selected_ids                           = ( ! empty( $get_selected_ids_and_entire_store_flag['selected_ids'] ) ) ? $get_selected_ids_and_entire_store_flag['selected_ids'] : array();
			$is_entire_store                        = ( ! empty( $get_selected_ids_and_entire_store_flag['entire_store'] ) ) ? $get_selected_ids_and_entire_store_flag['entire_store'] : false;

			$selected_ids_option_key = ''; // This is to store the selected ids in options instead of action scheduler params in case of scheduled bulk edits.
			$identifier              = '';
			if ( is_callable( array( 'SA_Manager_Pro_Background_Updater', 'get_identifier' ) ) ) {
				$identifier = SA_Manager_Pro_Background_Updater::get_identifier();
			}
			if ( ( ! empty( $this->req_params['isScheduled'] ) ) && ( ! empty( $this->req_params['scheduledFor'] && '0000-00-00 00:00:00' !== $this->req_params['scheduledFor'] ) ) ) {
				$selected_ids_option_key = $identifier . '_ids_scheduled_' . strtotime( 'now' );
				update_option( sanitize_key( $selected_ids_option_key ), $selected_ids );
			}
			$params = array(
				'process_name'                    => _x( 'Bulk Edit', 'process name', 'smart-manager-for-wp-e-commerce' ),
				'process_key'                     => 'bulk_edit',
				'callback'                        => array(
					'class_path' => $this->req_params['class_path'],
					'func'       => array( $this->req_params['class_nm'], 'process_batch_update' ),
				),
				'actions'                         => $batch_update_actions,
				'is_scheduled'                    => ( ! empty( $this->req_params['isScheduled'] ) ) ? $this->req_params['isScheduled'] : false,
				'scheduled_for'                   => $this->req_params['scheduledFor'],
				'selected_ids'                    => $selected_ids,
				'storewide_option'                => $this->req_params['storewide_option'],
				'active_module'                   => $this->req_params['active_module'],
				'entire_store'                    => $is_entire_store,
				'dashboard_key'                   => $this->dashboard_key,
				'dashboard_title'                 => $this->dashboard_title,
				'class_path'                      => $this->req_params['class_path'],
				'class_nm'                        => $this->req_params['class_nm'],
				'scheduled_action_admin_url'      => $this->req_params['scheduledActionAdminUrl'],
				'backgroundProcessRunningMessage' => ( ! empty( $this->req_params['backgroundProcessRunningMessage'] ) ) ? $this->req_params['backgroundProcessRunningMessage'] : '',
				'title'                           => ( ! empty( $this->req_params['title'] ) ) ? $this->req_params['title'] : '',
				'selected_ids_option_key'         => ( ! empty( $selected_ids_option_key ) ) ? $selected_ids_option_key : '',
			);
			$params = apply_filters(
				'sa_manager_batch_update_params',
				$params,
				array(
					'req_params'                         => $this->req_params,
					'selected_ids_and_entire_store_flag' => $get_selected_ids_and_entire_store_flag,
					'identifier'                         => $identifier,
				)
			);
			self::send_to_background_process(
				$params
			);
		}

		/**
		 * Processes batch update conditions and prepares database updates.
		 *
		 * @param array $batch_args Arguments for the batch update, including:
		 *     - 'selected_ids': (array) IDs to be updated in the batch.
		 *     - 'batch_params': (array) Parameters for batch processing
		 *     - 'task_details_data': (array) Optional, data for undoing tasks.
		 * @return void
		 */
		public static function process_batch_update( $batch_args = array() ) {
			if ( empty( $batch_args ) || ( ! is_array( $batch_args ) ) || empty( $batch_args['selected_ids'] ) || empty( $batch_args['batch_params'] ) || empty( $batch_args['batch_params']['process_name'] ) ) {
				return;
			}
			do_action( self::$plugin_prefix . '_pre_process_batch_update_args' );
			$db_updates_args = array(); // For storing all of the selected/entire ids args with its actions.
			if ( ( 'Undo Tasks' === $batch_args['batch_params']['process_name'] ) || ( ! empty( $batch_args['task_details_data'] ) ) ) {
				foreach ( $batch_args['task_details_data'] as $args ) {
					$args = self::process_batch_update_args( $args );
					if ( empty( $args ) ) {
						continue;
					}
					$db_updates_args[] = $args;
				}
			} else {
				foreach ( $batch_args['selected_ids'] as $selected_id ) {
					$prev_vals = array();
					foreach ( $batch_args['batch_params']['actions'] as $key => $args ) {
						$args['id'] = $selected_id;
						$args       = self::process_batch_update_args( $args, $prev_vals );
						if ( empty( $args ) ) {
							continue;
						}
						$special_batch_update_operators = ( ( ! empty( $args['operator'] ) ) && ( 'copy_from_field' === $args['operator'] ) && ( ! empty( $args['selected_column_name'] ) ) ) ? array( $args['selected_column_name'] => 'copy_from_field' ) : array();
						$special_batch_update_operators = apply_filters( self::$plugin_prefix . '_special_batch_update_operators', $special_batch_update_operators, $args );
						if ( ( ! empty( $prev_vals ) ) && is_array( $prev_vals ) && ( ! empty( $special_batch_update_operators ) ) && is_array( $special_batch_update_operators ) ) { // To handle operators like 'set_to_regular_price, set_to_sale_price'.
							$operator_key  = array_search( $args['operator'], $special_batch_update_operators, true );
							$args['value'] = ( ! empty( $operator_key ) && in_array( $operator_key, array_keys( $prev_vals ), true ) ) ? $prev_vals[ $operator_key ] : $args['value'];
						}
						$db_updates_args[] = $args;
						if ( ( count( $batch_args['batch_params']['actions'] ) - 1 ) === $key ) {
							continue;
						}
						$prev_vals[ $args['col_nm'] ] = $args['value'];
					}
				}
			}
			// update the data in database.
			do_action( self::$plugin_prefix . '_pre_process_batch_db_updates' );
			if ( empty( $db_updates_args ) ) {
				return;
			}
			self::process_batch_update_db_updates( $db_updates_args );
		}

		/**
		 * Retrieves the previous data for a batch update operation.
		 *
		 * @param mixed $prev_val The previous value to be used as a reference. Default is 0.
		 * @param array $args     Optional. An array of arguments to customize the data retrieval process.
		 *                        Default is an empty array.
		 *
		 * @return mixed The previous data retrieved based on the provided arguments.
		 */
		/**
		 * Retrieves the previous data for a batch update operation.
		 *
		 * @param mixed $prev_val The previous value to be used as a reference. Default is 0.
		 * @param array $args     Optional. An array of arguments to customize the data retrieval process.
		 *                        Default is an empty array.
		 *
		 * @return mixed The previous data retrieved based on the provided arguments.
		 */
		public static function get_previous_data_for_batch_update( $prev_val = 0, $args = array() ) {
			if ( empty( $args['id'] ) || empty( $args['table_nm'] ) || empty( $args['col_nm'] ) ) {
				return;
			}
			switch ( $args['table_nm'] ) {
				case 'posts':
					return get_post_field( $args['col_nm'], $args['id'] );
				case 'postmeta':
					return get_post_meta( $args['id'], $args['col_nm'], true );
				case 'terms':
					return wp_get_object_terms( $args['id'], $args['col_nm'], 'orderby=none&fields=ids' );
			}
			return $prev_val;
		}
		/**
		 * Processes and validates arguments for batch updates.
		 *
		 * @param array $args Arguments for the batch update, including:
		 *     - 'type': (string) The data type and table/column identifiers.
		 *     - 'operator': (string) Operation to perform (e.g., set, append, increase).
		 *     - 'id': (int) ID of the record to update.
		 *     - 'date_type': (string) Specifies if data is a date, time, or numeric.
		 *     - 'value': (mixed) New value or modifier for the batch update.
		 *     - 'meta': (array) Additional meta options for the update.
		 *
		 * @param array $prev_vals Array of previous values in case of multiple actions for same column.
		 * @return array|false Processed and validated batch update arguments, or false if invalid.
		 */
		public static function process_batch_update_args( $args = array(), $prev_vals = array() ) {
			if ( empty( $args ) ) {
				return false;
			}
			do_action( self::$plugin_prefix . '_pre_process_batch', $args );
			// code for processing logic for batch update.
			if ( empty( $args['type'] ) || empty( $args['operator'] ) || empty( $args['id'] ) || empty( $args['date_type'] ) ) {
				return false;
			}
			$type_exploded = explode( '/', $args['type'] );
			if ( count( $type_exploded ) < 2 ) {
				return false;
			}
			if ( count( $type_exploded ) > 2 ) {
				$args['table_nm'] = $type_exploded[0];
				$cond             = explode( '=', $type_exploded[1] );
				if ( 2 === count( $cond ) ) {
					$args['col_nm'] = $cond[1];
				}
			} else {
				$args['col_nm']   = $type_exploded[1];
				$args['table_nm'] = $type_exploded[0];
			}
			$prev_val = '';
			$new_val  = $prev_val;
			if ( ( ! empty( $prev_vals ) ) && is_array( $prev_vals ) && ( ! empty( $prev_vals[ $args['col_nm'] ] ) ) ) {
				$prev_val = $prev_vals[ $args['col_nm'] ];
			} else {
				$prev_val = apply_filters( self::$plugin_prefix . '_batch_update_prev_value', $prev_val, $args );
				if ( empty( $prev_val ) ) {
					$prev_val = self::get_previous_data_for_batch_update( $prev_val, $args );
				}
				if ( 'numeric' === $args['date_type'] ) {
					$prev_val = ( ! empty( $prev_val ) ) ? floatval( $prev_val ) : 0;
				}
			}
			$args['prev_val'] = $prev_val;
			$value1           = $args['value'];
			$args_meta        = ( ! empty( $args['meta'] ) ) ? $args['meta'] : array();
			if ( 'numeric' === $args['date_type'] ) {
				$value1 = ( ! empty( $value1 ) ) ? floatval( $value1 ) : 0;
			}
			// Code for handling different conditions for updating datetime fields.
			if ( self::$plugin_prefix . '.datetime' === $args['date_type'] && ( 'set_date_to' === $args['operator'] || 'set_time_to' === $args['operator'] ) ) {
				// if prev_val is null.
				if ( empty( $prev_val ) ) {
					$date = ( 'set_date_to' === $args['operator'] ) ? $value1 : current_time( 'Y-m-d' );
					$time = ( 'set_time_to' === $args['operator'] ) ? $value1 : current_time( 'H:i:s' );
				} else {
					$date = ( 'set_date_to' === $args['operator'] ) ? $value1 : gmdate( 'Y-m-d', strtotime( $prev_val ) );
					$time = ( 'set_time_to' === $args['operator'] ) ? $value1 : gmdate( 'H:i:s', strtotime( $prev_val ) );
				}
				$value1 = $date . ' ' . $time;
			}
			if ( ( ( self::$plugin_prefix . '.datetime' === $args['date_type'] ) || ( self::$plugin_prefix . '.date' === $args['date_type'] ) || ( self::$plugin_prefix . '.time' === $args['date_type'] ) ) && ( ! empty( $args['date_type'] ) && 'timestamp' === $args['date_type'] ) ) { // code for handling timestamp values.
				if ( $args['date_type'] === self::$plugin_prefix . '.time' ) {
					$value1 = '1970-01-01 ' . $value1;
				}
				$value1 = strtotime( $value1 );
			}
			// Code for handling increase/decrease date by operator.
			$date_type_fields = array( self::$plugin_prefix . '.date', self::$plugin_prefix . '.datetime', self::$plugin_prefix . '.time', 'timestamp' );
			$date_format      = 'Y-m-d';
			if ( in_array( $args['date_type'], $date_type_fields, true ) ) {
				switch ( $args['date_type'] ) {
					case 'timestamp':
					case self::$plugin_prefix . '.date':
						$date_format = 'Y-m-d';
						break;
					case self::$plugin_prefix . '.datetime':
						$date_format = 'Y-m-d H:i:s';
						break;
					case self::$plugin_prefix . '.time':
						$date_format = 'h:i';
						break;
				}
				$args['prev_val'] = ( ! empty( $prev_val ) ? ( strtotime( $prev_val ) ? $prev_val : gmdate( $date_format, $prev_val ) ) : current_time( $date_format ) );
				$value1           = ( ! empty( $value1 ) ? ( strtotime( $value1 ) ? $value1 : gmdate( $date_format, $value1 ) ) : $value1 );
			}
			$additional_date_operators = array( 'increase_date_by', 'decrease_date_by' );
			if ( in_array( $args['date_type'], $date_type_fields, true ) && in_array( $args['operator'], $additional_date_operators, true ) ) {
				$args['meta']['dateDuration'] = ! empty( $args['meta']['dateDuration'] ) ? $args['meta']['dateDuration'] : ( ( self::$plugin_prefix . '.time' === $args['date_type'] ) ? 'hours' : 'days' );
				$args['value']                = ! empty( $args['value'] ) ? $args['value'] : 0;
				$prev_val                     = ( ! empty( $args['prev_val'] ) ) ? $args['prev_val'] : current_time( $date_format );
				$value1                       = gmdate( $date_format, strtotime( $prev_val . ( ( 'increase_date_by' === $args['operator'] ) ? '+' : '-' ) . $args['value'] . $args['meta']['dateDuration'] ) );
			}
			if ( ( 'dropdown' === $args['date_type'] ) || ( 'multilist' === $args['date_type'] ) ) {
				if ( ( 'add_to' === $args['operator'] ) || ( 'remove_from' === $args['operator'] ) ) {
					if ( 'terms' === $args['table_nm'] ) {
						$prev_val = wp_get_object_terms( $args['id'], $args['col_nm'], 'orderby=none&fields=ids' );
					} else {
						if ( ! empty( $args['multiSelectSeparator'] ) && ! empty( $prev_val ) ) {
							$prev_val = explode( $args['multiSelectSeparator'], $prev_val );
						} else {
							$prev_val = ( ! empty( $prev_val ) ) ? $prev_val : array();
						}
					}
					$value1 = ( ! is_array( $value1 ) ) ? array( $value1 ) : $value1;
					if ( ! empty( $prev_val ) ) {
						$value1 = ( 'add_to' === $args['operator'] ) ? array_merge( $prev_val, $value1 ) : array_diff( $prev_val, $value1 );
					}
					$value1 = array_unique( $value1 );
				}

				$separator = ( ! empty( $args['multiSelectSeparator'] ) ) ? $args['multiSelectSeparator'] : ',';
				$value1    = ( is_array( $value1 ) ) ? implode( $separator, $value1 ) : $value1;
			}
			// Code for handling serialized data updates.
			if ( self::$plugin_prefix . '.serialized' === $args['date_type'] ) {
				$value1 = maybe_unserialize( $value1 );
			}
			// default value for prev_val.
			$numeric_operators = array( 'increase_by_per', 'decrease_by_per', 'increase_by_num', 'decrease_by_num' );
			if ( in_array( $args['operator'], $numeric_operators, true ) && empty( $prev_val ) ) {
				$prev_val = 0;
			}
			// cases to update the value based on the batch update actions.
			switch ( $args['operator'] ) {
				case 'set_to':
					$new_val = $value1;
					break;
				case 'prepend':
					$new_val = $value1 . '' . $prev_val;
					break;
				case 'append':
					$new_val = $prev_val . '' . $value1;
					break;
				case 'search_and_replace':
					if ( isset( $args_meta['replace_value'] ) ) {
						$replace_val = ( ! empty( $args_meta['replace_value'] ) ) ? $args_meta['replace_value'] : '';
						$new_val     = str_replace( $value1, $replace_val, $prev_val );
					} else {
						$new_val = $prev_val;
					}
					break;
				case 'increase_by_per':
					$new_val = ( ! empty( $prev_val ) ) ? round( ( $prev_val + ( $prev_val * ( $value1 / 100 ) ) ), apply_filters( 'sa_manager_num_decimals', get_option( 'woocommerce_price_num_decimals' ) ) ) : '';
					break;
				case 'decrease_by_per':
					$new_val = self::decrease_value_by_per( $prev_val, $value1 );
					break;
				case 'increase_by_num':
					$new_val = round(
						( $prev_val + $value1 ),
						apply_filters(
							'sa_manager_num_decimals',
							get_option( 'woocommerce_price_num_decimals' )
						)
					);
					break;
				case 'decrease_by_num':
					$new_val = self::decrease_value_by_num( $prev_val, $value1 );
					break;
				default:
					$new_val = $value1;
					break;
			}
			// Code for handling 'copy_from' and 'copy_from_field' action.
			$args['copy_from_operators'] = array( 'copy_from', 'copy_from_field' );
			$value1                      = ( 'copy_from_field' === $args['operator'] && empty( $args['value'] ) ) ? $args['type'] : $args['value'];
			if ( in_array( $args['operator'], $args['copy_from_operators'], true ) && ( ! empty( $value1 ) ) ) {
				$args['selected_table_name']  = $args['table_nm'];
				$args['selected_column_name'] = $args['col_nm'];
				$args['selected_value']       = $value1;
				if ( 'copy_from_field' === $args['operator'] ) {
					$explode_selected_value = ( false !== strpos( $args['selected_value'], '/' ) ) ? explode( '/', $args['selected_value'] ) : $args['selected_value'];
					if ( is_array( $explode_selected_value ) && count( $explode_selected_value ) >= 2 ) {
						$args['selected_table_name']  = $explode_selected_value[0];
						$args['selected_column_name'] = $explode_selected_value[1];
						$cond                         = ( false !== strpos( $args['selected_column_name'], '=' ) ) ? explode( '=', $args['selected_column_name'] ) : $args['selected_column_name'];
						$args['selected_column_name'] = ( ( is_array( $cond ) ) && ( 2 === count( $cond ) ) ) ? $cond[1] : $cond;
					}
					$args['selected_value'] = $args['id'];
				}
				switch ( $args['selected_table_name'] ) {
					case 'posts':
						$new_val = get_post_field( $args['selected_column_name'], $args['selected_value'] );
						break;
					case 'postmeta':
						$new_val = get_post_meta( $args['selected_value'], $args['selected_column_name'], true );
						break;
					case 'terms':
						$term_ids = wp_get_object_terms(
							$args['selected_value'],
							$args['selected_column_name'],
							array(
								'orderby' => 'term_id',
								'order'   => 'ASC',
								'fields'  => 'ids',
							)
						);
						$new_val  = ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) ? $term_ids : array();
						break;
					case 'custom':
						$new_val = apply_filters( self::$plugin_prefix . '_get_value_for_copy_from_operator', $new_val, $args );
						break;
					default:
						$new_val = $value1;
						break;
				}
				$new_val           = ( 'numeric' === $args['date_type'] && empty( $new_val ) ) ? 0 : $new_val;
				$args['new_value'] = $new_val;
				$new_val           = ( ( 'copy_from_field' === $args['operator'] && ( ! empty( $args['copy_field_data_type'] ) ) ) ) ? self::handle_serialized_data( $args ) : $new_val;
			}
			$args['value'] = $new_val;
			$args          = apply_filters( self::$plugin_prefix . '_post_batch_process_args', $args );
			return $args;
		}

		/**
		 * Handle and process serialized data.
		 *
		 * @param array $args Serialized or raw data to be handled. Default empty array.
		 *
		 * @return mixed Processed data, usually an array or original value after deserialization.
		 */
		public static function handle_serialized_data( $args = array() ) {
			if ( empty( $args['date_type'] ) || empty( $args['new_value'] ) ) {
				return '';
			}

			switch ( true ) {
				case ( self::$plugin_prefix . '.serialized' === $args['date_type'] ):
					return maybe_unserialize( $args['new_value'] );
				case ( self::$plugin_prefix . '.serialized' !== $args['date_type'] && self::$plugin_prefix . '.serialized' === $args['copy_field_data_type'] ):
					return maybe_serialize( $args['new_value'] );
				default:
					return $args['new_value'];
			}
		}
		/**
		 * Handle the batch update db updates
		 *
		 * @param array $arguments args array.
		 * @return boolean true if successfully updated else false
		 */
		public static function process_batch_update_db_updates( $arguments = array() ) {
			if ( empty( $arguments ) || ( ! is_array( $arguments ) ) ) {
				return;
			}
			$update                    = false;
			$default_batch_update      = true;
			$post_data                 = array();
			$update_result             = array();
			$postmeta_failed_to_update = array();
			$taxonomies                = array();
			// code to map data for updation.
			foreach ( $arguments as $key => $args ) {
				if ( empty( $args['id'] ) ) {
					continue;
				}
				if ( empty( $post_data[ $args['id'] ]['meta_input'] ) ) {
					$post_data[ $args['id'] ]['meta_input'] = array();
				}
				if ( empty( $post_data[ $args['id'] ]['tax_input'] ) ) {
					$post_data[ $args['id'] ]['tax_input'] = array();
				}
				do_action( self::$plugin_prefix . '_pre_batch_update_db_updates', $args );
				$default_batch_update = apply_filters( self::$plugin_prefix . '_default_batch_update_db_updates', $default_batch_update, $args );
				if ( empty( $default_batch_update ) ) {
					continue;
				}
				switch ( $args['table_nm'] ) {
					case 'posts':
						$post_data[ $args['id'] ][ $args['col_nm'] ] = $args['value'];
						if ( 'post_date' === $args['col_nm'] ) {
							$post_data[ $args['id'] ]['edit_date']     = true;
							$post_data[ $args['id'] ]['post_date_gmt'] = get_gmt_from_date( $args['value'] );
						}
						break;
					case 'postmeta':
						$post_data[ $args['id'] ]['meta_input'][ $args['col_nm'] ] = $args['value'];
						break;
					case 'terms':
						$post_data[ $args['id'] ]['tax_input'][ $args['col_nm'] ][] = self::batch_update_terms_table_data( $args, true );
						if ( ! in_array( $args['col_nm'], $taxonomies, true ) ) {
							$taxonomies[] = $args['col_nm'];
						}
						break;
					case 'custom':
						if ( 'copy_from' === $args['operator'] ) {
							$arguments[ $key ]['update'] = apply_filters( self::$plugin_prefix . '_update_value_for_copy_from_operator', $args );
						}
						break;
					default:
						$post_data[ $args['id'] ][ $args['col_nm'] ] = $args['value'];
						break;
				}
			}
			set_transient( self::$plugin_prefix . '_skip_delete_dashboard_transients', 1, DAY_IN_SECONDS ); // for preventing delete dashboard transients.
			if ( ! empty( $post_data ) ) {
				$update_result = self::update_posts(
					array(
						'posts_data' => $post_data,
						'taxonomies' => $taxonomies,
					)
				);
				// for handling failed post metas status.
				if ( ( ! empty( $update_result ) ) && ( ! empty( $update_result['postmeta_update_result'] ) ) ) {
					if ( ( 'success' !== $update_result['postmeta_update_result'] ) && is_array( $update_result['postmeta_update_result'] ) && ( ! empty( $update_result['postmeta_update_result'] ) ) ) {
						$postmeta_failed_to_update = $update_result['postmeta_update_result'];
					}
				}
			}
			foreach ( $arguments as $args ) {
				if ( empty( $args['id'] ) ) {
					continue;
				}
				$custom_update_status = array_key_exists( 'update', $args ) ? $args['update'] : true;
				switch ( $args['table_nm'] ) {
					case 'posts':
						$update = ( ( empty( $update_result ) ) || ( ! is_array( $update_result ) ) || ( empty( $update_result['posts_update_result'] ) ) ) ? false : true;
						break;
					case 'postmeta':
						if ( empty( $args['col_nm'] ) ) {
							break;
						}
						$update = true;
						if ( ( empty( $update_result ) ) || ( ! is_array( $update_result ) ) ) { // if all meta data is not updated.
							$update = false;
						} elseif ( ! empty( $postmeta_failed_to_update ) ) { // else check if any meta data failed to update.
							$update = ( in_array( $args['id'] . '/' . $args['col_nm'], $postmeta_failed_to_update, true ) ) ? false : true;
						}
						break;
					case 'terms':
						$update = ( empty( $update_result ) || ( is_array( $update_result ) && ( ( empty( $update_result['taxonomies_update_result'] ) ) || ( empty( $update_result['taxonomies_update_result']['status'] ) ) ) ) ) ? false : true;
						break;
					case 'custom':
						$update = $custom_update_status;
						break;
					default:
						$update = ( ( empty( $update_result ) ) || ( ! is_array( $update_result ) ) || ( empty( $update_result['posts_update_result'] ) ) ) ? false : true;
						break;
				}
				$update = apply_filters( self::$plugin_prefix . '_post_batch_update_db_updates', $update, $args );
				if ( empty( $update ) ) {
					if ( is_callable( array( self::$plugin_main_class_nm, 'log' ) ) ) {
						/* translators: %s process name */
						log( 'error', sprintf( _x( '%s failed', 'update status', 'smart-manager-for-wp-e-commerce' ), ( ! empty( $args['process_name'] ) ? $args['process_name'] : '' ) ) );
					}
					continue;
				}
				do_action( self::$plugin_prefix . '_update_params_post_processing_batch_update', array_merge( $args, array( 'update_result' => $update_result ) ) );
			}
			// execute actions after updating a post.
			if ( ( ! empty( $update_result ) ) && ( is_array( $update_result ) ) && ( ! empty( $update_result['after_update_actions_params'] ) ) ) {
				self::update_posts_after_update_actions(
					$update_result['after_update_actions_params']
				);
			}
			$update = apply_filters( self::$plugin_prefix . '_handle_post_processing_batch_update', $update );
			return $update;
		}

		/**
		 * Function to batch update terms table related data
		 *
		 * @param  array   $args request params array.
		 *
		 * @param  boolean $is_post_terms wheather it's a post term.
		 *
		 * @return boolean $update result of the function call.
		 */
		public static function batch_update_terms_table_data( $args = array(), $is_post_terms = false ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['operator'] ) || empty( $args['id'] ) || empty( $args['col_nm'] ) ) {
				return false;
			}
			$value = ( is_array( $args['value'] ) && ! empty( $args['value'][0] ) ) ? intval( $args['value'][0] ) : intval( $args['value'] );
			if ( ( ! empty( $args['copy_from_operators'] ) && is_array( $args['copy_from_operators'] ) ) && in_array( $args['operator'], $args['copy_from_operators'], true ) ) {
				$value = $args['value'];
			}
			if ( $is_post_terms ) {
				return array(
					'value'    => $value,
					'operator' => $args['operator'],
				);
			}
			if ( 'remove_from' === $args['operator'] ) {
				return wp_remove_object_terms( $args['id'], $value, $args['col_nm'] );
			} else {
				$append = ( 'add_to' === $args['operator'] ) ? true : false;
				return wp_set_object_terms( $args['id'], $value, $args['col_nm'], $append );
			}
		}

		/**
		 * Handle batch update process completion
		 *
		 * @return void
		 */
		public static function batch_process_complete() {
			$identifier = '';
			if ( is_callable( array( 'SA_Manager_Pro_Background_Updater', 'get_identifier' ) ) ) {
				$identifier = SA_Manager_Pro_Background_Updater::get_identifier();
			}
			if ( empty( $identifier ) ) {
				return;
			}
			$background_process_params = get_option( $identifier . '_params', false );
			if ( empty( $background_process_params ) ) {
				if ( is_callable( 'sa_manager_log' ) ) {
					sa_manager_log( 'error', _x( 'No batch process params found', 'batch process', 'smart-manager-for-wp-e-commerce' ) );
				}
				return;
			}
			delete_option( $identifier . '_params' );
			// Preparing email content.
			$email                         = get_option( 'admin_email' );
			$site_title                    = get_option( 'blogname' );
			$email_heading_color           = get_option( 'woocommerce_email_base_color' );
			$email_heading_color           = ( empty( $email_heading_color ) ) ? '#96588a' : $email_heading_color;
			$email_text_color              = get_option( 'woocommerce_email_text_color' );
			$email_text_color              = ( empty( $email_text_color ) ) ? '#3c3c3c' : $email_text_color;
			self::$actions                 = ( ! empty( $background_process_params['actions'] ) ) ? $background_process_params['actions'] : array();
			$records_str                   = $background_process_params['id_count'] . ' ' . ( ( $background_process_params['id_count'] > 1 ) ? _x( 'records', 'background process notification', 'smart-manager-for-wp-e-commerce' ) : _x( 'record', 'background process notification', 'smart-manager-for-wp-e-commerce' ) );
			$records_str                  .= ( $background_process_params['entire_store'] ) ? ' (' . _x( 'entire store', 'background process notification', 'smart-manager-for-wp-e-commerce' ) . ')' : '';
			$background_process_param_name = $background_process_params['process_name'];
			/* translators: %1$1s: site title %2$2s: background process parameter name */
			$title = sprintf( _x( '[%1$1s] %2$2s process completed!', 'background process title', 'smart-manager-for-wp-e-commerce' ), $site_title, $background_process_param_name );
			ob_start();
			$background_process_actions = self::$actions;
			include apply_filters( self::$plugin_prefix . '_batch_email_template', constant( strtoupper( self::$plugin_prefix ) . '_EMAIL_TEMPLATE_PATH' ) . '/bulk-edit.php' );
			$message = ob_get_clean();
			$subject = $title;
			self::send_email(
				array(
					'subject' => $subject,
					'message' => $message,
					'email'   => $email,
				)
			);
		}

		/**
		 * Get selected item IDs and entire store flag from request args.
		 *
		 * @param array  $args         Request arguments.
		 * @param string $selected_ids Comma-separated selected IDs. Default empty.
		 *
		 * @return array Selected IDs and store-wide flag.
		 */
		public function get_selected_ids_and_entire_store_flag( $args = array(), $selected_ids = '' ) {
			$selected_ids         = ( ! empty( $selected_ids ) ) ? trim( $selected_ids, '[]' ) : ( ( ! empty( $this->req_params['selected_ids'] ) ) ? trim( $this->req_params['selected_ids'], '[]' ) : array() );
			$selected_ids         = json_decode( "[$selected_ids]" );
			$entire_store         = false;
			$can_fetch_entire_ids = apply_filters( 'sa_can_fetch_entire_ids', true, $args );
			if ( ( ! empty( $can_fetch_entire_ids ) ) && ( ! empty( $this->req_params['storewide_option'] ) ) && ( 'entire_store' === $this->req_params['storewide_option'] ) && ( ! empty( $this->req_params['active_module'] ) ) ) {
				$selected_ids = apply_filters( 'sa_' . $this->plugin_sku . '_get_entire_store_ids', $selected_ids, $args );
				$entire_store = true;
			}
			// Use a filter to allow modification of selected IDs and subscription separation.
			return apply_filters(
				'sa_manager_batch_update_selection_data',
				array(
					'selected_ids' => $selected_ids,
					'entire_store' => $entire_store,
				),
				$this->req_params
			);
		}

		/**
		 * Decreases the given value by a specified percentage.
		 *
		 * @param float|int $prev_val The initial value before the decrease.
		 * @param float|int $per The percentage by which to decrease the initial value.
		 * @return int The value after the decrease.
		 */
		public static function decrease_value_by_per( $prev_val = 0, $per = 0 ) {
			if ( ( empty( $prev_val ) ) || ( empty( $per ) ) ) {
				return $prev_val;
			}
			return round( ( $prev_val - ( $prev_val * ( $per / 100 ) ) ), apply_filters( 'sa_manager_num_decimals', get_option( 'woocommerce_price_num_decimals' ) ) );
		}

		/**
		 * Decreases the given value by a specified number.
		 *
		 * @param float|int $prev_val The initial value before decrease. Default is 0.
		 * @param float|int $num The number to decrease the initial value by. Default is 0.
		 * @return int The resulting value after decrease.
		 */
		public static function decrease_value_by_num( $prev_val = 0, $num = 0 ) {
			if ( empty( $prev_val ) || empty( $num ) ) {
				return $prev_val;
			}
			return round( ( $prev_val - $num ), apply_filters( 'sa_manager_num_decimals', get_option( 'woocommerce_price_num_decimals' ) ) );
		}

		/**
		 * Function to fetch column data type
		 *
		 * @param  string $dashboard_key current dashboard name.
		 * @return string $col_data_type column data type
		 */
		public static function get_column_data_type( $dashboard_key = '' ) {
			if ( empty( $dashboard_key ) ) {
				return;
			}
			$current_store_model = get_transient( 'sa_' . self::$plugin_prefix . '_' . $dashboard_key );
			if ( empty( $current_store_model ) && is_array( $current_store_model ) ) {
				return;
			}
			$current_store_model = json_decode( $current_store_model, true );
			$col_model           = ( ! empty( $current_store_model['columns'] ) ) ? $current_store_model['columns'] : array();
			if ( empty( $col_model ) ) {
				return;
			}
			$col_data_type  = array();
			$date_type_cols = array( self::$plugin_prefix . '.date', self::$plugin_prefix . '.datetime', self::$plugin_prefix . '.time', 'timestamp' );
			// Code for storing the timestamp cols.
			foreach ( $col_model as $col ) {
				if ( empty( $col['type'] ) ) {
					continue;
				}
				$col_data_type[ $col['src'] ] = ( ( in_array( $col['type'], $date_type_cols, true ) ) && ( ! empty( $col['date_type'] ) && ( 'timestamp' === $col['date_type'] ) ) ) ? 'timestamp' : $col['type'];
			}
			return $col_data_type;
		}


		/**
		 * Generates an advanced search query for scheduled exports.
		 *
		 * When order statuses are provided in the parameters, the query will include a separate
		 * condition for each order status along with the date range.
		 *
		 * @param array $args Array of arguments.
		 *
		 * @return string JSON-encoded advanced search query.
		 */
		public static function get_scheduled_exports_advanced_search_query( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['interval_days'] ) ) || ( empty( $args['table_nm'] ) ) || ( empty( $args['date_col'] ) ) ) {
				return '';
			}
			global $wpdb;
			// Get the export date range.
			$date_range = self::get_scheduled_export_date_range( (int) $args['interval_days'] );
			if ( ( empty( $date_range ) ) || ( ! is_array( $date_range ) ) || ( empty( $date_range['start_date'] ) ) || ( empty( $date_range['end_date'] ) ) ) {
				return '';
			}
			// Determine if order statuses are provided and not empty.
			if ( ! empty( $args['order_statuses'] ) && is_array( $args['order_statuses'] ) && ( ! empty( $args['status_col'] ) ) ) {
				$rules = array();
				// Build a separate AND block for each order status.
				foreach ( $args['order_statuses'] as $status ) {
					$rules[] = array(
						'condition' => 'AND',
						'rules'     => array(
							array(
								'type'     => $wpdb->prefix . $args['table_nm'] . '.' . $args['status_col'],
								'operator' => 'is',
								'value'    => $status,
							),
							array(
								'type'     => $wpdb->prefix . $args['table_nm'] . '.' . $args['date_col'],
								'operator' => 'gte',
								'value'    => $date_range['start_date'],
							),
							array(
								'type'     => $wpdb->prefix . $args['table_nm'] . '.' . $args['date_col'],
								'operator' => 'lte',
								'value'    => $date_range['end_date'],
							),
						),
					);
				}
				return wp_json_encode(
					array(
						array(
							'condition' => 'OR',
							'rules'     => $rules,
						),
					)
				);
			} else {
				// If no order statuses are provided, only use the date range.
				return wp_json_encode(
					array(
						array(
							'condition' => 'OR',
							'rules'     => array(
								array(
									'condition' => 'AND',
									'rules'     => array(
										array(
											'type'     => $wpdb->prefix . $args['table_nm'] . '.' . $args['date_col'],
											'operator' => 'gte',
											'value'    => $date_range['start_date'],
										),
										array(
											'type'     => $wpdb->prefix . $args['table_nm'] . '.' . $args['date_col'],
											'operator' => 'lte',
											'value'    => $date_range['end_date'],
										),
									),
								),
							),
						),
					)
				);
			}
		}

		/**
		 * Calculates the start and end date range for exporting orders based on run time and interval.
		 *
		 * @param int    $interval_days  The number of past days to include in the export.
		 * @param string $end_date_time  The scheduled run time in 'Y-m-d H:i:s' format.
		 * @return array                 Associative array with 'start_date' and 'end_date'.
		 */
		public static function get_scheduled_export_date_range( $interval_days = 0, $end_date_time = '' ) {
			$interval_days = intval( $interval_days );
			if ( ( empty( $interval_days ) ) ) {
				return;
			}
			if ( ( empty( $end_date_time ) ) ) {
				$end_date_time = current_time( 'Y-m-d H:i:s' );
			}
			// Convert GMT offset (in hours) to seconds.
			$offset    = (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS;
			$timestamp = strtotime( $end_date_time );
			if ( ! $timestamp ) {
				return;
			}
			return array(
				'start_date' => gmdate( 'Y-m-d', strtotime( "-{$interval_days} days", $timestamp - $offset ) + $offset ) . ' 00:00:00',
				'end_date'   => gmdate( 'Y-m-d', strtotime( '-1 days', $timestamp - $offset ) + $offset ) . ' 23:59:59',
			);
		}

		/**
		 * Sends an email using WooCommerce's `wc_mail` if available,
		 * otherwise falls back to WordPress's `wp_mail`.
		 *
		 * @param array $args {.
		 *     @type string $subject Email subject.
		 *     @type string $email   Recipient's email address.
		 *     @type string $message Email body content.
		 * }.
		 * @return void
		 */
		public static function send_email( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['subject'] ) ) || ( empty( $args['email'] ) ) || ( empty( $args['message'] ) ) ) {
				return;
			}
			if ( function_exists( 'wc_mail' ) ) {
				wc_mail( sanitize_email( $args['email'] ), $args['subject'], $args['message'] );
			} elseif ( function_exists( 'wp_mail' ) ) {
				wp_mail( sanitize_email( $args['email'] ), $args['subject'], $args['message'] );
			}
		}

		/**
		 * Retrieves an array of WP_Post objects based on post IDs.
		 *
		 * @param array $post_ids Array of post IDs to retrieve.
		 * @return array|null Array of WP_Post objects or void if input is invalid.
		 */
		public static function get_post_obj_from_ids( $post_ids = array() ) {
			if ( empty( $post_ids ) || ( ! is_array( $post_ids ) ) ) {
				return;
			}
			$num_ids  = count( $post_ids );
			$post_ids = array_map( 'intval', $post_ids ); // Sanitize ids.
			global $wpdb;
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}posts WHERE ID IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ')',
					$post_ids
				)
			);
			if ( is_wp_error( $results ) || empty( $results ) || ( ! is_array( $results ) ) ) {
				return;
			}
			return array_map(
				function ( $result ) {
					return new WP_Post( $result );
				},
				$results
			);
		}

		/**
		 * Merges new data into existing post data, with new fields overwriting old ones.
		 *
		 * @param WP_Post|null $post The post object to update.
		 * @param array        $args New post data fields to merge.
		 * @return array $args Updated args.
		 */
		public static function process_post_update_data( $post = null, $args = array() ) {
			if ( empty( $post ) || empty( $args ) || ( ! is_array( $args ) ) ) {
				return $args;
			}
			// First, get all of the original fields.
			$current_post_data = get_post( $post->ID, ARRAY_A );
			// Escape data pulled from DB.
			$current_post_data = wp_slash( $current_post_data );
			// Passed post category list overwrites existing category list if not empty.
			$post_cats = ( ! empty( $args['post_category'] ) && is_array( $args['post_category'] ) && count( $args['post_category'] ) > 0 ) ? $args['post_category'] : $current_post_data['post_category'];
			// Drafts shouldn't be assigned a date unless explicitly done so by the user.
			$clear_date = ( ! empty( $current_post_data['post_status'] ) && in_array( $current_post_data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) && empty( $args['edit_date'] ) && ( '0000-00-00 00:00:00' === $current_post_data['post_date_gmt'] ) ) ? true : false;
			// Merge old and new fields with new fields overwriting old ones.
			$args                  = ( ( ! empty( $current_post_data ) ) && ( is_array( $current_post_data ) ) ) ? array_merge( $current_post_data, $args ) : $args;
			$args['post_category'] = $post_cats;
			if ( $clear_date ) {
				$args['post_date']     = current_time( 'mysql' );
				$args['post_date_gmt'] = '';
			}
			return $args;
		}

		/**
		 * Updates multiple posts with provided data.
		 *
		 * @param array $request_params Array of post data and fields to update where each key in array is a post ID and the value is an associative array of post fields to update.
		 * @return array|false Array of updated post IDs or false if an error occurs in update.
		 */
		public static function update_posts( $request_params = array() ) {
			if ( ( ! is_array( $request_params ) ) || empty( $request_params ) || empty( $request_params['posts_data'] ) || ( ! is_array( $request_params['posts_data'] ) ) ) {
				return;
			}
			$wp_error         = false;
			$fire_after_hooks = true;
			$posts_parms_arr  = $request_params['posts_data'];
			global $wpdb;
			$post_ids = array_map( 'intval', array_keys( $request_params['posts_data'] ) ); // Sanitize ids.
			$posts    = self::get_post_obj_from_ids( $post_ids );
			if ( empty( $posts ) || ( ! is_array( $posts ) ) ) {
				return;
			}
			$posts_data_to_update      = array();
			$posts_meta_data_to_update = array();
			$posts_meta_keys           = array();
			$posts_before              = array();
			$posts_data_for_after_hook = array(); // data maybe modified by filter wp_insert_post_data.
			$posts_args_for_after_hook = array(); // unmodified data.
			$update                    = true;
			$posts_update_result       = true;
			$postmeta_update_result    = array();
			$terms_update_result       = array();
			$updated_posts_obj         = array();
			$taxonomies                = ( ! empty( $request_params['taxonomies'] ) ) ? $request_params['taxonomies'] : array();
			$taxonomy_data_to_update   = array();
			$taxonomies_update_result  = true;
			// compat for 'Germanized for WooCommerce Pro' plugin.
			$taxonomy_terms = apply_filters(
				self::$plugin_prefix . '_get_taxonomy_terms',
				$taxonomies
			);
			// code for mapping posts update data.
			foreach ( $posts as $post ) {
				if ( empty( $post->ID ) ) {
					continue;
				}
				$post_parms = $posts_parms_arr[ $post->ID ]; // original post parms array, may have structure different from WP post parms array.
				if ( is_object( $post_parms ) ) {
					// Non-escaped post was passed.
					$post_parms = get_object_vars( $post_parms ); // convert an object into an associative array.
					$post_parms = wp_slash( $post_parms );
				}
				$postarr = self::process_post_update_data( $post, $post_parms );
				if ( ( true === self::is_skip_post_data_update( $post_parms, $postarr ) ) ) {
					continue;
				}
				// map $postarr['tax_input'] just like wp does.
				if ( ! empty( $postarr['tax_input'] ) && is_array( $postarr['tax_input'] ) ) {
					$postarr['tax_input'] = array_map(
						function ( $terms ) {
							return array_column(
								array_filter(
									$terms,
									function ( $term ) {
										return ( ( ! empty( $term['operator'] ) ) && ( 'remove_from' !== $term['operator'] ) );
									}
								),
								'value'
							);
						},
						$postarr['tax_input']
					);
				}
				if ( ! is_array( $postarr ) ) {
					continue;
				}
				// Capture original pre-sanitized array for passing into filters.
				$unsanitized_postarr = $postarr;
				$user_id             = get_current_user_id();
				$defaults            = array(
					'post_author'           => $user_id,
					'post_content'          => '',
					'post_content_filtered' => '',
					'post_title'            => '',
					'post_excerpt'          => '',
					'post_status'           => 'draft',
					'post_type'             => 'post',
					'comment_status'        => '',
					'ping_status'           => '',
					'post_password'         => '',
					'to_ping'               => '',
					'pinged'                => '',
					'post_parent'           => 0,
					'menu_order'            => 0,
					'guid'                  => '',
					'import_id'             => 0,
					'context'               => '',
					'post_date'             => '',
					'post_date_gmt'         => '',
				);
				$postarr             = wp_parse_args( $postarr, $defaults );
				unset( $postarr['filter'] );
				$postarr = sanitize_post( $postarr, 'db' );
				$guid    = $postarr['guid'];
				// Get the post ID and GUID.
				$post_id                  = $post->ID;
				$post_before              = $post;
				$posts_before[ $post_id ] = $post_before; // posts before array with key as id and value as post obj.
				if ( is_null( $post_before ) ) {
					continue;
				}
				$guid            = $post->guid;
				$previous_status = $post->post_status;
				$post_type       = empty( $postarr['post_type'] ) ? 'post' : $postarr['post_type'];
				$post_title      = $postarr['post_title'];
				$post_content    = $postarr['post_content'];
				$post_excerpt    = $postarr['post_excerpt'];
				$post_name       = ( ! empty( $postarr['post_name'] ) ) ? $postarr['post_name'] : $post_before->post_name;
				$maybe_empty     = 'attachment' !== $post_type
					&& ! $post_content && ! $post_title && ! $post_excerpt
					&& post_type_supports( $post_type, 'editor' )
					&& post_type_supports( $post_type, 'title' )
					&& post_type_supports( $post_type, 'excerpt' );
				// Filters whether the post should be considered "empty".
				if ( apply_filters( 'wp_insert_post_empty_content', $maybe_empty, $postarr ) ) {
					continue;
				}
				$post_status = empty( $postarr['post_status'] ) ? 'draft' : $postarr['post_status'];
				if ( 'attachment' === $post_type && ( ! in_array( $post_status, array( 'inherit', 'private', 'trash', 'auto-draft' ), true ) ) ) {
					$post_status = 'inherit';
				}
				if ( ! empty( $postarr['post_category'] ) ) {
					// Filter out empty terms.
					$post_category = array_filter( $postarr['post_category'] );
				} elseif ( ! isset( $postarr['post_category'] ) ) {
					$post_category = $post_before->post_category;
				}
				// Make sure we set a valid category.
				if ( empty( $post_category ) || ( 0 === count( $post_category ) ) || ( ! is_array( $post_category ) ) ) {
					// 'post' requires at least one category.
					$post_category = ( 'post' === $post_type && 'auto-draft' !== $post_status ) ? array( get_option( 'default_category' ) ) : array();
				}
				// Don't allow contributors to set the post slug for pending review posts. For new posts check the primitive capability, for updates check the meta capability.
				if ( 'pending' === $post_status ) {
					$post_type_object = get_post_type_object( $post_type );
					if ( ! current_user_can( 'publish_post', $post_id ) ) {
						$post_name = '';
					}
				}
				// Create a valid post name. Drafts and pending posts are allowed to have an empty post name.
				if ( empty( $post_name ) ) {
					$post_name = ( ! in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) ? sanitize_title( $post_title ) : '';
				} else {
					// On updates, we need to check to see if it's using the old, fixed sanitization context.
					$check_name = sanitize_title( $post_name, '', 'old-save' );
					$post_name  = ( ( strtolower( rawurlencode( $post_name ) ) === $check_name ) && ( $post->post_name === $check_name ) ) ? $check_name : sanitize_title( $post_name );
				}
				// Resolve the post date from any provided post date or post date GMT strings if none are provided, the date will be set to now.
				$post_date = wp_resolve_post_date( $postarr['post_date'], $postarr['post_date_gmt'] );
				if ( ! $post_date ) {
					continue;
				}
				$post_date_gmt     = ( empty( $postarr['post_date_gmt'] ) || ( '0000-00-00 00:00:00' === $postarr['post_date_gmt'] ) ) ? ( ( ! in_array( $post_status, get_post_stati( array( 'date_floating' => true ) ), true ) ) ? get_gmt_from_date( $post_date ) : '0000-00-00 00:00:00' ) : $postarr['post_date_gmt'];
				$post_modified     = current_time( 'mysql' );
				$post_modified_gmt = current_time( 'mysql', 1 );
				// set modified date parms to posts_parms_arr to update it in DB.
				if ( ( empty( $posts_parms_arr[ $post->ID ]['post_modified'] ) ) ) {
					$posts_parms_arr[ $post->ID ]['post_modified'] = $post_modified;
				}
				if ( ( empty( $posts_parms_arr[ $post->ID ]['post_modified_gmt'] ) ) ) {
					$posts_parms_arr[ $post->ID ]['post_modified_gmt'] = $post_modified_gmt;
				}
				// Comment status.
				$comment_status = ( empty( $postarr['comment_status'] ) ) ? 'closed' : $postarr['comment_status'];
				// These variables are needed by compact() later.
				$post_content_filtered = $postarr['post_content_filtered'];
				$post_author           = ( ! empty( $postarr['post_author'] ) ) ? $postarr['post_author'] : $user_id;
				$ping_status           = empty( $postarr['ping_status'] ) ? get_default_comment_status( $post_type, 'pingback' ) : $postarr['ping_status'];
				$to_ping               = ( ! empty( $postarr['to_ping'] ) ) ? sanitize_trackback_urls( $postarr['to_ping'] ) : '';
				$pinged                = ( ! empty( $postarr['pinged'] ) ) ? $postarr['pinged'] : '';
				$import_id             = ( ! empty( $postarr['import_id'] ) ) ? $postarr['import_id'] : 0;
				// The 'wp_insert_post_parent' filter expects all variables to be present. Previously, these variables would have already been extracted.
				$menu_order    = ( ( ! empty( $postarr['menu_order'] ) ) ) ? (int) $postarr['menu_order'] : 0;
				$post_password = ( ! empty( $postarr['post_password'] ) ) ? $postarr['post_password'] : '';
				$post_password = ( 'private' === $post_status ) ? '' : $post_password;
				$post_parent   = ( ( ! empty( $postarr['post_parent'] ) ) ) ? (int) $postarr['post_parent'] : 0;
				$new_postarr   = array_merge(
					array(
						'ID' => $post_id,
					),
					compact( array_diff( array_keys( $defaults ), array( 'context', 'filter' ) ) )
				);
				// Filters the post parent -- used to check for and prevent hierarchy loops.
				$post_parent = apply_filters( 'wp_insert_post_parent', $post_parent, $post_id, $new_postarr, $postarr );
				// If the post is being untrashed and it has a desired slug stored in post meta, reassign it.
				if ( ( 'trash' === $previous_status ) && ( 'trash' !== $post_status ) ) {
					$desired_post_slug = get_post_meta( $post_id, '_wp_desired_post_slug', true );
					if ( $desired_post_slug ) {
						delete_post_meta( $post_id, '_wp_desired_post_slug' );
						$post_name = $desired_post_slug;
					}
				}
				// If a trashed post has the desired slug, change it and let this post have it.
				if ( ( 'trash' !== $post_status ) && $post_name ) {
					// Filters whether or not to add a `__trashed` suffix to trashed posts that match the name of the updated post.
					$add_trashed_suffix = apply_filters( 'add_trashed_suffix_to_trashed_posts', true, $post_name, $post_id );
					if ( $add_trashed_suffix ) {
						wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_id );
					}
				}
				// When trashing an existing post, change its slug to allow non-trashed posts to use it.
				if ( ( 'trash' === $post_status ) && ( 'trash' !== $previous_status ) && ( 'new' !== $previous_status ) ) {
					$post_name = wp_add_trashed_suffix_to_post_name_for_post( $post_id );
				}
				$post_name = wp_unique_post_slug( $post_name, $post_id, $post_status, $post_type, $post_parent );
				// Don't unslash.
				$post_mime_type = ( ! empty( $postarr['post_mime_type'] ) ) ? $postarr['post_mime_type'] : '';
				$data           = compact(
					'post_author',
					'post_date',
					'post_date_gmt',
					'post_content',
					'post_content_filtered',
					'post_title',
					'post_excerpt',
					'post_status',
					'post_type',
					'comment_status',
					'ping_status',
					'post_password',
					'post_name',
					'to_ping',
					'pinged',
					'post_modified',
					'post_modified_gmt',
					'post_parent',
					'menu_order',
					'post_mime_type',
					'guid'
				);
				$emoji_fields   = array( 'post_title', 'post_content', 'post_excerpt' );
				foreach ( $emoji_fields as $emoji_field ) {
					if ( ! empty( $data[ $emoji_field ] ) ) {
						$charset = $wpdb->get_col_charset( $wpdb->posts, $emoji_field );
						if ( 'utf8' === $charset ) {
							$data[ $emoji_field ] = wp_encode_emoji( $data[ $emoji_field ] );
						}
					}
				}
				// Filters slashed post data just before it is inserted into the database.
				$data = apply_filters( 'wp_insert_post_data', $data, $postarr, $unsanitized_postarr, $update );
				$data = wp_unslash( $data );
				// Fires immediately before an existing post is updated in the database.
				do_action( 'pre_post_update', $post_id, $data );
				$posts_data_to_update[ $post_id ] = $posts_parms_arr[ $post->ID ];
				if ( empty( $data['post_name'] ) && ( ! in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) ) ) {
					$data['post_name']                             = wp_unique_post_slug( sanitize_title( $data['post_title'], $post_id ), $post_id, $data['post_status'], $post_type, $post_parent );
					$posts_data_to_update[ $post_id ]['post_name'] = $data['post_name'];
				}
				if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
					wp_set_post_categories( $post_id, $post_category );
				}
				if ( ( ! empty( $postarr['tags_input'] ) ) && is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
					wp_set_post_tags( $post_id, $postarr['tags_input'] );
				}

				if ( ! empty( $post_parms['tax_input'] ) && is_array( $post_parms['tax_input'] ) ) {
					foreach ( $post_parms['tax_input'] as $taxonomy => $terms_data ) {
						if ( ( ! taxonomy_exists( $taxonomy ) ) || ( empty( $terms_data ) ) || ( ! is_object_in_taxonomy( $post_type, $taxonomy ) ) ) {
							continue;
						}
						// Prepare an array to hold term values.
						$term_ids_set     = array();
						$term_ids_remove  = array();
						$append           = false; // Default append to false.
						$remove_all_terms = false;
						foreach ( $terms_data as $term_data ) {
							if ( ( empty( $term_data ) ) || ( empty( $term_data['operator'] ) ) ) {
								continue;
							}
							$remove_all_terms = ( ! empty( $term_data['remove_all_terms'] ) && true === $term_data['remove_all_terms'] ) ? true : false;
							if ( ( 'remove_from' !== $term_data['operator'] ) ) {
								if ( ( is_array( $term_data['value'] ) ) ) {
									$term_ids_set = array_map( 'absint', $term_data['value'] );
								} else {
									$term_ids_set[] = ( ! empty( $term_data['value'] ) ) ? absint( $term_data['value'] ) : 0;
								}
								if ( 'add_to' === $term_data['operator'] ) {
									$append = true; // Set append if any term needs it.
								}
							} else {
								if ( ( is_array( $term_data['value'] ) ) ) {
									$term_ids_remove = array_map( 'absint', $term_data['value'] );
								} else {
									$term_ids_remove[] = ( ! empty( $term_data['value'] ) ) ? absint( $term_data['value'] ) : 0;
								}
							}
						}
						// compat for 'Germanized for WooCommerce Pro' plugin.
						$postarr = apply_filters(
							self::$plugin_prefix . '_update_meta_args',
							$postarr,
							array(
								'taxonomy'        => $taxonomy,
								'term_ids'        => $term_ids_set,
								'term_ids_remove' => $term_ids_remove,
								'taxonomy_terms'  => $taxonomy_terms,
							)
						);
						$taxonomy_data_to_update[ $post_id ][ $taxonomy ] = array(
							'term_ids_set'     => $term_ids_set,
							'taxonomy'         => $taxonomy,
							'append'           => $append,
							'term_ids_remove'  => $term_ids_remove,
							'remove_all_terms' => $remove_all_terms,
						);
					}
				}
				if ( ! empty( $postarr['meta_input'] ) ) {
					$posts_meta_data_to_update[ $post_id ] = $postarr['meta_input'];
					$posts_meta_keys                       = array_unique(
						array_merge(
							$posts_meta_keys,
							array_keys( $postarr['meta_input'] )
						)
					);
				}
				$posts_data_for_after_hook[ $post_id ] = $data; // modified data by filter wp_insert_post_data.
				$posts_args_for_after_hook[ $post_id ] = $postarr; // same as data but unmodified, since data is filtered by hook.
				$updated_posts_obj[ $post_id ]         = self::update_post_object( $post, (object) $data );
			}
			// Update posts data.
			$posts_update_result = self::run_bulk_update_posts_query( $posts_data_for_after_hook, $post_ids );
			if ( empty( $posts_update_result ) ) {
				return array(
					'taxonomies_update_result' => false,
					'postmeta_update_result'   => false,
					'posts_update_result'      => false,
				);
			}
			// update post_meta data.
			if ( ( ! empty( $posts_meta_data_to_update ) ) && ( ! empty( $posts_meta_keys ) ) ) {
				$postmeta_update_result = self::update_meta_tables(
					array(
						'meta_data_edited'     => array(
							'postmeta' => $posts_meta_data_to_update,
						),
						'meta_keys_edited'     => ( is_array( $posts_meta_keys ) ) ? array_unique( $posts_meta_keys ) : array(),
						'task_id'              => ( ! empty( $request_params['task_id'] ) ) ? absint( $request_params['task_id'] ) : 0,
						'prev_postmeta_values' => ( ! empty( $request_params['prev_postmeta_values'] ) ) ? $request_params['prev_postmeta_values'] : array(),
					)
				);
			}
			// update terms data.
			if ( ( ! empty( $taxonomy_data_to_update ) ) && ( ! empty( $taxonomies ) ) ) {
				$taxonomies_update_result = self::set_or_remove_object_terms(
					array(
						'taxonomy_data_to_update' => $taxonomy_data_to_update,
						'taxonomies'              => $taxonomies,
						'task_id'                 => ( ! empty( $request_params['task_id'] ) ) ? absint( $request_params['task_id'] ) : 0,
					)
				);
			}
			// return update result.
			return array(
				'taxonomies_update_result'    => ( ! empty( $taxonomies_update_result ) ) ? $taxonomies_update_result : false,
				'postmeta_update_result'      => $postmeta_update_result,
				'posts_update_result'         => $posts_update_result,
				'after_update_actions_params' => array(
					'post_ids'                  => $post_ids,
					'posts_data_for_after_hook' => $posts_data_for_after_hook,
					'posts_args_for_after_hook' => $posts_args_for_after_hook,
					'fire_after_hooks'          => $fire_after_hooks,
					'posts_before'              => $posts_before,
					'task_id'                   => ( ! empty( $request_params['task_id'] ) ) ? absint( $request_params['task_id'] ) : 0,
					'posts_fields_edited'       => $posts_parms_arr,
					'updated_posts'             => $updated_posts_obj,
				),
			);
		}

		/**
		 * Updates multiple posts in the WordPress posts table using a single database call.
		 *
		 * @param array $posts_data_to_update Associative array of post data with post IDs as keys.
		 * @param array $selected_post_ids Array of post IDs to update.
		 * @return void|false|int Number of rows affected if the update is successful, or false if the update fails or returns an error.
		 */
		public static function run_bulk_update_posts_query( $posts_data_to_update = array(), $selected_post_ids = array() ) {
			if ( empty( $posts_data_to_update ) || ( ! is_array( $posts_data_to_update ) ) || empty( $selected_post_ids ) || ( ! is_array( $selected_post_ids ) ) ) {
				return;
			}
			global $wpdb;
			// Sanitize and count post IDs.
			$selected_post_ids = array_map( 'intval', $selected_post_ids );
			$num_ids           = count( $selected_post_ids );
			$columns           = array(
				'post_author',
				'post_date',
				'post_date_gmt',
				'post_content',
				'post_content_filtered',
				'post_title',
				'post_excerpt',
				'post_status',
				'post_type',
				'comment_status',
				'ping_status',
				'post_password',
				'post_name',
				'to_ping',
				'pinged',
				'post_modified',
				'post_modified_gmt',
				'post_parent',
				'menu_order',
				'post_mime_type',
				'guid',
			);
			// Initialize arrays to hold SQL parts.
			$case_statements = array_fill_keys( $columns, array() );
			// Build CASE statements for each field.
			foreach ( $posts_data_to_update as $post_id => $post_data ) {
				if ( ! in_array( $post_id, $selected_post_ids, true ) ) {
					continue;
				}
				foreach ( $case_statements as $field => &$case_clause ) {
					if ( ! isset( $post_data[ $field ] ) ) {
						continue;
					}
					$case_clause[] = $wpdb->prepare( 'WHEN %d THEN %s', $post_id, $post_data[ $field ] );
				}
			}

			// Construct SET clauses with CASE expressions.
			$set_clauses = array();
			foreach ( $case_statements as $field => $clauses ) {
				if ( 0 === count( $clauses ) ) {
					continue;
				}
				$set_clauses[] = "{$field} = CASE ID " . implode( ' ', $clauses ) . " ELSE {$field} END";
			}
			// If there are no valid fields to update, exit.
			if ( 0 === count( $set_clauses ) ) {
				return;
			}
			// Execute the query with the selected post IDs.
			$wpdb1 = $wpdb;
			if ( ( is_wp_error( $wpdb1->query( $wpdb1->prepare( "UPDATE {$wpdb1->prefix}posts SET " . implode( ', ', $set_clauses ) . ' WHERE ID IN (' . implode( ',', array_fill( 0, count( $selected_post_ids ), '%d' ) ) . ')', $selected_post_ids ) ) ) ) ) {
				sa_manager_log( 'error', _x( 'Bulk update posts batch failed', 'bulk update process', 'smart-manager-for-wp-e-commerce' ) );
				return false;
			}
			return true;
		}

		/**
		 * Function for actions to execute after updating a post.
		 *
		 * @param array $parms Parameters for actions to fire after post update.
		 * @return void
		 */
		public static function update_posts_after_update_actions( $parms = array() ) {
			if ( ( empty( $parms ) ) || ( ! is_array( $parms ) ) ) {
				return;
			}
			$post_ids                  = ( ! empty( $parms['post_ids'] ) ) ? $parms['post_ids'] : array();
			$posts_data_for_after_hook = ( ! empty( $parms['posts_data_for_after_hook'] ) ) ? $parms['posts_data_for_after_hook'] : array();
			$posts_args_for_after_hook = ( ! empty( $parms['posts_args_for_after_hook'] ) ) ? $parms['posts_args_for_after_hook'] : array();
			$fire_after_hooks          = ( ! empty( $parms['fire_after_hooks'] ) ) ? $parms['fire_after_hooks'] : false;
			$posts_before              = ( ! empty( $parms['posts_before'] ) ) ? $parms['posts_before'] : array();
			$posts_fields_edited       = ( ! empty( $parms['posts_fields_edited'] ) ) ? $parms['posts_fields_edited'] : array();
			$task_id                   = ( ! empty( $parms['task_id'] ) ) ? $parms['task_id'] : 0;
			$update                    = true;
			$updated_posts_obj         = ( ! empty( $parms['updated_posts'] ) ) ? $parms['updated_posts'] : array();
			if ( ( empty( $posts_fields_edited ) ) || ( empty( $post_ids ) ) || ( empty( $posts_data_for_after_hook ) ) || ( empty( $posts_args_for_after_hook ) ) || ( empty( $posts_before ) ) ) {
				return;
			}
			if ( ( empty( $updated_posts_obj ) ) || ( ! is_array( $updated_posts_obj ) ) ) {
				return;
			}
			foreach ( $updated_posts_obj as $post ) {
				if ( ( empty( $post->ID ) ) ) {
					continue;
				}
				$post_id     = $post->ID;
				$post_before = $posts_before[ $post_id ];
				$data        = $posts_data_for_after_hook[ $post_id ]; // post data that maybe modified by filter.
				$args        = $posts_args_for_after_hook[ $post_id ]; // unmodified post data.
				if ( ! empty( $args['page_template'] ) ) {
					$post->page_template = $args['page_template'];
					$page_templates      = wp_get_theme()->get_page_templates( $post );
					( ( 'default' !== $args['page_template'] ) && ( ! isset( $page_templates[ $args['page_template'] ] ) ) ) ? update_post_meta( $post_id, '_wp_page_template', 'default' ) : update_post_meta( $post_id, '_wp_page_template', $args['page_template'] );
				}
				self::fire_post_update_hooks( $post, $post_before, $update, $post_before->post_status, $data['post_status'] );
				if ( $fire_after_hooks ) {
					wp_after_insert_post( $post, $update, $post_before );
				}
				if ( ( empty( $task_id ) ) || ( ! is_array( $posts_fields_edited ) ) ) {
					continue;
				}
				foreach ( $posts_fields_edited[ $post_id ] as $key => $value ) {
					if ( ( ! empty( $key ) ) && ( 'ID' === $key ) ) {
						continue;
					}
					do_action(
						'sa_manager_update_action_params',
						array(
							'task_id'     => $task_id,
							'action'      => 'set_to',
							'status'      => 'completed',
							'record_id'   => $post_id,
							'field'       => 'posts/' . $key,
							'prev_val'    => $post_before->$key,
							'updated_val' => $value,
						)
					);
				}
			}
		}

		/**
		 * Fires custom hooks when an existing post is updated.
		 *
		 * @param WP_Post|null $post            The post object after update.
		 * @param WP_Post|null $post_before     The post object before update.
		 * @param bool         $update          Whether this is an update. Default true.
		 * @param string       $previous_status The post's previous status.
		 * @param string       $new_status      The post's new status.
		 *
		 * @return void
		 */
		public static function fire_post_update_hooks( $post = null, $post_before = null, $update = true, $previous_status = '', $new_status = '' ) {
			if ( ( empty( $post ) ) || ( empty( $post_before ) ) || ( empty( $previous_status ) ) || ( empty( $new_status ) ) ) {
				return;
			}
			$post->ID = absint( $post->ID );
			do_action( self::$plugin_prefix . '_before_run_after_update_hooks', $post, $post_before );
			do_action( 'transition_post_status', $new_status, $previous_status, $post );
			do_action( "{$previous_status}_to_{$new_status}", $post );
			do_action( "{$new_status}_{$post->post_type}", $post->ID, $post, $previous_status );
			do_action( "edit_post_{$post->post_type}", $post->ID, $post );
			do_action( 'edit_post', $post->ID, $post );
			do_action( 'post_updated', $post->ID, $post, $post_before );
			do_action( "save_post_{$post->post_type}", $post->ID, $post, $update );
			do_action( 'save_post', $post->ID, $post, $update );
			do_action( 'wp_insert_post', $post->ID, $post, $update );
		}

		/**
		 * Check whether to skip post data update based on specific parameters.
		 *
		 * @param array $update_params Parameters provided for the update, expected to include 'tax_input'.
		 * @param array $post_data Data of the post being updated, including post type.
		 *
		 * @return bool|void Returns true to skip update, false to proceed, or void if input is invalid.
		 */
		public static function is_skip_post_data_update( $update_params = array(), $post_data = array() ) {
			if ( ( empty( $update_params ) ) || ( empty( $post_data ) ) || ( ! is_array( $post_data ) ) || ( empty( $post_data['post_type'] ) ) || ( ! is_array( $update_params ) ) ) {
				return;
			}
			$skip_update = false;
			if ( ( count( $update_params ) !== 1 ) || ( ! array_key_exists( 'tax_input', $update_params ) ) ) {
				return $skip_update;
			}
			if ( ( ! is_array( $update_params['tax_input'] ) ) || ( empty( $update_params['tax_input'] ) ) ) {
				return $skip_update;
			}
			foreach ( array_keys( $update_params['tax_input'] ) as $taxonomy ) {
				if ( ( taxonomy_exists( $taxonomy ) ) && ( is_object_in_taxonomy( $post_data['post_type'], $taxonomy ) ) ) {
					return false;
				}
				$skip_update = true;
			}
			return $skip_update;
		}

		/**
		 * Function Clones a post object and updates its properties without modifying the original.
		 *
		 * @param object|null $post_object The original post object to be cloned and updated.
		 * @param object|null $update_properties Key-value pairs of properties to update.
		 *
		 * @return object|null The updated post object or null
		 */
		public static function update_post_object( $post_object = null, $update_properties = null ) {
			if ( ( empty( $post_object ) ) || ( empty( $update_properties ) ) ) {
				return;
			}
			// Clone the post object to avoid modifying the original.
			$post_object = clone $post_object;
			foreach ( $update_properties as $property => $value ) {
				if ( ! property_exists( $post_object, $property ) ) {
					continue;
				}
				$post_object->$property = $value; // Override existing property with new value.
			}
			return $post_object;
		}

		/**
		 * Retrieve meta data for the given IDs and meta keys.
		 *
		 * @param array  $ids              Array of object IDs (e.g., post IDs).
		 * @param array  $meta_keys        Array of meta keys to retrieve.
		 * @param string $update_table     Meta table name (e.g., wp_postmeta).
		 * @param string $update_table_key Column name used to match object ID. Default 'post_id'.
		 *
		 * @return array Retrieved meta data grouped by ID.
		 */
		public static function get_meta_data( $ids, $meta_keys, $update_table, $update_table_key = 'post_id' ) {
			global $wpdb;

			$ids_format       = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
			$meta_keys_format = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
			$group_by         = '';

			if ( 'postmeta' === $update_table ) {
				$group_by = 'GROUP BY ' . $update_table_key . ' , meta_id';
			}

			$old_meta_data_query   = "SELECT *
								FROM {$wpdb->prefix}$update_table
								WHERE post_id IN (" . implode( ',', $ids ) . ")
									AND meta_key IN ('" . implode( "','", $meta_keys ) . "')
									AND 1=%d
								$group_by";
			$wpdb1                 = $wpdb;
			$old_meta_data_results = $wpdb1->get_results( $wpdb1->prepare( $old_meta_data_query, 1 ), 'ARRAY_A' );

			$old_meta_data = array();

			if ( count( $old_meta_data_results ) > 0 ) {
				foreach ( $old_meta_data_results as $meta_data ) {

					$post_id = $meta_data[ $update_table_key ];
					unset( $meta_data[ $update_table_key ] );

					if ( empty( $old_meta_data[ $post_id ] ) ) {
						$old_meta_data[ $post_id ] = array();
					}

					$old_meta_data[ $post_id ][] = $meta_data;
				}
			}

			return $old_meta_data;
		}

		/**
		 * Updates or inserts metadata for meta tables.
		 *
		 * Metadata is either added if missing, or updated if it already exists.
		 *
		 * @param array $args contains meta_data_edited The main data array structured as:
		 * [table_name => [id => [meta_key => meta_value]]].
		 * table_name` (string): The name of the table to update.
		 * id (int): The identifier for each record (e.g., post ID).
		 * meta_key (string): The meta key to be updated.
		 * meta_value (mixed): The new value to store.
		 *
		 * meta_keys_edited Array of specific meta keys to update.
		 *
		 * @return array|true update_failed_data array, true if all ids are updated successfully.
		 */
		public static function update_meta_tables( $args = array() ) {
			if ( empty( $args['meta_data_edited'] ) ) {
				return;
			}
			global $wpdb;
			$update_params_meta = array(); // for all tables with meta_key = meta_value like structure for updating the values.
			$insert_params_meta = array(); // for all tables with meta_key = meta_value like structure for inserting the values.
			$update_failed_data = array();
			$field_names        = array();
			foreach ( $args['meta_data_edited'] as $update_table => $update_params ) {
				if ( empty( $update_params ) ) {
					continue;
				}
				$post_ids         = array_keys( $update_params );
				$meta_keys_edited = ( ! empty( $args['meta_keys_edited'] ) ) ? $args['meta_keys_edited'] : array();
				$update_table_key = ''; // pkey for the update table.
				if ( 'postmeta' === $update_table ) {
					$update_table_key = 'post_id';
				}
				// Code for getting the old values and meta_ids.
				$old_meta_data = self::get_meta_data( $post_ids, $meta_keys_edited, $update_table, $update_table_key );
				$meta_data     = array();
				if ( ! empty( $old_meta_data ) ) {
					foreach ( $old_meta_data as $key => $old_values ) {
						foreach ( $old_values as $data ) {
							if ( empty( $meta_data[ $key ] ) ) {
								$meta_data[ $key ] = array();
							}
							$meta_data[ $key ][ $data['meta_key'] ]               = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							$meta_data[ $key ][ $data['meta_key'] ]['meta_id']    = $data['meta_id'];
							$meta_data[ $key ][ $data['meta_key'] ]['meta_value'] = $data['meta_value']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						}
					}
				}
				$meta_index          = 0;
				$insert_meta_index   = 0;
				$index               = 0;
				$insert_index        = 0;
				$old_post_id         = '';
				$update_params_index = 0;
				// Code for generating the query.
				foreach ( $update_params as $id => $updated_data ) {
					$updated_data_index = 0;
					$update_params_index++;
					foreach ( $updated_data as $key => $value ) {
						$updated_data_index++;
						$field_names[ $id ][ $key ] = "{$update_table}/meta_key={$key}/meta_value={$key}";
						$key                        = wp_unslash( $key );
						$value                      = esc_sql( wp_unslash( $value ) );
						$meta_type                  = 'post';
						if ( 'postmeta' === $update_table ) {
							$value = sanitize_meta( $key, $value, 'post' );
						}
						// Filter whether to update metadata of a specific type.
						$check = apply_filters( "update_{$meta_type}_metadata", null, $id, $key, $value, '' );
						if ( null !== $check ) {
							continue;
						}
						if ( is_numeric( $value ) ) {
							$value = strval( $value );
						}
						// Code for handling if the meta key does not exist.
						if ( empty( $meta_data[ $id ][ $key ] ) ) {
							// Filter whether to add metadata of a specific type.
							$check = apply_filters( "add_{$meta_type}_metadata", null, $id, $key, $value, false );
							if ( null !== $check ) {
								continue;
							}
							if ( empty( $insert_params_meta[ $update_table ] ) ) {
								$insert_params_meta[ $update_table ]                                 = array();
								$insert_params_meta[ $update_table ][ $insert_meta_index ]           = array();
								$insert_params_meta[ $update_table ][ $insert_meta_index ]['values'] = array();
							}
							if ( $insert_index >= 5 ) { // Code to have not more than 5 value sets in single insert query.
								$insert_index = 0;
								$insert_meta_index++;
							}
							$insert_params_meta[ $update_table ][ $insert_meta_index ]['values'][] = array(
								'id'         => $id,
								'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
								'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							);
							$value = maybe_serialize( $value );
							if ( empty( $insert_params_meta[ $update_table ][ $insert_meta_index ]['query'] ) ) {
								$insert_params_meta[ $update_table ][ $insert_meta_index ]['query'] = '(' . $id . ", '" . $key . "', '" . $value . "')";
							} else {
								$insert_params_meta[ $update_table ][ $insert_meta_index ]['query'] .= ', (' . $id . ", '" . $key . "', '" . $value . "')";
							}
							$insert_index++;
							continue;
						}
						$value = maybe_serialize( $value );
						if ( empty( $update_params_meta[ $update_table ] ) ) {
							$update_params_meta[ $update_table ]                         = array();
							$update_params_meta[ $update_table ][ $meta_index ]          = array();
							$update_params_meta[ $update_table ][ $meta_index ]['ids']   = array();
							$update_params_meta[ $update_table ][ $meta_index ]['query'] = '';
						}

						// if meta old value & new value does not match then create a query for updating.
						if ( ! empty( $meta_data[ $id ][ $key ] ) && $meta_data[ $id ][ $key ]['meta_value'] !== $value ) {
							$meta_data[ $id ][ $key ]['meta_value'] = $value; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							if ( $index >= 5 && $old_post_id !== $id ) {
								$update_params_meta[ $update_table ][ $meta_index ]['query'] .= ' ELSE meta_value END END ';
								$index = 0;
								$meta_index++;
							}

							if ( empty( $update_params_meta[ $update_table ][ $meta_index ]['query'] ) ) {
								$update_params_meta[ $update_table ][ $meta_index ]['query'] = ' CASE post_id ';
							}

							if ( $old_post_id !== $id ) {
								if ( ! empty( $index ) ) {
									$update_params_meta[ $update_table ][ $meta_index ]['query'] .= ' ELSE meta_value END ';
								}
								$update_params_meta[ $update_table ][ $meta_index ]['query'] .= " WHEN '" . $id . "' THEN
CASE meta_key ";

								$old_post_id = $id;
								$update_params_meta[ $update_table ][ $meta_index ]['ids'][] = $id;
								$index++;
							}
							$update_params_meta[ $update_table ][ $meta_index ]['query'] .= " WHEN '" . $key . "' THEN '" . $value . "' ";
						}
					}
					// Code for the last condition.
					if ( count( $update_params ) === $update_params_index && count( $updated_data ) === $updated_data_index && ! empty( $update_params_meta[ $update_table ][ $meta_index ]['query'] ) ) {
						$update_params_meta[ $update_table ][ $meta_index ]['query'] .= ' ELSE meta_value END END ';
					}
				}

				// Start here... update the actions and query in for loop.
				if ( ! empty( $insert_params_meta ) ) {
					foreach ( $insert_params_meta as $insert_table => $data ) {
						if ( ! is_array( $data ) || 0 === count( $data ) ) {
							continue;
						}
						$insert_table_key = 'post_id';
						foreach ( $data as $insert_params ) {
							if ( empty( $insert_params['values'] ) || empty( $insert_params['query'] ) ) {
								continue;
							}
							$insert_meta_query = "INSERT INTO {$wpdb->prefix}" . $insert_table . ' (' . $insert_table_key . ',meta_key,meta_value) VALUES ' . $insert_params['query'];
							if ( 'postmeta' === $insert_table ) {
								// function to replicate WordPress add_metadata().
								self::add_post_meta(
									array(
										'meta_type'        => 'post',
										'insert_values'    => $insert_params['values'],
										'insert_meta_query' => $insert_meta_query,
										'insert_table_key' => $insert_table_key,
										'field_names'      => $field_names,
										'task_id'          => ! empty( $args['task_id'] ) ? $args['task_id'] : 0,
										'prev_postmeta_values' => ! empty( $args['prev_postmeta_values'] ) ? $args['prev_postmeta_values'] : array(),
									)
								);
							} else {
								$wpdb1              = $wpdb;
								$result_insert_meta = $wpdb1->query( $insert_meta_query );
							}
						}
					}
				}

				// data updation for meta tables.
				if ( ! empty( $update_params_meta ) ) {
					foreach ( $update_params_meta as $update_table => $data ) {
						if ( ! is_array( $data ) || 0 === count( $data ) ) {
							continue;
						}
						$update_table_key = ( empty( $update_table_key ) ) ? 'post_id' : $update_table_key;
						foreach ( $data as $update_params ) {
							if ( empty( $update_params['ids'] ) || empty( $update_params['query'] ) ) {
								continue;
							}
							$update_meta_query = "UPDATE {$wpdb->prefix}$update_table
SET meta_value = " . $update_params['query'] . "
WHERE $update_table_key IN (" . implode( ',', $update_params['ids'] ) . ')';
							if ( 'postmeta' === $update_table ) {
								// function to replicate WordPress update_postmeta().
								$update_result = self::update_post_meta(
									array(
										'meta_type'        => 'post',
										'update_ids'       => ( ! empty( $update_params['ids'] ) ) ? $update_params['ids'] : array(),
										'meta_data'        => ( ! empty( $meta_data ) ) ? $meta_data : array(),
										'update_meta_query' => $update_meta_query,
										'update_table_key' => $update_table_key,
										'field_names'      => $field_names,
										'task_id'          => ! empty( $args['task_id'] ) ? $args['task_id'] : 0,
										'prev_postmeta_values' => ! empty( $args['prev_postmeta_values'] ) ? $args['prev_postmeta_values'] : array(),
									)
								);
								if ( true !== $update_result['update_status'] && ! empty( $update_result['update_failed_data'] && is_array( $update_result['update_failed_data'] ) ) ) {
									$update_failed_data = array_merge( $update_failed_data, $update_result['update_failed_data'] );
								}
							}
						}
					}
				}
			}
			return ! empty( $update_failed_data ) ? $update_failed_data : 'success';
		}

		/**
		 * Updates meta data in batch for various WordPress meta types, with action hooks for pre- and post-update events.
		 *
		 * This function replicates the functionality of `update_post_meta()` but allows batch updating for multiple meta IDs
		 *
		 * @param array $args {
		 *     Arguments for updating meta data.
		 *
		 *     @type array  $update_ids        Array of IDs for posts, users, or other objects to update.
		 *     @type array  $meta_data         Nested array where each ID has meta keys with corresponding meta values.
		 *     @type string $meta_type         Type of meta (e.g., 'post', 'user') used for triggering specific hooks.
		 *     @type string $update_table_key  Database column used as the key for the update (e.g., 'post_id' for post meta).
		 *     @type string $update_meta_query Optional. SQL query string for executing the batch update.
		 * }
		 *
		 * @global wpdb $wpdb WordPress database abstraction object.
		 * @return array $result Array of result data.
		 */
		public static function update_post_meta( $args = array() ) {
			if ( empty( $args['update_ids'] ) || empty( $args['meta_data'] ) ) {
				return array();
			}
			$result = array(
				'update_status'      => false,
				'update_failed_data' => array(),
			);
			global $wpdb;
			$update_query_ids    = array();
			$update_query_values = $update_query_ids;
			// Code for executing actions pre update.
			foreach ( $args['update_ids'] as $id ) {
				if ( empty( $args['meta_data'][ $id ] ) ) {
					continue;
				}
				$meta_key_update_values = '';
				foreach ( $args['meta_data'][ $id ] as $meta_key => $value ) {
					do_action( "update_{$args['meta_type']}_meta", $value['meta_id'], $id, $meta_key, $value['meta_value'] );
					$meta_value = maybe_serialize( $value['meta_value'] );
					if ( 'post' === $args['meta_type'] ) {
						do_action( 'update_postmeta', $value['meta_id'], $id, $meta_key, $value['meta_value'] );
					}
					if ( empty( $args['update_meta_query'] ) ) {
						$meta_key_update_values .= " WHEN '" . $meta_key . "' THEN '" . $value['meta_value'] . "' ";
					}
				}
				if ( empty( $args['update_meta_query'] ) && ! empty( $meta_key_update_values ) ) {
					$update_query_ids[]    = $id;
					$update_query_values[] = " WHEN '" . $id . "' THEN CASE meta_key " . $meta_key_update_values . ' ELSE meta_value END ';
				}
			}
			if ( empty( $args['update_meta_query'] ) && ! empty( $update_query_values ) ) {
				$args['update_meta_query'] = "UPDATE {$wpdb->prefix}" . $args['meta_type'] . 'meta SET meta_value = CASE ' . $args['update_table_key'] . ' ' . implode( ' ', $update_query_values ) . ' END 
									WHERE ' . $args['update_table_key'] . ' IN (' . implode( ',', $update_query_ids ) . ' ) ';
			}
			if ( empty( $args['update_meta_query'] ) ) {
				return array();
			}
			$wpdb1              = $wpdb;
			$result_update_meta = $wpdb1->query( $args['update_meta_query'] );
			if ( ! empty( $result_update_meta ) && ! is_wp_error( $result_update_meta ) ) {
				$result['update_status'] = true;
			}
			// Code for executing actions post update.
			foreach ( $args['update_ids'] as $id ) {
				if ( empty( $args['meta_data'][ $id ] ) ) {
					continue;
				}
				wp_cache_delete( $id, $args['meta_type'] . '_meta' );
				foreach ( $args['meta_data'][ $id ] as $meta_key => $meta_data ) {
					do_action( "updated_{$args['meta_type']}_meta", $meta_data['meta_id'], $id, $meta_key, $meta_data['meta_value'] );
					$meta_value = maybe_serialize( $meta_data['meta_value'] );
					if ( 'post' === $args['meta_type'] ) {
						do_action( 'updated_postmeta', $meta_data['meta_id'], $id, $meta_key, $meta_value );
					}
					if ( empty( $result_update_meta ) || ( is_wp_error( $result_update_meta ) ) ) {
						$result['update_status']        = false;
						$result['update_failed_data'][] = $id . '/' . $meta_key;
					}
					if ( empty( $args['field_names'][ $id ][ $meta_key ] ) ) {
						continue;
					}
					do_action(
						'sa_manager_update_meta_action_details',
						array_merge(
							$args,
							array(
								'id'         => $id,
								'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
								'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							)
						)
					);
				}
			}
			return $result;
		}

		/**
		 * Replicates WordPress core add_metadata() specifically for post meta.
		 *
		 * Adds a meta key-value pair to a specific post, similar to add_post_meta().
		 *
		 * @param array $args Arguments for adding meta data.
		 *
		 * @return void
		 */
		public static function add_post_meta( $args = array() ) {
			if ( empty( $args ) ) {
				return;
			}
			global $wpdb;
			if ( empty( $args['insert_values'] ) ) {
				return;
			}

			$insert_query_values = array();

			// Code for executing actions pre insert.
			foreach ( $args['insert_values'] as $insert_value ) {
				do_action( "add_{$args['meta_type']}_meta", $insert_value['id'], $insert_value['meta_key'], $insert_value['meta_value'] );

				if ( empty( $args['insert_meta_query'] ) ) {
					$insert_query_values[] = ' ( ' . $insert_value['id'] . ", '" . $insert_value['meta_key'] . "', '" . $insert_value['meta_value'] . "' ) ";
				}
			}

			if ( empty( $args['insert_meta_query'] ) && ! empty( $insert_query_values ) ) {
				$args['insert_meta_query'] = "INSERT INTO {$wpdb->prefix}" . $args['meta_type'] . 'meta(' . $args['insert_table_key'] . ', meta_key, meta_value) VALUES ' . implode( ',', $insert_query_values );
			}

			// Code for inserting the values.
			$wpdb1              = $wpdb;
			$result_insert_meta = $wpdb1->query( $args['insert_meta_query'] );
			$mid                = $wpdb->insert_id;

			// Code for executing actions pre insert.
			foreach ( $args['insert_values'] as $insert_value ) {
				wp_cache_delete( $insert_value['id'], $args['meta_type'] . '_meta' );
				do_action( "added_{$args['meta_type']}_meta", $mid, $insert_value['id'], $insert_value['meta_key'], $insert_value['meta_value'] );

				$mid++;
				if ( empty( $args['task_id'] ) || empty( $insert_value['id'] ) || empty( $insert_value['meta_key'] ) || ( is_wp_error( $result_insert_meta ) ) || ! isset( $args['field_names'][ $insert_value['id'] ][ $insert_value['meta_key'] ] ) ) {
					continue;
				}
				do_action(
					'sa_manager_update_action_params',
					array(
						'task_id'     => $args['task_id'],
						'action'      => 'set_to',
						'status'      => 'completed',
						'record_id'   => $insert_value['id'],
						'field'       => $args['field_names'][ $insert_value['id'] ][ $insert_value['meta_key'] ],
						'prev_val'    => $args['prev_postmeta_values'][ $insert_value['id'] ][ $insert_value['meta_key'] ],
						'updated_val' => $insert_value['meta_value'],
					)
				);
			}
		}

		/**
		 * Bulk updates terms for multiple posts, replicating wp_set_object_terms & wp_remove_object_terms functionality.
		 *
		 * Handles term relationships, updates taxonomy counts, and triggers relevant WordPress actions.
		 *
		 * @param array $args array of taxonomy data.
		 *
		 * @return bool True on success, array of taxonomy data failed to update on failure, void when data is invalid
		 */
		public static function set_or_remove_object_terms( $args = array() ) {
			if ( ( empty( $args ) ) || ( empty( $args['taxonomy_data_to_update'] ) ) || ( empty( $args['taxonomies'] ) ) || ( ! is_array( $args['taxonomies'] ) ) || ( ! is_array( $args['taxonomy_data_to_update'] ) ) ) {
				return;
			}
			global $wpdb;
			$posts_params_arr       = $args['taxonomy_data_to_update'];
			$taxonomies             = array_map( 'sanitize_text_field', array_unique( $args['taxonomies'] ) );
			$objects_new_tt_ids     = array(); // tt ids to create relationship for.
			$tt_ids                 = array(); // tt ids (passed + newly created).
			$objects_tt_ids         = array();
			$taxonomy_count_data    = array();
			$insert_tt_placeholders = array();
			$delete_tt_placeholders = array();
			$delete_tt_ids          = array(); // all tt ids to delete relationship.
			$objects_delete_tt_ids  = array(); // tt ids to delete relationship keyed by object id.
			$existing_relationships = array();
			$insert_result          = false;
			$delete_result          = false;
			$task_id                = ( ! empty( $args['task_id'] ) ) ? $args['task_id'] : 0;
			// Fetch existing term relationships for objects/posts and taxonomies.
			$existing_terms = wp_get_object_terms(
				array_map( 'absint', array_keys( $posts_params_arr ) ), // passing object ids.
				$taxonomies,
				array(
					'fields'                 => 'all_with_object_id',
					'orderby'                => 'none',
					'update_term_meta_cache' => false,
				)
			);
			// extract old terms ids and map existing term relationships.
			foreach ( $existing_terms as $term ) {
				if ( ( empty( $term->taxonomy ) ) || ( empty( $term->term_taxonomy_id ) ) || ( empty( $term->object_id ) ) ) {
					continue;
				}
				$existing_relationships[ $term->object_id ][ $term->taxonomy ][] = $term->term_taxonomy_id;
			}
			// Process the term relationships for each object.
			foreach ( $posts_params_arr as $object_id => $params ) {
				if ( ( empty( $object_id ) ) || ( empty( $params ) ) || ( ! is_array( $params ) ) ) {
					continue;
				}
				$object_id = absint( $object_id ); // sanitize object_id or post id.

				foreach ( $params as $taxonomy => $taxonomy_data ) {
					if ( ( empty( $taxonomy_data ) ) || ( empty( $taxonomy ) ) || ( empty( $taxonomy_data['term_ids_set'] ) && empty( $taxonomy_data['term_ids_remove'] ) ) ) {
						continue;
					}
					$taxonomy = sanitize_text_field( $taxonomy ); // sanitize taxonomy.
					$append   = ( ! empty( $taxonomy_data['append'] ) ) ? true : false;

					$object_rel = ( ( ! empty( $existing_relationships ) ) && ( ! empty( $existing_relationships[ $object_id ] ) ) ) ? $existing_relationships[ $object_id ] : array();

					$object_existing_tt_ids = ( ( ! empty( $object_rel ) ) && ( ! empty( $object_rel[ $taxonomy ] ) ) ) ? $object_rel[ $taxonomy ] : array();

					$objects_delete_tt_ids[ $object_id ][ $taxonomy ] = array();
					$objects_new_tt_ids[ $object_id ][ $taxonomy ]    = array();
					$objects_tt_ids[ $object_id ][ $taxonomy ]        = array();
					foreach ( $taxonomy_data['term_ids_set'] as $tt_id ) {
						if ( ( empty( $tt_id ) ) ) {
							if ( ( ! empty( $taxonomy_data['remove_all_terms'] ) ) ) {
								$taxonomy_data['term_ids_remove'] = $object_existing_tt_ids;
							}
							continue;
						}
						// code for create term.
						if ( ! is_int( $tt_id ) ) { // if not int then value assumed to be new term name (default WordPress behaviour).
							$term_info = term_exists( $tt_id, $taxonomy );
							if ( ! $term_info ) {
								$term_info = wp_insert_term( $tt_id, $taxonomy );
							}
							if ( is_wp_error( $term_info ) || ! is_array( $term_info ) || empty( $term_info['term_id'] ) ) {
								continue;
							}
							$tt_id = $term_info['term_taxonomy_id'];
						}
						$tt_id = absint( $tt_id ); // sanitize term id.
						if ( empty( $tt_id ) ) {
							continue;
						}
						if ( ( empty( $tt_ids[ $taxonomy ] ) ) || ( ! in_array( $tt_id, $tt_ids[ $taxonomy ], true ) ) ) {
							$tt_ids[ $taxonomy ][] = $tt_id;
						}
						if ( ( empty( $objects_tt_ids[ $object_id ][ $taxonomy ] ) ) || ( ! in_array( $tt_id, $objects_tt_ids[ $object_id ][ $taxonomy ], true ) ) ) {
							$objects_tt_ids[ $object_id ][ $taxonomy ][] = $tt_id;
						}

						// skip if the term relationship already exists.
						if ( ( in_array( $tt_id, $object_existing_tt_ids, true ) ) ) {
							continue;
						}
						if ( ( empty( $objects_new_tt_ids[ $object_id ][ $taxonomy ] ) ) || ( ! in_array( $tt_id, $objects_new_tt_ids[ $object_id ][ $taxonomy ], true ) ) ) {
							// Fires immediately before an object-term relationship is added.
							$objects_new_tt_ids[ $object_id ][ $taxonomy ][] = $tt_id;
							do_action( 'add_term_relationship', $object_id, $tt_id, $taxonomy );
							$insert_tt_placeholders[] = $wpdb->prepare( '(%d, %d)', $object_id, $tt_id );
						}
					}
					// Collect old term relationships for deletion if not appending.
					if ( ( ! $append ) && ( ! empty( $object_existing_tt_ids ) ) ) {
						foreach ( $object_existing_tt_ids as $old_tt_id ) {
							if ( ( empty( $tt_ids[ $taxonomy ] ) ) || ( in_array( $old_tt_id, $tt_ids[ $taxonomy ], true ) ) ) {
								continue;
							}
							if ( ( empty( $objects_delete_tt_ids[ $object_id ][ $taxonomy ] ) ) || ( ! in_array( $old_tt_id, $objects_delete_tt_ids[ $object_id ][ $taxonomy ], true ) ) ) {
								$delete_tt_placeholders[]                           = $wpdb->prepare( '(%d, %d)', $object_id, $old_tt_id );
								$objects_delete_tt_ids[ $object_id ][ $taxonomy ][] = $old_tt_id;
							}
							if ( ( empty( $delete_tt_ids[ $taxonomy ] ) ) || ( ! in_array( $old_tt_id, $delete_tt_ids[ $taxonomy ], true ) ) ) {
								$delete_tt_ids[ $taxonomy ][] = $old_tt_id;
							}
						}
					}
					if ( ( empty( $taxonomy_data['term_ids_remove'] ) ) || ( ! is_array( $taxonomy_data['term_ids_remove'] ) ) ) {
						if ( ( ! empty( $objects_delete_tt_ids[ $object_id ][ $taxonomy ] ) ) ) {
							do_action( 'delete_term_relationships', $object_id, $objects_delete_tt_ids[ $object_id ][ $taxonomy ], $taxonomy );
						}
						continue;
					}
					foreach ( $taxonomy_data['term_ids_remove'] as $tt_id ) {
						if ( ( empty( $tt_id ) ) || ( ! in_array( $tt_id, $object_existing_tt_ids, true ) ) ) {
							continue;
						}
						if ( ( ! in_array( $tt_id, $objects_delete_tt_ids[ $object_id ][ $taxonomy ], true ) ) ) {
							$delete_tt_placeholders[]                           = $wpdb->prepare( '(%d, %d)', $object_id, $tt_id );
							$objects_delete_tt_ids[ $object_id ][ $taxonomy ][] = $tt_id;
						}
						if ( ( empty( $delete_tt_ids[ $taxonomy ] ) ) || ( ! in_array( $tt_id, $delete_tt_ids[ $taxonomy ], true ) ) ) {
							$delete_tt_ids[ $taxonomy ][] = $tt_id;
						}
					}
					if ( ( ! empty( $objects_delete_tt_ids[ $object_id ][ $taxonomy ] ) ) ) {
						do_action( 'delete_term_relationships', $object_id, $objects_delete_tt_ids[ $object_id ][ $taxonomy ], $taxonomy );
					}
				}
			}

			$all_tts = array(); // for updating counts of the taxonomies.
			foreach ( $taxonomies as $taxonomy ) {
				if ( empty( $tt_ids[ $taxonomy ] ) && empty( $delete_tt_ids[ $taxonomy ] ) ) {
					continue;
				}
				$taxonomy_count_data[ $taxonomy ] = array();
				if ( ( ! empty( $tt_ids[ $taxonomy ] ) ) ) {
					$all_tts                          = array_merge( $all_tts, $tt_ids[ $taxonomy ] );
					$taxonomy_count_data[ $taxonomy ] = array_merge( $taxonomy_count_data[ $taxonomy ], $tt_ids[ $taxonomy ] );
				}
				if ( ( ! empty( $delete_tt_ids[ $taxonomy ] ) ) ) {
					$all_tts                          = array_merge( $all_tts, $delete_tt_ids[ $taxonomy ] );
					$taxonomy_count_data[ $taxonomy ] = array_merge( $taxonomy_count_data[ $taxonomy ], $delete_tt_ids[ $taxonomy ] );
				}
			}
			if ( empty( $all_tts ) ) {
				return;
			}
			$all_tts = array_unique( $all_tts );
			// Perform bulk insert.
			$wpdb1 = $wpdb;
			if ( ( ! empty( $insert_tt_placeholders ) ) ) {
				$insert_result = $wpdb1->query( "INSERT INTO $wpdb1->term_relationships (object_id, term_taxonomy_id) VALUES " . implode( ',', $insert_tt_placeholders ) . ' ON DUPLICATE KEY UPDATE term_taxonomy_id = term_taxonomy_id' );
			}
			// Perform bulk delete.
			if ( ( ! empty( $delete_tt_placeholders ) ) ) {
				$delete_result = $wpdb1->query( "DELETE FROM $wpdb1->term_relationships WHERE (object_id, term_taxonomy_id) IN (" . implode( ',', $delete_tt_placeholders ) . ')' );
			}
			if ( ( empty( $delete_result ) ) && ( empty( $insert_result ) ) ) {
				return;
			}
			// Remove the WC action.
			if ( class_exists( 'WooCommerce' ) ) {
				remove_action( 'set_object_terms', 'wc_clear_term_product_ids', 10 );
			}
			// fire add and delete terms post actions.
			foreach ( $posts_params_arr as $object_id => $params ) {
				if ( ( empty( $object_id ) ) || ( empty( $params ) ) || ( ! is_array( $params ) ) ) {
					continue;
				}
				foreach ( $params as $taxonomy => $taxonomy_data ) {
					if ( ( empty( $taxonomy_data ) ) || ( empty( $taxonomy ) ) || ( empty( $taxonomy_data['term_ids_set'] ) && empty( $taxonomy_data['term_ids_remove'] ) ) ) {
						continue;
					}
					// $existing_relationships
					$taxonomy_old_tt_ids = ( ( ! empty( $existing_relationships[ $object_id ] ) ) && ( ! empty( $existing_relationships[ $object_id ][ $taxonomy ] ) ) ) ? $existing_relationships[ $object_id ][ $taxonomy ] : array();
					// fire add terms post action for each term.
					if ( ( ! empty( $insert_result ) ) && ( ! is_wp_error( $insert_result ) ) && ( ! empty( $objects_new_tt_ids[ $object_id ] ) ) && ( ! empty( $objects_new_tt_ids[ $object_id ][ $taxonomy ] ) ) ) {
						foreach ( $objects_new_tt_ids[ $object_id ][ $taxonomy ] as $tt_id ) {
							do_action( 'added_term_relationship', $object_id, $tt_id, $taxonomy );
							if ( empty( $task_id ) ) {
								continue;
							}
							do_action(
								'sa_manager_update_action_params',
								array(
									'task_id'     => $task_id,
									'action'      => 'remove_from',
									'status'      => 'completed',
									'record_id'   => $object_id,
									'field'       => 'terms/' . $taxonomy,
									'prev_val'    => $tt_id,
									'updated_val' => $taxonomy_old_tt_ids,
								)
							);
						}
					}
					if ( ( ! empty( $objects_tt_ids ) ) && ( ! empty( $objects_tt_ids[ $object_id ] ) ) && ( ! empty( $objects_tt_ids[ $object_id ][ $taxonomy ] ) ) ) {
						do_action( 'set_object_terms', $object_id, $taxonomy_data['term_ids_set'], $objects_tt_ids[ $object_id ][ $taxonomy ], $taxonomy, $taxonomy_data['append'], $taxonomy_old_tt_ids );
					}
					// fire delete terms post action.
					if ( ( ! empty( $delete_result ) ) && ( ! is_wp_error( $delete_result ) ) && ( ! empty( $delete_tt_ids ) ) ) {
						if ( empty( $objects_delete_tt_ids ) || ( empty( $objects_delete_tt_ids[ $object_id ] ) ) || ( empty( $objects_delete_tt_ids[ $object_id ][ $taxonomy ] ) ) ) {
							continue;
						}

						do_action( 'deleted_term_relationships', $object_id, $objects_delete_tt_ids[ $object_id ][ $taxonomy ], $taxonomy );
						wp_cache_delete( $object_id, $taxonomy . '_relationships' );
						if ( empty( $task_id ) ) {
							continue;
						}
						foreach ( $objects_delete_tt_ids[ $object_id ][ $taxonomy ] as $delete_tt_id ) {
							do_action(
								'sa_manager_update_action_params',
								array(
									'task_id'     => $task_id,
									'action'      => 'add_to',
									'status'      => 'completed',
									'record_id'   => $object_id,
									'field'       => 'terms/' . $taxonomy,
									'prev_val'    => $delete_tt_id,
									'updated_val' => $taxonomy_old_tt_ids,
								)
							);
						}
					}
				}
			}
			// update terms count.
			self::update_term_count( $taxonomy_count_data );
			do_action( self::$plugin_prefix . '_post_process_terms_update', $all_tts );
			// clear cache.
			wp_cache_set_terms_last_changed();
			if ( ( class_exists( 'SitePress' ) ) ) {
				foreach ( $posts_params_arr as $object_id => $params ) {
					$object_id = absint( $object_id );
					if ( ( empty( $object_id ) ) ) {
						continue;
					}
					self::sync_wpml_terms_translations( $object_id );
				}
			}
			return array(
				'status'                 => true,
				'existing_relationships' => $existing_relationships,
			);
		}

		/**
		 * Function to update posts count for terms of the taxonomy.
		 *
		 * @param array $taxonomy_count_data  array taxonomy data containing terms.
		 *
		 * @return void
		 */
		public static function update_term_count( $taxonomy_count_data = array() ) {
			if ( ( empty( $taxonomy_count_data ) ) || ( ! is_array( $taxonomy_count_data ) ) ) {
				return;
			}
			// update terms count for each taxonomy.
			foreach ( $taxonomy_count_data as $taxonomy => $terms ) {
				if ( ( empty( $terms ) ) || ( empty( $taxonomy ) ) || ( ! is_array( $terms ) ) ) {
					continue;
				}
				$terms = array_map( 'intval', $terms );

				$taxonomy = get_taxonomy( $taxonomy );
				if ( ( empty( $taxonomy ) ) ) {
					return;
				}
				if ( ( ! empty( $taxonomy->update_count_callback ) ) ) {
					// handle product taxonomies terms count.
					if ( ( '_wc_term_recount' === $taxonomy->update_count_callback ) && ( class_exists( 'SA_Manager_Pro_Product' ) ) && ( is_callable( array( 'SA_Manager_Pro_Product', 'products_taxonomy_term_recount' ) ) ) ) {
						SA_Manager_Pro_Product::products_taxonomy_term_recount( $terms, $taxonomy );
					} else {
						call_user_func( $taxonomy->update_count_callback, $terms, $taxonomy );
					}
				} else {
					$object_types = (array) $taxonomy->object_type;
					foreach ( $object_types as &$object_type ) {
						if ( strpos( $object_type, 'attachment:' ) ) {
							list( $object_type ) = explode( ':', $object_type );
						}
					}

					if ( array_filter( $object_types, 'post_type_exists' ) === $object_types ) {
						// Only post types are attached to this taxonomy.
						self::update_post_term_count( $terms, $taxonomy );
					} else {
						// Default count updater.
						self::update_generic_term_count( $terms, $taxonomy );
					}
				}
				clean_term_cache( $terms, '', false );
			}
		}

		/**
		 * Function to update posts count for terms.
		 *
		 * @param array  $terms  array of terms.
		 * @param object $taxonomy  Taxonomy object.
		 *
		 * @return void
		 */
		public static function update_post_term_count( $terms = array(), $taxonomy = null ) {
			if ( ( empty( $terms ) ) || ( empty( $taxonomy ) ) || ( ! is_array( $terms ) ) ) {
				return;
			}
			global $wpdb;

			$object_types = (array) $taxonomy->object_type;

			foreach ( $object_types as &$object_type ) {
				list($object_type) = explode( ':', $object_type );
			}

			$object_types = array_unique( $object_types );

			$check_attachments = array_search( 'attachment', $object_types, true );
			if ( false !== $check_attachments ) {
				unset( $object_types[ $check_attachments ] );
				$check_attachments = true;
			}
			$object_types  = esc_sql( array_filter( $object_types, 'post_type_exists' ) );
			$post_statuses = esc_sql(
				apply_filters( 'update_post_term_count_statuses', array( 'publish' ), $taxonomy )
			);
			// Prepare the placeholders for the terms in a single query.
			$placeholders = implode( ',', array_fill( 0, count( $terms ), '%d' ) );
			$counts       = array();
			// Query for attachment counts, if applicable.
			if ( $check_attachments ) {
				$terms             = array_map( 'intval', $terms );
				$post_status_args  = $post_statuses;
				$attachment_counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
					$wpdb->prepare(
						"SELECT term_taxonomy_id, COUNT(*) AS count
					FROM $wpdb->term_relationships
					INNER JOIN $wpdb->posts AS p1 ON p1.ID = $wpdb->term_relationships.object_id
					WHERE term_taxonomy_id IN (" . implode( ', ', array_fill( 0, count( $terms ), '%d' ) ) . ')
					AND (
						post_status IN (' . implode( ', ', array_fill( 0, count( $post_statuses ), '%s' ) ) . ")
						OR (
							post_status = 'inherit'
							AND post_parent > 0
							AND (
								SELECT post_status
								FROM $wpdb->posts
								WHERE ID = p1.post_parent
							) IN (" . implode( ', ', array_fill( 0, count( $post_statuses ), '%s' ) ) . ")
						)
					)
					AND post_type = 'attachment'
					GROUP BY term_taxonomy_id
					",
						array_merge( $terms, $post_status_args, $post_status_args )
					),
					OBJECT_K
				);
				$counts            = array_merge( $counts, $attachment_counts );
			}
			// Query for other object types.
			if ( $object_types ) {
				$terms                    = array_map( 'intval', $terms );
				$post_statuses            = array_map( 'sanitize_text_field', $post_statuses );
				$object_types             = array_map( 'sanitize_text_field', $object_types );
				$object_type_placeholders = implode( ', ', array_fill( 0, count( $object_types ), '%s' ) );
				$post_counts              = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
					$wpdb->prepare(
						"SELECT term_taxonomy_id, COUNT(*) AS count
					FROM $wpdb->term_relationships
					INNER JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
					WHERE term_taxonomy_id IN (" . implode( ', ', array_fill( 0, count( $terms ), '%d' ) ) . ')
					AND post_status IN (' . implode( ', ', array_fill( 0, count( $post_statuses ), '%s' ) ) . ')
					AND post_type IN (' . implode( ', ', array_fill( 0, count( $object_types ), '%s' ) ) . ')
					GROUP BY term_taxonomy_id
					',
						array_merge( $terms, $post_statuses, $object_types )
					),
					OBJECT_K
				);
				$counts                   = array_merge_recursive( $counts, $post_counts );
			}
			$updates = array();
			foreach ( (array) $terms as $term ) {
				if ( ( empty( $term ) ) ) {
					continue;
				}
				// Pre-action for the term.
				do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
				$count = 0;
				// Iterate over the array of objects in $counts to sum matching counts.
				foreach ( $counts as $object ) {
					if ( ( ! empty( $object->term_taxonomy_id ) ) && (int) $object->term_taxonomy_id === $term ) {
						$count += ( ! empty( $object->count ) ) ? (int) $object->count : 0;
					}
				}
				// Collect update data.
				$updates[] = $wpdb->prepare( '(%d, %d)', $term, $count );
			}

			// Perform bulk update query.
			if ( empty( $updates ) ) {
				return;
			}
			$wpdb1  = $wpdb;
			$query  = "
				INSERT INTO $wpdb1->term_taxonomy (term_taxonomy_id, count)
				VALUES " . implode( ', ', $updates ) . '
				ON DUPLICATE KEY UPDATE count = VALUES(count)';
			$result = $wpdb1->query( $query );
			if ( ( empty( $result ) ) || ( is_wp_error( $result ) ) ) {
				return;
			}
			// Post-action for the term count update.
			foreach ( (array) $terms as $term ) {
				do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
			}
		}

		/**
		 * Function to update other post types terms count apart from posts .
		 *
		 * @param array  $terms  array of terms.
		 * @param object $taxonomy  Taxonomy object.
		 *
		 * @return void
		 */
		public static function update_generic_term_count( $terms = array(), $taxonomy = null ) {
			if ( ( empty( $terms ) ) || ( empty( $taxonomy ) ) || ( ! is_array( $terms ) ) ) {
				return;
			}
			global $wpdb;
			// Fetch counts for all terms in one query.
			$terms  = array_map( 'intval', $terms );
			$counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					"SELECT term_taxonomy_id, COUNT(*) AS count 
					FROM $wpdb->term_relationships 
					WHERE term_taxonomy_id IN (" . implode( ',', array_fill( 0, count( $terms ), '%d' ) ) . ') 
					GROUP BY term_taxonomy_id',
					$terms
				),
				OBJECT_K
			);

			$updates = array();
			foreach ( (array) $terms as $term ) {
				if ( ( empty( $term ) ) ) {
					continue;
				}
				$count = 0;
				// Iterate over the array of objects in $counts to sum matching counts.
				foreach ( $counts as $object ) {
					if ( ( empty( $object ) ) ) {
						continue;
					}
					if ( isset( $object->term_taxonomy_id ) && (int) $object->term_taxonomy_id === $term ) {
						$count += isset( $object->count ) ? (int) $object->count : 0;
					}
				}
				// Pre-action for the term.
				do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
				$wpdb1 = $wpdb;
				// Collect update data.
				$updates[] = $wpdb1->prepare( '(%d, %d)', $term, $count );
			}

			// Perform bulk update query.
			if ( ! empty( $updates ) ) {
				$query  = "INSERT INTO $wpdb->term_taxonomy (term_taxonomy_id, count) VALUES " . implode( ', ', $updates ) . ' ON DUPLICATE KEY UPDATE count = VALUES(count)';
				$wpdb1  = $wpdb;
				$result = $wpdb1->query( $query );
				if ( ( empty( $result ) ) || ( is_wp_error( $result ) ) ) {
					return;
				}
				foreach ( (array) $terms as $term ) {
					// Post-action for the term.
					do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
				}
			}
		}

		/**
		 * Deletes multiple metadata entries in bulk.
		 *
		 * @param array $meta_objects The post metadata.
		 *
		 * @return array|void array of grouped meta data else void
		 */
		public static function group_meta_data_to_delete( $meta_objects = array() ) {
			if ( ( empty( $meta_objects ) ) || ( ! is_array( $meta_objects ) ) ) {
				return;
			}
			$meta_ids_to_delete    = array();
			$meta_data_for_actions = array();
			foreach ( $meta_objects as $meta ) {
				if ( ( empty( $meta->meta_id ) ) ) {
					continue;
				}
				$key                                       = $meta->meta_key . '|' . $meta->meta_value . '|' . $meta->object_id; // phpcs:ignore
				$meta_data_for_actions[ $key ]['meta_key'] = ( ! empty( $meta->meta_key ) ) ? sanitize_key( $meta->meta_key ) : ''; // phpcs:ignore
				$meta_data_for_actions[ $key ]['meta_value'] = ( isset( $meta->meta_value ) ) ? maybe_serialize( $meta->meta_value ) : ''; // phpcs:ignore
				$meta_data_for_actions[ $key ]['object_id']  = ( ! empty( $meta->object_id ) ) ? absint( $meta->object_id ) : 0; // phpcs:ignore
				$meta_data_for_actions[ $key ]['meta_ids'][] = ( ! empty( $meta->meta_id ) ) ? absint( $meta->meta_id ) : 0; // phpcs:ignore
				$meta_ids_to_delete[]                        = $meta->meta_id; // phpcs:ignore
			}
			return array(
				'meta_data_for_actions' => ( ! empty( $meta_data_for_actions ) ) ? array_values( $meta_data_for_actions ) : array(),
				'meta_ids_to_delete'    => array_unique( $meta_ids_to_delete ),
			);
		}

		/**
		 * Deletes multiple metadata entries in bulk.
		 *
		 * @param array $args An array of data to delete.
		 * @return bool|null True on success else null
		 */
		public static function delete_metadata( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['meta_type'] ) ) || ( empty( $args['meta_data'] ) ) || ( ! is_array( $args['meta_data'] ) ) ) {
				return;
			}
			$meta_type = $args['meta_type'];
			$meta_data = $args['meta_data'];
			global $wpdb;
			$table = _get_meta_table( $meta_type );
			if ( ! $table ) {
				return;
			}
			$type_column = sanitize_key( $meta_type . '_id' );
			$id_column   = ( 'user' === $meta_type ) ? 'umeta_id' : 'meta_id';
			// Prepare query for bulk selection.
			$select_placeholders = array();
			$select_params       = array();
			$object_ids          = array();
			foreach ( $meta_data as $data ) {
				$object_id = ( ! empty( $data['object_id'] ) ) ? absint( $data['object_id'] ) : 0;
				$meta_key  = ( ! empty( $data['meta_key'] ) ) ? wp_unslash( $data['meta_key'] ) : '';
				if ( empty( $object_id ) || empty( $meta_key ) ) {
					continue;
				}
				$meta_value = ( ! empty( $data['meta_value'] ) ) ? maybe_serialize( wp_unslash( $data['meta_value'] ) ) : '';
				// Short-circuit filter.
				$check = apply_filters( "delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, false );
				if ( null !== $check ) {
					continue;
				}
				// Add conditions for query building.
				$select_placeholders[] = "(meta_key = %s AND $type_column = %d" . ( ( ! empty( $meta_value ) ) ? ' AND meta_value = %s' : '' ) . ')';
				$select_params[]       = $meta_key;
				$select_params[]       = $object_id;
				if ( ( ! empty( $meta_value ) ) ) {
					$select_params[] = $meta_value;
				}
			}
			if ( empty( $select_placeholders ) ) {
				return;
			}
			// Run query to fetch meta data.
			$object_id_col = $meta_type . '_id';
			$query         = 'SELECT ' . str_replace( $object_id_col, "$object_id_col AS object_id", implode( ', ', array( 'meta_id', 'meta_key', 'meta_value', $object_id_col ) ) ) . " FROM $table WHERE " . implode( ' OR ', $select_placeholders );
			$wpdb1         = $wpdb;
			$result        = $wpdb1->get_results( $wpdb1->prepare( $query, $select_params ) );
			if ( ( empty( $result ) ) || ( is_wp_error( $result ) ) ) {
				return;
			}
			// map data for post and pre actions.
			$grouped_meta_data = self::group_meta_data_to_delete( $result );
			if ( empty( $grouped_meta_data ) || ( ! is_array( $grouped_meta_data ) ) || empty( $grouped_meta_data['meta_ids_to_delete'] ) || empty( $grouped_meta_data['meta_data_for_actions'] ) ) {
				return;
			}
			$meta_ids_to_delete    = ( ! empty( $grouped_meta_data['meta_ids_to_delete'] ) ) ? $grouped_meta_data['meta_ids_to_delete'] : array();
			$meta_data_for_actions = ( ! empty( $grouped_meta_data['meta_data_for_actions'] ) ) ? $grouped_meta_data['meta_data_for_actions'] : array();
			// Fire pre actions.
			foreach ( $meta_data_for_actions as $data ) {
				if ( ( empty( $data ) ) || ( empty( $data['meta_ids'] ) ) || ( empty( $data['object_id'] ) ) || ( empty( $data['meta_key'] ) ) || ( ! isset( $data['meta_value'] ) ) ) {
					continue;
				}
				$object_ids[] = $object_id;
				do_action( "delete_{$meta_type}_meta", $data['meta_ids'], $data['object_id'], $data['meta_key'], $data['meta_value'] );
				if ( 'post' === $meta_type ) {
					do_action( 'delete_postmeta', $data['meta_ids'] );
				}
			}
			// Run delete query.
			$query  = "DELETE FROM $table WHERE $id_column IN ( " . implode( ',', array_map( 'absint', $meta_ids_to_delete ) ) . ' )';
			$wpdb1  = $wpdb;
			$result = $wpdb1->query( $query );
			if ( empty( $result ) || ( is_wp_error( $result ) ) ) {
				return;
			}
			// clear cache.
			if ( ( ! empty( $object_ids ) ) ) {
				wp_cache_delete_multiple( $object_ids, $meta_type . '_meta' );
			}
			// Fire post actions.
			foreach ( $meta_data_for_actions as $data ) {
				if ( ( empty( $data ) ) || ( empty( $data['meta_ids'] ) ) || ( empty( $data['object_id'] ) ) || ( empty( $data['meta_key'] ) ) || ( ! isset( $data['meta_value'] ) ) ) {
					continue;
				}
				do_action( "deleted_{$meta_type}_meta", $data['meta_ids'], $data['object_id'], $data['meta_key'], $data['meta_value'] );
				if ( 'post' === $meta_type ) {
					do_action( 'deleted_postmeta', $data['meta_ids'] );
				}
			}
			return true;
		}

		/**
		 * Function to get Term By ID form the array of term objects.
		 *
		 * @param array $terms  array of term objects.
		 * @param int   $term_id  ID of the terms to get.
		 *
		 * @return object|void term object on success else void.
		 */
		public static function get_term_by_id( $terms = array(), $term_id = 0 ) {
			if ( empty( $terms ) || empty( $term_id ) || ( ! is_array( $terms ) ) ) {
				return;
			}
			foreach ( $terms as $term ) {
				if ( empty( $term ) ) {
					continue;
				}
				if ( ( ! empty( $term->term_id ) ) && ( absint( $term->term_id ) === absint( $term_id ) ) ) {
					return $term;
				}
			}
		}

		/**
		 * Generates and configures a column model for a dropdown field.
		 *
		 * @param array $col_obj The column object to configure.
		 * @param array $dropdown_values (optional) An associative array of dropdown options (key => value).
		 * @return array The modified column object with dropdown configuration.
		 */
		public function generate_dropdown_col_model( $col_obj, $dropdown_values = array() ) {
			$dropdown_keys            = ( ! empty( $dropdown_values ) ) ? array_keys( $dropdown_values ) : array();
			$col_obj['defaultValue']  = ( ! empty( $dropdown_keys[0] ) ) ? $dropdown_keys[0] : '';
			$col_obj['save_state']    = true;
			$col_obj['values']        = $dropdown_values;
			$col_obj['selectOptions'] = $dropdown_values; // For inline editing.
			$col_obj['search_values'] = array();
			foreach ( $dropdown_values as $key => $value ) {
				$col_obj['search_values'][] = array(
					'key'   => $key,
					'value' => $value,
				);
			}
			$col_obj['type']         = 'dropdown';
			$col_obj['strict']       = true;
			$col_obj['allowInvalid'] = false;
			$col_obj['editor']       = 'select';
			$col_obj['renderer']     = 'selectValueRenderer';
			return $col_obj;
		}

		/**
		 * Syncs taxonomy terms across WPML translations for a post.
		 *
		 * @param int $post_id Post ID to sync terms for.
		 * @return void
		 */
		public static function sync_wpml_terms_translations( $post_id = 0 ) {
			if ( empty( absint( $post_id ) ) ) {
				return;
			}
			//Compat for WPML to sync the terms across translations.
			if ( ! class_exists( 'WPML_Term_Translation_Utils' ) ) {
				return;
			}
			global $sitepress;
			$active_languages = ( ( ! empty( $sitepress ) ) && ( is_callable( array( $sitepress, 'get_active_languages' ) ) ) ) ? $sitepress->get_active_languages() : array();
			if ( ( empty( $active_languages ) ) || ( ! is_array( $active_languages ) ) ) {
				return;
			}
			if ( ( empty( self::$term_translation_utils ) ) ) {
				self::$term_translation_utils = new WPML_Term_Translation_Utils( $sitepress );
			}
			foreach ( $active_languages as $language_code => $active_language ) {
				if ( ( empty( $language_code ) ) || ( ! is_callable( array( self::$term_translation_utils, 'sync_terms' ) ) ) ) {
					continue;
				}
				self::$term_translation_utils->sync_terms( $post_id, $language_code );
			}
		}
	}
}
