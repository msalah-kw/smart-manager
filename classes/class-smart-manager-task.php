<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Task' ) ) {
	/**
	 * Class that extends Smart_Manager_Base
	 */
	class Smart_Manager_Task extends Smart_Manager_Base {
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
		 * Singleton class
		 *
		 * @var object
		 */
		protected static $_instance = null;
		/**
		 * Advanced search table types
		 *
		 * @var array
		 */
		public $advanced_search_table_types = array();
		/**
		 * Array of field names for modifying data model key
		 *
		 * @var array
		 */
		public $key_mod_fields = array();
		/**
		 * Smart_Manager_Pro_Base object
		 *
		 * @var object
		 */
		public $pro_base = null;
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
					$advanced_search_table_types['flat'] = array_merge( array(
					'sm_tasks' => 'id'
				), ( ! empty( $advanced_search_table_types['flat'] ) ? $advanced_search_table_types['flat'] : array() ) );
					return $advanced_search_table_types;
				}
			); // should be kept before calling the parent class constructor.
			parent::__construct( $dashboard_key );
			$this->dashboard_key = $dashboard_key;

			if ( file_exists(SM_PLUGIN_DIR_PATH . '/pro/classes/class-smart-manager-pro-base.php') ) {
				include_once SM_PLUGIN_DIR_PATH . '/pro/classes/class-smart-manager-pro-base.php';
				$this->pro_base = new Smart_Manager_Pro_Base( $dashboard_key );
				$this->advanced_search_operators = ( ! empty( $this->pro_base->advance_search_operators ) ) ? $this->pro_base->advance_search_operators : $this->advanced_search_operators;
			}

			$this->store_col_model_transient_option_nm = 'sa_sm_' . $this->dashboard_key . '_tasks';
			add_filter( 'sm_default_dashboard_model', array( &$this, 'generate_dashboard_model' ) );
			add_filter( 'sm_data_model', array( &$this, 'generate_data_model' ), 10, 2 );
			add_filter(
				'sm_load_default_store_model',
				function() {
					return false;
				}
			);
			add_filter(
				'sm_beta_load_default_data_model',
				function() {
					return false;
				}
			);
			add_filter(
				'sa_sm_can_apply_dashboard_model_filter',
				function() {
					return false;
				}
			);
		}

		/**
		 * Generate dashboard model
		 *
		 * @param array $dashboard_model array contains the dashboard_model data.
		 * @return array $dashboard_model returns dashboard_model data
		 */
		public function generate_dashboard_model( $dashboard_model = array() ) {
			global $wpdb;
			$col_model = array();
			$results   = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}sm_tasks", 'ARRAY_A' );
			$num_rows  = $wpdb->num_rows;
			$enum_fields = array( 'status', 'type' );
			$display_names = array(
				'id' => __( 'ID', 'smart-manager-for-wp-e-commerce' )
			);
			if ( $num_rows > 0 ) {
				foreach ( $results as $result ) {
					$field_nm = ( ! empty( $result['Field'] ) ) ? $result['Field'] : '';
					$args = array(
						'table_nm' => 'sm_tasks',
						'col'      => $field_nm,
						'db_type'  => ( ! empty( $result['Type'] ) ) ? $result['Type'] : '',
						'editable' => false,
						'editor'   => false,
					);
					if ( 'post_type' === $field_nm ) {
						$args['type'] = 'dropdown';
						$args['search_values'][] = array( 'key' => $this->dashboard_key, 'value' => __( ucwords( str_replace( '_', ' ', $this->dashboard_key ) ), 'smart-manager-for-wp-e-commerce' ) );
					} elseif ( in_array( $field_nm, $enum_fields, true ) ) {
						$args['type'] = 'dropdown';
						$args['width'] = 100;
						if ( ! empty( $this->get_col_values( $field_nm ) ) && is_array( $this->get_col_values( $field_nm ) ) ) {
							foreach ( $this->get_col_values( $field_nm ) as $key => $value ) {
								$args['search_values'][] = array(
									'key'   => $key,
									'value' => $value,
								);
							}
						}
					} elseif ( 'actions' === $field_nm ) {
						$args['editor'] = 'sm.serialized';
					} elseif ( 'record_count' === $field_nm ) {
						$args['width'] = 100;
					}

					if( ! empty( $display_names[$field_nm] ) ){
						$args['name'] = $display_names[$field_nm];
					}

					$col_model [] = $this->get_default_column_model( $args );
				}
			}

			return array(
				'display_name'   => __( ucwords( str_replace( '_', ' ', $this->dashboard_key . '_tasks' ) ), 'smart-manager-for-wp-e-commerce' ),
				'columns'        => $col_model,
				'per_page_limit' => '', // blank, 0, -1 all values refer to infinite scroll.
				'treegrid'       => false, // flag for setting the treegrid.
			);
		}

		/**
		 * Generate data model
		 *
		 * @param array $data_model array containing the data model.
		 * @param array $data_col_params array containing column params.
		 * @return array $data_model returns data_model array
		 */
		public function generate_data_model( $data_model = array(), $data_col_params = array() ) {
			global $wpdb;
			$current_user_id     = get_current_user_id();
			$items               = array();
			$index               = 0;
			$post_type           = array( $this->dashboard_key );
			$current_user_role = ( is_callable( array( 'Smart_Manager', 'get_current_user_role' ) ) ) ? Smart_Manager::get_current_user_role() : '';
			$where = apply_filters(
				'sm_where_tasks_cond',
				' AND '. $wpdb->prefix . 'sm_tasks.post_type = %s' .
				( (!empty( $current_user_role ) && 'administrator' === $current_user_role) ? '' : ' AND author = %d')
			);
			$order_by            = apply_filters( 'sm_orderby_tasks_cond', $wpdb->prefix . 'sm_tasks.id DESC ' );
			$group_by            = apply_filters( 'sm_groupby_tasks_cond', ' ' . $wpdb->prefix . 'sm_tasks.id ' );
			$join                = apply_filters( 'sm_join_tasks_cond', '' );
			$select              = apply_filters( 'sm_select_tasks_query', "SELECT {$wpdb->prefix}sm_tasks.*" );
			$from                = apply_filters( 'sm_from_tasks_query', " FROM {$wpdb->prefix}sm_tasks" );
			$start               = ( ! empty( $this->req_params['start'] ) ) ? intval( $this->req_params['start'] ) : 0;
			$limit               = ( ! empty( $this->req_params['sm_limit'] ) ) ? intval( $this->req_params['sm_limit'] ) : 50;
			$current_page        = ( ! empty( $this->req_params['sm_page'] ) ) ? $this->req_params['sm_page'] : '1';
			$start_offset        = ( ( $current_page > 1 ) && ( ! empty( $limit ) ) ) ? intval( ( ( $current_page - 1 ) * $limit ) ) : $start;
			$current_store_model = self::get_store_model_transient();
			if ( ! empty( $current_store_model ) && ! is_array( $current_store_model ) ) {
				$current_store_model = json_decode( $current_store_model, true );
			}
			$col_model = ( ! empty( $current_store_model['columns'] ) ) ? $current_store_model['columns'] : array();
			if ( empty( $col_model ) || ! is_array( $col_model ) ) {
				return;
			}
			$search_cols_type = array(); // array for col & its type for advanced search.
			// Code to handle simple search functionality.
			$simple_search_where_cond  = array();
			$search_text = '';
			if ( ! empty( $this->req_params['search_text'] ) || ( ! empty( $this->req_params['advanced_search_query'] ) && '[]' !== $this->req_params['advanced_search_query'] ) ) {
				if ( ! empty( $this->req_params['search_text'] ) ) {
					$search_text = $wpdb->_real_escape( $this->req_params['search_text'] );
				}
				// Code for getting tasks table condition.
				foreach ( $col_model as $col ) {
					switch ( true ) {
						case ( ! empty( $this->req_params['search_text'] ) ):
							if ( empty( $col['src'] ) ) {
								break;
							}
							$src_exploded = explode( '/', $col['src'] );
							if ( ! empty( $src_exploded[0] ) && ( 'sm_tasks' === $src_exploded[0] ) ) {
								$simple_search_where_cond[] = "( {$wpdb->prefix}sm_tasks." . $src_exploded[1] . " LIKE %s )";
							}
							break;
						default:
							if ( ! empty( $col['table_name'] ) && ! empty( $col['col_name'] ) ) {
								$search_cols_type[ $col['table_name'] . '.' . $col['col_name'] ] = $col['type'];
							}
					}
				}
				if ( ! empty( $this->req_params['search_text'] ) ) {
					$where .= ( ! empty( $simple_search_where_cond ) ) ? ' AND (' . implode( ' OR ', $simple_search_where_cond ) . ' )' : '';
				}
			}
			// Code fo handling advanced search functionality.
			$sm_advanced_search_results_persistent = 0; // flag to handle persistent search results.
			if ( ! empty( $this->req_params['advanced_search_query'] ) && ( '[]' !== $this->req_params['advanced_search_query'] ) ) {
				$this->req_params['advanced_search_query'] = json_decode( stripslashes( $this->req_params['advanced_search_query'] ), true );
				if ( ! empty( $this->req_params['advanced_search_query'] ) ) {
					$this->advanced_search_operators = ( ! empty( $data_col_params['advanced_search_operators'] ) ) ? $data_col_params['advanced_search_operators'] : $this->advanced_search_operators;
					if ( ! empty( $this->req_params['table_model']['posts']['where']['post_type'] ) ) {
						$post_type = ( is_array( $this->req_params['table_model']['posts']['where']['post_type'] ) ) ? $this->req_params['table_model']['posts']['where']['post_type'] : array( $this->req_params['table_model']['posts']['where']['post_type'] );
					}
					$this->process_search_cond(
						array(
							'post_type' => $post_type,
							'search_query' => ( ! empty( $this->req_params['advanced_search_query'] ) ) ? $this->req_params['advanced_search_query'] : array(),
							'SM_IS_WOO30' => ( ! empty( $this->req_params['SM_IS_WOO30'] ) ) ? $this->req_params['SM_IS_WOO30'] : '',
							'search_cols_type' => $search_cols_type,
							'data_col_params' => $data_col_params,
						)
					);
				}
				$join .= " JOIN {$wpdb->base_prefix}sm_advanced_search_temp
								ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = {$wpdb->prefix}sm_tasks.id)";
				$where .= " AND {$wpdb->base_prefix}sm_advanced_search_temp.flag > 0";
			}
			// Code for sorting task records.
			if ( ! empty( $this->req_params['sort_params'] ) && ! empty( $data_col_params['data_cols'] ) ) {

				$sort_params = $this->build_query_sort_params( array( 'sort_params' => $this->req_params['sort_params'],
																		'data_cols' => $data_col_params['data_cols']
															) );
				if( ! empty( $sort_params ) && ! empty( $sort_params['table'] ) && ! empty( $sort_params['column_nm'] && ! empty( $sort_params['sortOrder'] ) ) ) {
					$order_by = $wpdb->prefix . $sort_params['table'] . '.' . $sort_params['column_nm'] . ' ' . $sort_params['sortOrder'] . ' ';
				}
			}
			$query_limit_str  = ( ! empty( $this->req_params['cmd'] ) && ( 'get_export_csv' === $this->req_params['cmd'] ) ) ? '' : 'LIMIT ' . $start_offset . ', ' . $limit;
			$user_id_constraint = ( ! empty( $current_user_role ) && 'administrator' === $current_user_role ) ? '' : $current_user_id;
			$base_args = array( 1, $this->dashboard_key );
			if ( ! empty( $user_id_constraint ) ) {
				$base_args[] = $user_id_constraint;
			}
			$args = ( ! empty( $this->req_params['search_text'] ) ) ? array_merge( $base_args, array_fill( 0, sizeof( $simple_search_where_cond ), '%' . $wpdb->esc_like( $search_text ) . '%' ) ) : $base_args;
			$ids              = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT {$wpdb->prefix}sm_tasks.id" . $from . $join . "
					WHERE 1 = %d" . $where,
					$args
				)
			);
			$total_count      = $wpdb->num_rows;
			// Code for saving the task ids.
			if ( ( defined( 'SMPRO' ) && true === SMPRO ) && ( ! empty( $this->req_params['search_text'] ) ) || ( ! empty( $this->req_params['advanced_search_query'] ) && '[]' === $this->req_params['advanced_search_query'] ) && ( ! empty( $ids ) ) ) {
				set_transient( 'sa_sm_search_post_ids', implode( ',', $ids ), WEEK_IN_SECONDS );
			}
			$task_results = $wpdb->get_results(
				$wpdb->prepare(
					$select . $from . $join . "
					WHERE 1=%d 
					" . $where . "
					GROUP BY" . $group_by . "
					ORDER BY " . $order_by
					. $query_limit_str,
					$args
				),
				ARRAY_A
			);
			$total_pages  = 1;
			if ( ( ! empty( $total_count ) ) && ( $total_count > $limit ) && ( 'get_export_csv' !== $this->req_params['cmd'] ) ) {
				$total_pages = ceil( $total_count / $limit );
			}
			if ( ! empty( $task_results ) ) {
				foreach ( $task_results as $tasks ) {
					foreach ( $tasks as $key => $value ) {
						if ( is_array( $data_col_params['data_cols'] ) && ! empty( $data_col_params['data_cols'] ) ) {
							if ( false === array_search( $key, $data_col_params['data_cols'] ) ) {
								continue; // cond for checking col in col model.
							}
						}
						$key_mod                  = ( ( ( ! empty( $this->key_mod_fields ) ) && is_array( $this->key_mod_fields ) && in_array( $key, $this->key_mod_fields ) ) ? 'sm_task_details_' : 'sm_tasks_' ) . strtolower( str_replace( ' ', '_', $key ) );
						$items[ $index ][ $key_mod ] = $value;
						if ( 'author' === $key ) {
							$user_info   = get_user_by( 'id', intval( $value ) );
							$items[ $index ][ $key_mod ] = ( $user_info instanceof WP_User ) ? ( ( ! empty( $user_info->display_name ) ? $user_info->display_name : '' ) . ( ! empty( $user_info->user_email ) ? ' (' . $user_info->user_email . ')' : '' ) ) : '';
						}
					}
					$index++;
				}
			}
			$data_model ['items']       = ( ! empty( $items ) ) ? $items : array();
			$data_model ['start']       = $start + $limit;
			$data_model ['page']        = $current_page;
			$data_model ['total_pages'] = ( ! empty( $total_pages ) ) ? $total_pages : 0;
			$data_model ['total_count'] = ( ! empty( $total_count ) ) ? $total_count : 0;
			return $data_model;
		}

		/**
		 * Insert task details into sm_task_details table
		 *
		 * @param array $params contains task_id, action, status, record_id, field, prev_val, updated_val.
		 * @return void
		 */
		public static function task_details_update() {
			global $wpdb;
			$params = ( ! empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) ) ? Smart_Manager_Base::$update_task_details_params : array();
			sm_task_details_update( $params );
		}

		/**
		 * Get previous data
		 *
		 * @param int    $post_id for getting previous data by passing post id.
		 * @param string $table for getting previous data by passing table name.
		 * @param string $column for getting previous data by passing column name.
		 * @return result of function call
		 */
		public static function get_previous_data( $post_id = 0, $table = '', $column = '' ) {
			if ( empty( $post_id ) || empty( $table ) || empty( $column ) ) {
				return;
			}
			switch ( $table ) {
				case 'posts':
					return get_post_field( $column, $post_id );
				case 'postmeta':
					return get_post_meta( $post_id, $column, true );
				case 'terms':
					return wp_get_object_terms( $post_id, $column, 'orderby=none&fields=ids' );
			}
		}

		/**
		 * Get store column model transient
		 *
		 * @return result of function call
		 */
		public function get_store_model_transient() {
			if ( empty( $this->store_col_model_transient_option_nm ) ) {
				return;
			}
			return get_transient( $this->store_col_model_transient_option_nm );
		}

		/**
		 * Get column values for particular column
		 *
		 * @param string $field_nm field name - column/field name
		 * @return array array of column values for particular column
		 */
		public function get_col_values( $field_nm = '' ) {
			if ( empty( $field_nm ) ) {
				return;
			}
			switch( $field_nm ) {
				case 'status':
					return array(
						'in-progress' => __( 'In Progress', 'smart-manager-for-wp-e-commerce' ),
						'completed' => __( 'Completed', 'smart-manager-for-wp-e-commerce' ),
						'scheduled' => __( 'Scheduled', 'smart-manager-for-wp-e-commerce' ),
					);
				case 'type':
					return  array(
						'inline' => __( 'Inline', 'smart-manager-for-wp-e-commerce' ),
						'bulk_edit' => __( 'Bulk Edit', 'smart-manager-for-wp-e-commerce' ),
					);
			}
		}
	}
}
