<?php
/**
 * Common core product class.
 *
 * @package common-core/
 * @since       8.64.0
 * @version     8.67.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SA_Manager_Product' ) ) {
	/**
	 * Class properties and methods will go here.
	 */
	class SA_Manager_Product extends SA_Manager_Base {
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
		 * @param string $plugin_data Plugin data.
		 * @return self|null self::$instance The single instance of the class
		 */
		public static function instance( $plugin_data = null ) {
			if ( is_null( self::$instance ) && ! empty( $plugin_data ) ) {
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
			parent::__construct( $plugin_data );
			$this->dashboard_key = ( ! empty( $plugin_data['dashboard_key'] ) ) ? $plugin_data['dashboard_key'] : '';
			$this->plugin_sku    = ( ! empty( $plugin_data['plugin_sku'] ) ) ? $plugin_data['plugin_sku'] : '';
			$this->post_type     = array( 'product', 'product_variation' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->req_params = ( ! empty( $_REQUEST ) )
				? ( function_exists( 'wc_clean' )
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					? wc_clean( wp_unslash( $_REQUEST ) )
					: array_map(
						function( $value ) {
							if ( is_array( $value ) ) {
								return array_map( 'sanitize_text_field', $value );
							}
							return is_scalar( $value ) ? sanitize_text_field( $value ) : $value;
						},
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						wp_unslash( $_REQUEST )
					)
				)
				: array();
			add_filter( 'sa_' . $this->plugin_sku . '_dashboard_model', array( $this, 'products_dashboard_model' ), 10, 2 );
		}

		/**
		 * Get the dashboard model
		 *
		 * @param array $dashboard_model dashbaord model array.
		 * @param array $dashboard_model_saved saved dashboard model array.
		 * @return array $dashboard_model updated dashbaord model array
		 */
		public function products_dashboard_model( $dashboard_model = array(), $dashboard_model_saved = array() ) {
			global $wpdb, $current_user;
			$visible_columns        = array(
				'ID',
				'_thumbnail_id',
				'post_title',
				'_sku',
				'_regular_price',
				'_sale_price',
				'_stock',
				'post_status',
				'post_content',
				'product_cat',
				'product_attributes',
				'_length',
				'_width',
				'_height',
				'_visibility',
				'_tax_status',
				'product_type',
				'edit_link',
				'view_link',
			);
			$custom_numeric_columns = array( '_regular_price', '_sale_price', '_price' );
			$integer_columns        = array( '_stock' );
			$numeric_columns        = array( '_length', '_width', '_height' );
			$date_columns           = array( '_sale_price_dates_from', '_sale_price_dates_to' );
			if ( empty( $dashboard_model['columns'] ) ) {
				$dashboard_model['columns'] = array();
			}
			$column_model                = &$dashboard_model['columns'];
			$column_model_transient      = get_user_meta( get_current_user_id(), 'sa_' . $this->plugin_sku . '_' . $this->dashboard_key, true );
			$dashboard_model['treegrid'] = 'true'; // for setting the treegrid.
			if ( isset( $column_model_transient['treegrid'] ) ) {
				$dashboard_model['treegrid'] = $column_model_transient['treegrid'];
			}
			$dashboard_model['tables']['posts']['where']['post_type'] = ( 'true' === $dashboard_model['treegrid'] || true === $dashboard_model['treegrid'] ) ? array( 'product', 'product_variation' ) : array( 'product' );
			$product_visibility_index                                 = sa_multidimesional_array_search( 'terms/product_visibility', 'src', $column_model );
			$product_shop_url_index                                   = sa_multidimesional_array_search( 'custom/product_shop_url', 'src', $column_model );
			if ( ! empty( $product_visibility_index ) ) {
				$visibility_index = sa_multidimesional_array_search( 'postmeta/meta_key=_visibility/meta_value=_visibility', 'src', $column_model );

				if ( ! empty( $visibility_index ) && isset( $column_model[ $visibility_index ] ) ) {
					unset( $column_model[ $visibility_index ] );
					$column_model = array_values( $column_model );
				}

				$featured_index = sa_multidimesional_array_search( 'postmeta/meta_key=_featured/meta_value=_featured', 'src', $column_model );

				if ( ! empty( $featured_index ) && isset( $column_model[ $featured_index ] ) ) {
					unset( $column_model[ $featured_index ] );
					$column_model = array_values( $column_model );
				}
			}
			$attr_col_index        = sa_multidimesional_array_search( 'custom/product_attributes', 'src', $column_model );
			$attributes_val        = array();
			$attributes_label      = array();
			$attributes_search_val = array();
			$attribute_meta_cols   = array();
			// Load from cache.
			if ( is_null( $attr_col_index ) || ( ! isset( $column_model[ $attr_col_index ]['values'] ) ) || empty( $column_model[ $attr_col_index ]['values'] ) ) {
				// Query to get the attribute name.
				$results_attribute_label = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					"SELECT attribute_name, attribute_label, attribute_type
				FROM {$wpdb->prefix}woocommerce_attribute_taxonomies",
					'ARRAY_A'
				);
				$attribute_label_count   = $wpdb->num_rows;
				if ( $attribute_label_count > 0 ) {
					foreach ( $results_attribute_label as $results_attribute_label1 ) {
						$attributes_label[ 'pa_' . $results_attribute_label1['attribute_name'] ]['lbl']  = $results_attribute_label1['attribute_label'];
						$attributes_label[ 'pa_' . $results_attribute_label1['attribute_name'] ]['type'] = $results_attribute_label1['attribute_type'];
					}
				}
			} else {
				$column_model [ $attr_col_index ]['batch_editable'] = true;
			}
			// Get Product Visibility options.
			$product_visibility_options = array();
			if ( function_exists( 'wc_get_product_visibility_options' ) ) {
				$product_visibility_options = wc_get_product_visibility_options();
			} else { // default values for product_visibility.
				$product_visibility_options = array(
					'visible' => _x( 'Shop and search results', 'shop and search results product visibility option', 'smart-manager-for-wp-e-commerce' ),
					'catalog' => _x( 'Shop only', 'shop only product visibility option', 'smart-manager-for-wp-e-commerce' ),
					'search'  => _x( 'Search results only', 'search results only product visibility option', 'smart-manager-for-wp-e-commerce' ),
					'hidden'  => _x( 'Hidden', 'hidden product visibility option', 'smart-manager-for-wp-e-commerce' ),
				);
			}
			foreach ( $column_model as $key => &$column ) {
				if ( empty( $column['src'] ) ) {
					continue;
				}
				$src          = $column['src'];
				$src_exploded = explode( '/', $column['src'] );
				if ( count( $src_exploded ) < 2 ) {
					$src = $column['src'];
				}
				if ( count( $src_exploded ) > 2 ) {
					$col_table = $src_exploded[0];
					$cond      = explode( '=', $src_exploded[1] );

					if ( 2 === count( $cond ) ) {
						$src = $cond[1];
					}
				} else {
					$src       = $src_exploded[1];
					$col_table = $src_exploded[0];
				}
				$column = apply_filters( 'sa_manager_dashboard_columns', $column, $src, $visible_columns, $dashboard_model_saved );
				if ( ! empty( $src ) ) {
					if ( ( 'pa_' === substr( $src, 0, 3 ) && 'terms' === $col_table ) ) {
						$attr_name     = substr( $src, 3 );
						$attr_name_src = 'pa_' . $attr_name;
						if ( 'pa_' === substr( $src, 0, 3 ) && 'terms' === $col_table && ! empty( $attributes_val[ $attr_name_src ] ) ) {
							$attributes_val [ $attr_name_src ]['val'] = $column['values'];
						} else {
							$attributes_val [ $src ]         = array();
							$attributes_val [ $src ]['lbl']  = ( ! empty( $attributes_label[ $src ]['lbl'] ) ) ? $attributes_label[ $src ]['lbl'] : $src;
							$attributes_val [ $src ]['val']  = ( ! empty( $column['values'] ) ) ? $column['values'] : array();
							$attributes_val [ $src ]['type'] = ( ! empty( $attributes_label[ $src ]['type'] ) ) ? $attributes_label[ $src ]['type'] : $src;
							unset( $column_model[ $key ] );
							$column_model = array_values( $column_model );
						}
						// code for search columns.
						$attributes_search_val[ $attr_name_src ] = ! empty( $column['search_values'] ) ? $column['search_values'] : array();
						$column['type']                          = $this->plugin_sku . '.multilist';
					} elseif ( ( false !== strpos( $src, 'attribute_pa' ) && 'postmeta' === $col_table ) ) {
						$attribute_meta_cols[ substr( $src, 10 ) ] = $key;
					} elseif ( empty( $dashboard_model_saved ) ) {
						if ( 'product_cat' === $src ) {
							$column['type']     = $this->plugin_sku . '.multilist';
							$column['editable'] = false;
							$column['name']     = _x( 'Category', 'Product category', 'smart-manager-for-wp-e-commerce' );
							$column['key']      = _x( 'Category', 'Product category', 'smart-manager-for-wp-e-commerce' );
						} elseif ( 'product_type' === $src ) {
							$column['type'] = 'dropdown';
						} elseif ( in_array( $src, $numeric_columns, true ) ) {
							$column['type']   = 'numeric';
							$column['editor'] = 'customNumericEditor';
						} elseif ( in_array( $src, $integer_columns, true ) ) {
							$column['type']          = 'numeric';
							$column['editor']        = 'customNumericEditor';
							$column['decimalPlaces'] = ( has_filter( 'woocommerce_stock_amount', 'floatval' ) && '_stock' === $src ) ? 13 : 0; // Compat for Decimal Product Quantity Plugins.
						} elseif ( in_array( $src, $custom_numeric_columns, true ) ) {
							$column['type']      = 'text';
							$column['editor']    = $column['type'];
							$column['validator'] = 'customNumericTextEditor';
							switch ( $src ) {
								case '_price':
									$column['batch_editable'] = false;
									break;
							}
						} elseif ( in_array( $src, $date_columns, true ) ) {
							$column['type']                             = $this->plugin_sku . '.date';
							$column['editor']                           = $column['type'];
							$column['date_type']                        = 'timestamp';
							$column['is_utc']                           = true;
							$column['is_display_date_in_site_timezone'] = true;
						} elseif ( '_visibility' === $src ) {
							$column['type'] = 'dropdown';
							// get the custom product_visibility using woo function.
							$column ['values']        = $product_visibility_options;
							$column ['search_values'] = array();
							if ( ! empty( $column ['values'] ) ) {
								foreach ( $column ['values'] as $key => $value ) {
									$column['search_values'][] = array(
										'key'   => $key,
										'value' => $value,
									);
								}
							}
						} elseif ( '_tax_status' === $src ) {
							$column['type']             = 'dropdown';
							$column ['values']          = array(
								'taxable'  => _x( 'Taxable', 'taxable tax status', 'smart-manager-for-wp-e-commerce' ),
								'shipping' => _x( 'Shipping only', 'shipping only tax status', 'smart-manager-for-wp-e-commerce' ),
								'none'     => _x( 'None', 'none tax status', 'smart-manager-for-wp-e-commerce' ),
							);
							$column ['search_values']   = array();
							$column['search_values'][0] = array(
								'key'   => 'taxable',
								'value' => _x( 'Taxable', 'taxable tax status', 'smart-manager-for-wp-e-commerce' ),
							);
							$column['search_values'][1] = array(
								'key'   => 'shipping',
								'value' => _x( 'Shipping only', 'shipping only tax status', 'smart-manager-for-wp-e-commerce' ),
							);
							$column['search_values'][2] = array(
								'key'   => 'none',
								'value' => _x( 'None', 'none tax status', 'smart-manager-for-wp-e-commerce' ),
							);
						} elseif ( '_stock_status' === $src ) {
							$column['type'] = 'dropdown';
							// get the custom _stock_status using woo function.
							if ( function_exists( 'wc_get_product_stock_status_options' ) ) {
								$column ['values'] = wc_get_product_stock_status_options();
							} else { // default values for _stock_status.
								$column ['values'] = array(
									'instock'     => _x( 'In stock', 'in stock status', 'smart-manager-for-wp-e-commerce' ),
									'outofstock'  => _x( 'Out of stock', 'out of stock status', 'smart-manager-for-wp-e-commerce' ),
									'onbackorder' => _x( 'On backorder', 'on backorder status', 'smart-manager-for-wp-e-commerce' ),
								);
							}
							$column ['search_values'] = array();
							if ( ! empty( $column ['values'] ) ) {
								foreach ( $column ['values'] as $key => $value ) {
									$column['search_values'][] = array(
										'key'   => $key,
										'value' => $value,
									);
								}
							}
							$color_codes          = array(
								'green' => array( 'instock' ),
								'red'   => array( 'outofstock' ),
								'blue'  => array( 'onbackorder' ),
							);
							$column['colorCodes'] = apply_filters( $this->plugin_sku . '_' . $this->dashboard_key . '' . $src . '_color_codes', $color_codes );
						} elseif ( '_tax_class' === $src ) {
							$column['type'] = 'dropdown';
							// get the custom tax status using woo function.
							if ( function_exists( 'wc_get_product_tax_class_options' ) ) {
								$column ['values'] = wc_get_product_tax_class_options();
							} else { // default values for tax_status.
								$column ['values'] = array(
									''             => _x( 'Standard', 'standard tax status', 'smart-manager-for-wp-e-commerce' ),
									'reduced-rate' => _x( 'Reduced Rate', 'reduced rate tax status', 'smart-manager-for-wp-e-commerce' ),
									'zero-rate'    => _x( 'Zero Rate', 'zero rate tax status', 'smart-manager-for-wp-e-commerce' ),
								);
							}
							$column ['search_values'] = array();
							if ( ! empty( $column ['values'] ) ) {
								foreach ( $column ['values'] as $key => $value ) {
									$column['search_values'][] = array(
										'key'   => $key,
										'value' => $value,
									);
								}
							}
						} elseif ( '_backorders' === $src ) {
							$column['type'] = 'dropdown';
							// get the custom _backorders using woo function.
							if ( function_exists( 'wc_get_product_backorder_options' ) ) {
								$column ['values'] = wc_get_product_backorder_options();
							} else { // default values for _backorders.
								$column ['values'] = array(
									'no'     => _x( 'Do Not Allow', 'do not allow backorder status', 'smart-manager-for-wp-e-commerce' ),
									'notify' => _x( 'Allow, but notify customer', 'allow but notify customer backorder status', 'smart-manager-for-wp-e-commerce' ),
									'yes'    => _x( 'Allow', 'allow backorder status', 'smart-manager-for-wp-e-commerce' ),
								);
							}
							$column ['search_values'] = array();
							if ( ! empty( $column ['values'] ) ) {
								foreach ( $column ['values'] as $key => $value ) {
									$column['search_values'][] = array(
										'key'   => $key,
										'value' => $value,
									);
								}
							}
							$color_codes          = array(
								'green' => array( 'yes', 'notify' ),
								'red'   => array( 'no' ),
								'blue'  => array(),
							);
							$column['colorCodes'] = apply_filters( $this->plugin_sku . '_' . $this->dashboard_key . '' . $src . '_color_codes', $color_codes );

						} elseif ( 'product_shipping_class' === $src ) {
							$column['type'] = 'dropdown';
							if ( empty( $column ['values'] ) ) {
								$column ['values'] = array();
							}
							if ( empty( $column ['search_values'] ) ) {
								$column ['search_values'] = array();
							}
							$column ['values'] = array_replace( array( '' => _x( 'No shipping class', 'no shipping class', 'smart-manager-for-wp-e-commerce' ) ), $column ['values'] );
							$no_shipping_class = array(
								'key'   => '',
								'value' => _x( 'No shipping class', 'no shipping class', 'smart-manager-for-wp-e-commerce' ),
							);
							if ( false === array_search( $no_shipping_class, $column['search_values'], true ) ) {
								$column['search_values'][] = $no_shipping_class;
							}
						} elseif ( '_sku' === $src ) {
							$column ['name']   = _x( 'SKU', 'SKU column name', 'smart-manager-for-wp-e-commerce' );
							$column ['key']    = _x( 'SKU', 'SKU column key', 'smart-manager-for-wp-e-commerce' );
							$column ['type']   = 'text';
							$column ['editor'] = 'text';
						} elseif ( 'post_title' === $src ) {
							$column ['name'] = _x( 'Name', 'post title column name', 'smart-manager-for-wp-e-commerce' );
							$column ['key']  = _x( 'Name', 'title column key', 'smart-manager-for-wp-e-commerce' );
						} elseif ( 'post_name' === $src ) {
							$column ['name'] = _x( 'Slug', 'slug column name', 'smart-manager-for-wp-e-commerce' );
							$column ['key']  = _x( 'Slug', 'slug column key', 'smart-manager-for-wp-e-commerce' );
						} elseif ( 'post_content' === $src ) {
							$column ['name'] = _x( 'Description', 'description column name', 'smart-manager-for-wp-e-commerce' );
							$column ['key']  = _x( 'Description', 'description column key', 'smart-manager-for-wp-e-commerce' );
						} elseif ( 'post_excerpt' === $src ) {
							$column ['name'] = _x( 'Additional Description', 'additional description column name', 'smart-manager-for-wp-e-commerce' );
							$column ['key']  = _x( 'Additional Description', 'additional description column key', 'smart-manager-for-wp-e-commerce' );
						} elseif ( 'attribute_pa' !== substr( $src, 0, 12 ) && 'attribute_' === substr( $src, 0, 10 ) ) {
							$column ['batch_editable'] = false;
						} elseif ( '_default_attributes' === $src ) {
							$column ['batch_editable'] = true;
						} elseif ( '_product_attributes' === $src ) {
							$column ['batch_editable'] = false;
						} elseif ( '_product_url' === $src ) {
							$column ['name'] = _x( 'External Url', 'url column name', 'smart-manager-for-wp-e-commerce' );
							$column ['key']  = _x( 'External Url', 'url column key', 'smart-manager-for-wp-e-commerce' );
						} elseif ( '_product_image_gallery' === $src ) {
							$column ['type']           = $this->plugin_sku . '.multipleImage';
							$column ['editable']       = false;
							$column ['editor']         = false;
							$column ['batch_editable'] = true;
						}
						if ( 'dropdown' === $column['type'] ) {
							$column ['selectOptions'] = $column['values'];
							$column ['editor']        = 'select';
							$column ['renderer']      = 'selectValueRenderer';
						}

						// Code for handling color codes for 'stock' field.
						if ( '_stock' === $src ) {
							$wc_low_stock_threshold = absint( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );
							$color_codes            = array(
								'green'  => array( 'min' => ( $wc_low_stock_threshold + 1 ) ),
								'red'    => array( 'max' => 0 ),
								'yellow' => array(
									'min' => 1,
									'max' => $wc_low_stock_threshold,
								),
							);
							$column['colorCodes']   = apply_filters( $this->plugin_sku . '_' . $this->dashboard_key . '' . $src . '_color_codes', $color_codes );
						}
					}
				}
			}
			$index = 0;
			if ( empty( $attr_col_index ) ) {
				$index = count( $column_model );
				// Code for including custom columns for product dashboard.
				$column_model [ $index ]                   = array();
				$column_model [ $index ]['src']            = 'custom/product_attributes';
				$column_model [ $index ]['data']           = sanitize_title( str_replace( '/', '_', $column_model [ $index ]['src'] ) ); // generate slug using the WordPress function if not given.
				$column_model [ $index ]['name']           = _x( 'Attributes', 'attributes column', 'smart-manager-for-wp-e-commerce' );
				$column_model [ $index ]['key']            = $column_model [ $index ]['name'];
				$column_model [ $index ]['type']           = $this->plugin_sku . '.longstring';
				$column_model [ $index ]['batch_editable'] = true;
				// Code for assigning attr. values.
				$column_model [ $index ]['values']    = $attributes_val;
				$column_model[ $index ]['editable']   = false;
				$column_model[ $index ]['searchable'] = false;
				$column_model[ $index ]['width']      = 100;
				$column_model[ $index ]['save_state'] = true;
				$column_model[ $index ]['wordWrap']   = false; // For disabling word-wrap.
				if ( empty( $dashboard_model_saved ) ) {
					$position = array_search( 'product_attributes', $visible_columns, true );
					if ( false !== $position ) {
						$column_model[ $index ]['position'] = $position + 1;
						$column_model[ $index ]['hidden']   = false;
					} else {
						$column_model[ $index ]['hidden'] = true;
					}
				}
				$column_model[ $index ]['allow_showhide'] = true;
				$column_model[ $index ]['exportable']     = true;
			} elseif ( empty( $column_model [ $attr_col_index ]['values'] ) ) {
				$column_model [ $attr_col_index ]['values'] = $attributes_val; // Code for assigning attr. values.
			}
			// code for creating search columns for attributes.
			if ( ! empty( $attributes_search_val ) ) {
				foreach ( $attributes_search_val as $key => $value ) {
					++$index;
					// Code for including custom columns for product dashboard.
					$column_model [ $index ]                   = array();
					$column_model [ $index ]['src']            = 'terms/attribute_' . $key;
					$column_model [ $index ]['data']           = sanitize_title( str_replace( '/', '_', $column_model [ $index ]['src'] ) ); // generate slug using the WordPress function if not given.
					$column_model [ $index ]['name']           = _x( 'Attributes', 'attributes column name', 'smart-manager-for-wp-e-commerce' ) . ': ' . substr( $key, 3 );
					$column_model [ $index ]['key']            = $column_model [ $index ]['name'];
					$column_model [ $index ]['type']           = 'dropdown';
					$column_model [ $index ]['editable']       = false;
					$column_model [ $index ]['batch_editable'] = false;
					$column_model [ $index ]['table_name']     = $wpdb->prefix . 'terms';
					$column_model [ $index ]['col_name']       = 'attribute_' . $key;
					// Code for assigning attr. values.
					$column_model [ $index ]['values'] = array();
				}
				++$index;
				// Code for including custom attribute column for product dashboard.
				$column_model [ $index ]                   = array();
				$column_model [ $index ]['src']            = 'postmeta/meta_key=_product_attributes/meta_value=_product_attributes';
				$column_model [ $index ]['data']           = sanitize_title( str_replace( '/', '_', $column_model [ $index ]['src'] ) ); // generate slug using the WordPress function if not given.
				$column_model [ $index ]['name']           = _x( 'Attributes: custom', 'custom attributes column name', 'smart-manager-for-wp-e-commerce' );
				$column_model [ $index ]['key']            = $column_model [ $index ]['name'];
				$column_model [ $index ]['type']           = 'text';
				$column_model [ $index ]['hidden']         = true;
				$column_model [ $index ]['editable']       = false;
				$column_model [ $index ]['batch_editable'] = false;
				$column_model [ $index ]['table_name']     = $wpdb->prefix . 'postmeta';
				$column_model [ $index ]['col_name']       = '_product_attributes';
				// Code for assigning attr. values.
				$column_model [ $index ]['values'] = array();
			}
			if ( ! empty( $product_visibility_index ) && empty( $dashboard_model_saved ) ) {
				$product_visibility_index = sa_multidimesional_array_search( 'terms/product_visibility', 'src', $column_model );
				if ( isset( $column_model[ $product_visibility_index ] ) ) {
					unset( $column_model[ $product_visibility_index ] );
					$column_model             = array_values( $column_model ); // added for recalculating the indexes of the array.
					$product_visibility_index = sa_multidimesional_array_search( 'terms/product_visibility', 'src', $column_model );
				}
				$index = count( $column_model );
				$index++;
				if ( empty( $product_visibility_index ) ) {
					// Code for including custom columns for product dashboard.
					$column_model [ $index ]                   = array();
					$column_model [ $index ]['src']            = 'terms/product_visibility';
					$column_model [ $index ]['data']           = sanitize_title( str_replace( '/', '_', $column_model [ $index ]['src'] ) ); // generate slug using the WordPress function if not given.
					$column_model [ $index ]['name']           = _x( 'Catalog Visibility', 'catalog visibility column name', 'smart-manager-for-wp-e-commerce' );
					$column_model [ $index ]['key']            = $column_model [ $index ]['name'];
					$column_model [ $index ]['type']           = 'dropdown';
					$column_model [ $index ]['editable']       = true;
					$column_model [ $index ]['batch_editable'] = true;
					$column_model [ $index ]['table_name']     = $wpdb->prefix . 'terms';
					$column_model [ $index ]['col_name']       = 'product_visibility';
					// Code for assigning attr. values.
					$column_model [ $index ]['values']         = $product_visibility_options;
					$column_model [ $index ] ['selectOptions'] = $column_model [ $index ]['values'];
					$column_model [ $index ] ['editor']        = 'select';
					$column_model [ $index ] ['renderer']      = 'selectValueRenderer';
				}
				$featured_index = sa_multidimesional_array_search( 'terms/product_visibility_featured', 'src', $column_model );
				if ( empty( $featured_index ) ) {
					++$index;
					$column_model [ $index ]                      = array();
					$column_model [ $index ]['src']               = 'terms/product_visibility_featured';
					$column_model [ $index ]['data']              = sanitize_title( str_replace( '/', '_', $column_model [ $index ]['src'] ) ); // generate slug using the WordPress function if not given.
					$column_model [ $index ]['name']              = _x( 'Featured', 'featured column name', 'smart-manager-for-wp-e-commerce' );
					$column_model [ $index ]['key']               = $column_model [ $index ]['name'];
					$column_model [ $index ]['type']              = 'checkbox';
					$column_model [ $index ]['editable']          = true;
					$column_model [ $index ]['batch_editable']    = true;
					$column_model [ $index ]['table_name']        = $wpdb->prefix . 'terms';
					$column_model [ $index ]['col_name']          = 'product_visibility_featured';
					$column_model [ $index ]['checkedTemplate']   = 'yes';
					$column_model [ $index ]['uncheckedTemplate'] = 'no';
					// Code for assigning attr. values.
					$column_model [ $index ]['values'] = array();
				}
			}
			if ( ! empty( $attribute_meta_cols ) ) {
				foreach ( $attribute_meta_cols as $src => $index ) {
					if ( ! empty( $column_model[ $index ] ) && ! empty( $attributes_search_val[ $src ] ) ) {
						$column_model[ $index ]['values']        = array();
						$column_model[ $index ]['search_values'] = $attributes_search_val[ $src ];
						foreach ( $column_model[ $index ]['search_values'] as $obj ) {
							$column_model[ $index ]['values'][ $obj['key'] ] = $obj['value'];
						}
						$column_model[ $index ]['type']          = 'dropdown';
						$column_model[ $index ]['strict']        = true;
						$column_model[ $index ]['allowInvalid']  = false;
						$column_model[ $index ]['selectOptions'] = $column_model[ $index ]['values'];
						$column_model[ $index ]['editor']        = 'select';
						$column_model[ $index ]['renderer']      = 'selectValueRenderer';
					}
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
		 * Function for updating product visibility.
		 *
		 * @param int    $id selected id.
		 * @param string $visibility selected visibility name.
		 * @return boolean true if updated successfully else false
		 */
		public function set_product_visibility( $id = 0, $visibility = '' ) {
			if ( empty( $id ) || empty( $visibility ) ) {
				return;
			}
			$visibility = strtoupper( $visibility );
			$result     = array();
			if ( strtoupper( 'visible' ) === $visibility ) {
				$result = wp_remove_object_terms( $id, array( 'exclude-from-search', 'exclude-from-catalog' ), 'product_visibility' );
			} else {
				$terms = '';
				if ( strtoupper( 'catalog' ) === $visibility ) {
					$terms = 'exclude-from-search';
				} elseif ( strtoupper( 'search' ) === $visibility ) {
					$terms = 'exclude-from-catalog';
				} elseif ( strtoupper( 'hidden' ) === $visibility ) {
					$terms = array( 'exclude-from-search', 'exclude-from-catalog' );
				}

				if ( ! empty( $terms ) ) {
					wp_remove_object_terms( $id, array( 'exclude-from-search', 'exclude-from-catalog' ), 'product_visibility' );
					$result = wp_set_object_terms( $id, $terms, 'product_visibility', true );
				}
			}
			return ( ( ! empty( $result ) ) && ( ! is_wp_error( $result ) ) ) ? true : false;
		}
	}
}
