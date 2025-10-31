<?php
/**
 * Common product class.
 *
 * @package common-pro/
 * @since       8.64.0
 * @version     8.67.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SA_Manager_Pro_Product' ) ) {
	/**
	 * Class properties and methods will go here.
	 */
	class SA_Manager_Pro_Product extends SA_Manager_Pro_Base {
		/**
		 * Current dashboard key
		 *
		 * @var string
		 */
		public $dashboard_key = '';
		/**
		 * Default store model
		 *
		 * @var array
		 */
		public $default_store_model = array();

		/**
		 * Stores the plugin SKU
		 *
		 * @var string
		 */
		public $plugin_sku = '';

		/**
		 * Holds common core product object.
		 *
		 * @var null $common_core_product
		 */
		public $common_core_product = null;

		/**
		 * Stores the plugin directory path.
		 *
		 * @var string $plugin_dir_path
		 */
		public $plugin_dir_path = '';

		/**
		 * Stores the plugin file path.
		 *
		 * @var string $plugin_file
		 */
		public $plugin_file = '';

		/**
		 * Holds the old title of a variation product.
		 *
		 * @var string $variation_product_old_title The previous title of the variation product.
		 */
		public $variation_product_old_title = '';

		/**
		 * Holds the single instance of the class.
		 *
		 * Ensures only one instance of the class exists.
		 *
		 * @var self|null
		 */
		protected static $instance = null;

		/**
		 * Returns the single instance of the class, creating it if it doesn't exist.
		 *
		 * This method implements the Singleton pattern. It ensures that only one
		 * instance of the class is created, using the provided dashboard key.
		 *
		 * @param array $plugin_data Array of plugin data.
		 * @return self|null self::$instance The single instance of the class
		 */
		public static function instance( $plugin_data = array() ) {
			if ( is_null( self::$instance ) && ( ! empty( $plugin_data ) ) ) {
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
			$plugin_data           = ( ! empty( $plugin_data ) && is_array( $plugin_data ) ) ? $plugin_data : array();
			$folder_flag           = ( ! empty( $plugin_data['folder_flag'] ) && '/lib' === $plugin_data['folder_flag'] ) ? $plugin_data['folder_flag'] : '';
			$this->plugin_dir_path = ( ! empty( $plugin_data['plugin_dir'] ) ) ? $plugin_data['plugin_dir'] : '';
			$core_base_path        = $this->plugin_dir_path . $folder_flag . '/common-core/classes/';
			$core_file_name        = 'class-sa-manager-product.php';
			is_callable( array( 'SA_Manager_Pro_Background_Updater', 'sa_manager_file_safe_include' ) ) ? SA_Manager_Pro_Background_Updater::sa_manager_file_safe_include( $core_base_path, $core_file_name ) : '';
			if ( class_exists( 'SA_Manager_Product' ) && is_callable( array( 'SA_Manager_Product', 'instance' ) ) ) {
				$this->common_core_product = SA_Manager_Product::instance( $plugin_data );
			}
			$this->plugin_sku = ( ! empty( $plugin_data['plugin_sku'] ) ) ? $plugin_data['plugin_sku'] : '';
			parent::__construct( $plugin_data );
			$this->post_type   = array( 'product', 'product_variation' );
			$this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
			if (
				is_callable( array( $this, 'batch_update_copy_from_ids_query_result' ) )
				&& ! has_filter(
					$this->plugin_sku . '_batch_update_copy_from_ids_query_result',
					array( $this, 'batch_update_copy_from_ids_query_result' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_batch_update_copy_from_ids_query_result',
					array( $this, 'batch_update_copy_from_ids_query_result' ),
					10,
					2
				);
			}

			if (
				is_callable( array( $this, 'products_post_batch_process_args' ) )
				&& ! has_filter(
					$this->plugin_sku . '_post_batch_process_args',
					array( $this, 'products_post_batch_process_args' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_post_batch_process_args',
					array( $this, 'products_post_batch_process_args' ),
					11,
					1
				);
			}

			if (
				is_callable( array( $this, 'products_pre_batch_update_db_updates' ) )
				&& ! has_action(
					$this->plugin_sku . '_pre_batch_update_db_updates',
					array( $this, 'products_pre_batch_update_db_updates' )
				)
			) {
				add_action(
					$this->plugin_sku . '_pre_batch_update_db_updates',
					array( $this, 'products_pre_batch_update_db_updates' )
				);
			}

			if (
				is_callable( array( $this, 'products_post_batch_update_db_updates' ) )
				&& ! has_filter(
					$this->plugin_sku . '_post_batch_update_db_updates',
					array( $this, 'products_post_batch_update_db_updates' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_post_batch_update_db_updates',
					array( $this, 'products_post_batch_update_db_updates' ),
					10,
					2
				);
			}

			if (
				is_callable( array( $this, 'products_batch_update_prev_value' ) )
				&& ! has_filter(
					$this->plugin_sku . '_batch_update_prev_value',
					array( $this, 'products_batch_update_prev_value' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_batch_update_prev_value',
					array( $this, 'products_batch_update_prev_value' ),
					12,
					2
				);
			}

			if (
				is_callable( array( $this, 'get_value_for_copy_from_operator' ) )
				&& ! has_filter(
					$this->plugin_sku . '_get_value_for_copy_from_operator',
					array( $this, 'get_value_for_copy_from_operator' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_get_value_for_copy_from_operator',
					array( $this, 'get_value_for_copy_from_operator' ),
					12,
					2
				);
			}

			if (
				is_callable( array( $this, 'update_value_for_copy_from_operator' ) )
				&& ! has_filter(
					$this->plugin_sku . '_update_value_for_copy_from_operator',
					array( $this, 'update_value_for_copy_from_operator' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_update_value_for_copy_from_operator',
					array( $this, 'update_value_for_copy_from_operator' ),
					12,
					1
				);
			}

			if (
				is_callable( array( $this, 'products_pre_batch_update' ) )
				&& ! has_action(
					$this->plugin_sku . '_pre_process_batch',
					array( $this, 'products_pre_batch_update' )
				)
			) {
				add_action(
					$this->plugin_sku . '_pre_process_batch',
					array( $this, 'products_pre_batch_update' )
				);
			}
			if (
				is_callable( array( $this, 'products_dashboard_model' ) )
				&& ! has_filter(
					'sa_' . $this->plugin_sku . '_dashboard_model',
					array( $this, 'products_dashboard_model' )
				)
			) {
				add_filter(
					'sa_' . $this->plugin_sku . '_dashboard_model',
					array( $this, 'products_dashboard_model' ),
					12,
					2
				);
			}
			if (
				is_callable( array( $this, 'products_before_run_after_update_hooks' ) )
				&& ! has_action(
					$this->plugin_sku . '_before_run_after_update_hooks',
					array( $this, 'products_before_run_after_update_hooks' )
				)
			) {
				add_action(
					$this->plugin_sku . '_before_run_after_update_hooks',
					array( $this, 'products_before_run_after_update_hooks' )
				);
			}
			if (
				is_callable( array( $this, 'post_process_terms_update' ) )
				&& ! has_action(
					$this->plugin_sku . '_post_process_terms_update',
					array( $this, 'post_process_terms_update' )
				)
			) {
				add_action(
					$this->plugin_sku . '_post_process_terms_update',
					array( $this, 'post_process_terms_update' )
				);
			}
			// Compat for 'Germanized for WooCommerce Pro' plugin.
			if (
				is_callable( array( $this, 'get_product_manufacturer_terms' ) )
				&& ! has_filter(
					$this->plugin_sku . '_get_taxonomy_terms',
					array( $this, 'get_product_manufacturer_terms' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_get_taxonomy_terms',
					array( $this, 'get_product_manufacturer_terms' )
				);
			}
			if ( is_callable( array( $this, 'update_meta_args' ) )
				&& ! has_filter(
					$this->plugin_sku . '_update_meta_args',
					array( $this, 'update_meta_args' )
				)
			) {
				add_filter(
					$this->plugin_sku . '_update_meta_args',
					array( $this, 'update_meta_args' ),
					10,
					2
				);
			}
		}

		/**
		 * Magic method to proxy undefined method calls to the common core product object.
		 *
		 * @param string $function_name Name of the method being called.
		 * @param array  $arguments     Optional. Arguments to pass to the method. Default empty array.
		 *
		 * @return mixed The result of the proxied method call, or null if not callable.
		 */
		public function __call( $function_name, $arguments = array() ) {
			if ( empty( $this->common_core_product ) ) {
				return;
			}
			if ( ! is_callable( array( $this->common_core_product, $function_name ) ) ) {
				return;
			}
			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $this->common_core_product, $function_name ), $arguments );
			} else {
				return call_user_func( array( $this->common_core_product, $function_name ) );
			}
		}

		/**
		 * Get value for copy from operator
		 *
		 * @param string $new_val new value.
		 * @param array  $args array of selected field, operator and value.
		 * @return array value of selected column.
		 */
		public static function get_value_for_copy_from_operator( $new_val = '', $args = array() ) {
			if ( empty( $args['selected_column_name'] ) || ( 'product_attributes' !== $args['selected_column_name'] ) || empty( intval( $args['selected_value'] ) ) ) {
				return $new_val;
			}
			return get_post_meta( $args['selected_value'], '_product_attributes', true );
		}

		/**
		 * Function for overriding the select clause for fetching the ids for batch update 'copy from' functionality.
		 *
		 * @param array $query_result query result.
		 * @param array $args array of selected field, operator and value.
		 * @return string $select updated select query
		 */
		public function batch_update_copy_from_ids_query_result( $query_result = array(), $args = array() ) {
			if ( empty( $args ) || ! is_array( $args ) || empty( $args['search_ids'] ) || ! is_array( $args['search_ids'] ) || empty( $args['dashboards'] ) || ! is_array( $args['dashboards'] ) || empty( $args['search_term'] ) ) {
				return $query_result;
			}
			global $wpdb;
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					" SELECT ID AS id, 
					( CASE 
						WHEN (post_excerpt != '' AND post_type = 'product_variation') THEN CONCAT(post_title, ' - ( ', post_excerpt, ' ) ')
						ELSE post_title
					END ) as title FROM {$wpdb->prefix}posts 
							WHERE post_status != 'trash' 
							AND ( id LIKE %s OR post_title LIKE %s OR post_excerpt LIKE %s ) 
							AND id IN ( " . implode( ',', array_fill( 0, count( $args['search_ids'] ), '%d' ) ) . ' ) 
							AND post_type IN (' . implode( ',', array_fill( 0, count( $args['dashboards'] ), '%s' ) ) . ')',
					array_merge(
						array(
							'%' . $wpdb->esc_like( $args['search_term'] ) . '%',
							'%' . $wpdb->esc_like( $args['search_term'] ) . '%',
							'%' . $wpdb->esc_like( $args['search_term'] ) . '%',
						),
						$args['search_ids'],
						$args['dashboards']
					)
				),
				'ARRAY_A'
			);
			return ( ! empty( $results ) ) ? $results : $query_result;
		}
		/**
		 * Get previous values for the taxonomy
		 *
		 * @param string $prev_val previous value for current taxonomy.
		 * @param array  $args args has id, column name and table name.
		 * @return array|string Returns term IDs array on success, or the previous value.
		 */
		public static function products_batch_update_prev_value( $prev_val = '', $args = array() ) {
			if ( 'custom' === $args['table_nm'] && 'product_attributes' === $args['col_nm'] && ! empty( $args['meta']['attributeName'] ) ) {
				$result = wp_get_object_terms( $args['id'], $args['meta']['attributeName'], 'orderby=none&fields=ids' );
				return ( ( ! empty( $result ) ) && ( ! is_wp_error( $result ) ) ) ? $result : $prev_val;
			}
			return $prev_val;
		}

		/**
		 * Prepare dashboard model for products.
		 *
		 * @param array $dashboard_model       Default dashboard model for products.
		 * @param array $dashboard_model_saved Saved dashboard model settings, if available.
		 *
		 * @return array Modified dashboard model for products.
		 */
		public function products_dashboard_model( $dashboard_model, $dashboard_model_saved ) {
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
				// phpcs:ignore https://woocommerce.com/products/minmax-quantities/
				'minimum_allowed_quantity'           => __( 'Minimum quantity', 'smart-manager-for-wp-e-commerce' ),
				'maximum_allowed_quantity'           => __( 'Maximum quantity', 'smart-manager-for-wp-e-commerce' ),
				'group_of_quantity'                  => __( 'Group of...', 'smart-manager-for-wp-e-commerce' ),
				'variation_minimum_allowed_quantity' => __( 'Variation Minimum quantity', 'smart-manager-for-wp-e-commerce' ),
				'variation_maximum_allowed_quantity' => __( 'Variation Maximum quantity', 'smart-manager-for-wp-e-commerce' ),
				'variation_group_of_quantity'        => __( 'Variation Group of...', 'smart-manager-for-wp-e-commerce' ),
				// phpcs:ignore https://wordpress.org/plugins/minmax-quantity-for-woocommerce/
				'min_quantity'                       => __( 'Minimum Quantity', 'smart-manager-for-wp-e-commerce' ),
				'max_quantity'                       => __( 'Maximum Quantity', 'smart-manager-for-wp-e-commerce' ),
				'min_quantity_var'                   => __( 'Variation Minimum Quantity', 'smart-manager-for-wp-e-commerce' ),
				'max_quantity_var'                   => __( 'Variation Maximum Quantity', 'smart-manager-for-wp-e-commerce' ),
				// phpcs:ignore https://wordpress.org/plugins/woo-min-max-quantity-limit/
				'_wc_mmax_min'                       => __( 'Min Quantity', 'smart-manager-for-wp-e-commerce' ),
				'_wc_mmax_max'                       => __( 'Max Quantity', 'smart-manager-for-wp-e-commerce' ),
				// phpcs:ignore [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
				'_subscription_price'                => __( 'Subscription Price', 'smart-manager-for-wp-e-commerce' ),
				'_subscription_sign_up_fee'          => __( 'Sign-up Fee', 'smart-manager-for-wp-e-commerce' ),
				'_subscription_trial_length'         => __( 'Free Trial', 'smart-manager-for-wp-e-commerce' ),
				// phpcs:ignore [WooCommerce Cost Of Goods](https://woocommerce.com/products/woocommerce-cost-of-goods/)
				'_wc_cog_cost'                       => __( 'Cost of Good', 'smart-manager-for-wp-e-commerce' ),
				// phpcs:ignore [Germanized for WooCommerce](https://wordpress.org/plugins/woocommerce-germanized/)
				'_unit_product'                      => __( 'Product Units', 'smart-manager-for-wp-e-commerce' ),
				'_unit_base'                         => __( 'Unit Price Units', 'smart-manager-for-wp-e-commerce' ),
				'_unit_price_regular'                => __( 'Regular Unit Price', 'smart-manager-for-wp-e-commerce' ),
				'_unit_price_sale'                   => __( 'Sale Unit Price', 'smart-manager-for-wp-e-commerce' ),
			);

			$numeric_text_editor_columns = array(
				'_wc_booking_duration',
				'_wc_booking_min_duration',
				'_wc_booking_max_duration',
				'_wc_booking_cancel_limit',
				'_wc_booking_min_date',
				'_wc_booking_max_date',
			);

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
				// phpcs:ignore https://wordpress.org/plugins/woo-min-max-quantity-limit/
				'_wc_mmax_prd_opt_enable' => __( 'Enable Min Max Quantity', 'smart-manager-for-wp-e-commerce' ),
			);

			$checkbox_yes_no_columns = array(
				// phpcs:ignore https://woocommerce.com/products/minmax-quantities/
				'min_max_rules'                    => __( 'Min/Max Rules', 'smart-manager-for-wp-e-commerce' ),
				'allow_combination'                => __( 'Allow Combination', 'smart-manager-for-wp-e-commerce' ),
				'minmax_do_not_count'              => __( 'Order rules: Do not count', 'smart-manager-for-wp-e-commerce' ),
				'minmax_cart_exclude'              => __( 'Order rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				'minmax_category_group_of_exclude' => __( 'Category group-of rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				'variation_minmax_do_not_count'    => __( 'Variation Order rules: Do not count', 'smart-manager-for-wp-e-commerce' ),
				'variation_minmax_cart_exclude'    => __( 'Variation Order rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				'variation_minmax_category_group_of_exclude' => __( 'Variation Category group-of rules: Exclude', 'smart-manager-for-wp-e-commerce' ),
				// phpcs:ignore [Germanized for WooCommerce](https://wordpress.org/plugins/woocommerce-germanized/)
				'_unit_price_auto'                 => __( 'Calculate unit prices automatically', 'smart-manager-for-wp-e-commerce' ),
			);

			$booking_duration_unit = array(
				'month'  => __( 'Month(s)', 'smart-manager-for-wp-e-commerce' ),
				'day'    => __( 'Day(s)', 'smart-manager-for-wp-e-commerce' ),
				'hour'   => __( 'Hour(s)', 'smart-manager-for-wp-e-commerce' ),
				'minute' => __( 'Minutes(s)', 'smart-manager-for-wp-e-commerce' ),
			);

			$column_model = &$dashboard_model['columns'];

			foreach ( $column_model as $key => &$column ) {
				if ( empty( $column['src'] ) ) {
					continue;
				}

				$src_exploded = explode( '/', $column['src'] );

				if ( count( $src_exploded ) < 2 ) {
					$col_nm = $column['src'];
				}

				if ( count( $src_exploded ) > 2 ) {
					$col_table = $src_exploded[0];
					$cond      = explode( '=', $src_exploded[1] );

					if ( count( $cond ) === 2 ) {
						$col_nm = $cond[1];
					}
				} else {
					$col_nm    = $src_exploded[1];
					$col_table = $src_exploded[0];
				}

				if ( empty( $col_nm ) ) {
					continue;
				}
				switch ( $col_nm ) {
					case '_wc_booking_duration_type':
						$column['key']         = __( 'Booking Duration (Type)', 'smart-manager-for-wp-e-commerce' );
						$column['name']        = $column['key'];
						$booking_duration_type = array(
							'fixed'    => __( 'Fixed blocks of', 'smart-manager-for-wp-e-commerce' ),
							'customer' => __( 'Customer defined blocks of', 'smart-manager-for-wp-e-commerce' ),
						);

						$column = $this->generate_dropdown_col_model( $column, $booking_duration_type );
						break;
					case '_wc_booking_duration_unit':
						$column['key']  = __( 'Booking Duration (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column         = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_cancel_limit_unit':
						$column['key']  = __( 'Booking can be cancelled until (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column         = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_min_date_unit':
						$column['key']  = __( 'Minimum block bookable (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column         = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_max_date_unit':
						$column['key']  = __( 'Maximum block bookable (Unit)', 'smart-manager-for-wp-e-commerce' );
						$column['name'] = $column['key'];
						$column         = $this->generate_dropdown_col_model( $column, $booking_duration_unit );
						break;
					case '_wc_booking_calendar_display_mode':
						$column['key']                 = __( 'Calendar display mode', 'smart-manager-for-wp-e-commerce' );
						$column['name']                = $column['key'];
						$booking_calendar_display_mode = array(
							''               => __( 'Display calendar on click', 'smart-manager-for-wp-e-commerce' ),
							'always_visible' => __( 'Calendar always visible', 'smart-manager-for-wp-e-commerce' ),
						);
						$column                        = $this->generate_dropdown_col_model( $column, $booking_calendar_display_mode );
						break;
					case '_wc_booking_resources_assignment':
						$column['key']                = __( 'Resources are...', 'smart-manager-for-wp-e-commerce' );
						$column['name']               = $column['key'];
						$booking_resources_assignment = array(
							'customer'  => __( 'Customer selected', 'smart-manager-for-wp-e-commerce' ),
							'automatic' => __( 'Automatically assigned', 'smart-manager-for-wp-e-commerce' ),
						);
						$column                       = $this->generate_dropdown_col_model( $column, $booking_resources_assignment );
						break;
					case '_wc_booking_default_date_availability':
						$column['key']                       = __( 'All dates are...', 'smart-manager-for-wp-e-commerce' );
						$column['name']                      = $column['key'];
						$booking_resources_date_availability = array(
							'available'     => __( 'available by default', 'smart-manager-for-wp-e-commerce' ),
							'non-available' => __( 'not-available by default', 'smart-manager-for-wp-e-commerce' ),
						);
						$column                              = $this->generate_dropdown_col_model( $column, $booking_resources_date_availability );
						break;
					case '_wc_booking_check_availability_against':
						$column['key']                       = __( 'Check rules against...', 'smart-manager-for-wp-e-commerce' );
						$column['name']                      = $column['key'];
						$booking_resources_date_availability = array(
							''      => __( 'All blocks being booked', 'smart-manager-for-wp-e-commerce' ),
							'start' => __( 'The starting block only', 'smart-manager-for-wp-e-commerce' ),
						);
						$column                              = $this->generate_dropdown_col_model( $column, $booking_resources_date_availability );
						break;
					case '_product_addons':
						$path = $this->plugin_dir_path . '/pro/assets/js/json-schema/product-addons.json';
						if ( ( file_exists( $path ) ) && ( is_readable( $path ) ) ) {
							$column['editor_schema'] = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						}
						break;
					case '_subscription_period_interval':
						$column['key']                = __( 'Subscription Periods', 'smart-manager-for-wp-e-commerce' );
						$column['name']               = $column['key'];
						$subscription_period_interval = ( function_exists( 'wcs_get_subscription_period_interval_strings' ) ) ? wcs_get_subscription_period_interval_strings() : array();
						$column                       = $this->generate_dropdown_col_model( $column, $subscription_period_interval );
						break;
					case '_subscription_period':
						$column['name']      = __( 'Billing Period', 'smart-manager-for-wp-e-commerce' );
						$column['key']       = $column['name'];
						$subscription_period = ( function_exists( 'wcs_get_subscription_period_strings' ) ) ? wcs_get_subscription_period_strings() : array();
						$column              = $this->generate_dropdown_col_model( $column, $subscription_period );
						break;
					case '_subscription_length':
						$column['key']           = __( 'Expire After', 'smart-manager-for-wp-e-commerce' );
						$column['name']          = $column['key'];
						$wcs_subscription_ranges = ( function_exists( 'wcs_get_subscription_ranges' ) ) ? wcs_get_subscription_ranges() : array();
						$subscription_ranges     = array( __( 'Never expire', 'smart-manager-for-wp-e-commerce' ) );
						if ( ! empty( $wcs_subscription_ranges['day'] ) ) {
							foreach ( $wcs_subscription_ranges['day'] as $key => $values ) {
								if ( $key > 0 ) {
									$subscription_ranges[ $key ] = $key . ' Renewals';
								}
							}
						}
						$column = $this->generate_dropdown_col_model( $column, $subscription_ranges );
						break;
					case '_subscription_trial_period':
						$column['key']             = __( 'Subscription Trial Period', 'smart-manager-for-wp-e-commerce' );
						$column['name']            = $column['key'];
						$subscription_time_periods = ( function_exists( 'wcs_get_available_time_periods' ) ) ? wcs_get_available_time_periods() : array();
						$column                    = $this->generate_dropdown_col_model( $column, $subscription_time_periods );
						break;
					case ( ! empty( $numeric_columns[ $col_nm ] ) ):
						$column['key']    = $numeric_columns[ $col_nm ];
						$column['name']   = $column['key'];
						$column['type']   = 'numeric';
						$column['editor'] = ( in_array( $col_nm, $numeric_text_editor_columns, true ) ) ? $column['type'] : 'customNumericEditor';
						$column['min']    = 0;
						$column['width']  = 50;
						$column['align']  = 'right';
						break;
					case ( ! empty( $checkbox_empty_one_columns[ $col_nm ] ) ):
						$column['key']               = $checkbox_empty_one_columns[ $col_nm ];
						$column['name']              = $column['key'];
						$column['type']              = 'checkbox';
						$column['editor']            = $column['type'];
						$column['checkedTemplate']   = 1;
						$column['uncheckedTemplate'] = '';
						$column['width']             = 30;
						break;
					case ( ! empty( $checkbox_zero_one_columns[ $col_nm ] ) ):
						$column['key']               = $checkbox_zero_one_columns[ $col_nm ];
						$column['name']              = $column['key'];
						$column['type']              = 'checkbox';
						$column['editor']            = $column['type'];
						$column['checkedTemplate']   = 1;
						$column['uncheckedTemplate'] = 0;
						$column['width']             = 30;
						break;
					case ( ! empty( $checkbox_yes_no_columns[ $col_nm ] ) ):
						$column['key']               = $checkbox_yes_no_columns[ $col_nm ];
						$column['name']              = $column['key'];
						$column['type']              = 'checkbox';
						$column['editor']            = $column['type'];
						$column['checkedTemplate']   = 'yes';
						$column['uncheckedTemplate'] = 'no';
						$column['width']             = 30;
						break;
					// phpcs:ignore [Germanized for WooCommerce](https://wordpress.org/plugins/woocommerce-germanized/)
					case 'product_unit':
						$column['type']          = 'dropdown';
						$column['renderer']      = 'selectValueRenderer';
						$column['editable']      = false;
						$column['editor']        = 'select';
						$column['strict']        = true;
						$column['allowInvalid']  = false;
						$column['selectOptions'] = $column['values'];
						break;
					case '_unit':
						$column['type']         = 'dropdown';
						$column['renderer']     = 'selectValueRenderer';
						$column['editable']     = false;
						$column['editor']       = 'select';
						$column['strict']       = true;
						$column['allowInvalid'] = false;

						$column['selectOptions'] = array();
						$column['values']        = $column['selectOptions'];
						$column['search_values'] = array();
						if ( function_exists( 'WC_Germanized' ) ) {
							$wc_germanized = WC_Germanized();
							if ( is_callable( array( $wc_germanized, 'plugin_path' ) ) ) {
								$column['selectOptions'] = ( file_exists( $wc_germanized->plugin_path() . '/i18n/units.php' ) ) ? include $wc_germanized->plugin_path() . '/i18n/units.php' : array();
								$column['values']        = $column['selectOptions'];
								if ( ! empty( $column['values'] ) ) {
									foreach ( $column['values'] as $key => $value ) {
										$column['search_values'][] = array(
											'key'   => $key,
											'value' => $value,
										);
									}
								}
							}
						}
						break;
				}
			}

			if ( ! empty( $dashboard_model_saved ) ) {
				$col_model_diff = sa_array_recursive_diff( $dashboard_model_saved, $dashboard_model );
			}

			// clearing the transients before return.
			if ( ! empty( $col_model_diff ) ) {
				delete_transient( 'sa_' . $this->plugin_sku . '_' . $this->dashboard_key );
			}

			return $dashboard_model;
		}

		/**
		 * Handles post batch updates for products
		 *
		 * @param boolean $update_flag update flag for product post batch update.
		 * @param array   $args array of selected field, operator and value.
		 * @return boolean true if updated successfully else false
		 */
		public static function products_post_batch_update_db_updates( $update_flag = false, $args = array() ) {
			// code for handling updation of price & sales pice in woocommerce.
			$price_columns = array( '_regular_price', '_sale_price', '_sale_price_dates_from', '_sale_price_dates_to' );
			if ( ! empty( $args['table_nm'] ) && ( 'postmeta' === $args['table_nm'] ) && ( ( ! empty( $args['col_nm'] ) ) && ( true === in_array( $args['col_nm'], $price_columns, true ) ) ) ) {
				switch ( $args['col_nm'] ) {
					case '_sale_price_dates_from':
						update_post_meta( $args['id'], '_sale_price_dates_from', sa_get_utc_timestamp_from_site_date( $args['value'] . ' 00:00:00' ) );
						break;
					case '_sale_price_dates_to':
						update_post_meta( $args['id'], '_sale_price_dates_to', sa_get_utc_timestamp_from_site_date( $args['value'] . ' 23:59:59' ) );
						break;
					// Code to handle setting of 'regular_price' & 'sale_price' in proper way.
					case ( empty( $args['operator'] ) || ( ! empty( $args['operator'] ) && ! in_array( $args['operator'], array( 'set_to_regular_price', 'set_to_sale_price', 'copy_from_field' ), true ) ) ):
						$regular_price = ( '_regular_price' === $args['col_nm'] ) ? $args['value'] : get_post_meta( $args['id'], '_regular_price', true );
						$sale_price    = ( '_sale_price' === $args['col_nm'] ) ? $args['value'] : get_post_meta( $args['id'], '_sale_price', true );
						if ( $sale_price >= $regular_price ) {
							update_post_meta( $args['id'], '_sale_price', '' );
						}
						break;
				}
				sa_update_price_meta( array( $args['id'] ) );
				// Code For updating the parent price of the product.
				sa_variable_parent_sync_price( array( $args['id'] ) );
				$update_flag = true;
			}

			if ( ! empty( $args['table_nm'] ) && 'postmeta' === $args['table_nm'] && ( ! empty( $args['col_nm'] ) && in_array( $args['col_nm'], array( '_stock', '_backorders' ), true ) ) ) { // For handling product inventory updates.
				$update_flag = sa_update_stock_status( $args['id'], $args['col_nm'], $args['value'] );
			}

			// Code for 'WooCommerce Product Stock Alert' plugin compat -- triggering `save_post` action.
			if ( ! empty( $args['table_nm'] ) && 'postmeta' === $args['table_nm'] && ( ! empty( $args['col_nm'] ) && ( '_stock' === $args['col_nm'] || '_manage_stock' === $args['col_nm'] ) ) ) {
				sa_update_post( $args['id'] );
			}

			// code to sync the variations title if the variation parent title has been updated.
			if ( ( ! empty( $args['table_nm'] ) && 'posts' === $args['table_nm'] ) && ( ! empty( $args['col_nm'] ) && 'post_title' === $args['col_nm'] ) ) {

				$new_title = ( ! empty( $args['value'] ) ) ? $args['value'] : '';

				if ( ! empty( self::$instance->variation_product_old_title[ $args['id'] ] ) && $new_title !== self::$instance->variation_product_old_title[ $args['id'] ] ) {
					$new_title_update_case = 'WHEN post_parent=' . $args['id'] . ' THEN REPLACE(post_title, \'' . self::$instance->variation_product_old_title[ $args['id'] ] . '\', \'' . $new_title . '\')';
					sa_sync_variation_title( array( $new_title_update_case ), array( $args['id'] ) );
				}
			}

			if ( ( ! empty( $args['table_nm'] ) && 'terms' === $args['table_nm'] ) && ( ! empty( $args['col_nm'] ) && 'product_visibility' === $args['col_nm'] ) ) {
				$val         = ( ! empty( $args['value'] ) ) ? $args['value'] : '';
				$update_flag = self::$instance->set_product_visibility( $args['id'], $val );
			}

			if ( ( ! empty( $args['table_nm'] ) && 'terms' === $args['table_nm'] ) && ( ! empty( $args['col_nm'] ) && 'product_visibility_featured' === $args['col_nm'] ) ) {
				$val         = ( ! empty( $args['value'] ) ) ? $args['value'] : '';
				$update_flag = ( 'Yes' === $val || 'yes' === $val ) ? wp_set_object_terms( $args['id'], 'featured', 'product_visibility', true ) : wp_remove_object_terms( $args['id'], 'featured', 'product_visibility' );
			}

			// Code for updating product attributes.
			if ( ( ! empty( $args['table_nm'] ) && 'custom' === $args['table_nm'] ) && ( ( ! empty( $args['col_nm'] ) && in_array( $args['col_nm'], array( 'product_attributes', 'product_attributes_add', 'product_attributes_remove' ), true ) ) || ( ! empty( $args['operator'] ) && in_array( $args['operator'], array( 'add_to', 'remove_from', 'copy_from' ), true ) ) ) && ( ! empty( $args['meta']['attributeName'] ) ) ) {
				$action           = $args['meta']['attributeName'];
				$current_term_ids = array();
				delete_transient( 'wc_layered_nav_counts_' . $action );
				$product_attributes = get_post_meta( $args['id'], '_product_attributes', true );

				if ( empty( $product_attributes ) || ! is_array( $product_attributes ) ) {
					$product_attributes = array();
				}

				$all_terms_ids = array();

				if ( 'custom' !== $action ) {
					$current_term_ids = wp_get_object_terms( $args['id'], $action, 'orderby=none&fields=ids' );
					$current_term_ids = ( ! is_wp_error( $current_term_ids ) ) ? $current_term_ids : array();
					if ( ! empty( $args['value'] ) && 'all' === $args['value'] ) { // creating array of all values for the attribute.
						$taxonomy_terms = get_terms(
							$action,
							array(
								'hide_empty' => 0,
								'orderby'    => 'id',
							)
						);

						if ( ! empty( $taxonomy_terms ) ) {
							foreach ( $taxonomy_terms as $term_obj ) {
								$all_terms_ids[] = $term_obj->term_id;
							}
						}
					}
				}

				if ( 'add_to' === $args['operator'] ) {

					if ( 'custom' !== $action ) {

						if ( ( is_array( $current_term_ids ) ) && ! in_array( $args['value'], $current_term_ids, true ) ) {

							if ( 'all' !== $args['value'] ) {
								$current_term_ids[] = intval( $args['value'] );
							} else {
								$current_term_ids = $all_terms_ids;
							}

							$update_flag = wp_set_object_terms( $args['id'], $current_term_ids, $action );
						}

						if ( empty( $product_attributes[ $action ] ) ) {
							$product_attributes[ $action ] = array(
								'name'         => $action,
								'value'        => '',
								'position'     => 1,
								'is_visible'   => 1,
								'is_variation' => 0,
								'is_taxonomy'  => 1,
							);
						}
					} elseif ( 'custom' === $action ) {

						$value = ( ( ! empty( $args['meta']['attribute_values'] ) ) ? $args['meta']['attribute_values'] : '' );

						if ( ! empty( $product_attributes[ $args['value'] ] ) ) {
							$product_attributes[ $args['value'] ]['value'] = $value;
						} else {
							$product_attributes[ $args['value'] ] = array(
								'name'         => $args['value'],
								'value'        => $value,
								'position'     => 1,
								'is_visible'   => 1,
								'is_variation' => 0,
								'is_taxonomy'  => 0,
							);
						}

						$update_flag = true;

					}
				} elseif ( 'remove_from' === $args['operator'] ) {
					if ( 'custom' !== $action ) {

						$all = ( ! empty( $args['value'] ) && 'all' === $args['value'] ) ? true : false;

						$key = array_search( (int) $args['value'], $current_term_ids, true );

						if ( false !== $key ) {
							unset( $current_term_ids[ $key ] );
							$update_flag = wp_set_object_terms( $args['id'], $current_term_ids, $action );
						} elseif ( true === $all ) {
							$update_flag = wp_set_object_terms( $args['id'], array(), $action );
						}

						if ( ( 0 === count( $current_term_ids ) || true === $all ) && ! empty( $product_attributes[ $action ] ) ) {
							unset( $product_attributes[ $action ] );
						}
					}
				} elseif ( 'copy_from' === $args['operator'] && ( ! empty( $args['selected_value'] ) ) && ( ! empty( $args['id'] ) ) ) {
					if ( ! empty( $args['value'] ) && is_array( $args['value'] ) ) {
						foreach ( $args['value'] as $action => $value ) {
							wp_set_object_terms( $args['id'], array(), $action );
							$current_term_ids = wp_get_object_terms( $args['selected_value'], $action, 'orderby=none&fields=ids' );
							$update_flag      = wp_set_object_terms( $args['id'], $current_term_ids, $action );
							if ( empty( $product_attributes[ $action ] ) ) {
								$product_attributes[ $action ] = array(
									'name'         => $action,
									'value'        => '',
									'position'     => 1,
									'is_visible'   => 1,
									'is_variation' => 0,
									'is_taxonomy'  => 1,
								);
							}
						}
					}
				}

				update_post_meta( $args['id'], '_product_attributes', $product_attributes );
				sa_update_product_attribute_lookup_table( array( $args['id'] ) );
			}

			// Code for updating product categories.
			if ( ! empty( $args['table_nm'] ) && 'custom' === $args['table_nm'] && ( ! empty( $args['col_nm'] ) && false !== strpos( $args['col_nm'], 'product_cat' ) ) ) {

				$action           = ( ! empty( $args['operator'] ) ) ? $args['operator'] : '';
				$value            = ( ! empty( $args['value'] ) ) ? intval( $args['value'] ) : 0;
				$taxonomy_nm      = 'product_cat';
				$current_term_ids = array();

				if ( ! empty( $action ) && 'set_to' !== $action ) {
					$current_term_ids = wp_get_object_terms( $args['id'], $taxonomy_nm, 'orderby=none&fields=ids' );

					if ( 'add_to' === $action ) {
						$current_term_ids[] = $value;
					} elseif ( 'remove_from' === $action ) {
						$key = array_search( (int) $value, $current_term_ids, true );
						if ( false !== $key ) {
							unset( $current_term_ids[ $key ] );
						}
					}
				} elseif ( ! empty( $action ) && 'set_to' === $action ) {
					$current_term_ids = array( $value );
				}

				$update_flag = wp_set_object_terms( $args['id'], $current_term_ids, $taxonomy_nm );

			}

			// product clear_caches.
			clean_post_cache( $args['id'] );
			wc_delete_product_transients( $args['id'] );
			if ( class_exists( 'WC_Cache_Helper' ) ) {
				( is_callable( array( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) ) ? WC_Cache_Helper::invalidate_cache_group( 'product_' . $args['id'] ) : WC_Cache_Helper::incr_cache_prefix( 'product_' . $args['id'] );
			}

			do_action( 'woocommerce_update_product', $args['id'], wc_get_product( $args['id'] ) );

			return ( ( ! empty( $update_flag ) ) && ( ! is_wp_error( $update_flag ) ) ) ? true : false;

		}

		/**
		 * Update value for copy from operator
		 *
		 * @param array $args array of selected field, operator and value.
		 * @return boolean
		 */
		public static function update_value_for_copy_from_operator( $args = array() ) {
			if ( empty( $args['id'] ) || ( 'product_attributes' !== $args['col_nm'] ) || ( ! isset( $args['value'] ) ) ) {
				return false;
			}
			return update_post_meta( $args['id'], '_product_attributes', $args['value'] );
		}

		/**
		 * Handles pre-batch updates for products
		 *
		 * @param array $args array of selected field, operator and value.
		 * @return void
		 */
		public static function products_pre_batch_update_db_updates( $args = array() ) {
			if ( empty( $args['id'] ) || empty( $args['table_nm'] ) || ( ! empty( $args['table_nm'] ) && 'posts' !== $args['table_nm'] ) || empty( $args['col_nm'] ) || ( ! empty( $args['col_nm'] ) && 'post_title' !== $args['col_nm'] ) ) {
				return;
			}
			$results = sa_get_current_variation_title( array( $args['id'] ) );
			if ( empty( $results ) || ( ! is_array( $results ) ) || count( $results ) > 0 ) {
				return;
			}
			foreach ( $results as $result ) {
				self::$instance->variation_product_old_title[ $result['id'] ] = $result['post_title'];
			}
		}

		/**
		 * Updates args value post batch process
		 *
		 * @param array $args array of selected field, operator and value.
		 * @return array $args updated args array
		 */
		public static function products_post_batch_process_args( $args = array() ) {
			if ( ! empty( $args['operator'] ) && ( in_array( $args['operator'], array( 'set_to_regular_price', 'set_to_sale_price', 'set_to_regular_price_and_decrease_by_per', 'set_to_regular_price_and_decrease_by_num' ), true ) ) ) {
				$price_operators = array(
					'set_to_regular_price',
					'set_to_sale_price',
					'set_to_regular_price_and_decrease_by_per',
					'set_to_regular_price_and_decrease_by_num',
				);
				if ( in_array( $args['operator'], $price_operators, true ) ) {
					// Determine the selected field for fetching previous value.
					$col_nm = ( 'set_to_sale_price' === $args['operator'] ) ? '_sale_price' : '_regular_price';
					// Fetch the previous value.
					$prev_val = get_post_meta( $args['id'], $col_nm, true );
					if ( in_array(
						$args['operator'],
						array(
							'set_to_regular_price',
							'set_to_sale_price',
						),
						true
					) ) {
						$args['value'] = $prev_val;
					}
					// Modify value only for decrease operations.
					$args['value'] = ( 'set_to_regular_price_and_decrease_by_per' === $args['operator'] ) ? self::decrease_value_by_per( $prev_val, $args['value'] ) : ( ( 'set_to_regular_price_and_decrease_by_num' === $args['operator'] ) ? self::decrease_value_by_num( $prev_val, $args['value'] ) : $args['value'] );
				}
			}
			return $args;
		}

		/**
		 * Update certain columns before doing batch update
		 *
		 * @param array $args array of update related data.
		 * @return void
		 */
		public static function products_pre_batch_update( $args = array() ) {
			if ( empty( $args['type'] ) || ( ! empty( $args['type'] ) && ( 'postmeta/meta_key=_sale_price/meta_value=_sale_price' !== $args['type'] ) ) || ( ! in_array( $args['operator'], array( 'increase_by_per', 'decrease_by_per', 'increase_by_num', 'decrease_by_num' ), true ) ) || empty( $args['id'] ) ) {
				return;
			}
			$regular_price = get_post_meta( $args['id'], '_regular_price', true );
			if ( ! empty( $regular_price ) && empty( get_post_meta( $args['id'], '_sale_price', true ) ) ) {
				update_post_meta( $args['id'], '_sale_price', $regular_price );
			}
		}

		/**
		 * Function to get product manufacturer terms.
		 * compat for 'Germanized for WooCommerce Pro' plugin.
		 *
		 * @param object $post updated post object.
		 * @param object $post_before post object before update.
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
		 * Deletes metadata for the given taxonomy terms, replication of wc_clear_term_product_ids.
		 *
		 * @param array $taxonomy_term_ids Array of taxonomy term IDs to process.
		 */
		public static function post_process_terms_update( $taxonomy_term_ids = array() ) {
			if ( ( empty( $taxonomy_term_ids ) ) || ( ! is_array( $taxonomy_term_ids ) ) || ( ! class_exists( 'SA_Manager_Pro_Base' ) ) || ( ! is_callable( array( 'SA_Manager_Pro_Base', 'delete_metadata' ) ) ) ) {
				return;
			}
			$terms_meta_data = array_map(
				function ( $tt_id ) {
					return array(
						'object_id' => $tt_id,
						'meta_key'  => 'product_ids', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					);
				},
				$taxonomy_term_ids
			);
			SA_Manager_Pro_Base::delete_metadata(
				array(
					'meta_type' => 'term',
					'meta_data' => $terms_meta_data,
				)
			);
		}

		/**
		 * Function to get product manufacturer terms.
		 * compat for 'Germanized for WooCommerce Pro' plugin.
		 *
		 * @param array $taxonomies Array of taxonomies to be updated.
		 *
		 * @return array Array of product_manufacturer taxonomy terms.
		 */
		public static function get_product_manufacturer_terms( $taxonomies = array() ) {
			$product_manufacturer_terms = array();
			if ( ( empty( $taxonomies ) ) || ( ! in_array( 'product_manufacturer', $taxonomies, true ) ) ) {
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
				$term                                        = SA_Manager_Pro_Base::get_term_by_id( $args['taxonomy_terms'], $term_id );
				$term_slug                                   = ( ( ! empty( $term ) ) && ( ! empty( $term->slug ) ) ) ? $term->slug : '';
				$postarr['meta_input']['_manufacturer_slug'] = $term_slug;
			}
			return $postarr;
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
			if ( empty( $terms ) || ! is_array( $terms ) || ! is_object( $taxonomy ) || empty( $taxonomy->name ) ) {
				return;
			}
			global $wpdb;
			// Standard callback.
			if ( $callback ) {
				if ( ( ! class_exists( 'SA_Manager_Pro_Base' ) ) || ( ! is_callable( array( 'SA_Manager_Pro_Base', 'update_post_term_count' ) ) ) ) {
					return;
				}
				SA_Manager_Pro_Base::update_post_term_count( $terms, $taxonomy );
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
					$terms[] = ( ! empty( $term ) ) ? $term->term_id : 0;
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
				$wpdb1 = $wpdb;
				$count = $wpdb1->get_var( implode( ' ', $term_query ) );

				// Update the count.
				update_term_meta( $term_id, 'product_count_' . $taxonomy->name, absint( $count ) );
			}

			delete_transient( 'wc_term_counts' );
		}

		/**
		 * Sets the visibility of a product.
		 *
		 * This method delegates the visibility setting to the common core product object,
		 * if it exists and implements the 'set_product_visibility' method.
		 *
		 * @param int    $id         The ID of the product to update.
		 * @param string $visibility The desired visibility status (e.g., 'visible', 'catalog', 'search', 'hidden').
		 *
		 * @return bool True on success, false on failure or if the method is not available.
		 */
		public function set_product_visibility( $id = 0, $visibility = '' ) {
			if ( isset( $this->common_core_product ) && method_exists( $this->common_core_product, 'set_product_visibility' ) ) {
				return $this->common_core_product->set_product_visibility( $id, $visibility );
			}
			return false;
		}
	}
}
