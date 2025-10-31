<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Task' ) ) {
	/**
	 * Class that extends Smart_Manager_Pro_Base
	 */
	class Smart_Manager_Pro_Task extends Smart_Manager_Pro_Base {
		/**
		 * Current dashboard name
		 *
		 * @var string
		 */
		public $dashboard_key = '';
		/**
		 * Selected record ids
		 *
		 * @var array
		 */
		public $selected_ids = array();
		/**
		 * Entire task records
		 *
		 * @var boolean
		 */
		public $entire_task = false;
		/**
		 * Smart_Manager_Task object
		 *
		 * @var object
		 */
		public $task = null;
		/**
		 * Singleton class
		 *
		 * @var object
		 */
		protected static $_instance = null;
		/**
		 * Instance of the class
		 *
		 * @param string $dashboard_key Current dashboard name.
		 * @return object
		 */
		public static function instance( $dashboard_key ) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self( $dashboard_key );
			}
			return self::$_instance;
		}
		/**
		 * Constructor is called when the class is instantiated
		 *
		 * @param string $dashboard_key $dashboard_key Current dashboard name.
		 * @return void
		 */
		function __construct( $dashboard_key ) {
			add_filter(
				'sm_search_table_types',
				function( $advanced_search_table_types = array() ) {
					return array( 
						'flat' => array(
							'sm_task_details' => 'task_id',
							'posts' => 'ID',
						)
					);
				}
			);
			parent::__construct( $dashboard_key );
			self::actions();
			$this->dashboard_key = $dashboard_key;
			if ( file_exists(SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-task.php') ) {
				include_once SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-task.php';
				$this->task = new Smart_Manager_Task( $dashboard_key );
			}
			$this->store_col_model_transient_option_nm = 'sa_sm_' . $this->dashboard_key . '_tasks'; // Kept this here as it will override the base file $this->store_col_model_transient_option_nm.
			// Modify the dashboard model with additional or updated data.
			add_filter( 'sm_default_dashboard_model', array( &$this, 'modify_dashboard_model' ), 20 );
			add_filter( 'sm_join_tasks_cond', array( &$this, 'join_cond' ) );
			add_filter( 'sm_search_query_posts_select', array( &$this, 'modify_posts_for_advanced_search_select' ), 10, 2 );
			add_filter( 'sm_search_query_posts_from', array( &$this, 'modify_posts_for_advanced_search_from' ), 10, 2 );
		}
		/**
		 * Add filters for doing actions
		 *
		 * @return void
		 */
		public static function actions() {
			add_filter( 'sm_beta_background_entire_store_ids_from', __CLASS__ . '::undo_all_task_ids_from_clause' );
			add_filter( 'sm_beta_background_entire_store_ids_where', __CLASS__ . '::undo_all_task_ids_where_clause' );
			add_filter( 'sm_post_batch_update_db_updates', __CLASS__ . '::post_undo', 10, 2 );
			add_action('sa_manager_background_process_complete', __CLASS__ . '::background_process_complete' );
			add_filter(
				'sa_can_fetch_entire_ids',
				function () {
					return false;
				}
			);
		}

		public function __call( $function_name, $arguments = array() ) {

			if( empty( $this->task ) ) {
				return;
			}

			if ( ! is_callable( array( $this->task, $function_name ) ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $this->task, $function_name ), $arguments );
			} else {
				return call_user_func( array( $this->task, $function_name ) );
			}
		}
		/**
		 * Undo changes for task records
		 *
		 * @return void
		 */
		public function undo() {
			$selected_task_ids = $this->get_task_detail_ids( '_undo_task_id' );
			$get_selected_ids_and_entire_store_flag = apply_filters(
				'get_selected_ids_and_entire_store_flag',
				array(),
				$selected_task_ids
			);
			$selected_ids = ( ! empty( $get_selected_ids_and_entire_store_flag['selected_ids'] ) ) ? $get_selected_ids_and_entire_store_flag['selected_ids'] : array();
			$is_entire_store = ( ! empty( $get_selected_ids_and_entire_store_flag['entire_store'] ) ) ? $get_selected_ids_and_entire_store_flag['entire_store'] : false;
			SA_Manager_Pro_Base::send_to_background_process(
				array(
					'process_name' => _x( 'Undo Tasks', 'process name', 'smart-manager-for-wp-e-commerce' ),
					'process_key' => 'undo_tasks',
					'callback'     => array(
						'class_path' => $this->req_params['class_path'],
						'func'       => array(
							$this->req_params['class_nm'],
							'process_undo',
						),
					),
					'selected_ids' => $selected_ids,
					'entire_task' => $this->entire_task,
					'storewide_option' => $this->req_params['storewide_option'],'active_module' => $this->req_params['active_module'],
					'entire_store' => $is_entire_store,
					'dashboard_key' => $this->dashboard_key,
					'dashboard_title' => $this->dashboard_title,
					'class_path' => $this->req_params['class_path'],
					'class_nm' => $this->req_params['class_nm'],
					'backgroundProcessRunningMessage' => $this->req_params['backgroundProcessRunningMessage'],
					'SM_IS_WOO30' => $this->req_params['SM_IS_WOO30']
				)
			);
		}

		/**
		 * Processing undo for task record
		 *
		 * @param array $args contains task_details_ids, fetch.
		 * @return void
		 */
		public static function process_undo( $args = array() ) {
			if ( empty( $args )|| empty( $args['selected_ids'] ) ) {
				return;
			}
			$col_data_type = SA_Manager_Pro_Base::get_column_data_type( $args['dashboard_key'] );
			$dashboard_key = $args['dashboard_key'];
			$task_details = self::get_task_details(
				array(
					'task_details_ids' => ( ! is_array( $args['selected_ids'] ) ) ? array( $args['selected_ids'] ) : $args['selected_ids'],
					'fetch'            => 'all',
				)
			);
			$task_details_data = self::prepare_task_details_data(
				array(
					'col_data_type' => $col_data_type,
					'dashboard_key' => $dashboard_key,
					'task_details'  => $task_details
				)
			);
			if( empty( $task_details_data ) ) return;
			$args['task_details_data'] = $task_details_data;
			SA_Manager_Pro_Base::process_batch_update( $args );
		}

		/**
		 * Prepares task details data for batch updates based on column data type and dashboard key.
		 *
		 * @param array $params Parameters for processing task details, including:
		 *
		 * @return array Processed task details data for batch updating.
		*/
		public static function prepare_task_details_data( $params = array() ) {
			if ( empty( $params['dashboard_key'] ) || empty( $params['task_details'] ) ) {
				return array();
			}
			$col_data_type   = $params['col_data_type'];
			$task_details_data = array();
			foreach ($params['task_details'] as $task_detail ) {
				$task_detail['date_type'] = ( ! empty( $col_data_type[ $task_detail['type'] ] ) ) ? $col_data_type[ $task_detail['type'] ] : 'text';
				$task_detail['dashboard_key'] = $params['dashboard_key'];
				$prev_values = ( ! empty( $task_detail['value'] ) ) ? explode( ',', $task_detail['value'] ) : '';
				$task_detail = apply_filters( 'sm_process_undo_args_before_update', $task_detail );
				if ( is_callable( 'sa_manager_log' ) ) {
					sa_manager_log( 'info', _x( 'Undo process args ', 'undo process args', 'smart-manager-for-wp-e-commerce' ) . print_r( $task_detail, true ) );
				}
				if ( 'set_to' === $task_detail['operator'] && 'sm.multilist' === $task_detail['date_type'] && ( ! empty( $prev_values ) && ( count( $prev_values ) > 0 ) ) && ( ! empty( $task_detail['updated_value'] ) ) ) {
					$updated_values = explode( ',', $task_detail['updated_value'] );
					// Set 'remove_from' operator for updated values.
					foreach ( $updated_values as $updated_value ) {
						if ( ( false !== strpos( $task_detail['type'], 'terms' ) ) && ( in_array( $updated_value, $prev_values ) ) ) {
							continue;
						}
						$task_detail['value'] = $updated_value;
						$task_detail['operator'] = 'remove_from';
						if ( ! empty( $task_detail ) ) {
							$task_details_data[] = $task_detail;
						}
					}
					// Set 'add_to' operator for original values.
					foreach ( $prev_values as $value ) {
						if ( ( false !== strpos( $task_detail['type'], 'terms' ) ) && ( ! in_array( $value, $prev_values ) ) ) {
							continue;
						}
						$task_detail['value'] = $value;
						$task_detail['operator'] = 'add_to';
						if ( ! empty( $task_detail ) ) {
							$task_details_data[] = $task_detail;
						}
					}
				} elseif ( ! empty( $task_detail ) ) {
					$task_details_data[] = $task_detail;
				}
			}
			return $task_details_data;
		}

		/**
		 *  Function to update the from clause for getting entire task ids from tasks table
		 *
		 * @param string $from from string.
		 * @return string from query
		 */
		public static function undo_all_task_ids_from_clause( $from = '' ) {
			return ( empty( $from ) ) ? $from : str_replace( 'posts', 'sm_tasks', $from );
		}

		/**
		 * Function to update the where clause for getting entire task ids from tasks table
		 *
		 * @param string $where where string.
		 * @return string where query
		 */
		public static function undo_all_task_ids_where_clause( $where = '' ) {
			return ( ! empty( $where ) && ( false === strpos( $where, 'WHERE' ) ) ) ? 'WHERE 1=1 ' : str_replace( "AND post_status != 'trash'", '', $where );
		}

		/**
		 * Get task ids from tasks table based on completed and scheduled date time
		 *
		 * @param string $scheduled_datetime scheduled datetime.
		 * @return array $task_ids task ids array
		 */
		public static function get_task_ids( $scheduled_datetime = '' ) {
			if ( empty( $scheduled_datetime ) ) {
				return;
			}
			global $wpdb;
			$task_ids = $wpdb->get_col(
				"SELECT id
				FROM {$wpdb->prefix}sm_tasks
				WHERE completed_date < '" . $scheduled_datetime . "'"
			);
			return ( ! is_wp_error( $task_ids ) ) ? $task_ids : array();
		}

		/**
		 * Delete tasks
		 *
		 * @return void
		 */
		public function delete() {
			$selected_task_ids = $this->get_task_detail_ids( '_delete_task_id' );
			$get_selected_ids_and_entire_store_flag = apply_filters(
				'get_selected_ids_and_entire_store_flag',
				array(),
				$selected_task_ids
			);
			$selected_ids = ( ! empty( $get_selected_ids_and_entire_store_flag['selected_ids'] ) ) ? $get_selected_ids_and_entire_store_flag['selected_ids'] : array();
			$is_entire_store = ( ! empty( $get_selected_ids_and_entire_store_flag['entire_store'] ) ) ? $get_selected_ids_and_entire_store_flag['entire_store'] : false;
			SA_Manager_Pro_Base::send_to_background_process(
				array(
					'process_name' => _x( 'Delete Tasks', 'process name', 'smart-manager-for-wp-e-commerce' ),
					'process_key' => 'delete_tasks',
					'callback' => array(
						'class_path' => $this->req_params['class_path'],
						'func' => array(
							$this->req_params['class_nm'], 'process_delete'
						),
					),
					'selected_ids' => $selected_ids,
					'entire_task' => $this->entire_task,
					'storewide_option' => $this->req_params['storewide_option'],'active_module' => $this->req_params['active_module'],
					'entire_store' => $is_entire_store,
					'dashboard_key' => $this->dashboard_key,
					'dashboard_title' => $this->dashboard_title,
					'class_path' => $this->req_params['class_path'],
					'class_nm' => $this->req_params['class_nm'],
					'backgroundProcessRunningMessage' => $this->req_params['backgroundProcessRunningMessage'],
					'SM_IS_WOO30' => $this->req_params['SM_IS_WOO30']
				)
			);
		}

		/**
		 * Process the deletion of task details record
		 *
		 * @param array $args record id.
		 * @return boolean
		 */
		public static function process_delete( $args = array() ) {
			if ( empty( $args ) && empty( $args['selected_ids'] ) ) {
				return false;
			}
			return ( self::delete_task_details( ( ! is_array( $args['selected_ids'] ) ? array( $args['selected_ids'] ) : $args['selected_ids'] ) ) ) ? true : false;
		}

		/**
		 * Delete tasks from tasks table
		 *
		 * @param array $task_ids array of task ids.
		 * @return boolean true if number of rows deleted, or false on error
		 */
		public static function delete_tasks( $task_ids = array() ) {
			if ( empty( $task_ids ) || ( ! is_array( $task_ids ) ) ) {
				if ( is_callable( 'sa_manager_log' ) ) {
					sa_manager_log( 'error', _x( 'No task ids found for deleting tasks', 'delete tasks', 'smart-manager-for-wp-e-commerce' ) );
				}
				return false;
			}
			global $wpdb;
			return ( ! is_wp_error(
				$wpdb->query(
					"DELETE FROM {$wpdb->prefix}sm_tasks
					WHERE id IN (" . implode( ',', $task_ids ) . ')'
				)
			) ) ? true : false;
		}

		/**
		 * Delete task details from task details table
		 *
		 * @param array $task_detail_ids task detail ids.
		 * @return boolean true if number of rows deleted, or false on error
		 */
		public static function delete_task_details( $task_detail_ids = array() ) {
			if ( empty( $task_detail_ids ) && ! is_array( $task_detail_ids ) ) {
				if ( is_callable( 'sa_manager_log' ) ) {
					sa_manager_log( 'error', _x( 'No task detail ids found for deleting task details', 'delete task details', 'smart-manager-for-wp-e-commerce' ) );
				}
				return false;
			}
			global $wpdb;
				return ( ! is_wp_error(
					$wpdb->query(
						"DELETE FROM {$wpdb->prefix}sm_task_details
						WHERE id IN (" . implode( ',', $task_detail_ids ) . ')'
					)
				) ) ? true : false;
		}

		/**
		 * Schedule task deletion after x number of days
		 *
		 * @return void
		 */
		public static function schedule_task_deletion() {
			if ( ! function_exists( 'as_has_scheduled_action' ) ) {
				return;
			}
			$is_scheduled = as_has_scheduled_action( 'sm_schedule_tasks_cleanup' ) ? true : false;
			if ( ! ( ( false === $is_scheduled ) && function_exists( 'as_schedule_single_action' ) ) ) {
				return;
			}
			$task_deletion_days = intval( get_option( 'sa_sm_tasks_cleanup_interval_days' ) );
			if ( empty( $task_deletion_days ) ) {
				$task_deletion_days = intval( apply_filters( 'sa_sm_tasks_cleanup_interval_days', 90 ) );
				if ( empty( $task_deletion_days ) ) {
					return;
				}
				update_option( 'sa_sm_tasks_cleanup_interval_days', $task_deletion_days, 'no' );
			}
			$timestamp = strtotime( date('Y-m-d H:i:s', strtotime( "+".$task_deletion_days." Days" ) ) );
			if ( empty( $timestamp ) ) {
				return;
			}
			as_schedule_single_action( $timestamp, 'sm_schedule_tasks_cleanup' );
		}

		/**
		 * Delete task details after changes are undone
		 *
		 * @param boolean $delete_flag flag for delete.
		 * @param array   $params task_details_id.
		 * @return boolean
		 */
		public static function post_undo( $delete_flag = true, $params = array() ) {
			if ( empty( $params['task_details_id'] ) && ( empty( $delete_flag ) ) ) {
				return;
			}
			return ( self::delete_task_details( ( ! is_array( $params['task_details_id'] ) ? array( $params['task_details_id'] ) : $params['task_details_id'] ) ) ) ? true : false;
		}

		/**
		 * Delete tasks from tasks table and delete undo/delete option from options table after completing undo/delete action
		 *
		 * @param string $identifier identifier name - either undo or delete.
		 * @return void
		 */
		public static function background_process_complete( $identifier = '' ) {
			if ( empty( $identifier ) ) {
				return $identifier;
			}
			$failed_task_ids = array();
			$option_nm = self::get_process_option_name( $identifier );
			if ( empty( $option_nm ) ) {
				return;
			}
			$task_ids = get_option( $identifier . $option_nm );
			if ( empty( $task_ids ) ) {
				return;
			}
			$results = self::get_task_details(
				array(
					'task_ids' => $task_ids,
					'fetch'    => 'count',
				)
			);
			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {
					if ( ! empty( $result['count'] ) ) {
						$failed_task_ids[] = $result['id'];
					}
				}
			}
			$delete_task_ids = ( ! empty( $failed_task_ids ) && is_array( $failed_task_ids ) && is_array( $task_ids ) ) ? array_diff( $task_ids, $failed_task_ids ) : $task_ids;
			if ( empty( $delete_task_ids ) ) {
				return;
			}
			if ( self::delete_tasks( $delete_task_ids ) ) {
				delete_option( $identifier . $option_nm );
			}
		}

		/**
		 * Get task detail ids using selected task ids and store them in options table in case of undo and delete actions
		 *
		 * @param string $option_nm option name - either _undo_task_id or _delete_task_id.
		 * @return array $fetched_task_details_ids ids of task details
		 */
		public function get_task_detail_ids( $option_nm = '' ) {
			if ( empty( $option_nm ) ) {
				return;
			}
			$identifier = '';
			$task_ids = ( ! empty( $this->req_params['selected_ids'] ) ) ? json_decode( stripslashes( $this->req_params['selected_ids'] ), true ) : array();
			if ( ( ! empty( $this->req_params['storewide_option'] ) ) && ( 'entire_store' === $this->req_params['storewide_option'] ) && ( ! empty( $this->req_params['active_module'] ) ) ) {
				$task_ids = $this->get_entire_store_ids();
				$this->entire_task = true;
			}
			if ( is_callable( array( 'Smart_Manager_Pro_Background_Updater', 'get_identifier' ) ) ) {
				$identifier = Smart_Manager_Pro_Background_Updater::get_identifier();
			}
			if ( ! empty( $identifier ) && ( ! empty( $task_ids ) ) ) {
				update_option( $identifier . $option_nm, $task_ids, 'no' );
			}
			$task_details_ids = self::get_task_details(
				array(
					'task_ids' => $task_ids,
					'fetch' => 'ids',
				)
			);
			$fetched_task_details_ids = array();
			foreach ( $task_details_ids as $task_details_id ) {
				$fetched_task_details_ids[] = $task_details_id['task_details_id'];
			}
			return (! empty($fetched_task_details_ids) && is_array($fetched_task_details_ids)) ? json_encode($fetched_task_details_ids) : $this->req_params['selected_ids']; // ids of task details.
		}

		/**
		 * Get process option name from options table incase of undo and delete actions
		 *
		 * @param string $identifier identifier name - either undo or delete.
		 * @return string | boolean process option name if true, else false
		 */
		public static function get_process_option_name( $identifier = '' ) {
			if ( empty( $identifier ) ) {
				return;
			}
			$params = get_option( $identifier . '_params' );
			if ( empty( $params['process_name'] ) ) {
				return;
			}
			$process_names = array( 'Undo Tasks', 'Delete Tasks' );
			return ( in_array( $params['process_name'], $process_names, true ) ) ? ( ( 'Undo Tasks' === $params['process_name'] ) ? '_undo_task_id' : '_delete_task_id' ) : false;
		}

		/**
		 * Get task details
		 *
		 * @param array $params task_ids, task_details_ids, fetch.
		 * @return array task details [ids( tasks/task details ), count of id, record_id, field, prev_value, operator]
		 */
		public static function get_task_details( $params = array() ) {
			if ( empty( $params ) ) {
				return;
			}
			global $wpdb;
			$task_ids         = ( ! empty( $params['task_ids'] ) ) ? $params['task_ids'] : array();
			$task_details_ids = ( ! empty( $params['task_details_ids'] ) ) ? $params['task_details_ids'] : array();
			$fetch            = ( ! empty( $params['fetch'] ) ) ? $params['fetch'] : array();
			switch ( $params ) {
				case ( ( ! empty( $task_ids ) ) && ( ! empty( $fetch ) ) && ( 'ids' === $fetch ) ):
					return $wpdb->get_results(
						"SELECT task_id AS task_id, id AS task_details_id
						FROM {$wpdb->prefix}sm_task_details
						WHERE task_id IN (" . implode( ',', $task_ids ) . ")
						ORDER BY task_details_id DESC",
						'ARRAY_A'
					);
				case ( ( ! empty( $task_details_ids ) ) && ( ! empty( $fetch ) ) && ( 'all' === $fetch ) ):
					//fetch task details data.
					return $wpdb->get_results(
						"SELECT id AS task_details_id, record_id AS id, field AS type, prev_val AS value, action AS operator, updated_val AS updated_value
						FROM {$wpdb->prefix}sm_task_details
						WHERE id IN (" . implode( ',', $task_details_ids ) . ")
						ORDER BY task_details_id DESC",
						'ARRAY_A'
					);
				case ( ( ! empty( $task_ids ) ) && ( ! empty( $fetch ) ) && ( 'count' === $fetch ) ):
					return $wpdb->get_results(
						"SELECT task_id AS id, IFNULL( count(id), 0 ) AS count
						FROM {$wpdb->prefix}sm_task_details
						WHERE task_id IN (" . implode( ',', $task_ids ) . ')
						GROUP BY task_id',
						'ARRAY_A'
					);
			}
		}

		
		/**
		 * Modifies the dashboard model with additional or updated data.
		 *
		 * @param array $dashboard_model The current dashboard model data.
		 * @return array The modified dashboard model data.
		 */
		public function modify_dashboard_model( $dashboard_model = array() ) {
			if ( empty( $dashboard_model ) || ( ! is_array( $dashboard_model ) ) || ( empty( $dashboard_model['columns'] ) ) ){
				return $dashboard_model;
			}
			$dashboard_model['columns'][] = $this->get_default_column_model( array(
				'table_nm' => 'sm_task_details',
				'col'      => 'record_id',
				'type'     => 'numeric',
				'editable' => false,
				'editor'   => false,
				'name'     => sprintf( '%s ID', ( ! empty( $this->req_params ) && is_array( $this->req_params ) ) ? ( ( ! empty( absint( $this->req_params['is_taxonomy'] ) ) ) ? _x( 'Term', 'column name', 'smart-manager-for-wp-e-commerce' ) : ( ! empty( $this->req_params['active_module'] ) ? sm_get_post_type_singular_name( $this->req_params['active_module'] ) : _x( 'Record', 'column name', 'smart-manager-for-wp-e-commerce' ) ) ) : _x( 'Record', 'column name', 'smart-manager-for-wp-e-commerce' ) ),
				'hidden'   => true,
			) );
			return $dashboard_model;
		}
		
		/**
		 * Modify join condition for fetching stock fields from task details
		 *
		 * @param string $join join condition of sm_tasks table.
		 * @return string updated join condition
		 */
		public function join_cond( $join = '' ) {
			global $wpdb;
			$join_cond = " JOIN {$wpdb->prefix}sm_task_details ON ({$wpdb->prefix}sm_task_details.task_id = {$wpdb->prefix}sm_tasks.id)";
			return ( false === strpos( $join, $join_cond ) ) ? $join . $join_cond : $join;
		}
		
		/**
		 * Modify select condition for fetching stock fields from task details
		 *
		 * @param string $select select query of sm_tasks table.
		 * @return string updated select query
		 */
		public function select_query( $select = '' ) {
			global $wpdb;
			return "SELECT {$wpdb->prefix}sm_tasks.*, {$wpdb->prefix}sm_task_details.record_id, {$wpdb->prefix}sm_task_details.prev_val, {$wpdb->prefix}sm_task_details.updated_val";
		}

		/**
		* Modify advanced search select query
		*
		* @param array $args array of flag and cat_flag data.
		* @return string updated select query
		*/
		public function modify_select_query_for_advanced_search( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args[ 'flag' ] ) ) || ( empty( $args[ 'cat_flag' ] ) ) ) {
				return '';
			}
			global $wpdb;
			return "SELECT DISTINCT {$wpdb->prefix}sm_task_details.task_id " . $args['flag'] ." ". $args['cat_flag'];
		}

		/**
		* Modify advanced search from clause for posts table
		*
		* @param string $from from clause of posts table.
		* @param array $params array of search params.
		* @return string updated from clause
		*/
		public function modify_posts_for_advanced_search_from( $from = '', $params = array() ) {
			global $wpdb;
			return " FROM {$wpdb->prefix}sm_task_details JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}sm_task_details.record_id)";
		}

		/**
		 * Modify advanced search select query for posts table
		 *
		 * @param string $select select query of posts table.
		 * @param array $params array of search params.
		 * @return string updated select query
		 */
		public function modify_posts_for_advanced_search_select( $select = '', $params = array() ) {
			if ( ( empty( $params ) ) || ( ! is_array( $params ) ) || ( empty( $params[ 'flag' ] ) ) || ( empty( $params[ 'cat_flag' ] ) ) ) {
				return '';
			}
			return $this->modify_select_query_for_advanced_search( array( 
			'flag' => $params['flag'],
			'cat_flag' => $params['cat_flag'] ) );
		}
	}
}
