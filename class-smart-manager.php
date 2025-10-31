<?php

defined( 'ABSPATH' ) || exit;

class Smart_Manager {

	static $text_domain, $prefix, $sku, $plugin_file, $sm_is_woo44, $sm_is_woo40, $sm_is_woo39, $sm_is_woo36, $sm_is_woo30, $sm_is_woo22, $sm_is_woo21, $sm_is_woo79, $sm_is_wc_hpos_tables_exists = false, $sm_is_woo92;

	public  $plugin_path 	= '',
			$plugin_url 	= '',
			$plugin_info 	= '',
			$version 		= '',
			$updater 		= '',
			$error_message 	= '',
			$upgrade 		= '',
			$update_msg 	= '',
			$success_msg 	= '',
			$sm_accessible_views = array(),
			$sm_owned_views = array(),
			$sm_public_views = array(),
			$sm_view_post_types = array(),
			$sm_saved_searches = array(),
			$all_views = array(),
			$dupdater = '',
			$dupgrade = '',
			$show_pricing_page = false;

	protected static $_instance = null;
	public static $sm_dashboards_final = array();
	public static $sm_public_dashboards = array();
	public static $taxonomy_dashboards = array();
	// Time saved per record in hours.
	public static $time_saved_per_record = array(
		'inline'                  => ( 2 / 60 ),
		'advanced_search_inline'  => ( 3 / 60 ),
		'bulk'                    => ( 4.5 / 60 ),
	);
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		$this->define_constants(); // for defining constants
		$this->includes(); // for including necessary files
		$this->init_hooks(); // for defining all actions & filters
	}

	//Function for defining WooCommerce related constants for SM
	public function define_woo_constants() {
		if( defined('WOOCOMMERCE_VERSION') ) {
			// checking the version for WooCommerce plugin
			define ( 'IS_WOO13', version_compare ( WOOCOMMERCE_VERSION, '1.4', '<' ) );
			if ( version_compare( WOOCOMMERCE_VERSION , '9.2.0', '<' ) ) {
				if ( version_compare( WOOCOMMERCE_VERSION , '7.9.0', '<' ) ) {

					if ( version_compare( WOOCOMMERCE_VERSION , '4.4.0', '<' ) ) {

						if ( version_compare( WOOCOMMERCE_VERSION , '4.0.0', '<' ) ) {

							if ( version_compare( WOOCOMMERCE_VERSION , '3.9.0', '<' ) ) {

								if ( version_compare( WOOCOMMERCE_VERSION , '3.6.0', '<' ) ) {

									if (version_compare ( WOOCOMMERCE_VERSION, '3.0.0', '<' )) {

										if (version_compare ( WOOCOMMERCE_VERSION, '2.2.0', '<' )) {

											if (version_compare ( WOOCOMMERCE_VERSION, '2.1.0', '<' )) {

												if (version_compare ( WOOCOMMERCE_VERSION, '2.0', '<' )) {
													define ( 'SM_IS_WOO16', "true" );
												} else {
													define ( 'SM_IS_WOO16', "false" );
												}
												define ( 'SM_IS_WOO21', "false" );
											} else {
												define ( 'SM_IS_WOO16', "true" );
												define ( 'SM_IS_WOO21', "true" );
											}
											define ( 'SM_IS_WOO22', "false" );
										} else {
											define ( 'SM_IS_WOO16', "true" );
											define ( 'SM_IS_WOO21', "true" );
											define ( 'SM_IS_WOO22', "true" );
										}
										define ( 'SM_IS_WOO30', "false" );
									} else {
										define ( 'SM_IS_WOO16', "true" );
										define ( 'SM_IS_WOO21', "true" );
										define ( 'SM_IS_WOO22', "true" );
										define ( 'SM_IS_WOO30', "true" );
									}
									define ( 'SM_IS_WOO36', "false" );
								} else {
									define( 'SM_IS_WOO36', 'true' );
									define( 'SM_IS_WOO30', 'true' );
									define( 'SM_IS_WOO22', 'true' );
									define( 'SM_IS_WOO21', 'true' );
									define( 'SM_IS_WOO16', 'true' );
								}
								define( 'SM_IS_WOO39', 'false' );
							} else {
								define( 'SM_IS_WOO39', 'true' );
								define( 'SM_IS_WOO36', 'true' );
								define( 'SM_IS_WOO30', 'true' );
								define( 'SM_IS_WOO22', 'true' );
								define( 'SM_IS_WOO21', 'true' );
								define( 'SM_IS_WOO16', 'true' );
							}
							define( 'SM_IS_WOO40', 'false' );
						} else {
							define( 'SM_IS_WOO40', 'true' );
							define( 'SM_IS_WOO39', 'true' );
							define( 'SM_IS_WOO36', 'true' );
							define( 'SM_IS_WOO30', 'true' );
							define( 'SM_IS_WOO22', 'true' );
							define( 'SM_IS_WOO21', 'true' );
							define( 'SM_IS_WOO16', 'true' );
						}
						define( 'SM_IS_WOO44', 'false' );
					} else {
						define( 'SM_IS_WOO44', 'true' );
						define( 'SM_IS_WOO40', 'true' );
						define( 'SM_IS_WOO39', 'true' );
						define( 'SM_IS_WOO36', 'true' );
						define( 'SM_IS_WOO30', 'true' );
						define( 'SM_IS_WOO22', 'true' );
						define( 'SM_IS_WOO21', 'true' );
						define( 'SM_IS_WOO16', 'true' );
					}
					define( 'SM_IS_WOO79', 'false' );
				} else {
					( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&  \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) ? define( 'SM_IS_WOO79', 'true' ) : define( 'SM_IS_WOO79', 'false' );
					define( 'SM_IS_WOO44', 'true' );
					define( 'SM_IS_WOO40', 'true' );
					define( 'SM_IS_WOO39', 'true' );
					define( 'SM_IS_WOO36', 'true' );
					define( 'SM_IS_WOO30', 'true' );
					define( 'SM_IS_WOO22', 'true' );
					define( 'SM_IS_WOO21', 'true' );
					define( 'SM_IS_WOO16', 'true' );
				}
				define( 'SM_IS_WOO92', 'false' );
			} else {
				define( 'SM_IS_WOO92', 'true' );
				( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&  \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) ? define( 'SM_IS_WOO79', 'true' ) : define( 'SM_IS_WOO79', 'false' );
				define( 'SM_IS_WOO44', 'true' );
				define( 'SM_IS_WOO40', 'true' );
				define( 'SM_IS_WOO39', 'true' );
				define( 'SM_IS_WOO36', 'true' );
				define( 'SM_IS_WOO30', 'true' );
				define( 'SM_IS_WOO22', 'true' );
				define( 'SM_IS_WOO21', 'true' );
				define( 'SM_IS_WOO16', 'true' );
			}
		}
	}

	public function define_constants() {
		$plugin = plugin_basename( SM_PLUGIN_FILE );
		$msg = str_word_count("Upgrade In Progress");
		$upmsg = "Upgrade to";

		$this->plugin_path  = untrailingslashit( plugin_dir_path( SM_PLUGIN_FILE ) );
		$this->plugin_url   = untrailingslashit( plugins_url( '/', SM_PLUGIN_FILE ) );
		$this->update_msg   = 'editing';
		define( 'SM_PLUGIN_DIR', dirname( $plugin ) );
		define( 'SM_PLUGIN_BASE_NM', $plugin );
		define( 'SM_TEXT_DOMAIN', 'smart-manager-for-wp-e-commerce' );
		define( 'SM_PREFIX', 'sa_smart_manager' );
		define( 'SM_SKU', 'sm' );
		define( 'SM_PLUGIN_NAME', 'Smart Manager' );
		define( 'SM_UPGRADE', $msg );
		define( 'SM_DUPGRADE', ( ($msg*8)+1 ) );
		define( 'SM_UPDATE', $upmsg );
		define( 'SM_ADMIN_URL', get_admin_url() ); //defining the admin url
		define( 'SM_APP_ADMIN_URL', admin_url( 'admin.php?page=smart-manager' ) );

		define( 'SM_PLUGIN_DIR_PATH', dirname( SM_PLUGIN_FILE ) );
		define( 'SM_PLUGINS_FILE_PATH', dirname( dirname( SM_PLUGIN_FILE ) ) );
		define( 'SM_PLUGIN_DIRNAME', plugins_url( '', SM_PLUGIN_FILE ) );

		if ( ! defined( 'SM_IMG_URL' ) ) {
			define( 'SM_IMG_URL', SM_PLUGIN_DIRNAME . '/assets/images/' );
		}

		if (!defined('STORE_APPS_URL')) {
			define( 'STORE_APPS_URL', 'https://www.storeapps.org/' );
		}

		if ( ! defined( 'SMPRO' ) ) {
			if (file_exists ( (dirname ( SM_PLUGIN_FILE )) . '/pro/assets/js/smart-manager.js' )) {
				define ( 'SMPRO', true );
			} else {
				define ( 'SMPRO', false );
			}
		}

		if ( ! defined( 'SM_PRO_URL' ) ) {
			define( 'SM_PRO_URL', (dirname ( SM_PLUGIN_FILE )) . '/pro/' );
		}

		// Static variables
		self::$text_domain = (defined('SM_TEXT_DOMAIN')) ? SM_TEXT_DOMAIN : 'smart-manager-for-wp-e-commerce';
		self::$prefix = (defined('SM_PREFIX')) ? SM_PREFIX : 'sa_smart_manager';
		self::$sku = (defined('SM_SKU')) ? SM_SKU : 'sm';
		self::$plugin_file = (defined('SM_PLUGIN_FILE')) ? SM_PLUGIN_FILE : '';
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_info = get_plugins();
		$this->plugin_info = $plugin_info [SM_PLUGIN_BASE_NM];
		$this->updater = rand(3,3);
		$this->dupdater = rand(25,25);
		$this->upgrade = (defined('SM_UPGRADE')) ? SM_UPGRADE : 3;
		$this->dupgrade = (defined('SM_DUPGRADE')) ? SM_DUPGRADE : 25;
		$this->success_msg   = (defined('SM_UPDATE')) ? SM_UPDATE : '';
		if (! defined('SM_EMAIL_TEMPLATE_PATH')) {
			define('SM_EMAIL_TEMPLATE_PATH', SM_PRO_URL . 'templates/emails');
		}
	}

	//Function for defining dashboards
	public static function get_dashboards() {

		global $wp_version, $wpdb;

		$post_types = get_post_types( array(), 'objects' ); //Code to get all the custom post types as dashboards
		$ignored_post_types = array('revision', 'product_variation', 'shop_order_refund');
		self::$sm_dashboards_final = array();
		self::$sm_public_dashboards = array();
		$dashboard_post_types = array();
		if( !empty( $post_types ) ) {
			foreach( $post_types as $post_type => $obj  ) {

				if( in_array($post_type, $ignored_post_types) ) {
					continue;
				}

				$label = ( ! empty( $obj->label ) ) ? $obj->label : $post_type;
				self::$sm_dashboards_final[ $post_type ] = $label;
				if( !empty( $obj->public ) && $obj->public == 1 ) {
					self::$sm_public_dashboards[] = $post_type;
				}
			    if ( ! isset( $dashboard_post_types[ $label ] ) ) {
			        $dashboard_post_types[ $label ] = array();
			    }
			    $dashboard_post_types[ $label ][] = $post_type;
			}
		}
		self::$sm_dashboards_final ['user'] = __(ucwords('users'), 'smart-manager-for-wp-e-commerce');
		if ( ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) && ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) ) {
			$post_type = 'product_stock_log';
			$label = _x( 'Product Stock Log', 'product stock log dashboard name', 'smart-manager-for-wp-e-commerce' );
			self::$sm_dashboards_final[ $post_type ] = $label;
			if ( ! isset( $dashboard_post_types[ $label ] ) ) {
			    $dashboard_post_types[ $label ] = array();
			  }
			$dashboard_post_types[ $label ][] = $post_type;
		}
		if ( is_callable( array( 'Smart_Manager', 'handle_duplicate_dashboard_names' ) ) ) {
			self::handle_duplicate_dashboard_names( $dashboard_post_types, 'post_type' );
		}
		// TODO change
		if( is_plugin_active( 'lifterlms/lifterlms.php' ) ){
			self::$sm_dashboards_final ['llms_order'] = __( 'LifterLMS Orders', 'smart-manager-for-wp-e-commerce');
			self::$sm_dashboards_final ['llms_coupon'] = __( 'LifterLMS Coupons', 'smart-manager-for-wp-e-commerce');
		}

		if ( ! defined( 'SM_BETA_ALL_DASHBOARDS' ) ) {
			define( 'SM_BETA_ALL_DASHBOARDS', json_encode( self::$sm_dashboards_final ) );
		}

		return self::$sm_dashboards_final = apply_filters( 'sm_active_dashboards', self::$sm_dashboards_final );
	}

	//Function for getting all eligible views
	public function get_views() {

		if( !( defined('SMPRO') && true === SMPRO ) ) {
			return;
		}

		if( class_exists( 'Smart_Manager_Pro_Views' ) ) {
			$view_obj = Smart_Manager_Pro_Views::get_instance();
			if( is_callable( array( $view_obj, 'get_all_accessible_views' ) ) ){
				$views = $view_obj->get_all_accessible_views( array_merge( self::$sm_dashboards_final, self::$taxonomy_dashboards ) );
				if( ! empty( $views ) ) {
					$this->sm_accessible_views = ( ! empty( $views['accessible_views'] ) ) ? $views['accessible_views'] : array();
					$this->sm_owned_views = ( ! empty( $views['owned_views'] ) ) ? $views['owned_views'] : array();
					$this->sm_public_views = ( ! empty( $views['public_views'] ) ) ? $views['public_views'] : array();
					$this->all_views = array_merge( array_keys( $this->sm_accessible_views ), $this->sm_owned_views, $this->sm_public_views );
					$this->sm_view_post_types = ( ! empty( $views['view_post_types'] ) ) ? $views['view_post_types'] : array();
					$this->sm_saved_searches = ( ! empty( $views['saved_searches'] ) ) ? $views['saved_searches'] : array();
				}
			}
		}

		$this->sm_accessible_views = apply_filters( 'sm_accessible_views', $this->sm_accessible_views );
	}

	//Function for defining taxonomies dashboards
	public static function get_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => 1 ), 'objects' ); //TODO: later we can add compat for hidden taxonomies as well
		$dashboard_taxonomies = array();
		if( ! empty( $taxonomies ) ){
			foreach( $taxonomies as $slug => $obj ){
				$label = ( ! empty( $obj->label ) ) ? $obj->label : $slug;
				self::$taxonomy_dashboards[ $slug ] = $label;
				if ( ! isset( $dashboard_taxonomies[ $label ] ) ) {
			        $dashboard_taxonomies[ $label ] = array();
			    }
			    $dashboard_taxonomies[ $label ][] = $slug;
			}

			if ( is_callable( array( 'Smart_Manager', 'handle_duplicate_dashboard_names' ) ) ) {
				self::handle_duplicate_dashboard_names( $dashboard_taxonomies, 'taxonomy' );
			}

			if ( ! defined( 'SM_ALL_TAXONOMY_DASHBOARDS' ) ) {
				define( 'SM_ALL_TAXONOMY_DASHBOARDS', json_encode( self::$taxonomy_dashboards ) );
			}

			return self::$taxonomy_dashboards = apply_filters( 'sm_active_taxonomy_dashboards', self::$taxonomy_dashboards );
		}
	}

	// Function to include necessary files for SM
	public function includes() {

		global $current_user;

		//for settings
		if( file_exists( $this->plugin_path . '/classes/class-smart-manager-settings.php' ) ){
			include_once $this->plugin_path . '/classes/class-smart-manager-settings.php';
			if( defined( 'SMPRO' ) && SMPRO === true && file_exists( SM_PRO_URL . 'classes/class-smart-manager-pro-settings.php' ) ) {
				include_once SM_PRO_URL . 'classes/class-smart-manager-pro-settings.php';
			}
		}

		if( file_exists( $this->plugin_path . '/classes/class-smart-manager-install.php' ) ) {
			include_once $this->plugin_path . '/classes/class-smart-manager-install.php';
		}
		if( file_exists( $this->plugin_path . '/classes/class-smart-manager-utils.php' ) ) {
			include_once $this->plugin_path . '/classes/class-smart-manager-utils.php';
		}
		if( file_exists( $this->plugin_path . '/common-core/classes/class-sa-manager-utils.php' ) ) {
			include_once $this->plugin_path . '/common-core/classes/class-sa-manager-utils.php';
		}
		if ( file_exists( $this->plugin_path . '/common-core/classes/class-sa-manager-controller.php' ) ) {
			include_once $this->plugin_path . '/common-core/classes/class-sa-manager-controller.php';
			$sa_manager_common_params = ( is_callable( 'get_sa_manager_common_params' ) && function_exists( 'get_sa_manager_common_params' ) ) ? get_sa_manager_common_params() : array();
			$GLOBALS['common_core_controller'] = new SA_Manager_Controller(
				$sa_manager_common_params
			);
		}
		if( file_exists( $this->plugin_path . '/classes/class-smart-manager-controller.php' ) ) {
			include_once $this->plugin_path . '/classes/class-smart-manager-controller.php';
			$GLOBALS['smart_manager_controller'] = new Smart_Manager_Controller();
		}

		//for including background updater & other libraries
		if ( defined('SMPRO') && SMPRO === true ) {

			if ( ! class_exists( 'ActionScheduler' ) && file_exists( dirname( SM_PLUGIN_FILE ). '/pro/libraries/action-scheduler/action-scheduler.php' ) ) {
				include_once 'pro/libraries/action-scheduler/action-scheduler.php';
			}

			if (file_exists((dirname(SM_PLUGIN_FILE)) . '/pro/common-pro/classes/class-sa-manager-pro-background-updater.php')) {
				include_once 'pro/common-pro/classes/class-sa-manager-pro-background-updater.php';
			} // include common background updater file.
			if( file_exists( (dirname( SM_PLUGIN_FILE )) . '/pro/classes/class-smart-manager-pro-background-updater.php') ) {
				include_once 'pro/classes/class-smart-manager-pro-background-updater.php';
			}
			if( !class_exists( 'Smart_Manager_Pro_Access_Privilege' ) && file_exists( (dirname( SM_PLUGIN_FILE )) . '/pro/classes/class-smart-manager-pro-access-privilege.php' ) ) {
				include_once 'pro/classes/class-smart-manager-pro-access-privilege.php';
			}

			if ( !class_exists( 'Smart_Manager_Pro_Views' ) && file_exists( ( dirname( SM_PLUGIN_FILE ) ) . '/pro/classes/class-smart-manager-pro-views.php' ) ) {
				require_once 'pro/classes/class-smart-manager-pro-views.php';
			}
		}

		if ( is_admin() ) {
			if( file_exists( $this->plugin_path . '/classes/class-smart-manager-admin-welcome.php' ) ) {
				include_once $this->plugin_path . '/classes/class-smart-manager-admin-welcome.php';
			}

			if( file_exists( $this->plugin_path . '/classes/class-storeapps-marketplace.php' ) ) {
				include_once $this->plugin_path . '/classes/class-storeapps-marketplace.php';
			}

			if( file_exists( $this->plugin_path . '/classes/deactivation-survey/class-sa-smart-manager-deactivation.php' ) ) {
				include_once $this->plugin_path . '/classes/deactivation-survey/class-sa-smart-manager-deactivation.php';
			}

			if ( class_exists( 'SA_Smart_Manager_Deactivation' ) ) {
				if ( defined('SMPRO') && true === SMPRO ) {
					$sm_plugin_name = SM_PLUGIN_NAME . ' - Pro';
				} else {
					$sm_plugin_name = SM_PLUGIN_NAME . ' - Lite';
				}
				$sa_sm_deativate = new SA_Smart_Manager_Deactivation( SM_PLUGIN_BASE_NM, $sm_plugin_name );
			}

		}

	}

	public function init_hooks() {

		register_activation_hook( SM_PLUGIN_FILE, array( 'Smart_Manager_Install', 'install' ) );
		register_deactivation_hook( SM_PLUGIN_FILE, array( 'Smart_Manager_Install', 'deactivate' ) );
		add_action( 'plugins_loaded', array( &$this, 'on_plugins_loaded' ) );
		add_action( 'wp_loaded', array( &$this, 'on_wp_loaded' ) );

		//filters for handling quick_help_widget
		add_filter( 'sa_active_plugins_for_quick_help', array( &$this, 'quick_help_widget' ), 10, 2 );
		add_filter( 'sa_is_page_for_notifications', array( &$this, 'is_page_for_notifications' ), 10, 2 );

		add_action ( 'admin_head', array(&$this,'remove_help_tab_and_hiding_admin_notices') ); // For removing the help tab and hiding admin notices

		add_filter( 'site_transient_update_plugins', array( &$this, 'overwrite_site_transient' ), 11, 1 );
		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'overwrite_site_transient' ), 11, 1 );

		add_action( 'admin_enqueue_scripts', array( $this, 'sa_sm_dequeue_scripts' ), 999 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		add_action( 'admin_init', array( $this, 'on_admin_init' ) );
		add_action( 'admin_init', array( $this, 'localize_smart_manager' ) ); //Language loader

		add_action( 'admin_notices', array( $this, 'add_admin_notices' ) );

		// Remove WP footer on SM pages
		add_filter( 'admin_footer_text', array( &$this, 'footer_text') );
		add_filter( 'update_footer', array( &$this, 'update_footer_text'), 99 );

		//For handling media links on plugins page
		add_action( 'admin_footer', array( &$this, 'add_plugin_social_links' ) );

		add_action( 'admin_footer', array( $this, 'smart_manager_support_ticket_content' ) );
		if( 'yes' === Smart_Manager_Settings::get( 'show_manage_with_smart_manager_button' ) ) {
			add_action( 'admin_footer', array( $this, 'manage_with_smart_manager' ) );
		}

		add_action( 'admin_menu', array( $this, 'add_menu_access' ), 9 );
		if( 'yes' === Smart_Manager_Settings::get( 'show_smart_manager_menu_in_admin_bar' ) ) {
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 99 );
		}

		if (is_admin() ) {
			add_action ( 'wp_ajax_sm_update_to_pro', array( $this, 'update_to_pro' ) );
		}

		if ( defined('SMPRO') && SMPRO === false ) {
			add_action( 'admin_init', array( $this, 'show_upgrade_to_pro' ) ); //for handling Pro to Lite
		} else if ( defined('SMPRO') && SMPRO === true ) {
			add_action( 'admin_init', array( $this, 'pro_activated' ) );
			add_filter( 'plugin_auto_update_setting_html', array( $this,'auto_update_setting_html' ), 10, 3 );
		}

		// Action to declare WooCommerce HPOS compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_additonal_links' ), 99, 4 );
		add_action( 'admin_enqueue_scripts', array( $this, 'sa_sm_dequeue_styles' ), 999 );
		add_filter('sa_plugin_key', function () {
			return 'smart_manager'; // For using in JS code inside background_hearbeat().
		});
		//To extend woocommerce_product_import_process_item_data hook on importer screen.
		add_action('wp_ajax_woocommerce_do_ajax_product_import', function () {
			if ( ( defined('SMPRO') ) && ( SMPRO === true ) && ! class_exists( 'Smart_Manager_Pro_Product_Import_CSV' ) && file_exists( ( dirname( SM_PLUGIN_FILE ) ) . '/pro/classes/class-smart-manager-pro-product-import-csv.php' ) ) {
				require_once 'pro/classes/class-smart-manager-pro-product-import-csv.php';
			}
		});
		add_action( 'wp_ajax_dismiss_feature_notice', array( $this, 'dismiss_feature_notice' ) );
		add_filter( 'sa_sm_manager_request_handler_allowed_dir_path', array( $this, 'request_handler_allowed_dir_path' ), 10 );
		if ( ( defined('SMPRO') ) && ( SMPRO === true ) && ! class_exists( 'Smart_Manager_Pro_Product_Changes_Tracker' ) && file_exists( ( dirname( SM_PLUGIN_FILE ) ) . '/pro/classes/class-smart-manager-pro-product-changes-tracker.php' ) ) {
			require_once 'pro/classes/class-smart-manager-pro-product-changes-tracker.php';
		}
	}

	// Find latest StoreApps Upgrade file
	public function get_latest_upgrade_class() {

		$available_classes = get_declared_classes();
		$available_upgrade_classes = array_filter( $available_classes, function ( $class_name ) {
																								return strpos( $class_name, 'StoreApps_Upgrade_' ) === 0;
																							} );
		$latest_class = 'StoreApps_Upgrade_4_0';
		$latest_version = 0;
		foreach ( $available_upgrade_classes as $class ) {
			$exploded = explode( '_', $class );
			$get_numbers = array_filter( $exploded, function ( $value ) {
														return is_numeric( $value );
													} );
			$version = implode( '.', $get_numbers );
			if ( version_compare( $version, $latest_version, '>' ) ) {
				$latest_version = $version;
				$latest_class = $class;
			}
		}

		return $latest_class;
	}

	//Function for actions to be done on 'plugins_loaded' event
	public function on_plugins_loaded() {
		global $current_user;

		if ( ( defined('SMPRO') && SMPRO === true ) && ! class_exists( 'StoreApps_Upgrade_4_0' ) && file_exists( ( dirname( SM_PLUGIN_FILE ) ) . '/pro/sa-includes/class-storeapps-upgrade-4-0.php' ) ) {
			require_once 'pro/sa-includes/class-storeapps-upgrade-4-0.php';
		}

		$this->show_pricing_page = apply_filters( 'sm_show_pricing_page', false );

		//define woo constants
		$this->define_woo_constants();
		self::$sm_is_woo92 = ( defined('SM_IS_WOO92') && 'true' === SM_IS_WOO92 ) ? true : false;
		self::$sm_is_woo79 = ( defined('SM_IS_WOO79') && 'true' === SM_IS_WOO79 ) ? true : false;
		self::$sm_is_woo44 = (defined('SM_IS_WOO44')) ? SM_IS_WOO44 : '';
		self::$sm_is_woo40 = (defined('SM_IS_WOO40')) ? SM_IS_WOO40 : '';
		self::$sm_is_woo39 = (defined('SM_IS_WOO39')) ? SM_IS_WOO39 : '';
		self::$sm_is_woo36 = (defined('SM_IS_WOO36')) ? SM_IS_WOO36 : '';
		self::$sm_is_woo30 = (defined('SM_IS_WOO30')) ? SM_IS_WOO30 : '';
		self::$sm_is_woo22 = (defined('SM_IS_WOO22')) ? SM_IS_WOO22 : '';
		self::$sm_is_woo21 = (defined('SM_IS_WOO21')) ? SM_IS_WOO21 : '';

		if( self::$sm_is_woo79 && function_exists( 'wc_get_container' ) && class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer' ) && wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class )->check_orders_table_exists() ){
			self::$sm_is_wc_hpos_tables_exists = true;
		}

		//Code for handling the in app offer
		if ( ! class_exists( 'SA_SM_In_App_Offer' ) && file_exists( (dirname( SM_PLUGIN_FILE )) . '/classes/sa-includes/class-sa-sm-in-app-offer.php' ) ) {
			include_once 'classes/sa-includes/class-sa-sm-in-app-offer.php';
			$args = array(
				'file'           => (dirname( SM_PLUGIN_FILE )) . '/classes/sa-includes/',
				'prefix'         => 'sm',				// prefix/slug of your plugin
				'option_name'    => 'sa_sm_offer_bfcm_2024',
				'campaign'       => 'sa_bfcm_2024',
				'start'          => '2024-11-26 07:00:00',
				'end'            => '2024-12-06 06:30:00',
				'is_plugin_page' => ( !empty($_GET['page']) && in_array( $_GET['page'], array( 'smart-manager', 'sm-storeapps-plugins' ) ) ) ? true : false,	// page where you want to show offer, do not send this if no plugin page is there and want to show offer on Products page
			);
			$sa_offer = SA_SM_In_App_Offer::get_instance( $args );
			if ( ! defined( 'SA_OFFER_VISIBLE' ) ) {

				$show = false;

				$timezone_format = _x( 'Y-m-d H:i:s', 'timezone date format' );
				$current_date    = strtotime( date_i18n( $timezone_format ) );
				$start           = strtotime( $args['start'] );
				$end             = strtotime( $args['end'] );
				if ( ( $current_date >= $start ) && ( $current_date <= $end ) ) {
					$show = true;
				}

				define( 'SA_OFFER_VISIBLE', $show );
			}
		}

		if ( ! empty($_GET['page']) && $_GET['page'] == "smart-manager" && ! empty( $_GET['dashboard'] ) ) {
			if( ! empty( $_GET['is_view'] ) ) {
				update_option('sm_wp_dashboard_view_'.get_current_user_id(), $_GET['dashboard'], 'no' );
				wp_safe_redirect( remove_query_arg(array( 'dashboard', 'is_view' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			} else {
				update_option('sm_wp_dashboard_post_type_'.get_current_user_id(), $_GET['dashboard'], 'no' );
				wp_safe_redirect( remove_query_arg('dashboard', wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			}

			exit;
		}
	}

	//Function for actions to be done on 'wp_loaded' event
	public function on_wp_loaded() {
		if ( defined('SMPRO') && SMPRO === true ) {
			$latest_upgrade_class = $this->get_latest_upgrade_class();

			$sku = SM_SKU;
			$prefix = SM_PREFIX;
			$plugin_name = SM_PLUGIN_NAME;
			$documentation_link = 'https://www.storeapps.org/knowledgebase_category/smart-manager/';
			$GLOBALS['smart_manager_upgrade'] = new $latest_upgrade_class( SM_PLUGIN_FILE, $sku, $prefix, $plugin_name, SM_TEXT_DOMAIN, $documentation_link );
		}
	}

	// function to handle the display of quick help widget
	public function quick_help_widget( $active_plugins, $upgrader ) {

		if ( is_admin() && !empty( $_GET['page'] ) && ( 'smart-manager-settings' === $_GET['page'] ) ) {
			$active_plugins[SM_SKU] = 'smart-manager';
		} elseif ( array_key_exists( SM_SKU, $active_plugins ) ) {
			unset( $active_plugins[SM_SKU] );
		}

		return $active_plugins;
	}

	public function is_page_for_notifications( $is_page, $upgrader ) {

		$landing_page = ( !empty( $_GET['landing-page'] ) ) ? $_GET['landing-page'] : '';

		if ( is_admin() && ! empty( $_GET['page'] ) && ( ( 'smart-manager' === $_GET['page'] && 'sm-about' !== $landing_page ) || 'smart-manager-settings' === $_GET['page'] ) ) {
			return true;
		}

		return $is_page;
	}

	// Function to override the site transient
	public function overwrite_site_transient( $plugin_info ) {

		if ( ! defined('SM_SKU') ) {
			return $plugin_info;
		}

		$data = get_option( '_storeapps_connector_data', array() );
		$sm_license_key = !empty($data[SM_SKU]) ? $data[SM_SKU]['license_key'] : '';

		$sm_download_url = $this->get_pro_download_url();

		if ( file_exists((dirname( SM_PLUGIN_FILE )) . '/pro/sm.js') && (empty($sm_license_key) || empty($sm_download_url)) ) {
			$plugin_base_file = plugin_basename( SM_PLUGIN_FILE );

			$live_version = !empty($data[SM_SKU]['live_version']) ? $data[SM_SKU]['live_version'] : '';
			$installed_version = !empty($data[SM_SKU]['installed_version']) ? $data[SM_SKU]['installed_version'] : '';

			if ( version_compare( $live_version, $installed_version, '>' ) ) {
				$plugin_info->response[$plugin_base_file]->package = '';
			}
		}

		return $plugin_info;
	}

	public function get_pro_download_url() {
		$sm_old_download_url = '';
		$sm_new_download_url = '';
		if ( defined('SM_PREFIX') ) {
			$sm_old_download_url = get_site_option( SM_PREFIX.'_download_url' );
		}
		$data = get_option( '_storeapps_connector_data', array() );
		if ( defined('SM_SKU') && ! empty( $data[SM_SKU] ) ) {
			$sm_new_download_url = ( !empty( $data[SM_SKU]['download_url'] ) ) ? $data[SM_SKU]['download_url'] : '';
		}
		$sm_download_url = ( ! empty( $sm_new_download_url ) ) ? $sm_new_download_url : $sm_old_download_url;
		return $sm_download_url;
	}

	public function is_pro_available() {
		$sm_download_url = $this->get_pro_download_url();
		if ( ! file_exists( ( dirname( SM_PLUGIN_FILE ) ) . '/pro/assets/js/smart-manager.js' ) && ! empty( $sm_download_url ) ) {
			return true;
		}
		return false;
	}

	/*
	* Function to to handle media links on plugin page
	*/
	public function add_plugin_social_links() {
		$is_pro_available = $this->is_pro_available();
		if( $is_pro_available === true ) { //request ftp credentials form
			wp_print_request_filesystem_credentials_modal();
		}

		?>
		<script type="text/javascript">
			jQuery(function() {
				jQuery(document).ready(function() {
					jQuery('tr[id="smart-manager"]').find( 'div.plugin-version-author-uri' ).addClass( 'sa_smart_manager_social_links' );
				});
			});
		</script>
		<style type="text/css">
			@keyframes beat {
				to { transform: scale(1.1); }
			}
			.sm_pricing_icon {
				animation: beat .25s infinite alternate;
				transform-origin: center;
				color: #ea7b00;
				display: inline-block;
				font-size: 1.5em;
			}
		</style>

		<?php
	}

	public function localize_smart_manager() {
		$text_domain = SM_TEXT_DOMAIN;

		$plugin_dirname = dirname( plugin_basename(SM_PLUGIN_FILE) );

		$locale = apply_filters( 'plugin_locale', get_locale(), $text_domain );

		$loaded = load_textdomain( $text_domain, WP_LANG_DIR . '/plugins/' . $text_domain . '-' . $locale . '.mo' );

		if ( ! $loaded ) {
			$loaded = load_plugin_textdomain( $text_domain, false, $plugin_dirname . '/languages/' );
		}
	}

	//function to show the upgrade to Pro link only for Pro to Lite
	public function show_upgrade_to_pro() {

		if( !( !empty( $_GET['page'] ) && ( 'smart-manager' === $_GET['page'] || 'smart-manager-woo' === $_GET['page'] || 'smart-manager-wpsc' === $_GET['page'] ) ) ) {
			return;
		}

		$sm_license_key = get_site_option( SM_PREFIX.'_license_key' );

		if ( !empty($sm_license_key) ) {
			$storeapps_validation_url = 'https://www.storeapps.org/?wc-api=validate_serial_key&serial=' . urlencode( $sm_license_key ) . '&is_download=true&sku=' . SM_SKU . '&uuid=' . admin_url();
			$resp_type = array ('headers' => array ('content-type' => 'application/text' ) );
			$response_info = wp_remote_post( $storeapps_validation_url, $resp_type ); //return WP_Error on response failure

			if (is_array( $response_info )) {
				$response_code = wp_remote_retrieve_response_code( $response_info );
				$response_msg = wp_remote_retrieve_response_message( $response_info );

				if ($response_code == 200) {
					$storeapps_response = wp_remote_retrieve_body( $response_info );
					$decoded_response = json_decode( $storeapps_response );
					if ($decoded_response->is_valid == 1) {
						update_site_option( SM_PREFIX.'_download_url', $decoded_response->download_url );
						define('SMPROTOLITE', true);
					} else {
						define('SMPROTOLITE', false);
					}
				} else {
					define('SMPROTOLITE', false);
				}
			}
		}
	}

	public function pro_activated() {
		$is_check = get_option( SM_PREFIX . '_check_update', 'no' );
		if ( $is_check === 'no' ) {
		  $response = wp_remote_get( 'https://www.storeapps.org/wp-admin/admin-ajax.php?action=check_update&plugin='.SM_SKU );
		  update_option( SM_PREFIX . '_check_update', 'yes', 'no' );
		}
	}

	function get_free_menu_position($start, $increment = 0.0001) {
		foreach ($GLOBALS['menu'] as $key => $menu) {
			$menus_positions[] = $key;
		}

		if (!in_array($start, $menus_positions)) return $start;

		/* the position is already reserved find the closet one */
		while (in_array($start, $menus_positions)) {
			$start += $increment;
		}
		return $start;
	}

	// Function to draw the relevant page
	function add_admin_page() {

		if( !empty($_GET['landing-page']) ) {
			$GLOBALS['smart_manager_admin_welcome']->show_welcome_page();
		} else if( !empty( $_GET['page'] ) && 'smart-manager' === $_GET['page'] ) {
			$this->show_console_beta();
		} else if( ( !empty( $_GET['page'] ) && 'smart-manager-pricing' === $_GET['page'] ) ) {
			if ( headers_sent() ) {
				echo "<meta http-equiv='refresh' content='" . esc_attr( "0;url=admin.php?page=smart-manager&tab=upgrade#!/pricing" ) . "' />";
			} else {
				wp_redirect( admin_url( 'admin.php?page=smart-manager&tab=upgrade#!/pricing' ) );
			}
			exit;
		} else if( ( !empty( $_GET['page'] ) && 'sm-storeapps-plugins' === $_GET['page'] ) && ( class_exists( 'StoreApps_Marketplace' ) && is_callable( array('StoreApps_Marketplace', 'init') ) ) ) {
			StoreApps_Marketplace::init();
		} else {
			if ( headers_sent() ) {
				echo "<meta http-equiv='refresh' content='" . esc_attr( "0;url=admin.php?page=smart-manager" ) . "' />";
			} else {
				wp_redirect( admin_url( 'admin.php?page=smart-manager' ) );
			}
			exit;
		}
	}

	// Function to add menu
	function add_menu() {

		$current_user_role = ( is_callable( array( 'Smart_Manager', 'get_current_user_role' ) ) ) ? self::get_current_user_role() : '';
		$position = (string) $this->get_free_menu_position(56.00001);

		if( ( defined( 'SMPRO' ) && true === SMPRO  ) || ( ( ! empty( $current_user_role ) && 'administrator' === $current_user_role ) ) ) {
			$page = add_menu_page( 'Smart Manager', 'Smart Manager','read', 'smart-manager', array( $this, 'add_admin_page' ), 'dashicons-performance', $position );

			if( defined( 'SMPRO' ) && true !== SMPRO || ! empty( $this->show_pricing_page ) ) {
				add_submenu_page( 'smart-manager', __( '<span class="sm_pricing_icon"> 🔥 </span> Go Pro', 'smart-manager-for-wp-e-commerce' ), __( '<span class="sm_pricing_icon"> 🔥 </span> Go Pro', 'smart-manager-for-wp-e-commerce' ), 'manage_options', 'smart-manager-pricing', array( $this, 'add_admin_page' ) );
			}

			add_submenu_page( 'smart-manager', __( 'Docs & Support', 'smart-manager-for-wp-e-commerce' ),  __( 'Docs & Support', 'smart-manager-for-wp-e-commerce' ), 'manage_options', 'smart-manager&landing-page=sm-about', array( $this, 'add_admin_page' ) );

			$show_sa_plugins_page = true;
			$show_sa_plugins_page = apply_filters('sm_show_sa_plugins_page', $show_sa_plugins_page);

			if( !empty( $show_sa_plugins_page ) ) {
				add_submenu_page( 'smart-manager', __( 'StoreApps Plugins', 'smart-manager-for-wp-e-commerce' ),  __( 'StoreApps Plugins', 'smart-manager-for-wp-e-commerce' ), 'manage_options', 'sm-storeapps-plugins', array( $this, 'add_admin_page' ) );
			}
		}
	}

	function add_menu_access() {
		global $wpdb;

		$current_user_role = ( is_callable( array( 'Smart_Manager', 'get_current_user_role' ) ) ) ? self::get_current_user_role() : '';
		if( ( empty( $current_user_role ) ) ) return;

		if( 'administrator' === $current_user_role ){
			$this->add_menu();
			return;
		}

		$query = "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'sm_" . $current_user_role . "_dashboard'";
		$result_old = $wpdb->get_results( $query );

		$user_role_accessible_dashboards = array();
		$user_accessible_dashboards = array();

		if( class_exists('Smart_Manager_Pro_Access_Privilege') ) {
			$option_nm = Smart_Manager_Pro_Access_Privilege::$access_privilege_option_start."".$current_user_role."".Smart_Manager_Pro_Access_Privilege::$access_privilege_option_end;
			$user_role_accessible_dashboards = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_name = %s", $option_nm ), 'ARRAY_A' );
			$user_accessible_dashboards = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}usermeta WHERE user_id = %d AND meta_key = %s", get_current_user_id(), Smart_Manager_Pro_Access_Privilege::$access_privilege_option_start."dashboards" ), 'ARRAY_A' );
		}
		if ( ( ! empty( $result_old[0] ) && ! empty( $result_old[0]->option_value ) ) || ! empty( $user_accessible_dashboards )  || ! empty( $user_role_accessible_dashboards ) ) { //modified cond for client fix
			$this->add_menu();
		}
	}


	public static function get_current_user_role() {

		global $current_user;

		if ( ! function_exists('wp_get_current_user') ) {
			require_once (ABSPATH . 'wp-includes/pluggable.php'); // Sometimes conflict with SB-Welcome Email Editor
		}

		$current_user = wp_get_current_user(); // Sometimes conflict with SB-Welcome Email Editor
		$current_user_role = '';
		$current_user_caps = '';

		$roles = ( is_object( $current_user ) && ( ! empty( $current_user->roles ) ) && ( is_array( $current_user->roles ) ) && ( ! empty( $current_user->roles[0] ) ) ) ? array_values( $current_user->roles ) : $current_user->roles;
		if( ! empty( $roles ) && sizeof( $roles ) > 0 ) {
			$user_role = array_search( 'administrator', $roles );
			$current_user_role = ( false !== $user_role ) ? $roles[ $user_role ] : $roles[0];
		}

		$caps = ( ! empty( $current_user->caps ) ) ? array_keys( $current_user->caps ) : array();
		if( ! empty( $caps ) && sizeof( $caps ) > 0 ) {
			$user_caps = array_search( 'administrator', $roles );
			$current_user_caps = ( false !== $user_caps ) ? $caps[ $user_caps ] : $caps[0];
		}

		return ( ( ! empty( $current_user_role ) && 'administrator' === $current_user_role ) || ( ! empty( $current_user_caps ) && 'administrator' === $current_user_caps ) ) ? 'administrator' : ( !empty( $current_user_caps ) ? $current_user_caps : $current_user_role );
	}

	public function on_admin_init() {
		global $wp_version,$wpdb;
		if( is_callable( array( 'Smart_Manager', 'get_version' ) ) ) {
			$this->version = self::get_version();
		}
		$this->get_dashboards();
		$this->get_taxonomies();
		$this->get_views();

		$plugin = plugin_basename( SM_PLUGIN_FILE );
		$old_plugin = 'smart-manager/smart-manager.php';
		if (is_plugin_active( $old_plugin )) {
			deactivate_plugins( $old_plugin );
			$action_url = "plugins.php?action=activate&plugin=$plugin&plugin_status=all&paged=1";
			$url = wp_nonce_url( $action_url, 'activate-plugin_' . $plugin );
			update_option( 'recently_activated', array ($plugin => time() ) + ( array ) get_option( 'recently_activated' ), 'no' );

			if (headers_sent())
				echo "<meta http-equiv='refresh' content='" . esc_attr( "0;url=plugins.php?deactivate=true&plugin_status=$status&paged=$page" ) . "' />";
			else {
				wp_redirect( str_replace( '&amp;', '&', $url ) );
				exit();
			}
		}
		// Including Scripts for using the wordpress new media manager
		if (version_compare ( $wp_version, '3.5', '>=' )) {
			define ( 'IS_WP35', true);

			if ( !empty( $_GET['page'] ) && ( 'smart-manager' === $_GET['page'] || 'smart-manager-settings' === $_GET['page'] ) ) {
				wp_enqueue_media();
				wp_enqueue_script( 'custom-header' );
				// wp_enqueue_script( 'media-upload' );
			}
		}

		//Flag for handling changes since WP 4.0+
		if (version_compare ( $wp_version, '4.0', '>=' )) {
			define ( 'IS_WP40', true);
		}
		if ( ( defined('SMPRO') ) && ( SMPRO === true ) && ( ( ! empty( $_GET['post_type'] ) ) && ( 'product' === sanitize_text_field( $_GET['post_type'] ) ) && ( ( ! empty( $_GET['page'] ) ) && ( 'product_importer' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) ) ) && ! class_exists( 'Smart_Manager_Pro_Product_Import_CSV' ) && file_exists( ( dirname( SM_PLUGIN_FILE ) ) . '/pro/classes/class-smart-manager-pro-product-import-csv.php' ) ) {
			require_once 'pro/classes/class-smart-manager-pro-product-import-csv.php';
		}
	}

	// Function to handle SM admin notices
	function add_admin_notices() {
		if ( ( ! empty( $_GET['page'] ) ) && ( defined('SMPRO') ) && ( SMPRO === true ) && ( 'sm-storeapps-plugins' === $_GET['page'] || ( ( 'smart-manager' === $_GET['page'] ) && ( ! empty( $_GET['landing-page'] ) && ( 'sm-about' ===  $_GET['landing-page'] ) ) ) ) ) {
			$this->add_feature_notice(
				'ai_based_advanced_search_for_products',
				_x( '<strong>AI-Powered Advanced Product Search!</strong>', 'admin notice description for new feature', 'smart-manager-for-wp-e-commerce' ),
				_x( 'Enable it via <strong>Settings > General Settings > AI Integration</strong>.', 'admin notice instructions for new feature', 'smart-manager-for-wp-e-commerce' )
			);
		}
		if( !( !empty( $_GET['page'] ) && ( 'smart-manager' === $_GET['page'] ) ) ) {
			return;
		}

		if (SMPRO === false) {
			$this->add_promo_notices();
		}
	}

	// Function to handle SM In App Promo
	function add_promo_notices() {

		if ( !empty($_GET['page']) && ( 'smart-manager' === $_GET['page'] ) ) {

			$sm_dismiss_admin_notice = '';
			$sm_promo_msg = '';

			$sm_lite_activation_date = get_option( 'sm_lite_activation_date', false );
			$timezone_format = _x('Y-m-d H:i:s', 'timezone date format');
			$current_wp_date = date_i18n($timezone_format);

			if ( $sm_lite_activation_date === false ) {
				$sm_lite_activation_date = $current_wp_date;
				add_option('sm_lite_activation_date',$sm_lite_activation_date);
				add_option('_sm_update_418_date',$sm_lite_activation_date);
			} else {
				$sm_lite_activation_date = get_option( '_sm_update_418_date', false );
				if( false === $sm_lite_activation_date ) {
					$sm_lite_activation_date = $current_wp_date;
					add_option('_sm_update_418_date',$sm_lite_activation_date);
				}
			}

			$date_diff = floor(( strtotime($current_wp_date) - strtotime( $sm_lite_activation_date ) ) / (3600 * 24) );

			$is_pro_available = $this->is_pro_available();

			if ( 'smart-manager' === $_GET['page'] && $is_pro_available === false && ( ! defined('SA_OFFER_VISIBLE') || ( defined('SA_OFFER_VISIBLE') && SA_OFFER_VISIBLE === false ) ) ) {

				$sm_inline_update_count = get_option( 'sm_inline_update_count', 0 );
				$sm_current_user_display_name = self::sm_get_current_user_display_name();
				if( ( empty( $sm_current_user_display_name ) ) ) return;

				if( false !== get_option( 'sm_dismiss_admin_notice', false ) ) {
					delete_option( 'sm_dismiss_admin_notice' );
				}

				echo '<style type="text/css">
					.sm_design_notice {
						display: none;
						width: 50%;
						background-color: rgb(204 251 241 / 82%) !important;
						margin-top: 1em !important;
						margin-bottom: 1em !important;
						padding: 1em;
						box-shadow: 0 0 7px 0 rgba(0, 0, 0, .2);
						font-size: 1.1em;
						// border: 0.15rem solid #5850ec;
						margin: 0 auto;
						text-align: center;
						border-bottom-right-radius: 0.25rem;
						border-bottom-left-radius: 0.25rem;
						border-top: 4px solid #508991 !important;
					}
					.sm_main_headline {
						font-size: 1.7em;
						color: rgb(55 65 81);
						opacity: 0.9;
					}
					.sm_main_headline .dashicons.dashicons-awards {
						font-size: 3em;
						color: #508991;
						width: unset;
						line-height: 3rem;
						margin-right: 0.1em;
					}
					.sm_sub_headline {
						font-size: 1.2em;
						color: rgb(55 65 81);
						line-height: 1.3em;
						opacity: 0.8;
					}
				</style>';
				$man_hours_data = self::sm_get_man_hours_data();
				if( ( ! empty( $man_hours_data ) ) && ( is_array( $man_hours_data ) ) && ( ! empty( $man_hours_data['display_man_hours'] ) )  ){
					echo self::sm_get_man_hours_html( $man_hours_data, $sm_current_user_display_name );
				}else{
					echo '<div class="sm_design_notice">
						<div class="sm_container">
							<div class="sm_main_headline"><span class="dashicons dashicons-awards"></span><span>'. ( ( self::show_halloween_offer() ) ? sprintf(
								/* translators: %1$s: current user display name %2$s: HTML of Pro price discount */
								__( 'Hey %1$s, grab your %2$s Halloween discount!', 'smart-manager-for-wp-e-commerce' ),
								$sm_current_user_display_name,
								'<span style="font-weight: bold;font-size: 2rem;color: rgb(20 184 166);color: #508991;color: rgb(55 65 81);">'. __( "25% off", "smart-manager-for-wp-e-commerce" ) .'</span>' ) : sprintf(
								/* translators: %1$s: current user display name %2$s: HTML of Pro price discount */
									__( 'Hey %1$s, you just unlocked %2$s on Smart Manager Pro!', 'smart-manager-for-wp-e-commerce' ), $sm_current_user_display_name,
									'<span style="font-weight: bold;font-size: 2rem;color: rgb(20 184 166);color: #508991;color: rgb(55 65 81);">'. __( "25% off", "smart-manager-for-wp-e-commerce" ) .'</span>' ) ) .'</span></div>
							<div class="sm_sub_headline" style="margin: 0.75rem 0 0 .5em !important;">' . sprintf(
								/* translators: %s: pricing page link */
								__( '%s to check Smart Manager Pro features/benefits and claim your discount.', 'smart-manager-for-wp-e-commerce' ), '<a style="color: rgb(55 65 81);" href="'. admin_url( 'admin.php?page=smart-manager-pricing' ) .'" target="_blank">' . __( 'Click here', 'smart-manager-for-wp-e-commerce' ) . '</a>' ) .'</div>
						</div>
					</div>';
				}
			}
		}
	}

	// Function to dequeue unwanted scripts on Smart Manager page.
	public function sa_sm_dequeue_scripts() {
		global $wp_scripts;
		if (  is_admin() && !empty( $_GET['page'] ) && ( 'smart-manager' === $_GET['page'] || 'smart-manager-settings' === $_GET['page'] ) ) {
			$dequeue_handles = array( 'wpml-tm-progressbar', 'wpml-tm-scripts', 'toolset-utils', 'elex_selectwoo_js', 'adl-bootstrap-js' );
			if ( is_plugin_active( 'addify-product-labels-and-stickers/class-af_wcbm_main.php' ) && ( is_array( $dequeue_handles ) ) ) { // Compat for 'Product Labels and Stickers' plugin.
				array_push( $dequeue_handles, 'cpt_badge_managment_select_js' );
			}
			foreach( $wp_scripts->registered as $script ) {
				$handle = $script->handle;
				if( false !== stripos($handle, 'select2') || false !== in_array( $handle, $dequeue_handles ) ){
					if ( wp_script_is( $handle ) ) {
						wp_dequeue_script( $handle );
						wp_deregister_script( $handle );
					}
				}
			}
		}
	}

	public function enqueue_admin_scripts() {

		global $wp_version, $wpdb, $current_user;

		$registered_scripts = array();

		if( !empty( $_GET['landing-page'] ) || !( !empty( $_GET['page'] ) && 'smart-manager' === $_GET['page'] ) ) {
			return;
		}

		if ( !wp_script_is( 'jquery' ) ) {
			wp_enqueue_script( 'jquery' );
		}

		if ( !wp_script_is( 'underscore' ) ) {
			wp_enqueue_script( 'underscore' );
		}

		if ( function_exists('wp_enqueue_editor') ) {
			wp_enqueue_editor();
		}

		$deps = array('jquery', 'jquery-ui-core' , 'jquery-ui-widget' , 'jquery-ui-accordion' , 'jquery-ui-autocomplete' , 'jquery-ui-button' , 'jquery-ui-datepicker' ,
						'jquery-ui-dialog' , 'jquery-ui-draggable' , 'jquery-ui-droppable' , 'jquery-ui-menu' , 'jquery-ui-mouse' , 'jquery-ui-position' , 'jquery-ui-progressbar'
						, 'jquery-ui-selectable' , 'jquery-ui-resizable' , 'jquery-ui-sortable' , 'jquery-ui-slider' , 'jquery-ui-tooltip' ,'jquery-ui-tabs' , 'jquery-ui-spinner' ,
						'jquery-effects-core' , 'jquery-effects-blind' , 'jquery-effects-bounce' , 'jquery-effects-clip' , 'jquery-effects-drop' ,
						'jquery-effects-explode' , 'jquery-effects-fade' , 'jquery-effects-fold' , 'jquery-effects-highlight' , 'jquery-effects-pulsate' , 'jquery-effects-scale' ,
						'jquery-effects-shake' , 'jquery-effects-slide' , 'jquery-effects-transfer', 'underscore');

		wp_register_script ( 'sm_select2', plugins_url ( '/assets/js/select2/select2.full.min.js', SM_PLUGIN_FILE ), $deps, '4.0.5' );
		wp_enqueue_script( 'sm_select2' );
		wp_register_script ( 'sm_mithril', plugins_url ( '/common-core/assets/js/mithril/mithril.min.js', SM_PLUGIN_FILE ), $deps, $this->version );
		wp_register_script ( 'sm_search_styles', plugins_url ( '/common-core/assets/js/styles.js', SM_PLUGIN_FILE ), array( 'sm_mithril' ), $this->version );
		wp_register_script ( 'sm_dashboard_js', plugins_url ( '/assets/js/admin.js', SM_PLUGIN_FILE ), array( 'sm_search_styles', 'wp-i18n'), $this->version );
		$last_reg_script = 'sm_mithril';
		//Code for loading custom js automatically
		$custom_lib_js = array();
		$js_paths       = array(
			'/assets/js/*/*.js',
			'/common-core/assets/js/*/*.js',
		);
		if ( true === SMPRO && ! empty( $js_paths ) && is_array( $js_paths ) ) {
			$js_paths = array_merge(
				$js_paths,
				array(
					'/pro/assets/js/*/*.js',
					'/pro/common-pro/assets/js/*/*.js',
				)
			);
		}
		if ( ! empty( $js_paths ) && is_array( $js_paths ) ) {
			foreach ( $js_paths as $path ) {
				$files = glob( $this->plugin_path . $path );

				if ( empty( $files ) || ! is_array( $files ) ) {
					continue;
				}

				$custom_lib_js = array_merge( $custom_lib_js, $files );
			}
		}
		if ( ! empty( $custom_lib_js ) ) {
			$index = 0;

			foreach ( $custom_lib_js as $file ) {
				$folder_path = substr( $file, 0, strrpos( $file, '/', -3 ) );
				$folder_name = substr( $folder_path, strrpos( $folder_path, '/', -3 ) + 1 );

				if ( 'mithril' === $folder_name ) {
					continue;
				}

				$pro_flag    = ( true === SMPRO && false !== strpos( $file, '/pro/' ) ) ? 'pro' : '';
				$folder_flag = '';

				if ( true === SMPRO && false !== strpos( $file, '/pro/' ) ) {
					$folder_flag = ( false !== strpos( $file, '/pro/common-pro/' ) ) ? 'pro/common-pro' : 'pro';
				} elseif ( false !== strpos( $file, '/common-core/' ) ) {
					$folder_flag = 'common-core';
				}

				$file_nm = 'sm_' . ( ! empty( $pro_flag ) ? $pro_flag . '_' : '' ) . 'custom_' . preg_replace( '/[\s\-.]/', '_', substr( $file, strrpos( $file, '/', -3 ) + 1 ) );

				if ( 'sm_pro_custom_smart_manager_js' === $file_nm ) {
					continue;
				}

				$script_path = ( $folder_flag ? '/' . $folder_flag : '' ) . '/assets/js/' . $folder_name . '/' . substr( $file, strrpos( $file, '/', -3 ) + 1 );
				wp_register_script( $file_nm, plugins_url( $script_path, SM_PLUGIN_FILE ), array( $last_reg_script ), $this->version );

				$last_reg_script = $file_nm;
				$index++;
			}
		}
		$sm_common_core_base = 'sm_custom_core_base_js';
		wp_register_script(
			$sm_common_core_base,
			plugins_url( '/common-core/assets/js/core-base.js', SM_PLUGIN_FILE ),
			array ($last_reg_script),
			$this->version,
			true
		);
		wp_register_script ( 'sm_custom_smart_manager_js', plugins_url ( '/assets/js/smart-manager.js', SM_PLUGIN_FILE ), array( $last_reg_script, $sm_common_core_base ), $this->version );
		$last_reg_script = 'sm_custom_smart_manager_js';

		if( SMPRO === true ) {
			wp_register_script ( 'sm_pro_custom_smart_manager_js', plugins_url ( '/pro/assets/js/smart-manager.js', SM_PLUGIN_FILE ), array ($last_reg_script), $this->version );
			$last_reg_script = 'sm_pro_custom_smart_manager_js';
			//NLP JS.
			wp_register_script ( 'sm_pro_nl_converter', plugins_url ( '/pro/assets/js/smart-manager-nl-converter.js', SM_PLUGIN_FILE ), array ($last_reg_script), $this->version );
			$last_reg_script = 'sm_pro_nl_converter';
			wp_register_script ( 'sm_pro_url_parser', plugins_url ( '/pro/assets/js/smart-manager-url-parser.js', SM_PLUGIN_FILE ), array( $last_reg_script, $sm_common_core_base ), $this->version );
			$last_reg_script = 'sm_pro_url_parser';
		}

		// Code for loading custom js automatically
		$custom_js = array();
		$js_paths   = array(
			'/common-core/assets/js/*.js',
			'/assets/js/*.js'
		);
		if ( is_array( $js_paths ) && ! empty( $js_paths ) ) {
			foreach ( $js_paths as $path ) {
				$files = glob( $this->plugin_path . $path );
				if ( empty( $files ) || ! is_array( $files ) ) {
					continue;
				}
				$custom_js = array_merge( $custom_js, $files );
			}
		}
		if ( ! empty( $custom_js ) && is_array( $custom_js ) ) {
			$index = 0;

			foreach ( $custom_js as $file ) {
				if ( empty( $file ) ) {
					continue;
				}

				$file_nm = 'sm_custom_' . preg_replace( '/[\s\-.]/', '_', substr( $file, strrpos( $file, '/', -3 ) + 1 ) );
				array_push( $registered_scripts, $file_nm );

				if ( in_array( $file_nm, array( 'sm_custom_smart_manager_js', 'sm_custom_styles_js', 'sm_custom_admin_js', 'sm_custom_core_base_js' ), true ) ) {
					continue;
				}

				$folder_flag = false !== strpos( $file, '/common-core/' ) ? '/common-core' : '';

				if ( 0 === $index && empty( $last_reg_script ) ) {
					wp_register_script( $file_nm, plugins_url( $folder_flag . '/assets/js/' . substr( $file, strrpos( $file, '/', -3 ) + 1 ), SM_PLUGIN_FILE ), array( 'sm_custom_smart_manager_js' ), $this->version );
				} else {
					wp_register_script( $file_nm, plugins_url( $folder_flag . '/assets/js/' . substr( $file, strrpos( $file, '/', -3 ) + 1 ), SM_PLUGIN_FILE ), array( $last_reg_script ), $this->version );
				}

				$last_reg_script = $file_nm;
				$index++;
			}
		}
		//Updating The Files Recieved in SM Beta
		$successful = ($this->updater * $this->upgrade)/$this->updater;

		// Code for loading custom js for PRO automatically
		if ( true === SMPRO ) {
			$custom_js = glob( $this->plugin_path . '/pro/assets/js/*.js' );
			$custom_js = array_merge( $custom_js, glob( $this->plugin_path . '/pro/common-pro/assets/js/*.js' ) );
			if ( ! empty( $custom_js ) && is_array( $custom_js ) ) {
				foreach ( $custom_js as $file ) {
					if ( ! is_string( $file ) || empty( $file ) ) {
						continue;
					}
					$file_nm = 'sm_pro_custom_' . preg_replace( '/[\s\-.]/', '_', substr( $file, ( strrpos( $file, '/', -3 ) + 1 ) ) );
					array_push( $registered_scripts, $file_nm );
					if ( 'sm_pro_custom_smart_manager_js' === $file_nm ) {
						continue;
					}
					$folder_flag = '';
					if ( true === SMPRO && false !== strpos( $file, '/pro/' ) ) {
						$folder_flag = ( false !== strpos( $file, '/pro/common-pro/' ) ) ? '/pro/common-pro' : '/pro';
					} elseif ( false !== strpos( $file, '/common-core/' ) ) {
						$folder_flag = 'common-core';
					}
					if ( ! empty( $folder_flag ) ) {
						wp_register_script(
							$file_nm,
							plugins_url( $folder_flag . '/assets/js/' . substr( $file, ( strrpos( $file, '/', -3 ) + 1 ) ), SM_PLUGIN_FILE ),
							array( $last_reg_script ),
							$this->version
						);
						$last_reg_script = $file_nm;
						$index++;
					}
				}
			}
		}
		( is_callable( array( 'Smart_Manager', 'set_script_translations' ) ) ) ? self::set_script_translations( $registered_scripts ) : '';

		$sm_dashboard_keys = ( !empty( self::$sm_dashboards_final ) ) ? array_keys( self::$sm_dashboards_final ) : array();

		// set the default dashboard
		$search_type = get_transient( 'sa_sm_'.$current_user->user_email.'_search_type' );

		$recent_dashboards = get_option('sm_wp_dashboard_post_type_'.get_current_user_id(), false);
		$is_redirect = false;

		if( empty( $recent_dashboards ) ) {
			$recent_dashboards = get_user_meta( get_current_user_id(), 'sa_sm_recent_post_types', true );
			if( empty( $recent_dashboards ) ){
				$key = 'sa_sm_'.$current_user->user_email.'_default_dashboard';
				$recent_dashboards = get_transient( $key );
				if( ! empty( $recent_dashboards ) ) {
					update_user_meta( get_current_user_id(), 'sa_sm_recent_post_types', array( $recent_dashboards ) );
					delete_transient( $key );
				}
			}
		} else {
			$is_redirect = true;
			sa_sm_update_recent_dashboards( 'post_types', $recent_dashboards );
			$recent_dashboards = get_user_meta( get_current_user_id(), 'sa_sm_recent_post_types', true );
			delete_option('sm_wp_dashboard_post_type_'.get_current_user_id());
		}

		if( ! empty( $recent_dashboards ) && ! is_array( $recent_dashboards ) ){
			$recent_dashboards = array( $recent_dashboards );
		}

		$recent_dashboards = ( ! empty( $recent_dashboards ) && ! empty( $sm_dashboard_keys ) ) ? array_values( array_intersect( $recent_dashboards, $sm_dashboard_keys ) ) : array(); //Added to give access to only accessible dashboards

		// Code to set default if recent dashboards is blank
		if( empty( $recent_dashboards ) && ! empty( $sm_dashboard_keys ) ){
			$recent_dashboards = array( (is_plugin_active( 'woocommerce/woocommerce.php' ) && !empty( self::$sm_dashboards_final['product'] ) ) ? 'product' : $sm_dashboard_keys[0] );
		}

		$recent_dashboard_type = get_user_meta( get_current_user_id(), 'sa_sm_recent_dashboard_type', true );
		$recent_dashboard_type = ( empty( $recent_dashboards ) && 'post_type' === $recent_dashboard_type ) ? '' : $recent_dashboard_type;

		$recent_views = get_option('sm_wp_dashboard_view_'.get_current_user_id(), false);

		if( empty( $recent_views ) ) {
			$recent_views = get_user_meta( get_current_user_id(), 'sa_sm_recent_views', true );
		} else {
			sa_sm_update_recent_dashboards( 'views', $recent_views );
			$recent_views = get_user_meta( get_current_user_id(), 'sa_sm_recent_views', true );
			delete_option('sm_wp_dashboard_view_'.get_current_user_id());
			$recent_dashboard_type = 'view';
		}

		if( ! empty( $recent_views ) && ! is_array( $recent_views ) ){
			$recent_views = array( $recent_views );
		}

		$recent_views = ( ! empty( $recent_views ) && ! empty( $this->all_views ) ) ? array_values( array_intersect( $recent_views, $this->all_views ) ) : array();
		$recent_views = ( empty( $recent_views ) && empty( $recent_dashboards ) && ! empty( $this->all_views ) && is_array( $this->all_views ) ) ? array( $this->all_views[0] ) : $recent_views;

		$recent_dashboard_type = ( empty( $recent_views ) && 'view' === $recent_dashboard_type ) ? '' : $recent_dashboard_type;
		$recent_dashboard_type = ( empty( $recent_dashboard_type ) && ! empty( $recent_views ) && empty( $recent_dashboards ) ) ? 'view' : $recent_dashboard_type;

		//code for handling recent taxonomy dashboards
		$recent_taxonomy_dashboards = get_user_meta( get_current_user_id(), 'sa_sm_recent_taxonomies', true );
		if( ! empty( $recent_taxonomy_dashboards ) && ! is_array( $recent_taxonomy_dashboards ) ){
			$recent_taxonomy_dashboards = array( $recent_taxonomy_dashboards );
		}

		$recent_taxonomy_dashboards = ( ! empty( $recent_taxonomy_dashboards ) && ! empty( self::$taxonomy_dashboards ) ) ? array_values( array_intersect( $recent_taxonomy_dashboards, array_keys( self::$taxonomy_dashboards ) ) ) : array();
		$recent_taxonomy_dashboards = ( empty( $recent_taxonomy_dashboards ) && empty( $recent_views ) && empty( $recent_dashboards ) && ! empty( self::$taxonomy_dashboards ) && is_array( self::$taxonomy_dashboards ) ) ? array( array_keys( self::$taxonomy_dashboards )[0] ) : $recent_taxonomy_dashboards;

		$recent_dashboard_type = ( empty( $recent_taxonomy_dashboards ) && 'taxonomy' === $recent_dashboard_type ) ? '' : $recent_dashboard_type;
		$recent_dashboard_type = ( empty( $recent_dashboard_type ) && ! empty( $recent_taxonomy_dashboards )  && empty( $recent_views ) && empty( $recent_dashboards ) ) ? 'taxonomy' : $recent_dashboard_type;

		if( empty( $recent_dashboard_type ) ){
			$recent_dashboard_type = 'post_type';
			if( ! empty( $recent_taxonomy_dashboards ) ){
				$recent_dashboard_type = 'taxonomy';
			} else if( ! empty( $recent_views ) ){
				$recent_dashboard_type = 'view';
			}
		}

		//Updating The Files Recieved in SM Beta
		$deleted_successful = ( ($this->dupdater * $this->dupgrade)/$this->dupdater ) * 2;

		self::$sm_dashboards_final ['sm_nonce'] = wp_create_nonce( 'sa-sm-manager-security' );
		$batch_background_process = false;
		$background_process_name = '';

		if( SMPRO === true ) {
			$batch_background_process = get_site_option('sm_beta_background_process_status', false);
			$background_process_params = get_transient('sm_beta_background_process_params');
			$background_process_name = (!empty($background_process_params['process_name'])) ? $background_process_params['process_name'] : '';
		}

		$lite_dashboards = array('product', 'shop_order', 'shop_coupon', 'post', 'product_stock_log');

		$trash_enabled = true;
		if( defined('EMPTY_TRASH_DAYS') ){
			if( 0 == EMPTY_TRASH_DAYS ) {
				$trash_enabled = false;
			}
		}

		// Filter for disabling the 'Move to trash' and 'Delete Permanently' functionalities.
		$disable_trash_and_delete_permanently = apply_filters( 'sm_disable_trash_and_delete_permanently', false );
		$trash_and_delete_permanently_disable_message = apply_filters( 'sm_trash_and_delete_permanently_disable_message', __( 'This functionality has been disabled. Please contact store administrator for enabling the same.', 'smart-manager-for-wp-e-commerce' ) );

		// Filter to disable editing some columns.
		$disable_col_edit = apply_filters( 'sm_disable_col_edit', false );
		$col_edit_disable_message = apply_filters( 'sm_col_edit_disable_message', _x( 'This column edit has been disabled. Please contact store administrator for enabling the same.', 'Column edit disable message', 'smart-manager-for-wp-e-commerce' ) );

		$sm_beta_params = array(
							'sm_dashboards' => json_encode(self::$sm_dashboards_final),
							'sm_views' => json_encode($this->sm_accessible_views),
							'sm_owned_views' => json_encode( $this->sm_owned_views ),
							'sm_public_views' => json_encode( $this->sm_public_views ),
							'sm_view_post_types' => json_encode( $this->sm_view_post_types ),
							'sm_saved_searches' => json_encode( $this->sm_saved_searches ),
							'recent_dashboards' => json_encode( $recent_dashboards ),
							'recent_views' => json_encode( $recent_views ),
							'recent_dashboard_type' => $recent_dashboard_type,
							'sm_dashboards_public' => json_encode(self::$sm_public_dashboards),
							'taxonomy_dashboards' => wp_json_encode( self::$taxonomy_dashboards ),
							'all_taxonomy_dashboards' => SM_ALL_TAXONOMY_DASHBOARDS,
							'recent_taxonomy_dashboards' => json_encode( $recent_taxonomy_dashboards ),
							'SM_IS_WOO36' => self::$sm_is_woo36,
							'SM_IS_WOO30' => self::$sm_is_woo30,
							'SM_IS_WOO22' => self::$sm_is_woo22,
							'SM_IS_WOO21' => self::$sm_is_woo21,
							'SM_BETA_PRO' => SMPRO,
							'SM_APP_ADMIN_URL' => SM_APP_ADMIN_URL,
							'record_per_page' => Smart_Manager_Settings::get( 'per_page_record_limit' ),
							'sm_admin_email' => apply_filters( 'sm_admin_email', get_option( 'admin_email' ) ),
							'batch_background_process' => $batch_background_process,
							'background_process_name' => $background_process_name,
							'updated_successful' => $successful,
							'deleted_successful' => $deleted_successful,
							'updated_msg' => $this->update_msg.' more',
							'success_msg' => $this->success_msg,
							'lite_dashboards' => json_encode($lite_dashboards),
							'search_type' => ( ( !empty( $search_type ) ) ? $search_type : 'simple' ),
							'wpdb_prefix' => $wpdb->prefix,
							'trashEnabled' => $trash_enabled,
							'background_process_running_message' => __( 'In the meanwhile, you can use Smart Manager. But before using actions like ', 'smart-manager-for-wp-e-commerce') .' <strong>'. __( 'Bulk Edit', 'smart-manager-for-wp-e-commerce') .'</strong>/ <strong>'. __('Duplicate Records', 'smart-manager-for-wp-e-commerce') .'</strong>/ <strong>'. __( 'Delete Records', 'smart-manager-for-wp-e-commerce') .'</strong>/ <strong>'. __( 'Undo Tasks', 'smart-manager-for-wp-e-commerce') .'</strong>/ <strong>'. __( 'Delete Tasks', 'smart-manager-for-wp-e-commerce') .'</strong>/ <strong>'. __( 'Export CSV', 'smart-manager-for-wp-e-commerce') .'</strong>, '. __('you will have to wait for the current background process to finish.', 'smart-manager-for-wp-e-commerce' ),
							'trashAndDeletePermanently' => array( 'disable' => $disable_trash_and_delete_permanently, 'error_message' => $trash_and_delete_permanently_disable_message ),
							'colEditDisableMessage' => array( 'disable' => $disable_col_edit, 'error_message' => $col_edit_disable_message ),
							'forceCollapseAdminMenu' => ( 'no' === Smart_Manager_Settings::get( 'wp_force_collapse_admin_menu' ) ) ? 0 : 1,
							'rowHeight' => Smart_Manager_Settings::get( 'grid_row_height' ),
							'defaultImagePlaceholder' => SM_IMG_URL.'image-placeholder.png',
							'showTasksTitleModal' => ( 'no' === apply_filters( 'sm_show_tasks_title_modal', Smart_Manager_Settings::get( 'show_tasks_title_modal' ) ) ) ? 0 : 1,
							'useNumberFieldForNumericCols' => ( 'no' === apply_filters( 'sm_use_number_field_for_numeric_cols', Smart_Manager_Settings::get( 'use_number_field_for_numeric_cols' ) ) ) ? 0 : 1,
							'WCProductImportURL' => admin_url( 'edit.php?post_type=product&page=product_importer' ),
							'allSettings' => Smart_Manager_Settings::get(),
							'useDatePickerForDateTimeOrDateCols' => ( 'no' === apply_filters( 'sm_use_date_picker_for_date_or_datetime_cols', Smart_Manager_Settings::get( 'use_date_picker_for_date_or_datetime_cols' ) ) ) ? 0 : 1,
							'SM_IS_WOO79' => ( ! empty( self::$sm_is_woo79 ) ) ? 'true' : 'false',
							'isSAOfferVisible' => SA_OFFER_VISIBLE,
							'isSAOfferBannerVisible' => ( 'yes' === get_option( 'sa_sm_offer_bfcm_2024', 'yes' ) ) ? true : false,
							'scheduled_action_admin_url' => self::get_scheduled_actions_search_url('storeapps_sm_scheduled_actions'),
							'scheduled_export_actions_admin_url' => self::get_scheduled_actions_search_url('storeapps_smart_manager_scheduled_export_actions'),
							'is_admin' => ( 'administrator' === self::get_current_user_role() ) ? true : false,
							'manHoursData' => self::sm_get_man_hours_data(),
							'userName' => self::sm_get_current_user_display_name(),
							'orderStatuses' => ( function_exists( 'wc_get_order_statuses' ) ) ? wc_get_order_statuses() : array(),
							'isSubscriptionPluginActive' => ( class_exists( 'WC_Subscriptions' ) ) ? true : false,
							'subscriptionsAcceptManualRenewals' => ( get_option( 'woocommerce_subscriptions_accept_manual_renewals', 'no' ) === 'yes' ) ? true : false,
							'subscriptionsExist' => ( class_exists( 'WC_Subscriptions' ) && function_exists( 'wcs_do_subscriptions_exist' ) ) ? wcs_do_subscriptions_exist() : false,
							'isStripeGatewayActive' => sm_is_stripe_gateway_active(),
							'is_ai_integration_enabled' => self::is_ai_integration_enabled()
						);

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		if ( ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins ) ) ) {
			$sm_beta_params['woo_price_decimal_places'] = wc_get_price_decimals();
			$sm_beta_params['woo_price_decimal_separator'] = wc_get_price_decimal_separator();
		}
		//WPML language params.
		if ( ( ! empty( $_GET['lang'] ) ) && ( class_exists( 'SitePress' ) ) ) {
			$sm_beta_params['lang'] = sanitize_text_field( wp_unslash( $_GET['lang'] ) );
		}

		wp_localize_script( 'sm_custom_smart_manager_js', 'sm_beta_params', $sm_beta_params );

		wp_enqueue_script( $last_reg_script );

		// Including Scripts for using the wordpress new media manager
		if (version_compare ( $wp_version, '3.5', '>=' )) {
			if ( isset($_GET['page']) && ($_GET['page'] == "smart-manager" || $_GET['page'] == "smart-manager-settings")) {
				wp_enqueue_media();
				wp_enqueue_script( 'custom-header' );
			}
		}

		do_action('smart_manager_enqueue_scripts'); //action for hooking any scripts
	}

	function enqueue_admin_styles() {
		$this->enqueue_admin_footer_style();
		if( !empty( $_GET['landing-page'] ) || !( !empty( $_GET['page'] ) && 'smart-manager' === $_GET['page'] ) ) {
			return;
		}

		wp_enqueue_style( 'wp-jquery-ui-dialog' );

		//Registering styles for visualsearch lib.
		wp_register_style ( 'sm_search', plugins_url ( '/common-core/assets/css/styles.css', SM_PLUGIN_FILE ), array(), $this->version );

		//Code for loading custom js for PRO automatically
		$custom_css = array();
		$css_paths = array(
			'/assets/css/*/*.css',
			'/common-core/assets/css/*/*.css',
		);

		if ( ( true === SMPRO ) && ( ! empty( $css_paths ) ) && is_array( $css_paths ) ) {
			$css_paths = array_merge(
				$css_paths,
				array(
					'/pro/assets/css/*.css',
					'/pro/assets/css/*/*.css',
					'/pro/common-pro/assets/css/*.css'
				)
			);
		}

		if ( ( ! empty( $css_paths ) ) && is_array( $css_paths ) ) {
			foreach ( $css_paths as $path ) {
				$files = glob( $this->plugin_path . $path );
				if ( ( empty( $files ) ) || ( ! is_array( $files ) ) ) {
					continue;
				}
				$custom_css = array_merge( $custom_css, $files );
			}
		}

		if ( ( ! empty( $custom_css ) ) && is_array( $custom_css ) ) {
			$index           = 0;
			$last_reg_script = 'sm_search';
			$folder_flag = '';
			$folder_name = '';
			foreach ( $custom_css as $file ) {
				$folder_path = substr( $file, 0, strrpos( $file, '/', -3 ) );
				$folder_name = substr( $folder_path, strrpos( $folder_path, '/', -3 ) + 1 );
				$pro_flag    = ( ( true === SMPRO ) && ( false !== strpos( $file, '/pro/' ) ) ) ? 'pro' : '';
				if ( ( true === SMPRO ) && ( false !== strpos( $file, '/pro/' ) ) ) {
					$folder_flag = ( false !== strpos( $file, '/pro/common-pro/' ) ) ? 'pro/common-pro' : 'pro';
				} elseif ( false !== strpos( $file, '/common-core/' ) ) {
					$folder_flag = 'common-core';
				}
				$file_nm = 'sm_' . ( ! empty( $pro_flag ) ? $pro_flag . '_' : '' ) . 'custom_' . preg_replace( '/[\s\-.]/', '_', substr( $file, strrpos( $file, '/', -3 ) + 1 ) );
				if ( in_array( $file_nm, array( 'sm_pro_custom_smart_manager_css', 'sm_pro_custom_styles_css', 'sm_pro_custom_admin_css' ) ) ) {
					continue;
				}
				$folder_name = ( 'sm_pro_custom_sa_manager_css' === $file_nm ) ? "" : $folder_name;
				$script_path = ( $folder_flag ? '/' . $folder_flag : '' ) . '/assets/css/' . $folder_name . '/' . substr( $file, strrpos( $file, '/', -3 ) + 1 );
				wp_register_style( $file_nm, plugins_url( $script_path, SM_PLUGIN_FILE ), array( $last_reg_script ), $this->version );
				$last_reg_script = $file_nm;
				$index++;
			}
		}

		wp_register_style ( 'sm_main_style', plugins_url ( '/assets/css/smart-manager.css', SM_PLUGIN_FILE ), array($last_reg_script), $this->version );
		$last_reg_script = 'sm_main_style';

		if( SMPRO === true ) {
			wp_register_style ( 'sm_pro_main_style', plugins_url ( '/pro/assets/css/smart-manager.css', SM_PLUGIN_FILE ), array($last_reg_script), $this->version );
			$last_reg_script = 'sm_pro_main_style';
		}

		wp_enqueue_style( $last_reg_script );

		do_action('smart_manager_enqueue_scripts');	//action for hooking any styles
	}

	function get_latest_version() {
		$sm_plugin_info = get_site_transient( 'update_plugins' );
		$latest_version = isset( $sm_plugin_info->response [SM_PLUGIN_BASE_NM]->new_version ) ? $sm_plugin_info->response [SM_PLUGIN_BASE_NM]->new_version : '';
		return $latest_version;
	}

	function get_user_sm_version() {
		$sm_plugin_info = get_plugins();
		$user_version = $sm_plugin_info [SM_PLUGIN_BASE_NM] ['Version'];
		return $user_version;
	}

	function is_pro_updated() {
		$user_version = $this->get_user_sm_version();
		$latest_version = $this->get_latest_version();
		return version_compare( $user_version, $latest_version, '>=' );
	}

	// function for removing the Help Tab and hiding admin notices except SM admin notices.
	function remove_help_tab_and_hiding_admin_notices(){
		// condition to remove the help tab only from SM pages.
		if ( ! empty( $_GET['page'] ) && 'smart-manager' === $_GET['page'] ) {
			$screen = get_current_screen();
			$screen->remove_help_tabs();
			// hiding admin notices except SM admin notices.
			?>
				<style type="text/css">
					.notice {
						display: none !important;
					}
					.<?php echo esc_html( self::$sku . '-notice' ); ?> {
						display: block !important;
					}
				</style>
			<?php
		}
		if ( ( defined( 'SMPRO' ) && true === SMPRO ) && ! empty( $this->show_pricing_page ) ) {
			?>
				<style type="text/css">
					.toplevel_page_smart-manager > .wp-submenu > li:nth-child(3){
						display: none;
					}
				</style>
			<?php
		}
		if( ! empty( $_GET['tab'] ) && 'upgrade' === $_GET['tab'] ){
			global $submenu_file;
			$submenu_file = 'smart-manager-pricing';
		}
	}

	//Function to re-update to Pro in case of Pro to Lite
	function update_to_pro() {
		// Check nonce for security.
		check_ajax_referer( 'sm_update_to_pro', 'security' );
		// Check if user has the required capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}
		$sm_download_url = $this->get_pro_download_url();

		if ( ! empty( $sm_download_url ) ) {

			include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

			$skin     = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );

			$result = $upgrader->run( array(
				'package'           => $sm_download_url,
				'destination'       => WP_PLUGIN_DIR,
				'clear_destination' => true,
				'clear_working'     => true,
				'hook_extra'        => array(
											'plugin' => 'smart-manager-for-wp-e-commerce/smart-manager.php',
											'type'   => 'plugin',
											'action' => 'update',
										),
			) );

			if( !empty($result) ) {
				die('Success');
			} else {
				die('Failed');
			}

		}
	}

	// Function to show upgrade notifications
	function show_upgrade_notifications() {

		?>
			<script type="text/javascript">

					jQuery(document).ready(function(){
						var current_url = "<?php echo admin_url('admin.php?&page=smart-manager'); ?>";
						jQuery('.request-filesystem-credentials-dialog-content').find('form').attr('action',current_url+'&action=sm_update_to_pro');

						jQuery('.request-filesystem-credentials-dialog-content').find('form').on('submit', function(e){
							e.preventDefault();

							jQuery( '#request-filesystem-credentials-dialog' ).hide();
							jQuery( 'body' ).removeClass( 'modal-open' );

							let params = jQuery(this).serializeArray();
							params.security =  '<?php echo esc_attr( wp_create_nonce( 'sm_update_to_pro' ) ); ?>';
							setTimeout(function(){ jQuery.ajax({
														type : 'POST',
														url: (ajaxurl.indexOf('?') !== -1) ? ajaxurl + '&action=sm_update_to_pro' : ajaxurl + '?action=sm_update_to_pro',
														dataType:"text",
														async: false,
														data: params,
														success: function(response) {
															jQuery('#sm_pro_to_lite_msg').removeClass('notice-error').addClass('notice-success').html('<div style="margin:.5em 0;"><?php echo __( 'Upgraded successfully!!!', SM_TEXT_DOMAIN ); ?></div>');

															// Remove navigation prompt
															window.onbeforeunload = null;

															setTimeout(function(){ window.location.replace(current_url); }, 3000);
														}
													});
								}, 1000);

						});
					});

					jQuery(document).on('click','#sm_update_to_pro_link',function(e){
						e.preventDefault();

						var current_url = "<?php echo admin_url('admin.php?&page=smart-manager'); ?>";
						var $modal = jQuery( '#request-filesystem-credentials-dialog' );
						jQuery('#sm_pro_to_lite_msg_hidden').html(jQuery('#sm_pro_to_lite_msg').html());
						jQuery('#sm_pro_to_lite_msg').html('<div style="margin:.5em 0;"><span style="margin-right:6px;color:#f56e28;animation:rotation 2s infinite linear;" class="dashicons dashicons-update"></span><?php echo __( 'Upgrading to Smart Manager Pro...', SM_TEXT_DOMAIN ); ?></div>');

						// Enable navigation prompt
						window.onbeforeunload = function() {
							return true;
						};

						setTimeout(function(){ jQuery.ajax({
									type : 'POST',
									url: (ajaxurl.indexOf('?') !== -1) ? ajaxurl + '&action=sm_update_to_pro' : ajaxurl + '?action=sm_update_to_pro',
									dataType:"text",
									async: false,
									data: {
										security: '<?php echo esc_attr( wp_create_nonce( 'sm_update_to_pro' ) ); ?>'
									},
									success: function(response) {

										if( response == 'Success' ) {
											jQuery('#sm_pro_to_lite_msg').removeClass('notice-error').addClass('notice-success').html('<div style="margin:.5em 0;"><?php echo __( 'Upgraded successfully!!!', SM_TEXT_DOMAIN ); ?></div>');

											// Remove navigation prompt
											window.onbeforeunload = null;

											setTimeout(function(){ window.location.replace(current_url); }, 3000);
										} else {
											jQuery( 'body' ).addClass( 'modal-open' );
											$modal.show();
											$modal.find( 'input:enabled:first' ).focus();
										}
									}
								});
						}, 1000);

					});

					jQuery(document).on('click', '[data-js-action="close"], .notification-dialog-background',function(e){
						e.preventDefault();

						// Remove navigation prompt
						window.onbeforeunload = null;

						jQuery('#sm_pro_to_lite_msg').html(jQuery('#sm_pro_to_lite_msg_hidden').html());

						jQuery( '#request-filesystem-credentials-dialog' ).hide();
						jQuery( 'body' ).removeClass( 'modal-open' );

					});

		</script>

		<?php
			$is_pro_available = $this->is_pro_available();
			if( $is_pro_available === true ) { ?>

				<div id="sm_pro_to_lite_msg" class="update-message notice inline notice-error notice-alt" style="display:block !important;">
					<p>
						<?php
							printf( ('<b>' . __( 'Oops!', SM_TEXT_DOMAIN ) . '</b> ' . __( 'Seems like your Smart Manager plugin has downgraded to the Lite version. ', SM_TEXT_DOMAIN ) . " " . '<a id="sm_update_to_pro_link" href="">' . " " .__( 'Click here', SM_TEXT_DOMAIN ) . '</a> ')." ".__( 'to', SM_TEXT_DOMAIN )." <b>".__( 'convert it back to the Pro version.', SM_TEXT_DOMAIN )."</b>" );
						?>
					</p>
				</div>
				<div id="sm_pro_to_lite_msg_hidden" style="display:none;"></div>

				<?php

			} else if ( SMPRO === false && get_option('sm_dismiss_admin_notice') == '1') { ?>
					<div id="message" class="updated fade" style="display:block !important;">
						<p> <?php
								printf( ('<b>' . __( 'Important:', SM_TEXT_DOMAIN ) . '</b> ' . __( 'Upgrade to Pro to get features like \'<i>Manage any Custom Post Type</i>\' , \'<i>Bulk Edit</i>\' , \'<i>Export CSV </i>\' , \'<i>Duplicate Products</i>\' &amp; many more...', SM_TEXT_DOMAIN ) . " " . '<br /><a href="%1s" target=_storeapps>' . " " .__( 'Learn more about Pro version', SM_TEXT_DOMAIN ) . '</a> ' . __( 'or take a', SM_TEXT_DOMAIN ) . " " . '<a href="%2s" target=_livedemo>' . " " . __( 'Live Demo', SM_TEXT_DOMAIN ) . '</a>'), 'https://www.storeapps.org/product/smart-manager', 'http://demo.storeapps.org/?demo=sm-woo' );
							?>
						</p>
					</div>
				<?php
			}
	}

	//function for showing the sm page
	function show_console_beta() {

		global $wpdb;

		$latest_version = $this->get_latest_version();
		$is_pro_updated = $this->is_pro_updated();
		$is_pricing_page = ( ! empty( $_GET['tab'] ) && 'upgrade' === $_GET['tab'] ) ? true : false;
		?>
		<div id="sa_manager_main"> </div>
		<?php
			wp_enqueue_script( 'sm_dashboard_js' );
		?>
		<div class="wrap" style="margin: 0!important;">
			<?php if( ! $is_pricing_page ) { ?>
				<style>
					div#TB_window {
						background: lightgrey;
					}
				</style>
				<?php if ( SMPRO === true && function_exists( 'smart_support_ticket_content' ) ) smart_support_ticket_content();  ?>

				<div id="sm_nav_bar" style="margin-bottom:1em;">
					<div class='sm_beta_left'>
						<span class="sm-h2">
						<?php
								echo 'Smart Manager';
								echo ' <sup style="vertical-align: super;background-color: #EC8F1C;background-color:#508991;font-size: 0.7em !important;padding: 2px 3px;border-radius: 2px;font-weight: 600;letter-spacing:0.1em;"><span>'.((SMPRO === true) ? __('PRO', 'smart-manager-for-wp-e-commerce') : __('LITE', 'smart-manager-for-wp-e-commerce')).'</span></sup>';
								$plug_page = '';

						?>
						</span>
					</div>
					<span id="sm_nav_bar_right" style="float: right;"></span>
				</div>
		<?php
			}
			if (! $is_pro_updated) {
				?> <?php
				$admin_url = SM_ADMIN_URL . "plugins.php";
				$update_link = __( 'An upgrade for Smart Manager Pro', 'smart-manager-for-wp-e-commerce' ) . " " . $latest_version . " " . __( 'is available.', 'smart-manager-for-wp-e-commerce' ) . " " . "<a align='right' href=$admin_url>" . __( 'Click to upgrade.', 'smart-manager-for-wp-e-commerce' ) . "</a>";
				$this->display_notice( $update_link );
				?> <?php
			}

			if( is_callable( array( $this, 'show_upgrade_notifications' ) ) ) {
				$this->show_upgrade_notifications();
			}
			if( is_callable( array( $this, 'add_feature_notice' ) ) ) {
				$this->add_feature_notice(
					'ai_based_advanced_search_for_products',
					_x( '<strong>AI-Powered Advanced Product Search!</strong>', 'admin notice description for new feature', 'smart-manager-for-wp-e-commerce' ),
					_x( 'Enable it via <strong>Settings > General Settings > AI Integration</strong>.', 'admin notice instructions for new feature', 'smart-manager-for-wp-e-commerce' )
				);
			}
			if( ! $is_pricing_page ) {
		?>
				<div id="sm_editor_grid" ></div>
				<div id="sm_pagging_bar"></div>
				<div id="sm_inline_dialog"></div>
				<div class="sa-loader-container">
					<div class="sm-loader">
						<div></div>
						<div></div>
						<div></div>
						<div></div>
						<div></div>
						<div></div>
						<div></div>
						<div></div>
					</div>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Set translation script for JS
	 */
	public static function set_script_translations( $handles = array() ) {
		if ( function_exists( 'wp_set_script_translations' ) && ! empty( $handles ) && sizeof( $handles ) > 0 ) {
			foreach( $handles as $handle ){
				wp_set_script_translations( $handle, 'smart-manager-for-wp-e-commerce', plugin_dir_path( __FILE__ ) . 'languages' );
			}
		}
	}

	/**
	 * Smart Manager's Support Form
	 */
	public function smart_manager_support_ticket_content() {

		if ( !( !empty( $_GET['page'] ) && ( 'smart-manager' === $_GET['page'] || 'smart-manager-settings' === $_GET['page'] ) ) ) {
			return;
		}

		if ( !wp_script_is('thickbox') ) {
			if (!function_exists('add_thickbox')) {
				require_once ABSPATH . 'wp-includes/general-template.php';
			}
			add_thickbox();
		}

		if( !is_callable( array( $this, 'get_latest_upgrade_class' ) ) ){
			return;
		}

		$latest_upgrade_class = $this->get_latest_upgrade_class();

		if ( ! method_exists( $latest_upgrade_class, 'support_ticket_content' ) ) return;

		$plugin_data = get_plugin_data( self::$plugin_file );
		$license_key = get_site_option( self::$prefix.'_license_key' );

		$latest_upgrade_class::support_ticket_content( 'sa_smart_manager_beta', self::$sku, $plugin_data, $license_key, 'smart-manager-for-wp-e-commerce' );
	}

	public function footer_text( $sm_footer_text ) {
		if ( is_admin() && !empty( $_GET['page'] ) && ( 'smart-manager-woo' === $_GET['page'] || 'smart-manager-wpsc' === $_GET['page'] || ( !empty( $_GET['sm_old'] ) && ( 'woo' === $_GET['sm_old'] || 'wpsc' === $_GET['sm_old'] ) && 'smart-manager' === $_GET['page'] ) || 'smart-manager' === $_GET['page'] || 'smart-manager-settings' === $_GET['page'] || 'smart-manager-pricing' === $_GET['page'] || 'sm-storeapps-plugins' === $_GET['page'] ) ) {
			$plugin_data = get_plugin_data( SM_PLUGIN_FILE );
			ob_start();
			?>
			<span class="sm-footer-left">
				<span class="sm-footer-left-top">
					<?php
					printf(
						/* translators: 1: heart symbol 2: plugin author */
						esc_html_x( 'Smart Manager, made with %1$s by %2$s', 'plugin and brand text in footer', 'smart-manager-for-wp-e-commerce' ),
						'<span class="sm-heart">&hearts;</span>',
						'<span class="sm-brand">' . esc_html_x( 'StoreApps', 'brand name in footer', 'smart-manager-for-wp-e-commerce' ) . '</span>'
					);
					?>
				</span>
				<br />
				<span class="sm-footer-left-bottom">
					<?php
					printf(
						/* translators: 1: pipe as text separator 2: plugin version */
						esc_html_x( 'Proudly built for WordPress & WooCommerce %1$s Version %2$s', 'text for showing plugin is for WordPress and WooCommerce in footer', 'smart-manager-for-wp-e-commerce' ),
						'<span class="sm-separator">|</span>',
						esc_html( $plugin_data['Version'] )
					)
					?>
				</span>
			</span>
			<?php
			return ob_get_clean();
		}

		return $sm_footer_text;
	}

	function update_footer_text( $sm_version_text ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_admin() && ! empty( $_GET['page'] ) && ( 'smart-manager-woo' === $_GET['page'] || 'smart-manager-wpsc' === $_GET['page'] || ( !empty( $_GET['sm_old'] ) && ( 'woo' === $_GET['sm_old'] || 'wpsc' === $_GET['sm_old'] ) && 'smart-manager' === $_GET['page'] ) || 'smart-manager' === $_GET['page'] || 'smart-manager-settings' === $_GET['page'] || 'smart-manager-pricing' === $_GET['page'] || 'sm-storeapps-plugins' === $_GET['page'] ) ) {
			ob_start();
			?>
			<span class="sm-footer-right">
				<span class="sm-footer-right-top">
					<?php
					printf(
						/* translators: 1: open the anchor tag for plugin review link 2: five star symbol 3: close the anchor tag for plugin review link */
						esc_html_x( 'If you like it, please give us %1$s %2$s rating%3$s.', 'text for plugin review in footer', 'smart-manager-for-wp-e-commerce' ),
						'<a target="_blank" href="https://wordpress.org/support/plugin/smart-manager-for-wp-e-commerce/reviews/?filter=5#new-post">',
						'<span class="sm-star">&starf;&starf;&starf;&starf;&starf;</span>',
						'</a>'
					);
					?>
				</span>
				<br />
				<span class="sm-footer-right-bottom">
					<?php
					printf(
						/* translators: 1: open the anchor tag for plugin feature request link 2: close the anchor tag for plugin feature request link */
						esc_html_x( 'Do you have a feature request? Tell us %1$shere%2$s.', 'text for plugin feature request in footer', 'smart-manager-for-wp-e-commerce' ),
						'<a target="_blank" href="https://www.storeapps.org/support/contact-us/?utm_source=sm&utm_medium=in_app_footer&utm_campaign=feature_request">',
						'</a>'
					);
					?>
				</span>
			</span>
			<?php
			return ob_get_clean();
		}
		return $sm_version_text;
	}

	//Function for showing the sm-privilege settings
	function show_privilege_page() {
		if (file_exists( $this->plugin_path . '/pro/sm-privilege.php' )) {
			include_once ($this->plugin_path . '/pro/sm-privilege.php');
			return;
		} else {
			$error_message = __( "A required Smart Manager file is missing. Can't continue. ", 'smart-manager-for-wp-e-commerce' );
		}
	}

	//function to display notices
	function display_notice($notice) {
		echo "<div class='sm-upgrade-notice'>
					<p>";
		echo _e( $notice, 'smart-manager-for-wp-e-commerce' );
		echo "</p></div>";
	}

	//function to error messages
	function display_err() {
		echo "<div id='notice' class='error'>";
		echo "<b>" . __( 'Error:', 'smart-manager-for-wp-e-commerce' ) . "</b>" . $this->error_message;
		echo "</div>";
	}

	public static function get_data() {
		return get_plugin_data( SM_PLUGIN_FILE );
	}

	public static function get_version() {

		$version = '';

		if( is_callable( array( 'Smart_Manager', 'get_data' ) ) ) {
			$plugin_data = self::get_data();
			$version = $plugin_data['Version'];
		}

		return $version;
	}

	function manage_with_smart_manager() {
		$current_post_type = get_current_screen()->post_type;
		$current_screen_id = get_current_screen()->id;

		if ( ( ! empty( $current_post_type ) && 'edit-'.$current_post_type === $current_screen_id ) || 'users' === $current_screen_id  ) {
			$dashboard = ( ( ! empty( $current_post_type ) ) ? $current_post_type : 'user' );

			wp_register_script( 'manage_with_sm', plugins_url( '/assets/js/manage-with-smart-manager.js', SM_PLUGIN_FILE ), array(), self::get_version(), true );
			wp_enqueue_script( 'manage_with_sm' );
			$sm_params = array(
				'url' => admin_url( 'admin.php?page=smart-manager' ) . '&dashboard=' . $dashboard,
				'string' => '<img src="' . SM_IMG_URL . 'menu-icon-16x16.png" /> Manage with Smart Manager',
				'active_dashboard' => $dashboard,
			);
			wp_localize_script( 'manage_with_sm', 'manage_with_sm', $sm_params );
			?>
			<style type="text/css">
				.page-title-action.edit-sm {
					background-color: #ffffff;
					border: 1px solid #0e9f6e;
					color: #0e9f6e;
				}
				.page-title-action.edit-sm img {
					vertical-align: sub;
				}
				.page-title-action.edit-sm:hover {
					background: #f1f1f1 !important;
					border-color: #0e9f6e !important;
					color: #0e9f6e !important;
				}
			</style>
			<?php
		}
	}

	// Function to disable WP plugin auto updates -- added v5.13.0
	public function auto_update_setting_html( $html, $plugin_file, $plugin_data ) {
		if ( defined('SM_PLUGIN_BASE_NM' ) && SM_PLUGIN_BASE_NM === $plugin_file ) {
			$html = __( 'Auto-updates are not available for this plugin.', 'smart-manager-for-wp-e-commerce' );
		}
		return $html;
	}

	/**
	 * Function for handling adding of Smart Manager in wp admin menu bar.
	 *
	 * @param object $wp_admin_bar WP_Admin_Bar instance.
	 * @return void.
	 */
	public function add_admin_bar_menu( $wp_admin_bar = null ) {
		if ( empty( $wp_admin_bar ) ) {
			return;
		}

		$current_user_role = ( is_callable( array( 'Smart_Manager', 'get_current_user_role' ) ) ) ? self::get_current_user_role() : '';
		if( ! ( ( defined( 'SMPRO' ) && true === SMPRO  ) || ( ( ! empty( $current_user_role ) && 'administrator' === $current_user_role ) ) ) ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id' => 'sm-admin-bar-btn',
			'title' => '<span class="ab-icon dashicons-before dashicons-performance"></span>Smart Manager',
			'href' => admin_url( 'admin.php?page=smart-manager' ),
			'meta' => array(
				'title' => 'Smart Manager - WooCommerce Advanced Bulk Edit, Inventory Management & more'
			)
		) );
	}

	/**
	 * Function to declare WooCommerce HPOS compatibility
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'smart-manager-for-wp-e-commerce/smart-manager.php', true );
		}
	}

	/**
	 * Function to handle duplicate dashboard names
	 *
	 * @param array $dashboard_slugs dashboard slugs.
	 * @param string $dashboard_type dashboard type.
	 */
	public static function handle_duplicate_dashboard_names( $dashboard_slugs = array(), $dashboard_type = '' ) {
		if ( empty( $dashboard_slugs ) || ( ! is_array( $dashboard_slugs ) )  ) {
			return;
		}
		array_map( function( $slug = '' ) use ( $dashboard_type ) {
			switch ( $dashboard_type ) {
				case 'post_type':
					if ( isset( self::$sm_dashboards_final[ $slug ] ) ) {
						if ( in_array( $slug, array( 'product', 'shop_order', 'shop_coupon' ) ) ) {
							self::$sm_dashboards_final[ $slug ] = _x( 'WooCommerce - ', 'WooCommerce post type label', 'smart-manager-for-wp-e-commerce' ) . self::$sm_dashboards_final[ $slug ];
						} else {
							self::$sm_dashboards_final[ $slug ] = self::$sm_dashboards_final[ $slug ] . " ($slug)";
						}
					}
					break;
				case 'taxonomy':
					if ( isset( self::$taxonomy_dashboards[ $slug ] ) ) {
						if ( in_array( $slug, array( 'product_type', 'product_visibility', 'product_cat', 'product_tag', 'product_shipping_class' ) ) ) {
							self::$taxonomy_dashboards[ $slug ] = _x( 'WooCommerce - ', 'WooCommerce post type label', 'smart-manager-for-wp-e-commerce' ) . self::$taxonomy_dashboards[ $slug ];
						} else {
					   		self::$taxonomy_dashboards[ $slug ] = self::$taxonomy_dashboards[ $slug ] . " ($slug)";
						}
					}
					break;
			}
		}, array_reduce( $dashboard_slugs, function( $merged_slugs = array(), $slugs = array() ) {
			return ( ( is_array( $slugs ) ) && ( count( $slugs ) > 1 ) ) ? array_merge( $merged_slugs, $slugs ) : $merged_slugs;
		}, [] ) );
	}

	/**
	 * Function to add additional links under plugins meta on plugins page for 5-star and Go Pro in case of lite version installed.
	 *
	 * @param array  $plugin_meta Plugin meta.
	 * @param string $plugin_file Plugin file.
	 * @param array  $plugin_data Plugin's data.
	 * @param string $status Plugin's status.
	 * @return array Plugin meta with additional links.
	 */
	public function add_additonal_links( $plugin_meta = array(), $plugin_file = '', $plugin_data = array(), $status = '' ) {
		if ( ( defined('SM_PLUGIN_BASE_NM' ) && ( SM_PLUGIN_BASE_NM !== $plugin_file ) ) || ( ! defined('SM_PLUGIN_BASE_NM' ) ) || empty( $plugin_file ) ) {
			return $plugin_meta;
		}
		if ( ( defined('SMPRO') && false === SMPRO ) || ( ! defined('SMPRO') ) ) {
			$plugin_meta[] = '<span class="sm_pricing_icon"> 🔥 </span> <a href="' . esc_url( admin_url( 'admin.php?page=smart-manager-pricing' ) ) . '" target="storeapps_go_pro" title="' . _x( 'Go Pro', 'go pro link title', 'smart-manager-for-wp-e-commerce' ) . '">' . _x( 'Go Pro', 'go pro link', 'smart-manager-for-wp-e-commerce' ) . '</a>';
		}

		$plugin_meta[] = sprintf(
			/* translators: %1\$: 5-star link %2s: 5-star link */
			__( "Boost us with %1\$s&#11088;&#11088;&#11088;&#11088;&#11088; &#128640;%2\$s", 'smart-manager-for-wp-e-commerce' ),
			'<a href="https://wordpress.org/support/plugin/smart-manager-for-wp-e-commerce/reviews/?filter=5#new-post" target="storeapps_5_star" title="' . _x( '5-star review', '5-star link title', 'smart-manager-for-wp-e-commerce' ) . '">', '</a>' );

		return $plugin_meta;
	}

	/**
	 * Function to determine if Halloween specific offer is to be shown or not
	 *
	 * @return boolean Flag to determine whether Halloween specific offer is to be shown or not
	 */
	public static function show_halloween_offer(){
		return ( ( time() >= strtotime( '2023-10-26 11:30:00' ) ) && ( time() <= strtotime( '2023-11-02 07:00:00' ) ) ) ? true : false;
	}


	/**
	 * Calculate saved time and additional savings using bulk edit calculation.
	 *
	 * @param string $edit_type Type of edit ('inline', 'advanced_search_inline', 'bulk').
	 * @param int    $records_updated Number of records updated.
	 * @param string $return_unit Unit to return the result in ('hrs' or 'mins'). Default is 'hrs'.
	 * @return array|void Array with man-hours saved and additional savings, or void if input is invalid.
	*/
	public static function sm_get_time_saved_with_additional_savings( $edit_type = '', $records_updated = 0, $return_unit = 'hrs' ) {
		if ( empty( $edit_type ) || empty( $records_updated ) || ! array_key_exists( $edit_type, self::$time_saved_per_record ) ) {
			return;
		}
		$return_unit = strtolower( sanitize_text_field( $return_unit ) );
		// Calculate man-hours saved for the given edit type.
		$man_hours_saved = floatval( ( absint( $records_updated ) ) * ( self::$time_saved_per_record[ $edit_type ] ) );

		// Additional savings if bulk edit was used.
		$additional_savings = 0;
		if ( $edit_type !== 'bulk' ) {
			$additional_savings = absint( $records_updated ) * ( self::$time_saved_per_record['bulk'] - self::$time_saved_per_record[ $edit_type ] );
		}
		$multiplier = ( $return_unit === 'mins' ) ? 60 : 1;
		return array(
			'time_saved'        => round( $man_hours_saved * $multiplier, 2 ),
			'additional_savings' => round( $additional_savings * $multiplier, 2 ),
			'unit'              => $return_unit
		);
	}

	/**
	 * Update man-hours saved and records updated in the database.
	 *
	 * @param string $edit_type Type of edit ('inline', 'advanced_search_inline', 'bulk').
	 * @param int    $records_updated Number of records updated.
	 * @return void
	*/
	public static function sm_update_man_hours_data( $edit_type = '', $records_updated = 0 ) {
		if ( empty( $edit_type ) || empty( $records_updated ) ) {
			return;
		}

		$edit_type = sanitize_key( $edit_type );
		$records_updated = absint( $records_updated );

		$time_saved_details = self::sm_get_time_saved_with_additional_savings( $edit_type, $records_updated, 'hrs' );
		if ( empty( $time_saved_details['time_saved'] ) ) {
			return;
		}

		$man_hours_saved = floatval( $time_saved_details['time_saved'] );
		if ( empty( $man_hours_saved ) ) {
			return;
		}

		$man_hours_data = get_option( 'sa_sm_man_hours_saved', array() );
		$records_data = get_option( 'sa_sm_records_updated', array() );

		// Set 'advanced_search_inline' as 'inline' to simplify man-hours tracking.
		if( $edit_type === 'advanced_search_inline' ){
			$edit_type = 'inline';
		}

		$man_hours_data[ $edit_type ] = round( ( ! empty( $man_hours_data[ $edit_type ] ) ) ? ( $man_hours_data[ $edit_type ] + $man_hours_saved ) : $man_hours_saved, 2 );
		$records_data[ $edit_type ] = round( ( ! empty( $records_data[ $edit_type ] ) ) ? ( $records_data[ $edit_type ] + $records_updated ) : $records_updated, 2 );

		update_option( 'sa_sm_man_hours_saved', $man_hours_data );
		update_option( 'sa_sm_records_updated', $records_data );
	}

	/**
	 * Get total man-hours saved from options and determine if they should be displayed.
	 *
	 * @return array|void Array with 'man_hours' and 'display_man_hours' keys, void if not found or empty
	*/
	public static function sm_get_man_hours_data() {
		$man_hours_data = get_option( 'sa_sm_man_hours_saved', array() );
		if ( ( ! is_array( $man_hours_data ) ) || ( empty( $man_hours_data['inline'] ) ) ) {
			return array(
				'man_hours_saved' => 0,
				'display_man_hours' => false,
				'additional_savings' => 0,
			);
		}
		return array(
			'man_hours_saved'    => floatval( $man_hours_data['inline'] ),
			'display_man_hours'  => ( ( floatval( $man_hours_data['inline'] ) >= 0.25 ) && ( ( defined('SMPRO') && false === SMPRO ) || ( ! defined('SMPRO') ) ) ) ? true : false,
			'additional_savings' => round( self::sm_calculate_additional_man_hrs_savings( floatval( $man_hours_data['inline'] ) ), 2 )
		);
	}

	/**
	 * Calculate additional man-hours saved by using bulk edit instead of inline edit.
	 *
	 * @param float $man_hours_inline Total man-hours saved using inline edit.
	 * @return float Additional man-hours saved using bulk edit.
	*/
	public static function sm_calculate_additional_man_hrs_savings( $man_hours_inline = 0 ) {
		if( ( empty( $man_hours_inline ) ) ) {
			return;
		}
		return round( ( ( floatval( $man_hours_inline ) ) / ( floatval( self::$time_saved_per_record['inline'] ) ) ) * ( ( floatval( self::$time_saved_per_record['bulk'] ) ) - ( floatval( self::$time_saved_per_record['inline'] ) ) ), 2 );
	}

	/**
	 * Display a notice summarizing the saved man-hours and available discounts.
	 *
	 * @param string $user_name The name of the current user.
	 * @param array  $man_hours_data The data about the saved man-hours and additional savings.
	 * @return string html containing man hours data or empty string if data is not valid.
	*/
	public static function sm_get_man_hours_html( $man_hours_data = array(), $user_name = '' ) {
		if( ( empty( $man_hours_data ) ) || ( ! is_array( $man_hours_data ) ) || ( empty( $user_name ) ) || ( empty( $man_hours_data['additional_savings'] ) ) || ( empty( $man_hours_data['man_hours_saved'] ) ) ) {
			return '';
		}
		return '<style>
			.sm_main_headline {
				display: flex;
				justify-content: center;
			}
			.sm_main_headline .dashicons {
				margin-right: 1rem;
			}
			.sm_main_content {
				font-size: 1rem;
			}
			.sm_claim_discount {
				margin-top: 1rem;
			}
			.discount-text {
				font-weight: bold;
				font-size: 1.25rem;
				color: rgb(55, 65, 81);
			}
			.sm_sub_headline {
				font-size: 1.1em;
				line-height: 1.5rem;
			}
			.pricing-link {
				color: rgb(55, 65, 81);
			}
		</style>
		<div class="sm_design_notice">
			<div class="sm_container">
				<div class="sm_main_headline">
				<div class="dashicons dashicons-awards"></div>
				<div class="sm_main_content">
					<span>
					' . sprintf(
						/* translators: %1$s: user name, %2$s: saved man-hours */
						__( 'Hey %1$s, you’ve just saved <strong>%2$s productive hours</strong> with Smart Manager! 🎉', 'smart-manager-for-wp-e-commerce' ),
						$user_name,
						$man_hours_data['man_hours_saved']
					) . '
					</span>
					<div class="sm_claim_discount">
					' . sprintf(
						/* translators: %1$s: discount percentage, %2$s: additional man-hours */
						__( 'Upgrade to Smart Manager Pro to save <strong>additional %1$s hours</strong> (minimum) and unlock all features. %2$s', 'smart-manager-for-wp-e-commerce' ),
						$man_hours_data['additional_savings'],
						'<a class="pricing-link" href="' . admin_url( 'admin.php?page=smart-manager-pricing' ) . '" target="_blank">' . __( 'Get it at', 'smart-manager-for-wp-e-commerce' ) . ' <strong>'. __( '25% off!', 'smart-manager-for-wp-e-commerce' ) . '</strong> </a>'
					) . '
					</div>
				</div>
				</div>
			</div>
		</div>';
	}

	/**
	 * Get the display name of the current user or a fallback value.
	 *
	 * @param string $fallback The fallback value to use if the user's display name is not set. Default is 'there'.
	 * @return string|false The display name of the current user or false if user not exist.
	*/
	public static function sm_get_current_user_display_name( $fallback = 'there' ) {
		if( ( empty( $fallback ) ) ){
			$fallback = 'there';
		}
		$current_user = wp_get_current_user();
		if ( ! $current_user->exists() ) {
			return false;
		}
		$display_name = $current_user->display_name;
		return ( ( ! empty( $display_name ) ) ) ? $display_name : __( $fallback, 'smart-manager-for-wp-e-commerce' );
	}

	/**
	 * Get Action Scheduler admin URL.
	 *
	 * @param string $search Search query string for Action Scheduler.
	 * @return string
	 */
	public static function get_scheduled_actions_search_url($search = '')
	{
		return (empty($search)) ? '' : esc_url(admin_url('tools.php?page=action-scheduler&orderby=schedule&order=desc&action=-1&action2=-1&status=pending&paged=1') . '&s=' . urlencode($search));
	}

	/**
	 * Function to dequeue styles in Smart Manager page
	 *
	 * @return void
	 */
	public function sa_sm_dequeue_styles()
	{

		global $wp_styles;
		if (! is_admin() || empty($_GET['page']) || (! empty($_GET['page']) && ('smart-manager' !== $_GET['page'])) || empty($wp_styles->queue) || (! is_array($wp_styles->queue))) {
			return;
		}
		$dequeue_handles = array('adl-lp-bootstrap');
		foreach ($wp_styles->queue as $handle) {
			if (empty($handle) || empty($dequeue_handles) || (! is_array($dequeue_handles)) || (! in_array($handle, $dequeue_handles)) || (! wp_style_is($handle))) {
				continue;
			}
			wp_dequeue_style($handle);
			wp_deregister_style($handle);
		}
	}

	/**
	 * Generic function to show admin notice about new features on Smart Manager pages.
	 *
	 * @param string $feature_key Unique identifier for the feature (e.g., 'sku_generation', 'bulk_edit').
	 * @param string $description The main description of the feature.
	 * @param string $instructions Optional instructions on how to use the feature.
	 * @return void
	 */
	public function add_feature_notice( $feature_key = '', $description = '', $instructions = '' ) {
		if ( ( empty( $feature_key ) ) || ( empty( $description ) ) || ( empty( $instructions ) ) ) {
			return;
		}
		$this->clear_previous_feature_notices( array( 'generate_sku_feature' ) );
		$feature_key = sanitize_key( $feature_key );
		if ( ( ! defined('SMPRO') ) || ( SMPRO !== true ) || ( empty( $_GET['page'] ) ) || ( ! in_array( sanitize_text_field( wp_unslash( $_GET['page'] ) ), array( 'smart-manager', 'sm-storeapps-plugins', 'sm-about' ), true ) ) || get_option( "sa_sm_hide_{$feature_key}_notice" ) ) {
			return;
		}
		?>
		<script>
			jQuery(document).on('click', '#sm_<?php echo esc_attr( $feature_key ); ?>_notice .notice-dismiss', function(){
				jQuery.ajax({
					url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					type: 'post',
					dataType: 'json',
					data: {
						action: 'dismiss_feature_notice',
						feature_key: '<?php echo $feature_key; ?>',
						security: '<?php echo wp_create_nonce( "sa-dismiss-{$feature_key}-notice" ); ?>'
					}
				});
			});
		</script>
		<?php
		
		printf(
			'<div class="sm_feature_notice notice notice-%s is-dismissible" id="sm_%s_notice" style="display:block !important;"><p>✨ <strong>%s</strong> %s %s</p></div>',
			'success',
			esc_attr( $feature_key ),
			'Introducing:',
			wp_kses_post( $description ),
			wp_kses_post( $instructions )
		);
	}

	/**
	 * AJAX handler to dismiss feature notices.
	 *
	 * @return void
	 */
	public function dismiss_feature_notice() {
		if ( empty( $_POST['feature_key'] ) ) {
			wp_send_json_error();
		}
		$feature_key = sanitize_key( $_POST['feature_key'] );
		check_ajax_referer( "sa-dismiss-{$feature_key}-notice", 'security' );
		return update_option( "sa_sm_hide_{$feature_key}_notice", true );
	}

	/**
	 * Remove/clear previous feature notices DB options when a new feature is announced.
	 *
	 * @param array $previous_feature_keys. Array of previous feature keys to dismiss.
	 * @return void
	 */
	public function clear_previous_feature_notices( $previous_feature_keys = array() ) {
		// If no previous keys specified, find all feature notice options.
		if ( empty( $previous_feature_keys ) || ( ! is_array( $previous_feature_keys ) ) ) {
			return;
		}
		// Dismiss previous notices.
		foreach ( $previous_feature_keys as $old_key ) {
			delete_option( "sa_sm_hide_" . sanitize_key( $old_key ) . "_notice" );
		}
	}

	/**
	 * Handles the allowed directory paths for request processing.
	 *
	 * This function can be used to filter or modify the list of allowed directory paths
	 * for requests handled.
	 *
	 * @param array $allowed_dir_path An array of allowed directory paths.
	 * @return array The filtered or modified array of allowed directory paths.
	 */
	public function request_handler_allowed_dir_path( $allowed_dir_path = array() ) {
		$plugin_base_path = plugin_dir_path( __FILE__ );
		return array_filter( array(
			realpath( $plugin_base_path . 'common-core/classes' ),
			realpath( $plugin_base_path . 'pro/common-pro/classes' )
		) );
	}

	/**
	 * Method to enqueue footer css file
	 * 
	 * @return void
	 */
	public function enqueue_admin_footer_style() {
		$get_page = ( ! empty( $_GET['page'] ) ) ? ( ( function_exists( 'wc_clean' ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : wp_unslash( sanitize_text_field( $_GET['page'] ) ) ) : ''; // phpcs:ignore
		if ( empty( boolval( in_array( $get_page, array( 'smart-manager', 'sm-about', 'smart-manager-pricing', 'sm-storeapps-plugins' ), true ) ) ) ) {
			return;
		}
		$plugin_data = ( function_exists( 'get_plugin_data' ) ) ? get_plugin_data( self::$plugin_file ) : array();
		wp_enqueue_style( 'smart-manager-footer-css', plugins_url ( '/assets/css/smart-manager-footer.css', SM_PLUGIN_FILE ), array(), ( ! empty( $plugin_data['Version'] ) ) ? $plugin_data['Version'] : false );
	}
	/**
	 * Check if AI integration is enabled and a model is selected.
	 *
	 * @return bool True if AI integration is enabled and a model is selected, false otherwise.
	 */
	public function is_ai_integration_enabled() {
		$ai_integration_settings = Smart_Manager_Settings::get('ai_integration_settings');
		return ( is_array( $ai_integration_settings ) && ! empty( $ai_integration_settings['selectedModel'] ) );
	}
}

$GLOBALS['smart_manager_beta'] = Smart_Manager::instance();
