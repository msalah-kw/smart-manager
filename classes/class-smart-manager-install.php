<?php

defined( 'ABSPATH' ) || exit;

class Smart_Manager_Install {

	/**
	 * DB updates and callbacks that need to be run per version.
	 *
	 * @var array
	 */
	private static $db_updates = array(
		'5.0.0' => array(
			'create_table_for_custom_views',
			'create_predefined_custom_views',
			'update_500_model_transients'
		),
		'5.0.1' => array(
			'update_500_model_transients'
		),
		'5.16.0' => array(
			'update_516_alter_table'
		),
		'8.0.0' => array(
			'create_tables_for_tasks'
		),
		'8.9.0' => array(
			'update_890_port_settings'
		),
		'8.18.0' => array(
			'update_8180_create_tasks_tables_for_lite_version'
		),
		'8.31.0' => array(
			'update_8310_port_access_privilege_settings'
		),
		'8.56.0' => array(
			'update_856_alter_table'
		),
		'8.58.0' => array(
			'create_predefined_custom_views'
		),
		'8.59.0' => array(
			'create_predefined_custom_views'
		),
		'8.60.0' => array(
			'create_predefined_custom_views'
		),
		'8.63.0' => array(
			'create_predefined_custom_views',
			'rename_attachment_columns'
		),
		'8.69.0' => array(
			'update_869_alter_table'
		)
	);

	/**
	 * Hook in tabs.
	 */
	public static function init() {
		if( !defined('DOING_AJAX') || true !== DOING_AJAX ){
			add_action( 'init', array( __CLASS__, 'maybe_update_db_version' ) );
		}
	}

