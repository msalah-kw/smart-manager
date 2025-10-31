<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_User' ) ) {
	class Smart_Manager_Pro_User extends Smart_Manager_Pro_Base {
		public $dashboard_key = '', $usermeta_ignored_cols = '', $advanced_search_table_types = array(
					'flat' => array( 
						'users'         => 'id'
					),
					'meta' => array( 
						'usermeta' => 'user_id' 
					)
				);

		protected static $_instance = null;

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			add_filter(
				'sm_search_table_types',
				function( $advanced_search_table_types = array() ) {
					return $this->advanced_search_table_types;
				}
			); // should be kept before calling the parent class constructor.
			parent::__construct($dashboard_key);
			self::actions();

			$this->dashboard_key = $dashboard_key;
			$this->post_type = $dashboard_key;
			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();

			$this->usermeta_ignored_cols = apply_filters('sm_usermeta_ignored_cols', array('session_tokens', 'wp_woocommerce_product_import_mapping', 'wp_product_import_error_log'));

			add_filter( 'sm_default_dashboard_model',array(&$this,'default_user_dashboard_model') );
			add_filter( 'sm_beta_load_default_data_model',array(&$this,'load_default_data_model') );
			add_filter( 'sm_default_inline_update', function() { return false; } );
			add_action( 'sm_inline_update_post',array(&$this,'user_inline_update'), 10, 2 );
			add_filter( 'sm_data_model',array(&$this,'generate_data_model'), 10, 2 );
			add_filter( 'sm_deleter', array( &$this, 'user_deleter' ), 10, 2 );
			add_filter( 'sm_beta_delete_records_ids', array( $this, 'users_delete_record_ids' ), 10, 2 );
			add_filter( 'sm_search_query_terms_select', array( &$this,'search_query_terms_select' ), 10, 2 );
			add_filter( 'sm_search_query_terms_from', array( &$this,'search_query_terms_from' ), 10, 2 );
			add_filter( 'sm_search_query_terms_where', array( &$this,'search_query_terms_where' ), 10, 2 );
			//Orders count advanced search.
			add_filter( 'sm_search_query_usermeta_from',array( __CLASS__, 'search_query_usermeta_from' ), 10, 2 );
			add_filter( 'sm_search_query_usermeta_select',array( __CLASS__, 'search_query_usermeta_select' ), 10, 2 );
			add_filter( 'sm_search_query_usermeta_where',array( __CLASS__, 'search_query_usermeta_where' ), 10, 2 );
		}

		public static function actions() {
			// add_filter('sm_beta_batch_update_entire_store_ids_query', __CLASS__. '::users_batch_update_entire_store_ids_query', 10, 1);
			add_filter( 'sm_beta_background_entire_store_ids_from', __CLASS__. '::users_batch_update_entire_store_ids_from', 10, 2 );
			add_filter( 'sm_beta_background_entire_store_ids_where', __CLASS__. '::users_batch_update_entire_store_ids_where', 10, 2 );
			add_filter( 'sm_batch_update_prev_value', __CLASS__. '::users_batch_update_prev_value', 10, 2 );
			add_filter( 'sm_default_batch_update_db_updates',  __CLASS__. '::users_default_batch_update_db_updates', 10, 2 );
			add_filter( 'sm_post_batch_update_db_updates', __CLASS__. '::users_post_batch_update_db_updates', 10, 2 );
			add_filter( 'sm_pro_default_process_delete_records', function() { return false; } );
		}

		public function get_batch_update_copy_from_record_ids( $args = array() ) {

			global $wpdb;
			$data = array();

			$is_ajax = ( isset( $args['is_ajax'] )  ) ? $args['is_ajax'] : true;

			$search_term = ( ! empty( $this->req_params['search_term'] ) ) ? $this->req_params['search_term'] : ( ( ! empty( $args['search_term'] ) ) ? $args['search_term'] : '' );
			$select = apply_filters( 'sm_batch_update_copy_from_user_ids_select', "SELECT ID AS id, user_login AS title", $args );
			$search_cond = ( ! empty( $search_term ) ) ? " AND ( id LIKE '%".$search_term."%' OR user_login LIKE '%".$search_term."%' OR user_email LIKE '%".$search_term."%' ) " : '';
			$search_cond_ids = ( !empty( $args['search_ids'] ) ) ? " AND id IN ( ". implode(",", $args['search_ids']) ." ) " : '';
			$results = $wpdb->get_results( $select . " FROM {$wpdb->prefix}users WHERE 1=1 ". $search_cond ." ". $search_cond_ids, 'ARRAY_A' );

			if( count( $results ) > 0 ) {
				foreach( $results as $result ) {
					$data[ $result['id'] ] = trim($result['title']);
				}
			}

			$data = apply_filters( 'sm_batch_update_copy_from_user_ids', $data );
			
			if( $is_ajax ){
				wp_send_json( $data );
			} else {
				return $data;
			}
		}

		public function default_user_dashboard_model ($dashboard_model) {
			if ( empty( $dashboard_model ) ) {
				return;
			}
			global $wpdb, $current_user, $_wp_admin_css_colors;
			$col_model = array();
			$ignored_term_cols = array( 'object_id' );
			// fetching terms columns.
			$afwc_multilist_fields = array( 'afwc_user_tags' => _x( 'AFWC Affiliate Tags', 'AFWC Affiliate Tags field name', 'smart-manager-for-wp-e-commerce' ) );
			foreach ( $dashboard_model['columns'] as $key => &$value ) {
				if ( empty( $value ) || empty( $value['src'] ) ) {
					continue;
				}
				$col_explode = explode( '/', $value['src'] );
				if ( empty( $col_explode[0] ) || empty( $col_explode[1] ) ) {
					continue;
				}
				if ( ( 'terms' === $col_explode[0] ) ) {
					if ( in_array( $col_explode[1], $ignored_term_cols ) ) {
						continue;
					}
					$value['name'] = $value['key'] = $afwc_multilist_fields[ $col_explode[1] ];
					$col_model[] = $dashboard_model['columns'][$key];
				}	
			}
			$default_hidden_cols = apply_filters( 'sm_users_default_hidden_cols', array( 'user_url', 'user_activation_key', 'user_status' ) );
			$default_non_editable_cols = apply_filters( 'sm_users_default_non_editable_cols', array( 'ID', 'user_login' ) );
			$default_ignored_cols = apply_filters( 'sm_users_default_ignored_cols', array( 'user_activation_key', 'user_status' ) );

			$query_users_col = "SHOW COLUMNS FROM {$wpdb->users}";
			$results_users_col = $wpdb->get_results($query_users_col, 'ARRAY_A');
			$users_num_rows = $wpdb->num_rows;

			if ($users_num_rows > 0) {
				foreach ($results_users_col as $col) {
					
					$temp = array();
					$field_nm = (!empty($col['Field'])) ? $col['Field'] : '';

					if( in_array($field_nm, $default_ignored_cols) ) {
						continue;
					}

					$temp ['src'] = 'users/'.$field_nm;
					$temp ['data'] = sanitize_title(str_replace('/', '_', $temp ['src'])); // generate slug using the wordpress function if not given 
					$temp ['name'] = __(ucwords(str_replace('_', ' ', $field_nm)), 'smart-manager-for-wp-e-commerce');

					$temp ['table_name'] = $wpdb->prefix.'users';
					$temp ['col_name'] = $field_nm;

					$temp ['key'] = $temp ['name']; //for advanced search

					$type = 'text';
					$temp ['width'] = 100;
					$temp ['align'] = 'left';

					if (!empty($col['Type'])) {
						$type_strpos = strrpos($col['Type'],'(');
						if ($type_strpos !== false) {
							$type = substr($col['Type'], 0, $type_strpos);
						} else {
							$types = explode( " ", $col['Type'] ); // for handling types with attributes (biginit unsigned)
							$type = ( ! empty( $types ) ) ? $types[0] : $col['Type']; 
						}

						if (substr($type,-3) == 'int') {
							$type = 'numeric';
							$temp ['min'] = 0;
							$temp ['width'] = 50;
							$temp ['align'] = 'right';
						} else if ($type == 'text') {
							$temp ['width'] = 130;
							$type = 'text';
						} else if (substr($type,-4) == 'char' || substr($type,-4) == 'text') {
							if ($type == 'longtext') {
								$type = 'sm.longstring';
								$temp ['width'] = 150;
							} else {
								$type = 'text';
							}
						} else if (substr($type,-4) == 'blob') {
							$type = 'sm.longstring';
						} else if ($type == 'datetime' || $type == 'timestamp') {
							$type = 'sm.datetime';
							$temp ['width'] = 102;
						} else if ($type == 'date' || $type == 'year') {
							$type = 'date';
						} else if ($type == 'decimal' || $type == 'float' || $type == 'double' || $type == 'real') {
							$type = 'numeric';
							$temp ['min'] = 0;
							$temp ['width'] = 50;
							$temp ['align'] = 'right';
						} else if ($type == 'boolean') {
							$type = 'checkbox';
							$temp ['width'] = 30;
						}

					}

					$temp ['hidden']			= false;
					$temp ['editable']			= true;
					$temp ['batch_editable']	= true; // flag for enabling the batch edit for the column
					$temp ['sortable']			= true;
					$temp ['resizable']			= true;

					//For disabling frozen
					$temp ['frozen']			= false;

					$temp ['allow_showhide']	= true;
					$temp ['exportable']		= true; //default true. flag for enabling the column in export
					$temp ['searchable']		= true;
					$temp ['placeholder'] = ''; //for advanced search

					//Code for handling the positioning of the columns
					if ($field_nm == 'ID') {
						$temp ['position'] = 1;
						$temp ['align'] = 'left';
					} else if ($field_nm == 'user_login') {
						$temp ['position'] = 2;
					} else if ($field_nm == 'user_pass') {
						$temp ['position'] = 3;
						$temp ['searchable'] = false;
						$temp ['placeholder'] = 'Click to change';
						$type = 'text';
					} else if ($field_nm == 'user_nicename') {
						$temp ['name'] = __('Nickname', 'smart-manager-for-wp-e-commerce');
						$temp ['key'] = $temp ['name'];
					}

					if( !empty( $default_non_editable_cols ) && in_array( $field_nm, $default_non_editable_cols ) ) {
						$temp ['editor'] = false;
						$temp ['editable'] = false;
						$temp ['batch_editable'] = false;
					}

					if( $field_nm == 'user_pass' ){
						$temp ['type'] = 'password';
					} else {
						$temp ['type'] = $type;
					}

					$temp ['values'] = array();
					$temp ['hidden'] = ( in_array($field_nm, $default_hidden_cols) ) ? true : false;
					$temp ['category'] = ''; //for advanced search
					$col_model [] = $temp;
				}
			}

			$default_um_visible_cols = apply_filters('sm_usermeta_visible_cols', array('first_name', 'last_name', 'description', 'rich_editing', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'billing_email', 'billing_phone'));
			$default_um_disabled_cols = apply_filters('sm_usermeta_disabled_cols', array('billing_country', 'billing_state'));
			$default_um_non_searchable_cols = apply_filters( 'sm_usermeta_non_searchable_cols', array( $wpdb->prefix.'capabilities' ) );
			$um_serialized_cols = apply_filters( 'sm_usermeta_serialized_cols', array( $wpdb->prefix.'capabilities', 'afwc_additional_fields' ) );

			//code for getting the meta cols
			$results_usermeta_col = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT(meta_key) as meta_key,
																meta_value
															FROM {$wpdb->usermeta}
															WHERE meta_key NOT LIKE 'sa_sm_%' AND meta_key NOT LIKE 'free-%' AND meta_key NOT LIKE '_oembed%' AND meta_key NOT IN ( '". implode("','", $this->usermeta_ignored_cols) ."' )
																AND 1=%d
															GROUP BY meta_key", 1), 'ARRAY_A');
			$um_num_rows = $wpdb->num_rows;
			if ($um_num_rows > 0) {

				$meta_keys = array();

				foreach ($results_usermeta_col as $key => $usermeta_col) {
					if (empty($usermeta_col['meta_value'])) {
						$meta_keys [] = $usermeta_col['meta_key']; //TODO: if possible store in db instead of using an array
					}

					unset($results_usermeta_col[$key]);
					$results_usermeta_col[$usermeta_col['meta_key']] = $usermeta_col;
				}

				if (!empty($meta_keys)) {
					$results_um_meta_value = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT(meta_key) as meta_key,
																				meta_value
																			FROM {$wpdb->usermeta}
																			WHERE meta_key IN ( '". implode("','", $meta_keys) ."' )
																				AND meta_value != %s
																			GROUP BY meta_key", ''), 'ARRAY_A');
					$num_rows_meta_value = $wpdb->num_rows;

					if ($num_rows_meta_value > 0) {
						foreach ($results_um_meta_value as $result_meta_value) {
							if (isset($results_usermeta_col [$result_meta_value['meta_key']])) {
								$results_usermeta_col [$result_meta_value['meta_key']]['meta_value'] = $result_meta_value['meta_value'];
							}
						}
					}
				}

				$index = count($col_model);

				$col_model [$index] = array();
				$col_model [$index]['src'] = 'usermeta/user_id';

				$src = '';

				if( ! empty( $col_model [$index]['src'] ) ) {
					$src = str_replace('/', '_', $col_model [$index]['src']);
				}
				$user_id = 'user id';
				
				$col_model [$index]['data'] = sanitize_title($src); // generate slug using the WordPress function if not given 
				$col_model [$index]['name'] = __(ucwords($user_id), 'smart-manager-for-wp-e-commerce');
				$col_model [$index]['key'] = $col_model [$index]['name']; //for advanced search
				$col_model [$index]['type'] = 'numeric';
				$col_model [$index]['hidden']	= true;
				$col_model [$index]['allow_showhide'] = false;
				$col_model [$index]['editable']	= false;
				$col_model [$index]['batch_editable']	= false;
				$col_model [$index]['exportable']		= true; //default true. flag for enabling the column in export
				$col_model [$index]['searchable']		= true;

				$col_model [$index]['table_name'] = $wpdb->prefix.'usermeta';
				$col_model [$index]['col_name'] = 'user_id';

				$col_model [$index] ['category'] = ''; //for advanced search
				$col_model [$index] ['placeholder'] = ''; //for advanced search

				$index++;


				$col_model [$index] = array();
				$col_model [$index]['src'] = 'usermeta/role';

				$src = '';
				if( ! empty( $col_model [$index]['src'] ) ){
					$src = str_replace('/', '_', $col_model [$index]['src']);
				}
				$role = 'role';
				
				$col_model [$index]['data'] = sanitize_title($src); // generate slug using the wordpress function if not given 
				$col_model [$index]['name'] = __(ucwords($role), 'smart-manager-for-wp-e-commerce');
				$col_model [$index]['key'] = $col_model [$index]['name']; //for advanced search
				$col_model [$index]['type'] = 'dropdown';
				$col_model [$index]['strict'] = true;
				$col_model [$index]['allowInvalid'] = false;
				$col_model [$index]['hidden']	= false;
				$col_model [$index]['allow_showhide'] = true;
				$col_model [$index]['editable']	= true;
				$col_model [$index]['position'] = 2;
				$col_model [$index]['batch_editable']	= true; // flag for enabling the batch edit for the column
				$col_model [$index]['exportable']		= true; //default true. flag for enabling the column in export
				$col_model [$index]['searchable']		= true;

				$all_roles = array();
				$col_model [$index]['values'] = array();

				if( function_exists('get_editable_roles') ) {
					$all_roles = get_editable_roles();	
				}

				if( !empty( $all_roles ) ) {

					$col_model [$index]['search_values'] = array();

					foreach ( $all_roles as $role => $details) {
                		$name = translate_user_role( $details['name'] );
                		$col_model [$index]['values'][$role] = $name;
                		$col_model [$index]['search_values'][] = array('key' => $role, 'value' => $name);
                	}
				}

				$col_model [$index] ['editor'] = 'select';
				$col_model [$index] ['selectOptions'] = $col_model [$index]['values'];
				$col_model [$index] ['renderer'] = 'selectValueRenderer';

				$col_model [$index]['table_name'] = $wpdb->prefix.'usermeta';
				$col_model [$index]['col_name'] = $wpdb->prefix.'capabilities';

				$col_model [$index] ['category'] = ''; //for advanced search
				$col_model [$index] ['placeholder'] = ''; //for advanced search


				$index++;

				$custom_cols = array('last_order_date', 'last_order_total', 'orders_count', 'orders_total');

				foreach( $custom_cols as $col ) {
					$col_model [$index] = array();
					$col_model [$index]['src'] = 'custom/'.$col;
					$col_model [$index]['data'] = sanitize_title(str_replace('/', '_', $col_model [$index]['src'])); // generate slug using the wordpress function if not given 
					$col_model [$index]['name'] = __(ucwords(str_replace('_', ' ', $col)), 'smart-manager-for-wp-e-commerce');
					$col_model [$index]['key'] = $col_model [$index]['name']; //for advanced search
					$col_model [$index]['type'] = ( 'orders_count' === $col ) ? 'numeric' : 'text';
					$col_model [$index]['hidden']	= false;
					$col_model [$index]['allow_showhide'] = true;
					$col_model [$index]['editable']	= false;
					$col_model [$index]['sortable']	= false;

					$col_model [$index]['table_name'] = $wpdb->prefix.'usermeta';
					$col_model [$index]['col_name'] = ( 'orders_count' === $col ) ? 'orders_count' : 'user_id';
					$col_model [$index]['exportable'] = true; //default true. flag for enabling the column in export
					$col_model [$index]['searchable'] = true;

					$col_model [$index] ['category'] = ''; //for advanced search
					$col_model [$index] ['placeholder'] = ''; //for advanced search

					$index++;
				}
				// Mapping for AFW plugin fields.
				$afwc_text_fields = array( 
					'afwc_is_affiliate' => _x( 'AFWC Is Affiliate', 'AFWC Is Affiliate field name', 'smart-manager-for-wp-e-commerce' ),
					'afwc_ref_url_id'  => _x( 'AFWC Referral URL ID', 'AFWC Referral URL ID field name', 'smart-manager-for-wp-e-commerce' ),
					'afwc_paypal_email'  => _x( 'AFWC PayPal Email', 'AFWC PayPal Email field name', 'smart-manager-for-wp-e-commerce' ),
					'afwc_parent_chain' => _x( 'AFWC Parent Chain', 'AFWC Parent Chain field name', 'smart-manager-for-wp-e-commerce' ),
					'afwc_ltc_customers' => _x( 'AFWC Lifetime Customers', 'AFWC Lifetime Customers field name', 'smart-manager-for-wp-e-commerce' ), 
					'afwc_affiliate_desc' => _x( 'AFWC Affiliate Form Description', 'AFWC Affiliate Form Description field name', 'smart-manager-for-wp-e-commerce' ),
					'afwc_affiliate_contact' => _x( 'AFWC Affiliate Form Contact', 'AFWC Affiliate Form Contact field name', 'smart-manager-for-wp-e-commerce' ),
					'afwc_affiliate_skype'  => _x( 'AFWC Affiliate Skype Handle', 'AFWC Affiliate Skype Handle field name', 'smart-manager-for-wp-e-commerce' )
				);
				$afwc_serialized_fields = array( 'afwc_additional_fields' => _x( 'AFWC Additional Form Fields', 'AFWC Additional Form Fields field name', 'smart-manager-for-wp-e-commerce' ) );
				foreach ($results_usermeta_col as $usermeta_col) {

					$temp = array();
					$type = 'text';

					$meta_key = ( !empty( $usermeta_col['meta_key'] ) ) ? $usermeta_col['meta_key'] : '';
					$meta_value = ( !empty( $usermeta_col['meta_value'] ) || $usermeta_col['meta_value'] == 0 ) ? $usermeta_col['meta_value'] : '';

					$temp ['src'] = 'usermeta/meta_key='.$meta_key.'/meta_value='.$meta_key;
					$temp ['data'] = strtolower(str_replace(array('/','='), '_', $temp ['src'])); // generate slug using the wordpress function if not given 
					$temp ['name'] = __(ucwords(str_replace('_', ' ', $meta_key)), 'smart-manager-for-wp-e-commerce');
					$temp ['key'] = $temp ['name']; //for advanced search

					$temp ['table_name'] = $wpdb->prefix.'usermeta';
					$temp ['col_name'] = $meta_key;

					$temp ['width'] = 100;
					$temp ['align'] = 'left';
					if ( $meta_value == 'yes' || $meta_value == 'no' || $meta_value == 'true' || $meta_value == 'false' || ( is_numeric($meta_value) && ( $meta_value == 0 || $meta_value == 1 ) ) ) {
						$type = 'checkbox';

						if( $meta_value == 'yes' || $meta_value == 'no' ) {
							$temp ['checkedTemplate'] = 'yes';
      						$temp ['uncheckedTemplate'] = 'no';
						} else if( is_int( $meta_value ) && ( $meta_value == 0 || $meta_value == 1 ) ) {
							$temp ['checkedTemplate'] = 0;
      						$temp ['uncheckedTemplate'] = 1;
						}

						$temp ['width'] = 30;
					} else if( is_numeric( $meta_value ) ) {

						if( function_exists('isTimestamp') ) {
							if( isTimestamp( $meta_value ) ) {
								$type = 'sm.datetime';
								$temp ['width'] = 102;
								$temp ['date_type'] = 'timestamp';
							}
						} 

						if( $type != 'sm.datetime' ) {
							$type = 'numeric';
							$temp ['min'] = 0;	
							$temp ['width'] = 50;
							$temp ['align'] = 'right';	
						}
					} else if( is_serialized( $meta_value ) === true || ( !empty($um_serialized_cols) && in_array($meta_key, $um_serialized_cols) ) ) {
						$type = 'sm.serialized';
						$temp ['width'] = 200;
						if ( 'afwc_additional_fields' === $meta_key ) {
							$temp ['editor'] = $type;
							$temp ['name'] = $temp ['key'] = $afwc_serialized_fields[ $meta_key ];
						}
					}
					$type = ( 'nickname' === $meta_key ) ? 'text' : $type;
					$temp ['type'] = $type;
					$temp ['values'] = array();

					if( $meta_key == 'admin_color' ) {

						$temp ['search_values'] = array();
						
						$themes = array_keys($_wp_admin_css_colors);
						foreach( $themes as $theme ) {
							$name = ( !empty($_wp_admin_css_colors[$theme]) ) ? $_wp_admin_css_colors[$theme]->name : ucwords($theme);
							$temp ['values'][$theme] = $name;
                			$temp ['search_values'][] = array('key' => $theme, 'value' => $name);
						}
					} elseif ( ( ! empty( $afwc_text_fields ) && is_array( $afwc_text_fields ) ) && in_array( $meta_key, array_keys($afwc_text_fields) ) ) {
						$temp['type'] = $temp['editor'] = 'text';
					  	$temp['name'] = $temp['key'] = $afwc_text_fields[ $meta_key ];
					}

					$temp ['hidden'] = ( !empty($default_um_visible_cols) && in_array($meta_key, $default_um_visible_cols) ) ? false : true;
					$hidden_col_array = array('_edit_lock','_edit_last');

					if (array_search($meta_key,$hidden_col_array) !== false ) {
						$temp ['hidden'] = true;	
					}

					
					$temp ['editable']			= ( !empty($default_um_disabled_cols) && in_array($meta_key, $default_um_disabled_cols) ) ? false : true;
					$temp ['editor']			= ( 'numeric' === $temp ['type'] ) ? 'customNumericEditor' : $temp ['type'];
					$temp ['batch_editable']	= true; // flag for enabling the batch edit for the column
					$temp ['sortable']			= true;
					$temp ['resizable']			= true;
					$temp ['frozen']			= false;
					$temp ['allow_showhide']	= true;
					$temp ['exportable']		= true; //default true. flag for enabling the column in export
					$temp ['searchable']		= ( !empty($default_um_non_searchable_cols) && in_array($meta_key, $default_um_non_searchable_cols) ) ? false : true;

					$temp ['category'] = ''; //for advanced search
					$temp ['placeholder'] = ''; //for advanced search

					$col_model [] = $temp;
				}
				if ( ! empty( $col_model ) && is_array( $col_model ) ) {
					$col_model = &$col_model;
				}
			}

			$dashboard_model['columns'] = $col_model;

			return $dashboard_model;
		}

		//function to avoid generation of the default data model
		public function load_default_data_model ($flag) {
			return false;
		}


		public function process_user_search_cond($params = array()) {

			global $wpdb;


			if( empty($params) || empty($params['search_query']) ) {
				return;
			}

			$rule_groups = ( ! empty( $params['search_query'] ) ) ? $params['search_query'][0]['rules'] : array();

			if( empty( $rule_groups ) ) {
				return;
			}

			$wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp"); // query to reset advanced search temp table

            $advanced_search_query = array();
            $i = 0;

            $search_cols_type = ( ! empty( $params['search_cols_type'] ) ) ? $params['search_cols_type'] : array();
            $non_flat_table_types = ( ! empty( $this->advanced_search_table_types['meta'] ) ) ? array_merge( array( 'terms' ), array_keys( $this->advanced_search_table_types['meta'] ) ) : array( 'terms' );

            foreach ($rule_groups as $rule_group) {

                if (is_array($rule_group)) {

                		// START FROM HERE
                        if ( ! empty( $this->advanced_search_table_types ) ) {
							if ( ! empty( $this->advanced_search_table_types['flat'] ) ) {
								foreach ( array_keys( $this->advanced_search_table_types['flat'] ) as $table ) {
									$advanced_search_query[$i]['cond_'. $table] = '';
								}
							}

							if ( ! empty( $non_flat_table_types ) ) {
								foreach ( $non_flat_table_types as $table ) {
									$advanced_search_query[$i]['cond_'. $table] = '';
									$advanced_search_query[$i]['cond_'. $table .'_col_name'] = '';
									$advanced_search_query[$i]['cond_'. $table .'_col_value'] = '';
									$advanced_search_query[$i]['cond_'. $table .'_operator'] = '';
								}
							}
						}
                        $search_value_is_array = 0; //flag for array of search_values

						$rule_group = apply_filters('sm_user_before_search_string_process', $rule_group);
						$rules = ( ! empty( $rule_group['rules'] ) ) ? $rule_group['rules'] : array();

                        foreach( $rules as $rule ) {
							if( ! empty( $rule['type'] ) ) {
								$field = explode( '.', $rule['type'] );
								$rule['table_name'] = ( ! empty( $field[0] ) ) ? $field[0] : '';
								$rule['col_name'] = ( ! empty( $field[1] ) ) ? $field[1] : '';
							}

                            $search_col = (!empty($rule['col_name'])) ? $rule['col_name'] : '';
							$selected_search_operator = (!empty($rule['operator'])) ? $rule['operator'] : '';
							$search_operator = ( ! empty( $this->advanced_search_operators[$selected_search_operator] ) ) ? $this->advanced_search_operators[$selected_search_operator] : $selected_search_operator;
                            $search_data_type = ( ! empty( $search_cols_type[$rule['type']] ) ) ? $search_cols_type[$rule['type']] : 'text';
                            $search_value = (!empty($rule['value']) && $rule['value'] != "''") ? $rule['value'] : ( ( in_array( $search_data_type, array( "number", "numeric" ) ) ) ? '0' : '' );
                            if ( 'terms' === $rule['table_name'] && "''" === $search_value ){ // For handling taxonomy empty strings
								switch( $search_operator ){
									case 'is':
										$search_operator = 'is not';
										break;
									case 'is not':
										$search_operator = 'is';
										break;
								}
							}

                            $search_params = array('search_string' => $rule,
													'search_col' => $search_col,
													'search_operator' => $search_operator, 
													'search_data_type' => $search_data_type, 
													'search_value' => $search_value,
													'selected_search_operator' => $selected_search_operator,
													'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '',
													'table_nm' => $rule['table_name'] );

                           	if( !empty( $params['data_col_params'] ) ) {
                            	$search_value = ( in_array($search_col, $params['data_col_params']['data_cols_timestamp']) ) ? strtotime($search_value) : $search_value;
                            }

                            if (!empty($rule['table_name']) && $rule['table_name'] == $wpdb->prefix.'users') {

                            	$search_col = apply_filters('sm_search_format_query_users_col_name', $search_col, $search_params);
                                $search_value = apply_filters('sm_search_format_query_users_col_value', $search_value, $search_params);
								if ( empty( $advanced_search_query[$i]['cond_users_col_values'] ) ) {
									$advanced_search_query[$i]['cond_users_col_values'] = array();
								}
								if ( empty( $advanced_search_query[$i]['cond_users_selected_search_operators'] ) ) {
									$advanced_search_query[$i]['cond_users_selected_search_operators'] = array();
								}
                                if ( in_array( $search_data_type, array( "number", "numeric" ) ) ) {
                                    $users_cond = $rule['table_name'].".".$search_col . " ". $search_operator ." %f";
                                } else if ( $search_data_type == "date" || $search_data_type == "sm.datetime" ) {
                                	$users_cond = $rule['table_name'].".".$search_col . " ". $search_operator ." %s ";
                                } else {
                                    if ($search_operator == 'is') {
                                        $users_cond = $rule['table_name'].".".$search_col . " LIKE %s";
                                    } else if ($search_operator == 'is not') {
                                        $users_cond = $rule['table_name'].".".$search_col . " NOT LIKE %s";
                                    } else {
                                        $users_cond = $rule['table_name'].".".$search_col . " ". $search_operator ." %s";
                                    }
                                }

                                $users_cond = apply_filters('sm_search_users_cond', $users_cond, $search_params);

                                $advanced_search_query[$i]['cond_users'] .= $users_cond ." && ";
								$advanced_search_query[$i]['cond_users_col_values'][] = $search_value;
								$advanced_search_query[$i]['cond_users_selected_search_operators'][] = $search_params['selected_search_operator'];
                            } else if (!empty($rule['table_name']) && $rule['table_name'] == $wpdb->prefix.'usermeta') {

                                $advanced_search_query[$i]['cond_usermeta_col_name'] .= $search_col;
                                $search_col = apply_filters('sm_search_format_query_usermeta_col_name', $search_col, $search_params);
                                $search_value = apply_filters('sm_search_format_query_usermeta_col_value', $search_value, $search_params);
								if ( empty(  $advanced_search_query[$i]['cond_usermeta_col_values'] ) ) {
									$advanced_search_query[$i]['cond_usermeta_col_values'] = array();
								}
								if ( empty(  $advanced_search_query[$i]['cond_usermeta_selected_search_operators'] ) ) {
									$advanced_search_query[$i]['cond_usermeta_selected_search_operators'] = array();
								}
                                if ( in_array( $search_data_type, array( "number", "numeric" ) ) ) {
                                    $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value ". $search_operator ." %f )";
                                    $advanced_search_query[$i]['cond_usermeta_operator'] .= $search_operator;
                                } else if ( $search_data_type == "date" || $search_data_type == "sm.datetime" ) {
                                	$postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value ". $search_operator ." %s )";
                                    $advanced_search_query[$i]['cond_usermeta_operator'] .= $search_operator;
                                } else {
                                    if( $search_operator == 'is' ) {

                                    	if( $search_col == $wpdb->prefix.'capabilities' ) {
                                    		$search_value = '%'. $search_value .'%';
                                    	}

                                        $advanced_search_query[$i]['cond_usermeta_operator'] .= 'LIKE';
                                        $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value LIKE %s" . " )";

                                        
                                    } else if( $search_operator == 'is not' ) {

                                    	if( $search_col == $wpdb->prefix.'capabilities' ) {
                                    		$search_value = '%'. $search_value .'%';
                                    	}

                                        $advanced_search_query[$i]['cond_usermeta_operator'] .= 'NOT LIKE';
                                        $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value NOT LIKE %s" . " )";

                                    } else {

                                        $advanced_search_query[$i]['cond_usermeta_operator'] .= $search_operator;
                                        $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value ". $search_operator ." %s" . " )";
                                    }
                                    
                                }

                                $postmeta_cond = apply_filters('sm_search_usermeta_cond', $postmeta_cond, $search_params);

                                $advanced_search_query[$i]['cond_usermeta'] .= $postmeta_cond ." && ";
                                $advanced_search_query[$i]['cond_usermeta_col_name'] .= " && ";
                                $advanced_search_query[$i]['cond_usermeta_col_values'][] = $search_value;
                                $advanced_search_query[$i]['cond_usermeta_operator'] .= " && ";
								$advanced_search_query[$i]['cond_usermeta_selected_search_operators'][] = $search_params['selected_search_operator'];
                            } elseif ( ( ! empty( $rule['table_name'] ) ) && $wpdb->prefix.'terms' === $rule['table_name'] ) {
                                $advanced_search_query[$i] = Smart_Manager_Base::create_terms_table_search_query( array(
									'search_query' => $advanced_search_query[$i],
									'search_params' => $search_params,
									'rule'			=> $rule
								) );
                            }

                            $advanced_search_query[$i] = apply_filters('sm_user_search_query_formatted', $advanced_search_query[$i], $search_params);
                        }
                        if ( ! empty( $advanced_search_query[$i] ) ) {
							foreach( $advanced_search_query[$i] as $key => $value ){
								if ( is_array( $value ) || ( " && " !== substr( $value, -4 ) ) ) {
									continue;
								}
								$advanced_search_query[$i][ $key ] = ( ! empty( $value ) ) ? substr( $value, 0, -4 ) : '';
							}
						}
                    }

                    $i++;
				}
				
                //Code for handling advanced search conditions
		        if (!empty($advanced_search_query)) {

		            $index_search_string = 1; // index to keep a track of flags in the advanced search temp 
		            $search_params = array();

		            foreach ($advanced_search_query as &$advanced_search_query_string) {
		            	$this->previous_cond_has_results = true;
		                //Cond for usermeta
		                if (!empty($advanced_search_query_string['cond_usermeta'])) {

		                    $cond_usermeta_array = explode(" &&  ",$advanced_search_query_string['cond_usermeta']);

		                    $cond_usermeta_col_name = (!empty($advanced_search_query_string['cond_usermeta_col_name'])) ? explode(" && ",$advanced_search_query_string['cond_usermeta_col_name']) : '';
		                    $cond_usermeta_col_values = $advanced_search_query_string['cond_usermeta_col_values'];
		                    $cond_usermeta_operator = (!empty($advanced_search_query_string['cond_usermeta_operator'])) ? explode(" && ",$advanced_search_query_string['cond_usermeta_operator']) : '';

		                    $index = 0;
		                    $cond_usermeta_post_ids = '';
		                    $result_usermeta_search = '';

		                    foreach ($cond_usermeta_array as $cond_usermeta) {
		                        $usermeta_search_result_flag = ( $index == (sizeof($cond_usermeta_array) - 1) ) ? ', '.$index_search_string : ', 0';

		                        $cond_usermeta_col_name1 = (!empty($cond_usermeta_col_name[$index])) ? trim($cond_usermeta_col_name[$index]) : '';
		                        $cond_usermeta_col_value1 = (!empty($cond_usermeta_col_values[$index])) ? trim($cond_usermeta_col_values[$index]) : '';
		                        $cond_usermeta_operator1 = (!empty($cond_usermeta_operator[$index])) ? trim($cond_usermeta_operator[$index]) : '';

		                        $search_params = array('cond_usermeta_col_name' => $cond_usermeta_col_name1,
		                    							'cond_usermeta_col_value' => $cond_usermeta_col_value1,
		                    							'cond_usermeta_operator' => $cond_usermeta_operator1,
		                    							'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '');

		                        $cond_usermeta = apply_filters('sm_search_usermeta_condition_start', $cond_usermeta, $search_params);

		                        $search_params['cond_usermeta'] = $cond_usermeta;

		                        $usermeta_advanced_search_select = 'SELECT DISTINCT '.$wpdb->prefix.'usermeta.user_id '. $usermeta_search_result_flag .' ,0 ';
		                        $usermeta_advanced_search_from = 'FROM '.$wpdb->prefix.'usermeta ';
		                        $usermeta_advanced_search_where = 'WHERE '.$cond_usermeta;

		                        $usermeta_advanced_search_select = apply_filters('sm_search_query_usermeta_select', $usermeta_advanced_search_select, $search_params);
								$usermeta_advanced_search_from	= apply_filters('sm_search_query_usermeta_from', $usermeta_advanced_search_from, $search_params);
								$usermeta_advanced_search_where	= apply_filters('sm_search_query_usermeta_where', $usermeta_advanced_search_where, $search_params);

		                        //Query to find if there are any previous conditions
		                        $count_temp_previous_cond = $wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp 
		                                                                    SET flag = 0
		                                                                    WHERE flag = ". $index_search_string);

		                        //Code to handle condition if the ids of previous cond are present in temp table
		                        if (($index == 0 && $count_temp_previous_cond > 0) || (!empty($result_usermeta_search))) {
		                            $usermeta_advanced_search_from .= " JOIN ".$wpdb->base_prefix."sm_advanced_search_temp
		                                                                ON (".$wpdb->base_prefix."sm_advanced_search_temp.product_id = {$wpdb->usermeta}.user_id)";

		                            $usermeta_advanced_search_where .= " AND ".$wpdb->base_prefix."sm_advanced_search_temp.flag = 0";
		                        }

		                        $result_usermeta_search = array();

		                        if (!empty($usermeta_advanced_search_select ) && !empty($usermeta_advanced_search_from ) && !empty($usermeta_advanced_search_where )) {
									$usermeta_advanced_search_select = esc_sql( $usermeta_advanced_search_select );
									$exp_search_val = ( is_array( $cond_usermeta_col_values ) && ( ! empty( $cond_usermeta_col_values[ $index ] ) ) ) ? $cond_usermeta_col_values[ $index ] : $cond_usermeta_col_values;
									$exp_search_val = $this->format_advanced_search_value( array(
										'search_val' => $exp_search_val,
										'selected_search_operator' => $advanced_search_query_string['cond_usermeta_selected_search_operators'][ $index ],
									) );
									
			                        $query_usermeta_search = $wpdb->prepare( "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
			                                                        (". $usermeta_advanced_search_select ."
			                                                        ". $usermeta_advanced_search_from ."
			                                                        ".$usermeta_advanced_search_where.")", $exp_search_val );
			                        $result_usermeta_search = $wpdb->query ( $query_usermeta_search );
			                    }

			                    do_action('sm_search_usermeta_condition_complete',$result_usermeta_search,$search_params);

		                        $index++;
		                    }

		                    do_action('sm_search_usermeta_conditions_array_complete',$search_params);

		                    //Query to delete the unwanted post_ids
		                    $wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp WHERE flag = 0");
		                }

		                //Cond for users
		                if (!empty($advanced_search_query_string['cond_users'])) {

		                    $cond_users_array = explode(" && ",$advanced_search_query_string['cond_users'] );

		                    $index = 0;
		                    $cond_users_post_ids = '';
		                    $result_users_search = '';

		                    foreach ( $cond_users_array as $cond_users ) {
		                        $users_search_result_flag = ( $index == (sizeof($cond_users_array) - 1) ) ? ', '.$index_search_string : ', 0';

		                        $cond_users = apply_filters('sm_search_users_condition_start', $cond_users);

		                        $search_params = array('cond_users' => $cond_users,
		                    							'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '');

		                        $users_advanced_search_select = "SELECT DISTINCT {$wpdb->users}.id ". $users_search_result_flag ." ,0 ";
		                        $users_advanced_search_from = " FROM {$wpdb->users} ";
		                        $users_advanced_search_where = " WHERE ". $cond_users ." ";

		                        $users_advanced_search_select = apply_filters('sm_search_query_users_select', $users_advanced_search_select, $search_params);
								$users_advanced_search_from	= apply_filters('sm_search_query_users_from', $users_advanced_search_from, $search_params);
								$users_advanced_search_where	= apply_filters('sm_search_query_users_where', $users_advanced_search_where, $search_params);

		                        //Query to find if there are any previous conditions
		                        $count_temp_previous_cond = $wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp 
		                                                                    SET flag = 0
		                                                                    WHERE flag = ". $index_search_string);


		                        //Code to handle condition if the ids of previous cond are present in temp table
		                        if ( ($index == 0 && $count_temp_previous_cond > 0) || (!empty($result_users_search)) ) {
		                            $users_advanced_search_from .= " JOIN ".$wpdb->base_prefix."sm_advanced_search_temp ON (".$wpdb->base_prefix."sm_advanced_search_temp.product_id = {$wpdb->users}.id) ";

		                            $users_advanced_search_where .= " AND ".$wpdb->base_prefix."sm_advanced_search_temp.flag = 0 ";
		                        }

		                        $result_users_search = array();

		                        if (!empty($users_advanced_search_select ) && !empty($users_advanced_search_from ) && !empty($users_advanced_search_where )) {
									$users_advanced_search_select = esc_sql( $users_advanced_search_select );
									$users_advanced_search_from = esc_sql( $users_advanced_search_from );
									$exp_search_val = ( is_array( $advanced_search_query_string['cond_users_col_values'] ) && ( ! empty( $advanced_search_query_string['cond_users_col_values'][ $index ] ) ) ) ? $advanced_search_query_string['cond_users_col_values'][ $index ] : '';
									$exp_search_val = $this->format_advanced_search_value( array(
										'search_val' => $exp_search_val,
										'selected_search_operator' => $advanced_search_query_string['cond_users_selected_search_operators'][ $index ],
									) );
			                        $query_users_search = $wpdb->prepare( "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
			                                                        ( ". $users_advanced_search_select ."
			                                                        ". $users_advanced_search_from ."
			                                                        ". $users_advanced_search_where .")", $exp_search_val );
			                        $result_users_search = $wpdb->query ( $query_users_search );
			                    }
		                        
			                    do_action('sm_search_users_condition_complete',$result_users_search,$search_params);

		                        $index++;
		                    }

		                    do_action('sm_search_users_conditions_array_complete',$search_params);

		                    //Query to delete the unwanted post_ids
		                    $wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp WHERE flag = 0");

		                } elseif ( ! empty( $advanced_search_query_string['cond_terms'] ) ) {
							$this->process_terms_table_search_query( array_merge( $params, array( 
								'search_query' 			=> $advanced_search_query_string,
								'search_query_index' 	=> $index_search_string
							) ) );
						}
		                $index_search_string++;
		            }
		        }
		}

		//function to generate data model
		public function generate_data_model ($data_model, $data_col_params) {
			global $wpdb, $current_user;

			$items = array();
			$index = 0;

			$join = $where = '';
			$order_by = " ORDER BY {$wpdb->users}.id DESC ";

			$start = (!empty($this->req_params['start'])) ? $this->req_params['start'] : 0;
			$limit = (!empty($this->req_params['sm_limit'])) ? $this->req_params['sm_limit'] : 50;
			$current_page = (!empty($this->req_params['sm_page'])) ? $this->req_params['sm_page'] : '1';
			$start_offset = ($current_page > 1) ? (($current_page - 1) * $limit) : $start;

			$current_store_model = get_transient( 'sa_sm_'.$this->dashboard_key );
			if( ! empty( $current_store_model ) && !is_array( $current_store_model ) ) {
				$current_store_model = json_decode( $current_store_model, true );
			}
			$col_model = (!empty($current_store_model['columns'])) ? $current_store_model['columns'] : array();

			$search_cols_type = array(); //array for col & its type for advanced search

			if (!empty($col_model)) {
				foreach ($col_model as $col) {
					if( ! empty( $col['table_name'] ) && ! empty( $col['col_name'] ) ){
						$search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ] = $col['type'];
					}
				}
			}

			//Code to clear the advanced search temp table
	        if ( empty($this->req_params['advanced_search_query']) || $this->req_params['advanced_search_query'] == '[]') {
	            $wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp");
	            delete_option('sm_advanced_search_query');
	        }        

	        // if( !empty($this->req_params['date_filter_query']) && ( defined('SMPRO') && true === SMPRO ) ) {

	        // 	if( empty($this->req_params['search_query']) ) {
	        // 		$this->req_params['search_query'] = array( $this->req_params['date_filter_query'] );
	        // 	} else {

	        // 		$date_filter_array = json_decode(stripslashes($this->req_params['date_filter_query']),true);

	        // 		foreach( $this->req_params['search_query'] as $key => $search_string_array ) {
	        // 			$search_string_array = json_decode(stripslashes($search_string_array),true);

	        // 			foreach( $date_filter_array as $date_filter ) {
			// 				$search_string_array[] = $date_filter;		
	        // 			}

	        // 			$this->req_params['search_query'][$key] = addslashes(json_encode($search_string_array));
	        // 		}
	        // 	}
	        // }

	        $sm_advanced_search_results_persistent = 0; //flag to handle persistent search results

	        //Code fo handling advanced search functionality
	        if( !empty( $this->req_params['advanced_search_query'] ) && $this->req_params['advanced_search_query'] != '[]' ) {

				$this->req_params['advanced_search_query'] = json_decode(stripslashes($this->req_params['advanced_search_query']), true);

	            if (!empty($this->req_params['advanced_search_query'])) {
	            	$post_type = $wpdb->prefix . $this->dashboard_key;
	            	if ( ! empty( $this->req_params['table_model']['posts']['where']['post_type'] ) ) {
						$post_type = ( is_array( $this->req_params['table_model']['posts']['where']['post_type'] ) ) ? $this->req_params['table_model']['posts']['where']['post_type'] : array( $this->req_params['table_model']['posts']['where']['post_type'] );
					}
					$this->process_user_search_cond(array( 
														'search_query' => (!empty($this->req_params['advanced_search_query'])) ? $this->req_params['advanced_search_query'] : array(),
														'SM_IS_WOO30' => (!empty($this->req_params['SM_IS_WOO30'])) ? $this->req_params['SM_IS_WOO30'] : '',
														'search_cols_type' => $search_cols_type,

														'post_type' => $post_type	
													)
												);

	            }

	            $join = " JOIN {$wpdb->base_prefix}sm_advanced_search_temp
                            	ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = {$wpdb->users}.id)";

                $where = " AND {$wpdb->base_prefix}sm_advanced_search_temp.flag > 0";

	        }

	        //Code to handle simple search functionality
	        if( !empty( $this->req_params['search_text'] ) ) {


	        	$user_where_cond = array();

	        	$search_text = $wpdb->_real_escape( $this->req_params['search_text'] );

	        	$join = " JOIN {$wpdb->usermeta} 
	        				ON ({$wpdb->usermeta}.user_id = {$wpdb->users}.id)";
	        				
	        	//Code for getting users table condition
	        	if( !empty( $col_model ) ) {
	        		foreach( $col_model as $col ) {
	        			if (empty($col['src'])) continue;

						$src_exploded = explode("/",$col['src']);

						$ignored_cols = array('user_pass');
	        			$simple_search_ignored_cols = apply_filters('sm_simple_search_ignored_users_columns', $ignored_cols, $col_model);

						if( !empty( $src_exploded[0] ) && $src_exploded[0] == 'users' && !in_array($src_exploded[1], $simple_search_ignored_cols) ) {
							$user_where_cond[] = "( {$wpdb->users}.".$src_exploded[1]." LIKE '%".$search_text."%' )";
						}
	        		}
	        	}

				$where = " AND (({$wpdb->usermeta}.meta_value LIKE '%".$search_text."%') ";
				$where .= ( ( !empty( $user_where_cond ) ) ? ' OR '. implode(" OR ", $user_where_cond) : '' )." )";
			}

			if( !empty( $this->req_params['sort_params'] ) ) {
	        	if( !empty( $this->req_params['sort_params']['column'] ) && !empty( $this->req_params['sort_params']['sortOrder'] ) ) {

	        		$usermeta_cols = $numeric_usermeta_cols = $data_cols = array();

	        		foreach( $col_model as $col ) {
	        			if (empty($col['src'])) continue;

						$src_exploded = explode("/",$col['src']);

						if (empty($src_exploded)) continue;

						$type = ( !empty( $col['type'] ) ) ? $col['type'] : '';

						if ( sizeof($src_exploded) > 2) {
							$col_meta = explode("=",$src_exploded[1]);
							$col_nm = $col_meta[1];
						} else {
							$col_nm = $src_exploded[1];
						}
						$data_cols[] = $col_nm;

						if( !empty( $src_exploded[0] ) && $src_exploded[0] == 'usermeta' && $col_nm != 'user_id' ) {
							$usermeta_cols[] = $col_nm;

							if( in_array( $type, array( "number", "numeric" ) ) ) {
								$numeric_usermeta_cols[] = $col_nm;
							}
						}
					}

					// Code for handling sorting of the postmeta
					$sort_params = $this->build_query_sort_params( array( 'sort_params' => $this->req_params['sort_params'],
																		'numeric_meta_cols' => $numeric_usermeta_cols,
																		'data_cols' => $data_cols
															) );

					if( ! empty( $sort_params ) && ! empty( $sort_params['table'] ) && ! empty( $sort_params['column_nm'] && ! empty( $sort_params['sortOrder'] ) ) ) {
						$sort_params['column_nm'] = ( 'meta_value_num' === $sort_params['column_nm'] ) ? 'meta_value+0' : $sort_params['column_nm'];
						if( ! empty( $sort_params['sort_by_meta_key'] ) ) {
							$join .= ( false === strpos( $join, "JOIN {$wpdb->usermeta}" ) ) ? " JOIN {$wpdb->usermeta} 
		        					ON ({$wpdb->usermeta}.user_id = {$wpdb->users}.id)" : "";
							$where .= " AND ( ".$wpdb->prefix."". $sort_params['table'] .".meta_key ='". $sort_params['sort_by_meta_key'] ."' ) ";
						}

						$order_by = " ORDER BY ".$wpdb->prefix."". $sort_params['table'] .".". $sort_params['column_nm'] ." ". $sort_params['sortOrder'] ." ";

						if ( ! empty( $sort_params['table'] ) && 'terms' === $sort_params['table'] ) {
							$join = $this->terms_table_column_sort_query( 
								array(
									'col_name'     => $sort_params['column_nm'],
									'id'           => $wpdb->prefix . 'users.id',
									'sort_order'   => $sort_params['sortOrder'],
									'join'         => $join,
									'wp_query_obj' => '',
								)
							);
							$order_by = ' ORDER BY taxonomy_sort.term_name ' . $sort_params['sortOrder'] ;
						}
					}
	        	}
	        }

	        $query_limit_str = ( ! empty( $this->req_params['cmd'] ) && 'get_export_csv' === $this->req_params['cmd'] && ( ! empty( $this->req_params['storewide_option'] ) ) ) ? '' : 'LIMIT '.$start_offset.', '.$limit;
	        if ( ( ! empty( $this->req_params[ 'selected_ids' ] ) && '[]' !== $this->req_params[ 'selected_ids' ] ) && empty( $this->req_params['storewide_option'] ) &&  ( ! empty( $this->req_params[ 'cmd' ] ) && ( 'get_export_csv' === $this->req_params[ 'cmd' ] ) ) ) {
				$selected_ids = json_decode( stripslashes( $this->req_params[ 'selected_ids' ] ) );
				$where .= ( ! empty( $selected_ids ) ) ? " AND {$wpdb->prefix}users.ID IN (" . implode( ",", $selected_ids ) . ")" : $where;
			}
			//code to fetch data from users table
			$user_ids = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT {$wpdb->users}.id 
														FROM {$wpdb->users}
														". $join ."
														WHERE 1=%d 
														".$where, 1));
			$users_total_count = $wpdb->num_rows;

			//Code for saving the post_ids in case of simple search
			if( ( defined('SMPRO') && true === SMPRO ) && !empty( $this->req_params['search_text'] ) || (!empty($this->req_params['advanced_search_query']) && $this->req_params['advanced_search_query'] != '[]') ) {
				$user_ids_imploded = implode( ",",$user_ids );
				set_transient( 'sa_sm_search_post_ids', $user_ids_imploded , WEEK_IN_SECONDS );
			}

			$users_results = $wpdb->get_results( $wpdb->prepare("SELECT {$wpdb->users}.* 
																FROM {$wpdb->users}
																". $join ." 
																WHERE 1=%d 
																". $where ." 
																GROUP BY {$wpdb->users}.id 
																". $order_by ."
																". $query_limit_str, 1), ARRAY_A );

			if ( ! empty( $sort_params['column_nm'] ) && in_array( $sort_params['column_nm'], array( 'last_order_date','last_order_total', 'orders_count', 'orders_total' ) ) ) {
				switch ( $sort_params['column_nm'] ) {
					case 'orders_total':
					case 'last_order_total':
						$order_by = " ORDER BY order_total ". $sort_params['sortOrder'] ." ";
						break;
					case 'orders_count':
						$order_by = " ORDER BY order_count ". $sort_params['sortOrder'] ." ";
						break;
					case 'last_order_date':
						$order_by = " ORDER BY date ". $sort_params['sortOrder'] ." ";
						break;
				}
				if ( ! empty( Smart_Manager::$sm_is_woo79 ) && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
					$join .= ( false === strpos( $join, "LEFT JOIN {$wpdb->prefix}wc_orders" ) ) ? " LEFT JOIN {$wpdb->prefix}wc_orders as wo 
					ON( wo.customer_id = {$wpdb->prefix}users.ID )
					AND wo.type = 'shop_order'
					AND wo.status IN ( 'wc-completed','wc-processing' ) " : "";
					$users_results = $wpdb->get_results( $wpdb->prepare( "SELECT {$wpdb->prefix}users.*,
																	IFNULL(wo.total_amount, 0) AS order_total, 
																	IFNULL(wo.customer_id, 0) AS customer_id,
																	IFNULL( date_format( max( wo.date_created_gmt ), '%%Y-%%m-%%d, %%r' ), NULL ) AS date,
																	IFNULL( count( wo.id ), 0 ) AS order_count
																	FROM {$wpdb->prefix}users
																	". $join ."
																	WHERE 1=%d
																	". $where ." 
																	GROUP BY {$wpdb->prefix}users.id 
																	". $order_by ."
																	". $query_limit_str, 1 ), ARRAY_A );
																	
				} else {
					$join .= ( false === strpos( $join, "LEFT JOIN {$wpdb->prefix}wc_order_stats" ) ) ? " LEFT JOIN {$wpdb->prefix}wc_order_stats as os
					ON( os.customer_id = u.ID
						AND os.status IN ( 'wc-completed','wc-processing' )
						AND os.total_sales > 0 ) " : "";
					$users_results = $wpdb->get_results( $wpdb->prepare( "SELECT u.*,
														IFNULL( SUM( os.total_sales ), 0 ) as order_total,
														IFNULL( GROUP_CONCAT( distinct os.order_id ORDER BY os.date_created DESC SEPARATOR ',' ), 0 ) AS all_id,
														IFNULL( date_format( max( os.date_created ), '%%Y-%%m-%%d, %%r' ), NULL ) AS date,
														IFNULL( count( os.order_id ), 0 ) AS order_count
														FROM {$wpdb->prefix}users as u
														". $join ."
														WHERE 1=%d
														". $where ." 
														GROUP BY u.ID 
														". $order_by ."
														". $query_limit_str, 1 ), ARRAY_A );
				}	
 			}
			$total_pages = 1;

        	if( $users_total_count > $limit && $this->req_params['cmd'] != 'get_export_csv' ) {
        		$total_pages = ceil($users_total_count/$limit);
        	}

			if( !empty( $users_results ) ) {

				$user_ids = array();

				foreach( $users_results as $user ) {
					if( !empty($user['ID']) ) {
						$user_ids[] = $user['ID'];
					}
				}

				//code to get the usermeta data
				$um_results = $wpdb->get_results( $wpdb->prepare("SELECT user_id,
																		meta_key,
																		meta_value 
																		FROM {$wpdb->usermeta} 
																		WHERE 1=%d 
																			AND user_id IN (". implode(",", $user_ids) .") 
																			AND meta_key NOT IN ('". implode("','",$this->usermeta_ignored_cols) ."')
																		GROUP BY user_id, meta_key", 1), ARRAY_A );



				if( count($um_results) > 0 ) {

					$records_meta = array();

					foreach ($um_results as $meta_data) {
	                    $key = preg_replace('/[^A-Za-z0-9\-_]/', '', $meta_data['meta_key']); //for formatting meta keys of custom keys
	                    $records_meta[$meta_data['user_id']][$key] = $meta_data['meta_value'];
	                }
				}

				$customer_ids = array();

				foreach( $users_results as $user ) {

					$user_id = (!empty( $user['ID'] )) ? $user['ID'] : 0;

					foreach( $user as $key => $value ) {
						if( is_array( $data_col_params['data_cols'] ) && !empty( $data_col_params['data_cols'] ) ) {
							if( array_search( $key, $data_col_params['data_cols'] ) === false ) {
	    				 		continue; //cond for checking col in col model
							}	
						}
						
						if( is_array( $data_col_params['data_cols_checkbox'] ) && !empty( $data_col_params['data_cols_checkbox'] ) && !empty( $data_col_params['data_cols_unchecked_template'] ) && is_array( $data_col_params['data_cols_unchecked_template'] ) ) {

	    					if( array_search( $key, $data_col_params['data_cols_checkbox'] ) !== false && $value == '' ) { //added for bad_value checkbox
        						$value = $data_col_params['data_cols_unchecked_template'][$key];
        					}
        				}

	    				$key_mod = 'users_'.strtolower(str_replace(' ', '_', $key));
	    				$items [$index][$key_mod] = ( $key != 'user_pass' ) ? $value : '';
	    			}


	    			if( !empty( $records_meta[$user_id] ) ) {

	    				foreach( $records_meta[$user_id] as $key => $value ) {

	    					if (array_search($key, $data_col_params['data_cols']) === false) continue; //cond for checking col in col model

	    					//Code for handling serialized data
        					if (array_search($key, $data_col_params['data_cols_serialized']) !== false) {
								$value = maybe_unserialize($value);
								if ( !empty( $value ) ) {
									$value = json_encode($value);
								}
								
	        				} else if( array_search($key, $data_col_params['data_cols_checkbox']) !== false && $value == '' ) { //added for bad_value checkbox
	        					$value = $data_col_params['data_cols_unchecked_template'][$key];
	        				} else if( is_array( $data_col_params['data_cols_timestamp'] ) && !empty( $data_col_params['data_cols_timestamp'] ) ) {
        						if( in_array( $key, $data_col_params['data_cols_timestamp'] ) && !empty( $value ) && is_numeric( $value ) ) {
									if( function_exists('isTimestamp') && isTimestamp( $value ) ){
										$date = new DateTime("@".$value);
										$value = $date->format('Y-m-d H:i:s');
									}
        						}
        					}

	        				$key_mod = 'usermeta_meta_key_'.$key.'_meta_value_'.$key;
	        				$items [$index][$key_mod] = (!empty($value)) ? $value : '';
	    				}

	    				
	    				if( ( defined('SMPRO') && true === SMPRO ) ) {
	    					$items [$index]['custom_last_order_date'] = '-';
		    				$items [$index]['custom_last_order_total'] = '-';
		    				$items [$index]['custom_orders_count'] = '-';
		    				$items [$index]['custom_orders_total'] = '-';	
	    				} else {
	    					$items [$index]['custom_last_order_date'] = '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
		    				$items [$index]['custom_last_order_total'] = '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
		    				$items [$index]['custom_orders_count'] =  '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
		    				$items [$index]['custom_orders_total'] =  '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
	    				}
	    				
	    				$cap_key = $wpdb->prefix.'capabilities';

	    				if( !empty($records_meta[$user_id][$cap_key]) ) {

			    			$caps = maybe_unserialize($records_meta[$user_id][$cap_key]);
			    			$role = array_keys($caps);
			    			$items [$index]['usermeta_role'] = ( !empty($role[0]) ) ? $role[0] : '';

			    			if( !empty( $items [$index]['usermeta_role'] ) ) {
			    				$customer_ids[$user_id] = $index;
			    			}
			    		}

	    			}

	    			$index++;
	    		}
				
	    		if( ! empty( $customer_ids ) && ( defined('SMPRO') && true === SMPRO ) ) {
					$customers_order_meta = array();
					if ( ! empty( Smart_Manager::$sm_is_woo79 ) && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
						$customers_order_meta = $this->sm_get_custom_order_data( $customers_order_meta, 'customers_order_meta', array_keys( $customer_ids ) );
					} else {
						$customers_order_meta = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value as cust_id,
		    																		GROUP_CONCAT(distinct pm.post_ID 
									 				                                    ORDER BY p.post_date DESC SEPARATOR ',' ) AS all_id,
												                                    date_format(max(p.post_date), '%%Y-%%m-%%d, %%r') AS date,
												                           count(pm.post_id) as count
												                           FROM {$wpdb->prefix}postmeta AS pm
												                                    JOIN {$wpdb->prefix}posts AS p 
												                                    	ON (p.ID = pm.post_id
												                                    		AND p.post_type = 'shop_order'
												                                    		AND p.post_status IN ('wc-completed','wc-processing')
												                                    		AND pm.meta_key = %s)
												                           WHERE pm.meta_value IN (" . implode( ",", array_keys( $customer_ids ) ) . ")                           
												                           GROUP BY pm.meta_value
												                           ORDER BY date", '_customer_user' ), 'ARRAY_A' );
					}

		    		if( ! empty( $customers_order_meta ) ) {

		    			$order_ids = array();
		    			$max_oid = array();

		    			foreach( $customers_order_meta as $customer_order_meta ) {

		    				$oids = ( !empty( $customer_order_meta['all_id'] ) ) ? explode( ",", $customer_order_meta['all_id'] ) : array('');

		    				foreach( $oids as $oid ) {
		    					$order_ids[$oid] = $customer_order_meta['cust_id'];
		    				}

		    				$max_oid[ $oids[0] ] = $customer_order_meta['cust_id'];

		    				$index = $customer_ids[$customer_order_meta['cust_id']];
		    				$items [$index]['custom_last_order_date'] = ( !empty( $customer_order_meta['date'] ) ) ? $customer_order_meta['date'] : '-';
		    				$items [$index]['custom_orders_count'] = ( !empty( $customer_order_meta['count'] ) ) ? $customer_order_meta['count'] : 0;
		    			}

		    			if( !empty( $order_ids ) ) {
		    				$customer_totals = array();
							if ( ! empty( Smart_Manager::$sm_is_woo79 ) && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) {
								$customer_totals = $this->sm_get_custom_order_data( $customer_totals, 'customer_totals', array_keys( $order_ids ) );
							} else {
								$customer_totals = $wpdb->get_results( $wpdb->prepare( "SELECT post_id AS order_id,
	    																				meta_value AS order_total
		    																		FROM {$wpdb->prefix}postmeta
		    																		WHERE meta_key = %s
		    																			AND post_id IN ( ". implode( ",", array_keys( $order_ids ) ) ." )", '_order_total' ), 'ARRAY_A' );
							}
		    				
		    				if( !empty( $customer_totals ) ) {
		    					foreach( $customer_totals as $customer_total ) {
		    						$order_id = ( !empty( $customer_total['order_id'] ) ) ? intval( $customer_total['order_id'] ) : '';
		    						$order_total = ( !empty( $customer_total['order_total'] ) ) ? floatval( $customer_total['order_total'] ) : 0;

		    						if( empty( $order_id ) ) {
		    							return;
		    						}

		    						if( !empty( $max_oid[ $order_id ] ) ) {
		    							$index = $customer_ids[$max_oid[ $order_id ]];
		    							$items [$index]['custom_last_order_total'] = $order_total;
		    						}

		    						if( !empty( $order_ids[ $order_id ] ) ) {
		    							$index = $customer_ids[$order_ids[ $order_id ]];
										if( '-' == $items [$index]['custom_orders_total'] ){
											$items [$index]['custom_orders_total'] = 0;
										}
		    							$items [$index]['custom_orders_total'] += $order_total;
		    						}
		    					}
		    				}
		    			}
		    		}
	    		}
			}
			$formatted_terms_data = $this->format_terms_data(
				array(
	        			'items'               => $items,
	        			'terms_visible_cols'  => $data_col_params['terms_visible_cols'],
	        			'data_cols_multilist' => $data_col_params['data_cols_multilist'],
	        			'data_cols_dropdown'  => $data_col_params['data_cols_dropdown'],
	        			'ids'                 => $user_ids,
	        			'id_name'             => 'users_id',
	        			'postmeta_cols'       => array()
	        		) );
			if ( ! empty( $formatted_terms_data ) ) {
				$items = $formatted_terms_data;
			}
			$data_model ['items'] = (!empty($items)) ? $items : '';
        	$data_model ['start'] = $start+$limit;
        	$data_model ['page'] = $current_page;
        	$data_model ['total_pages'] = $total_pages;
        	$data_model ['total_count'] = $users_total_count;

			return $data_model;

		}

		//function for modifying edited data before updating
		public function user_inline_update($edited_data, $params) {
			if (empty($edited_data)) return $edited_data;
			global $wpdb;

			$default_user_keys = array( 'ID', 'user_pass', 'user_login', 'user_nicename', 'user_url', 'user_email', 'display_name', 'nickname', 'first_name', 
										'last_name', 'description', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl',
										'user_registered', 'show_admin_bar_front', 'role', 'locale' );

			foreach ($edited_data as $id => $edited_row) {

				if( empty( $id ) ) {
					continue;
				}

				$default_insert_users = array();
				$insert_usermeta = array();

				foreach( $edited_row as $key => $value ) {
					$prev_val = '';
					$edited_value_exploded = explode("/", $key);
					
					if( empty( $edited_value_exploded ) ) continue;

					$update_table = $edited_value_exploded[0];
					$update_column = $edited_value_exploded[1];

					if ( sizeof( $edited_value_exploded ) <= 2) {
						if( ( ($update_table == 'users') || ($update_table == 'usermeta' && $update_column == 'role') ) ) {

							if( $update_table == 'usermeta' && $update_column == 'role' && (!empty( $params['data_cols_list_val'][$update_column][$value] )) ) {
								$default_insert_users [$update_column] = $value;
							} else if ( $update_column != 'role' ) {
								$default_insert_users [$update_column] = $value;
							}
						} elseif ( 'terms' === $update_table ) {
							$this->update_terms_table_data(
								array(
									'update_column' => $update_column,
									'data_cols_multiselect' => $params['data_cols_multiselect'],
									'data_cols_multiselect_val' => $params['data_cols_multiselect_val'],
									'data_cols_list' => $params['data_cols_list'],
									'data_cols_list_val' => $params['data_cols_list_val'],
									'value' => $value,
									'id' => $id
								)
							);
						}
					} elseif ( sizeof( $edited_value_exploded ) > 2) {
						$cond = explode( '=', $edited_value_exploded[1]);
						$update_column_exploded = explode( '=', $edited_value_exploded[2]);
						$update_column = $update_column_exploded[1];

						if( in_array( $update_column, $params['data_cols_timestamp'] ) ) {
    						$value = strtotime($value);
    					}

						if( 'usermeta' === $update_table && in_array( $update_column, $default_user_keys ) ) {
							if( $update_column == 'use_ssl' ) {
								$value = ( $value == 'yes' ) ? 0 : 1;
							}
							$default_insert_users [$update_column] = $value;
						} else if( $update_table == 'usermeta' && in_array( $update_column, $default_user_keys ) === false ) {
							$insert_usermeta [$update_column] = $value;
						}
					}
					$this->field_names[ $id ][ $update_column ] = $key;
					// For fetching previous values.
					if ( ! empty( $id ) && ! empty( $update_table ) && ! empty( $update_column ) ) {
						$prev_val = self:: users_batch_update_prev_value( $prev_val, array( 'id' => $id, 'table_nm' => $update_table, 'col_nm' => $update_column ) );
						$prev_val = ( ( ! empty( $params ) ) && ( ! empty( $update_column ) ) ) ? sa_sm_format_prev_val( array(
								'prev_val' => $prev_val,
								'update_column' => $update_column,
								'col_data_type' => $params,
								'updated_val' => $value
							) ) : $prev_val;
						$this->prev_post_values[ $id ][ $update_column ] = $prev_val;
					}					
				}
				if ( !empty ( $default_insert_users ) ) {
					$default_insert_users['ID'] = intval( $id );

					if( isset( $default_insert_users ['user_email'] ) && empty( $default_insert_users ['user_email'] ) ){
						wp_send_json( array( 'msg'  => __( 'Email cannot be empty', 'smart-manager-for-wp-e-commerce' ) ) );
					}

					$email_exists = ( ! empty ( $default_insert_users ['user_email'] ) ) ? ( email_exists( $default_insert_users ['user_email'] ) ) : '';

					if ( ! empty( $email_exists ) && ( empty( $default_insert_users['ID'] ) || ( ! empty( $default_insert_users['ID'] ) && intval( $email_exists ) !== $default_insert_users['ID']  ) ) ){
						wp_send_json( array( 'msg'  => __( 'Email already exists. Please enter another email.', 'smart-manager-for-wp-e-commerce' ) ) );
					}

					if ( empty( $default_insert_users['ID'] ) ){ //Code for inserting users
						$default_insert_users ['user_pass'] = ( empty( $default_insert_users ['user_pass'] ) ) ? wp_generate_password() : $default_insert_users ['user_pass'];
						$default_insert_users ['user_login'] = $default_insert_users ['user_email'] ;
						$id = wp_insert_user( $default_insert_users );
						if ( ! is_wp_error( $id ) ) {
							do_action( 'register_new_user', $id);
						}
					} else { //Code for updating users
						$id = wp_update_user( $default_insert_users );
		               			if ( ( ! property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) || empty( $this->task_id ) ||is_wp_error( $id ) || empty( $id ) ) {
	    						continue;
	    					}
						foreach ( $default_insert_users as $key => $value ) {
							if ( ( ( ! empty( $key ) ) && ('ID' === $key ) ) || ! isset( $this->field_names[ $id ][ $key ] ) ) {
	    							continue;
		    					}
			    				Smart_Manager_Base::$update_task_details_params[] = array(
			    					'task_id' => $this->task_id,
								'action' => 'set_to',
								'status' => 'completed',
								'record_id' => $id,
								'field' => $this->field_names[ $id ][ $key ],                                                               
								'prev_val' => $this->prev_post_values[ $id ][ $key ],
								'updated_val' => $value,
							);
						}
						
					}				
				}
				if ( ! is_wp_error( $id ) && ! empty( $insert_usermeta ) ) {
					if ( ( ! property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) || empty( $this->task_id ) || empty( $id ) ) {
		    				continue;
		    			}
					foreach ( $insert_usermeta as $key => $value ) {
						if ( ( is_wp_error( update_user_meta( $id, $key, $value ) ) ) || empty( $key ) || ! isset( $this->field_names[ $id ][ $key ] ) ) {
						    continue;
						}
				    			Smart_Manager_Base::$update_task_details_params[] = array(
				    				'task_id' => $this->task_id,
								'action' => 'set_to',
								'status' => 'completed',
								'record_id' => $id,
								'field' => $this->field_names[ $id ][ $key ],                      
								'prev_val' => $this->prev_post_values[ $id ][ $key ],
								'updated_val' => $value,
					   	);
					}
				}
			}
		}

		public static function users_batch_update_entire_store_ids_from( $from, $params ) {
			$from = str_replace('posts', 'users', $from);
			return $from;
		}


		public static function users_batch_update_entire_store_ids_where( $where, $params ) {
			global $wpdb;

			$search_cond_pos = strpos( $where, "AND {$wpdb->base_prefix}sm_advanced_search_temp" );
			if( !empty( $search_cond_pos ) ) {
				$where = 'WHERE '. substr( $where, ($search_cond_pos + 3) );
			} else {
				$where = 'WHERE 1=1 ';
			}

			return $where;
		}


		public static function users_batch_update_entire_store_ids_query( $query ) {

			global $wpdb;

			$query = $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE 1=%d", 1 );
			return $query;
		}

		public static function users_batch_update_prev_value( $prev_val = '', $args = array() ) {

			global $wpdb;

			if( 'users' === $args['table_nm'] ) {
				$prev_val = $wpdb->get_var( $wpdb->prepare( "SELECT ". $args['col_nm'] ." FROM $wpdb->users WHERE ID = %d", $args['id'] ) );
			} else if( 'usermeta' === $args['table_nm'] && 'role' !== $args['col_nm'] ) {
				$prev_val = get_user_meta( $args['id'], $args['col_nm'], true );
			} else if( 'usermeta' === $args['table_nm'] && 'role' === $args['col_nm'] ) {
				$caps = get_user_meta($args['id'], 'wp_capabilities', true);
				$prev_val = array_keys((array)$caps);
			}
			return $prev_val;
		}

		public static function users_default_batch_update_db_updates( $flag = false, $args = array() ) {
			return false;
		}

		public static function users_post_batch_update_db_updates( $update_flag = false, $args = array() ) {

			$default_user_keys = array( 'ID', 'user_pass', 'user_login', 'user_nicename', 'user_url', 'user_email', 'display_name', 'nickname', 'first_name', 
										'last_name', 'description', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl',
										'user_registered', 'show_admin_bar_front', 'role', 'locale' );

			$id = ( !empty( $args['id'] ) ) ? $args['id'] : 0;
			$col_nm = ( !empty( $args['col_nm'] ) ) ? $args['col_nm'] : ''; 
			$value = ( !empty( $args['value'] ) ) ? $args['value'] : '';

			if( ! empty( $args['copy_from_operators'] ) && in_array( $args['operator'], $args['copy_from_operators'] ) ) {
				$value = ( is_callable( array( 'Smart_Manager_Pro_User', 'users_batch_update_new_value' ) ) ) ? self::users_batch_update_new_value( $args ) : $value;	
			}	

			if( !empty( $col_nm ) && in_array( $col_nm, $default_user_keys ) ) {
				$id = wp_update_user( array( 'ID' => $id, $args['col_nm'] => $value ) );

				if( !is_wp_error( $id ) ) {
					$update_flag = true;
				}

			} else if ( !empty( $args['table_nm'] ) && $args['table_nm'] == 'usermeta' ) {
				update_user_meta( $id, $col_nm, $value );
				$update_flag = true;
			} elseif ( 'terms' === $args['table_nm'] ) {
				self::batch_update_terms_table_data( $args );
			}

			return $update_flag;
		}

		/**
		 * Function to get new value for copy from operators 
		 */
		public static function users_batch_update_new_value( $args = array() ) {

			global $wpdb;

			if( empty( $args['selected_table_name'] ) || empty( $args['selected_column_name'] ) || empty( $args['selected_value'] ) ) {
				return '';
			}
			$args['new_value'] = '';
			if( 'users' === $args['selected_table_name'] ) {
				$args['new_value'] = $wpdb->get_var( $wpdb->prepare( "SELECT IFNULL( ". $args['selected_column_name'] .", '' ) FROM $wpdb->users WHERE ID = %d", $args['selected_value'] ) );
			} else if( 'usermeta' === $args['selected_table_name'] ) {	
				if( 'role' === $args['selected_column_name'] ) {
					$user_meta = get_userdata( $args['selected_value'] );
					$user_roles = ( ! empty( $user_meta ) ) ? $user_meta->roles : '';
					$args['new_value'] = ( ! empty( $user_roles ) && is_array( $user_roles ) ) ? $user_roles[0] : '';

				} else {
					$args['new_value'] = get_user_meta( $args['selected_value'], $args['selected_column_name'], true );
				}
			}
			return ( ( 'copy_from_field' === $args['operator'] && ( ! empty ( $args['copy_field_data_type'] ) ) ) && is_callable( array( 'Smart_Manager_Pro_Base', 'handle_serialized_data' ) ) ) ? Smart_Manager_Pro_Base::handle_serialized_data( $args ) : $args['new_value'];	
		}

		/**
		 * Function to provide deleter for the user
		 *
		 * @param  mixed $deleter The deleter.
		 * @param  array $args    Additional arguments.
		 * @return mixed $deleter The modified deleter
		 */
		public function user_deleter( $deleter = null, $args = array() ) {

			if ( ! empty( $args['source']->req_params['active_module'] ) && 'user' === $args['source']->req_params['active_module'] ) {
				global $wpdb;

				$deleter = array(
					'callback' => 'wp_delete_user'
				);

				$delete_ids = (!empty($args['source']->req_params['ids'])) ? json_decode(stripslashes($args['source']->req_params['ids']), true) : array();

				$ignore_user_ids   = self::get_admin_user_ids();
				$ignore_user_ids[] = get_current_user_id();
				$ignore_user_ids   = array_unique( $ignore_user_ids );

				$valid_delete_ids = array();
				if ( ! empty( $ignore_user_ids ) ) {
					$valid_delete_ids = array_diff( $delete_ids, $ignore_user_ids );
				}

				if ( ! empty( $valid_delete_ids ) ) {
					$deleter['delete_ids'] = $valid_delete_ids;
				}

			}

			return $deleter;
		}

		/**
		 * Function to get user ids of administrator of the website
		 *
		 * @return array $admin_user_ids The found ids
		 */
		public static function get_admin_user_ids() {

			$args = array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			);
			
			$admin_user_ids = get_users( $args );

			return $admin_user_ids;
		}


		/**
		 * Handle user ids to be deleted
		 * 
		 * @param  array $ids  The user ids.
		 * @param  array $args Additional arguments.
		 * @return array $ids
		 */
		public function users_delete_record_ids( $ids = array(), $args = array() ) {

			if ( !empty( $ids ) ) {
				$ignore_user_ids   = self::get_admin_user_ids();
				$ignore_user_ids[] = get_current_user_id();
				$ignore_user_ids   = array_unique( $ignore_user_ids );
				$ids               = array_diff( $ids, $ignore_user_ids );
			}

			return $ids;
		}

		/**
		 * Function to handle delete of a single record
		 *
		 * @param  array $params Required params
		 * @return boolean
		 */
		public static function sm_process_delete_non_posts_records( $params = array() ) {

			$deleting_id = ( !empty( $params['id'] ) ) ? $params['id'] : '';

			do_action('sm_beta_pre_process_delete_users', array( 'deleting_id' => $deleting_id, 'source' => __CLASS__ ) );

			//code for processing logic for duplicate records
			if( empty( $deleting_id ) ) {
				return false;
			}

			$result = wp_delete_user( $deleting_id );

			do_action( 'sm_beta_post_process_delete_users', array( 'deleting_id' => $deleting_id, 'source' => __CLASS__ ) );
			
			if( empty( $result ) ) {
				return false;
			} else {
				return true;
			}

		}

		/**
	     * Function for getting custom order data.
	     *
	     * @param array $data customers order meta data or customers order total.
	     * @param string $key customer order meta or order total data.
	     * @param array $ids array of customer ids.
	     * @return array fetched data.
	     */
	    public function sm_get_custom_order_data( $data = array(), $key = '', $ids = array() ) {
			global $wpdb;
			if ( empty( Smart_Manager::$sm_is_woo79 ) || empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) || ( empty( $key ) ) || ( empty( $ids ) )  || ( ! is_array( $ids ) ) ) {
				return $data;
			}

			switch( $key ) {
				case 'customers_order_meta':
					return $wpdb->get_results( 
						$wpdb->prepare(
						"SELECT customer_id as cust_id, 
								GROUP_CONCAT(ID ORDER BY date_created_gmt DESC SEPARATOR ',' ) AS all_id, 
								date_format( max( date_created_gmt ), '%%Y-%%m-%%d, %%r' ) AS date, 
								count( ID ) as count 
							FROM {$wpdb->prefix}wc_orders 
							WHERE customer_id IN (" . implode( ",", $ids ) . ") 
								AND type = 'shop_order' 
								AND status IN ('wc-completed','wc-processing') 
							GROUP BY customer_id 
							ORDER BY date" 
						), 'ARRAY_A' );
				case 'customer_totals':
				return $wpdb->get_results( "SELECT id AS order_id, 
												total_amount AS order_total 
											FROM {$wpdb->prefix}wc_orders 
										WHERE ID IN ( ". implode( ",", $ids ) ." )", 'ARRAY_A' );
	      	}
	    }

	    /**
	     * Function for getting select query for terms table.
	     *
	     * @param string $search_query_terms_select select query for terms table.
	     * @param array $search_params array of search_params.
	     * @return string select query.
	     */
	    public function search_query_terms_select( $search_query_terms_select = '', $search_params = array() ) {
	    	global $wpdb;
	    	if ( ( ! is_array( $search_params ) ) || ( ! isset( $search_params['search_query_index'] ) ) ) {
	    		return $search_query_terms_select;
	    	}
	    	return "SELECT DISTINCT " . $wpdb->prefix . "users.id, " . $search_params['search_query_index'] . " , 0  ";
		}

		/**
	     * Function for getting from query for terms table.
	     *
	     * @param string $search_query_terms_from from query for terms table.
	     * @param array $search_params array of search_params.
	     * @return string from query.
	     */
	    public function search_query_terms_from( $search_query_terms_from = '', $search_params = array() ) {
	    	global $wpdb;
		    return "FROM {$wpdb->prefix}users
							JOIN {$wpdb->prefix}term_relationships
								ON ({$wpdb->prefix}term_relationships.object_id = {$wpdb->prefix}users.id
								)";
	    }

		/**
	     * Function for getting where query for terms table.
	     *
	     * @param string $search_query_terms_where where query for terms table.
	     * @param array $search_params array of search_params.
	     * @return string where query.
	     */
		public function search_query_terms_where( $search_query_terms_where = '', $search_params = array() ) {
	    	global $wpdb;
	    	if ( ( ! is_array( $search_params ) ) || ( ! isset( $search_params['result_taxonomy_ids'] ) ) ) {
	    		return $search_query_terms_where;
	    	}
	    	$search_query_terms_where = "WHERE {$wpdb->prefix}term_relationships.term_taxonomy_id IN (". $search_params['result_taxonomy_ids'] .")";
	    	if ( ! empty( $search_params['tt_ids_to_exclude'] ) ) {
	    		$search_query_terms_where .= " AND {$wpdb->prefix}users.id NOT IN ( SELECT object_id 
																			FROM {$wpdb->prefix}term_relationships
																			WHERE term_taxonomy_id IN (". implode( ",", $search_params['tt_ids_to_exclude'] ) .") )";
	    	}
			return $search_query_terms_where;
		}

		/**
		 * Builds the SELECT part of a usermeta search query.
		 *
		 * @param string $select Optional. Existing SELECT clause to append to. Default empty string.
		 * @param array  $args   Optional. Arguments to customize the query.
		 * @return string Modified SELECT clause for the usermeta search query.
		 */
		public static function search_query_usermeta_select( $select = '', $args = array() ) {
			return ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['cond_usermeta_operator'] ) ) || ( empty( $args['cond_usermeta_col_name'] ) ) || ( 'orders_count' !== $args['cond_usermeta_col_name'] ) || ( ! empty( Smart_Manager::$sm_is_woo79 ) && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) ) ? $select : "SELECT DISTINCT pm.meta_value , 1 ,0 ";
		}
		/**
		 * Constructs the SQL FROM clause for searching user meta data.
		 *
		 * @param string $from Optional. The initial FROM clause to build upon.
		 * @param array  $args Optional. Additional arguments to customize the query.
		 * @return string The modified FROM clause for the user meta search query.
		 */
		public static function search_query_usermeta_from( $from = '', $args = array() ) {
			global $wpdb;
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['cond_usermeta_col_name'] ) ) || ( 'orders_count' !== $args['cond_usermeta_col_name'] ) ) {
				return $from;
			}
			return ( ! empty( Smart_Manager::$sm_is_woo79 ) && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) ? $from." JOIN {$wpdb->users} ON {$wpdb->prefix}users.ID = {$wpdb->prefix}usermeta.user_id": "FROM {$wpdb->postmeta} AS pm JOIN {$wpdb->posts} AS p ON (p.ID = pm.post_id AND p.post_type = 'shop_order' AND p.post_status IN ('wc-completed','wc-processing'))";
		}

		/**
		 * Modifies the WHERE clause for usermeta search queries.
		 *
		 * @param string $where The existing WHERE clause of the query.
		 * @param array  $args  Additional arguments that may influence the query modification.
		 *
		 * @return string The modified WHERE clause.
		 */
		public static function search_query_usermeta_where( $where = '', $args = array() ) {
			global $wpdb;
			// Add orders count search condition for HPOS and legacy tables.
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['cond_usermeta_operator'] ) ) || ( empty( $args['cond_usermeta_col_name'] ) ) || ( 'orders_count' !== $args['cond_usermeta_col_name'] ) ) {
				return $where;
			}
			$operator = $args['cond_usermeta_operator'];
			return ( ! empty( Smart_Manager::$sm_is_woo79 ) && ! empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) ? " AND ( SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE customer_id = {$wpdb->prefix}users.ID AND type = 'shop_order' AND status IN ('wc-completed','wc-processing') ) {$operator} %d" : $wpdb->prepare( "WHERE pm.meta_key = '_customer_user' GROUP BY pm.meta_value HAVING COUNT(pm.post_id) {$operator} %d", absint( $args['cond_usermeta_col_value'] ) );
		}
	}
}
