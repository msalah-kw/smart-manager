<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Product_Stock_Log' ) ) {
	/**
	 * Class that extends Smart_Manager_Task
	 */
	class Smart_Manager_Product_Stock_Log extends Smart_Manager_Task {
		/**
		 * Current dashboard name
		 *
		 * @var string
		 */
		public $dashboard_key = '';
		/**
		 * Array of field names for modifying data model key
		 *
		 * @var array
		 */
		public $key_mod_fields = array();
		/**
		 * Advanced search table types
		 *
		 * @var array
		 */
		public $advanced_search_table_types = array();
		/**
		 * posts join clause
		 *
		 * @var string
		 */
		public $posts_join = '';
		/**
		 * postmeta join clause
		 *
		 * @var string
		 */
		public $postmeta_join = '';
		/**
		 * terms join clause
		 *
		 * @var string
		 */
		public $terms_join = '';
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
			// should be kept before calling the parent class constructor.
			add_filter(
				'sm_search_table_types',
				function( $advanced_search_table_types = array() ) {
					return array( 'flat' => array(
						'sm_task_details' => 'task_id',
						'posts' => 'ID',
					),
					'meta' =>  array(
						'postmeta' => 'post_id',
					),
					'terms' => array(
						'terms' => 'term_id',
					) );
				}
			);
			parent::__construct( $dashboard_key );
			global $wpdb;
			$this->dashboard_key = 'product';
			$this->key_mod_fields = array( 'record_id', 'prev_val', 'updated_val' );
			$this->posts_join = " JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}sm_task_details.record_id)";
			$this->postmeta_join = " LEFT JOIN {$wpdb->prefix}postmeta ON ({$wpdb->prefix}sm_task_details.record_id = {$wpdb->prefix}postmeta.post_id AND {$wpdb->prefix}postmeta.meta_key = '_sku')";
			$this->terms_join = " LEFT JOIN {$wpdb->prefix}term_relationships ON ({$wpdb->prefix}sm_task_details.record_id = {$wpdb->prefix}term_relationships.object_id)";
			add_filter( 'sm_default_dashboard_model', array( &$this, 'dashboard_model' ) );
			add_filter( 'sm_data_model', array( &$this, 'data_model' ), 99, 2 );
			add_filter( 'sm_where_tasks_cond', array( &$this, 'where_cond' ) );
			add_filter( 'sm_join_tasks_cond', array( &$this, 'join_cond' ) );
			add_filter( 'sm_select_tasks_query', array( &$this, 'select_query' ) );
			add_filter( 'sm_search_query_posts_select', array( &$this, 'modify_posts_for_advanced_search_select' ), 10, 2 );
			add_filter( 'sm_search_query_posts_from', array( &$this, 'modify_posts_for_advanced_search_from' ), 10, 2 );
			add_filter( 'sm_search_query_postmeta_select', array( &$this, 'modify_postmeta_for_advanced_search_select' ), 10, 2 );
			add_filter( 'sm_search_query_postmeta_from', array( &$this, 'modify_postmeta_for_advanced_search_from' ), 11, 2 );
			add_filter( 'sm_search_query_terms_select', array( &$this, 'modify_terms_for_advanced_search_select' ), 10, 2 );
			add_filter( 'sm_search_query_terms_from', array( &$this, 'modify_terms_for_advanced_search_from' ), 10, 2 );
			add_filter( 'sm_search_query_terms_where',array( &$this,'modify_terms_for_advanced_search_where' ), 10, 2 );
		}

		/**
		 * Generate dashboard model for product stock log
		 *
		 * @param array $dashboard_model array contains the dashboard_model data.
		 * @return array $dashboard_model returns dashboard_model data
		 */
		public function dashboard_model( $dashboard_model = array() ) {
			global $wpdb;
			if ( empty( $dashboard_model ) || empty( $dashboard_model['columns'] ) ) {
				return $dashboard_model;
			}
			$task_cols = array(
				'id'   => _x( 'Task ID', 'task id', 'smart-manager-for-wp-e-commerce' ),
				'type' => _x( 'Type', 'task type', 'smart-manager-for-wp-e-commerce' ),
				'status'   => _x( 'Status', 'task status', 'smart-manager-for-wp-e-commerce' ),
				'completed_date' => _x( 'Date', 'task completed date', 'smart-manager-for-wp-e-commerce' ),
				'author' => _x( 'Author', 'name and email address of the user who created the task', 'smart-manager-for-wp-e-commerce' )
			);
			$column_model = array();
			$column_model = &$dashboard_model['columns'];
			foreach ( $column_model as $key => &$column ) {
				if ( empty( $column['src'] ) ) continue;
				$src_exploded = explode( "/", $column['src'] );
				if ( empty( $src_exploded ) ) {
					$src = $column['src'];
				}
				$src = $src_exploded[1];
				$col_table = $src_exploded[0];
				if ( sizeof( $src_exploded ) > 2 ) {
					$col_table = $src_exploded[0];
					$cond = explode( "=", $src_exploded[1] );
					if ( 2 === sizeof( $cond ) ) {
						$src = $cond[1];
					}
				}
				if ( empty( $src ) ) {
					continue;
				}
				if ( false === array_key_exists( $src, $task_cols ) ) {
					unset( $column_model[ $key ] );
					continue;
				}
				$column['name'] = $column['key'] = $task_cols[ $src ];
			}
			$product_stock_fields = array( 
				'record_id'   => _x( 'Product ID', 'product id', 'smart-manager-for-wp-e-commerce' ),
				'prev_val'    => _x( 'Old Value', 'old stock value', 'smart-manager-for-wp-e-commerce' ),
				'updated_val' => _x( 'New Value', 'New stock value', 'smart-manager-for-wp-e-commerce' )
			);
			$numeric_cols = array_merge( $product_stock_fields, array( 
				'post_parent' => _x( 'Parent ID', 'parent id', 'smart-manager-for-wp-e-commerce' )
				 ) 
			);
			$product_cols = array( 
				'_sku'           => _x( 'SKU', 'product SKU', 'smart-manager-for-wp-e-commerce' ),
				'post_title' => _x( 'Product Title', 'product title', 'smart-manager-for-wp-e-commerce' ),
				'product_type'  => _x( 'Product Type', 'product type', 'smart-manager-for-wp-e-commerce' )
			);
			$cols = array_merge( $numeric_cols, $product_cols );
            foreach ( $cols as $key => $val ) {
				$args = array(
					'table_nm' 	=> 'posts',
					'col'		=> $key,
					'type'		=> ( array_key_exists( $key, $numeric_cols ) ) ? 'numeric' : 'text',
					'editable'	=> false,
					'editor'	=> false,
					'name'      => $cols[ $key ]
				);
				if ( array_key_exists( $key, $product_stock_fields ) ) {
					$args['table_nm' ] = 'sm_task_details';
				} elseif ( '_sku' === $key ) {
					$args['table_nm' ] = 'postmeta';
					$args['is_meta']	= true;
				} elseif ( 'product_type' === $key ) {
					$args['table_nm' ] = 'terms';
					$args['type']	= 'dropdown';
					$taxonomy_terms = get_terms( array(
					    'taxonomy'   => $key,
					    'hide_empty' => false,
					) );
					$terms_val = array();
					$terms_val_search = array();
					if ( ! is_wp_error( $taxonomy_terms ) && ! empty( $taxonomy_terms ) ) {
						foreach ( $taxonomy_terms as $term_obj ) {
							if ( empty( $terms_val[ $term_obj->taxonomy ] ) ) {
								$terms_val[ $term_obj->taxonomy ] = array();
							}
							$title = ucwords( $term_obj->name );
							$terms_val[ $term_obj->taxonomy ][ $term_obj->term_id ] = $title;
							$terms_val_search[ $term_obj->taxonomy ][ $term_obj->slug ] = $title; //for advanced search
						}	
					}		
					if ( ! empty( $terms_val[ $key ] ) ) {
						if( ! empty( $terms_val_search[ $key ] ) ){
							$args['search_values'] = array();
							foreach( $terms_val_search[ $key ] as $key => $value ) {
								$args['search_values'][] = array( 'key' => $key, 'value' => $value );
							}
						}
					}
				}
				$column_model[] = $this->get_default_column_model( $args );
            }
            $dashboard_model['display_name'] = _x( 'Product Stock Log', 'product stock log dashboard', 'smart-manager-for-wp-e-commerce' );
            return $dashboard_model;
		}
		
		/**
		 * Generate data model
		 *
		 * @param array $data_model array containing the data model.
		 * @param array $data_col_params array containing column params.
		 * @return array $data_model returns data_model array
		 */
		public function data_model( $data_model = array(), $data_col_params = array() ) {
			if ( empty( $data_model ) || ( ! is_array( $data_model ) ) || empty( $data_model['items'] ) ) {
				return $data_model;
			}
			global $wpdb;
			$index = 0;
			$items = $data_model['items'];
			$product = null;
			foreach ( $items as $value ) {
				if ( ( ( ! empty( $value ) ) && is_array( $value ) ) && in_array( 'sm_task_details_record_id', array_keys( $value ) ) && ( ! empty( $value['sm_task_details_record_id'] ) ) ) {
					$product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $value['sm_task_details_record_id'] ) ) : null;
					;
				}
				if ( ! $product instanceof WC_Product ) {
					continue;
				}
				$items[ $index ]['posts_post_title'] = ( is_callable( array( $product, 'get_name' ) ) ) ? $product->get_name() : '';
				$items[ $index ]['terms_product_type'] = ( is_callable( array( $product, 'get_type' ) ) ) ? $product->get_type() : '';
				$items[ $index ]['postmeta_meta_key__sku_meta_value__sku'] = ( is_callable( array( $product, 'get_sku' ) ) ) ? $product->get_sku() : '';
				$items[ $index ]['posts_post_parent'] = ( is_callable( array( $product, 'get_parent_id' ) ) ) ? $product->get_parent_id() : 0;
				$index++;	
			}
			$data_model['items'] = ( ! empty( $items ) ) ? $items : array();
			return $data_model;
		}
		
		/**
		 * Modify where condition for fetching stock fields from task details
		 *
		 * @param string $where where condition of sm_tasks table.
		 * @return string updated where condition
		 */
		public function where_cond( $where = '' ) {
			global $wpdb;
			$where_cond = " AND {$wpdb->prefix}sm_task_details.field = 'postmeta/meta_key=_stock/meta_value=_stock'";
			return ( false === strpos( $where, $where_cond ) ) ? $where . $where_cond : $where;
		}
		
		/**
		 * Modify join condition for fetching stock fields from task details
		 *
		 * @param string $join join condition of sm_tasks table.
		 * @return string updated join condition
		 */
		public function join_cond( $join = '' ) {
			global $wpdb;
			$join_cond = " JOIN {$wpdb->prefix}sm_task_details
								ON ({$wpdb->prefix}sm_task_details.task_id = {$wpdb->prefix}sm_tasks.id)";
			if ( ! empty( $this->req_params['sort_params'] ) ) {
				if ( ! empty( $this->req_params['sort_params']['column'] ) && ! empty( $this->req_params['sort_params']['sortOrder'] ) ) {
					if ( false !== strpos( $this->req_params['sort_params']['column'], '/' ) ) {
						$col_exploded = explode( '/', $this->req_params['sort_params']['column'] );
						$table_nm = $col_exploded[0];
					}
					switch ( $table_nm ) {
						case 'posts':
							$join_cond .= $this->posts_join;
							break;
						case 'postmeta':
							$join_cond .= $this->postmeta_join;
							break;
						case 'terms':
							$join_cond .= $this->posts_join . $this->terms_join;
							break;
					}
				}
			}
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
		 * Modify advanced search select query for posts table
		 *
		 * @param string $select select query of posts table.
		 * @param array $params array of search params.
		 * @return string updated select query
		 */
		public function modify_posts_for_advanced_search_select( $select = '', $params = array() ) {
			return $this->modify_select_query_for_advanced_search( array( 
			'flag' => $params['flag'],
			'cat_flag' => $params['cat_flag'] ) );
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
			return " FROM {$wpdb->prefix}sm_task_details" . $this->posts_join;
		}

		/**
		 * Modify advanced search select query for postmeta table
		 *
		 * @param string $select select query of postmeta table.
		 * @param array $params array of search params.
		 * @return string updated select query
		 */
		public function modify_postmeta_for_advanced_search_select( $select = '', $params = array() ) {
			return $this->modify_select_query_for_advanced_search( array( 
			'flag' => $params['flag'],
			'cat_flag' => ", 0" ) );
		}

		/**
		 * Modify advanced search from clause for postmeta table
		 *
		 * @param string $from from clause of postmeta table.
		 * @param array $params array of search params.
		 * @return string updated from clause
		 */
		public function modify_postmeta_for_advanced_search_from( $from = '', $params = array() ) {
			global $wpdb;
			return " FROM {$wpdb->prefix}sm_task_details" . $this->postmeta_join;
		}

		/**
		 * Modify advanced search select query for terms table
		 *
		 * @param string $select select query.
		 * @param array $params array of search params.
		 * @return string updated select query
		 */
		public function modify_terms_for_advanced_search_select( $select = '', $params = array() ) {
			return $this->modify_select_query_for_advanced_search( array( 
			'flag' => $params['terms_search_result_flag'],
			'cat_flag' => ", 0" ) );
		}

		/**
		 * Modify advanced search select query
		 *
		 * @param array $args array of flag and cat_flag data.
		 * @return string updated select query
		 */
		public function modify_select_query_for_advanced_search( $args = array() ) {
			global $wpdb;
			return "SELECT DISTINCT {$wpdb->prefix}sm_task_details.task_id " . $args['flag'] ." ". $args['cat_flag'];
		}

		/**
		 * Modify advanced search select query for terms table
		 *
		 * @param string $from from clause of terms table.
		 * @param array $params array of search params.
		 * @return string updated from clause
		 */
		public function modify_terms_for_advanced_search_from( $from = '', $params = array() ) {
			global $wpdb;
			return " FROM {$wpdb->prefix}sm_task_details" . $this->terms_join;
		}

		/**
		 * Modify advanced search select query for terms table
		 *
		 * @param string $search_query_terms_where search query of terms table.
		 * @param array $search_params array of search params.
		 * @return string updated select query
		 */
		public function modify_terms_for_advanced_search_where( $search_query_terms_where = '', $search_params = array() ) {
			global $wpdb;
			$search_query_terms_where = "WHERE {$wpdb->prefix}term_relationships.term_taxonomy_id IN (". $search_params['result_taxonomy_ids'] .")";
			return ( ! empty( $search_params['tt_ids_to_exclude'] ) ) ? $search_query_terms_where . " AND {$wpdb->prefix}sm_task_details.record_id NOT IN ( SELECT object_id FROM {$wpdb->prefix}term_relationships WHERE term_taxonomy_id IN (". implode( ",", $search_params['tt_ids_to_exclude'] ) .") )" : $search_query_terms_where;
		}
	}
}