    /**
	 * Install SM.
	 */
	public static function install() {
        if ( ! is_blog_installed() ) {
			return;
		}

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'sa_sm_installing' ) ) {
			return;
        }
        
        // If we made it till here nothing is running yet, lets set the transient now.
        set_transient( 'sa_sm_installing', 'yes', MINUTE_IN_SECONDS * 10 );
		self::create_tables();
		self::maybe_update_db_version();
		delete_transient( 'sa_sm_installing' );

		// Redirect to welcome screen
		if ( ! is_network_admin() && ! isset( $_GET['activate-multi'] ) ) {
			set_transient( '_sm_activation_redirect', 1, 30 );
		}
	}

	/**
	 * Actions on deactivate plugin.
	 */
	public static function deactivate() {
		global $wpdb;

		$table_name = $wpdb->prefix.'sm_advanced_search_temp';
		if ( $table_name === $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		}
		
		$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_sa_sm_%' OR option_name LIKE '_transient_timeout_sa_sm_%'"); //for deleting post type transients
	}

	/**
	 * Get list of DB update callbacks.
	 *
	 * @return array
	 */
	public static function get_db_update_callbacks() {
		return self::$db_updates;
	}
	
	/**
	 * Is a DB update needed?
	 *
	 * @return boolean
	 */
	public static function needs_db_update() {
		$current_db_version = get_option( 'sa_sm_db_version', null );
		$updates            = self::get_db_update_callbacks();
		$update_versions    = array_keys( $updates );
		usort( $update_versions, 'version_compare' );
		return ( ( ! is_null( $current_db_version ) && version_compare( $current_db_version, end( $update_versions ), '<' ) ) || is_null( $current_db_version ) );
	}

	/**
	 * See if we need to show or run database updates during install.
	 *
	 */
	static function maybe_update_db_version() {
		if ( self::needs_db_update() ) {
			self::update();
		}
	}

	/**
	 * Update DB version to current.
	 *
	 * @param string|null $version New Smart Manager DB version or null.
	 */
	public static function update_db_version( $version = null ) {
		if( ! empty( $version ) ) {
			update_option( 'sa_sm_db_version', $version, 'no' );
		}
	}

	/**
	 * Process all DB updates.
	 */
	private static function update() {

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'sa_sm_updating' ) ) {
			return;
		}
		
		// If we made it till here nothing is running yet, lets set the transient now.
        set_transient( 'sa_sm_updating', 'yes', MINUTE_IN_SECONDS * 10 );

		$current_db_version = get_option( 'sa_sm_db_version' );
		
		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					if ( is_callable( array( __CLASS__, $update_callback ) ) ) {
						call_user_func( array( __CLASS__, $update_callback ) );
					}
				}
				self::update_db_version($version);
			}
		}

		delete_transient( 'sa_sm_updating' );

	}

	public static function update_500_model_transients(){
		global $wpdb;

		$wpdb->query(
					$wpdb->prepare(
							"UPDATE {$wpdb->prefix}options
							SET option_name = REPLACE(option_name, %s, %s)
							WHERE option_name LIKE %s",	
							'_transient_sm_beta_',
							'_transient_sa_sm_',
							$wpdb->esc_like('_transient_sm_beta_') . '%'
						)
					);

		$wpdb->query(
					$wpdb->prepare(
							"UPDATE {$wpdb->prefix}options
							SET option_name = REPLACE(option_name, %s, %s)
							WHERE option_name LIKE %s",	
							'_transient_timeout_sm_beta_',
							'_transient_timeout_sa_sm_',
							$wpdb->esc_like('_transient_timeout_sm_beta_') . '%'
						)
					);
	}

	public static function create_table_for_custom_views() {
		global $wpdb;

		if( !( defined('SMPRO') && true === SMPRO ) ) {
			return array();
		}

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sm_views` (
					`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
					`author` bigint UNSIGNED NOT NULL,
					`title` varchar(255) NOT NULL,
					`slug` varchar(255) NOT NULL,
					`params` longtext NOT NULL,
					`is_public` bit(1) NOT NULL DEFAULT b'0',
					`post_type` varchar(20) NOT NULL,
					`created_date` int UNSIGNED NOT NULL,
					`modified_date` int UNSIGNED NOT NULL,
					PRIMARY KEY (`id`)
					) $collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		return dbDelta( $tables );
	}

	public static function create_tables(){
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sm_advanced_search_temp` (
						`product_id` bigint(20) unsigned NOT NULL UNIQUE default '0',
						`flag` bigint(20) unsigned NOT NULL default '0',
						`cat_flag` bigint(20) unsigned NOT NULL default '0',
						PRIMARY KEY (`product_id`)
						) $collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $tables );

		// Added code in v5.5.0 as in some cases the sm_views table was not created
		if ( is_callable( array( __CLASS__, 'create_table_for_custom_views' ) ) ) {
			call_user_func( array( __CLASS__, 'create_table_for_custom_views' ) );
		}
		if ( is_callable( array( __CLASS__, 'create_tables_for_tasks' ) ) ) {
			call_user_func( array( __CLASS__, 'create_tables_for_tasks' ) );
		}
	}

	public static function update_516_alter_table(){
		global $wpdb;
		
		$table_name = $wpdb->prefix.'sm_advanced_search_temp';
		if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
			if ( ! $wpdb->get_var( "SHOW KEYS FROM $table_name WHERE Key_name = 'PRIMARY' AND Column_name = 'product_id'" ) ) {
				$wpdb->query( "ALTER TABLE $table_name ADD PRIMARY KEY(`product_id`), ADD UNIQUE KEY(`product_id`)" );
			}
		}
	}

	public static function create_tables_for_tasks() {
		global $wpdb;
		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$task_table = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sm_tasks` (
								`id` bigint NOT NULL AUTO_INCREMENT COMMENT 'id of the task',
								`title` text NOT NULL COMMENT 'field title',
								`date` datetime NOT NULL COMMENT 'task creation date',
								`completed_date` datetime NULL COMMENT 'task completion date',
								`post_type` text NOT NULL COMMENT 'field post type',
								`author` int NOT NULL DEFAULT '0' COMMENT 'id of the user who created the task',
								`type` enum('inline','bulk_edit') NOT NULL COMMENT 'edit functionality type',
								`status` enum('in-progress','completed','scheduled') NOT NULL COMMENT 'field updated status',
								`actions` longtext NOT NULL COMMENT 'serialized string of all actions executed in this task',
								`record_count` bigint NOT NULL COMMENT 'count of records updated in this task',
								PRIMARY KEY (`id`)
							) $collate;";
		dbDelta( $task_table );					

		$task_details_table = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sm_task_details` (
								`id` bigint NOT NULL AUTO_INCREMENT COMMENT 'task detail id',
								`task_id` bigint NOT NULL COMMENT 'Foreign key (id) from sm_tasks table',
								`record_id` bigint NOT NULL COMMENT 'id of the record being updated',
								`status` enum('in-progress','completed','scheduled') NOT NULL COMMENT 'field updated status',
								`field` text NOT NULL COMMENT 'name of the field/column being updated',
								`action` varchar(255) NOT NULL COMMENT 'action executed on the field',
								`prev_val` longtext NOT NULL COMMENT 'field value before updates',
								`updated_val` longtext NOT NULL COMMENT 'field value after updates',
								PRIMARY KEY (`id`)
							) $collate;";

		dbDelta( $task_details_table );
	}

	// Function to port settings
	public static function update_890_port_settings(){
		if ( ! class_exists( 'Smart_Manager_Settings' ) ) {
			return;
		}
		if( empty( Smart_Manager_Settings::$db_option_key ) ){
			return;
		}

		$settings = array(
			'general' => array(
				'toggle' => array(
					'wp_force_collapse_admin_menu'                  => get_option( 'sm_wp_force_collapse_admin_menu', 'yes' ),
					'use_number_field_for_numeric_cols'             => get_option( 'sm_use_number_field_for_numeric_cols', 'yes' ),
					'view_trash_records'                            => get_option( 'sm_view_trash_records', 'no' ),
					'show_manage_with_smart_manager_button'         => get_option( 'sm_show_manage_with_sm_button', 'yes' ),
					'show_smart_manager_menu_in_admin_bar'          => get_option( 'sm_show_smart_manager_menu_in_admin_bar', 'yes' )
				),
				'numeric' => array(
					'per_page_record_limit' => get_option( '_sm_beta_set_record_limit', 50 )
				),
				'text'  => array(
					'grid_row_height' => get_option( 'sm_grid_row_height', '50px' )
				)
			)
		);
		
		if( defined('SMPRO') && true === SMPRO ) {
			$settings['general']['toggle']['show_tasks_title_modal'] = get_option( 'sm_show_tasks_title_modal', 'yes' );
			
			$attachment_url = get_option( 'smart_manager_company_logo', '' );
			$attachment_id = ( ! empty( $attachment_url ) ) ? attachment_url_to_postid( $attachment_url ) : 0;
			$settings['general']['image'] = array( 'company_logo_for_print_invoice' => $attachment_id );
		}

		update_option( Smart_Manager_Settings::$db_option_key, $settings, 'no' );
	}

	public static function update_8180_create_tasks_tables_for_lite_version() {
		self::create_tables_for_tasks();
	}

	/**
	* Function to port access privilege settings
	*
	*/
	public static function update_8310_port_access_privilege_settings() {
		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT LEFT(SUBSTR(option_name, %d), LOCATE(%s, SUBSTR(option_name, %d)) -1) as user_role,
															option_value as dashboards
															FROM {$wpdb->prefix}options 
															WHERE option_name LIKE %s 
															AND option_name LIKE %s", 
															strlen( 'sm_beta_' ) + 1, 
															'_accessible_dashboards',
															strlen( 'sm_beta_' ) + 1,
															$wpdb->esc_like( 'sm_beta_' ) . '%',
															'%' . $wpdb->esc_like( '_accessible_dashboards' ) ), 'ARRAY_A' );
		if ( empty( $results ) || ! is_array( $results ) ) {
			return;
		}
		$post_type_dashboards = ( class_exists( 'Smart_Manager' ) && is_callable( array('Smart_Manager', 'get_dashboards') ) ) ? Smart_Manager::get_dashboards() : array();
		$taxonomy_dashboards = ( class_exists( 'Smart_Manager' ) && is_callable( array('Smart_Manager', 'get_taxonomies') ) ) ? Smart_Manager::get_taxonomies() : array();
		foreach ( $results as $result ) {
			$get_user_role_accessible_dashboards = array(
				'valid' => array(
									'post_types' => array(),
									'taxonomies' => array(),
									'sm_views' => array(),
								),
				'not_valid' => array(
									'post_types' => array(),
									'taxonomies' => array(),
									'sm_views' => array(),
								)
			);
			$role = ( ! empty( $result['user_role'] ) ) ? $result['user_role'] : '';
			if ( empty( $role ) ) {
				continue;
			}
			$dashboards = ( ! empty( $result['dashboards'] ) ) ? $result['dashboards'] : '';
			if ( empty( $dashboards ) ) {
				continue;
			}
			$dashboards = explode( ",", $dashboards );
			if ( empty( $dashboards ) ||! is_array( $dashboards ) ) {
				continue;
			}
			foreach ( $dashboards as $dashboard ) {
				switch ( $dashboard ) {
					case ( ! empty( $post_type_dashboards ) && is_array( $post_type_dashboards ) && array_key_exists( $dashboard, $post_type_dashboards ) ):
						$get_user_role_accessible_dashboards['valid']['post_types'][] = $dashboard;
						break;
					case ( ! empty( $taxonomy_dashboards ) && is_array( $taxonomy_dashboards ) && array_key_exists( $dashboard, $taxonomy_dashboards ) ):
						$get_user_role_accessible_dashboards['valid']['taxonomies'][] = $dashboard;
						break;
					default:
						$get_user_role_accessible_dashboards['valid']['sm_views'][] = $dashboard;
						break;
				}
			}
			if ( empty( $get_user_role_accessible_dashboards ) ) {
				continue;
			}
			$wpdb->query( "INSERT INTO {$wpdb->prefix}options ( option_name, option_value, autoload ) VALUES ( 'sa_sm_" . $role . "_dashboards', '" . maybe_serialize( $get_user_role_accessible_dashboards ) ."', 'no' )" );
			delete_option( "sm_beta_" . $role . "_accessible_dashboards" );
		}
	}

	/**
	 * Check if a specific column exists in a table.
	 *
	 * @param string $table_name  The name of the table to check.
	 * @param string $column_name The name of the column to check for.
	 *
	 * @return bool True if the column exists, false otherwise, void if table_name,column_name is empty.
	*/
	public static function check_table_column_exists( $table_name = "", $column_name = "" ) {
		if ( ( empty( $table_name ) ) || ( empty( $column_name ) ) ) {
			return;
		}
		// Check if the table exists.
		if ( empty( self::check_table_exists( $table_name ) ) ) {
			return;
		}
		global $wpdb;
		$column_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table_name LIKE %s", $column_name ) );
		if ( ( is_wp_error( $column_exists ) ) ) {
			return;
		}
		return ( ( empty( $column_exists ) ) ) ? false : true;
	}

	/**
	 * Check if a table exists in the database.
	 *
	 * @param string $table_name The name of the table to check.
	 *
	 * @return bool True if the table exists, false otherwise, void if table_name in empty.
	*/
	public static function check_table_exists( $table_name = "" ) {
		if ( ( empty( $table_name ) ) ) {
			return;
		}
		global $wpdb;
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		return ( ( empty( $table_exists ) ) || ( is_wp_error( $table_exists ) ) ) ? false : true;
	}

	/**
	 * Update the 'sm_views' table to add the 'type' column if it does not exist.
	 *
	 * The 'type' column is added as an ENUM type with default value '0'.
	 * 
	 * @return bool Void.
	*/
	public static function update_856_alter_table() {
		global $wpdb;
		// Check if the 'type' column already exists.
		if ( false === ( self::check_table_column_exists( $wpdb->prefix . 'sm_views', 'type' ) ) ) {
			// Add the 'type' column as ENUM type with default value '0'.
			$wpdb->query( $wpdb->prepare( "
            	ALTER TABLE {$wpdb->prefix}sm_views
            	ADD COLUMN `type` ENUM('0', '1', '2') DEFAULT %s
            	COMMENT 'ENUM values: 0 - Custom View, 1 - Saved Search, 2 - Saved Bulk Edit'
            	AFTER `post_type`", 
            	'0'
        	) );
		}
	}

	/**
	 * Creates predefined Custom Views.
	 * 
	 * @return void
	 */
	public static function create_predefined_custom_views() {
		if ( ! ( defined('SMPRO') && ( true === SMPRO ) ) ) {
			return;
		}
		global $wpdb;
		$email = get_option( 'admin_email' );
		if ( empty( $email ) ) {
			return;
		}
		$user = get_user_by( 'email', $email );
		if ( empty( $user ) || empty( $user->ID ) ) {
			return;
		}
		$user_id = $user->ID;
		$views    = array(
			'product-stock' => array(
				'title'     => 'Product Stock',
				'params'    => '{"columns":{"posts_id":{"width":100,"position":1},"postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id":{"width":211.787109375,"position":2},"posts_post_title":{"width":200,"position":3},"postmeta_meta_key__sku_meta_value__sku":{"width":100,"position":4},"postmeta_meta_key__manage_stock_meta_value__manage_stock":{"width":191.77734375,"position":5},"postmeta_meta_key__stock_status_meta_value__stock_status":{"width":168.4130859375,"position":6},"postmeta_meta_key__stock_meta_value__stock":{"width":100,"position":7},"postmeta_meta_key__backorders_meta_value__backorders":{"width":151.728515625,"position":8}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"treegrid":"true","search_params":{"isAdvanceSearch":"false","params":""}}',
				'post_type' => 'product',
				'is_public' => 1,
			),
			'product-price-list' => array(
				'title'     => 'Product Price List',
				'params'    => '{"columns":{"posts_id":{"width":100,"position":1},"postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id":{"width":211.787109375,"position":2},"posts_post_title":{"width":200,"position":3},"postmeta_meta_key__sku_meta_value__sku":{"width":100,"position":4},"postmeta_meta_key__regular_price_meta_value__regular_price":{"width":181.7431640625,"position":5},"postmeta_meta_key__sale_price_meta_value__sale_price":{"width":136.728515625,"position":6},"postmeta_meta_key__sale_price_dates_from_meta_value__sale_price_dates_from":{"width":250,"position":7},"postmeta_meta_key__sale_price_dates_to_meta_value__sale_price_dates_to":{"width":250,"position":8}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"treegrid":"true","search_params":{"isAdvanceSearch":"false","params":""}}',
				'post_type' => 'product',
				'is_public' => 1,
			),
			'customers' => array(
				'title'     => 'Customers',
				'params'    => '{"columns":{"users_id":{"width":100,"position":1},"usermeta_meta_key_first_name_meta_value_first_name":{"width":196,"position":2},"usermeta_meta_key_last_name_meta_value_last_name":{"width":210,"position":3},"users_user_email":{"width":239,"position":4},"users_user_registered":{"width":204,"position":5},"custom_last_order_date":{"width":207,"position":6},"custom_last_order_total":{"width":179,"position":7},"custom_orders_count":{"width":157,"position":8},"custom_orders_total":{"width":147,"position":9},"usermeta_meta_key_billing_address_1_meta_value_billing_address_1":{"width":232,"position":10},"usermeta_meta_key_billing_city_meta_value_billing_city":{"width":197,"position":11},"usermeta_meta_key_billing_state_meta_value_billing_state":{"width":192,"position":12},"usermeta_meta_key_billing_phone_meta_value_billing_phone":{"width":200,"position":13}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"search_params":{"isAdvanceSearch":"true","params":[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'.$wpdb->prefix.'usermeta.'.$wpdb->prefix.'capabilities","operator":"is","value":"customer"}]}]}]}}',
				'post_type' => 'user',
				'is_public' => 1,
			),
			'pending-for-shipping-orders' => array(
				'title'     => 'Pending for Shipping Orders',
				'params'    => '{"columns":{"wc_orders_id":{"width":100,"position":1},"wc_orders_date_created_gmt":{"width":197,"position":2},"woocommerce_order_items_shipping_method":{"width":193,"position":3},"wc_orders_total_amount":{"width":149,"position":4},"custom_line_items":{"width":325,"position":5},"wc_orders_billing_email":{"width":222,"position":6},"wc_order_addresses_billing_first_name":{"width":189,"position":7},"wc_order_addresses_billing_last_name":{"width":181,"position":8},"wc_orders_meta_meta_key__billing_address_index_meta_value__billing_address_index":{"width":373,"position":9},"wc_orders_meta_meta_key__shipping_address_index_meta_value__shipping_address_index":{"width":340,"position":10},"wc_orders_payment_method":{"width":211,"position":11},"wc_orders_currency":{"width":135,"position":12},"wc_orders_customer_note":{"width":211,"position":13}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"search_params":{"isAdvanceSearch":"true","params":[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'.$wpdb->prefix.'wc_orders.status","operator":"is","value":"wc-processing"}]}]}]}}',
				'post_type' => 'shop_order',
				'is_public' => 1,
			),
			'draft-products' => array(
				'title'     => 'Draft Products',
				'params'    => '{"columns":{"posts_id":{"width":141,"position":1},"postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id":{"width":206,"position":2},"postmeta_meta_key__product_image_gallery_meta_value__product_image_gallery":{"width":324,"position":3},"posts_post_date":{"width":188,"position":4},"posts_post_author":{"width":142,"position":5},"posts_post_title":{"width":275,"position":6},"postmeta_meta_key__sku_meta_value__sku":{"width":185,"position":7},"postmeta_meta_key__regular_price_meta_value__regular_price":{"width":155,"position":8},"postmeta_meta_key__sale_price_meta_value__sale_price":{"width":145,"position":9},"postmeta_meta_key__manage_stock_meta_value__manage_stock":{"width":154,"position":10},"postmeta_meta_key__stock_status_meta_value__stock_status":{"width":184,"position":11},"postmeta_meta_key__stock_meta_value__stock":{"width":140,"position":12},"posts_post_content":{"width":280,"position":13},"posts_post_excerpt":{"width":299,"position":14},"terms_product_cat":{"width":238,"position":15},"custom_product_attributes":{"width":306,"position":16},"terms_product_type":{"width":191,"position":17},"custom_edit_link":{"width":88,"position":18},"custom_view_link":{"width":90,"position":19}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"search_params":{"isAdvanceSearch":"true","params":[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'.$wpdb->prefix.'posts.post_status","operator":"is","value":"draft"}]}]}]}}',
				'post_type' => 'product',
				'is_public' => 1,
			),
			'expired-coupons' => array(
				'title'     => 'Expired Coupons',
				'params'    => '{"columns":{"posts_id":{"width":112,"position":1},"posts_post_title":{"width":229,"position":2},"postmeta_meta_key_date_expires_meta_value_date_expires":{"width":257,"position":3},"postmeta_meta_key_discount_type_meta_value_discount_type":{"width":260,"position":4},"postmeta_meta_key__used_by_meta_value__used_by":{"width":204,"position":5},"postmeta_meta_key_coupon_amount_meta_value_coupon_amount":{"width":212,"position":6},"posts_post_excerpt":{"width":366,"position":7},"postmeta_meta_key_product_ids_meta_value_product_ids":{"width":256,"position":8},"postmeta_meta_key_product_categories_meta_value_product_categories":{"width":216,"position":9},"postmeta_meta_key_customer_email_meta_value_customer_email":{"width":200,"position":10},"postmeta_meta_key_usage_count_meta_value_usage_count":{"width":146,"position":11},"postmeta_meta_key_usage_limit_meta_value_usage_limit":{"width":234,"position":12},"postmeta_meta_key_free_shipping_meta_value_free_shipping":{"width":195,"position":13},"postmeta_meta_key_individual_use_meta_value_individual_use":{"width":209,"position":14},"postmeta_meta_key_exclude_sale_items_meta_value_exclude_sale_items":{"width":210,"position":15},"postmeta_meta_key_usage_limit_per_user_meta_value_usage_limit_per_user":{"width":225,"position":16}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"search_params":{"isAdvanceSearch":"true","params":[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"' . $wpdb->prefix . 'postmeta.date_expires","operator":"lt","value":"'. date('Y-m-d') . '"}]}]}]}}',
				'post_type' => 'shop_coupon',
				'is_public' => 1,
			),
			'unattached-media' => array(
				'title'     => 'Unattached Media',
				'params'    => '{"columns":{"postmeta_meta_key__wp_attached_file_meta_value__wp_attached_file":{"width":130,"position":1},"posts_post_author":{"width":299,"position":2},"posts_post_date":{"width":277,"position":3},"custom_edit_link":{"width":120,"position":4},"custom_view_link":{"width":120,"position":5}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"search_params":{"isAdvanceSearch":"true","params":[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"' . $wpdb->prefix . 'posts.post_parent","operator":"eq","value":"0"}]}]}]}}',
				'post_type' => 'attachment',
				'is_public' => 1,
			)
		);
		$views['pending-for-shipping-orders']['params'] = ( empty( Smart_Manager::$sm_is_wc_hpos_tables_exists ) ) ? '{"columns":{"posts_id":{"width":122,"position":1},"posts_post_date_gmt":{"width":199,"position":2},"woocommerce_order_items_shipping_method":{"width":215,"position":3},"postmeta_meta_key__order_total_meta_value__order_total":{"width":136,"position":4},"custom_line_items":{"width":390,"position":5},"postmeta_meta_key__billing_email_meta_value__billing_email":{"width":281,"position":6},"postmeta_meta_key__billing_first_name_meta_value__billing_first_name":{"width":261,"position":7},"postmeta_meta_key__billing_last_name_meta_value__billing_last_name":{"width":224,"position":8},"postmeta_meta_key__billing_address_index_meta_value__billing_address_index":{"width":349,"position":9},"postmeta_meta_key__shipping_address_index_meta_value__shipping_address_index":{"width":372,"position":10},"postmeta_meta_key__payment_method_meta_value__payment_method":{"width":207,"position":11},"postmeta_meta_key__order_currency_meta_value__order_currency":{"width":199,"position":12},"custom_note_for_customer":{"width":250,"position":13}},"sort_params":{"orderby":"ID","order":"DESC","default":true},"search_params":{"isAdvanceSearch":"true","params":[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'.$wpdb->prefix.'posts.post_status","operator":"is","value":"wc-processing"}]}]}]}}' : $views['pending-for-shipping-orders']['params'];
		// Fetch already existing slugs for predefined Custom Views in a single query.
		$predefined_custom_views_slugs = array_keys( $views );
		$placeholders = ( ( ! empty( $predefined_custom_views_slugs ) ) && ( is_array( $predefined_custom_views_slugs ) ) ) ? implode( ',', array_fill( 0, count( $predefined_custom_views_slugs ), '%s' ) ) : '';
		// Delete 'pending-for-shipping-orders' slug for both HPOS and non-HPOS stores.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sm_views WHERE slug = %s", 'pending-for-shipping-orders' ) );
		// Fetch existing view slugs.
		$existing_slugs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT slug FROM {$wpdb->prefix}sm_views WHERE slug IN ( $placeholders )",
				...$predefined_custom_views_slugs
			)
		);
		$non_existing_slugs = ( is_array( $predefined_custom_views_slugs ) && is_array( $existing_slugs ) && ( ! empty( $predefined_custom_views_slugs ) ) ) ? array_diff( $predefined_custom_views_slugs, $existing_slugs ) : array();
		// If no new views to add, return early.
		if ( empty( $non_existing_slugs ) || ( ! is_array( $non_existing_slugs ) ) ) {
			return;
		}
		foreach ( $non_existing_slugs as $non_existing_slug ) {
			if ( empty( $views[ $non_existing_slug ] ) || ( ! is_array( $views[ $non_existing_slug ] ) ) ) {
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}sm_views ( author, title, slug, params, is_public, post_type, created_date, modified_date )
					VALUES ( %d, %s, %s, %s, %d, %s, %d, %d )",
					$user_id,
					$views[ $non_existing_slug ]['title'],
					$non_existing_slug,
					$views[ $non_existing_slug ]['params'],
					$views[ $non_existing_slug ]['is_public'],
					$views[ $non_existing_slug ]['post_type'],
					time(),
					time()
				)
			);
		}
	}

	/**
	 * Rename attachment post type columns
	 * 
	 * @return void
	 */
	public static function rename_attachment_columns() {
		if ( ( ! ( defined('SMPRO') ) || ( true !== SMPRO ) ) ) {
			return;
		}
		$columns = get_option( 'sa_sm_attachment_columns', array() );
		if( ( ! is_array( $columns ) ) || ( ( is_array( $columns ) ) && ( ! empty( $columns[ 'postmeta_meta_key__wp_attached_file_meta_value__wp_attached_file' ] ) ) ) ) {
			return;
		}
		$columns[ 'postmeta_meta_key__wp_attached_file_meta_value__wp_attached_file' ] = 'File';
		update_option( 'sa_sm_attachment_columns', $columns );
	}

	/**
	 * Update the 'sm_tasks' table to add 'external' enum option to type column if it exists.
	 * 
	 * @return bool Void.
	*/
	public static function update_869_alter_table() {
		global $wpdb;
		// Check if the 'type' column already exists.
		if ( true === ( self::check_table_column_exists( $wpdb->prefix . 'sm_tasks', 'type' ) ) ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE {$wpdb->prefix}sm_tasks MODIFY `type` ENUM(%s,%s,%s) NOT NULL COMMENT 'edit functionality type'", 'inline', 'bulk_edit', 'external' ) );
		}
	}
}

Smart_Manager_Install::init();
