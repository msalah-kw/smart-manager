<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Shop_Order' ) ) {
	class Smart_Manager_Shop_Order extends Smart_Manager_Base {
		public $dashboard_key = '',
			$default_store_model = array(),
			$flat_tables = array( 'wc_orders', 'wc_order_addresses', 'wc_order_operational_data' ),
			$order_old_statuses = array(),
			$status_color_codes = array( 'green' 	=> array( 'wc-completed', 'wc-processing' ),
										'red' 		=> array( 'wc-cancelled', 'wc-failed', 'wc-refunded' ),
										'orange' 	=> array( 'wc-on-hold', 'wc-pending' ) );
		public static $kpi_query_results = array(),
			$address_types = array( 'billing', 'shipping' ),
			$hpos_tables_column_property_mapping = array();

		function __construct($dashboard_key) {
			// Hooks for WC v7.9 (HPOS) compat
			if ( ! empty( Smart_Manager::$sm_is_woo79 ) ) {
				add_filter( 'sm_search_table_types', array( 'Smart_Manager_Shop_Order', 'sm_order_search_table_types' ), 12, 1 ); // should be kept before calling the parent class constructor
			}
			parent::__construct($dashboard_key);

			$this->dashboard_key = $dashboard_key;
			$this->post_type = $dashboard_key;
			$this->req_params  	= ( ! empty( $_REQUEST ) ) ? $_REQUEST : array();
			add_filter( 'sm_data_model', array( &$this, 'orders_data_model' ), 10, 2 );

			// hooks for delete functionality
			add_filter( 'sm_default_process_delete_records', function() { return false; } );
			add_filter( 'sm_default_process_delete_records_result', array( 'Smart_Manager_Shop_Order', 'order_trash' ), 12, 2 );

			// Hooks for WC v7.9 (HPOS) compat
			if ( ! empty( Smart_Manager::$sm_is_woo79 ) ) {
				add_filter( 'sm_load_default_store_model', function() { return false; } );
				add_filter( 'sm_default_dashboard_model', array( &$this, 'default_dashboard_model' ), 10, 1 );
				add_filter( 'sm_get_custom_cols', array( 'Smart_Manager_Shop_Order', 'get_address_cols' ), 10, 2 );

				add_filter( 'sm_ignored_cols', array( 'Smart_Manager_Shop_Order', 'get_flat_table_ignored_cols' ) );
				add_filter( 'sm_flat_table_col_titles', array( 'Smart_Manager_Shop_Order', 'get_flat_table_col_titles' ) );

				add_filter( 'sm_beta_load_default_data_model', function() { return false; } );

				// Filters for modifying advanced search query clauses
				add_filter( 'woocommerce_orders_table_query_clauses',  array( &$this, 'modify_orders_table_query_clauses' ), 99, 3 );
				add_filter( 'sm_search_query_formatted', array( 'Smart_Manager_Shop_Order', 'sm_order_addresses_search_query_formatted' ), 12, 2 );
				add_filter( 'sm_search_wc_orders_meta_cond', array( &$this,'search_wc_orders_meta_cond' ), 10, 2 );

				// Filters for 'inline_update' functionality
				add_filter( 'sm_default_inline_update', function() { return false; } );

			} else {
				add_filter( 'sa_sm_dashboard_model', array( &$this,'orders_dashboard_model' ), 10, 2 );
				add_filter( 'sm_posts_where', array( &$this,'sm_query_orders_where_cond' ),100,2);
				add_filter( 'sm_posts_join_paged', array( &$this,'sm_order_query_join' ), 100, 2 );
				add_filter( 'sm_posts_orderby', array( &$this,'sm_query_order_by' ), 100, 2 );
				add_filter( 'sm_found_posts', array( 'Smart_Manager_Shop_Order' ,'kpi_data_query' ), 100, 2 );
				add_filter( 'sm_inline_update_pre', array( &$this, 'pre_inline_update' ), 10, 1 );
				add_filter( 'sm_batch_update_copy_from_ids_select',array( &$this,'sm_batch_update_copy_from_ids_select' ), 10, 2 );
			}
			add_action( 'sm_inline_update_post', array( &$this, 'orders_inline_update' ), 10, 2 );
		}

		//Function for overriding the select clause for fetching the ids for batch update 'copy from' functionality
		public function sm_batch_update_copy_from_ids_select( $select, $args ) {
			$select = " SELECT ID AS id, CONCAT('Order #', ID) AS title ";
			return $select;
		}

		/**
		 * Function to generate the column model for orders custom columns
		 *
		 * @param array $column_model column model array
		 * @param string $dashboard dashboard name.
         * @return array $column_model updated column model array.
		 */
		public static function generate_orders_custom_column_model( $column_model = array(), $dashboard = '' ) {

			global $wpdb;
			$custom_columns = array( 'shipping_method', 'shipping_method_title', 'coupons_used', 'line_items', 'details', 'order_sub_total', 'edit_link' );
			$order_notes_cols = ( 'shop_order' === $dashboard ) ? array( 'note_for_customer' ) : array();
			$custom_columns = array_merge( $custom_columns, $order_notes_cols );
			$order_items_table_searchable_cols = array( 'shipping_method', 'shipping_method_title', 'coupons_used' );
			$index = sizeof($column_model);

			foreach( $custom_columns as $col ) {

				$src = ( in_array( $col, $order_items_table_searchable_cols ) ? 'woocommerce_order_items/' : 'custom/' ). $col;

				$col_index = sa_multidimesional_array_search ($src, 'src', $column_model);
				if( empty( $col_index ) ) {
					$column_model [$index] = array();
					$column_model [$index]['src'] = $src;
					$column_model [$index]['data'] = sanitize_title(str_replace('/', '_', $column_model [$index]['src'])); // generate slug using the wordpress function if not given
					$column_model [$index]['name'] = __(ucwords(str_replace('_', ' ', $col)), 'smart-manager-for-wp-e-commerce');
					if ( ! empty( $col ) && 'edit_link' === $col ) {
						$column_model [$index]['name'] = _x('Edit', 'order edit link', 'smart-manager-for-wp-e-commerce');
						$column_model [$index]['renderer'] = 'html';
					}
					$column_model [$index]['key'] = $column_model [$index]['name'];
					$column_model [$index]['type'] = 'text';
					$column_model [$index]['hidden']	= false;
					$column_model [$index]['editable']	= false;
					$column_model [$index]['editor']	= false;
					$column_model [$index]['batch_editable'] = false;
					$column_model [$index]['sortable']	= true;
					$column_model [$index]['resizable']	= true;
					$column_model [$index]['allow_showhide'] = true;
					$column_model [$index]['exportable']	= true;
					$column_model [$index]['searchable']	= in_array( $col, $order_items_table_searchable_cols ) ? true : false;
					$column_model [$index]['save_state'] = true;
					$column_model [$index]['values'] = array();
					$column_model [$index]['search_values'] = array();

					if( in_array( $col, $order_items_table_searchable_cols ) ) {
						$column_model [$index]['table_name'] = $wpdb->prefix.'woocommerce_order_items';
						$column_model [$index]['col_name'] = $col;
					}
					if ( ! empty( $col ) && 'shipping_method' === $col ) {
						$column_model [$index]['editable'] = true;
						$column_model [$index]['batch_editable'] = true;
						$column_model [$index]['type'] = 'dropdown';
						$column_model [$index] ['editor'] = 'select';
						$column_model [$index] ['renderer'] = 'selectValueRenderer';
						$available_shipping_methods = array();
						$shipping_method_values = array();
						if ( ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) && ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) && is_callable( array( WC()->shipping, 'get_shipping_methods' ) ) ) {
							$available_shipping_methods = WC()->shipping->get_shipping_methods();
							if ( ! empty( $available_shipping_methods ) && is_array( $available_shipping_methods ) ) {
								foreach( $available_shipping_methods as $shipping_method ) {
									if ( ! is_callable( array( $shipping_method, 'get_method_title' ) ) ) {
										continue;
									}
									$column_model [$index]['values'][$shipping_method->id] = esc_html( $shipping_method->get_method_title() );
								}
							}
							if ( ! empty( $column_model [$index]['values'] ) && is_array( $column_model [$index]['values'] ) ) {
								foreach( $column_model [$index]['values'] as $key => $value ) {
									$column_model[$index]['search_values'][] = array( 'key' => $key, 'value' => $value );
								}
							}
						}
						$column_model [$index] ['selectOptions'] = $column_model [$index]['values'];
						$column_model [$index]['searchable']	= ( defined('SMPRO') && true === SMPRO ) ? true : false;
					} else if ( ! empty( $col ) && 'note_for_customer' === $col ) {
						$column_model [$index]['editable']	= false;
						$column_model [$index]['type']	= 'dropdown';
						$column_model [$index]['batch_editable'] = true;
						$column_model [$index]['searchable'] = false;
					} else if ( ! empty( $col ) && 'shipping_method_title' === $col ) {
						$column_model [$index]['editable'] = true;
						$column_model [$index]['batch_editable'] = true;
						$column_model [$index]['editor']	= 'text';
						$column_model [$index]['searchable']	= false;
					}
					$index++;
				}
			}

			return $column_model;
		}

		public function orders_dashboard_model( $dashboard_model = array(), $dashboard_model_saved = array() ) {
			global $wpdb, $current_user;

			$dashboard_model['tables']['posts']['where']['post_type'] = 'shop_order';

			$visible_columns = array('ID', 'post_date', '_billing_first_name', '_billing_last_name', '_billing_email', 'post_status', '_order_total', 'details', '_payment_method_title', 'shipping_method', 'coupons_used', 'line_items');

			$numeric_columns = array('_billing_phone', '_cart_discount', '_cart_discount_tax', '_customer_user');

			$string_columns = array('_billing_postcode', '_shipping_postcode');

			$post_status_col_index = sa_multidimesional_array_search('posts_post_status', 'data', $dashboard_model['columns']);

			if( isset( $dashboard_model['columns'][$post_status_col_index] ) && is_callable( array( 'Smart_Manager_Shop_Order', 'generate_status_col_model' ) ) ) {
				$dashboard_model['columns'][$post_status_col_index] = self::generate_status_col_model( $dashboard_model['columns'][$post_status_col_index],
					array( 'curr_obj' => $this,
							'status_func' => 'wc_get_order_statuses',
							'default_status' => 'wc-pending',
							'color_codes' => $this->status_color_codes ) );
			}

			if( is_callable( array( 'Smart_Manager_Shop_Order', 'generate_orders_custom_column_model' ) ) ) {
				$dashboard_model['columns'] = self::generate_orders_custom_column_model( $dashboard_model['columns'], $this->dashboard_key );
			}

			$column_model = &$dashboard_model['columns'];

			//Code for unsetting the position for hidden columns

			foreach( $column_model as &$column ) {

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

				if ($src == 'post_date') {
					$column ['name'] = $column ['key'] = __('Date', 'smart-manager-for-wp-e-commerce');
				} else if ($src == 'post_status') {
					$column ['name'] = $column ['key'] = __('Status', 'smart-manager-for-wp-e-commerce');
				} else if ($src == 'post_excerpt') {
					$column ['name'] = $column ['key'] = __('Customer provided note', 'smart-manager-for-wp-e-commerce');
				} else if( !empty( $numeric_columns ) && in_array( $src, $numeric_columns ) ) {
					$column ['type'] = 'numeric';
					$column['editor'] = ( '_billing_phone' === $src ) ? 'numeric' : 'customNumericEditor';
				} else if( !empty( $string_columns ) && in_array( $src, $string_columns ) ) {
					$column ['type'] = $column ['editor'] = 'text';
				}
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


		public static function process_custom_search( $where, $params ) {

			global $wpdb;

			//Code for handling simple search
			if( empty( $params['search_text'] ) || strpos( $where, 'posts.ID IN' ) === true ) {
				return $where;
			}

			$search_text = $wpdb->_real_escape( $params['search_text'] );
			$dashboard = ( !empty( $params['active_module'] ) ) ? $params['active_module'] : 'shop_order';

			//Query to get the post_id of the products whose sku code matches with the one type in the search text box of the Orders Module
			$order_ids = self::get_filtered_order_ids( $search_text, $dashboard );
            if ( ! empty( $order_ids ) ) {
            	$where = " AND {$wpdb->prefix}posts.ID IN(". implode( ',', $order_ids ) .") AND {$wpdb->prefix}posts.post_type = '". $dashboard ."' ";
            }

			return $where;
		}

		public function sm_query_orders_where_cond ($where, $wp_query_obj) {
			global $wpdb;
			if ( empty( Smart_Manager::$sm_is_woo79 ) && ! empty( $this->req_params['search_text'] ) ) {
				$search_text = $this->req_params['search_text'];
				$where .= ( false === strpos( $where, "oi.order_item_name LIKE '%" . $wpdb->esc_like( $search_text ) . "%'" ) ) ? " OR ( oi.order_item_name LIKE '%" . $wpdb->esc_like( $search_text ) . "%' )" : '';
				$where .= ( false === strpos( $where, "oim.meta_value LIKE '%" . $wpdb->esc_like( $search_text ) . "%'" ) ) ? " OR ( oim.meta_value LIKE '%" . $wpdb->esc_like( $search_text ) . "%') " : '';
				$where .= ( false === strpos( $where, "cm.comment_content LIKE '%" . $wpdb->esc_like( $search_text ) . "%'" ) ) ? " OR ( cm.comment_content LIKE '%" . $wpdb->esc_like( $search_text ) . "%') " : '';
				return $where;
			}
			if( is_callable( array( 'Smart_Manager_Shop_Order', 'process_custom_search' ) ) ) {
				$where = self::process_custom_search( $where, $this->req_params );
			}
			return $where;
		}


		public static function generate_orders_custom_column_data( $data_model, $params ) {

			global $wpdb, $current_user;

			$order_ids = $order_coupons = array();
			$order_id_cond = '';
			$dashboard = ( !empty( $params['active_module'] ) ) ? $params['active_module'] : 'shop_order';

			if( !empty( $data_model['items'] ) ) {
				foreach( $data_model['items'] as $data ) {
					$order_ids[] = ( ! empty( Smart_Manager::$sm_is_woo79 ) && ( ! empty( $data['wc_orders_id'] ) ) ) ? $data['wc_orders_id'] : ( ( ! empty( $data['posts_id'] ) )  ? $data['posts_id'] : 0 );
				}
			}

			if( !empty( $order_ids ) ) {
				if( count( $order_ids ) > 100 ) {
					$order_ids_imploded = implode(",",$order_ids);
					update_option( 'sa_sm_export_'.$dashboard.'_ids', $order_ids_imploded, 'no' );
					$order_id_cond = "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'sa_sm_export_".$dashboard."_ids'";
					$csv_export = true;
				} else {
					$order_id_cond = implode(",",$order_ids);
					$csv_export = false;
				}
			}

			if( !empty( $order_id_cond ) ) {

				$results_order_coupons = $wpdb->get_results( $wpdb->prepare( "SELECT order_id,
									                                        GROUP_CONCAT(order_item_name
									                                                            ORDER BY order_item_id
									                                                            SEPARATOR ', ' ) AS coupon_used
									                                    FROM {$wpdb->prefix}woocommerce_order_items
									                                    WHERE order_item_type = %s
									                                    	".( ( !empty( $csv_export ) ) ? " AND FIND_IN_SET ( order_id, ( ".$order_id_cond." ) ) " : " AND order_id IN ( ".$order_id_cond." ) "  )."
									                                    GROUP BY order_id", 'coupon'), 'ARRAY_A' );

				if( !empty( $results_order_coupons ) ) {
					foreach( $results_order_coupons as $result ) {
	                    $order_coupons[$result['order_id']] = $result['coupon_used'];
	                }
				}

				$variation_ids = $wpdb->get_col( $wpdb->prepare( "SELECT order_itemmeta.meta_value
							                                        FROM {$wpdb->prefix}woocommerce_order_items AS order_items
							                                           LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta
							                                               ON (order_items.order_item_id = order_itemmeta.order_item_id)
							                                        WHERE order_itemmeta.meta_key = %s
							                                               AND order_itemmeta.meta_value > %d
							                                               ".( ( !empty( $csv_export ) ) ? " AND FIND_IN_SET ( order_items.order_id, ( ".$order_id_cond." ) ) " : " AND order_items.order_id IN ( ".$order_id_cond." ) "  ), '_variation_id', 0 ) );

	            if ( count( $variation_ids ) > 0 ) {

	            	if( count( $variation_ids ) > 100 ) {
						$variation_ids_imploded = implode(",",$variation_ids);
						update_option( 'sa_sm_export_'.$dashboard.'_variation_ids', $variation_ids_imploded, 'no' );
						$variation_id_cond = " AND FIND_IN_SET ( postmeta.post_id, ( SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'sa_sm_export_".$dashboard."_variation_ids' ) ) ";
					} else {
						$variation_id_cond = " AND postmeta.post_id IN (". implode(",",$variation_ids) .") ";
					}

	                $results_variation_att = $wpdb->get_results( $wpdb->prepare( "SELECT postmeta.post_id AS post_id,
										                                                    GROUP_CONCAT(postmeta.meta_value
										                                                        ORDER BY postmeta.meta_id
										                                                        SEPARATOR ',' ) AS meta_value
										                                            FROM {$wpdb->prefix}postmeta AS postmeta
										                                            WHERE postmeta.meta_key LIKE %s
										                                                ". $variation_id_cond ."
										                                            GROUP BY postmeta.post_id", 'attribute_%' ), 'ARRAY_A') ;
	            }
			}


			//Code to get the variation Attributes
			$attributes_terms = $wpdb->get_results( $wpdb->prepare( "SELECT terms.slug as slug, terms.name as term_name
										                          FROM {$wpdb->prefix}terms AS terms
										                            JOIN {$wpdb->prefix}postmeta AS postmeta
										                                ON ( postmeta.meta_value = terms.slug
										                                        AND postmeta.meta_key LIKE %s )
										                          GROUP BY terms.slug", 'attribute_%' ), 'ARRAY_A' );
            $attributes = array();
            foreach ( $attributes_terms as $attributes_term ) {
                $attributes[$attributes_term['slug']] = $attributes_term['term_name'];
            }

            $variation_att_all = array();

            if ( !empty($results_variation_att) && is_array( $results_variation_att ) && count( $results_variation_att ) > 0 ) {

                for ($i=0;$i<sizeof($results_variation_att);$i++) {
                    $variation_attributes = explode(", ",$results_variation_att [$i]['meta_value']);

                    $attributes_final = array();
                    foreach ($variation_attributes as $variation_attribute) {
                        $attributes_final[] = (isset($attributes[$variation_attribute]) ? $attributes[$variation_attribute] : ucfirst($variation_attribute) );
                    }

                    $results_variation_att [$i]['meta_value'] = implode(", ",$attributes_final);
                    $variation_att_all [$results_variation_att [$i]['post_id']] = $results_variation_att [$i]['meta_value'];
                }

            }

            //Code for handling search
            $order_id_join = '';
			if( !empty($params) && !empty($params['search_query']) && !empty($params['search_query'][0]) ) {
				$order_id_join = " JOIN {$wpdb->base_prefix}sm_advanced_search_temp as temp ON (temp.product_id = order_items.order_id)";
				$order_id_cond = '';
			} else if( !empty( $order_id_cond ) ) {
				$order_id_cond = ( ( !empty( $csv_export ) ) ? " AND FIND_IN_SET ( order_id, ( ".$order_id_cond." ) ) " : " AND order_id IN ( ".$order_id_cond." ) "  );
			}

			$order_items = array();
            $order_shipping_method = array();
			$order_shipping_method_title = array();
			$processed_results = array();
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT order_items.order_item_id,
				                            order_items.order_id    ,
				                            order_items.order_item_name AS order_prod,
				                            order_items.order_item_type,
				                            order_itemmeta.meta_key,
				                           	order_itemmeta.meta_value
				                        FROM {$wpdb->prefix}woocommerce_order_items AS order_items
				                            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta
				                                ON (order_items.order_item_id = order_itemmeta.order_item_id
				                                AND order_items.order_item_type IN ('line_item', 'shipping') )
				                            ". $order_id_join ."
				                        WHERE 1 = %d
				                        	AND order_items.order_item_type IN ('line_item', 'shipping')
				                            ". $order_id_cond ."
				                        GROUP BY order_items.order_item_id, order_itemmeta.meta_key", 1 ), 'ARRAY_A' );
			if ( ! empty( $results ) && is_array( $results ) ) {
				foreach( $results as $result ) {
					$item_id = $result['order_item_id'];
					if ( empty( $item_id ) ) {
						continue;
					}
					if ( ! isset( $processed_results[ $item_id ] ) ) {
						$processed_results[ $item_id ] = array(
							'order_id' => $result['order_id'],
							'order_prod' => $result['order_prod'],
							'order_item_type' => $result['order_item_type'],
							'meta_key' => array(),
							'meta_value' => array()
						);
					}
					$processed_results[ $item_id ]['meta_key'][] = $result['meta_key'];
					$processed_results[ $item_id ]['meta_value'][] = $result['meta_value'];
				}
			}

			// Convert $processed_results array to concatenated strings.
			if ( ( ! empty( $processed_results ) ) && is_array( $processed_results ) ) {
				foreach ( $processed_results as &$item ) {
					$item['meta_key'] = implode( '###', $item['meta_key'] );
					$item['meta_value'] = implode( '###', $item['meta_value'] );
				}
				foreach ( $processed_results as $result ) {
                    if ( ! isset( $order_items[ $result['order_id'] ] ) ) {
                        $order_items[ $result['order_id'] ] = array();
                    }
                    if ( 'shipping' === $result['order_item_type'] ) {
						$order_meta_key = explode( '###', $result['meta_key'] );
						$order_meta_values = explode( '###', $result['meta_value'] );
						if ( ! empty( $order_meta_key ) && is_array( $order_meta_key ) && 'method_id' === $order_meta_key[0] && ! empty( $order_meta_values ) && is_array( $order_meta_values ) ) {
							$order_shipping_method [$result['order_id']] = $order_meta_values[0];
						}
						$order_shipping_method_title[$result['order_id']] = $result['order_prod'];
                    } else {
                        $order_items [$result['order_id']] [] = $result;
                    }
                }
			}

            if( !empty( $data_model['items'] ) ) {
            	foreach( $data_model['items'] as $key => $order_data ) {
			$order_id = ( ! empty( Smart_Manager::$sm_is_woo79 ) && ( ! empty( $order_data['wc_orders_id'] ) ) ) ? $order_data['wc_orders_id'] : ( ( ! empty( $order_data['posts_id'] ) )  ? $order_data['posts_id'] : 0 );
            		if( !empty( $order_items[$order_id] ) ) {

            			foreach( $order_items[$order_id] as $order_item ) {
            				$order_meta_values = explode('###', $order_item ['meta_value'] );
	                        $order_meta_key = explode('###', $order_item ['meta_key'] );

	                        if (count($order_meta_values) != count($order_meta_key)) {
	                            continue;
	                        }

	                        $order_meta_key_values = array_combine($order_meta_key, $order_meta_values);

	                        $data_model['items'][$key]['custom_details'] = intval ( (!empty($data_model['items'][$key]['custom_details'])) ? $data_model['items'][$key]['custom_details'] : 0 );
	                        $data_model['items'][$key]['custom_details'] += intval( ( !empty( $order_meta_key_values['_qty'] ) ) ? $order_meta_key_values['_qty'] : 0 );

	                        $product_id = ( $order_meta_key_values['_variation_id'] > 0 ) ? $order_meta_key_values['_variation_id'] : $order_meta_key_values['_product_id'];
		                    $sm_sku = get_post_meta( $product_id, '_sku', true );
		                    if ( ! empty( $sm_sku ) ) {
		                            $sku_detail = '[SKU: ' . $sm_sku . ']';
		                    } else {
		                            $sku_detail = '';
		                    }

		                    $variation_att = ( isset( $variation_att_all [$order_meta_key_values['_variation_id']] ) && !empty( $variation_att_all [$order_meta_key_values['_variation_id']] ) ) ? $variation_att_all [$order_meta_key_values['_variation_id']] : '';

		                    $product_full_name = ( !empty( $variation_att ) ) ? $order_item['order_prod'] . ' (' . $variation_att . ')' : $order_item['order_prod'];

		                    $data_model['items'][$key]['custom_line_items'] = (!empty($data_model['items'][$key]['custom_line_items'])) ? $data_model['items'][$key]['custom_line_items'] : '';
		                    $data_model['items'][$key]['custom_line_items'] .= $product_full_name.' '.$sku_detail.'['.__('Qty','smart-manager-for-wp-e-commerce').': '.$order_meta_key_values['_qty'].']['.__('Price','smart-manager-for-wp-e-commerce').': '.($order_meta_key_values['_line_total']/$order_meta_key_values['_qty']).']';

		                    if( !empty( $order_meta_key_values['_wc_cog_item_total_cost'] ) ) {
		                    	$data_model['items'][$key]['custom_line_items'] .= '['.__('Cost of Good','smart-manager-for-wp-e-commerce').': '.wc_format_decimal($order_meta_key_values['_wc_cog_item_total_cost']).']';
		                    }

		                    $data_model['items'][$key]['custom_line_items'] .= ', ';

		                    $data_model['items'][$key]['custom_order_sub_total'] = floatval ( (!empty($data_model['items'][$key]['custom_order_sub_total'])) ? $data_model['items'][$key]['custom_order_sub_total'] : 0 );
	                        $data_model['items'][$key]['custom_order_sub_total'] += floatval( ( !empty( $order_meta_key_values['_line_subtotal'] ) ) ? $order_meta_key_values['_line_subtotal'] : 0 );
            			}

            			if( !empty( $data_model['items'][$key]['custom_line_items'] ) ) {
            				$data_model['items'][$key]['custom_line_items'] = substr( $data_model['items'][$key]['custom_line_items'], 0, -2 ); //To remove extra comma ', ' from returned
            			}
            			$data_model['items'][$key]['custom_details'] = !empty( $data_model['items'][$key]['custom_details'] ) ? ( ( $data_model['items'][$key]['custom_details'] == 1) ? $data_model['items'][$key]['custom_details'] . ' item' : $data_model['items'][$key]['custom_details'] . ' items' ) : '';
						if ( ! empty( Smart_Manager::$sm_is_woo79 ) ) {
							$link = admin_url( 'admin.php?page=wc-orders' . ( ( 'shop_subscription' === $dashboard ) ? '--' . $dashboard : '' ) . '&action=edit&id=' . $order_id );
	        				$data_model['items'][$key]['custom_edit_link'] = ( ! empty( $params['cmd'] ) && 'get_export_csv' !== $params['cmd'] && 'get_print_invoice' !== $params['cmd'] ) ? '<a href="'.$link.'" target="_blank" style="text-decoration:none !important; color:#5850ecc2 !important;"><span class="dashicons dashicons-external"></span></a>' : $link;
						}
						$order_obj = ( ( ! empty( $order_id ) ) && class_exists( 'WC_Order' ) ) ? new WC_Order( $order_id ) : null;
						$order_notes = ( $order_obj instanceof WC_Order && is_callable( array( $order_obj, 'get_customer_order_notes' ) ) ) ? $order_obj->get_customer_order_notes() : array();
						$notes_to_customer =  array();
						if ( ! empty( $order_notes ) && is_array( $order_notes ) ) {
							foreach ( $order_notes as $order_note ) {
								$notes_to_customer[] = sprintf(
									/* translators: %1$s: order note %2$s: author name %2$s: order note added date */
									_x( 'Note:  %1$s (Added by  %2$s on  %3$s)', 'order note', 'smart-manager-for-wp-e-commerce' ),
									$order_note->comment_content,
									$order_note->comment_author,
									get_comment_date( 'F j, Y \a\t g:i a', $order_note->comment_ID )
								);
							}
						}
						$data_model['items'][$key]['custom_note_for_customer'] = ( ! empty( $notes_to_customer ) && is_array( $notes_to_customer ) ) ? implode( ', ', $notes_to_customer ) : '';
            		}

                    $data_model['items'][$key]['woocommerce_order_items_coupons_used'] = ( !empty( $order_coupons[$order_id] ) ) ? $order_coupons[$order_id] : "";
                    $data_model['items'][$key]['woocommerce_order_items_shipping_method'] = ( !empty( $order_shipping_method[$order_id] ) ) ? $order_shipping_method[$order_id] : "";
					$data_model['items'][$key]['woocommerce_order_items_shipping_method_title'] = ( !empty( $order_shipping_method_title[$order_id] ) ) ? $order_shipping_method_title[$order_id] : "";
            	}
            }

			return $data_model;
		}

		//Function to query for the dashboard KPI data
		public static function kpi_data_query( $found_rows, $wp_query_obj ) {

			$query = ( !empty( $wp_query_obj->request ) ) ? $wp_query_obj->request : '';

			if( !empty( $query ) ) {

				global $wpdb;

				$status_counts = array();
				$from_strpos = strpos( $query, 'FROM' );
				$from_pos = ( !empty( $from_strpos ) ) ? $from_strpos : 0;

				if( $from_pos > 0 ) {
					$query = substr( $query, $from_pos );
					$groupby_strpos = strpos( $query, 'GROUP' );
					$group_pos = ( !empty( $groupby_strpos ) ) ? $groupby_strpos : 0;
					$query = substr( $query, 0, $group_pos );

					if( !empty( $query ) ) {
						self::$kpi_query_results = $wpdb->get_results( 'SELECT '.$wpdb->prefix.'posts.post_status, COUNT( DISTINCT( '.$wpdb->prefix.'posts.id ) ) AS count '. $query .' GROUP BY '.$wpdb->prefix.'posts.post_status', 'ARRAY_A' );
					}
				}
			}

			return $found_rows;
		}

		//Function for getting the KPI data
		public static function generate_orders_kpi_data( $params, $statuses = array() ) {

			global $wpdb;

			$kpi_data = array();
			$dashboard = ( !empty( $params['active_module'] ) ) ? $params['active_module'] : 'shop_order';
			$status_counts = ( !empty( self::$kpi_query_results ) ) ? self::$kpi_query_results : array();

			if( count($status_counts) > 0 ) {

				$dashboard_model = get_transient('sa_sm_'.$dashboard);
				if( ! empty( $dashboard_model ) ) {
					$dashboard_model = json_decode( $dashboard_model, true );
				}

				if( !empty( $dashboard_model['columns'] ) ) {
					foreach( $dashboard_model['columns'] as $colObj ) {
						if ( ! isset( $colObj['data'] ) ) continue;
						if( !empty( $colObj['data'] ) && !empty( $colObj['colorCodes'] ) && ( ( 'posts_post_status' === $colObj['data'] ) || ( 'wc_orders_status' === $colObj['data'] ) ) ) {
							foreach( $colObj['colorCodes'] as $key => $obj ) {
								foreach( $obj as $col ) {
									$color_codes[ $col ] = $key;
								}
							}
						}
					}
				}

				foreach( $status_counts as $value ) {

					if ( empty( $statuses ) ) continue;
					switch ( true ) {
						case ( array_key_exists( 'post_status', $value ) ):
							$key = ( ! empty( $statuses[$value['post_status']] ) ) ? $statuses[$value['post_status']] : ucwords($value['post_status']);
							$kpi_data[ $key ] = array( 'count' => $value['count'],
												'color' => ( ( !empty( $color_codes[$value['post_status']] ) ) ? $color_codes[$value['post_status']] : '' ) );
							break;

						case ( array_key_exists( 'status', $value ) ):
							$key = ( ! empty( $statuses[$value['status']] ) ) ? $statuses[$value['status']] : ucwords($value['status']);
							$kpi_data[ $key ] = array( 'count' => $value['count'],
												'color' => ( ( !empty( $color_codes[$value['status']] ) ) ? $color_codes[$value['status']] : '' ) );
							break;
					}
				}
			}

			return $kpi_data;
		}

		/**
		 * Function hooked to filter for modifying data model.
		 *
		 * @param array $data_model default generated data model.
		 * @param array $data_col_params function arguments.
		 * @return array $data_model updated data model.
		 */
		public function orders_data_model( $data_model = array(), $data_col_params = array() ) {
			return ( is_callable( array( 'Smart_Manager_Shop_Order', 'generate_data_model' ) ) ) ? Smart_Manager_Shop_Order::generate_data_model( $data_model, array( 'col_params' => $data_col_params, 'curr_obj' => $this, 'status_func' => 'wc_get_order_statuses' ) ) : $data_model;
		}

		/**
		 * Function for generating data model.
		 *
		 * @param array $data_model default generated data model.
		 * @param array $args function arguments.
		 * @return array $data_model updated data model.
		 */
		public static function generate_data_model( $data_model = array(), $args = array() ) {

			if( empty( $args ) || ! is_array( $args ) ) {
				return $data_model;
			}

			$data_col_params = ( ! empty( $args['col_params'] ) ) ? $args['col_params'] : array();
			$curr_obj = ( ! empty( $args['curr_obj'] ) ) ? $args['curr_obj'] : null;
			if( empty( $data_col_params ) || ! is_array( $data_col_params ) || empty( $curr_obj ) || ( ! empty( $curr_obj ) && empty( $curr_obj->dashboard_key ) ) ){
				return $data_model;
			}
			//if scheduled export then set the scheduled export params to req_params.
			if( ( ! empty ( $data_col_params['is_scheduled_export'] ) && ( 'true' === $data_col_params['is_scheduled_export'] ) ) && ( ! empty( $data_col_params['advanced_search_query'] ) ) ) {
				$curr_obj->req_params['advanced_search_query'] = $data_col_params['advanced_search_query'];
			}
			if( ( ! empty ( $data_col_params['is_scheduled_export'] ) && ( 'true' === $data_col_params['is_scheduled_export'] ) ) && ( ! empty( $data_col_params['sort_params'] ) ) ) {
				$curr_obj->req_params['sort_params'] = $data_col_params['sort_params'];
			}

			global $wpdb;
			if ( ! empty( Smart_Manager::$sm_is_woo79 ) ) {
				$orders_queries = array();
				$custom_columns_tables = array( 'woocommerce_order_itemmeta', 'woocommerce_order_items' );

				$col_model = ( ! empty( $data_col_params['col_model'] ) ) ? $data_col_params['col_model'] : array();
				if( empty( $col_model ) ){
					return $data_model;
				}

				$visible_cols = ( ! empty( $data_col_params['visible_cols'] ) ) ? $data_col_params['visible_cols'] : array();
				if( empty( $visible_cols ) ){
					return $data_model;
				}

				$query_args = array(
					'type' => $curr_obj->dashboard_key,
					'orderby' => ( ! empty ( $data_col_params['is_scheduled_export'] ) && ( 'true' === $data_col_params['is_scheduled_export'] ) ) ? 'date' : 'id',
					'order' => 'DESC',
					'offset' => ( ! empty( $data_col_params['offset'] ) ) ? intval( $data_col_params['offset'] ) : 0,
					'paginate' => true,
					'limit' => ( ! empty( $data_col_params['limit'] ) ) ? intval( $data_col_params['limit'] ) : 50
				);

				// ===============================================================
				// Code to handle display of 'trash' records
				// ===============================================================
				if( ! empty( $args['status_func'] ) && function_exists( $args['status_func'] ) ){
					$query_args['status'] = array_keys( call_user_func( $args['status_func'] ) );
					if( ! empty( $query_args['status'] ) && is_array( $query_args['status'] ) && is_callable( array( $curr_obj, 'is_show_trash_records' ) ) && ! empty( $curr_obj->is_show_trash_records() ) ) {
						$query_args['status'] = array_merge( $query_args['status'], array( 'trash' ) );
					}
				}

				// ===============================================================
				// Code for Advanced Search functionality
				// ===============================================================
				//Code to clear the advanced search temp table
				if( empty( $curr_obj->req_params['advanced_search_query'] ) ) {
					$wpdb->query( "DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp" );
					delete_option( 'sm_advanced_search_query' );
				}
				// Code for handling advanced search functionality.
				if ( ! empty( $curr_obj->req_params['advanced_search_query'] ) && $curr_obj->req_params['advanced_search_query'] != '[]' ) {
					$curr_obj->req_params['advanced_search_query'] = ( ! is_array( $curr_obj->req_params['advanced_search_query'] ) ) ? json_decode( stripslashes( $curr_obj->req_params['advanced_search_query'] ), true ) : $curr_obj->req_params['advanced_search_query'];
					if( ! empty( $curr_obj->req_params['advanced_search_query'] ) ) {
						$curr_obj->process_search_cond( array( 'post_type' => ( ! empty( $curr_obj->post_type ) && 'shop_subscription' === $curr_obj->post_type ) ? '' : $curr_obj->post_type,
														'search_query' => (!empty($curr_obj->req_params['advanced_search_query'])) ? $curr_obj->req_params['advanced_search_query'] : array(),
														'SM_IS_WOO30' => (!empty($curr_obj->req_params['SM_IS_WOO30'])) ? $curr_obj->req_params['SM_IS_WOO30'] : '',
														'search_cols_type' => $data_col_params['search_cols_type'],
														'data_col_params' => $data_col_params,
														'pkey' => 'order_id',
														'join_table' => 'wc_orders',
														'type' => 'type'
														 ) );

					}
				}

				// ===============================================================

				// ===============================================================
				// Code for Simple Search functionality
				// ===============================================================
				if( ! empty( $curr_obj->req_params['search_text'] ) ) {
					$col_name = '';
					$date_types = array( 'datetime', 'date', 'time' );
					$search_text = $wpdb->_real_escape( $curr_obj->req_params['search_text'] );
					if ( empty( $search_text ) ){
						return $data_model;
					}

					$search_order_ids = array();
					if ( is_callable( array( 'Smart_Manager_Shop_Order', 'get_filtered_order_ids' ) ) ) {
						$search_order_ids = self::get_filtered_order_ids( $search_text, $curr_obj->dashboard_key );
					}

					if ( ! empty( $search_order_ids ) ) {
						$orders_queries['field_query'][] = array(
						   	'field'   =>  $wpdb->prefix . 'wc_orders.id',
						    'value'   => implode( ',', $search_order_ids ),
						    'compare' => 'IN'
						);
					}

					$excluded_custom_tables = array_map( function( $custom_columns_table ) use ( $wpdb ) {
						return $wpdb->prefix . $custom_columns_table;
					}, $custom_columns_tables );

					foreach ( $col_model as $col ) {
						if ( empty( $col['table_name'] ) || empty( $col['col_name'] ) || empty( $col['type'] ) ){
							continue;
						}

						// NOTE: For now have excluded the custom tables and 'wc_order_operational_data' table from simple search field queries
						if ( ( $wpdb->prefix . 'wc_orders_meta' === $col['table_name'] ) || ( 'shipping_email' === $col['col_name'] ) || ( ( ! empty( $excluded_custom_tables ) ) && in_array( $col['table_name'], $excluded_custom_tables ) ) || ( $wpdb->prefix . 'wc_order_operational_data' === $col['table_name'] ) ){
							continue;
						}

						if ( $wpdb->prefix . 'wc_order_addresses' === $col['table_name'] ) {
							$col_name = explode('_', $col['col_name']);
							$col_name = substr( $col['col_name'], 0, strlen( $col_name[0] ) );
							$orders_queries['field_query'][] = array(
								'relation' => 'OR',
					            'field'   => $col_name . '_address' . '.' . $col['col_name'],
					           	'value'   => $search_text,
					            'compare' => 'LIKE'
					        );
						} elseif ( in_array( $col['type'], $date_types ) ) {
							$orders_queries['date_query'][] = array(
								'relation' => 'OR',
							  	'column'   => $col['col_name'],
				              	'date'   => $search_text,
				              	'inclusive' => true
				          	);
						} else {
							$orders_queries['field_query'][] = array(
								'relation' => 'OR',
					            'field'   => $col['table_name'] . '.' . $col['col_name'],
					            'value'   => $search_text,
					            'compare' => 'LIKE'
					        );
						}
					}
					// Code for woocommerce_order_items table simple search.
					$woi_order_ids = $wpdb->get_col(
										$wpdb->prepare(
											"SELECT DISTINCT( oi.order_id )
											 FROM {$wpdb->prefix}woocommerce_order_items AS oi
											 JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim
											 ON ( oim.order_item_id = oi.order_item_id AND oi.order_item_type = %s )
											 WHERE ( oi.order_item_name LIKE %s OR (oim.meta_value LIKE %s ) )",
											 'shipping',
											 '%' . $wpdb->esc_like( $curr_obj->req_params['search_text'] ). '%',
											 '%' . $wpdb->esc_like( $curr_obj->req_params['search_text'] ) . '%'
										)
									);
					// Code for comments table simple search.
					$comments_order_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT( cm.comment_post_ID )
							 FROM {$wpdb->prefix}comments AS cm
							 JOIN {$wpdb->prefix}commentmeta AS cmm
							 ON (cmm.comment_id = cm.comment_ID)
							 WHERE ( cm.comment_content LIKE %s AND cm.comment_type = %s )",
							 '%' . $wpdb->esc_like( $curr_obj->req_params['search_text'] ) . '%', 'order_note'
						)
					);
					$search_order_ids = array_merge( $woi_order_ids, $comments_order_ids );
					if ( ! empty( $comments_order_ids ) && is_array( $comments_order_ids ) ) {
						$orders_queries['field_query'][] = array(
							'field'     => $wpdb->prefix . 'wc_orders.id',
							'value'     => implode( ',', $comments_order_ids ),
							'compare'   => 'IN',
						);
					}
					if ( empty( $orders_queries ) ) {
						return $data_model;
					}
				}
				// ===============================================================

				if( ! empty( $orders_queries['field_query'] ) ){
					$orders_queries['field_query']['relation'] = 'OR';
					$query_args['field_query'] = $orders_queries['field_query'];
				}
				$orders = wc_get_orders( $query_args );

				if ( empty( $orders ) || is_wp_error( $orders ) ) {
					return $data_model;
				}

				if( ! isset( $orders->orders ) || ( isset( $orders->orders ) && ! is_array( $orders->orders ) ) ){
					return $data_model;
				}

				$non_getter_cols = $address_types = $columns = $order_ids = $items = array();

				foreach( $orders->orders as $order ) {
					if( ! $order instanceof WC_Order ){
						continue;
					}

					$order_id = $order->get_id();
					if( empty( $order_id ) || is_wp_error( $order_id ) ){
						continue;
					}

					if( ! empty( $args['curr_obj_getter_func'] ) && ! empty( $args['curr_obj_class_nm'] ) && function_exists( $args['curr_obj_getter_func'] ) && class_exists( $args['curr_obj_class_nm'] ) ) {
						$order = call_user_func( $args['curr_obj_getter_func'], $order_id );
						if( ! $order instanceof $args['curr_obj_class_nm'] ){
							continue;
						}
					}

					$order_ids[] = $order_id;
					$items[ $order_id ] = array( 'wc_orders_id' => $order_id );

					foreach ( $visible_cols as $col ) {
						if (  empty( $col['src'] ) || ( empty( $col['col_name'] ) ) || ( empty( $col['data'] ) ) ) {
							continue;
						}
						$col_property = ( is_callable( array( 'Smart_Manager_Shop_Order', 'get_property_name_for_col' ) ) ) ? Smart_Manager_Shop_Order::get_property_name_for_col( $col['col_name'], $col['table_name'] ) : $col['col_name'];
						$getter_func1 = 'get_'.$col_property;
						$getter_func2 = 'get'.$col_property;
						if ( is_callable( array( $order, $getter_func1 ) ) || is_callable( array( $order, $getter_func2 ) ) ) {
							$items[$order_id][$col['data']] = $order->{ ( is_callable( array( $order, $getter_func1 ) ) ? $getter_func1 : $getter_func2 ) }();
							if( ! empty( $col['type'] ) && 'sm.datetime' === $col['type'] && function_exists( 'wc_format_datetime' ) && ! empty( $items[$order_id][$col['data']] ) && $items[$order_id][$col['data']] instanceof WC_DateTime && is_callable( array( $items[$order_id][$col['data']], 'date' ) ) ){
								$items[$order_id][$col['data']] = $items[$order_id][$col['data']]->date( 'Y-m-d H:i:s' );
							} else if( ( 'get_status' === $getter_func1 || 'get_status' === $getter_func2 ) && ! in_array( $items[$order_id][$col['data']], array( 'trash' ) ) ) {
								$items[$order_id][$col['data']] = 'wc-'. $items[$order_id][$col['data']];
							}
							continue;
						}
						$col_exploded = ( ! empty( $col['src'] ) ) ? explode( "/", $col['src'] ) : array();
						if ( empty( $col_exploded ) || ! is_array( $col_exploded ) || ( is_array( $col_exploded ) && ( empty( $col_exploded[0] ) || empty( $col_exploded[1] ) ) ) ){
							continue;
						}

						// Code for fetching meta cols
						$table_nm = trim( $col_exploded[0] );
						$col_src = trim( $col_exploded[1] );
						if( ! empty( $table_nm ) && 'wc_orders_meta' === $table_nm && ! empty( $col_src ) ){
							$src = explode( "=", $col_src );
							if( ! is_array( $src ) || ( is_array( $src ) && ( empty( $src[0] ) || empty( $src[1] ) || 'meta_key' !== trim( $src[0] ) ) ) ){
								continue;
							}

							// Condition for handling fetching data for `date` cols which are not accessible using `get_meta` like in case of WC_Subscription
							if( ! empty( $col['type'] ) && ( 'sm.datetime' === $col['type'] || 'sm.date' === $col['type'] ) && ! empty( $args['meta_date_getter_func'] ) && is_callable( array( $order, $args['meta_date_getter_func'] ) ) ) {
								$src[1] = ( '_' === substr( $src[1], 0, 1) ) ? substr( $src[1], 1 ) : $src[1];
								$meta_data = $order->{$args['meta_date_getter_func']}( $src[1] );
							} else {
								$meta_data = $order->get_meta( trim( $src[1] ) );
							}

							$items[$order_id][$col['data']] = $meta_data;
							continue;
						}

						// Code for creating array for non-getting function cols
						if ( ! empty( $custom_columns_tables ) && ( is_array( $custom_columns_tables ) ) && ( ! empty( $table_nm ) ) && in_array( $table_nm, $custom_columns_tables ) ) {
							continue;
						}
						if( ! isset( $non_getter_cols[ $table_nm ] ) ){
							$non_getter_cols[ $table_nm ] = array();
						}
						if( in_array( $col['col_name'], $non_getter_cols[ $table_nm ] ) ){
							continue;
						}
						$non_getter_cols[ $table_nm ][] = $col['col_name'];
					}
				}

				$queries = array();

				if ( ! empty( $non_getter_cols ) && ! empty( $order_ids ) && is_array( $order_ids ) && count( $order_ids ) > 0 && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
					foreach ( $non_getter_cols as $table_nm => $values ) {
						if ( empty( $table_nm ) || empty( $values ) ) {
							continue;
						}
						switch( $table_nm ) {
							case 'wc_orders':
								$queries['orders'] = "SELECT " . implode( ",", array_unique( array_merge( array( 'id' ), $values ) ) ) . " FROM {$wpdb->prefix}" . $table_nm . " WHERE id IN (" . implode( ",", $order_ids ) . ")
								GROUP BY id";
								break;
							case 'wc_order_operational_data':
								$queries['wc_order_operational_data'] = "SELECT " . implode( ",", array_unique( array_merge( array( 'order_id' ), $values ) ) ) . " FROM {$wpdb->prefix}" . $table_nm . " WHERE order_id IN (" . implode( ",", $order_ids ) . ")
								GROUP BY order_id";
								break;
							case 'wc_orders_meta':
								$queries['meta'] = "SELECT order_id, meta_key AS meta_key, meta_value AS meta_value
												FROM {$wpdb->prefix}" . $table_nm . " WHERE order_id IN (" . implode( ",", $order_ids ) . ") AND meta_key IN ('" . implode( "','", $values ) . "')
												GROUP BY order_id";
								break;
							case 'wc_order_addresses':
								foreach( $values as $val ) {
									$src = explode( '_', $val );
									if ( empty( $src ) || ! is_array( $src ) || ( is_array( $src ) && ( empty( $src[0] ) || empty( $src[1] ) || ( ! empty( $src[0] ) && ! in_array( $src[0], self::$address_types ) ) ) ) ) {
										continue;
									}
									if( ! in_array( trim( $src[0] ), $address_types ) ){
										$address_types[] = trim( $src[0] );
									}
									$col = trim( substr( $val, strlen( $src[0] ) + 1 ) );
									if( ! empty( $col ) && ! in_array( $col, $columns ) ){
										$columns[] = $col;
									}
								}
								if( empty( $columns ) || empty( $address_types ) ) {
									break;
								}
								$queries['address'] = "SELECT order_id, address_type, " . implode( ",", $columns )."
													FROM {$wpdb->prefix}" . $table_nm . " WHERE order_id IN (" . implode( ",", $order_ids ) . ") AND address_type IN ('" . implode( "','", $address_types ) . "')
													GROUP BY address_type, order_id";
								break;
						}
					}

					if ( array_key_exists( 'orders', $queries ) && ( ! empty( $queries['orders'] ) ) ) {
						$results = $wpdb->get_results( $queries['orders'] ,'ARRAY_A' );
						if ( is_array( $results ) && ( ! empty( $results ) ) ) {
							foreach( $results as $result ) {
								if ( empty( $result ) || ! is_array( $result ) ) {
									continue;
								}

								$id = ( ! empty( $result['id'] ) ) ? $result['id'] : 0;
								if( empty( $id ) || ( ! empty( $id ) && ! isset( $items[$id] ) ) ){
									continue;
								}

								unset( $result['id'] );

								$items[$id] = array_merge( array_combine(
									array_map( function( $k ) {
										return 'wc_orders_' . $k;
									}, array_keys( $result ) ),
									$result
								), $items[$id] );
							}
						}
					}
					if ( array_key_exists( 'wc_order_operational_data', $queries ) && ( ! empty( $queries['wc_order_operational_data'] ) ) ) {
						$results = $wpdb->get_results( $queries['wc_order_operational_data'] ,'ARRAY_A' );
						if ( is_array( $results ) && ( ! empty( $results ) ) ) {
							foreach( $results as $result ) {
								if ( empty( $result ) || ! is_array( $result ) ) {
									continue;
								}

								$id = ( ! empty( $result['order_id'] ) ) ? $result['order_id'] : 0;
								if( empty( $id ) || ( ! empty( $id ) && ! isset( $items[$id] ) ) ){
									continue;
								}

								unset( $result['order_id'] );

								$items[$id] = array_merge( array_combine(
									array_map( function( $k ) {
										return 'wc_order_operational_data_' . $k;
									}, array_keys( $result ) ),
									$result
								), $items[$id] );
							}
						}
					}
					if ( array_key_exists( 'meta', $queries ) && ( ! empty( $queries['meta'] ) ) ) {
						$results = $wpdb->get_results( $queries['meta'] ,'ARRAY_A' );
						if( is_array( $results ) && ( ! empty( $results ) ) ) {
							foreach( $results as $result ) {
								if ( empty( $result ) || ! is_array( $result ) ) {
									continue;
								}

								$id = ( ! empty( $result['order_id'] ) ) ? $result['order_id'] : 0;
								if( empty( $id ) || ( ! empty( $id ) && ! isset( $items[$id] ) ) || empty( $result['meta_key'] ) ){
									continue;
								}

								unset( $result['order_id'] );

								$items[$id]['wc_orders_meta_meta_key_' . $result['meta_key'] . '_meta_value_' . $result['meta_key']] = ( ! empty( $result['meta_value'] ) ) ? $result['meta_value'] : '';
							}
						}
					}

					if ( array_key_exists( 'address', $queries ) && ( ! empty( $queries['address'] ) ) && ( ! empty( $address_types ) ) ) {
						$results = $wpdb->get_results( $queries['address'] ,'ARRAY_A' );
						if( is_array( $results ) && ( ! empty( $results ) ) ) {
							foreach( $results as $result ) {
								if ( empty( $result ) || ! is_array( $result ) ) {
									continue;
								}

								$id = ( ! empty( $result['order_id'] ) ) ? $result['order_id'] : 0;
								$address_type = ( ! empty( $result['address_type'] ) ) ? $result['address_type'] : '';
								if( empty( $id ) || ( ! empty( $id ) && ! isset( $items[$id] ) ) || empty( $address_type ) ){
									continue;
								}

								unset( $result['order_id'] );
								unset( $result['address_type'] );

								$items[$id] = array_merge( array_combine(
									array_map( function( $k ) use( $address_type ) {
										return 'wc_order_addresses_' . $address_type . '_' . $k;
									}, array_keys( $result ) ),
									$result
								), $items[$id] );
							}
						}
					}
				}

				$data = array(
					'items'			=> ( ! empty( $items ) ) ? array_values( $items ) : array(),
					'start'			=> $data_col_params['offset'] + $data_col_params['limit'],
					'page'			=> $data_col_params['current_page'],
					'total_pages'	=> ( isset( $orders->max_num_pages ) ) ? intval( $orders->max_num_pages ) : 0,
					'total_count'	=> ( isset( $orders->total ) ) ? intval( $orders->total ) : 0
				);
				$data_model = ( ! empty( $data_model ) && is_array( $data_model ) ) ? array_merge( $data_model, $data ) : $data;
			}

			// ===============================================================
			// Common code
			// ===============================================================

			// Code for generating custom column data
			if ( is_callable( array( 'Smart_Manager_Shop_Order', 'generate_orders_custom_column_data' ) ) ) {
				$data_model = self::generate_orders_custom_column_data( $data_model, $curr_obj->req_params );
			}

			// Code to generate order KPI data
			if( !empty( $curr_obj->req_params['sm_page'] ) && $curr_obj->req_params['sm_page'] == 1 ) {
				if( is_callable( array( 'Smart_Manager_Shop_Order', 'generate_orders_kpi_data' ) ) ) {
					$order_statuses = ( function_exists( $args['status_func'] ) ) ? call_user_func( $args['status_func'] ) : array();
					$data_model['kpi_data'] = self::generate_orders_kpi_data( $curr_obj->req_params, $order_statuses );
				}
			}

			return $data_model;
		}

		//function for additional things pre inline update
		public function pre_inline_update( $edited_data = array() ) {

			if ( empty( $edited_data ) ) {
				return $edited_data;
			}
			$prev_val = '';
			// For getting current task_id
			if ( true === array_key_exists( 'task_id', $edited_data ) ) {
				$this->task_id = intval( $edited_data['task_id'] );
				unset( $edited_data['task_id'] );
			}
			foreach( $edited_data as $id => $edited_row ) {
				if( empty( $id ) ) {
					continue;
				}
				// For fetching previous value
				if ( is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) ) {
					$prev_val = Smart_Manager_Task::get_previous_data( $id, 'posts', 'post_status' );
				}
				if( ! empty( $edited_row['posts/post_status'] ) && class_exists( 'WC_Order' ) ) {
					$order = new WC_Order( $id );
					$order->update_status( $edited_row['posts/post_status'], '', true );
					unset( $edited_data[$id]['posts/post_status'] );
				}
				if ( ( defined( 'SMPRO' ) && empty( SMPRO ) ) || empty( $this->task_id ) || empty( $edited_row['posts/post_status'] ) || empty(  property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) ) {
				   	continue;
				}
				Smart_Manager_Base::$update_task_details_params[] = array(
					'task_id' => $this->task_id,
					'action' => 'set_to',
					'status' => 'completed',
					'record_id' => $id,
					'field' => 'posts/post_status',
					'prev_val' => $prev_val,
					'updated_val' => $edited_row['posts/post_status']
				);
			}
			return $edited_data;
		}

		/**
			* Function for generating default dashboard model.
			* @param  array $dashboard_model default generated dashboard model.
			* @return array Updated default dashboard model
		*/
		public function default_dashboard_model( $dashboard_model = array() ) {
			return self::generate_hpos_dashboard_model( $this, array( 'dashboard_model' => $dashboard_model,
					'status_col_args' => array(
						'status_func' => 'wc_get_order_statuses',
						'default_status' => 'wc-pending',
						'color_codes' => $this->status_color_codes
					),
					'visible_columns' => array(
						'wc_orders_id', 'wc_orders_date_created_gmt', 'wc_order_addresses_billing_first_name', 'wc_order_addresses_billing_last_name', 'wc_orders_billing_email', 'wc_orders_status', 'wc_orders_total_amount', 'details', 'wc_orders_payment_method_title', 'shipping_method', 'coupons_used', 'line_items' )
			) );
		}

		/**
			* Mapping billing and shipping address columns.
			* @param  array $args args to get column model.
			* @param  string $field_nm field name.
			* @return array Updated args values based on address type
		*/
		public static function get_address_cols( $args = array(), $field_nm = '' ) {
			global $wpdb;
			if ( ( isset( $args['table_nm'] ) && ( 'wc_order_addresses' !== $args['table_nm'] ) ) || empty( $field_nm ) || ( ! isset( $args['col'] ) ) ) {
				return $args;
			}
			$col_titles = ( is_callable( array( 'Smart_Manager_Shop_Order', 'get_flat_table_col_titles' ) ) ) ? Smart_Manager_Shop_Order::get_flat_table_col_titles() : array();
			$updated_args = array_map( function( $address_type ) use ( $args, $field_nm, $col_titles ) {
				$args['col'] = $address_type . '_' . $field_nm;
				$args['name'] = ( ! empty( $col_titles[ $args['table_nm'] ] ) && is_array( $col_titles[ $args['table_nm'] ] ) && ! empty( $col_titles[ $args['table_nm'] ][$args['col']] ) ) ? $col_titles[ $args['table_nm'] ][$args['col']] : '';
				return $args;
			}, self::$address_types );
			return $updated_args;
		}

		/**
		 * Function hooked to filter for processing inline update.
		 *
		 * @param array $edited_data array of edited rows.
         * @param array $params function arguments.
         * @return void.
		 */
		public function orders_inline_update( $edited_data = array(), $params = array() ) {
			if ( empty( $edited_data ) || ! is_array( $edited_data ) ) {
				return;
			}
			self::process_shipping_details_update( array( 'edited_data' => $edited_data,
															'dashboard'    => $this->dashboard_key
														) );
			if ( ! empty( Smart_Manager::$sm_is_woo79 ) ) {
				( is_callable( array( 'Smart_Manager_Shop_Order', 'process_inline_update' ) ) ) ? Smart_Manager_Shop_Order::process_inline_update( $edited_data, array_merge( array( 'curr_obj' => $this ), $params ) ) : '';
			}
		}

		/**
        * Function for process inline update.
        *
        * @param array $edited_data array of edited rows.
        * @param array $params function arguments.
        * @return void.
        */
		public static function process_inline_update( $edited_data = array(), $params = array() ) {
			if ( empty( $edited_data ) || ! is_array( $edited_data ) ) {
				return $edited_data;
			}

			$curr_obj = ( ! empty( $params ) && ! empty( $params['curr_obj'] ) ) ? $params['curr_obj'] : null;
			if( empty( $curr_obj ) ){
				return $edited_data;
			}

			foreach( $edited_data as $id => $edited_row ) {
				$id = intval( $id );

				if ( empty( $id ) || empty( $edited_row ) ) {
					continue;
				}

				$order = wc_get_order( $id );

				if( empty( $order ) || ! $order instanceof WC_Order ){
					continue;
				}

				foreach( $edited_row as $key => $value ) {
					$src = explode( "/", $key );

					if( empty( $src ) || ! is_array( $src ) ){
						continue;
					}

					$update_table = trim( $src[0] );
					$update_column = trim( $src[1] );

					if( empty( $update_column ) || empty( $update_table ) ){
						continue;
					}
					if( 'wc_orders_meta' === $update_table && sizeof( $src ) > 2 ) {
						$meta_src = explode( "=", $src[2] );
						if( empty( $meta_src ) || ! is_array( $meta_src ) ){
							continue;
						}
						$update_column = ( ! empty( $meta_src[1] ) ) ? trim( $meta_src[1] ) : $update_column;
					}

					$type = 'text';
					if( 'wc_orders_meta' !== $update_table && ! empty( $params['data_cols_numeric'] ) && is_array( $params['data_cols_numeric'] ) && in_array( $update_column, $params['data_cols_numeric'] ) ){
						$type = 'numeric';
					} else if( 'wc_orders_meta' === $update_table && ! empty( $params['data_date_cols'] ) && is_array( $params['data_date_cols'] ) && in_array( $update_column, $params['data_date_cols'] ) ) { //condition for handling of meta date columns
						$type = 'sm.date';
					}

					if ( is_callable( array( 'Smart_Manager_Shop_Order', 'update_order_data') ) ) {
						self::update_order_data( array(
							'id' => $id,
							'table_nm' => $update_table,
							'col_nm' => $update_column,
							'value' => $value,
							'type' =>  $type,
							'order_obj' => $order,
							'numeric_cols_decimal_places' => ( ! empty( $params['numeric_cols_decimal_places'] ) ) ? $params['numeric_cols_decimal_places'] : array(),
							'meta_date_getter_func' => ( ! empty( $params['meta_date_getter_func'] ) ) ? $params['meta_date_getter_func'] : '',
							'meta_date_setter_func' => ( ! empty( $params['meta_date_setter_func'] ) ) ? $params['meta_date_setter_func'] : '',
							'task_id' => ( ! empty( $params['task_id'] ) ) ? $params['task_id'] : $curr_obj->task_id,
							'process' => 'inline_edit',
							'update_flag' => false,
						) );
					}
				}
			}
		}

		/**
		 * Function to get prev_val
		 *
		 * @param string $prev_val received prev_val.
		 * @param array $args array has id, table name, column name.
		 * @return string $prev_val updated prev_val
		 */
		public static function get_previous_value( $prev_val = '', $args = array() ) {
			if( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['id'] ) || empty( $args['table_nm'] ) || empty( $args['col_nm'] ) ){
				return $prev_val;
			}

			global $wpdb;

			$col_property = ( is_callable( array( 'Smart_Manager_Shop_Order', 'get_property_name_for_col' ) ) ) ? Smart_Manager_Shop_Order::get_property_name_for_col( $args['col_nm'], $args['table_nm'] ) : $args['col_nm'];
			$getter_func1 = 'get_' . $col_property;
			$getter_func2 = 'get' . $col_property;

			if( empty( $args['order_obj'] ) ){
				if( ! empty( $args['curr_obj_getter_func'] ) && ! empty( $args['curr_obj_class_nm'] ) && function_exists( $args['curr_obj_getter_func'] ) && class_exists( $args['curr_obj_class_nm'] ) ) {
					$args['order_obj'] = call_user_func( $args['curr_obj_getter_func'], $args['id'] );
					if( ! $args['order_obj'] instanceof $args['curr_obj_class_nm'] ){
						return $prev_val;
					}
				} else {
					$args['order_obj'] = wc_get_order( $args['id'] );
				}
			}

			switch ( $args['table_nm'] ) {
				case ( ( ! empty( $getter_func1 ) && is_callable( array( $args['order_obj'], $getter_func1 ) ) ) || ( ! empty( $getter_func2 ) && is_callable( array( $args['order_obj'], $getter_func2 ) ) ) ):
					$val = ( is_callable( array( $args['order_obj'], $getter_func1 ) ) ) ? $args['order_obj']->{$getter_func1}() : $args['order_obj']->{$getter_func2}();
					if( ! empty( $val ) && function_exists( 'wc_format_datetime' ) && $val instanceof WC_DateTime && is_callable( array( $val, 'date' ) ) ) {
						$val = $val->date( 'Y-m-d H:i:s' );
					}
					return ( ! empty( $val ) ) ? $val : $prev_val;
				case 'wc_orders_meta':
					// Condition for handling fetching data for `date` cols which are not accessible using `get_meta` like in case of WC_Subscription
					if( ! empty( $args['type'] ) && ( 'sm.datetime' === $args['type'] || 'sm.date' === $args['type'] ) && ! empty( $args['meta_date_getter_func'] ) && is_callable( array( $args['order_obj'], $args['meta_date_getter_func'] ) ) ) {
						$col_nm = ( '_' === substr( $args['col_nm'], 0, 1) ) ? substr( $args['col_nm'], 1 ) : $args['col_nm'];
						return $args['order_obj']->{$args['meta_date_getter_func']}( $col_nm );
					} else {
						return $args['order_obj']->get_meta( $args['col_nm'] );
					}
				case 'wc_orders':
					if( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
						return $prev_val;
					}
					$prev_val = $wpdb->get_var( "SELECT " . $args['col_nm'] . "
												FROM {$wpdb->prefix}". $args['table_nm'] . "
												WHERE id = " . $args['id'] );
					return is_null( $prev_val ) ? '' : $prev_val;
				case 'wc_order_operational_data':
					if( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
						return $prev_val;
					}
					$prev_val = $wpdb->get_var( "SELECT " . $args['col_nm'] . "
												FROM {$wpdb->prefix}". $args['table_nm'] . "
												WHERE order_id = " . $args['id'] );
					return is_null( $prev_val ) ? '' : $prev_val;
				case 'wc_order_addresses':
					if( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
						return $prev_val;
					}

					$src = explode( '_', $args['col_nm'] );
					if ( ( ( ! is_array( $src ) ) || empty( $src ) ) || ( is_array( $src ) && ( empty( $src[0] ) || empty( $src[1] ) || ! in_array( $src[0], self::$address_types ) ) ) ){
						return;
					}
					$col = trim( substr( $args['col_nm'], strlen( $src[0] ) + 1 ) );

					$prev_val = $wpdb->get_var( "SELECT " . $col . "
												FROM {$wpdb->prefix}". $args['table_nm'] . "
												WHERE order_id = " . $args['id'] ."
												AND address_type = '". $src[0] ."'" );
					return is_null( $prev_val ) ? '' : $prev_val;
				case 'woocommerce_order_items':
					$args['value'] = $args['id'];
					$args['dashboard_key'] = ( is_callable( array( $args['order_obj'], 'get_type' ) ) ) ? $args['order_obj']->get_type() : '';
					$args = array_merge( $args, array( 'selected_column_name' => $args['col_nm'] ) );
					return ( is_callable( array( 'Smart_Manager_Pro_Shop_Order', 'get_previous_shipping_details' ) ) ) ? Smart_Manager_Pro_Shop_Order::get_previous_shipping_details( $args ) : $prev_val;
			}

			return $prev_val;
		}

		/**
		 * Function to update order data in wc_orders, wc_orders_meta and wc_order_addresses tables
		 *
		 * @param array $args array has id, table name, column name, order object to update the tables.
		 * @return boolean returns true if updated successfully else returns false
		 */
		public static function update_order_data( $args = array() ) {
			if ( empty( $args ) || empty( $args['id'] ) || empty( $args['table_nm'] ) || empty( $args['col_nm'] ) || empty( $args['order_obj'] ) ) {
				return false;
			}

			global $wpdb;
			$col_property = ( is_callable( array( 'Smart_Manager_Shop_Order', 'get_property_name_for_col' ) ) ) ? Smart_Manager_Shop_Order::get_property_name_for_col( $args['col_nm'], $args['table_nm'] ) : $args['col_nm'];
			$setter_func1 = 'set_' . $col_property;
			$setter_func2 = 'set' . $col_property;

			$args['type'] = ( ! empty( $args['date_type'] ) ) ? $args['date_type'] : $args['type']; //Added fr BE. Need to change it to `data_type`

			if( ! empty( $args['process'] ) && 'inline_edit' === $args['process'] ){
				$prev_val = self::get_previous_value( '', $args );
			}

			switch( $args['table_nm'] ) {
				case ( ( ! empty( $setter_func1 ) && is_callable( array( $args['order_obj'], $setter_func1 ) ) ) || ( ! empty( $setter_func2 ) && is_callable( array( $args['order_obj'], $setter_func2 ) ) ) ):
					$result = ( is_callable( array( $args['order_obj'], $setter_func1 ) ) ) ? $args['order_obj']->{$setter_func1}( $args['value'] ) : $args['order_obj']->{$setter_func2}( $args['value'] );
					$args['order_obj']->save();
					break;
				case 'wc_orders_meta':
					// Condition for handling fetching data for `date` cols which are not accessible using `get_meta` like in case of WC_Subscription
					// Calling `update_meta_data` in case of WC_Subscription was causing duplicate entry in meta table
					if( ! empty( $args['type'] ) && ( 'sm.datetime' === $args['type'] || 'sm.date' === $args['type'] ) && ! empty( $args['meta_date_setter_func'] ) && is_callable( array( $args['order_obj'], $args['meta_date_setter_func'] ) ) ) {
						$col_nm = ( '_' === substr( $args['col_nm'], 0, 1) ) ? substr( $args['col_nm'], 1 ) : $args['col_nm'];
						$args['order_obj']->{$args['meta_date_setter_func']}( array( $col_nm => $args['value'] ) );
					} else {
						$args['order_obj']->update_meta_data( $args['col_nm'], $args['value'] );
					}
					$args['order_obj']->save();
					break;
				case 'wc_orders':
					if( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ){
						break;
					}
					$val = "'".$args['value']."'";
					if( ! empty( $args['type'] ) && 'numeric' === $args['type'] ){
						$val = ( ! empty( $args['numeric_cols_decimal_places'] ) && ! empty( $args['numeric_cols_decimal_places'][$args['col_nm']] ) ) ? floatval( $args['value'], $args['numeric_cols_decimal_places'][$args['col_nm']] ) : intval( $args['value'] );
					}
					$wpdb->query( "UPDATE {$wpdb->prefix}". $args['table_nm'] . "
									SET " . $args['col_nm'] . " = ". $val ."
									WHERE id = " . $args['id'] );
					break;
				case 'wc_order_operational_data':
					if( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ){
						break;
					}
					$val = "'".$args['value']."'";
					if( ! empty( $args['type'] ) && 'numeric' === $args['type'] ){
						$val = ( ! empty( $args['numeric_cols_decimal_places'] ) && ! empty( $args['numeric_cols_decimal_places'][$args['col_nm']] ) ) ? floatval( $args['value'], $args['numeric_cols_decimal_places'][$args['col_nm']] ) : intval( $args['value'] );
					}
					$wpdb->query( "UPDATE {$wpdb->prefix}". $args['table_nm'] . "
									SET " . $args['col_nm'] . " = ". $val ."
									WHERE order_id = " . $args['id'] );

					break;
				case 'wc_order_addresses':
					if( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ){
						break;
					}
					$src = explode( '_', $args['col_nm'] );
					if ( ( ( ! is_array( $src ) ) || empty( $src ) ) || ( is_array( $src ) && ( empty( $src[0] ) || empty( $src[1] ) || ! in_array( $src[0], self::$address_types ) ) ) ){
						return;
					}
					$col = trim( substr( $args['col_nm'], strlen( $src[0] ) + 1 ) );

					$id = $wpdb->get_var( "SELECT id
												FROM {$wpdb->prefix}". $args['table_nm'] . "
												WHERE order_id = " . $args['id'] ."
												AND address_type = '". $src[0] ."'" );

					if( ! empty( $id ) ){
						$query = "UPDATE {$wpdb->prefix}". $args['table_nm'] . "
								SET " . $col ." = '". $args['value'] ."'
								WHERE order_id = " . $args['id'] ."
									AND address_type = '". $src[0] ."'";
					} else{
						$query = "INSERT INTO {$wpdb->prefix}". $args['table_nm'] . "(order_id, address_type, ". $col .")
									VALUES(". $args['id'] .",'". $src[0] ."','". $args['value'] ."')";
					}

					$wpdb->query( $query );
					break;
			}

			if ( ( defined( 'SMPRO' ) && empty( SMPRO ) ) || empty( $args['task_id'] ) || empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) || empty( $args['process'] ) || ( ! empty( $args['process'] ) && 'inline_edit' !== $args['process'] ) ) {
				return $args['update_flag'];
		 	}

			Smart_Manager_Base::$update_task_details_params[] = array(
				'task_id' => $args['task_id'],
				'action' => 'set_to',
				'status' => 'completed',
				'record_id' => $args['id'],
				'field' => $args['table_nm'] . '/' . $args['col_nm'],
				'prev_val' => $prev_val,
				'updated_val' => $args['value']
			);
		}

		/**
		 * Function for modifying table types for advanced search.
		 *
		 * @param array $table_types array of table types.
		 * @return array $table_types updated array of table types.
		 */
		public static function sm_order_search_table_types( $table_types = array() ){
			if ( empty( Smart_Manager::$sm_is_woo79 ) || empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
				return $table_types;
			}
			$table_types['flat'] = array_merge( array(
				'wc_orders'  					=> 'id',
				'wc_order_addresses' 			=> 'order_id',
				'wc_order_operational_data' 	=> 'order_id'
			), ( ! empty( $table_types['flat'] ) ? $table_types['flat'] : array() ) );
			$table_types['meta']['wc_orders_meta'] = 'order_id';
			return $table_types;
		}

		/**
		 * Function for getting order ids for simple search
		 *
		 * @param string $search_text search text.
		 * @param string $dashboard dashboard name.
		 * @return array filtered order ids.
		 */
		public static function get_filtered_order_ids( $search_text = '', $dashboard = '' ) {
			global $wpdb;
            $userOrderIds = $skuOrderIds = $itemNameskuOrderIds = $billing_email_order_ids = array();

			$pIds  = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(post_id) FROM {$wpdb->prefix}postmeta
			              									WHERE meta_key = %s
			                 								AND meta_value LIKE %s", '_sku', '%' . $wpdb->esc_like($search_text) . '%') );
			if( count( $pIds ) > 0 ) {
				$skuOrderIds = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(order_id)
							                                    FROM {$wpdb->prefix}woocommerce_order_items AS woocommerce_order_items
							                                    	LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woocommerce_order_itemmeta USING ( order_item_id )
							                                    WHERE woocommerce_order_itemmeta.meta_key IN ( %s, %s )
							                                    	AND woocommerce_order_itemmeta.meta_value IN ( ". implode( ',', $pIds ) ." )", '_product_id', '_variation_id') );
			}

			//Query to perform simple search in either of item names i.e. product_name, shipping_title, coupon_code
			$itemNameskuOrderIds = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(order_id)
									                                FROM {$wpdb->prefix}woocommerce_order_items
									                                WHERE order_item_name LIKE %s", '%' . $wpdb->esc_like($search_text) . '%') );

			//Query for getting the orders based on the email entered in the Search Box - using 'billing_email'
			$billing_email_order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(p.ID)
            														FROM {$wpdb->prefix}posts AS p
            															JOIN {$wpdb->prefix}postmeta AS pm
            																ON( pm.post_id = p.ID
            																	AND p.post_type = %s
            																	AND pm.meta_key = %s )
            														WHERE pm.meta_value LIKE %s", $dashboard, '_billing_email', '%' . $wpdb->esc_like($search_text) . '%' ) );

			//Query for getting the user_id based on the email entered in the Search Box
            $userIds = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(id)
														FROM {$wpdb->users}
                    									WHERE user_email LIKE %s", '%' . $wpdb->esc_like($search_text) . '%' ) );
            if( count( $userIds ) > 0 ) {
            	$userOrderIds = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(p.ID)
            														FROM {$wpdb->prefix}posts AS p
            															JOIN {$wpdb->prefix}postmeta AS pm
            																ON( pm.post_id = p.ID
            																	AND p.post_type = %s
            																	AND pm.meta_key = %s )
            														WHERE pm.meta_value IN( ". implode( ',', $userIds ) ." )", $dashboard, '_customer_user' ) );
            }

            if( !empty( $skuOrderIds ) || !empty( $itemNameskuOrderIds ) || !empty( $userOrderIds ) || ! empty( $billing_email_order_ids ) ) {
            	return array_unique( array_merge( $skuOrderIds, $itemNameskuOrderIds, $userOrderIds, $billing_email_order_ids ) );
            }
		}

		/**
		 * Function to modify the advanced search query formatted array
		 *
		 * @param array $advanced_search_query advanced search query.
		 * @param array $search_params search params.
		 * @return array $advanced_search_query formatted advanced search query.
		 */
		public static function sm_order_addresses_search_query_formatted( $advanced_search_query = array(), $search_params = array() ) {
			global $wpdb;
			if ( ! empty( $search_params ) ) {
				$col_name = explode( '_', $search_params['search_string']['col_name'] );
				$adress_type = $col_name[0];
				unset($col_name[0]);
				$search_params['search_col'] = implode( '_', $col_name );
					if( ( $wpdb->prefix . 'wc_order_addresses' ) === $search_params['search_string']['table_name'] ) {
						$advanced_search_query['cond_wc_order_addresses'] = $search_params['search_string']['table_name'] . '.' . $search_params['search_col'] . $advanced_search_query['cond_wc_order_addresses_formatted_search_operator'] . " AND address_type LIKE '" . $adress_type . "'";
					}
			}
			return $advanced_search_query;
		}

		/**
		 * Function hooked to filter for modifying query clauses.
		 *
		 * @param array $clauses query clauses.
		 * @param object $args OrdersTableQuery object.
		 * @param array $args Query args.
		 *
		 * @return array $clauses array of modified query clauses.
		 */
		public function modify_orders_table_query_clauses( $clauses = array(), $query_obj = null, $args = array() ){
			return ( is_callable( array( 'Smart_Manager_Shop_Order', 'modify_table_query_clauses' ) ) ) ? Smart_Manager_Shop_Order::modify_table_query_clauses( $clauses, array( 'curr_obj' => $this, 'query_obj' => $query_obj, 'query_args' => $args ) ) : $clauses;
		}

		/**
		 * Function for modifying the table query clauses
		 *
		 * @param array $clauses query clauses.
		 * @param array $args function arguments.
		 *
		 * @return array $clauses array of modified query clauses.
		 */
		public static function modify_table_query_clauses( $clauses = array(), $args = array() ){

			if( empty( $args ) ){
				return $clauses;
			}

			$curr_obj = ( ! empty( $args['curr_obj'] ) ) ? $args['curr_obj'] : null;

			if( empty( $clauses ) || empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) || empty( $curr_obj ) || ( ! empty( $curr_obj ) && empty( $curr_obj->req_params ) ) ) {
				return $clauses;
			}

			global $wpdb;
			if( ! empty( $curr_obj->req_params['advanced_search_query'] ) && '[]' !== $curr_obj->req_params['advanced_search_query'] && ( empty( $clauses['join'] ) || strpos( $clauses['join'],'sm_advanced_search_temp') === false ) ) {
				$clauses['join'] .= " JOIN {$wpdb->base_prefix}sm_advanced_search_temp
                            	ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = {$wpdb->prefix}wc_orders.id)";
			}

			if( ! empty( $curr_obj->req_params['search_text'] ) && false !== stripos( $clauses['join'], 'inner join' ) ) {
				$clauses['join'] = str_ireplace( 'inner join', 'LEFT JOIN', $clauses['join'] );
			}

			// code for fetching the Order status KPI results
			self::$kpi_query_results = $wpdb->get_results( "SELECT {$wpdb->prefix}wc_orders.status,
															COUNT( DISTINCT( {$wpdb->prefix}wc_orders.id ) ) AS count
															FROM {$wpdb->prefix}wc_orders "
															. $clauses['join'] ." ".
															( ( ! empty( $clauses['where'] ) ) ? ' WHERE ' : '' ) . $clauses['where'] ."
															GROUP BY {$wpdb->prefix}wc_orders.status", 'ARRAY_A' );

			//Code for saving the post_ids in case of simple search
			if( ( defined('SMPRO') && true === SMPRO ) && ! empty( $curr_obj->req_params['search_text'] ) || ( ! empty( $curr_obj->req_params['advanced_search_query'] ) && $curr_obj->req_params['advanced_search_query'] != '[]' ) ) {
				$order_ids = $wpdb->get_col( "SELECT DISTINCT {$wpdb->prefix}wc_orders.id
															FROM {$wpdb->prefix}wc_orders "
															. $clauses['join'] ." ".
															( ( ! empty( $clauses['where'] ) ) ? ' WHERE ' : '' ) . $clauses['where'] );
				$order_ids = ( ! empty( $order_ids ) && is_array( $order_ids ) ) ? implode( ",", $order_ids ) : '';
				set_transient( 'sa_sm_search_post_ids', $order_ids , WEEK_IN_SECONDS );
			}

			if ( ( ! empty( $curr_obj->req_params[ 'selected_ids' ] ) && '[]' !== $curr_obj->req_params[ 'selected_ids' ] ) && empty( $curr_obj->req_params['storewide_option'] ) && ( ! empty( $curr_obj->req_params[ 'cmd' ] ) && ( 'get_export_csv' === $curr_obj->req_params[ 'cmd' ] ) ) ) {
				$selected_ids = json_decode( stripslashes( $curr_obj->req_params[ 'selected_ids' ] ) );
				$clauses['where'] .= ( ! empty( $selected_ids ) ) ? " AND {$wpdb->prefix}wc_orders.id IN (" . implode( ",", $selected_ids ) . ")" : '';
			}

			if( empty( $curr_obj->req_params['sort_params'] ) || ( ! empty( $curr_obj->req_params['sort_params'] ) && ( empty( $curr_obj->req_params['sort_params']['column'] ) || empty( $curr_obj->req_params['sort_params']['sortOrder'] ) ) ) ){
				return $clauses;
			}

			// Code to get all valid data cols
			$store_model_transient = ( ! empty( $curr_obj->store_col_model_transient_option_nm ) ) ? get_transient( $curr_obj->store_col_model_transient_option_nm ) : '';
			if( ! empty( $store_model_transient ) && !is_array( $store_model_transient ) ) {
				$store_model_transient = json_decode( $store_model_transient, true );
			}
			$col_model = ( ! empty( $store_model_transient['columns'] ) ) ? $store_model_transient['columns'] : array();

			if( empty( $col_model ) ){
				return $clauses;
			}

			$data_cols = $numeric_meta_cols = array();

			foreach ($col_model as $col) {
				if( ! empty( $col['hidden'] ) && ! empty( $col['data'] ) ) {
					continue;
				}

				$type = ( ! empty( $col['type'] ) ) ? $col['type'] : '';
				$validator = ( !empty( $col['validator'] ) ) ? $col['validator'] : '';

				$col_exploded = ( !empty( $col['src'] ) ) ? explode( "/", $col['src'] ) : array();

				if( empty( $col_exploded ) ) {
					continue;
				}

				if ( sizeof($col_exploded) > 2) {
					$col_meta = explode("=",$col_exploded[1]);
					$col_nm = $col_meta[1];
				} else {
					$col_nm = $col_exploded[1];
				}

				$data_cols[] = $col_nm;

				if( ! empty( $col_meta[0] ) && 'wc_orders_meta' === $col_meta[0] && ( 'number' === $type || 'numeric' === $type || 'customNumericTextEditor' === $validator ) ) {
					$numeric_meta_cols[] = $col_nm;
				}
			}

			$sort_params = $curr_obj->build_query_sort_params( array( 'sort_params' => $curr_obj->req_params['sort_params'],
																		'numeric_meta_cols' => $numeric_meta_cols,
																		'data_cols' => $data_cols
															) );
			if( empty( $sort_params ) || ( ! empty( $sort_params ) && ( empty( $sort_params['table'] ) || empty( $sort_params['column_nm'] ) || empty( $sort_params['sortOrder'] ) ) ) ) {
				return $clauses;
			}

			$sort_params['column_nm'] = ( 'meta_value_num' === $sort_params['column_nm'] ) ? 'meta_value+0' : $sort_params['column_nm'];
			$order_ids = array();

			if( in_array( $sort_params['table'], $curr_obj->flat_tables ) ){
				if( 'wc_orders' === $sort_params['table'] ){
					$clauses['orderby'] = $wpdb->prefix . $sort_params['table'] . '.' . $sort_params['column_nm'] . ' ' . $sort_params['sortOrder'];
					return $clauses;
				} else if( 'wc_order_operational_data' === $sort_params['table'] ){
					$order_ids = $wpdb->get_col( "SELECT DISTINCT order_id
												FROM ". $wpdb->prefix.$sort_params['table'] ."
												ORDER BY ". $sort_params['column_nm'] ." ". $sort_params['sortOrder'] );
				} else {
					$addresses_src = explode( '_', $sort_params['column_nm'] );

					if( empty( $addresses_src ) || ( ! empty( $addresses_src ) && is_array( $addresses_src ) && ( empty( $addresses_src[0] ) || empty( $addresses_src[1] || ! in_array( $addresses_src[0], self::$address_types ) ) ) ) ){
						return $clauses;
					}
					$col = trim( substr( $sort_params['column_nm'], strlen( $addresses_src[0] ) + 1 ) );

					$order_ids = $wpdb->get_col( "SELECT DISTINCT order_id
												FROM ". $wpdb->prefix.$sort_params['table'] ."
												WHERE address_type = '". $addresses_src[0] ."'
												ORDER BY ". $col ." ". $sort_params['sortOrder'] );
				}
			} else if( 'wc_orders_meta' === $sort_params['table'] && ! empty( $sort_params['sort_by_meta_key'] ) ){
				$order_ids = $wpdb->get_col( "SELECT DISTINCT order_id
											FROM ". $wpdb->prefix.$sort_params['table'] ."
											WHERE meta_key = '". $sort_params['sort_by_meta_key'] ."'
											ORDER BY ". $sort_params['column_nm'] ." ". $sort_params['sortOrder'] );
			}

			if( empty( $order_ids ) ){
				return $clauses;
			}

			$option_name = 'sm_data_model_sorted_ids';
			update_option( $option_name, implode( ',', $order_ids ), 'no' );

			$clauses['orderby'] = " FIND_IN_SET( " . $wpdb->prefix . "wc_orders.id, ( SELECT option_value FROM " . $wpdb->prefix . "options WHERE option_name = '" . $option_name . "' ) ) ";
			return $clauses;
		}

		/**
		 * Function for handling delete functionality.
		 *
		 * @param boolean $result result of the delete process.
		 * @param int $id order_id of the record to be deleted.
		 * @param array $params array of additional params for delete functionality.
		 * @return boolean $result result of the delete process.
		 */
		public static function process_delete( $result = false, $order_id = 0, $params = array() ) {
			$order_id = intval( $order_id );
			if ( empty( $order_id ) ) {
				return $result;
			}
			$force_delete = ( ! empty( $params['delete_permanently'] ) ) ? true : false;

			if( empty( Smart_Manager::$sm_is_woo79 ) ){
				$result = ( ( $force_delete ) ? wp_delete_post( $order_id, $force_delete ) : wp_trash_post( $order_id ) );
				return ( ! empty( $result ) ) ? true : false;
			}

			//Code for WC-HPOS handling
			$order = wc_get_order( $order_id );
            if( $order && $order instanceof WC_Order ) {
				return $order->delete( $force_delete );
			}
		}

		/**
		 * Function for order trash.
		 *
		 * @param int $count count of deleted order ids.
		 * @param array $args array of additional params for trash functionality.
		 * @return int $count count of deleted order ids.
		 */
		public static function order_trash( $count = 0, $args = array() ) {
			$ids = ( ! empty( $args['ids'] ) ) ? $args['ids'] : array();
			if( empty( $ids ) ){
				return $count;
			}

			$count = 0;
			foreach( $ids as $id ){
				$result = self::process_delete( false, intval( $id ) );
				if( ! empty( $result ) ){
					$count++;
				}
			}
			return $count;
		}

		/**
		 * Function for creating col_model for 'statuses' column.
		 *
		 * @param array $col 'statuses' default col_model.
		 * @param array $args function arguments.
		 * @return array $col 'statuses' updated col_model.
		 */
		public static function generate_status_col_model( $col = array(), $args = array() ){
			if( empty( $args ) || empty( $args['curr_obj'] ) || empty( $args['status_func'] ) || ( ! empty( $args['status_func'] ) && ! function_exists( $args['status_func'] ) ) ) {
				return $col;
			}

			$statuses = call_user_func( $args['status_func'] );
			if( empty( $statuses ) || ! is_array( $statuses ) ){
				return $col;
			}

			$color_codes = ( ! empty( $args['color_codes'] ) ) ? $args['color_codes'] : array();

			// Code to handle display of 'trash' value for 'status' -- not to be saved in transient
			if( ! empty( $args['curr_obj']->is_show_trash_records() ) ) {
				$statuses['trash'] = __( 'Trash', 'smart-manager-for-wp-e-commerce' );
				if( ! isset( $color_codes['red'] ) ){
					$color_codes['red'] = array();
				}
				$color_codes['red'][] = 'trash';
			}
			$keys = ( ! empty( $statuses ) ) ? array_keys( $statuses ) : array();

			$col['type'] = 'dropdown';
			$col['editor'] = 'select';
			$col['strict'] = true;
			$col['allowInvalid'] = false;
			$col['values'] = $statuses;
			$col['selectOptions'] = $col['values'];
			$col['defaultValue'] = ( !empty( $keys[0] ) ) ? $keys[0] : $args['default_status'];
			$col['save_state'] = true;
			$col['renderer'] = 'selectValueRenderer';
			$col['colorCodes'] = apply_filters( 'sm_' . $args['curr_obj']->dashboard_key . '_status_color_codes', $color_codes );
			$col['search_values'] = array();
			foreach( $statuses as $key => $value ) {
				$col['search_values'][] = array('key' => $key, 'value' => $value);
			}

			return $col;
		}

		/**
		 * Static function for creating HPOS dashboard model.
		 *
		 * @param array $curr_obj current class object.
		 * @param array $args array of required arguments.
		 * @return array updated dashboard model.
		 */
		public static function generate_hpos_dashboard_model( $curr_obj = null, $args = array() ) {
			if( empty( $curr_obj ) ){
				return ( ! empty( $args['dashboard_model'] ) ) ? $args['dashboard_model'] : array();
			}

			$col_model = array();

			foreach( $curr_obj->flat_tables as $table_nm ) {
				if( ! is_callable( array( $curr_obj, 'get_flat_table_columns' ) ) ){
					continue;
				}
				$params = array( 'table_nm' => $table_nm,
								'dashboard_model' => $args['dashboard_model']
							);
				if( ! empty( $args['visible_columns'] ) ) {
					$params['visible_columns'] = $args['visible_columns'];
				}
				$col_model = array_merge( $col_model, $curr_obj->get_flat_table_columns( $params ) );
			}
			$meta_table_columns = array();
			if( is_callable( array( $curr_obj, 'get_meta_table_columns' ) ) ){
				$params = array( 'meta_table_nm' => 'wc_orders_meta', 'table_nm' => 'wc_orders', 'child_id' => 'order_id', 'parent_id' => 'id', 'post_type' => 'type' );
				if( ! empty( $args['visible_columns'] ) ) {
					$params['visible_columns'] = $args['visible_columns'];
				}
				$meta_table_columns = $curr_obj->get_meta_table_columns( $params );
			}
			$col_model = ( ! empty( $meta_table_columns ) ) ? array_merge( $col_model, $meta_table_columns) : $col_model;

			$status_col_index = sa_multidimesional_array_search( 'wc_orders_status', 'data', $col_model );
			if( isset( $col_model[$status_col_index] ) && ! empty( $args['status_col_args'] ) && is_callable( array( 'Smart_Manager_Shop_Order', 'generate_status_col_model' ) ) ){
				$col_model[$status_col_index] = self::generate_status_col_model( $col_model[$status_col_index],
						array_merge( array( 'curr_obj' => $curr_obj ), $args['status_col_args'] ) );
			}

			if( is_callable( array( 'Smart_Manager_Shop_Order', 'generate_orders_custom_column_model' ) ) ) {
				$col_model = self::generate_orders_custom_column_model( $col_model, $curr_obj->dashboard_key );
			}

			return array('tables' => array(
				'wc_orders' => array(
					'pkey' => 'ID',
					'join_on' => '',
					'where' => array(
						'type' 	=> $curr_obj->dashboard_key,
						'status' 	=> 'any'
					)
				),
				'wc_order_addresses' => array(
					'pkey' => 'order_id',
					'join_on' => 'wc_order_addresses.order_id = wc_orders.ID',
					'where' => array()
				),
				'wc_order_operational_data' => array(
					'pkey' => 'order_id',
					'join_on' => 'wc_order_operational_data.order_id = wc_orders.ID',
					'where' => array()
				),
				'wc_orders_meta' 	 => array(
					'pkey' => 'order_id',
					'join_on' => 'wc_orders_meta.order_id = wc_orders.ID', // format current_table.pkey = joinning table.pkey
					'where' => array(),
				)
				),
				'display_name'   => __( ucwords( str_replace( '_', ' ', $curr_obj->dashboard_key ) ), 'smart-manager-for-wp-e-commerce' ),
				'columns'        => $col_model,
				'per_page_limit' => '', // blank, 0, -1 all values refer to infinite scroll.
				'treegrid'       => false, // flag for setting the treegrid.
			);
		}

		/**
		 * Static function for handling HPOS db migration for column model.
		 *
		 * @param array $col_model current saved column model.
		 * @return array $col_model updated column model.
		 */
		public static function migrate_col_model( $col_model = array() ) {

			if( empty( $col_model ) || empty( $col_model['columns'] ) ) {
				return array();
			}

			$tables = array(
				'wc_orders' => 'Automattic\WooCommerce\Database\Migrations\CustomOrderTable\PostToOrderTableMigrator',
				'wc_order_addresses/billing' => 'Automattic\WooCommerce\Database\Migrations\CustomOrderTable\PostToOrderAddressTableMigrator',
				'wc_order_addresses/shipping' => 'Automattic\WooCommerce\Database\Migrations\CustomOrderTable\PostToOrderAddressTableMigrator',
				'wc_order_operational_data' => 'Automattic\WooCommerce\Database\Migrations\CustomOrderTable\PostToOrderOpTableMigrator'
			);

			$col_mapping = array(
				'posts_id' 					=> 'wc_orders_id',
				'posts_post_status' 		=> 'wc_orders_status',
				'posts_post_date_gmt'		=> 'wc_orders_date_created_gmt',
				'posts_post_modified_gmt'	=> 'wc_orders_date_updated_gmt',
				'posts_post_parent'			=> 'wc_orders_parent_order_id',
				'posts_post_type'			=> 'wc_orders_type',
				'posts_post_excerpt'		=> 'wc_orders_customer_note'
			);

			foreach( $tables as $table => $class ){
				if( ! class_exists( $class ) ){
					continue;
				}
				$key = '';
				$table = explode( "/", $table );
				if( is_array( $table ) && ! empty( $table ) && ! empty( trim( $table[0] ) ) ) {
					$key = ( ! empty( $table[1] ) ) ? trim( $table[1] ) : '';
					$table = trim( $table[0] );
				}

				$obj = new $class($key);

				if( empty( $obj ) ){
					continue;
				}

				if( is_callable( array( $obj, 'get_meta_column_config' ) ) ){
					$cols = $obj->get_meta_column_config();
					if( empty( $cols ) ) {
						continue;
					}

					foreach( $cols as $col => $meta ){
						if( ! is_array( $meta ) || ( is_array( $meta ) && empty( $meta['destination'] ) ) ) {
							continue;
						}
						$old_key = "postmeta_meta_key_{$col}_meta_value_{$col}";
						$new_key = $table .'_'. ( ( ! empty( $key ) ) ? $key . '_' : '' ) . $meta['destination'];
						if( ! empty( $col_mapping[$old_key] ) || in_array( $new_key, $col_mapping ) ) {
							continue;
						}

						$col_mapping[$old_key] = $new_key;
					}
				}
			}

			// Code to update the column model
			$wc_class = 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController';
			if( ! class_exists( $wc_class ) || ( class_exists( $wc_class ) && empty( $wc_class::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION ) ) ){
				return $col_model;
			}

			$is_hpos_enabled = ( 'yes' === get_option( $wc_class::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, 'no' ) ) ? true : false;

			$updated_cols = array();
			foreach( $col_model['columns'] as $col => $meta ){
				$key = ( empty( $is_hpos_enabled ) ) ? array_search( $col, $col_mapping ) : ( ( ! empty( $col_mapping[ $col ] ) ) ? $col_mapping[ $col ] : '' );
				if( empty( $key ) || empty( $meta ) || ( ! empty( $key ) && ! empty( $updated_cols[$key] ) ) ) {
					continue;
				}

				if( ! empty( $key ) ){
					$updated_cols[$key] = $meta;
					continue;
				}

				// Handling for meta columns
				if( 'postmeta' === substr( $col, 0, 8 ) || 'wc_orders_meta' === substr( $col, 0, 14 ) ){
					if( $is_hpos_enabled ){
						$updated_cols[str_replace( 'postmeta', 'wc_orders_meta', $col )] = $meta;
						continue;
					} else {
						$updated_cols[str_replace( 'wc_orders_meta', 'postmeta', $col )] = $meta;
						continue;
					}
				}

				// Handling for custom columns
				if( 'custom' === substr( $col, 0, 6 ) ){
					$updated_cols[$col] = $meta;
					continue;
				}
			}

			$col_model['columns'] = ( ! empty( $updated_cols ) ) ? $updated_cols : $col_model['columns'];

			$col = ( ! empty( $col_model['sort_params'] ) && ! empty( $col_model['sort_params']['column'] ) ) ? $col_model['sort_params']['column'] : '';
			if( empty( $col ) ) {
				return $col_model;
			}

			$col = str_replace( array( '/', '=' ), '_', $col );
			$key = ( empty( $is_hpos_enabled ) ) ? array_search( $col, $col_mapping ) : ( ( ! empty( $col_mapping[ $col ] ) ) ? $col_mapping[ $col ] : '' );

			// Handling for meta columns
			if( empty( $key ) && ( 'postmeta' === substr( $col, 0, 8 ) || 'wc_orders_meta' === substr( $col, 0, 14 ) ) ) {
				$key = ( $is_hpos_enabled ) ? str_replace( 'postmeta', 'wc_orders_meta', $col ) : str_replace( 'wc_orders_meta', 'postmeta', $col );
			}

			$col_model['sort_params']['column'] = ( ! empty( $key ) ) ? $key : $col_model['sort_params']['column'];
			return $col_model;
		}

		/**
		 * Static function for defining columns to be ignored for flat tables.
		 *
		 * @param array $cols current ignored cols array.
		 * @return array $cols updated ignored cols array.
		 */
		public static function get_flat_table_ignored_cols( $cols = array() ) {
			return array(
				'wc_order_addresses' => array( 'id', 'order_id', 'address_type' ),
				'wc_order_operational_data' => array( 'id', 'order_id' )
			);
		}

		/**
		 * Static function for defining column titles for flat tables.
		 *
		 * @param array $cols current column titles array.
		 * @return array $cols updated column titles array.
		 */
		public static function get_flat_table_col_titles( $cols = array() ) {
			return array(
				'wc_order_addresses' => array( 'billing_email' => __( 'Address Billing Email', 'smart-manager-for-wp-e-commerce' ) )
			);
		}

		/**
		 * Static function for getting property name for column.
		 *
		 * @param string $col_nm column name.
		 * @param string $table_nm table name.
		 * @return string $col_nm property name for column.
		 */
		public static function get_property_name_for_col( $col_nm = '', $table_nm = '' ) {
			global $wpdb;

			if( empty( Smart_Manager::$sm_is_woo79 ) || empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) || ! function_exists( 'wc_get_container' ) || ! class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) || empty( $col_nm ) || empty( $table_nm ) ){
				return $col_nm;
			}

			if( empty( Smart_Manager_Shop_Order::$hpos_tables_column_property_mapping ) ){
				Smart_Manager_Shop_Order::$hpos_tables_column_property_mapping = wc_get_container()->get( 'Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' )->get_all_order_column_mappings();
			}

			$key = '';
			$table_nm = ( false === strpos( $table_nm, $wpdb->prefix ) ) ? $wpdb->prefix . $table_nm : $table_nm;
			switch( $table_nm ){
				case "{$wpdb->prefix}wc_orders":
					$key = 'orders';
					break;
				case "{$wpdb->prefix}wc_order_operational_data":
					$key = 'operational_data';
					break;
				case "{$wpdb->prefix}wc_order_addresses":
					$src = explode( '_', $col_nm );
					if ( ( ( ! is_array( $src ) ) || empty( $src ) ) || ( is_array( $src ) && ( empty( $src[0] ) || empty( $src[1] ) || ! in_array( $src[0], self::$address_types ) ) ) ){
						return $col_nm;
					}
					$key = ( 'billing' === $src[0] ) ? 'billing_address' : 'shipping_address';
					$col_nm = trim( substr( $col_nm, strlen( $src[0] ) + 1 ) );
					break;
			}

			if( empty( $key ) || empty( $col_nm ) || ( ! empty( $key ) && empty( Smart_Manager_Shop_Order::$hpos_tables_column_property_mapping[$key] ) ) ){
				return $col_nm;
			}

			if( empty( Smart_Manager_Shop_Order::$hpos_tables_column_property_mapping[$key][$col_nm] ) || ! is_array( Smart_Manager_Shop_Order::$hpos_tables_column_property_mapping[$key][$col_nm] ) ) {
				return $col_nm;
			}

			return ( ! empty( Smart_Manager_Shop_Order::$hpos_tables_column_property_mapping[$key][$col_nm]['name'] ) ) ? Smart_Manager_Shop_Order::$hpos_tables_column_property_mapping[$key][$col_nm]['name'] : $col_nm;
		}

		/**
		 * Search meta data from wc_orders_meta table.
		 *
		 * @param string $meta_cond meta conditions.
		 * @param array $search_params search params array.
		 * @return string $meta_cond Updated meta conditions
		 */
		public static function search_wc_orders_meta_cond( $meta_cond = '', $search_params = array() ) {
			if ( ( ! in_array( $search_params['search_col'], array( '_schedule_next_payment', '_schedule_trial_end', '_schedule_start', '_schedule_end', '_schedule_cancelled',
			'_schedule_payment_retry'
			 ) ) ) || ( ( ! empty( $search_params['rule_val'] ) && in_array( $search_params['search_operator'], array ( '=', '>', '>=' ) ) ) && ( '0' !== $search_params['rule_val'] && '' !== $search_params['rule_val'] && 0 !== $search_params['rule_val'] ) ) || empty( $search_params['search_col'] ) ) {
				return $meta_cond;
			}
			return ( '0' === $search_params['rule_val'] || '' === $search_params['rule_val'] || 0 === $search_params['rule_val'] ) ? "( ". $meta_cond ." OR ( ". $search_params['table_nm'] .".meta_key = '". $search_params['search_col'] . "' AND ". $search_params['table_nm'] .".meta_value ". $search_params['search_operator']." 0"." ) )" : "( ". $meta_cond ." OR ( ". $search_params['table_nm'] .".meta_key = '". $search_params['search_col'] . "' AND ". $search_params['table_nm'] .".meta_value = 0"." ) OR ( ". $search_params['table_nm'] .".meta_key = '". $search_params['search_col'] . "' AND ". $search_params['table_nm'] .".meta_value ". $search_params['search_operator']." '".$search_params['rule_val']."' ) )";
		}

		/**
		 * Update shipping details.
		 * @param  array $args subscription/ order ID, shipping total amount or shipping method, column name, dashbaord name.
		 *
		 * @return boolean true if success else false
		 */
		public static function update_shipping_details( $args = array() ) {
			if ( empty( $args['id'] ) || empty( $args['update_column'] ) || empty( $args['dashboard'] ) ) {
				return false;
			}
			$subs_or_order_obj = null;
			if ( 'shop_subscription' === $args['dashboard'] && function_exists( 'wcs_get_subscription' ) ) {
				$subscription = wcs_get_subscription( $args['id'] );
				if ( ! $subscription || ! is_a( $subscription, 'WC_Subscription' ) ) {
					return false;
				}
				$subs_or_order_obj = $subscription;
			} else {
				$order = wc_get_order( $args['id'] );
				if ( ! $order || ! is_a( $order, 'WC_Order' ) || ! is_callable( array( $order, 'get_status' ) ) ) {
					return false;
				}
				$status = $order->get_status();
				if ( empty( $status ) || ! in_array( $status, array( 'pending', 'on-hold' ) ) ) {
					return false;
				}
				$subs_or_order_obj = $order;
			}
			$line_items = ( is_callable( array( $subs_or_order_obj, 'get_items' ) ) ) ? $subs_or_order_obj->get_items( 'shipping' ) : array();
			if ( empty( $line_items ) || ! is_array( $line_items ) ) {
                return false;
            }
			foreach( $line_items as $item_id => $item ) {
				switch ( $args['update_column'] ) {
					case 'shipping_method':
						if ( ! is_callable( array( $item, 'set_method_id' ) ) ) {
							break;
						}
						$item->set_method_id( $args['value'] );
						break;
					case 'shipping_method_title':
						if ( ! is_callable( array( $item, 'set_method_title' ) ) ) {
							break;
						}
						$item->set_method_title( $args['value'] );
						break;
					case in_array( $args['update_column'], array( '_order_shipping', 'shipping_total_amount' ) ):
						if ( ! is_callable( array( $item, 'set_total' ) ) ) {
							break;
						}
						$item->set_total( $args['value'] );
						break;
				}
			}
			if ( ! is_callable( array( $subs_or_order_obj, 'calculate_totals' ) ) ) {
				return false;
			}
			$subs_or_order_obj->calculate_totals();
		}

		/**
		 * Function to process shipping details update.
		 *
		 * @param array $args array of shipping details.
         * @return void.
		 */
		public static function process_shipping_details_update( $args = array() ) {
			if ( empty( $args['edited_data'] ) || ! is_array( $args['edited_data'] ) ) {
				return;
			}
			foreach( $args['edited_data'] as $id => $edited_row ) {
				$id = intval( $id );
				if ( empty( $id ) || empty( $edited_row ) ) {
					continue;
				}
				foreach( $edited_row as $key => $value ) {
					$src = explode( "/", $key );
					if( empty( $src ) || ! is_array( $src ) ){
						continue;
					}
					$update_table = trim( $src[0] );
					$update_column = trim( $src[1] );
					if( empty( $update_column ) || empty( $update_table ) ){
						continue;
					}
					if( 'postmeta' === $update_table && sizeof( $src ) > 2 ) {
						$meta_src = explode( "=", $src[2] );
						if( empty( $meta_src ) || ! is_array( $meta_src ) ){
							continue;
						}
						$update_column = ( ! empty( $meta_src[1] ) ) ? trim( $meta_src[1] ) : $update_column;
					}
					if ( ! in_array( $update_column, array( '_order_shipping', 'shipping_total_amount', 'shipping_method', 'shipping_method_title' ) ) ) {
						continue;
					}
					Smart_Manager_Shop_Order::update_shipping_details(
						array(
							'id' => $id,
							'value' => $value,
							'update_column' => $update_column,
							'dashboard' => $args['dashboard']
						)
					);
				}
			}
		}

		/**
		 * Function to modify join clause.
		 *
		 * @param string $join join clause
		 * @param object $wp_query_obj wp query object.
         * @return string $join updated join clause.
		 */
		public function sm_order_query_join( $join = '', $wp_query_obj = null ) {
			global $wpdb;
			if ( empty( $this->req_params['search_text'] ) || empty( $this->dashboard_key ) ) {
				return $join;
			}
			$join .= ( false === strpos( $join, "{$wpdb->prefix}comments") ) ? " LEFT JOIN {$wpdb->prefix}comments AS cm ON ({$wpdb->prefix}posts.id = cm.comment_post_ID AND {$wpdb->prefix}posts.post_type = '{$this->dashboard_key}')" : '';
			$join .= ( ( false === strpos( $join, 'woocommerce_order_items' ) ) || ( false === strpos( $join, 'woocommerce_order_itemmeta' ) ) ) ? " LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON ({$wpdb->prefix}posts.id = oi.order_id AND {$wpdb->prefix}posts.post_type = '{$this->dashboard_key}') LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim ON (oi.order_item_id = oim.order_item_id AND oi.order_item_type = 'shipping')" : '';
			return $join;
		}
	}
}
