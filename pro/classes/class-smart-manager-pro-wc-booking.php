<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Wc_Booking' ) ) {
	class Smart_Manager_Pro_Wc_Booking extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
				$req_params = array(),
				$plugin_path = '';

		protected static $_instance = null;

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			
			parent::__construct($dashboard_key);

			$this->dashboard_key = $dashboard_key;
			$this->post_type = $dashboard_key;
			$this->req_params = (!empty($_REQUEST)) ? $_REQUEST : array();

			add_filter( 'sa_sm_dashboard_model',array( &$this,'bookings_dashboard_model' ), 10, 2 );
			add_filter( 'sm_data_model', array( &$this, 'bookings_data_model' ), 10, 2 );
			add_filter('sm_inline_update_pre',array(&$this,'bookings_inline_update_pre'),10,1);
		}

		public static function actions() {

		}

		//Function to override the dashboard model
		public function bookings_dashboard_model ($dashboard_model, $dashboard_model_saved) {
			global $wpdb, $current_user;

			$post_status_col_index = $bookable_product_col_index = '';

			$visible_columns = array( 'ID', 'post_date', 'post_status', '_booking_product_id', '_booking_customer_id', '_booking_start', '_booking_end', '_booking_cost' );
			$numeric_columns = array( '_booking_cost', '_booking_resource_id', '_booking_parent_id' );
			$datetime_columns = array( '_booking_start', '_booking_end' );
			$checkbox_empty_one_columns = array( '_booking_all_day' );



			if( !empty( $dashboard_model['columns'] ) ) {

				$column_model = &$dashboard_model['columns'];

				foreach( $column_model as $key => &$column ) {
					
					if (empty($column['src'])) continue;

					$src_exploded = explode("/",$column['src']);

					if (empty($src_exploded)) {
						$src = $column['src'];
					}

					if ( sizeof($src_exploded) > 2) {
						$col_table = $src_exploded[0];
						$cond = explode("=",$src_exploded[1]);

						if (sizeof($cond) == 2) {
							$src = $cond[1];
						}
					} else {
						$src = $src_exploded[1];
						$col_table = $src_exploded[0];
					}


					if( empty($dashboard_model_saved) ) {
						if (!empty($column['position'])) {
							unset($column['position']);
						}

						$position = array_search($src, $visible_columns);

						if ($position !== false) {
							$column['position'] = $position + 1;
							$column['hidden'] = false;
						} else {
							$column['hidden'] = true;
						}
					}

					switch( $src ) {
						case 'post_status':
							$post_status_col_index = $key;
							break;
						case '_booking_product_id':
							$bookable_product_col_index = $key;
							break;
						case '_booking_customer_id':
							$column['type'] = $column['editor'] = 'text';
							break;
						case in_array( $src, $datetime_columns ):
							$column['type'] = $column['editor'] = 'sm.datetime';
					  		$column['width'] = 102;
					  		break;
					  	case in_array( $src, $numeric_columns ):
					  		$column['type'] = 'numeric';
							$column['editor'] = 'customNumericEditor';
							$column['min'] = 0;
							$column['width'] = 50;
							$column['align'] = 'right';
							break;
						case in_array( $src, $checkbox_empty_one_columns ):
							$column['type'] = $column['editor'] = 'checkbox';
							$column['checkedTemplate'] = 1;
	      					$column['uncheckedTemplate'] = '';
							$column['width'] = 30;
							break;
					}
				}
			}


			//Code for Booking Status select box
			if( function_exists('get_wc_booking_statuses') && $post_status_col_index != '' ) {
				$booking_statuses = array_unique( array_merge( get_wc_booking_statuses( null, true ), get_wc_booking_statuses( 'user', true ), get_wc_booking_statuses( 'cancel', true ) ) );

				$booking_statuses_keys = ( !empty( $booking_statuses ) ) ? array_keys($booking_statuses) : array();
				$dashboard_model['columns'][$post_status_col_index]['defaultValue'] = ( !empty( $booking_statuses_keys[0] ) ) ? $booking_statuses_keys[0] : 'pending-confirmation';

				$dashboard_model['columns'][$post_status_col_index]['save_state'] = true;
				
				$dashboard_model['columns'][$post_status_col_index]['values'] = $booking_statuses;
				$dashboard_model['columns'][$post_status_col_index]['selectOptions'] = $booking_statuses; //for inline editing

				$dashboard_model['columns'][$post_status_col_index]['search_values'] = array();
				foreach ($booking_statuses as $key => $value) {
					$dashboard_model['columns'][$post_status_col_index]['search_values'][] = array('key' => $key, 'value' => $value);
				}

				$dashboard_model['columns'][$post_status_col_index] ['type'] = 'dropdown';
				$dashboard_model['columns'][$post_status_col_index] ['strict'] = true;
				$dashboard_model['columns'][$post_status_col_index] ['allowInvalid'] = false;
				$dashboard_model['columns'][$post_status_col_index] ['editor'] = 'select';
				$dashboard_model['columns'][$post_status_col_index] ['renderer'] = 'selectValueRenderer';

				$color_codes = array( 'green' => array( 'confirmed', 'paid', 'complete' ),
										'red' => array( 'cancelled' ),
										'orange' => array( 'unpaid' ),
										'blue' => array( 'in-cart', 'pending-confirmation' ) );

				$dashboard_model['columns'][$post_status_col_index]['colorCodes'] = apply_filters( 'sm_'.$this->dashboard_key.'_status_color_codes', $color_codes );
			}
			

			//Code for Booked Product select box
			if ( is_callable( array( 'WC_Bookings_Admin', 'get_booking_products' ) ) && $bookable_product_col_index != '' ) {
				$bookable_products = array( '' => __( 'N/A', 'woocommerce-bookings' ) );

				foreach ( WC_Bookings_Admin::get_booking_products() as $bookable_product ) {
					$bookable_products[ $bookable_product->get_id() ] = $bookable_product->get_name();

					$resources = $bookable_product->get_resources();

					foreach ( $resources as $resource ) {
						$bookable_products[ $bookable_product->get_id() . '=>' . $resource->get_id() ] = '&nbsp;&nbsp;&nbsp;' . $resource->get_name();
					}
				}

				$bookable_products_keys = ( !empty( $bookable_products ) ) ? array_keys($bookable_products) : array();

				$dashboard_model['columns'][$bookable_product_col_index]['name'] = __( 'Booked Product', 'smart-manager-for-wp-e-commerce' );
				$dashboard_model['columns'][$bookable_product_col_index]['key'] = $dashboard_model['columns'][$bookable_product_col_index]['name'];
				$dashboard_model['columns'][$bookable_product_col_index]['defaultValue'] = ( !empty( $bookable_products_keys[0] ) ) ? $bookable_products_keys[0] : '';

				$dashboard_model['columns'][$bookable_product_col_index]['save_state'] = true;
				
				$dashboard_model['columns'][$bookable_product_col_index]['values'] = $bookable_products;
				$dashboard_model['columns'][$bookable_product_col_index]['selectOptions'] = $bookable_products; //for inline editing

				$dashboard_model['columns'][$bookable_product_col_index]['search_values'] = array();
				foreach ($bookable_products as $key => $value) {
					$dashboard_model['columns'][$bookable_product_col_index]['search_values'][] = array('key' => $key, 'value' => $value);
				}

				$dashboard_model['columns'][$bookable_product_col_index] ['type'] = 'dropdown';
				$dashboard_model['columns'][$bookable_product_col_index] ['strict'] = true;
				$dashboard_model['columns'][$bookable_product_col_index] ['allowInvalid'] = false;
				$dashboard_model['columns'][$bookable_product_col_index] ['editor'] = 'select';
				$dashboard_model['columns'][$bookable_product_col_index] ['renderer'] = 'selectValueRenderer';
			}

			if (!empty($dashboard_model_saved)) {
				$col_model_diff = sa_array_recursive_diff($dashboard_model_saved,$dashboard_model);	
			}

			//clearing the transients before return
			if (!empty($col_model_diff)) {
				delete_transient( 'sa_sm_'.$this->dashboard_key );	
			}		

			return $dashboard_model;
		}

		//Function to modify the data_model
		public function bookings_data_model( $data_model, $data_col_params ) {

			if( empty( $data_model ) || empty( $data_model['items'] ) ) {
				return $data_model;
			}

			$store_model_transient = get_transient( 'sa_sm_'.$this->dashboard_key );
			if( ! empty( $store_model_transient ) && !is_array( $store_model_transient ) ) {
				$store_model_transient = json_decode( $store_model_transient, true );
			}
			$col_model = (!empty($store_model_transient['columns'])) ? $store_model_transient['columns'] : array();
			$data_cols_datetime = array();

			if (!empty($col_model)) {
				foreach ($col_model as $col) {

					if( !empty( $col['hidden'] ) && !empty( $col['data'] ) && array_search($col['data'], $data_col_params['required_cols']) === false ) {
						continue;
					}

					$data = ( !empty( $col['data'] ) ) ? $col['data'] : '';
					$type = ( !empty( $col['type'] ) ) ? $col['type'] : '';

					// Code for storing the datetime cols
					if( $type == 'sm.datetime' && empty( $col['date_type'] ) && !empty( $data ) ) {
						$data_cols_datetime[] = $data;
					}
				}
			}

			foreach( $data_model['items'] as $index => $items ) {
				foreach( $items as $key => $value ) {
					if( in_array( $key, $data_cols_datetime ) ) {
						$data_model['items'][$index][$key] = date( 'Y-m-d H:i:s', (int)strtotime($value) );
					}					
				}
			}
			return $data_model;
		}

		//function for modifying edited data before updating
		public function bookings_inline_update_pre( $edited_data ) {
			$store_model_transient = get_transient( 'sa_sm_'.$this->dashboard_key );
			if( ! empty( $store_model_transient ) && !is_array( $store_model_transient ) ) {
				$store_model_transient = json_decode( $store_model_transient, true );
			}
			$col_model = (!empty($store_model_transient['columns'])) ? $store_model_transient['columns'] : array();
			$data_cols_datetime = array();

			if (!empty($col_model)) {
				foreach ($col_model as $col) {

					$data = ( !empty( $col['data'] ) ) ? $col['data'] : '';
					$type = ( !empty( $col['type'] ) ) ? $col['type'] : '';

					// Code for storing the datetime cols
					if( $type == 'sm.datetime' && empty( $col['date_type'] ) && !empty( $data ) ) {
						$data_cols_datetime[] = $data;
					}
				}
			}

			foreach( $edited_data as $id => $cols ) {
				foreach( $cols as $key => $value ) {
					if( in_array( $key, $data_cols_datetime ) ) {
						$edited_data[$id][$key] = date( 'YmdHis', (int)strtotime($value) );
					}					
				}
			}

			return $edited_data;
		}
	}
}
