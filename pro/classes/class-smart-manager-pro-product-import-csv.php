<?php
/**
 * Smart_Manager_Pro_Product_Import_CSV class to habdle data via import.
 *
 * @version   1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Smart_Manager_Pro_Product_Import_CSV' ) ) {
	class Smart_Manager_Pro_Product_Import_CSV {
		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		function __construct() {
			$this->init_hooks(); // for defining all actions & filters
		}

		public function init_hooks() {
			// hooks to overwrite slug when importing the csv with updated data.
			add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'add_column_to_importer_exporter' ) );
			add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'add_default_column_mapping' ) );
			add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'process_product_import_data' ), 10, 2 );
			add_filter( 'woocommerce_product_import_process_item_data', array( $this, 'generate_sku_on_import' ), 10, 1 );
		}

		/**
		 * Adds a 'Slug' option to the import options array.
		 *
		 * @param array $options Existing import options.
		 * @return array Updated options array with 'Slug'.
		*/
		public function add_column_to_importer_exporter( $options = array() ) {
			$options['slug'] = __( 'Slug', 'smart-manager-for-wp-e-commerce' );
			return $options;
		}

		/**
		 * Maps the 'Slug' column to the corresponding field in the import data.
		 *
		 * @param array $columns Existing column mappings.
		 * @return array Updated column mappings with 'Slug' mapped to 'slug'.
		*/
		public function add_default_column_mapping( $columns = array() ) {
			$columns['Slug'] = 'slug';
			return $columns;
		}

		/**
		 * Processes the import for the product slug column and sets the slug.
		 *
		 * @param object|null $object The product object to be updated.
		 * @param array       $data Import data containing the slug.
		 * @return object|null The updated product object or null if slug is empty or method is not callable.
		*/
		public function process_product_import_data( $product = null, $data = array() ) {
			if ( ( ! is_array( $data ) ) || empty( $data['slug'] ) || ( ! is_callable( array( $product, 'set_slug' ) ) ) ) {
				return $product;
			}
			$product->set_slug( $data['slug'] );
			return $product;
		}

		/**
		 * Get product category names.
		 *
		 * @param WC_Product $product WooCommerce product object.
		 * @return array category names.
		 */
		public function get_product_categories( $product = null ) {
			if ( ( empty( $product ) )  || ( ! is_callable( array( $product, 'get_id' ) ) ) ) {
				return;
			}
			// Use parent product for variations.
			$product = ( ( ! empty( $product->is_type( 'variation' ) ) ) && ( is_callable( array( $product, 'get_parent_id' ) ) ) ) ? wc_get_product( $product->get_parent_id() ) : $product;
			$terms = ( ( ! empty( $product ) ) && ( is_callable( array( $product, 'get_id' ) ) ) ) ? wp_get_post_terms( $product->get_id(), 'product_cat', array('fields' => 'names') ) : null;
			if ( ( empty( $terms ) ) || ( is_wp_error( $terms ) ) || ( ! is_array( $terms ) ) ) {
				return;
			}
			foreach ( $terms as $term_name ) {
				if ( ( empty( $term_name ) ) ) {
					continue;
				}
				return array( sanitize_title( $term_name ) );
			}
		}

		/**
		 * Get product attributes (up to 2).
		 *
		 * @param WC_Product $product
		 * @return array $fallback_attrs
		 */
		public function get_product_attributes( $product = null, $count = 0 ) {
			$fallback_attrs = array();
			if ( ( empty( $count ) ) || ( empty( $product ) ) || ( ! is_object( $product ) ) || ( ! is_callable( array( $product, 'is_type' ) ) ) ) {
				return;
			}
			// Handle variation products
			if ( $product->is_type( 'variation' ) ) {
				$data = $product->get_data();
				if ( ( empty( $data ) ) || ( ! is_array( $data ) ) || ( empty( $data['attributes'] ) ) || ( ! is_array( $data['attributes'] ) ) ) {
					return;
				}
				foreach ( $data['attributes'] as $key => $value ) {
					if ( empty( $value ) ) {
						continue;
					}
					$taxonomy = str_replace( 'attribute_', '', $key );
					$name     = taxonomy_exists( $taxonomy ) ? ( ( $term = get_term_by( 'slug', $value, $taxonomy ) ) && ! is_wp_error( $term ) ? $term->name : $value ) : $value;
					$fallback_attrs[] = sanitize_title( $name );
					if ( count( $fallback_attrs ) >= absint( $count ) ) {
						break;
					}
				}
				return $fallback_attrs;
			}
			// Handle other product types products.
			if ( is_callable( array( $product, 'get_attributes' ) ) ) {
				$attributes = $product->get_attributes();
				if ( empty( $attributes ) || ! is_array( $attributes ) ) {
					return;
				}
				foreach ( $attributes as $attr ) {
					if ( ( empty( $attr ) ) || ( ! is_callable( array( $attr, 'get_options' ) ) ) ) {
						continue;
					}
					$options = $attr->get_options();
					if ( ( empty( $options ) ) || ( ! is_array( $options ) ) || empty( $options[0] ) ) {
						continue;
					}
					$taxonomy = is_callable( array( $attr, 'get_name' ) ) ? $attr->get_name() : '';
					$name     = taxonomy_exists( $taxonomy ) ? ( ( $term = get_term( absint( $options[0] ), $taxonomy ) ) && ! is_wp_error( $term ) ? $term->name : $options[0] ) : $options[0];

					$fallback_attrs[] = sanitize_title( $name );

					if ( count( $fallback_attrs ) >= 2 ) {
						break;
					}
				}
			}
			return $fallback_attrs;
		}

		/**
		 * Generate a structured SKU during product import if none is provided.
		 *
		 * @param array $item Product import item data.
		 * @return array Modified item with generated SKU.
		 */
		public function generate_sku_on_import( $item = array() ) {
			if ( ( empty( $item ) ) || ( ! is_array( $item ) ) || ( ! empty( $item['sku'] ) ) ) {
				return $item;
			}
			$settings = get_option( 'sa_sm_settings', array() );
			if ( ( empty( $settings ) ) || ( ! is_array( $settings ) ) || ( empty( $settings['general'] ) ) || ( ! is_array( $settings['general'] ) ) || ( empty( $settings['general']['toggle'] ) ) || ( ! is_array( $settings['general']['toggle'] ) || ( empty( $settings['general']['toggle']['generate_sku'] ) ) || ( 'yes' !== $settings['general']['toggle']['generate_sku'] ) ) ) {
				return $item;
			}

			$fallback_cats = [];
			$fallback_attrs = [];
			$product = null;

			if ( ( ! empty( $item['id'] ) ) ) {
				$product = wc_get_product( absint( $item['id'] ) );
			}
			if ( ( ! empty( $product ) ) && ( is_callable( array( $product, 'get_sku' ) ) ) && ( ! empty( $product->get_sku() ) ) ) {
				return $item;
			}
			if ( ( empty( $item['category_ids'] ) || empty( $item['raw_attributes'] ) || empty( $item['slug'] ) ) ) {
				if ( ( ! empty( $product ) ) && ( is_callable( array( $product, 'get_id' ) ) ) && ( 'importing' !== $product->get_status() ) ) {
					// Get fallback categories.
					if ( ( empty( $item['category_ids'] ) ) ) {
						$fallback_cats = $this->get_product_categories( $product );
					}
					// Get fallback attributes.
					if ( ( empty( $item['raw_attributes'] ) ) ) {
						$fallback_attrs = $this->get_product_attributes( $product, 2 );
					}
					//Get slug.
					if ( ( empty( $item['slug'] ) ) && ( is_callable( array( $product, 'get_slug' ) ) ) ) {
						$item['slug'] = $product->get_slug();
					}
				}
			}
			$max_length = absint( get_option( 'sa_sm_sku_max_length', 15 ) );
			$sku_parts_length = 3;
			$max_length = absint( apply_filters( 'sa_sm_sku_max_length', $max_length ) );
			$sku_parts = [];

			// Use $item['category_ids'] or fallback categories for SKU part (3 chars)
			if ( ( ! empty( $item['category_ids'] ) ) && ( is_array( $item['category_ids'] ) ) ) {
				//In case the term($item['category_ids'][0]) does not exist, WC will create it before this function hook runs.
				$term = get_term( absint( $item['category_ids'][0] ), 'product_cat' );
				if ( ! empty( $term ) && ! is_wp_error( $term ) && ! empty( $term->slug ) ) {
					$sku_parts[] = substr( sanitize_title( $term->slug ), 0, $sku_parts_length );
				}
			} elseif ( ( ! empty( $fallback_cats ) ) && ( is_array( $fallback_cats ) ) && ( ! empty( $fallback_cats[0] ) ) ) {
				$sku_parts[] = substr( sanitize_title( $fallback_cats[0] ), 0, $sku_parts_length );
			}

			// Add slug or name (3 chars)
			if ( ( ! empty( $item['slug'] ) ) || ( ! empty( $item['name'] ) ) ) {
				$slug = sanitize_title( ( ! empty( $item['slug'] ) ) ? $item['slug'] : $item['name'] );
				if ( ! empty( $slug ) ) {
					$sku_parts[] = substr( $slug, 0, $sku_parts_length );
				}
			}

			// Use up to 2 attribute values from $item or fallback (3 chars each)
			$count = 0;
			$attributes = ( ! empty( $item['raw_attributes'] ) && is_array( $item['raw_attributes'] ) ) ? $item['raw_attributes'] : $fallback_attrs;

			if ( ( ! empty( $attributes ) ) && ( is_array( $attributes ) ) ) {
				foreach ( $attributes as $attr ) {
					$val = sanitize_title( is_array( $attr ) && ! empty( $attr['value'][0] ) ? $attr['value'][0] : ( is_string( $attr ) ? $attr : '' ) );
					if ( empty( $val ) ) {
						continue;
					}
					$part = substr( $val, 0, $sku_parts_length );
					if ( ! in_array( $part, $sku_parts, true ) ) {
						$sku_parts[] = $part;
						if ( ++$count >= 2 ) {
							break;
						}
					}
				}
			}
			// Build and validate SKU.
			$sku = strtoupper( implode( '-', $sku_parts ) );
			$sku = strlen( $sku ) > $max_length ? rtrim( substr( $sku, 0, $max_length ), '-' ) : $sku;

			$suffix = 1;
			while ( wc_get_product_id_by_sku( $sku ) ) {
				$rand = str_pad( $suffix, 2, '0', STR_PAD_LEFT );
				$sku  = substr( $sku, 0, ( $max_length - strlen( $rand ) - 1 ) ) . '-' . $rand;
				if ( ++$suffix > 999 ) {
					break;
				}
			}
			$item['sku'] = sanitize_text_field( $sku );
			return $item;
		}

	}
	Smart_Manager_Pro_Product_Import_CSV::instance();
}
