<?php
/**
 * Smart_Manager_Pro_Product_Changes_Tracker class to track product changes.
 *
 * @package Smart_Manager_Pro_Product_Changes_Tracker
 * @since       8.69.0
 * @version     8.73.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Smart_Manager_Pro_Product_Changes_Tracker' ) ) {
	/**
	 * Class Smart_Manager_Pro_Product_Changes_Tracker
	 *
	 * Tracks and manages changes made to WooCommerce product properties.
	 */
	class Smart_Manager_Pro_Product_Changes_Tracker {
		/**
		 * Holds the single instance of the class following the Singleton pattern.
		 *
		 * @var self|null Stores the singleton instance of the class. Initialized as null.
		 * @static
		 * @access protected
		 */
		protected static $instance = null;

		/**
		 * Array to store previous stock values for products.
		 *
		 * @var array
		 * @static
		 */
		public static $products_prev_stock_val = array();

		/**
		 * Returns single instance of the class
		 *
		 * Ensures only one instance is loaded at any time using singleton pattern
		 *
		 * @return Smart_Manager_Pro_Product_Changes_Tracker Single instance of the class
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		/**
		 * Initializes the class by setting up hooks and filters
		 */
		public function __construct() {
			$this->init_hooks(); // for defining all actions & filters.
		}

		/**
		 * Initialize WordPress hooks and filters
		 *
		 * Sets up action to track WooCommerce product property updates
		 * using woocommerce_product_object_updated_props hook
		 */
		public function init_hooks() {
			add_action( 'woocommerce_product_object_updated_props', array( __CLASS__, 'track_updated_props' ), 20, 2 );
			// Capture old stock val.
			add_action( 'woocommerce_product_before_set_stock', array( __CLASS__, 'store_product_prev_stock_val' ), 10, 1 );
			// Add edit history links to product edit page and product list actions.
			add_action( 'add_meta_boxes', array( $this, 'add_edit_history_link_to_individual_product' ) );
			add_filter( 'post_row_actions', array( $this, 'add_product_edit_history_action_link' ), 10, 2 );
		}

		/**
		 * Stores the previous stock value for a product.
		 *
		 * @param WC_Product|null $product The product object to store stock value for, or null.
		 * @return void
		 */
		public static function store_product_prev_stock_val( $product = null ) {
			if ( empty( $product ) || ( ! is_object( $product ) ) || ( ! is_callable( array( $product, 'get_id' ) ) ) || ( ! is_callable( array( $product, 'get_stock_quantity' ) ) ) ) {
				return;
			}
			$product_id = $product->get_id();
			$old_prod   = wc_get_product( $product_id );
			if ( ( empty( $old_prod ) ) || ( ! is_object( $old_prod ) ) || ( ! is_callable( array( $old_prod, 'get_stock_quantity' ) ) ) ) {
				return;
			}
			self::$products_prev_stock_val[ $product->get_id() ] = $old_prod->get_stock_quantity();
		}

		/**
		 * Tracks changes to specific product properties.
		 * Only tracks price and stock related changes.
		 *
		 * @param WC_Product|null $product The product object.
		 * @param array           $updated_props Array of updated property names.
		 * @return void
		 */
		public static function track_updated_props( $product = null, $updated_props = array() ) {
			if ( ( empty( $product ) ) || ( ! is_object( $product ) ) || ( ! is_callable( array( $product, 'get_id' ) ) ) ) {
				return;
			}

			$product_id = $product->get_id();
			if ( empty( $product_id ) ) {
				return;
			}

			if ( isset( self::$products_prev_stock_val[ $product_id ] ) && empty( $updated_props ) ) {
				$updated_props[] = 'stock_quantity';
			}
			if ( ( empty( $updated_props ) ) || ( ! is_array( $updated_props ) ) ) {
				return;
			}
			$changes          = array();
			$actions          = array();
			$meta_keys_edited = array();
			$original_data    = ( is_callable( array( $product, 'get_data' ) ) ) ? $product->get_data() : array();

			foreach ( $updated_props as $property ) {
				if ( empty( $property ) ) {
					continue;
				}
				$getter_method = 'get_' . $property;
				if ( ! is_callable( array( $product, $getter_method ) ) ) {
					continue;
				}

				$current_value  = $product->$getter_method();
				$original_value = ( ! empty( $original_data ) && is_array( $original_data ) && ! empty( $original_data[ $property ] ) ) ? $original_data[ $property ] : '';
				if ( ( 'stock_quantity' === $property ) && ( ! empty( self::$products_prev_stock_val[ $product_id ] ) ) ) {
					$original_value = self::$products_prev_stock_val[ $product_id ];
					if ( $current_value === $original_value ) {
						continue;
					}
				}
				// Handling for sale price date from and date to fields.
				if ( is_object( $current_value ) && in_array( $property, array( 'date_on_sale_from', 'date_on_sale_to' ), true ) && is_callable( array( $current_value, 'getTimestamp' ) ) ) {
					$current_value = $current_value->getTimestamp();
				}
				if ( is_object( $original_value ) && in_array( $property, array( 'date_on_sale_from', 'date_on_sale_to' ), true ) && is_callable( array( $original_value, 'getTimestamp' ) ) ) {
					$original_value = $original_value->getTimestamp();
				}
				$meta_key = self::get_meta_key_for_property( $property );
				if ( empty( $meta_key ) ) {
					continue;
				}
				$actions[]          = array( "postmeta/meta_key={$meta_key}/meta_value={$meta_key}" => $current_value );
				$meta_keys_edited[] = $meta_key;
				$changes[]          = array(
					'record_id'   => $product_id,
					'status'      => 'completed',
					'field'       => "postmeta/meta_key={$meta_key}/meta_value={$meta_key}",
					'action'      => 'set_to',
					'prev_val'    => maybe_serialize( $original_value ),
					'updated_val' => maybe_serialize( $current_value ),
				);
			}
			unset( self::$products_prev_stock_val[ $product_id ] );
			if ( empty( $changes ) || ( ! is_array( $changes ) ) ) {
				return;
			}
			$task_id = self::create_task_entry( $actions, array_unique( $meta_keys_edited ) );
			if ( empty( $task_id ) ) {
				return;
			}
			self::record_changes( $changes, $task_id );
		}

		/**
		 * Creates a new task entry for tracking changes.
		 *
		 * @param array $actions Array of actions performed.
		 * @param array $meta_keys_edited Array of edited meta keys.
		 * @return int|false Task ID on success, false on failure
		 */
		public static function create_task_entry( $actions = array(), $meta_keys_edited = array() ) {
			if ( empty( $actions ) || empty( $meta_keys_edited ) || ( ! is_array( $actions ) ) || ( ! is_array( $meta_keys_edited ) ) ) {
				return false;
			}
			$title = 'Edited ' . implode(
				', ',
				array_map(
					function( $meta_key ) {
						return trim( ucwords( str_replace( '_', ' ', $meta_key ) ) );
					},
					$meta_keys_edited
				)
			);
			return sm_task_update(
				array(
					'title'          => $title,
					'created_date'   => gmdate( 'Y-m-d H:i:s' ),
					'completed_date' => '0000-00-00 00:00:00',
					'post_type'      => 'product',
					'type'           => 'external',
					'status'         => 'in-progress',
					'actions'        => $actions,
					'record_count'   => 1,
				)
			);
		}

		/**
		 * Maps product properties to their corresponding meta keys.
		 *
		 * @param string $property The property name to map.
		 * @return string The corresponding meta key or empty string
		 */
		public static function get_meta_key_for_property( $property = '' ) {
			$property_meta_map = array(
				'sku'                => '_sku',
				'global_unique_id'   => '_global_unique_id',
				'regular_price'      => '_regular_price',
				'sale_price'         => '_sale_price',
				'price'              => '_price',
				'date_on_sale_from'  => '_sale_price_dates_from',
				'date_on_sale_to'    => '_sale_price_dates_to',
				'total_sales'        => 'total_sales',
				'tax_status'         => '_tax_status',
				'tax_class'          => '_tax_class',
				'manage_stock'       => '_manage_stock',
				'backorders'         => '_backorders',
				'low_stock_amount'   => '_low_stock_amount',
				'sold_individually'  => '_sold_individually',
				'weight'             => '_weight',
				'length'             => '_length',
				'width'              => '_width',
				'height'             => '_height',
				'upsell_ids'         => '_upsell_ids',
				'cross_sell_ids'     => '_crosssell_ids',
				'purchase_note'      => '_purchase_note',
				'default_attributes' => '_default_attributes',
				'virtual'            => '_virtual',
				'downloadable'       => '_downloadable',
				'download_limit'     => '_download_limit',
				'download_expiry'    => '_download_expiry',
				'image_id'           => '_thumbnail_id',
				'stock_quantity'     => '_stock',
				'stock_status'       => '_stock_status',
				'average_rating'     => '_wc_average_rating',
				'rating_counts'      => '_wc_rating_count',
				'review_count'       => '_wc_review_count',
				'gallery_image_ids'  => '_product_image_gallery',
			);
			return ( ! empty( $property_meta_map[ $property ] ) ) ? $property_meta_map[ $property ] : '';
		}

		/**
		 * Records the tracked changes.
		 *
		 * @param array $changes Array of changes to record.
		 * @param int   $task_id Associated task ID.
		 * @return void
		 */
		public static function record_changes( $changes = array(), $task_id = 0 ) {
			if ( ( empty( $changes ) ) || ( ! is_array( $changes ) ) || ( empty( $task_id ) ) ) {
				return;
			}
			foreach ( $changes as &$change ) {
				if ( ( empty( $change ) ) || ( ! is_array( $change ) ) ) {
					continue;
				}
				$change['task_id'] = $task_id;
			}
			if ( function_exists( 'sm_task_details_update' ) ) {
				sm_task_details_update( $changes );
			}
		}

		/**
		 * Function to show "Stock log" box on admin product page
		 *
		 *  @return void
		 */
		public function add_edit_history_link_to_individual_product() {
			add_meta_box(
				'post_log_link',
				_x( 'Smart Manager', 'plugin name', 'smart-manager-for-wp-e-commerce' ),
				function ( $post = null ) {
					if ( ( empty( $post ) ) || ( ! is_object( $post ) ) ) {
						return;
					}
					echo '<a href="' . self::get_post_edit_history_url( $post ) . '" rel="permalink" target="_blank">' . esc_html_x( 'View Edit History', 'edit history meta box title', 'smart-manager-for-wp-e-commerce' ) . '</a>';
				},
				'product',
				'side'
			);
		}

		/**
		 * Get edit history URL for a post
		 *
		 * @param int|WP_Post $post Post ID or post object
		 * @return string|false The edit history URL, else false
		 */
		public static function get_post_edit_history_url( $post = null ) {
			return ( empty( $post ) || empty( $post->ID ) || empty( $post->post_type ) ) ? false : admin_url( sprintf( '?page=smart-manager&show_edit_history=1&id=%d&dashboard=%s', absint( $post->ID ), esc_attr( $post->post_type ) ) );
		}

		/**
		 * Add edit history link to product row actions
		 * 
		 * @param array $actions Array of actions
		 * @param object $post post object
		 * @return array array of actions containing edit history url
		 */
		public function add_product_edit_history_action_link( $actions = array(), $post = null ) {
			if ( ( empty( $post ) ) || ( ! is_object( $post ) ) || ( empty( $post->post_type ) ) || ( $post->post_type !== 'product' ) ) {
				return $actions;
			}
			/* translators: %s: URL and text for View Edit History link */
			$actions['sm_edit_history'] = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', self::get_post_edit_history_url( $post ), esc_html_x( 'View Edit History', 'edit history row action', 'smart-manager-for-wp-e-commerce' ) );
			return $actions;
		}

	}
	Smart_Manager_Pro_Product_Changes_Tracker::instance();
}
