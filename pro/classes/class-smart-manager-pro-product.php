<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Product' ) ) {
	class Smart_Manager_Pro_Product extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
				$variation_product_old_title = '',
				$plugin_path = '';


		protected static $_instance = null;

		public $product = '';

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			parent::__construct($dashboard_key);
			self::actions();

			$this->post_type = array('product', 'product_variation');
			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );

			if ( file_exists(SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-product.php') ) {
				include_once SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-product.php';
				$this->product = new Smart_Manager_Product( $dashboard_key );
			}
			add_filter( 'sm_data_model', array( &$this, 'products_data_model' ), 12, 2 );
			add_filter( 'sm_beta_background_entire_store_ids_select', array( __CLASS__, 'background_entire_store_ids_select' ), 10, 2 );
			add_filter( 'sm_beta_background_entire_store_ids_from', array( __CLASS__, 'background_entire_store_ids_from' ), 10, 2 );
			add_filter( 'sm_beta_background_entire_store_ids_where', array( __CLASS__, 'background_entire_store_ids_where' ), 10, 2 );
                        add_filter( 'sa_manager_batch_update_selection_data', array( __CLASS__, 'expand_variation_ids_for_bulk_price_stock' ), 5, 2 );
                        add_filter( 'sa_manager_batch_update_selection_data', array( __CLASS__, 'process_batch_update_selection_data' ), 10, 2 );
			add_filter( 'sa_sm_search_results_selected_ids', array( __CLASS__, 'get_product_types_of_search_result_ids' ), 10, 2 );
			add_filter( 'sa_sm_use_get_results_in_select_entire_store_ids_query', function( $use, $args ) {
				// Call the callback function and return true/false based on its result
				return self::batch_update_params_has_suscription_update_flag( $args ) ? true : false;
			}, 10, 2 );
			add_filter('sm_required_cols',array( __CLASS__, 'add_required_cols' ), 12, 1 );
			add_filter( 'sa_manager_batch_update_params', array( __CLASS__, 'add_batch_update_params' ), 10, 2 );
		}

		public function __call( $function_name, $arguments = array() ) {

			if( empty( $this->product ) ) {
				return;
			}

			if ( ! is_callable( array( $this->product, $function_name ) ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $this->product, $function_name ), $arguments );
			} else {
				return call_user_func( array( $this->product, $function_name ) );
			}
		}

		public static function actions() {
			add_filter( 'sm_task_details_update_by_prev_val',__CLASS__. '::task_details_update_by_prev_val', 12, 1 );
			add_filter( 'sm_disable_task_details_update',__CLASS__. '::disable_task_details_update', 12, 2 );
			add_filter( 'sm_process_undo_args_before_update',__CLASS__. '::process_undo_args_before_update', 12, 1 );
			add_filter( 'sm_task_update_action',__CLASS__. '::task_update_action', 12, 2 );
			add_filter( 'sm_delete_attachment_get_matching_gallery_images_post_ids',__CLASS__. '::get_matching_gallery_images_post_ids', 12, 2 );
			add_action( 'sm_pro_pre_process_delete_records', __CLASS__. '::products_pre_process_delete_records' );
			add_action( 'sm_pro_pre_process_move_to_trash_records', __CLASS__. '::products_pre_process_move_to_trash_records' );
			add_filter( 'sm_special_batch_update_operators', __CLASS__. '::special_batch_update_operators', 10, 2 );
			// compat for 'Germanized for WooCommerce Pro' plugin.
			add_filter( 'sm_pro_update_meta_args', __CLASS__. '::update_meta_args', 10, 2 );
			add_filter( 'sm_pro_get_taxonomy_terms', __CLASS__. '::get_product_manufacturer_terms', 10, 1 );
			add_filter( 'sm_process_inline_terms_update', __CLASS__. '::update_germanized_meta', 10, 1 );
			add_action( 'sm_pro_before_run_after_update_hooks', __CLASS__. '::products_before_run_after_update_hooks' );
			add_action( 'sm_pro_post_process_terms_update', __CLASS__. '::post_process_terms_update' );
			// Disable term recount for WooCommerce products.
			add_filter('woocommerce_product_recount_terms', '__return_false');
		}

		public function products_dashboard_model ($dashboard_model, $dashboard_model_saved) {
			global $wpdb;

			$numeric_columns = array(
				'_wc_booking_duration'               => __( 'Booking duration', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_min_duration'           => __( 'Minimum duration', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_max_duration'           => __( 'Maximum duration', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_cancel_limit'           => __( 'Booking can be cancelled until', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_min_persons_group'      => __( 'Min persons', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_max_persons_group'      => __( 'Max persons', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_qty'                    => __( 'Max bookings per block', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_min_date'               => __( 'Minimum block bookable', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_max_date'               => __( 'Maximum block bookable', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_buffer_period'          => __( 'Require a buffer period of', 'smart-manager-for-wp-e-commerce' ),
				// https://woocommerce.com/products/minmax-quantities/
				'minimum_allowed_quantity'           => __( 'Minimum quantity', 'smart-manager-for-wp-e-commerce' ),
				'maximum_allowed_quantity'           => __( 'Maximum quantity', 'smart-manager-for-wp-e-commerce' ),
				'group_of_quantity'                  => __( 'Group of...', 'smart-manager-for-wp-e-commerce' ),
				'variation_minimum_allowed_quantity' => __( 'Variation Minimum quantity', 'smart-manager-for-wp-e-commerce' ),
				'variation_maximum_allowed_quantity' => __( 'Variation Maximum quantity', 'smart-manager-for-wp-e-commerce' ),
				'variation_group_of_quantity'        => __( 'Variation Group of...', 'smart-manager-for-wp-e-commerce' ),
				// https://wordpress.org/plugins/minmax-quantity-for-woocommerce/
				'min_quantity'                       => __( 'Minimum Quantity', 'smart-manager-for-wp-e-commerce' ),
				'max_quantity'                       => __( 'Maximum Quantity', 'smart-manager-for-wp-e-commerce' ),
				'min_quantity_var'                   => __( 'Variation Minimum Quantity', 'smart-manager-for-wp-e-commerce' ),
				'max_quantity_var'                   => __( 'Variation Maximum Quantity', 'smart-manager-for-wp-e-commerce' ),
				// https://wordpress.org/plugins/woo-min-max-quantity-limit/
				'_wc_mmax_min'                       => __( 'Min Quantity', 'smart-manager-for-wp-e-commerce' ),
				'_wc_mmax_max'                       => __( 'Max Quantity', 'smart-manager-for-wp-e-commerce' ),
				// [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
				'_subscription_price'				 => __( 'Subscription Price', 'smart-manager-for-wp-e-commerce' ),
				'_subscription_sign_up_fee'			 => __( 'Sign-up Fee', 'smart-manager-for-wp-e-commerce' ),
				'_subscription_trial_length'		 => __( 'Free Trial', 'smart-manager-for-wp-e-commerce' ),
				// [WooCommerce Cost Of Goods](https://woocommerce.com/products/woocommerce-cost-of-goods/)
				'_wc_cog_cost'		 				 => __( 'Cost of Good', 'smart-manager-for-wp-e-commerce' ),
				// [Germanized for WooCommerce](https://wordpress.org/plugins/woocommerce-germanized/)
				'_unit_product'						 => __( 'Product Units', 'smart-manager-for-wp-e-commerce' ),
				'_unit_base'						 => __( 'Unit Price Units', 'smart-manager-for-wp-e-commerce' ),
				'_unit_price_regular'				 => __( 'Regular Unit Price', 'smart-manager-for-wp-e-commerce' ),
				'_unit_price_sale'					 => __( 'Sale Unit Price', 'smart-manager-for-wp-e-commerce' ),
			);

			$numeric_text_editor_columns = array( '_wc_booking_duration', '_wc_booking_min_duration', '_wc_booking_max_duration', '_wc_booking_cancel_limit',
												'_wc_booking_min_date', '_wc_booking_max_date' );

			$checkbox_empty_one_columns = array(
				'_wc_booking_enable_range_picker'    => __( 'Enable Calendar Range Picker?', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_requires_confirmation'  => __( 'Requires confirmation?', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_user_can_cancel'        => __( 'Can be cancelled?', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_has_persons'            => __( 'Has persons', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_has_resources'          => __( 'Has resources', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_person_cost_multiplier' => __( 'Multiply all costs by person count', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_person_qty_multiplier'  => __( 'Count persons as bookings', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_has_person_types'       => __( 'Enable person types', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_has_restricted_days'    => __( 'Restrict start and end days?', 'smart-manager-for-wp-e-commerce' ),
				'_wc_booking_apply_adjacent_buffer'  => __( 'Adjacent Buffering?', 'smart-manager-for-wp-e-commerce' ),
			);

			$checkbox_zero_one_columns = array(
				// https://wordpress.org/plugins/woo-min-max-quantity-limit/
				'_wc_mmax_prd_opt_enable' => __( 'Enable Min Max Quantity', 'smart-manager-for-wp-e-commerce' ),
			);

			$checkbox_yes_no_columns = array(
				// https://woocommerce.com/products/minmax-quantities/
				'min_max_rules'                              => __( 'Min/Max Rules', 'smart-manager-for-wp-e-commerce' ),
				'allow_combination'                          => __( 'Allow Combination', 'smart-manager-for-wp-e-commerce' ),
				'minmax_do_not_count'                        => __( 'Order rules: Do not count', 'smart-manager-for-wp-e-commerce' ),
				'minmax_cart_exclude'                        => __( 'Order rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				'minmax_category_group_of_exclude'           => __( 'Category group-of rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				'variation_minmax_do_not_count'              => __( 'Variation Order rules: Do not count', 'smart-manager-for-wp-e-commerce' ),
				'variation_minmax_cart_exclude'              => __( 'Variation Order rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				'variation_minmax_category_group_of_exclude' => __( 'Variation Category group-of rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				// [Germanized for WooCommerce](https://wordpress.org/plugins/woocommerce-germanized/)
				'_unit_price_auto' 							 => __( 'Calculate unit prices automatically', 'smart-manager-for-wp-e-commerce' ),
			);

			$booking_duration_unit = array(
				'month'  => __( 'Month(s)', 'smart-manager-for-wp-e-commerce'),
				'day'    => __( 'Day(s)', 'smart-manager-for-wp-e-commerce' ),
				'hour'   => __( 'Hour(s)', 'smart-manager-for-wp-e-commerce' ),
				'minute' => __( 'Minutes(s)', 'smart-manager-for-wp-e-commerce' )
			);

			$column_model = &$dashboard_model['columns'];

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
					case '_wc_booking_duration_type':
						$column['key'] = __( 'Booking Duration (Type)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$booking_duration_type = array( 'fixed' => __( 'Fixed blocks of', 'smart-manager-for-wp-e-commerce'),
														'customer' => __( 'Customer defined blocks of', 'smart-manager-for-wp-e-commerce' ) );

						$column = $this->generate_dropdown_col_model( $column, $booking_duration_type );
						break;
					case '_wc_booking_duration_unit':
						$column['key'] = __( 'Booking Duration (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_cancel_limit_unit':
						$column['key'] = __( 'Booking can be cancelled until (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_min_date_unit':
						$column['key'] = __( 'Minimum block bookable (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_max_date_unit':
						$column['key'] = __( 'Maximum block bookable (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_calendar_display_mode':
						$column['key'] = __( 'Calendar display mode', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$booking_calendar_display_mode = array( ''               => __( 'Display calendar on click', 'smart-manager-for-wp-e-commerce' ),
															'always_visible' => __( 'Calendar always visible', 'smart-manager-for-wp-e-commerce' )
														);
						$column = $this->generate_dropdown_col_model( $column, $booking_calendar_display_mode );
						break;
					case '_wc_booking_resources_assignment':
						$column['key'] = __( 'Resources are...', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$booking_resources_assignment = array( 'customer'  => __( 'Customer selected', 'smart-manager-for-wp-e-commerce' ),
																'automatic' => __( 'Automatically assigned', 'smart-manager-for-wp-e-commerce' )
															);
						$column = $this->generate_dropdown_col_model( $column, $booking_resources_assignment );
						break;
					case '_wc_booking_default_date_availability':
						$column['key'] = __( 'All dates are...', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$booking_resources_date_availability = array( 'available'     => __( 'available by default', 'smart-manager-for-wp-e-commerce' ),
																'non-available' => __( 'not-available by default', 'smart-manager-for-wp-e-commerce' )
															);
						$column = $this->generate_dropdown_col_model( $column, $booking_resources_date_availability );
						break;
					case '_wc_booking_check_availability_against':
						$column['key'] = __( 'Check rules against...', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$booking_resources_date_availability = array(   ''        => __( 'All blocks being booked', 'smart-manager-for-wp-e-commerce' ),
																		'start'   => __( 'The starting block only', 'smart-manager-for-wp-e-commerce' )
																	);
						$column = $this->generate_dropdown_col_model( $column, $booking_resources_date_availability );
						break;
					case '_product_addons':
						$column['editor_schema'] = file_get_contents( SM_PLUGIN_DIR_PATH . '/pro/assets/js/json-schema/product-addons.json' );
						break;
					case '_subscription_period_interval':
						$column['key'] = __( 'Subscription Periods', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$subscription_period_interval = ( function_exists('wcs_get_subscription_period_interval_strings') ) ? wcs_get_subscription_period_interval_strings() : array();
						$column = $this->generate_dropdown_col_model( $column, $subscription_period_interval );
						break;
					case '_subscription_period':
						$column['key'] = $column['name'] = __( 'Billing Period', 'smart-manager-for-wp-e-commerce' );
						$subscription_period = ( function_exists('wcs_get_subscription_period_strings') ) ? wcs_get_subscription_period_strings() : array();
						$column = $this->generate_dropdown_col_model( $column, $subscription_period );
						break;
					case '_subscription_length':
						$column['key'] = __( 'Expire After', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$wcs_subscription_ranges = ( function_exists('wcs_get_subscription_ranges') ) ? wcs_get_subscription_ranges() : array();
						$subscription_ranges = array( __( 'Never expire', 'smart-manager-for-wp-e-commerce' ) );
						if( !empty( $wcs_subscription_ranges['day'] ) ) {
							foreach( $wcs_subscription_ranges['day'] as $key => $values ) {
								if( $key > 0 ) {
									$subscription_ranges[ $key ] = $key .' Renewals';
								}
							}
						}
						$column = $this->generate_dropdown_col_model( $column, $subscription_ranges );
						break;
					case '_subscription_trial_period':
						$column['key'] = __( 'Subscription Trial Period', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$subscription_time_periods = ( function_exists('wcs_get_available_time_periods') ) ? wcs_get_available_time_periods() : array();
						$column = $this->generate_dropdown_col_model( $column, $subscription_time_periods );
						break;
					case ( !empty( $numeric_columns[ $col_nm ] ) ):
					  		$column['key'] = $numeric_columns[ $col_nm ];
					  		$column['name'] = $column['key'];
					  		$column['type'] = 'numeric';
					  		$column['editor'] = ( in_array( $col_nm, $numeric_text_editor_columns ) ) ? $column['type'] : 'customNumericEditor';
							$column['min'] = 0;
							$column['width'] = 50;
							$column['align'] = 'right';
							break;
					case ( !empty( $checkbox_empty_one_columns[ $col_nm ] ) ):
						$column['key'] = $checkbox_empty_one_columns[ $col_nm ];
						$column['name'] = $column['key'];
						$column['type'] = 'checkbox';
						$column['editor'] = $column['type'];
						$column['checkedTemplate'] = 1;
      					$column['uncheckedTemplate'] = '';
						$column['width'] = 30;
						break;
					case ( !empty( $checkbox_zero_one_columns[ $col_nm ] ) ):
						$column['key'] = $checkbox_zero_one_columns[ $col_nm ];
						$column['name'] = $column['key'];
						$column['type'] = 'checkbox';
						$column['editor'] = $column['type'];
						$column['checkedTemplate'] = 1;
      					$column['uncheckedTemplate'] = 0;
						$column['width'] = 30;
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
					// [Germanized for WooCommerce](https://wordpress.org/plugins/woocommerce-germanized/)
					case 'product_unit':
						$column ['type']= 'dropdown';
						$column ['renderer']= 'selectValueRenderer';
						$column ['editable']= false;
						$column ['editor']= 'select';
						$column ['strict'] = true;
						$column ['allowInvalid'] = false;
						$column ['selectOptions'] = $column['values'];
						break;
					case '_unit':
						$column ['type']= 'dropdown';
						$column ['renderer']= 'selectValueRenderer';
						$column ['editable']= false;
						$column ['editor']= 'select';
						$column ['strict'] = true;
						$column ['allowInvalid'] = false;

						$column ['values'] = $column ['selectOptions'] = array();
						$column ['search_values'] = array();
						if( function_exists( 'WC_Germanized' ) ){
							$wc_germanized = WC_Germanized();
							if( is_callable( array( $wc_germanized, 'plugin_path' ) ) ){
								$column ['values'] = $column ['selectOptions'] = ( file_exists( $wc_germanized->plugin_path() . '/i18n/units.php' ) ) ? include( $wc_germanized->plugin_path() . '/i18n/units.php' ) : array();
								if( ! empty( $column ['values'] ) ){
									foreach( $column ['values'] as $key => $value ) {
										$column['search_values'][] = array( 'key' => $key, 'value' => $value );
									}
								}
							}
						}
						break;
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

		public static function products_batch_update_entire_store_ids_query( $query ) {

			global $wpdb;

			$query = $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}posts WHERE 1=%d AND post_type IN ('product', 'product_variation')", 1 );
			return $query;
		}

		//function to process duplicate products logic
		public static function process_duplicate_record( $params ) {

			$original_id = ( !empty( $params['id'] ) ) ? $params['id'] : '';

			//code for processing logic for duplicate products
			if( empty( $original_id ) ) {
				return false;
			}

			$identifier = '';

			if ( is_callable( array( 'Smart_Manager_Pro_Background_Updater', 'get_identifier' ) ) ) {
				$identifier = Smart_Manager_Pro_Background_Updater::get_identifier();
			}

			if( empty( $identifier ) ) {
				return;
			}

			$background_process_params = get_option( $identifier.'_params', false );

			if( empty( $background_process_params ) ) {
				return;
			}

			do_action('sm_beta_pre_process_duplicate_products', $original_id );

			$product = wc_get_product( $original_id );

            $parent_id = 0;
            $woo_dup_obj = '';
            $dup_prod_id = '';

            if( !empty( $background_process_params ) && (!empty( $background_process_params['SM_IS_WOO30'] ) || !empty( $background_process_params['SM_IS_WOO22'] ) || !empty( $background_process_params['SM_IS_WOO21'] ) ) ) {
                $parent_id = wp_get_post_parent_id($original_id);

                $file = WP_PLUGIN_DIR . '/woocommerce/includes/admin/class-wc-admin-duplicate-product.php';
                if( file_exists( $file ) ) {
                	include_once ( $file ); // for handling the duplicate product functionality
                }

                if ( class_exists( 'WC_Admin_Duplicate_Product' ) ) {
                	$woo_dup_obj = new WC_Admin_Duplicate_Product();
                }

            } else {

            	$file = WP_PLUGIN_DIR . '/woocommerce/admin/includes/duplicate_product.php';
                if( file_exists( $file ) ) {
                	include_once ( $file ); // for handling the duplicate product functionality
                }

                $post = get_post( $original_id );
                $parent_id = $post->post_parent;
            }

            if ($parent_id == 0) {

                if ($woo_dup_obj instanceof WC_Admin_Duplicate_Product) {
                    if( !empty( $background_process_params ) && !empty( $background_process_params['SM_IS_WOO30'] ) ) {

                        $product = wc_get_product( $original_id );

                        $dup_prod = $woo_dup_obj->product_duplicate( $product );
						do_action( 'woocommerce_product_duplicate', $dup_prod, $product ); // Added for supporting custom fields when duplicating the product.
                        if( !is_wp_error($dup_prod) ) {
                        	$dup_prod_id = $dup_prod->get_id();
                        }


                    } else {
                        $dup_prod_id = $woo_dup_obj -> duplicate_product($post,0,$post->post_status);
                    }
                } else {
                    $dup_prod_id = woocommerce_create_duplicate_from_product($post,0,$post->post_status); //TODO check
                }

                //Code for updating the post name
                if( !empty( $background_process_params ) && empty( $background_process_params['SM_IS_WOO30'] ) ) {
                    $new_slug = sanitize_title( get_the_title($dup_prod_id) );
                    wp_update_post(
                                        array (
                                            'ID'        => $dup_prod_id,
                                            'post_name' => $new_slug
                                        )
                                    );
                }

            }

            if( is_wp_error($dup_prod_id) ) {
				return false;
			} else {
				return true;
			}
		}

		/**
		* Update update_task_details_params param by using previous value
		*
		* @param array $args args has array of task details update values
		* @return void
		*/
		public static function task_details_update_by_prev_val( $args = array() ) {
			if (  empty( $args ) ) {
				return $args;
			}
			$field_name = '';
			foreach ( $args as $arg ) {
				if ( ! empty( $arg['prev_val'] ) && is_array( $arg['prev_val'] ) ) {
					foreach ( $arg['prev_val'] as $key => $prev_val ) {
						switch (true) {
							case empty( $prev_val ):
								$field_name = 'custom/product_attributes_add';
								break;
							case ( ! empty( $prev_val ) && ( empty( in_array( $arg['updated_val'], $arg['prev_val'] ) ) && ( 'all' === $arg['updated_val'] && ( 'add_to' === $arg['operator'] ) ) ) ):
								$field_name = 'custom/product_attributes_remove';
								break;
							case ( ! empty( $prev_val ) && ! empty( in_array( $arg['updated_val'], $arg['prev_val'] ) ) ):
								$field_name = 'custom/product_attributes_add';
								break;
							case ( ! empty( $prev_val ) && ( empty( in_array( $arg['updated_val'], $arg['prev_val'] ) ) || ( 'all' === $arg['updated_val'] && ( 'remove_from' === $arg['operator'] ) ) ) ):
								$field_name = 'custom/product_attributes_add';
								break;
						}
						if ( ( ! empty( $arg['task_id'] ) ) ) {
							Smart_Manager_Base::$update_task_details_params[] = array(
								'task_id' => $arg['task_id'],
								'action' => $arg['action'],
								'status' => $arg['status'],
								'record_id' => $arg['record_id'],
								'field' => $field_name,
								'prev_val' => $prev_val,
								'updated_val' => $arg['updated_val'],
							);
						}
					}
				} else if ( empty( $arg['prev_val'] ) && ! is_array( $arg['prev_val'] ) ) {
					$field_name = 'custom/product_attributes_remove';
					if ( ( ! empty( $arg['task_id'] ) ) ) {
						Smart_Manager_Base::$update_task_details_params[] = array(
							'task_id' => $arg['task_id'],
							'action' => $arg['action'],
							'status' => $arg['status'],
							'record_id' => $arg['record_id'],
							'field' => $field_name,
							'prev_val' => $arg['updated_val'],
							'updated_val' => $arg['updated_val'],
						);
					}
				}
			}
		}

		/**
		 * Disable task details update
		 *
		 * @param boolean $update_flag
		 * @param array $args
		 * @return boolean
		 */
		public static function disable_task_details_update( $update_flag = false, $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['prev_vals'] ) ) || ( empty( $args['record_id'] ) ) || ( empty( $args['field_name'] ) ) ) {
				return $update_flag;
			}
			switch ( $args['field_name'] ) {
				case 'postmeta/meta_key=_product_attributes/meta_value=_product_attributes':
					return true;
				case 'postmeta/meta_key=_stock/meta_value=_stock':
					if ( ( empty( Smart_Manager_Base::$update_task_details_params ) ) || ( empty( $args['data'] ) ) || ( ! is_array( $args['data'] ) ) || ( empty( $args['data']['task_id'] ) ) ) {
						return $update_flag;
					}
					foreach ( Smart_Manager_Base::$update_task_details_params as $task_data ) {
						if ( ( empty( $task_data ) ) || ( ! is_array( $task_data ) ) || ( empty( $task_data['record_id'] ) ) || ( empty( $task_data['task_id'] ) ) || ( empty( $task_data['field'] ) ) ) {
							continue;
						}
						if ( ( intval( $args['data']['task_id'] ) === intval( $task_data['task_id'] ) ) && ( intval( $task_data['record_id'] ) === intval( $args['record_id'] ) ) && ( $task_data['field'] === $args['field_name'] ) ) {
							return true;
						}
					}
				default:
					return false;
			}
			return false;
		}

		/**
		* Update task args before processing undo
		*
		* @param array $args array of selected field, operator and value
		* @return array $args array of updated args
		*/
		public static function process_undo_args_before_update( $args = array() ) {
			if ( empty( $args['type'] ) || empty( $args['operator'] ) || ( ! in_array( $args['type'], array( 'custom/product_attributes_add', 'custom/product_attributes_remove' ) ) ) ) {
				return $args;
			}
			if ( empty( $args['meta']['attributeName'] ) ) {
				$args['meta']['attributeName'] = $args['operator'];
			}
			switch ( $args['type'] ) {
				case 'custom/product_attributes_add':
					$args['operator'] = 'add_to';
					break;
				case 'custom/product_attributes_remove':
					$args['operator'] = 'remove_from';
					break;
			}
			$args['type'] = 'custom/product_attributes';
			return $args;
		}

		/**
		* Update task action
		*
		* @param string $operator operator name
		* @param array $args array of field, operator and value
		* @return string action name
		*/
		public static function task_update_action( $operator = '', $args = array() ) {
			if ( empty( $args['type'] ) || ( 'custom/product_attributes' !== $args['type'] ) || empty( $operator ) || empty( $args['meta']['attributeName'] ) ) {
				return $operator;
			}
			return $args['meta']['attributeName'];
		}

		/**
		* Get gallery images post ids
		*
		* @param array $attached_media_post_ids attached media post ids
		* @param array $args array of post id and attachment id
		* @return array $attached_media_post_ids Updated value of attached media post ids
		*/
		public static function get_matching_gallery_images_post_ids( $attached_media_post_ids = array(), $args = array() ) {
			if ( ! is_array( $attached_media_post_ids ) || empty( $args ) || ! is_array( $args ) || empty( $args['post_id'] ) || empty( $args['attachment_id'] ) ) {
				return $attached_media_post_ids;
			}
			global $wpdb;
			$results = $wpdb->get_col(
				$wpdb->prepare( "SELECT DISTINCT post_id 
				FROM {$wpdb->prefix}postmeta WHERE post_id <> %d AND meta_key = %s AND meta_value REGEXP %s", $args['post_id'], '_product_image_gallery', $args['attachment_id'] )
			); // Improve REGEXP.
			if ( empty( $results ) || ! is_array( $results ) ) {
				return $attached_media_post_ids;
			}
			return array_merge( $attached_media_post_ids, $results );
		}

		/**
		* Adding new custom column in data model for stock column color code based on each product level low stock threshold
		*
		* @param array $data_model data model array.
		* @param array $data_col_params data columns params array.
		* @return array $data_model Updated data model array
		*/
		public function products_data_model( $data_model = array(), $data_col_params = array() ) {
			if ( empty( $data_model ) || ! is_array( $data_model ) || empty( $data_model['items'] ) || ! is_array( $data_model['items'] ) ) {
				return $data_model;
			}
			$color_code = 'red';
			foreach ( $data_model['items'] as $key => $data ) {
				if ( empty( $data['postmeta_meta_key__stock_meta_value__stock'] ) || empty( $data['posts_id'] ) ) {
					continue;
				}
				$low_stock_threshold = get_post_meta( $data['posts_id'], '_low_stock_amount', true );
				$low_stock_threshold = ( '' !== $low_stock_threshold ) ? absint( $low_stock_threshold ) : -1;
				if ( $low_stock_threshold < 0 ) {
					continue;
				}
				switch ( true ) {
					case ( absint( $data['postmeta_meta_key__stock_meta_value__stock'] ) > absint( $low_stock_threshold ) ):
						$color_code = 'green';
						break;
					case ( absint( $data['postmeta_meta_key__stock_meta_value__stock'] ) <= absint( $low_stock_threshold ) && ! empty( absint( $data['postmeta_meta_key__stock_meta_value__stock'] ) ) ):
						$color_code = 'yellow';
						break;
				}
				$data_model['items'][ $key ]['custom_stock_color_code'] = $color_code;
			}
			return $data_model;
		}

		/**
		 * Pre-processes deletion of product records in the database.
		 *
		 *
		 * @param array $args Required args array.
		 *
		 * @return void
		 */
		public static function products_pre_process_delete_records( $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['selected_post_ids'] ) || empty( $args['post_id_placeholders'] ) ) {
				return;
			}
			$selected_post_ids = $args['selected_post_ids'];
			$post_id_placeholders = $args['post_id_placeholders'];
			global $wpdb;
			$variations_ids = self::get_variations_for_selected_ids( $args );
			if ( ! empty( $variations_ids ) && ( is_array( $variations_ids ) && ( ! is_wp_error( $variations_ids ) ) ) ) {
				foreach ( $variations_ids as $variation_id ) {
					do_action( 'woocommerce_before_delete_product_variation', $variation_id );
					do_action( 'woocommerce_delete_product_variation', $variation_id );
				}
			}
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}posts WHERE post_parent IN ( $post_id_placeholders ) AND post_type = %s",
					array_merge( $selected_post_ids, array('product_variation') )
				)
			);
			$transient_names = array();
			$transients_timeout = array();
			// Delete from wc_product_meta_lookup
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id IN ( " . $post_id_placeholders . " )",
					$selected_post_ids
				)
			);
			// Transients to clear
			$transients_to_clear = array(
				'wc_products_onsale',
				'wc_featured_products',
				'wc_outofstock_count',
				'wc_low_stock_count',
			);
			$transient_types = array(
				'product_children', 'var_prices', 'related', 'child_has_weight', 'child_has_dimensions', 'blocks_has_downloadable_product', 'product_children'
			);
			// Add base transients.
			foreach ( $transients_to_clear as $transient ) {
				$transient_names[] = "'_transient_{$transient}'";
				$transients_timeout[] = "'_transient_timeout_{$transient}'";
			}
			// Add ID-specific transients.
			foreach ( $selected_post_ids as $id ) {
				wp_cache_delete( 'lookup_table', 'object_' . $id );
				foreach ( $transient_types as $type ) {
					$transient_names[] = "'_transient_wc_{$type}_{$id}'";
					$transients_timeout[] = "'_transient_timeout_wc_{$type}_{$id}'";
				}
			}
			if ( empty( $transient_names ) || ( ! is_array( $transient_names ) ) ) {
				return;
			}
			// Clear the transients from the cache.
			foreach ( $transient_names as $transient_name ) {
				do_action( "delete_transient_{$transient_name}", $transient_name );
			}
			if ( wp_using_ext_object_cache() ) {
				wp_cache_delete_multiple( $transient_names );
			} else { // If not using external object cache, remove transients from options table.
				self::sm_delete_transients( array(
					'transient_names' => $transient_names,
					'transients_timeout' => $transients_timeout
				));
			}
			// Trigger deleted transient actions.
			foreach ( $transient_names as $transient_name ) {
				do_action( 'deleted_transient', $transient_name );
			}
			// Post action after clearing transients.
			foreach ( $selected_post_ids as $id ) {
				do_action( 'woocommerce_delete_product_transients', $id );
			}
		}

		/**
		 * Pre-processes trash of product records in the database.
		 *
		 *
		 * @param array $args Required args array.
		 *
		 * @return void
		 */
		public static function products_pre_process_move_to_trash_records( $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['selected_post_ids'] ) || empty( $args['post_id_placeholders'] ) ) {
				return;
			}
			global $wpdb;
			$selected_post_ids = $args['selected_post_ids'];
			$post_id_placeholders = $args['post_id_placeholders'];
			$variations_ids = self::get_variations_for_selected_ids( $args );
			$args['selected_ids'] = $variations_ids;
			$result = self::sm_process_move_to_trash_records( array_merge( $args, array('move_to_trash_pre_action' => true ) ) );
			if ( ! empty( $variations_ids ) && ( is_array( $variations_ids ) && ( ! is_wp_error( $variations_ids ) ) ) ) {
				foreach ( $variations_ids as $variation_id ) {
					do_action( 'woocommerce_trash_product_variation', $variation_id );
				}
			}
			$transient_names = array();
			$transients_timeout = array();
			foreach ( $selected_post_ids as $post_id ) {
				$transient_names[] = "'_transient_wc_product_children_{$post_id}'";
				$transients_timeout[] = "'_transient_timeout_wc_product_children_{$post_id}'";
			}
			self::sm_delete_transients( array(
				'transient_names' => $transient_names,
				'transients_timeout' => $transients_timeout
			));
		}

		/**
		 * Get variation ids for selected product ids.
		 *
		 *
		 * @param array $args Required args array.
		 *
		 * @return result of the query
		 */
		public static function get_variations_for_selected_ids( $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['selected_post_ids'] ) || empty( $args['post_id_placeholders'] ) ) {
				return;
			}
			global $wpdb;
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID 
					FROM {$wpdb->prefix}posts
					WHERE post_parent IN ( " . $args['post_id_placeholders'] . " ) 
					AND post_type = %s", array_merge( $args['selected_post_ids'], array('product_variation')
				) )
			);
		}

		/**
		 * Delete product related transients usign SQL query.
		 *
		 * @param array $args Required args array.
		 */
		public static function sm_delete_transients( $args = array() ) {
			global $wpdb;
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['transient_names'] ) || empty( $args['transients_timeout'] ) ) {
				return;
			}
			$transient_names = array_map( 'esc_sql', $args['transient_names'] );
			$transients_timeout = array_map( 'esc_sql', $args['transients_timeout'] );
			// Create placeholders for the IN clause.
			$transient_placeholders = implode( ', ', array_fill( 0, count( $transient_names ), '%s' ) );
			$timeout_placeholders = implode( ', ', array_fill( 0, count( $transients_timeout ), '%s' ) );
			// Delete transients
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}options WHERE option_name IN ( $transient_placeholders )",
					$transient_names
				)
			);
			// Delete transient timeouts.
			if ( $result ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}options WHERE option_name IN ( $timeout_placeholders )",
						$transients_timeout
					)
				);
			}

		}

		/**
		 * Get special handling operators
		 *
		 * @param array $special_batch_update_operators Array of special handling operators.
		 *
		 * @param array $args Array of args.
		 *
		 * @return array Array of special handling operators.
		 */
		public static function special_batch_update_operators( $special_batch_update_operators = array(), $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || ( ! is_array( $special_batch_update_operators ) ) ) {
				return $special_batch_update_operators;
			}
			return array_merge( array( '_regular_price' => 'set_to_regular_price', '_sale_price' => 'set_to_sale_price' ), $special_batch_update_operators );
		}

		/**
		 * Update meta args
		 *
		 * @param array $postarr Array of post data.
		 *
		 * @param array $args Array of args.
		 *
		 * @return array $postarr Updated meta data args.
		 */
		public static function update_meta_args( $postarr = array(), $args = array() ) {
			if ( ( ! is_array( $postarr ) ) || ( ! is_array( $args ) ) || empty( $args['taxonomy'] ) || empty( $args['taxonomy_terms'] ) || ( 'product_manufacturer' !== $args['taxonomy'] ) || ( empty( $args['term_ids'] ) ) || ( ! is_array( $args['term_ids'] ) ) ) {
				return $postarr;
			}
			foreach ( $args['term_ids'] as $term_id ) {
				$term = Smart_Manager_Pro_Base::get_term_by_id( $args['taxonomy_terms'], $term_id );
				$term_slug = ( ( ! empty( $term ) ) && ( ! empty( $term->slug ) ) ) ? $term->slug : '';
				$postarr['meta_input']['_manufacturer_slug'] = $term_slug;
			}
			return $postarr;
		}

		/**
 		 * Deletes metadata for the given taxonomy terms, replication of wc_clear_term_product_ids.
		 *
		 * @param array $taxonomy_term_ids Array of taxonomy term IDs to process.
		*/
		public static function post_process_terms_update( $taxonomy_term_ids = array() ) {
			if ( ( empty( $taxonomy_term_ids ) ) || ( ! is_array( $taxonomy_term_ids ) ) || ( ! class_exists( 'Smart_Manager_Pro_Base' ) ) || ( ! is_callable( array( 'Smart_Manager_Pro_Base', 'delete_metadata' ) ) ) ) {
				return;
			}
			$terms_meta_data = array_map(function($tt_id) {
				return array(
					'object_id'  => $tt_id,
					'meta_key'   => 'product_ids',
				);
			}, $taxonomy_term_ids);
			if ( ( empty( $terms_meta_data ) ) || ( ! is_array( $terms_meta_data ) ) ) {
				return;
			}
			Smart_Manager_Pro_Base::delete_metadata( array( 'meta_type' => 'term', 'meta_data' => $terms_meta_data ) );
		}

		/**
		 * Function for recounting product terms, ignoring hidden products.
		 * replication of _wc_term_recount
		 *
		 * @param array  $terms                       List of terms.
		 * @param object $taxonomy                    Taxonomy.
		 * @param bool   $callback                    Callback.
		 * @param bool   $terms_are_term_taxonomy_ids If terms are from term_taxonomy_id column.
		 *
		 * @return void
		*/
		public static function products_taxonomy_term_recount( $terms, $taxonomy, $callback = true, $terms_are_term_taxonomy_ids = true ) {
			if ( ( empty( $terms ) ) || ( ! is_array( $terms ) ) || empty( $taxonomy ) || ( empty( $taxonomy->name ) ) ) {
				return;
			}
			global $wpdb;
			// Standard callback.
			if ( $callback ) {
				if ( ( ! class_exists( 'Smart_Manager_Pro_Base' ) ) || ( ! is_callable( array( 'Smart_Manager_Pro_Base', 'update_post_term_count' ) ) ) ) {
					return;
				}
				Smart_Manager_Pro_Base::update_post_term_count( $terms, $taxonomy );
			}

			$exclude_term_ids            = array();
			$product_visibility_term_ids = wc_get_product_visibility_term_ids();

			if ( $product_visibility_term_ids['exclude-from-catalog'] ) {
				$exclude_term_ids[] = $product_visibility_term_ids['exclude-from-catalog'];
			}

			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && $product_visibility_term_ids['outofstock'] ) {
				$exclude_term_ids[] = $product_visibility_term_ids['outofstock'];
			}

			$query = array(
				'fields' => "
					SELECT COUNT( DISTINCT ID ) FROM {$wpdb->posts} p
				",
				'join'   => '',
				'where'  => "
					WHERE 1=1
					AND p.post_status = 'publish'
					AND p.post_type = 'product'

				",
			);

			if ( count( $exclude_term_ids ) ) {
				$query['join']  .= " LEFT JOIN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( " . implode( ',', array_map( 'absint', $exclude_term_ids ) ) . ' ) ) AS exclude_join ON exclude_join.object_id = p.ID';
				$query['where'] .= ' AND exclude_join.object_id IS NULL';
			}

			// Pre-process term taxonomy ids.
			if ( ! $terms_are_term_taxonomy_ids ) {
				// We passed in an array of TERMS in format id=>parent.
				$terms = array_filter( (array) array_keys( $terms ) );
			} else {
				// If we have term taxonomy IDs we need to get the term ID.
				$term_taxonomy_ids = $terms;
				$terms             = array();
				foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
					$term    = get_term_by( 'term_taxonomy_id', $term_taxonomy_id, $taxonomy->name );
					$terms[] = $term->term_id;
				}
			}

			// Exit if we have no terms to count.
			if ( empty( $terms ) ) {
				return;
			}

			// Ancestors need counting.
			if ( is_taxonomy_hierarchical( $taxonomy->name ) ) {
				foreach ( $terms as $term_id ) {
					$terms = array_merge( $terms, get_ancestors( $term_id, $taxonomy->name ) );
				}
			}

			// Unique terms only.
			$terms = array_unique( $terms );

			// Count the terms.
			foreach ( $terms as $term_id ) {
				$terms_to_count = array( absint( $term_id ) );

				if ( is_taxonomy_hierarchical( $taxonomy->name ) ) {
					// We need to get the $term's hierarchy so we can count its children too.
					$children = get_term_children( $term_id, $taxonomy->name );

					if ( $children && ! is_wp_error( $children ) ) {
						$terms_to_count = array_unique( array_map( 'absint', array_merge( $terms_to_count, $children ) ) );
					}
				}

				// Generate term query.
				$term_query          = $query;
				$term_query['join'] .= " INNER JOIN ( SELECT object_id FROM {$wpdb->term_relationships} INNER JOIN {$wpdb->term_taxonomy} using( term_taxonomy_id ) WHERE term_id IN ( " . implode( ',', array_map( 'absint', $terms_to_count ) ) . ' ) ) AS include_join ON include_join.object_id = p.ID';

				// Get the count.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$count = $wpdb->get_var( implode( ' ', $term_query ) );

				// Update the count.
				update_term_meta( $term_id, 'product_count_' . $taxonomy->name, absint( $count ) );
			}

			delete_transient( 'wc_term_counts' );
		}

		/**
		 * Function to get product manufacturer terms.
		 * compat for 'Germanized for WooCommerce Pro' plugin.
		 *
		 * @param array  $taxonomies Array of taxonomies to be updated.
		 *
		 * @return array Array of product_manufacturer taxonomy terms.
		 */
		public static function get_product_manufacturer_terms ( $taxonomies = array() ) {
			$product_manufacturer_terms = array();
			if ( ( empty( $taxonomies ) ) || ( ! in_array( 'product_manufacturer', $taxonomies ) ) ) {
				return $product_manufacturer_terms;
			}
			$product_manufacturer_terms = get_terms(
				array(
					'taxonomy'   => 'product_manufacturer', // Pass an array of taxonomies.
					'hide_empty' => false,
				)
			);
			return ( ( ! empty( $product_manufacturer_terms ) ) && ( ! is_wp_error( $product_manufacturer_terms ) ) ) ? $product_manufacturer_terms : array();
		}

		/**
		 * Function to get product manufacturer terms.
		 * compat for 'Germanized for WooCommerce Pro' plugin.
		 *
		 * @param object  $post updated post object.
		 * @param object  $post_before post object before update.
		 *
		 * @return void
		 */
		public static function products_before_run_after_update_hooks( $post = null, $post_before = null ) {
			if ( ( empty( $post ) ) || ( empty( $post_before ) ) ) {
				return;
			}
			if ( class_exists( 'WC_GZDP_Unit_Price_Helper' ) ) {
				remove_action( 'wp_insert_post', array( WC_GZDP_Unit_Price_Helper::instance(), 'maybe_save_unit_price' ), 10 );
			}
		}

		/**
	     * Function to update meta values of Germanized for WooCommerce Pro plugin when updating terms by inline edit.
		 * compat for 'Germanized for WooCommerce Pro' plugin.
		 *
		 * @param  array $args array data.
		 *
		 * @return array $args array data.
		 */
		public static function update_germanized_meta( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['id'] ) ) || ( empty( $args['taxonomies'] ) ) || ( ! is_array( $args['taxonomies'] ) ) || ( ! in_array( 'product_manufacturer', $args['taxonomies'] ) ) || ( "product_manufacturer" !== $args['update_column'] ) ) {
				return $args;
			}
			$id = $args['id'];
			$args['prev_postmeta_values'][$id]['_manufacturer_slug'] = "";
			$args['meta_keys_edited']['_manufacturer_slug'] = "";
			//If all terms are removed then empty meta field.
			if ( ( is_array( $args['term_ids'] ) ) && ( 1 === count( $args['term_ids'] ) ) && ( ( empty( $args['term_ids'][0] ) ) || ( '0' === $args['term_ids'][0] ) ) ) {
				$args['meta_data_edited']['postmeta'][$id]['_manufacturer_slug'] = "";
				return $args;
			}

			$product_manufacturer_terms = get_terms(
				array(
					'taxonomy'   => 'product_manufacturer',
					'hide_empty' => false,
				)
			);

			$meta_data = apply_filters(
				'sm_pro_update_meta_args',
				array(),
				array(
					'taxonomy'       => $args['update_column'],
					'term_ids'       => $args['term_ids'],
					'taxonomy_terms' => $product_manufacturer_terms
				)
			);

			if ( empty( $meta_data ) || ! is_array( $meta_data ) || empty( $meta_data['meta_input']['_manufacturer_slug'] ) ) {
				return $args;
			}

			$args['meta_data_edited']['postmeta'][$id]['_manufacturer_slug'] = $meta_data['meta_input']['_manufacturer_slug'];

			$term_obj = Smart_Manager_Pro_Base::get_term_by_id( $product_manufacturer_terms, $args['prev_val'] );

			$args['prev_postmeta_values'][$id]['_manufacturer_slug'] = ( ! empty( $term_obj ) && ! empty( $term_obj->slug ) ) ? $term_obj->slug : '';

			return $args;
		}

		/**
		 * Checks if the batch update parameters include a subscription update flag.
		 *
		 * @param array $args An array of parameters to check for the subscription update flag. Default empty array.
		 * 
		 * @return bool True if the subscription update flag is present, false otherwise.
		 */
		public static function batch_update_params_has_suscription_update_flag( $args = array() ){
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) ) {
				return;
			}
			return ( ( ! empty( $args['update_product_subscriptions_price'] ) ) && ( 'true' === $args['update_product_subscriptions_price'] ) ) ? true : false;
		}

		/**
		 * Extends a SELECT SQL query for retrieving IDs and product type of all products in the store, when updating the subscription product price.
		 *
		 * @param string $select The base SELECT statement to use. Default is an empty string.
		 * @param array  $args   Additional arguments to customize the query. Default is an empty array.
		 * 
		 * @return string The generated SELECT SQL query for product IDs.
		 */
		public static function background_entire_store_ids_select( $select = '', $args = array() ) {
			if ( ( empty( $select ) ) || ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( self::batch_update_params_has_suscription_update_flag( $args ) ) ) ) {
				return  $select;
			}
			global $wpdb;
			return "SELECT {$wpdb->prefix}posts.ID AS product_id, t.slug AS product_type";
		}

		/**
		 * Extends From clause of the query for selecting product IDs across the entire store in a background process when updating the subscription product price.
		 *
		 * @param string $from From clause
		 * @param array  $args Additional arguments to filter or modify the query. Default empty array.
		 * 
		 * @return array List of product IDs matching the criteria.
		 */
		public static function background_entire_store_ids_from( $from = '', $args = array() ) {
			if ( ( empty( $from ) ) || ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( self::batch_update_params_has_suscription_update_flag( $args ) ) ) ) {
				return  $from;
			}
			global $wpdb;
			return $from." 
			LEFT JOIN {$wpdb->prefix}posts AS parent ON {$wpdb->prefix}posts.post_parent = parent.ID
			LEFT JOIN {$wpdb->prefix}term_relationships AS tr 
				ON (
					CASE 
						WHEN {$wpdb->prefix}posts.post_type = 'product_variation' THEN parent.ID
						ELSE {$wpdb->prefix}posts.ID
					END
				) = tr.object_id
			LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_type'
			LEFT JOIN {$wpdb->prefix}terms AS t ON tt.term_id = t.term_id";

		}

		/**
		 * Extends a WHERE clause for selecting product IDs across the entire store in a background process when updating the subscription product price.
		 *
		 * @param string $where Existing WHERE clause to append to. Default empty.
		 * @param array  $args  Additional arguments for customizing the WHERE clause. Default empty array.
		 * 
		 * @return string Modified WHERE clause for querying product IDs.
		 */
		public static function background_entire_store_ids_where( $where = '', $args = array() ) {
			if ( ( empty( $where ) ) || ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( self::batch_update_params_has_suscription_update_flag( $args ) ) ) ) {
				return  $where;
			}
			global $wpdb;
			return " WHERE {$wpdb->prefix}posts.post_type IN ('" . implode("','", $args['post_type']) . "') AND tt.taxonomy = 'product_type'";
		}

		/**
		 * Process batch updates selection data based on the provided parameters.
		 *
		 * @param array $selected_data Array of selected product data to be updated.
		 * @param array $args    Array of request parameters for the batch update operation.
		 * @return void
		 */
                public static function expand_variation_ids_for_bulk_price_stock( $selected_data = array(), $args = array() ) {
                        if ( empty( $selected_data ) || ! is_array( $selected_data ) || empty( $selected_data['selected_ids'] ) ) {
                                return $selected_data;
                        }
                        if ( empty( $args ) || ! is_array( $args ) || empty( $args['batchUpdateActions'] ) ) {
                                return $selected_data;
                        }

                        $batch_actions = json_decode( stripslashes( $args['batchUpdateActions'] ), true );
                        if ( empty( $batch_actions ) || ! is_array( $batch_actions ) ) {
                                return $selected_data;
                        }

                        $price_stock_keys = array( '_regular_price', '_sale_price', '_price', '_stock', '_stock_status', '_manage_stock', '_backorders' );
                        $requires_expansion = false;
                        foreach ( $batch_actions as $batch_action ) {
                                if ( empty( $batch_action['type'] ) || ! is_string( $batch_action['type'] ) ) {
                                        continue;
                                }
                                foreach ( $price_stock_keys as $key ) {
                                        if ( false !== strpos( $batch_action['type'], $key ) ) {
                                                $requires_expansion = true;
                                                break 2;
                                        }
                                }
                        }

                        if ( empty( $requires_expansion ) ) {
                                return $selected_data;
                        }

                        $selected_ids = $selected_data['selected_ids'];
                        if ( isset( $selected_ids[0] ) && is_array( $selected_ids[0] ) ) {
                                $selected_ids = array_column( $selected_ids, 'product_id' );
                        }
                        $selected_ids = array_filter( array_map( 'absint', (array) $selected_ids ) );
                        if ( empty( $selected_ids ) ) {
                                return $selected_data;
                        }

                        global $wpdb;
                        $variation_ids = array();
                        foreach ( array_chunk( $selected_ids, 500 ) as $id_chunk ) {
                                $id_chunk = array_filter( array_map( 'absint', $id_chunk ) );
                                if ( empty( $id_chunk ) ) {
                                        continue;
                                }
                                $ids_sql = implode( ',', $id_chunk );
                                $results = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent IN ( $ids_sql )"
                                );
                                if ( empty( $results ) ) {
                                        continue;
                                }
                                $variation_ids = array_merge( $variation_ids, array_map( 'absint', $results ) );
                        }

                        if ( ! empty( $variation_ids ) ) {
                                $selected_ids = array_merge( $selected_ids, $variation_ids );
                        }

                        $selected_data['selected_ids'] = array_values( array_unique( $selected_ids ) );
                        return $selected_data;
                }

                public static function process_batch_update_selection_data( $selected_data = array(), $args = array() ) {
			if( ( empty( $selected_data ) ) || ( ! is_array( $selected_data ) ) || ( empty( $selected_data['selected_ids'] ) ) || ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( Smart_Manager_Pro_Product::batch_update_params_has_suscription_update_flag( $args ) ) ) || ( empty( $selected_data['entire_store'] ) ) ) {
				return $selected_data;
			}
			$subscription_types = array( 'subscription', 'variable-subscription' );
			// Get only subscription product IDs.
			$selected_data['subscription_product_ids'] = array_column(
				array_filter(
					$selected_data['selected_ids'],
					function( $row ) use ( $subscription_types ) {
						return in_array( $row['product_type'], $subscription_types, true );
					}
				),
				'product_id'
			);
			// Flatten all product IDs.
			$selected_data['selected_ids'] = array_column( $selected_data['selected_ids'], 'product_id' );
			return $selected_data;
		}

		/**
		 * Retrieves an array containing product IDs and their corresponding product types from the transient list.
		 * 
		 * @param array $selected_ids Array of selected product ids.
		 * @param array $args    Array of request parameters for the batch update operation.
		 * 
		 * @return array Array of product data, where each element contains a product ID and its type.
		 */
		public static function get_product_types_of_search_result_ids( $selected_ids = array(), $args = array() ) {
			if ( ( empty( self::batch_update_params_has_suscription_update_flag( $args ) ) ) ) {
				return $selected_ids;
			}
			global $wpdb;
			// Prepare the SQL query, TODO: Later add the joins in the advanced_search_temp table in place of FIND_IN_SET to optimize the performance.
			$query = "
				SELECT 
					p.ID AS product_id,
					t.slug AS product_type
				FROM {$wpdb->prefix}posts AS p
				LEFT JOIN {$wpdb->prefix}posts AS parent 
					ON p.post_parent = parent.ID
				LEFT JOIN {$wpdb->prefix}term_relationships AS tr 
					ON (
						CASE 
							WHEN p.post_type = 'product_variation' THEN parent.ID
							ELSE p.ID
						END
					) = tr.object_id
				LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt 
					ON tr.term_taxonomy_id = tt.term_taxonomy_id
				LEFT JOIN {$wpdb->prefix}terms AS t 
					ON tt.term_id = t.term_id
				WHERE FIND_IN_SET(
					p.ID,
					(
						SELECT option_value
						FROM {$wpdb->prefix}options 
						WHERE option_name =  %s
						LIMIT 1
					)
				)
				AND tt.taxonomy = 'product_type'
				AND p.post_type IN ('product', 'product_variation')
			";
			$results = $wpdb->get_results( $wpdb->prepare( $query, '_transient_sa_sm_search_post_ids' ) );
			if ( ( empty( $results ) ) || ( is_wp_error( $results ) ) || ( ! is_array( $results ) ) ) {
				return $selected_ids;
			}
			$products = array();
			foreach ( $results as $row ) {
				if ( ( empty( $row ) ) || ( empty( $row->product_id ) ) || ( empty( $row->product_type ) ) ) {
					continue;
				}
				$products[] = array(
					'product_id'   => $row->product_id,
					'product_type' => $row->product_type,
				);
			}
			return $products;
		}

		/**
		 * Adds required columns to the provided array of columns.
		 *
		 * @param array $cols Optional. An array of existing columns. Default is an empty array.
		 * @return array The array of columns with required columns added.
		 */
		public static function add_required_cols( $cols = array() ) {
			if ( ! is_array( $cols ) ) {
				return;
			}
			$susbcriptions_exist  = ( class_exists( 'WC_Subscriptions' ) && function_exists( 'wcs_do_subscriptions_exist' ) ) ? wcs_do_subscriptions_exist() : false;
			if ( empty( $susbcriptions_exist ) ) {
				return $cols;
			}
			$cols[]  = 'terms_product_type';
			return $cols;
		}

		/**
		 * Adds batch update parameters for products.
		 *
		 * @param array $params Existing parameters to be updated.
		 * @param array $args Additional arguments for batch update.
		 * @return array Modified parameters array including batch update arguments.
		 */
		public static function add_batch_update_params( $params = array(), $args = array() ) {
			if ( ( empty( $params ) ) || ( ! is_array( $params ) ) || ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['req_params'] ) ) || ( ! is_array( $args['req_params'] ) ) || ( empty( $args['req_params']['update_product_subscriptions_price'] ) ) || ( 'true' !==  $args['req_params']['update_product_subscriptions_price'] ) ) {
				return $params;
			}
			//Get subscription product ids.
			$subscription_product_ids = ( ! empty(  $args['req_params']['subscription_product_ids'] ) ) ? trim( $args['req_params']['subscription_product_ids'], '[]' ) : array();
			$subscription_product_ids = json_decode( "[$subscription_product_ids]" );
			if ( ( ! empty( $params['entire_store'] ) ) && ( ! empty( $args['selected_ids_and_entire_store_flag']['subscription_product_ids'] ) && ( is_array( $args['selected_ids_and_entire_store_flag']['subscription_product_ids'] ) ) ) ) {
				$subscription_product_ids = $args['selected_ids_and_entire_store_flag']['subscription_product_ids'];
			}
			if ( ( empty( $subscription_product_ids ) ) || ( ! is_array( $subscription_product_ids ) ) ) {
				return $params;
			}
			update_option( $args['identifier'] . '_subscription_product_ids', $subscription_product_ids, 'no' );
			$params['update_product_subscriptions_price'] = true;
			return $params;
		}
	} //End of Class
}
