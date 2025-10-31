<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Shop_Order' ) ) {
	class Smart_Manager_Pro_Shop_Order extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
				$req_params = array(),
				$plugin_path = '';
		public static $custom_search_cols = array(
				'coupons_used'		=> 'woocommerce_order_items/coupon',
				'shipping_method'	=> 'woocommerce_order_items/shipping'
			),
			$custom_product_search_cols = array(
				'sku'	=> 'postmeta/_sku',
				'title'	=> 'posts/post_title'
			),
			$custom_product_search_key_prefix = 'sm_custom_product_';
		public static $advanced_search_option_name = 'sa_sm_search_order_product_ids',
			$hpos_tables_default_visible_cols = array(
				'wc_orders_id', 'wc_orders_date_created_gmt', 'wc_order_addresses_billing_first_name', 'wc_order_addresses_billing_last_name', 'wc_orders_billing_email', 'wc_orders_status', 'wc_orders_total_amount', 'custom_details', 'wc_orders_payment_method_title', 'shipping_method', 'coupons_used', 'custom_line_items'
			),
			$non_hpos_tables_default_visible_cols = array(
				'ID', 'post_date', '_billing_first_name', '_billing_last_name', '_billing_email', 'post_status', '_order_total', 'custom_details', '_payment_method_title', 'shipping_method', 'coupons_used', 'custom_line_items'
			);
		public $shop_order = '';

		protected static $_instance = null;

		/**
		 * Stores batch update actions for line items grouped by order ID.
		 * Format:
		 * [
		 *   order_id => [
		 *     'add_product'    => [ product_ids... ],
		 *     'remove_product' => [ product_ids... ],
		 *     'copy_product_from' => [ from_order_id ],
		 *     ...
		 *   ]
		 * ]
		 *
		 * @var array
		 */
		public static $line_items_update_data = array();

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			add_filter( 'sm_search_table_types',  __CLASS__. '::orders_search_table_types', 12, 1 ); // should be kept before calling the parent class constructor

			parent::__construct($dashboard_key);
			self::actions();

			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );

			if ( file_exists(SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-shop-order.php') ) {
				include_once SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-shop-order.php';
				$this->shop_order = new Smart_Manager_Shop_Order( $dashboard_key );
			}

			add_filter( 'sa_sm_dashboard_model', array( &$this, 'orders_dashboard_model' ), 12, 2 );
			add_filter( 'sm_search_query_formatted', array( &$this, 'order_itemmeta_search_query' ), 12, 2 );

			// Filters for modifying advanced search query clauses
			add_filter( 'sm_search_query_woocommerce_order_itemmeta_select', array( __CLASS__, 'orders_advanced_search_select' ), 12, 2 );
			add_filter( 'sm_search_query_woocommerce_order_itemmeta_from', array( __CLASS__, 'orders_advanced_search_from' ), 12, 2 );
			add_filter( 'sm_search_query_woocommerce_order_itemmeta_join', array( __CLASS__, 'orders_advanced_search_join' ), 12, 2 );
			add_filter( 'sm_search_woocommerce_order_items_cond', 'Smart_Manager_Pro_Shop_Order::orders_advanced_search_flat_table_cond', 12, 2 );

			add_action( 'sm_advanced_search_processing_complete', array( &$this, 'orders_advanced_search_post_processing' ), 12, 1 );

			if ( ! empty( Smart_Manager::$sm_is_woo79 ) ) {
				add_filter( 'sm_beta_background_entire_store_ids_query', array( $this,'get_entire_store_ids_query' ), 12, 1 );
			}
			add_filter( 'sm_modified_advanced_search_operators', array( &$this, 'modified_advanced_search_operators' ), 12, 1 );
			add_filter( 'sm_col_model_for_export', __CLASS__. '::orders_col_model_for_export', 12, 2 );
		}

		public static function actions() {
			add_filter( 'sm_batch_update_prev_value',  'Smart_Manager_Shop_Order::get_previous_value', 10, 2 );

			add_filter( 'sm_default_batch_update_db_updates',  __CLASS__. '::default_batch_update_db_updates', 10, 2 );
			add_filter( 'sm_post_batch_update_db_updates', __CLASS__. '::post_batch_update_db_updates', 10, 2 );
			add_filter( 'sm_pro_default_process_delete_records', function() { return false; } );
			add_filter( 'sm_pro_default_process_delete_records_result', 'Smart_Manager_Shop_Order::process_delete', 12, 3 );
			// Hoooks for updating line items.
			add_filter( 'sm_post_batch_process_args', array( __CLASS__, 'set_line_items_batch_update_args' ), 10, 1 );
			add_action( 'sm_pre_process_batch_db_updates', array( __CLASS__, 'process_line_items_batch_update' ) );
			add_action( 'sm_pre_process_batch_update_args', array( __CLASS__, 'pre_process_batch_update_args' ) );
		}

		public function __call( $function_name, $arguments = array() ) {

			if( empty( $this->shop_order ) ) {
				return;
			}

			if ( ! is_callable( array( $this->shop_order, $function_name ) ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $this->shop_order, $function_name ), $arguments );
			} else {
				return call_user_func( array( $this->shop_order, $function_name ) );
			}
		}

		public static function default_batch_update_db_updates( $flag = false, $args = array() ) {
			return ( 'posts' === $args['table_nm'] && 'post_status' === $args['col_nm'] ) ? false : $flag;
		}

		public static function post_batch_update_db_updates( $update_flag = false, $args = array() ) {
			if ( empty( $args ) || empty( $args['id'] ) ) return $update_flag;
			$args['order_obj'] = wc_get_order( $args['id'] );

			if ( ( in_array( $args['col_nm'], array( '_order_shipping', 'shipping_total_amount', 'shipping_method', 'shipping_method_title' ) ) ) ) {
				if ( ! empty( $args ) && ! empty( $args['operator'] ) && $args['copy_from_operators'] && in_array( $args['operator'], $args['copy_from_operators'] ) && ! empty( $args['selected_table_name'] ) && ! empty( $args['selected_column_name'] ) && ! empty( $args['selected_value'] ) && is_callable( array( 'Smart_Manager_Pro_Shop_Order', 'get_previous_shipping_details') ) ) {
					$args['value'] = self::get_previous_shipping_details( $args );
				}
				return Smart_Manager_Shop_Order::update_shipping_details(
					array(
						'id' => $args['id'],
						'value' => $args['value'],
						'update_column' => $args['col_nm'],
						'dashboard' => $args['dashboard_key']
					)
				);
			}
			if ( ( ! empty( $args['operator'] ) ) && in_array( $args['operator'], array( 'add_to', 'remove_from' ) ) && in_array( $args['col_nm'] , array( 'note_for_customer' ) ) ) {
				switch ( $args['operator'] ) {
					case 'add_to':
						$order = new WC_Order( $args['id'] );
						if ( ! $order instanceof WC_Order || ( ! is_callable( array( $order, 'add_order_note') ) ) ) {
							return $update_flag;
						}
						return $order->add_order_note( $args['value'], 1, true );
					case 'remove_from':
						if ( ! function_exists( 'wc_delete_order_note' ) ) {
							return $update_flag;
						}
						return wc_delete_order_note( $args['value'] );
				}
			}
			if ( ! empty( Smart_Manager::$sm_is_woo79 ) ) {
				if( ! empty( $args['curr_obj_getter_func'] ) && ! empty( $args['curr_obj_class_nm'] ) && function_exists( $args['curr_obj_getter_func'] ) && class_exists( $args['curr_obj_class_nm'] ) ) {
					$args['order_obj'] = call_user_func( $args['curr_obj_getter_func'], $args['id'] );
				}

				//Code for handling 'copy_from' and 'copy_from_field' action
				if( ! empty( $args ) && ! empty( $args['operator'] ) && $args['copy_from_operators'] && in_array( $args['operator'], $args['copy_from_operators'] ) && ! empty( $args['selected_table_name'] ) && ! empty( $args['selected_column_name'] ) && ! empty( $args['selected_value'] ) && is_callable( array( 'Smart_Manager_Shop_Order', 'get_previous_value') ) ) {
					$args['value'] = Smart_Manager_Shop_Order::get_previous_value(
							( ! empty( $args['prev_val'] ) ) ? $args['prev_val'] : '',
							array(
								'col_nm' => $args['selected_column_name'],
								'table_nm' => $args['selected_table_name'],
								'id' => intval( $args['selected_value'] ),
								'order_obj' => wc_get_order( intval( $args['selected_value'] ) )
							)
						);
				}
				return ( is_callable( array( 'Smart_Manager_Shop_Order', 'update_order_data') ) ) ? Smart_Manager_Shop_Order::update_order_data( array_merge( $args, array( 'update_flag' => $update_flag ) ) ) : $update_flag;
			}
			if( 'posts' === $args['table_nm'] && 'post_status' === $args['col_nm'] && ! empty( $args['value'] ) && class_exists( 'WC_Order' ) ){
				$order = new WC_Order( $args['id'] );
				return $order->update_status( $args['value'], '', true );
			}
			return $update_flag;
		}

		/**
		 * Processes the dashboard model.
		 *
		 * This method is responsible for generating or modifying the dashboard model, utilize a previously saved dashboard model and apply necessary changes or enhancements.
		 *
		 * @param array $dashboard_model        The current dashboard model data.
		 * @param array $dashboard_model_saved  The previously saved dashboard model data.
		 * @return array                       The processed dashboard model for the orders dashboard.
		 */
		public function orders_dashboard_model( $dashboard_model = array(), $dashboard_model_saved = array() ){
			if ( empty( $dashboard_model ) || ( ! is_array( $dashboard_model ) ) || empty( $this->dashboard_key ) ){
				return $dashboard_model;
			}
			return self::get_orders_dashboard_model( $dashboard_model, $dashboard_model_saved, $this->dashboard_key );
		}

		/**
		 * Function for adding custom columns for Orders dashboard
		 *
		 * @param array $dashboard_model array of dashboard model.
		 * @param array $dashboard_model_saved The saved dashboard model.
		 * @param string $dashboard_key Dashboard key, post type.
		 * 
		 * @return array $dashboard_model updated dashboard model.
		 */
		public static function get_orders_dashboard_model( $dashboard_model = array(), $dashboard_model_saved = array(), $dashboard_key = '' ){
			if ( ( empty( self::$custom_product_search_cols ) ) || ( ! is_array( self::$custom_product_search_cols ) ) || ( empty( $dashboard_key ) ) || empty( $dashboard_model ) || ( ! is_array( $dashboard_model ) ) ) {
				return $dashboard_model;
			}

			global $wpdb;

			if( empty( $dashboard_model['columns'] ) ){
				$dashboard_model['columns'] = array();
			}
			$column_model = &$dashboard_model['columns'];

			foreach( array_keys( self::$custom_product_search_cols ) as $col ){
				$col_name = self::$custom_product_search_key_prefix. '' .$col;
				$col_index = sa_multidimesional_array_search ( 'woocommerce_order_itemmeta/meta_key='. $col_name .'/meta_value='. $col_name, 'src', $column_model );

				if( ! empty( $col_index ) ) {
					continue;
				}
				$index = sizeof( $column_model );

				$column_model [$index] = array();

				$column_model [$index]['src'] = 'woocommerce_order_itemmeta/meta_key='. $col_name .'/meta_value='. $col_name;
				$column_model [$index]['data'] = sanitize_title( str_replace( '/', '_', $column_model [$index]['src'] ) ); // generate slug using the wordpress function if not given
				$column_model [$index]['name'] = __( 'Product '. ( ( 'sku' === $col ) ? 'SKU' : ucwords( str_replace('_', ' ', $col) ) ), 'smart-manager-for-wp-e-commerce' );
				$column_model [$index]['key'] = $column_model[$index]['name'];
				$column_model [$index]['type'] = 'text';
				$column_model [$index]['hidden']	= true;
				$column_model [$index]['editable']	= false;
				$column_model [$index]['batch_editable']	= false;
				$column_model [$index]['sortable']	= false;
				$column_model [$index]['resizable']	= false;
				$column_model [$index]['allow_showhide'] = false;
				$column_model [$index]['exportable']	= false;
				$column_model [$index]['searchable']	= true;
				$column_model [$index]['wordWrap'] = false; //For disabling word-wrap
				$column_model [$index]['table_name'] = $wpdb->prefix.'woocommerce_order_itemmeta';
				$column_model [$index]['col_name'] = $col_name;
				$column_model [$index]['width'] = 0;
				$column_model [$index]['save_state'] = false;
				//Code for assigning values
				$column_model [$index]['values'] = array();
				$column_model [$index]['search_values'] = array();
			}

			if ( ( ! empty( $dashboard_model_saved ) ) && ( is_array( $dashboard_model_saved ) ) ) {
				$col_model_diff = sa_array_recursive_diff( $dashboard_model_saved, $dashboard_model );
			}

			//clearing the transients before return
			if ( !empty( $col_model_diff ) ) {
				delete_transient( 'sa_sm_'.$dashboard_key );
			}
			return $dashboard_model;
		}

		/**
		 * Function for modifying search query for meta tables for advanced search.
		 *
		 * @param array $query  Optional. Existing query array to modify or extend. Default empty array.
		 * @param array $params Optional. Parameters to use for building the search query. Default empty array.
		 * @return array Modified query array with applied search conditions.
		 */
		public function order_itemmeta_search_query( $query = array(), $params = array() ){
			return self::get_order_itemmeta_search_query( array(
				'query'                     => ( ( ! empty( $query ) ) && ( is_array( $query ) ) ) ? $query : array(),
				'params'                    => ( ( ! empty( $params ) ) && ( is_array( $params ) ) ) ? $params : array(),
				'advanced_search_operators' => ( ( ! empty( $this->advanced_search_operators ) ) && ( is_array( $this->advanced_search_operators ) ) ) ? $this->advanced_search_operators : array()
			) );
		}

		/**
		 * Function for modifying search query for meta tables for advanced search.
		 *
		 * @param array $args Search arguments and parameters.
		 * @return array Modified search query.
		 */
		public static function get_order_itemmeta_search_query( $args = array() ){
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) ) {
				return array();
			}
			$query = ( ( ! empty( $args['query'] ) ) && ( is_array(  $args['query'] ) ) ) ? $args['query'] : array();
			$params = ( ( ! empty( $args['params'] ) ) && ( is_array(  $args['params'] ) ) ) ? $args['params'] : array();

			$search_string = ( ! empty( $params['search_string'] ) ) ? $params['search_string'] : array();
			$actual_selected_operator = $params['selected_search_operator'];
			$actual_search_value = $params['search_value'];

			if( empty( $search_string ) || ( ! empty( $search_string ) && empty( $search_string['table_name'] ) ) ){
				return $query;
			}

			$col = ( ! empty( $params['search_col'] ) ) ? $params['search_col'] : '';
			$searched_col_table_nm = $search_string['table_name'];

			if( empty( $col ) ||  strlen( $col ) < strlen( self::$custom_product_search_key_prefix ) || ( ! empty( $col ) &&  strlen( $col ) > strlen( self::$custom_product_search_key_prefix ) && ! in_array( substr( $col, strlen( self::$custom_product_search_key_prefix ) ), array_keys( self::$custom_product_search_cols ) ) ) ){
				return $query;
			}

			global $wpdb;

			$search_meta = ( ! empty( self::$custom_product_search_cols[ substr( $col, strlen( self::$custom_product_search_key_prefix ) ) ] ) ) ? explode( "/", self::$custom_product_search_cols[ substr( $col, strlen( self::$custom_product_search_key_prefix ) ) ] ) : array();
			if( empty( $search_meta ) ){
				return $query;
			}
			$search_table = ( ! empty( $search_meta[0] ) ) ? $search_meta[0] : '';
			$search_col = ( ! empty( $search_meta[1] ) ) ? $search_meta[1] : '';

			if( empty( $search_table ) || empty( $search_col ) ){
				return $query;
			}

			$search_val = ( ! empty( $params['search_value'] ) ) ? $params['search_value'] : '';
			$search_op = ( ! empty( $params['search_operator'] ) ) ? $params['search_operator'] : '';
			$searched_col_op = ( strpos( $search_op, ' not' ) || strpos( $search_op, 'not ' ) ) ? 'not' : '';

			$p_ids = array();

			$rule = array(
				'type' => $wpdb->prefix. '' .$search_table. '.' .$search_col,
				'operator' => $search_op,
				'value' => $search_val,
				'table_name' => $wpdb->prefix. '' .$search_table,
				'col_name' => $search_col
			);
			$selected_search_operator = ( ! empty( $rule['operator'] ) ) ? $rule['operator'] : '';
			$search_operator = ( ( is_array( $args['advanced_search_operators'] ) ) && ( ! empty( $args['advanced_search_operators'][$selected_search_operator] ) ) ) ? $args['advanced_search_operators'][$selected_search_operator] : $selected_search_operator;
			$params = array(
				'table_nm'	=> $search_table,
				'search_query' => array(
					'cond_'.$search_table => '',
					'cond_'.$search_table.'_col_name' => '',
					'cond_'.$search_table.'_col_value' => '',
					'cond_'.$search_table.'_operator' => ''
				),
				'search_params' => array(
					'search_string' => $rule,
					'search_col' => $search_col,
					'search_operator' => $search_operator,
					'search_data_type' => 'text',
					'search_value' => ( ( ( 'not like' === $search_operator ) || ( 'like' === $search_operator ) ) ? "%".$search_val."%" : $search_val ),
					'selected_search_operator' => $selected_search_operator,
					'SM_IS_WOO30' => ( ! empty( $params['SM_IS_WOO30'] ) ) ? $params['SM_IS_WOO30'] : '',
					'post_type' => array( 'product', 'product_variation')
				),
				'rule'			=> $rule,
				'skip_placeholders' =>true
			);
			if ( in_array( $actual_selected_operator, array( 'anyOf', 'notAnyOf' ) ) ) {
				$params['search_params']['selected_search_operator'] = $actual_selected_operator;
				$params['search_params']['search_value'] = $actual_search_value;
				$params['search_params']['table_nm'] = $search_table;
				$params['search_params']['is_meta_table'] = ( 'postmeta' === $search_table ) ? true : false;
				$params['search_params']['skip_placeholders'] = true;
				$meta_query = ( ( class_exists( 'Smart_Manager_Pro_Base' ) ) && ( is_callable( array( 'Smart_Manager_Pro_Base', 'modify_search_cond' ) ) ) ) ? Smart_Manager_Pro_Base::modify_search_cond( $query['cond_woocommerce_order_itemmeta'], $params['search_params'] ) : array();
			}
			// code for postmeta cols
			if( 'postmeta' === $search_table ){
				$meta_query = Smart_Manager_Base::create_meta_table_search_query( $params );
				if( empty( $meta_query ) || ( ! empty( $meta_query ) && empty( $meta_query['cond_postmeta'] ) ) ){
					return $query;
				}
				$cond = ( ! empty( $meta_query['cond_postmeta'] ) ) ? substr( $meta_query['cond_postmeta'], 0, -4 ) : '';
				if( empty( $cond ) ){
					return $query;
				}
				//Query to get the post_id of the products whose meta value matches with the one type in the search text box of the Orders Module
				$p_ids  = $wpdb->get_col( "SELECT DISTINCT(post_id) FROM {$wpdb->prefix}postmeta WHERE 1=1 AND ". $cond ); //not using wpdb->prepare as its failing if the `cond` is having `%s`
			} else if( 'posts' === $search_table ){
				$meta_query = Smart_Manager_Base::create_flat_table_search_query( $params );
				if( empty( $meta_query ) || ( ! empty( $meta_query ) && empty( $meta_query['cond_posts'] ) ) ){
					return $query;
				}
				$cond = ( ! empty( $meta_query['cond_posts'] ) ) ? substr( $meta_query['cond_posts'], 0, -4 ) : '';
				if( empty( $cond ) ){
					return $query;
				}
				//Query to get the post_id of the products whose meta value matches with the one type in the search text box of the Orders Module
				$p_ids  = $wpdb->get_col( "SELECT DISTINCT(ID) FROM {$wpdb->prefix}posts WHERE 1=1 AND ". $cond ); //not using wpdb->prepare as its failing if the `cond` is having `%s`, passing value in palce of the placeholders(%s).
			}

			if( is_wp_error( $p_ids ) || empty( $p_ids ) ) {
				return $query;
			}
			$ometa_cond = $searched_col_table_nm .".meta_value in (". implode( ",", $p_ids ) .")";
			if( count( $p_ids ) > 100 && !empty( self::$advanced_search_option_name ) ){
				update_option( self::$advanced_search_option_name, implode( ",", $p_ids ), 'no' );
				$ometa_cond = " FIND_IN_SET( ". $searched_col_table_nm .".meta_value, (SELECT option_value FROM ". $wpdb->prefix ."options WHERE option_name = '". self::$advanced_search_option_name ."') )";
			}

			$query['cond_woocommerce_order_itemmeta'] = "( ( ". $searched_col_table_nm .".meta_key = '_product_id' AND ". $ometa_cond ." )
															OR ( ". $searched_col_table_nm .".meta_key = '_variation_id' AND ". $ometa_cond ." ) )";
			return $query;
		}

		/**
		 * Function for modifying table types for advanced search.
		 *
		 * @param array $table_types array of table types.
		 * @return array $table_types updated array of table types.
		 */
		public static function orders_search_table_types( $table_types = array() ){
			$table_types['flat']['woocommerce_order_items'] =  'order_id';
			$table_types['meta']['woocommerce_order_itemmeta'] =  'order_id';
			return $table_types;
		}

		/**
		 * Function for modifying select clause for meta tables for advanced search.
		 *
		 * @param string $select The search query select clause.
		 * @param array $params The search condition params.
		 * @return string updated search query select clause.
		 */
		public static function orders_advanced_search_select( $select = '', $params = array() ){
			return str_replace( 'woocommerce_order_itemmeta.order_id', 'woocommerce_order_items.order_id', $select );
		}

		/**
		 * Function for modifying from clause for meta tables for advanced search.
		 *
		 * @param string $from The search query from clause.
		 * @param array $params The search condition params.
		 * @return string updated search query from clause.
		 */
		public static function orders_advanced_search_from( $from = '', $params = array() ){
			global $wpdb;
			return $from. '' .( ( false === strpos( $from, 'woocommerce_order_items' ) ) ? " JOIN {$wpdb->prefix}woocommerce_order_items
																					ON ({$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id)" : '' );
		}

		/**
		 * Function for modifying join clause for meta tables for advanced search.
		 *
		 * @param string $join The search query join clause.
		 * @param array $params The search condition params.
		 * @return string updated search query join clause.
		 */
		public static function orders_advanced_search_join( $join = '', $params = array() ){
			return str_replace( 'woocommerce_order_itemmeta.order_id', 'woocommerce_order_items.order_id', $join );
		}

		/**
		 * Function for modifying condition for flat tables for advanced search.
		 *
		 * @param string $cond The search condition string.
		 * @param array $params The search condition params.
		 * @return string updated search query condition.
		 */
		public static function orders_advanced_search_flat_table_cond( $cond = '', $params = array() ){
			$col = ( ! empty( $params['search_col'] ) ) ? $params['search_col'] : '';
			if( empty( $col ) || ( ! empty( $col ) && !in_array( $col, array_keys( self::$custom_search_cols ) ) ) ){
				return $cond;
			}
			$search_meta = explode( "/", self::$custom_search_cols[$col] );
			$search_table = ( ! empty( $search_meta[0] ) ) ? $search_meta[0] : '';
			$search_col = ( ! empty( $search_meta[1] ) ) ? $search_meta[1] : '';

			if( empty( $search_table ) || empty( $search_col ) || 'woocommerce_order_items' !== $search_table ){
				return $cond;
			}

			global $wpdb;

			// Handling for negation search conditions
			$updated_cond = "(". $wpdb->prefix ."woocommerce_order_items.order_item_type = '". $search_col ."' AND ". str_replace( $col, 'order_item_name', $cond ) ." )";
			if( ! empty( $updated_cond ) && !empty( $params['search_operator'] ) && 'not like' === $params['search_operator'] ){
				$o_ids = $wpdb->get_col( "SELECT DISTINCT(order_id) FROM {$wpdb->prefix}woocommerce_order_items WHERE ". str_replace( 'not like', 'like', $updated_cond ) );

				if( is_wp_error( $o_ids ) || empty( $o_ids ) ) {
					return $updated_cond;
				}

				if( count( $o_ids ) > 100 && !empty( self::$advanced_search_option_name ) ){
					update_option( self::$advanced_search_option_name, implode( ",", $o_ids ), 'no' );
					return "( NOT FIND_IN_SET( ". $wpdb->prefix ."woocommerce_order_items.order_id, (SELECT option_value FROM ". $wpdb->prefix ."options WHERE option_name = '". self::$advanced_search_option_name ."') ) )";
				}

				return "(". $wpdb->prefix ."woocommerce_order_items.order_id NOT IN (". implode( ",", $o_ids ) .") )";
			}

			return $updated_cond;
		}

		/**
		 * Function for things to be done post processing of advanced search.
		 *
		 * @return void
		 */
		public function orders_advanced_search_post_processing(){
			if( !empty( self::$advanced_search_option_name ) && !empty( get_option( self::$advanced_search_option_name ) ) ){
				delete_option( self::$advanced_search_option_name );
			}
		}

		/**
		 * AJAX handler function for copy from operator for bulk edit.
		 *
		 * @param array $args bulk edit params.
		 * @return string|array json encoded string or array of values.
		 */
		public function get_batch_update_copy_from_record_ids( $args = array() ) {
			return ( is_callable( array( 'Smart_Manager_Pro_Shop_Order', 'get_copy_from_record_ids' ) ) ) ? Smart_Manager_Pro_Shop_Order::get_copy_from_record_ids( array_merge( array( 'curr_obj' => $this, 'type' => 'shop_order', 'field_title_prefix' => 'Order' ), $args ) ) : '';
		}

		/**
		 * Function to get values for copy from operator for bulk edit.
		 *
		 * @param array $args function arguments.
		 * @return string|array json encoded string or array of values.
		 */
		public static function get_copy_from_record_ids( $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['type'] ) || empty( $args['curr_obj'] ) || empty( $args['field_title_prefix'] ) ) {
				return;
			}
			$curr_obj = $args['curr_obj'];
			if ( empty( Smart_Manager::$sm_is_woo79 ) && ( ! empty( $curr_obj->dashboard_key ) ) ) {
				$pro_base_instance = new parent( $curr_obj->dashboard_key );
				$pro_base_instance->get_batch_update_copy_from_record_ids( $args );
				return;
			}

			global $wpdb;
			$data = array();

			$is_ajax = ( isset( $args['is_ajax'] )  ) ? $args['is_ajax'] : true;
			$search_term = ( ! empty( $curr_obj->req_params['search_term'] ) ) ? $curr_obj->req_params['search_term'] : ( ( ! empty( $args['search_term'] ) ) ? $args['search_term'] : '' );
			$select = apply_filters( 'sm_batch_update_copy_from_ids_select', "SELECT id AS id, CONCAT('". $args['field_title_prefix'] ." #', id) AS title", $args );
			$search_cond = ( ! empty( $search_term ) ) ? " AND ( id LIKE '%".$search_term."%' OR status LIKE '%".$search_term."%' OR billing_email LIKE '%".$search_term."%' ) " : '';
			$search_cond_ids = ( !empty( $args['search_ids'] ) ) ? " AND id IN ( ". implode(",", $args['search_ids']) ." ) " : '';

			$query = $select . " FROM {$wpdb->prefix}wc_orders WHERE status != 'trash' ". $search_cond ." ". $search_cond_ids ." AND type = '" . $args['type'] . "'";
			$results = $wpdb->get_results( $query, 'ARRAY_A' );

			if( count( $results ) > 0 ) {
				foreach( $results as $result ) {
					$data[ $result['id'] ] = trim($result['title']);
				}
			}

			$data = apply_filters( 'sm_batch_update_copy_from_ids', $data );
			if( $is_ajax ){
				wp_send_json( $data );
			} else {
				return $data;
			}
		}

		/**
		 * Function for modifying query for getting ids in case of 'entire store' option.
		 *
		 * @param string $query query for fetching the ids when entire store option is selected.
		 * @return string updated query for fetching the ids when entire store option is selected.
		 */
		public function get_entire_store_ids_query( $query = '' ) {
			global $wpdb;
			return $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wc_orders WHERE status != 'trash' AND type = %s", 'shop_order' );
		}

		/**
		 * Function to process duplicate orders
		 *
		 * @param array $params params array
		 * @return boolean
		 */
		public static function process_duplicate_record( $params = array() ) {
			if ( empty( $params ) || ! is_array( $params ) || empty( $params['id'] ) ) {
                return false;
            }
            $original_id = intval( $params['id'] );
            $duplicate = wc_get_order( $original_id );
            if ( empty( $duplicate ) || ! $duplicate instanceof WC_Order ) {
                return false;
            }
            $duplicate->set_id( 0 );
			$duplicate_fields = [
				'customer_id',
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
				'billing_email',
				'billing_phone',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
				'shipping_country',
				'shipping_phone'
			];
			$order = wc_get_order( $original_id ); // original order.
			if ( empty( $order ) || ! $order instanceof WC_Order ) {
                return false;
            }
			foreach ( $duplicate_fields as $field ) {
			    $setter_method = "set_{$field}";
			    $getter_method = "get_{$field}";
				if ( ! is_callable( array( $duplicate, $setter_method ) ) || ! is_callable( array( $order, $getter_method ) ) ) {
					continue;
				}
			    $duplicate->$setter_method(''); // need for duplicating the addresses.
				$duplicate->$setter_method( $order->$getter_method() );
			}
			$line_items = ( is_callable( array( $order, 'get_items' ) ) ) ? $order->get_items() : array();
			if ( empty( $line_items ) || ! is_array( $line_items ) ) {
                return false;
            }
            foreach ( $line_items as $item_id => $item ) {
				if ( empty( $item ) || ! is_callable( array( $item, 'get_product' ) ) || ! is_callable( array( $item, 'get_quantity' ) ) || ! is_callable( array( $duplicate, 'add_product' ) ) ) {
					continue;
				}
                $duplicate->add_product( $item->get_product(), $item->get_quantity() );
            }
            $duplicate->save();
			if ( empty( $duplicate ) || ! $duplicate instanceof WC_Order ) {
                return false;
            }
            return true;
		}

		/**
		 * Function to get previous shipping details info.
		 *
		 * @param array $args args array
		 * @return mixed int or float or string based on column
		 */
		public static function get_previous_shipping_details( $args = array() ) {
			if ( empty( $args['value'] ) || empty( $args['selected_column_name'] ) || empty( $args['dashboard_key'] ) ) {
				return;
			}
			$subs_or_order_obj = null;
			if ( 'shop_subscription' === $args['dashboard_key'] && function_exists( 'wcs_get_subscription' ) ) {
				$subscription = wcs_get_subscription( absint( $args['value'] ) );
				if ( ! $subscription || ! is_a( $subscription, 'WC_Subscription' ) ) {
					return;
				}
				$subs_or_order_obj = $subscription;
			} else {
				$order = wc_get_order( $args['value'] );
				if ( ! $order || ! is_a( $order, 'WC_Order' ) || ! is_callable( array( $order, 'get_status' ) ) ) {
					return;
				}
				$status = $order->get_status();
				if ( empty( $status ) || ! in_array( $status, array( 'pending', 'on-hold' ) ) ) {
					return;
				}
				$subs_or_order_obj = $order;
			}
			$line_items = ( is_callable( array( $subs_or_order_obj, 'get_items' ) ) ) ? $subs_or_order_obj->get_items( 'shipping' ) : array();
			if ( empty( $line_items ) || ! is_array( $line_items ) ) {
                return;
            }
			foreach( $line_items as $item_id => $item ) {
				switch ( $args['selected_column_name'] ) {
					case 'shipping_method':
						if ( ! is_callable( array( $item, 'set_method_id' ) ) ) {
							break;
						}
						return $item->get_method_id();
					case 'shipping_method_title':
						if ( ! is_callable( array( $item, 'get_method_title' ) ) ) {
							break;
						}
						return $item->get_method_title();
					case in_array( $args['selected_column_name'], array( '_order_shipping', 'shipping_total_amount' ) ):
						if ( ! is_callable( array( $item, 'get_total' ) ) ) {
							break;
						}
						return $item->get_total();
				}
			}
		}

		/**
		 * Function to get order notes.
		 *
		 * @param array $args args array
		 * @return mixed $data
		 */
		public function get_order_notes_for_customer( $args = array() ) {
			global $wpdb;
			$data = array();
			$is_ajax = ( isset( $args['is_ajax'] )  ) ? $args['is_ajax'] : true;
			$search_term = ( ! empty( $this->req_params['search_term'] ) ) ? $this->req_params['search_term'] : ( ( ! empty( $args['search_term'] ) ) ? $args['search_term'] : '' );
			$field =  ( ! empty( $this->req_params['field'] ) ) ? explode( "/", $this->req_params['field'] ) : array();
			$search_cond = '';
			$prepare_values = ['order_note'];
			if ( ! empty( $field ) && is_array( $field ) && ( ! empty( $field[1] ) ) && ( 'note_for_customer' === $field[1] ) ) {
				$search_cond .= " AND cmm.meta_key = %s AND cmm.meta_value = %d";
				$prepare_values[] = 'is_customer_note'; // meta_key value
				$prepare_values[] = 1; // meta_value
			}
			if ( ! empty( $search_term ) ) {
				$search_cond .= " AND cm.comment_content LIKE %s";
				$prepare_values[] = '%' . $wpdb->esc_like( $search_term ) . '%';
			}
			$order_notes = $wpdb->get_results( $wpdb->prepare( "SELECT cm.comment_content AS order_note, cm.comment_post_ID as order_id, cm.comment_ID as comment_id
							                                        FROM {$wpdb->prefix}comments AS cm
							                                        JOIN {$wpdb->prefix}commentmeta AS cmm
							                                        ON (cmm.comment_id = cm.comment_ID)
							                                        WHERE cm.comment_type = %s" . $search_cond, ...$prepare_values ), 'ARRAY_A'
						);
			if ( ! empty( $order_notes ) && is_array( $order_notes ) ) {
				foreach ( $order_notes as $order_note ) {
					if ( empty( $order_note['comment_id'] || empty( $order_note['order_note'] ) || empty( $order_note['order_id'] ) ) ) {
						continue;
					}
					$data[ $order_note['comment_id'] ] = $order_note['order_note'] . ' #' . $order_note['order_id'];
				}
			}
			if ( $is_ajax ) {
				wp_send_json( $data );
			} else {
				return $data;
			}
		}

		/**
		 * Modifies the advanced search operators.
		 *
		 * @param array $advanced_search_operators An array of existing advanced search operators.
		 * @return array Modified array of advanced search operators.
		 */
		public function modified_advanced_search_operators( $advanced_search_operators = array() ) {
			return ( defined('SMPRO') && ! empty( SMPRO ) ) ? array_merge( $this->advanced_search_operators, $advanced_search_operators ) : $advanced_search_operators;
		}

		/**
		 * Filters the column model for scheduled order exports.
		 *
		 * This function ensures that only the default visible columns (based on HPOS or non-HPOS tables)
		 * are included in the export when it is a scheduled export. It hides any columns not part of the
		 * default visible set by removing them from the model.
		 *
		 * @param array $col_model Array of column model data to be filtered.
		 * @param array $params Parameters related to export.
		 *
		 * @return array Filtered column model with only default visible columns for export.
		 */
		public static function orders_col_model_for_export( $col_model = array(), $params = array() ) {
			if ( ( empty( $params ) ) || ( ! is_array( $params ) ) || ( empty( $params['is_scheduled_export'] ) ) || ( 'true' !== $params['is_scheduled_export'] ) || ( empty( $col_model ) ) || ( ! is_array( $col_model ) ) || ! class_exists( 'Smart_Manager' ) ) {
				return $col_model;
			}
			$cols = ( ( ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) && ( ! empty( self::$hpos_tables_default_visible_cols ) ) ) ? self::$hpos_tables_default_visible_cols : ( ( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) && ( ! empty( self::$non_hpos_tables_default_visible_cols ) ) ? self::$non_hpos_tables_default_visible_cols : array() );
			if ( empty( $cols ) || ! is_array( $cols ) ) {
				return $col_model;
			}
			foreach ( $col_model as $key => &$column ) {
				if ( ( empty( $column ) ) || ( ! is_array( $column ) ) || ( empty( $column['data'] ) ) ) {
					continue;
				}
				if ( ( true === in_array( $column['data'], $cols, true ) ) || ( ! empty( $column['col_name'] ) && true === in_array( $column['col_name'], $cols, true ) ) ) {
					$column['hidden'] = false;
					continue;
				}
				unset( $col_model[ $key ] );
			}
			return $col_model;
		}

		/**
		 * Set batch update data for line items by order ID and operation.
		 * Handles actions like add/remove/copy product or coupon.
		 *
		 * @param array $args array of data to be updated.
		 * @return array
		 */
		public static function set_line_items_batch_update_args( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) ) {
				return array();
			}
			if ( ( empty( $args['col_nm'] ) ) || ( 'line_items' !== $args['col_nm'] ) || ( empty( $args['value'] ) ) || ( empty( $args['table_nm'] ) ) || ( 'custom' !== $args['table_nm'] ) || ( ! in_array( $args['operator'], array( 'add_product', 'remove_product', 'copy_product_from', 'add_coupon', 'remove_coupon', 'copy_coupon_from', 'copy_from' ), true ) ) ) {
				return $args;
			}
			if ( empty( self::$line_items_update_data[ $args['id'] ] ) ) {
				self::$line_items_update_data[ $args['id'] ] = array();
			}
			if ( empty( self::$line_items_update_data[ $args['id'] ][ $args['operator'] ] ) ) {
				self::$line_items_update_data[ $args['id'] ][ $args['operator'] ] = array();
			}
			self::$line_items_update_data[ $args['id'] ][ $args['operator'] ][] = absint( $args['value'] );
			return array();
		}

		/**
		 * Processes batch updates on order line items.
		 *
		 * @return void
		 */
		public static function process_line_items_batch_update() {
			if ( ( empty( self::$line_items_update_data ) ) || ( ! is_array( self::$line_items_update_data ) ) ) {
				return;
			}
			$product_objs = array();
			$cached_coupon_codes = array();
			$order_line_items_to_remove = self::get_line_item_ids_by_order_ids( array_keys(
				array_filter(
					self::$line_items_update_data,
					function ( $actions ) {
						return ( ( ! empty( $actions ) ) && is_array( $actions ) && array_key_exists( 'remove_product', $actions ) && ! empty( $actions['remove_product'] ) ); //get order ids for all the orders that have remove_product action, in case of bulk edit it all ids in array will have remove_product action.
					}
				)
			));
			$copy_products_source_order = null;
			$copy_all_line_items_source_order = null;
			$copy_coupons_source_order = null;
			$source_order_coupons = array();
			foreach ( self::$line_items_update_data as $order_id => $args ) {
				$order_id = absint( $order_id );
				if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $order_id ) ) ) {
					continue;
				}
				//Add/Remove line items.
				foreach ( array( 'add_product', 'remove_product' ) as $key ) {
					if ( ( empty( $args[ $key ] ) ) || ( ! is_array( $args[ $key ] ) ) ) {
						continue;
					}
					$order = wc_get_order( $order_id );
					if ( empty( $order ) || ( ! is_callable( array( $order, 'is_editable' ) ) ) || ( empty( $order->is_editable() ) ) ) {
						continue;
					}
					switch ($key) {
						//Add line items.
						case 'add_product':
							$order_note = array();
							foreach ( $args['add_product'] as $product_id ) {
								$product_id = absint( $product_id );
								if ( ( empty( $product_id ) ) || ( ! is_array( $product_objs ) ) ) {
									continue;
								}
								//store product object for future referance.
								if ( ( empty( $product_objs[ $product_id ] ) ) ) {
									$product_objs[ $product_id ] = wc_get_product( absint( $product_id ) );
								}
								if( ( empty( $product_objs ) ) || ( ! is_array( $product_objs ) ) || empty( $product_objs[ $product_id ] ) ){
									continue;
								}
								// Add product to the new order.
								$new_item_data = self::add_product_line_item_to_order( $product_objs[ $product_id ], 1, $order );
								if ( ( empty( $new_item_data ) ) || ( empty( $new_item_data['item_id'] ) ) ) {
									continue;
								}
								if ( ! empty( $new_item_data['order_note'] ) ) {
									$order_note[] = $new_item_data['order_note'];
								}
							}
							if ( ! empty( $order_note ) ) {
								self::save_order( $order, sprintf(
									/* translators: %s: list of item names */
									_x( 'Added line items: %s by Smart Manager', 'order note', 'smart-manager-for-wp-e-commerce' ),
									implode( ', ', $order_note )
									)
								);
							}
							break;
						//Remove line items.
						case 'remove_product':
							if ( ( ! empty( $order_line_items_to_remove ) ) && ( is_array( $order_line_items_to_remove ) ) && ( ! empty( $order_line_items_to_remove[ $order_id ] ) ) && ( is_array( $order_line_items_to_remove[ $order_id ] ) ) ) {
								foreach ( $order_line_items_to_remove[ $order_id ] as $item_id ) {
									$item_id = absint( $item_id );
									if ( empty( $item_id ) || ! is_callable( array( $order, 'get_item' ) ) ) {
										continue;
									}
									$item = $order->get_item( $item_id );
									//Check if items exists before removing.
									if ( empty( $item ) || ! is_object( $item ) || ! is_callable( array( $item, 'get_product_id' ) ) || ! is_callable( array( $item, 'get_variation_id' ) ) )  {
										continue;
									}
									if ( ( ! in_array( $item->get_product_id(), $args['remove_product'], true ) ) && ( ! in_array( $item->get_variation_id(), $args['remove_product'], true ) ) ) {
										continue;
									}
									// Before deleting the item, adjust any stock values already reduced.
									$stock_note = wc_maybe_adjust_line_item_product_stock( $item, 0 );
									$order->add_order_note( ( ! empty( $stock_note ) && ! is_wp_error( $stock_note ) ) ? sprintf( /* translators: 1: item name, 2: stock adjustment from -> to */ _x( 'Deleted "%1$s" and adjusted stock (%2$s) by Smart Manager', 'order note', 'smart-manager-for-wp-e-commerce' ), $item->get_name(), $stock_note['from'] . '&rarr;' . $stock_note['to'] ) : sprintf( /* translators: %s: item name */ _x( 'Deleted "%s" by Smart Manager', 'order note', 'smart-manager-for-wp-e-commerce' ), $item->get_name() ), false, true );

									wc_delete_order_item( $item_id );
								}
								self::save_order( $order );
							}
							break;
						default:
							break;
					}
				}
				//Copy product line items.
				if ( ( ! empty( $args['copy_product_from'] ) ) && ( is_array( $args['copy_product_from'] ) ) ) {
					$copy_products_source_order = ( empty( $copy_products_source_order ) || ! is_object( $copy_products_source_order ) ) ? wc_get_order( absint( $args['copy_product_from'][0] ) ) : $copy_products_source_order;
					if ( ( ! empty( $copy_products_source_order ) ) && ( is_a( $copy_products_source_order, 'WC_Order' ) ) ) {
						self::copy_order_line_items( $copy_products_source_order, wc_get_order( $order_id ), array( 'line_item' ) );
					}
				}
				//Copy all line items.
				if ( ( ! empty( $args['copy_from'] ) ) && ( is_array( $args['copy_from'] ) ) ) {
					$copy_all_line_items_source_order = ( empty( $copy_all_line_items_source_order ) || ! is_object( $copy_all_line_items_source_order ) ) ? wc_get_order( absint( $args['copy_from'][0] ) ) : $copy_all_line_items_source_order;
					if ( ( ! empty( $copy_all_line_items_source_order ) ) && ( is_a( $copy_all_line_items_source_order, 'WC_Order' ) ) ) {
						self::copy_order_line_items( $copy_all_line_items_source_order, wc_get_order( $order_id ), array( 'line_item', 'shipping', 'fee' ) );
					}
				}
				//Copy coupons.
				if ( ( ! empty( $args['copy_coupon_from'] ) ) && ( is_array( $args['copy_coupon_from'] ) ) ) {
					$copy_coupons_source_order = ( empty( $copy_coupons_source_order ) || ! is_object( $copy_coupons_source_order ) ) ? wc_get_order( absint( $args['copy_coupon_from'][0] ) ) : $copy_coupons_source_order;
					if ( ( ! empty( $copy_coupons_source_order ) ) && ( is_a( $copy_coupons_source_order, 'WC_Order' ) ) ) {
						//Get coupons form source order.
						if ( ( empty( $source_order_coupons ) ) && ( is_callable( array( $copy_coupons_source_order, 'get_items' ) ) ) ) {
							foreach ( $copy_coupons_source_order->get_items( 'coupon' ) as $item_id => $coupon_item ) {
								$coupon_code = ( is_callable( array( $coupon_item, 'get_code' ) ) ) ? $coupon_item->get_code() : '';
								if ( empty( $coupon_code ) ) {
									continue;
								}
								$source_order_coupons[] = $coupon_code;
							}
						}
						//Add coupons.
						if ( ( ! empty( $source_order_coupons ) ) && ( is_array( $source_order_coupons ) ) ) {
							self::handle_order_coupons(
								array(
									'order'        => wc_get_order( $order_id ),
									'coupon_codes' => $source_order_coupons,
									'action'       => 'add',
								)
							);
						}
					}
				}
				// Add or remove coupon.
				foreach ( array( 'add' => 'add_coupon', 'remove' => 'remove_coupon' ) as $action => $key ) {
					if ( ( empty( $args[ $key ] ) ) || ( ! is_array( $args[ $key ] ) ) ) {
						continue;
					}
					$coupons_fetched = self::get_coupon_codes_by_ids( array_diff( $args[ $key ], array_keys( $cached_coupon_codes ) ) );
					$cached_coupon_codes += ( ( ! empty( $coupons_fetched ) ) && ( is_array( $coupons_fetched ) ) ) ? $coupons_fetched : array();
					self::handle_order_coupons(
						array(
							'order'        => wc_get_order( $order_id ),
							'coupon_codes' => array_values( array_intersect_key( $cached_coupon_codes, array_flip( $args[ $key ] ) ) ),
							'action'       => $action,
						)
					);
				}
			}
			self::$line_items_update_data = array(); //Reset variable when updates are done.
		}

		/**
		 * Applies or removes coupons from an order using coupon IDs or codes.
		 *
		 * @param array $args {
		 *     @type WC_Order order        Required. Order object.
		 *     @type array    coupon_ids   Optional. Array of coupon post IDs.
		 *     @type array    coupon_codes Optional. Array of coupon code strings.
		 *     @type string   action       Required. 'add' or 'remove'.
		 * }
		 *
		 * @return void
		 */
		public static function handle_order_coupons( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['order'] ) ) || ( ! is_a( $args['order'], 'WC_Order' ) ) || ( empty( $args['action'] ) ) || ( ! in_array( $args['action'], array( 'add', 'remove' ), true ) ) || ( empty( $args['coupon_codes'] ) ) || ( ! is_array( $args['coupon_codes'] ) ) || ( ! is_callable( array( $args['order'], 'is_editable' ) ) ) || ( empty( $args['order']->is_editable() ) )) {
				return;
			}
			//Add/Remove coupons.
			foreach ( $args['coupon_codes'] as $code ) {
				$code = wc_format_coupon_code( wp_unslash( $code ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( empty( $code ) ) {
					continue;
				}
				$result = ( 'add' === $args['action'] && is_callable( array( $args['order'], 'apply_coupon' ) ) ) ? $args['order']->apply_coupon( $code ) : ( is_callable( array( $args['order'], 'remove_coupon' ) ) ? $args['order']->remove_coupon( $code ) : false );
				if ( empty( $result ) ) {
					continue;
				}
				$args['order']->add_order_note(
					esc_html( sprintf( /* translators: %s: Coupon name */
						( 'add' === $args['action'] ) ? _x( 'Coupon applied: "%s", by Smart Manager', 'order note', 'smart-manager-for-wp-e-commerce' ) : _x( 'Coupon removed: "%s", by Smart Manager', 'order note', 'smart-manager-for-wp-e-commerce' ),
						$code
					) ),
					false,
					true
				);
			}
			self::save_order( $args['order'] );
		}

		/**
		 * Get line item IDs for specific WC orders using raw query.
		 *
		 * @param array $order_ids Array of WC order IDs.
		 * @return array Array of line item IDs.
		 */
		public static function get_line_item_ids_by_order_ids( $order_ids = array() ) {
			if ( ( empty( $order_ids ) ) || ( ! is_array( $order_ids ) ) ) {
				return;
			}
			$order_ids = array_map( 'absint', $order_ids );
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

			$results = $wpdb->get_results( $wpdb->prepare( "SELECT order_id, order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id IN ($placeholders) AND order_item_type = %s", ...array_merge( $order_ids, array( 'line_item' ) ) ), ARRAY_A );
			if( ( empty( $results ) ) || ( is_wp_error( $results ) ) ) {
				return;
			}
			$line_items = array();
			foreach ( $results as $row ) {
				if ( ( empty( $row['order_id'] ) ) || ( empty( $row['order_item_id'] ) ) ) {
					continue;
				}
				$line_items[ $row['order_id'] ][] = absint( $row['order_item_id'] );
			}
			return $line_items;
		}

		/**
		 * Copy line items from one WooCommerce order to another.
		 *
		 * @param WC_Order|null $from_order      Source order object.
		 * @param WC_Order|null $to_order        Target order object.
		 * @param array         $line_item_types Line item types to copy (default: line_item).
		 */
		public static function copy_order_line_items( $from_order = null, $to_order = null, $line_item_types = array( 'line_item' ) ) {
			// Validate input orders.
			if ( ( empty( $from_order ) ) || ( empty( $to_order ) ) || ( ! is_object( $from_order ) ) || ( ! is_object( $to_order ) ) || ( ! is_callable( array( $from_order, 'get_items' ) ) ) || ( ! is_callable( array( $to_order, 'is_editable' ) ) ) || ( empty( $to_order->is_editable() ) ) || ( empty( $line_item_types ) ) || ( ! is_array( $line_item_types ) ) ) {
				return;
			}

			$order_note = array();

			foreach ( $line_item_types as $item_type ) {
				if ( empty( $item_type ) ) {
					continue;
				}
				foreach ( $from_order->get_items( $item_type ) as $item ) {
					if ( ( empty( $item ) ) || ( ! is_object( $item ) ) ) {
						continue;
					}
					$new_item_data = array();
					switch ( $item_type ) {
						//copy products.
						case 'line_item':
							if ( ( is_callable( array( $item, 'get_product' ) ) ) && ( is_callable( array( $item, 'get_quantity' ) ) ) ) {
								$new_item_data = self::add_product_line_item_to_order( $item->get_product(), absint( $item->get_quantity() ), $to_order );
								if ( ( is_array( $new_item_data ) ) && ! empty( $new_item_data['order_note'] ) ) {
									$order_note[] = $new_item_data['order_note'];
								}
							}
						break;
						//Copy shipping.
						case 'shipping':
							$shipping = new WC_Order_Item_Shipping();
							// Set values with ternary + is_callable check
							( is_callable( array( $item, 'get_method_title' ) ) && is_callable( array( $shipping, 'set_method_title' ) ) ) ? $shipping->set_method_title( $item->get_method_title() ) : '';
							( is_callable( array( $item, 'get_method_id' ) ) && is_callable( array( $shipping, 'set_method_id' ) ) )       ? $shipping->set_method_id( $item->get_method_id() )       : 0;
							( is_callable( array( $item, 'get_total' ) ) && is_callable( array( $shipping, 'set_total' ) ) )               ? $shipping->set_total( $item->get_total() )               : 0;
							( is_callable( array( $item, 'get_taxes' ) ) && is_callable( array( $shipping, 'set_taxes' ) ) )               ? $shipping->set_taxes( $item->get_taxes() )               : 0;
							$to_order->add_item( $shipping );
							// Add order note if item_id is valid and method title is available
							if ( ( is_callable( array( $shipping, 'get_method_title' ) ) ) && ( ! empty( $shipping->get_method_title() ) ) ) {
								$order_note[] = sprintf(
									/* translators: %s: shipping method */
									_x( 'Shipping method: %s', 'order note', 'smart-manager-for-wp-e-commerce' ),
									$item->get_method_title()
								);
							}
						break;
						//Copy fee.
						case 'fee':
							$fee = new WC_Order_Item_Fee();
							// Set fee properties using ternary and is_callable checks
							( is_callable( array( $item, 'get_name' ) ) && is_callable( array( $fee, 'set_name' ) ) )             ? $fee->set_name( $item->get_name() )             : '';
							( is_callable( array( $item, 'get_total' ) ) && is_callable( array( $fee, 'set_total' ) ) )           ? $fee->set_total( $item->get_total() )           : 0;
							( is_callable( array( $item, 'get_tax_class' ) ) && is_callable( array( $fee, 'set_tax_class' ) ) )   ? $fee->set_tax_class( $item->get_tax_class() )   : '';
							( is_callable( array( $item, 'get_tax_status' ) ) && is_callable( array( $fee, 'set_tax_status' ) ) ) ? $fee->set_tax_status( $item->get_tax_status() ) : '';
							( is_callable( array( $item, 'get_taxes' ) ) && is_callable( array( $fee, 'set_taxes' ) ) )           ? $fee->set_taxes( $item->get_taxes() )           : 0;
							$to_order->add_item( $fee );
							if ( ( is_callable( array( $fee, 'get_name' ) ) ) && ( ! empty( $fee->get_name() ) ) ) {
								$order_note[] = sprintf(
									/* translators: %s: fee name */
									_x( 'Fee: %s', 'order note', 'smart-manager-for-wp-e-commerce' ),
									$item->get_name()
								);
							}
						break;
						default:
							break;
					}
					// Copy metadata if applicable.
					if ( ! empty( $new_item_data['item_id'] ) && is_callable( array( $item, 'get_meta_data' ) ) ) {
						foreach ( $item->get_meta_data() as $meta ) {
							if ( empty( $meta ) || ! is_object( $meta ) || empty( $meta->key ) || ! isset( $meta->value ) ) {
								continue;
							}
							wc_add_order_item_meta( absint( $new_item_data['item_id'] ), $meta->key, $meta->value );
						}
					}
				}
			}
			//Save the order.
			if ( ! empty( $order_note ) ) {
				self::save_order(
					$to_order,
					sprintf(
						/* translators: %1$s: list of item names %2$d: from order id */
						_x( 'Copied items: %1$s from order #%2$d, by Smart Manager', 'order note', 'smart-manager-for-wp-e-commerce' ),
						implode( ', ', $order_note ), ( is_callable( array( $from_order, 'get_id' ) ) ) ? absint( $from_order->get_id() ) : ''
					)
				);
			}
		}

		/**
		 * Add a product line item to the given WooCommerce order.
		 *
		 * @param WC_Product|null $product  The WooCommerce product object to add.
		 * @param int             $quantity The quantity of the product to add.
		 * @param WC_Order|null   $order    The WooCommerce order object to which the product will be added.
		 *
		 * @return array {
		 *     Associative array of new item data.
		 *
		 *     @type int    $item_id    The new order item ID.
		 *     @type string $order_note A formatted note describing the added item.
		 * }
		 */
		public static function add_product_line_item_to_order( $product = null, $quantity = 0, $order = null ) {
			$quantity = absint( $quantity );
			if ( ( empty( $product ) ) || ( empty( $quantity ) ) || ( ! is_object( $product ) ) || ( empty( $order ) ) || ( ! is_object( $order ) ) || ( ! is_callable( array( $order, 'add_product' ) ) ) ) {
				return;
			}
			// Add product to the new order.
			$new_item_id = absint( $order->add_product( $product, $quantity, array( 'order' => $order ) ) );
			return ( empty( $new_item_id ) ? false : array(
				'item_id'    => $new_item_id,
				'order_note' => sprintf(
					/* translators: 1: product name, 2: quantity */
					_x( '%1$s (×%2$d)', 'order note', 'smart-manager-for-wp-e-commerce' ),
					is_callable( array( $product, 'get_formatted_name' ) ) ? $product->get_formatted_name() : '',
					$quantity
				)
			) );
		}

		/**
		 * Save order with order note and recalculate totals.
		 *
		 * @param WC_Order|null $order WooCommerce order object.
		 * @param string $order_note Order note.
		 *
		 * @return void
		 */
		public static function save_order( $order = null, $order_note = '' ) {
			if ( ( empty( $order ) ) || ( ! is_object( $order ) ) || ( ! is_callable( array( $order, 'add_order_note' ) ) ) || ( ! is_callable( array( $order, 'calculate_totals' ) ) ) || ( ! is_callable( array( $order, 'save' ) ) ) ) {
				return;
			}
			if ( ! empty( $order_note ) ) {
				$order->add_order_note( $order_note, false, true );
			}
			$order->calculate_totals( true );
			$order->save();
		}

		/**
		 * Get coupon codes by post IDs using raw SQL.
		 *
		 * @param array $coupon_ids Array of coupon post IDs.
		 * @return array|void Array in format [ 'id' => 'code' ], void if validation fails or no results.
		 */
		public static function get_coupon_codes_by_ids( $coupon_ids = array() ) {
			global $wpdb;
			if ( empty( $coupon_ids ) || ! is_array( $coupon_ids ) ) {
				return;
			}
			$placeholders = implode( ',', array_fill( 0, count( $coupon_ids ), '%d' ) );
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_type = %s",
					...array_merge( array_map( 'absint', $coupon_ids ), array( 'shop_coupon' ) )
				),
				OBJECT_K
			);
			return ( ( empty( $results ) ) || ( is_wp_error( $results ) ) ) ? false : wp_list_pluck( $results, 'post_title', 'ID' );
		}

		/**
		 * Initializes or resets the line items update data before preparing batch update args.
		 *
		 * @return void
		 */
		public static function pre_process_batch_update_args() {
			self::$line_items_update_data = array();
		}
	}
}
