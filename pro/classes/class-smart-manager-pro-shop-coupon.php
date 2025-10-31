<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Shop_Coupon' ) ) {
	class Smart_Manager_Pro_Shop_Coupon extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
				$plugin_path = '';

		protected static $_instance = null;
		public $shop_coupon = '';


		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			parent::__construct($dashboard_key);
			self::actions();

			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );

			if ( file_exists(SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-shop-coupon.php') ) {
				include_once SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-shop-coupon.php';
				$this->shop_coupon = new Smart_Manager_Shop_Coupon( $dashboard_key );
			}

			add_filter( 'sa_sm_dashboard_model', array( &$this, 'coupons_dashboard_model' ), 10, 2 );
			add_filter( 'sm_batch_update_copy_from_ids_select', array( &$this, 'sm_batch_update_copy_from_ids_select' ), 10, 2 );
			add_filter( 'sm_data_model', array( &$this, 'coupons_data_model' ), 10, 2 );
			add_filter( 'sm_required_cols', array( &$this, 'sm_beta_required_cols' ), 10, 1 );
			add_filter( 'sm_inline_update_pre', array( &$this, 'coupons_inline_update_pre' ), 10, 1 );
		}

		public static function actions() {
			add_filter( 'sm_post_batch_process_args', __CLASS__. '::coupons_post_batch_process_args', 10, 1 );
		}

		public function __call( $function_name, $arguments = array() ) {

			if( empty( $this->shop_coupon ) ) {
				return;
			}

			if ( ! is_callable( array( $this->shop_coupon, $function_name ) ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $this->shop_coupon, $function_name ), $arguments );
			} else {
				return call_user_func( array( $this->shop_coupon, $function_name ) );
			}
		}

		public static function coupons_post_batch_process_args( $args ) {

			if( !empty( $args['col_nm'] ) && $args['col_nm'] == 'sa_cbl_locations_lookup_in' ) {
				$args['value'] = array( 'address' => $args['value'] );
			}
			if ( empty( $args['table_nm'] ) || 'postmeta' !== $args['table_nm'] || ! in_array( $args['col_nm'], array( 'product_categories', 'exclude_product_categories' ) ) || in_array( $args['operator'], array( 'copy_from', 'copy_from_field' ) ) ) {
				return $args;
			}
			$action = ( ! empty( $args['operator'] ) ) ? $args['operator'] : '';
			$value = ( ! empty( $args['value'] ) ) ? intval( $args['value'] ) : 0;
			$current_product_cat_ids = array();
			$product_cat_ids = array( $value );
			if ( ! empty( $action ) && 'set_to' !== $action ) {
				$current_product_cat_ids = get_post_meta( intval( $args['id'] ), $args['col_nm'] );
				if ( empty( $current_product_cat_ids ) || !is_array( $current_product_cat_ids ) ) {
					return $args;
				}
				foreach ( $current_product_cat_ids as $current_product_cat_id ) {
					$product_cat_ids = $current_product_cat_id;
					if( $action == 'add_to' ) {
						if ( ! is_array( $product_cat_ids ) || in_array( $value, $product_cat_ids ) ) {
							continue;
						}
						$product_cat_ids[] = $value;
					} else if ( $action == 'remove_from' ) {
						$key = array_search( $value, $product_cat_ids );
						if( false !== $key ) {
							unset( $product_cat_ids[ $key ] );
						}
					}
				}
			}
			$args['value'] = $product_cat_ids;
			return $args;
		}

		public function sm_beta_required_cols( $cols ) {
			$required_cols = array( 'posts_post_title' );
			return array_merge( $cols, $required_cols );
		}

		//function for modifying edited data before updating
		public function coupons_inline_update_pre( $edited_data ) {
			if (empty($edited_data)) return $edited_data;

			global $wpdb;

			$prod_title_ids = array();

			foreach ($edited_data as $key => $edited_row) {

				if( empty( $key ) ) {
					continue;
				}

				if( !empty( $edited_row['postmeta/meta_key=sa_cbl_locations_lookup_in/meta_value=sa_cbl_locations_lookup_in'] ) ) {
					$edited_data[$key]['postmeta/meta_key=sa_cbl_locations_lookup_in/meta_value=sa_cbl_locations_lookup_in'] = array( 'address' => $edited_data[$key]['postmeta/meta_key=sa_cbl_locations_lookup_in/meta_value=sa_cbl_locations_lookup_in'] );
				}
				if ( ! empty( $edited_row['postmeta/meta_key=product_categories/meta_value=product_categories'] ) ) {
					$edited_data[ $key ]['postmeta/meta_key=product_categories/meta_value=product_categories'] = array_filter( explode( ",", $edited_data[ $key ]['postmeta/meta_key=product_categories/meta_value=product_categories'] ) );
				}
				if ( ! empty( $edited_row['postmeta/meta_key=exclude_product_categories/meta_value=exclude_product_categories'] ) ) {
					$edited_data[ $key ]['postmeta/meta_key=exclude_product_categories/meta_value=exclude_product_categories'] = array_filter( explode( ",", $edited_data[ $key ]['postmeta/meta_key=exclude_product_categories/meta_value=exclude_product_categories'] ) );
				}
			}

			return $edited_data;
		}

		public function generate_select2_col_model( $column, $args ) {

			$options = array( 'allowClear' => true,
								'placeholder' => '',
								'multiple' => true,
								'dropdownCssClass'=> 'smSelect2Drop',
								'width'=> 'resolve' );

			if( empty( $args['values'] ) ) {
				$options ['loadDataDynamically'] = true;
				$options ['func_nm'] = ( ! empty( $args['func_nm'] ) ) ? $args['func_nm'] : '';
				$options ['minimumInputLength'] = 3;
				$args['values'] = array();
			} else {
				$options['data'] = $args['values'];
			}


			$column['type'] = 'dropdown';
			$column['editor'] = 'select2';
			$column['editable'] = false;
			$column['renderer'] = 'select2Renderer';
			$column['select2Options'] = $options;
			$column['search_values'] = array();

			foreach( $args['values'] as $option_value ) {
				$column['search_values'][] = array( 'key' => $option_value['id'], 'value' => $option_value['text'] );
			}

			return $column;
		}

		public function coupons_dashboard_model ($dashboard_model, $dashboard_model_saved) {

			global $wp_version;

			$date_columns = array(
				'date_expires'
			);

			$time_columns = array(
				'wc_sc_expiry_time'
			);


			$available_shipping_methods = $available_payment_methods = $attribute_taxonomies = $taxonomy_names = $taxonomy_obj = $attribute_labels = array();
			$cat_values = $editable_roles_values = $shipping_method_values = $payment_method_values = $attribute_values = $all_products = array();

			if ( ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) && ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) ) {
				$available_shipping_methods = WC()->shipping->get_shipping_methods();
				$available_payment_methods = WC()->payment_gateways->get_available_payment_gateways();

				//Code for getting all product attributes
				if( is_callable( 'wc_get_attribute_taxonomies' ) ) {
					$attribute_taxonomies = wc_get_attribute_taxonomies();
				}

				if( !empty( $attribute_taxonomies ) ) {
					foreach( $attribute_taxonomies as $attribute_taxonomy ) {
						if( is_callable( 'wc_attribute_taxonomy_name' ) ) {
							$attribute_labels [ wc_attribute_taxonomy_name( $attribute_taxonomy->attribute_name ) ] = $attribute_taxonomy->attribute_label;
						}
					}
					$taxonomy_names = array_keys( $attribute_labels );
				}
			}

			$taxonomy_names[] = 'product_cat';

			if (version_compare ( $wp_version, '4.5', '>=' )) {
    			$taxonomy_obj = get_terms( array(
									 	   'taxonomy' => $taxonomy_names,
											'get'      => 'all',
									));
    		} else {
    			$taxonomy_obj = get_terms( $taxonomy_names );
    		}

			$editable_roles = get_editable_roles();

			if( !empty( $taxonomy_obj ) ) {
				foreach( $taxonomy_obj as $obj ) {
					if( $obj->taxonomy == 'product_cat' ) {
						$cat_values[] = array( 'id' => $obj->term_id, 'text' => esc_html( $obj->name ) );
					} else {
						$attribute_values[] = array( 'id' => $obj->term_id, 'text' => ( !empty( $attribute_labels[ $obj->taxonomy ] ) ? $attribute_labels[ $obj->taxonomy ] : '' ).' --> '. $obj->name );
					}
				}
			}
			if ( ! empty( $taxonomy_obj )) {
				$terms_val = $this->get_parent_term_values(
					array(
						'taxonomy_obj'      => $taxonomy_obj,
						'include_taxonomy'  => 'product_cat' // include only 'product_cat' taxonomy.
					)
				);
			}
			if( !empty( $editable_roles ) ) {
				foreach( $editable_roles as $role_id => $role ) {
					$role_name = translate_user_role( $role['name'] );
					$editable_roles_values[] = array( 'id' => $role_id, 'text' => esc_html( $role_name ) );
				}
			}

			if( !empty( $available_shipping_methods ) ) {
				foreach( $available_shipping_methods as $shipping_method ) {
					$shipping_method_values[] = array( 'id' => $shipping_method->id, 'text' => esc_html( $shipping_method->get_method_title() ) );
				}
			}

			if( !empty( $available_payment_methods ) ) {
				foreach( $available_payment_methods as $payment_method ) {
					$payment_method_values[] = array( 'id' => $payment_method->id, 'text' => esc_html( $payment_method->get_title() ) );
				}
			}

			$all_products = $this->get_products();

			$multiselect_serialized_columns = array(
				'wc_sc_user_role_ids' 			=> array( 'title' 	=> __( 'Allowed user roles', 'smart-manager-for-wp-e-commerce' ),
														'values' 	=> $editable_roles_values ) ,
				'wc_sc_shipping_method_ids' 	=> array( 'title' 	=> __( 'Shipping methods', 'smart-manager-for-wp-e-commerce' ),
														'values' 	=> $shipping_method_values ) ,
				'wc_sc_payment_method_ids' 		=> array( 'title' 	=> __( 'Payment Methods', 'smart-manager-for-wp-e-commerce' ),
														'values' 	=> $payment_method_values ) ,

			);

			$multiselect_non_serialized_columns = array(
				'wc_sc_product_attribute_ids' 					=> array( 'title' 	=> __( 'Product Attributes', 'smart-manager-for-wp-e-commerce' ),
																		'values' 	=> $attribute_values,
																		'separator' => '|' ),
				'wc_sc_exclude_product_attribute_ids' 			=> array( 'title' 	=> __( 'Exclude Attributes', 'smart-manager-for-wp-e-commerce' ),
																		'values' 	=> $attribute_values,
																		'separator' => '|' ),
				'product_ids'									=> array( 'title' 	=> __( 'Products', 'smart-manager-for-wp-e-commerce' ),
																		'values' 	=> $all_products),
				'exclude_product_ids'							=> array( 'title' 	=> __( 'Exclude products', 'smart-manager-for-wp-e-commerce' ),
																		'values' 	=> $all_products),
			);

			$numeric_columns = array(
				'wc_sc_max_discount'                  => __( 'Max discount', 'smart-manager-for-wp-e-commerce' )
			);

			$column_titles = array(
				'sc_coupon_validity'                  => __( 'Coupon Validity', 'smart-manager-for-wp-e-commerce' ),
				'validity_suffix'                     => __( 'Validity Suffix', 'smart-manager-for-wp-e-commerce' ),
				'coupon_title_prefix'                 => __( 'Coupon Title Prefix', 'smart-manager-for-wp-e-commerce' ),
				'coupon_title_suffix'                 => __( 'Coupon Title Suffix', 'smart-manager-for-wp-e-commerce' ),
				'wc_coupon_message'					=> __( 'Display message', 'smart-manager-for-wp-e-commerce' ),
			);

			$checkbox_yes_no_columns = array(
				'sc_disable_email_restriction'		=> __( 'Disable Email Restriction', 'smart-manager-for-wp-e-commerce' ),
				'exclude_sale_items'				=> __( 'Exclude sale items', 'smart-manager-for-wp-e-commerce' ),
				'individual_use'					=> __( 'Individual use only', 'smart-manager-for-wp-e-commerce' ),
				'wc_email_message'					=> __( 'Email message?', 'smart-manager-for-wp-e-commerce' ),
				'free_shipping'						=> __( 'Allow free shipping', 'smart-manager-for-wp-e-commerce' ),
				'auto_generate_coupon'				=> __( 'Auto Generate Coupon', 'smart-manager-for-wp-e-commerce' ),
				'is_pick_price_of_product'			=> __( 'Is Pick Price of Product', 'smart-manager-for-wp-e-commerce' ),
				'sc_is_visible_storewide'			=> __( 'Coupon Is Visible Storewide', 'smart-manager-for-wp-e-commerce' ),
				'sc_restrict_to_new_user'			=> __( 'For new user only?', 'smart-manager-for-wp-e-commerce' ),
			);


			$column_model = &$dashboard_model['columns'];

			$coupon_shareable_link_index = sa_multidimesional_array_search('custom/coupon_shareable_link', 'src', $column_model);

			foreach( $column_model as $key => &$column ) {
				if ( empty( $column['src'] ) ) continue;

				$src_exploded = explode("/",$column['src']);

				if (empty($src_exploded)) {
					$col_nm = $column['src'];
				}

				if ( sizeof($src_exploded) > 2 ) {
					$col_table = $src_exploded[0];
					$cond = explode("=",$src_exploded[1]);

					if (sizeof($cond) == 2) {
						$col_nm = $cond[1];
					}
				} else {
					$col_nm = $src_exploded[1];
					$col_table = $src_exploded[0];
				}

				if( empty( $col_nm ) ) {
					continue;
				}

				switch( $col_nm ) {
					case 'customer_email':
						$column['key'] = __( 'Allowed emails', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column['editor'] = 'text';
						break;
					case ( !empty( $multiselect_serialized_columns[ $col_nm ] ) ):
						$column['key'] = $multiselect_serialized_columns[ $col_nm ]['title'];
						$column['name'] = $column['key'];
						$multiselect_values = $multiselect_serialized_columns[ $col_nm ]['values'];
						$column = $this->generate_select2_col_model( $column, array( 'values' => $multiselect_values ) );
						break;
					case ( !empty( $multiselect_non_serialized_columns[ $col_nm ] ) ):
						$column['key'] = $multiselect_non_serialized_columns[ $col_nm ]['title'];
						$column['name'] = $column['key'];
						$column['separator'] = ( !empty( $multiselect_non_serialized_columns[ $col_nm ]['separator'] ) ? $multiselect_non_serialized_columns[ $col_nm ]['separator'] : ',' );
						$multiselect_values = $multiselect_non_serialized_columns[ $col_nm ]['values'];
						$column = $this->generate_select2_col_model( $column, array( 'values' => $multiselect_values ) );
						break;
					case ( !empty( $numeric_columns[ $col_nm ] ) ):
						$column['key'] = $numeric_columns[ $col_nm ];
						$column['name'] = $column['key'];
						$column['type'] = 'numeric';
						$column['editor'] = 'customNumericEditor';
						break;
					case ( !empty( $checkbox_yes_no_columns[ $col_nm ] ) ):
						$column['key'] = $checkbox_yes_no_columns[ $col_nm ];
						$column['name'] = $column['key'];
						$column['type'] = 'checkbox';
						$column['editor'] = $column['type'];
						$column['checkedTemplate'] = 'yes';
      					$column['uncheckedTemplate'] = 'no';
						$column['width'] = 30;
						break;
					case ( !empty( $column_titles[ $col_nm ] ) ):
						$column['key'] = $column['name'] = $column_titles[ $col_nm ];
						break;
					case 'sa_cbl_locations_lookup_in':
						$column['key'] = $column['name'] = __( 'Address to look in', 'smart-manager-for-wp-e-commerce' );
						$values = array( 'billing' => __( 'Billing', 'smart-manager-for-wp-e-commerce'),
										'shipping' => __( 'Shipping', 'smart-manager-for-wp-e-commerce' ) );

						$column = $this->generate_dropdown_col_model( $column, $values );
						break;
					case 'date_expires':
						$column['key'] = $column['name'] = __( 'Coupon expiry date', 'smart-manager-for-wp-e-commerce' );
						$column['type'] = $column['editor'] = 'sm.date';
						$column['date_type'] = 'timestamp';
						break;
					case 'wc_sc_expiry_time':
						$column['key'] = $column['name'] = __( 'Coupon expiry time', 'smart-manager-for-wp-e-commerce' );
						$column['type'] = $column['editor'] = 'sm.time';
						$column['date_type'] = 'timestamp';
						break;
					case ( in_array( $col_nm, array( 'product_categories', 'exclude_product_categories' ) ) ):
						$column['values'] = ( ! empty( $this->terms_val_parent['product_cat'] ) ) ? $this->terms_val_parent['product_cat'] : $cat_values;
						$column['type'] = 'sm.multilist';
						$column['title'] = ( 'product_categories' === $col_nm ) ? __( 'Product categories', 'smart-manager-for-wp-e-commerce' ) : __( 'Exclude categories', 'smart-manager-for-wp-e-commerce' );
						break;
				}

			}

			if( empty( $coupon_shareable_link_index ) ) {

				$index = sizeof($column_model);

				$index++;

				$column_model [$index]['src'] = 'custom/coupon_shareable_link';
				$column_model [$index]['data'] = sanitize_title(str_replace('/', '_', $column_model [$index]['src'])); // generate slug using the wordpress function if not given
				$column_model [$index]['key'] = $column_model[$index]['name'] = __( 'Coupon shareable link', 'smart-manager-for-wp-e-commerce' );
				$column_model [$index]['type'] = 'text';
				$column_model [$index]['renderer'] = 'html';
				$column_model [$index]['hidden']	= false;
				$column_model [$index]['editable']	= false;
				$column_model [$index]['editor']	= false;
				$column_model [$index]['batch_editable']	= false;
				$column_model [$index]['sortable']	= true;
				$column_model [$index]['resizable']	= true;
				$column_model [$index]['allow_showhide']	= true;
				$column_model [$index]['exportable']	= true;
				$column_model [$index]['searchable']	= false;
				$column_model [$index]['width'] = 100;
				$column_model [$index]['save_state'] = true;
				$column_model [$index]['values'] = array();
				$column_model [$index]['search_values'] = array();

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

		public function get_products( $params = array() ) {

			$args = array_merge( $params, array( 'dashboard_key' => array('product', 'product_variation'),
													'search_term' => ( !empty( $this->req_params['searchTerm'] ) ? $this->req_params['searchTerm'] : '' ),
													'is_ajax' => false ) );

			$pro_base_instance = is_callable( array( 'Smart_Manager_Pro_Base', 'instance' ) ) ? parent::instance( $this->dashboard_key ) : null;
			$data = ( ( ! empty( $pro_base_instance ) ) && ( is_callable( array( $pro_base_instance, 'get_batch_update_copy_from_record_ids' ) ) ) ) ? $pro_base_instance->get_batch_update_copy_from_record_ids( $args )  : array();

			$products = array();

			if ( ( ! empty( $data ) ) && ( is_array( $data ) ) ) {
				foreach( $data as $id => $title ) {
					$products[] = array( 'id' => $id, 'text' => esc_html( $title ) );
				}
			}

			return $products;
		}

		public function coupons_data_model ($data_model, $data_col_params) {

			global $wpdb, $current_user;

			if(empty($data_model) || empty($data_model['items'])) {
				return $data_model;
			}

			$product_ids = array();
			$include_product_ids = array();
			$exclude_product_ids = array();

			foreach( $data_model['items'] as $key => $item ) {
				if( !empty( $item['postmeta_meta_key_sa_cbl_locations_lookup_in_meta_value_sa_cbl_locations_lookup_in'] ) ) {
					$value_obj = maybe_unserialize( $item['postmeta_meta_key_sa_cbl_locations_lookup_in_meta_value_sa_cbl_locations_lookup_in'] );
					$data_model['items'][$key]['postmeta_meta_key_sa_cbl_locations_lookup_in_meta_value_sa_cbl_locations_lookup_in'] = ( !empty( $value_obj['address'] ) ? $value_obj['address'] : 'billing' );
				}
				if( empty( $item['custom_coupon_shareable_link'] ) && !empty( $data_model['items'][$key]['posts_post_title'] ) ) {
					$link = home_url( '/?coupon-code=' . $data_model['items'][$key]['posts_post_title'] );
					$data_model['items'][$key]['custom_coupon_shareable_link'] = ( !empty( $this->req_params['cmd'] ) && $this->req_params['cmd'] != 'get_export_csv' && $this->req_params['cmd'] != 'get_print_invoice' ) ? "<span class='sm_click_to_copy' title='". __('Click to copy', 'smart-manager-for-wp-e-commerce') ."'>".$link."</span>" : $link;
				}
				if ( empty( $data_col_params ) || ! is_array( $data_col_params ) || ! isset( $data_col_params['data_cols_multilist'] ) || ! is_array( $data_col_params['data_cols_multilist'] ) ) {
					continue;
				}
				$multilist_separator = ', ';
				foreach( $data_col_params['data_cols_multilist'] as $col ) {
					$vals = maybe_unserialize( $item[ 'postmeta_meta_key_'.$col.'_meta_value_'.$col.'' ] );
					$data_model['items'][$key]['postmeta_meta_key_'.$col.'_meta_value_'.$col.''] = '';
					if ( empty( $vals ) || ! is_array( $vals ) ) {
						continue;
					}
					foreach ( $vals as $val ) {
						$term = get_term( $val );
						if ( empty( $term ) ) {
							continue;
						}
						$term_name = ucwords( $term->name );
						if( empty( $data_model ['items'][$key][ 'postmeta_meta_key_'.$col.'_meta_value_'.$col.'' ] ) ) {
							$data_model ['items'][$key][ 'postmeta_meta_key_'.$col.'_meta_value_'.$col.'' ] = $term_name;
						} else {
							$data_model ['items'][$key][ 'postmeta_meta_key_'.$col.'_meta_value_'.$col.'' ] .= $multilist_separator . "" . $term_name;
						}
					}
				}
			}
			return $data_model;
		}

		//Function for overriding the select clause for fetching the ids for batch update 'copy from' functionality
		public function sm_batch_update_copy_from_ids_select( $select, $args ) {

			if( empty( $args['dashboard_key'] ) ) {
				return $select;
			}

			$select = " SELECT ID AS id, 
							( CASE 
			            		WHEN (post_excerpt != '' AND post_type = 'product_variation') THEN CONCAT(post_title, ' - ( ', post_excerpt, ' ) ')
								ELSE post_title
			            	END ) as title ";

			return $select;
		}
	}
}
