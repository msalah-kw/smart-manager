<?php
/**
 * This file contains commonly used utility functions.
 *
 * @package common-core/
 * @since       8.64.0
 * @version     8.67.0
 */

if ( ! function_exists( 'sa_variable_parent_sync_price' ) ) {
	/**
	 * Sync price for variable parent product
	 *
	 * @param array $ids product ids array.
	 * @return void
	 */
	function sa_variable_parent_sync_price( $ids = array() ) {
		if ( empty( $ids ) ) {
			return;
		}
		foreach ( $ids as $id ) {
			$parent_id = wp_get_post_parent_id( $id );
			if ( $parent_id > 0 ) {
				if ( class_exists( 'WC_Product_Variable' ) && is_callable( array( 'WC_Product_Variable', 'sync' ) ) ) {
					WC_Product_Variable::sync( $parent_id );
					delete_transient( 'wc_product_children_' . $parent_id ); // added in woo24.
				}
			}
		}
	}
}


if ( ! function_exists( 'sa_update_stock_status' ) ) {
	/**
	 * Function for updating stock status value
	 *
	 * @param int    $id product id.
	 * @param string $update_column update column.
	 * @param mixed  $update_value update value.
	 * @return boolean updated result
	 */
	function sa_update_stock_status( $id = 0, $update_column = '', $update_value = '' ) {
		if ( empty( $id ) ) {
			return false;
		}
		$parent_id                 = wp_get_post_parent_id( $id );
		$woo_version               = ( defined( 'WOOCOMMERCE_VERSION' ) ) ? WOOCOMMERCE_VERSION : ( method_exists( 'WC', 'version' ) ? WC()->version : '0.0' );
		$woo_prod_obj_stock_status = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $id ) ) : null;
		if ( empty( $woo_prod_obj_stock_status ) || ! $woo_prod_obj_stock_status instanceof WC_Product ) {
			return false;
		}
		switch ( $update_column ) {
			case '_stock':
				if ( function_exists( 'wc_update_product_stock' ) ) {
					$prod = wc_get_product( $id );
					$prod->set_stock_quantity( $update_value );
					$result = wc_update_product_stock( $prod, $update_value );
					return ( ( empty( $result ) && 0 === $result ) || ( ( ! empty( $result ) ) && ( ! is_wp_error( $result ) ) ) ) ? true : false;
				} elseif ( 'yes' === get_post_meta( $id, '_manage_stock', true ) ) { // check if manage stock is enabled or not.
					if ( version_compare( $woo_version, '2.4', '>=' ) ) {
						if ( $parent_id > 0 ) {
							$stock_status_option = get_post_meta( $id, 'stock_status', true );
							$stock_status        = ( ! empty( $stock_status_option ) ) ? $stock_status_option : '';
							if ( is_callable( array( $woo_prod_obj_stock_status, 'set_stock_status' ) ) ) {
								$woo_prod_obj_stock_status->set_stock_status( $stock_status );
							}
						} elseif ( is_callable( array( $woo_prod_obj_stock_status, 'check_stock_status' ) ) ) {
							$woo_prod_obj_stock_status->check_stock_status();
						}
					} elseif ( is_callable( array( $woo_prod_obj_stock_status, 'set_stock' ) ) ) {
						$result = $woo_prod_obj_stock_status->set_stock( $update_value );
						return ( ( 0 === $result ) || ( $result && ! is_wp_error( $result ) ) ) ? true : false;
					}
				}
				break;
			case '_backorders':
				$backorders = is_callable( array( $woo_prod_obj_stock_status, 'get_backorders' ) ) ? $woo_prod_obj_stock_status->get_backorders() : 'no';
				if ( ! empty( $backorders ) && is_callable( array( $woo_prod_obj_stock_status, 'set_backorders' ) ) ) {
					$woo_prod_obj_stock_status->set_backorders( $backorders );
				}
				$result = $woo_prod_obj_stock_status->save();
				return ( ( 0 === $result ) || ( $result && ! is_wp_error( $result ) ) ) ? true : false;
		}
		return false;
	}
}

if ( ! function_exists( 'sa_array_recursive_diff' ) ) {
	/**
	 * Recursively calculates the difference between two arrays.
	 *
	 * @param array $array1 The first array to compare.
	 * @param array $array2 The second array to compare.
	 * @return array $array_diff An array containing the differences between the two arrays
	 */
	function sa_array_recursive_diff( $array1 = array(), $array2 = array() ) {
		$array_diff = array();
		foreach ( $array1 as $key => $value ) {
			if ( array_key_exists( $key, $array2 ) ) {
				if ( is_array( $value ) ) {
					$recursive_diff = sa_array_recursive_diff( $value, $array2[ $key ] );
					if ( count( $recursive_diff ) ) {
						$array_diff[ $key ] = $recursive_diff;
					}
				} else {
					if ( $value !== $array2[ $key ] ) {
						$array_diff[ $key ] = $value;
					}
				}
			} else {
				$array_diff[ $key ] = $value;
			}
		}
		return $array_diff;
	}
}

if ( ! function_exists( 'sa_multidimesional_array_search' ) ) {
	/**
	 * Searches for a value in a specified index of a multidimensional array and returns the corresponding key.
	 *
	 * @param string $id The value to search for.
	 * @param string $index The index/key within each sub-array to search.
	 * @param array  $array The multidimensional array to search within.
	 * @return mixed|null The key of the first occurrence of the value in the specified index, or null if the value is not found or if the input array is empty
	 */
	function sa_multidimesional_array_search( $id = '', $index = '', $array = array() ) {
		if ( empty( $array ) ) {
			return null;
		}
		foreach ( $array as $key => $val ) {
			if ( empty( $val[ $index ] ) ) {
				continue;
			}
			if ( $id == $val[$index] ) { // phpcs:ignore
				return $key;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'sa_woo_get_price' ) ) {
	/**
	 * Calculates the effective price for a WooCommerce product based on regular price, sale price, and sale price dates.
	 *
	 * @param int    $regular_price regular price value.
	 * @param int    $sale_price sale price value.
	 * @param string $sale_price_dates_from The start date of the sale price. Default is '0000-00-00 00:00:00'.
	 * @param string $sale_price_dates_to The end date of the sale price. Default is '0000-00-00 00:00:00'.
	 * @return int|float The effective price of the product.
	 */
	function sa_woo_get_price( $regular_price = 0, $sale_price = 0, $sale_price_dates_from = '0000-00-00 00:00:00', $sale_price_dates_to = '0000-00-00 00:00:00' ) {
		// Get price if on sale.
		$price     = ( $sale_price && empty( $sale_price_dates_to ) && empty( $sale_price_dates_from ) ) ? $sale_price : $regular_price;
		$from_date = ( is_numeric( $sale_price_dates_from ) && (int) $sale_price_dates_from === $sale_price_dates_from ) ? (int) $sale_price_dates_from : strtotime( $sale_price_dates_from );
		$to_date   = ( is_numeric( $sale_price_dates_to ) && (int) $sale_price_dates_to === $sale_price_dates_to ) ? (int) $sale_price_dates_to : strtotime( $sale_price_dates_to );
		if ( ! empty( $from_date ) && $from_date < strtotime( 'NOW' ) ) {
			$price = $sale_price;
		}
		if ( ! empty( $to_date ) && $to_date < strtotime( 'NOW' ) ) {
			$price = $regular_price;
		}
		return $price;
	}
}

if ( ! function_exists( 'sa_get_current_variation_title' ) ) {
	/**
	 * Function to fetch the variation current post title
	 *
	 * @param array $pids product ids array.
	 * @return array results array
	 */
	function sa_get_current_variation_title( $pids = array() ) {
		if ( empty( $pids ) || ( ! is_array( $pids ) ) ) {
			return array();
		}
		global $wpdb;
		$variable_taxonomy_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT taxonomy.term_taxonomy_id as term_taxonomy_id
															FROM {$wpdb->prefix}terms as terms
																JOIN {$wpdb->prefix}term_taxonomy as taxonomy 
																ON (taxonomy.term_id = terms.term_id
																AND taxonomy.taxonomy = %s)
															WHERE terms.slug IN ('variable', 'variable-subscription')",
				'product_type'
			)
		);
		if ( empty( $variable_taxonomy_ids ) || ( ! is_array( $variable_taxonomy_ids ) ) ) {
			return array();
		}
		// query to get the parent ids old title.
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, post_title 
								FROM {$wpdb->posts} as p
								JOIN {$wpdb->prefix}term_relationships as tp
									ON(tp.object_id = p.id
									AND p.post_type = %s)
									WHERE p.id IN (" . implode( ',', array_fill( 0, count( $pids ), '%d' ) ) . ')
									AND tp.term_taxonomy_id IN (' . implode( ',', array_fill( 0, count( $variable_taxonomy_ids ), '%d' ) ) . ')',
				array_merge( array( 'product' ), $pids, $variable_taxonomy_ids )
			),
			ARRAY_A
		);
	}
}

if ( ! function_exists( 'sa_sync_variation_title' ) ) {
	/**
	 * Function to sync the variations title when the parent product title is updated
	 *
	 * @param array $new_title_update_case array of new variation title update case.
	 * @param array $ids post parent ids array.
	 * @return int|false The number of rows updated, or false on error.
	 */
	function sa_sync_variation_title( $new_title_update_case = array(), $ids = array() ) {
		if ( empty( $new_title_update_case ) || empty( $ids ) || ! is_array( $new_title_update_case ) || ! is_array( $ids ) ) {
			return false;
		}
		global $wpdb;
		$case_sql_parts = array();
		$params         = array();
		foreach ( $new_title_update_case as $id => $title ) {
			$case_sql_parts[] = 'WHEN %d THEN %s';
			$params[]         = $id;
			$params[]         = $title;
		}
		$params[] = 'product_variation';
		$wpdb1    = $wpdb; // phpcs:ignore WordPress.DB.GlobalVariablesOverride.Prohibited
		return $wpdb1->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb1->prepare(
				"
				UPDATE {$wpdb1->posts}
				SET post_title = CASE ID " . implode( ' ', $case_sql_parts ) . ' END
				WHERE post_type = %s
				AND post_parent IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')
			',
				array_merge( $params, $ids )
			)
		);
	}
}

if ( ! function_exists( 'sa_update_price_meta' ) ) {
	/**
	 * Updates the '_price' meta field for WooCommerce products based on '_regular_price' and '_sale_price' meta fields.
	 *
	 * @param array $ids An array of post IDs for which to update the price meta field.
	 * @return void
	 */
	function sa_update_price_meta( $ids = array() ) {
		if ( empty( $ids ) || ( ! is_array( $ids ) ) ) {
			return;
		}
		global $wpdb;
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id,
						GROUP_CONCAT( meta_key ORDER BY meta_id SEPARATOR '##' ) AS meta_keys, 
						GROUP_CONCAT( meta_value ORDER BY meta_id SEPARATOR '##' ) AS meta_values 
					FROM {$wpdb->prefix}postmeta 
					WHERE meta_key IN ( %s, %s, %s, %s )
						AND post_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')
					GROUP BY post_id',
				array_merge( array( '_regular_price', '_sale_price', '_sale_price_dates_from', '_sale_price_dates_to' ), $ids )
			),
			'ARRAY_A'
		);
		if ( empty( $results ) ) {
			return;
		}
		$update_cases      = array();
		$ids_to_be_updated = array();
		foreach ( $results as $result ) {
			$meta_keys   = explode( '##', $result['meta_keys'] );
			$meta_values = explode( '##', $result['meta_values'] );
			if ( count( $meta_keys ) === count( $meta_values ) ) {
				$keys_values   = array_combine( $meta_keys, $meta_values );
				$from_date     = ( isset( $keys_values['_sale_price_dates_from'] ) ) ? $keys_values['_sale_price_dates_from'] : '';
				$to_date       = ( isset( $keys_values['_sale_price_dates_to'] ) ) ? $keys_values['_sale_price_dates_to'] : '';
				$regular_price = isset( $keys_values['_regular_price'] ) ? trim( $keys_values['_regular_price'] ) : '';
				$sale_price    = isset( $keys_values['_sale_price'] ) ? trim( $keys_values['_sale_price'] ) : '';
				$price         = sa_woo_get_price( $regular_price, $sale_price, $from_date, $to_date );
				$price         = trim( $price ); // For handling when both price and sales price are null.
				$meta_value    = ( ! empty( $price ) ) ? $price : '';
				update_post_meta( $result['post_id'], '_price', $meta_value );
			}
		}
	}
}

if ( ! function_exists( 'sa_update_product_attribute_lookup_table' ) ) {
	/**
	 * Function to update product attribute lookup table
	 *
	 * @param array $product_ids product ids array.
	 * @return void
	 */
	function sa_update_product_attribute_lookup_table( $product_ids = array() ) {
		if ( empty( $product_ids ) ) {
			return;
		}
		$insert_query_values = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$product_attributes = ( is_callable( array( $product, 'get_attributes' ) ) ) ? $product->get_attributes() : array();
			if ( empty( $product_attributes ) ) {
				continue;
			}
			$has_stock = ( ( is_callable( array( $product, 'get_stock_quantity' ) ) && ! empty( $product->get_stock_quantity() ) ) || ( is_callable( array( $product, 'is_in_stock' ) ) && ! empty( $product->is_in_stock() ) ) ) ? 1 : 0;
			foreach ( $product_attributes as $taxonomy_name => $attribute_data ) {
				if ( ( is_object( $attribute_data ) && is_callable( array( $attribute_data, 'get_id' ) ) && empty( $attribute_data->get_id() ) ) || empty( $taxonomy_name ) ) {
					continue;
				}
				$term_ids = ( is_callable( array( $attribute_data, 'get_options' ) ) ) ? $attribute_data->get_options() : array();
				if ( empty( $term_ids ) ) {
					continue;
				}
				$is_variation_attribute = ( is_callable( array( $attribute_data, 'get_variation' ) ) && ! empty( $attribute_data->get_variation() ) ) ? 1 : 0;
				foreach ( $term_ids as $term_id ) {
					if ( empty( $term_id ) ) {
						continue;
					}
					if ( empty( $is_variation_attribute ) ) {
						$insert_query_values[] = array(
							'product_id'             => $product_id,
							'product_or_parent_id'   => $product_id,
							'taxonomy'               => $taxonomy_name,
							'term_id'                => $term_id,
							'is_variation_attribute' => $is_variation_attribute,
							'in_stock'               => $has_stock,
						);
					} else {
						$variation_ids = ( is_callable( array( $product, 'get_children' ) ) ) ? $product->get_children() : array();
						if ( empty( $variation_ids ) ) {
							continue;
						}
						foreach ( $variation_ids as $variation_id ) {
							$insert_query_values[] = array(
								'product_id'             => $variation_id,
								'product_or_parent_id'   => $product_id,
								'taxonomy'               => $taxonomy_name,
								'term_id'                => $term_id,
								'is_variation_attribute' => $is_variation_attribute,
								'in_stock'               => $has_stock,
							);
						}
					}
				}
			}
		}
		sa_delete_attribute_lookup_data( $product_ids );
		if ( ! empty( $insert_query_values ) ) {
			sa_update_attribute_lookup_data( $insert_query_values );
		}
	}
}

if ( ! function_exists( 'sa_delete_attribute_lookup_data' ) ) {
	/**
	 * Function to delete the attribute lookup table data
	 *
	 * @param array $product_ids product ids.
	 * @return void
	 */
	function sa_delete_attribute_lookup_data( $product_ids = array() ) {
		if ( empty( $product_ids ) || ( ! is_array( $product_ids ) ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wc_product_attributes_lookup WHERE product_id IN (" . implode( ',', array_fill( 0, count( $product_ids ), '%d' ) ) . ') OR product_or_parent_id IN (' . implode( ',', array_fill( 0, count( $product_ids ), '%d' ) ) . ')',
				array_merge( $product_ids, $product_ids )
			)
		);
	}
}

if ( ! function_exists( 'sa_update_attribute_lookup_data' ) ) {
	/**
	 * Function for updating attribute lookup table
	 *
	 * @param array $insert_query_values array of insert query values.
	 * @return void
	 */
	function sa_update_attribute_lookup_data( $insert_query_values = array() ) {
		if ( empty( $insert_query_values ) || ! is_array( $insert_query_values ) ) {
			return;
		}
		global $wpdb;
		foreach ( $insert_query_values as $insert_query_value ) { // TODO: optimize this code block.
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}wc_product_attributes_lookup",
				$insert_query_value,
				array(
					'%d',
					'%d',
					'%s',
					'%d',
					'%d',
					'%d',
				)
			);
		}
	}
}


if ( ! function_exists( 'sa_update_post' ) ) {
	/**
	 * Custom function to update post - Compat for WooCommerce Product Stock Alert plugins
	 *
	 * @param int $id post id for which is to be updated.
	 * @return int Post ID on success, 0 on failure.
	 */
	function sa_update_post( $id = 0 ) {
		$id = intval( $id );
		if ( empty( $id ) ) {
			return 0;
		}
		$parent_id = wp_get_post_parent_id( $id );
		return ( empty( $parent_id ) ) ? wp_update_post( array( 'ID' => $id ) ) : wp_update_post( array( 'ID' => $parent_id ) );
	}
}

if ( ! function_exists( 'sa_get_site_timestamp_from_utc_date' ) ) {
	/**
	 * Function to get site timestamp from date passed in UTC timezone
	 *
	 * @param string $date Date string in UTC timezone.
	 * @return int Timestamp in site timezone
	 */
	function sa_get_site_timestamp_from_utc_date( $date = '' ) {
		if ( empty( $date ) ) {
			return $date;
		}
		$offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$date   = ( ! is_numeric( $date ) ) ? strtotime( $date ) : $date;
		return $date + $offset;
	}
}

if ( ! function_exists( 'sa_get_utc_timestamp_from_site_date' ) ) {
	/**
	 * Function to get UTC timestamp from date passed in site timezone
	 *
	 * @param string $date Date string in site timezone.
	 * @return int $timestamp Timestamp in UTC
	 */
	function sa_get_utc_timestamp_from_site_date( $date = '' ) {
		if ( empty( $date ) ) {
			return $date;
		}
		$offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$date   = ( ! is_numeric( $date ) ) ? strtotime( $date ) : $date;
		return $date - $offset;
	}
}

if ( ! function_exists( 'sa_manager_log' ) ) {
	/**
	 * Function to log messages.
	 *
	 * @param string $level   Message type. Valid values: debug, info, notice, warning, error, critical, alert, emergency.
	 * @param string $message The message to log.
	 */
	function sa_manager_log( $level = 'notice', $message = '' ) {
		$is_logging_enabled = get_option( 'sa_manager_enable_logging', 'yes' );
		if ( ( empty( $level ) && empty( $message ) ) || ( 'no' === $is_logging_enabled ) ) {
			return;
		}
		if ( defined( 'WC_PLUGIN_FILE' ) && ! empty( WC_PLUGIN_FILE ) ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->log( $level, $message, array( 'source' => 'smart-manager-for-wp-e-commerce' ) );
			} elseif ( file_exists( plugin_dir_path( WC_PLUGIN_FILE ) . 'includes/class-wc-logger.php' ) ) {
				include_once plugin_dir_path( WC_PLUGIN_FILE ) . 'includes/class-wc-logger.php';
				$logger = new WC_Logger();
				$logger->add( 'smart-manager-for-wp-e-commerce', $message );
			}
		} else {
			error_log('smart-manager-for-wp-e-commerce ' . $message); // phpcs:ignore
		}
	}
}

if ( ! function_exists( 'sa_get_supported_post_types' ) ) {
	/**
	 * Get the list of supported post types.
	 *
	 * @return array List of supported post types.
	 */
	function sa_get_supported_post_types() {
		return apply_filters( 'sa_get_supported_post_types', array( 'product' ) );
	}
}

if ( ! function_exists( 'sa_position_compare' ) ) {
	/**
	 * Compares the 'position' values of two associative arrays.
	 *
	 * @param array $a The first associative array with a 'position' key.
	 * @param array $b The second associative array with a 'position' key.
	 * @return int Comparison result: 0 if equal, -1 if $a < $b, 1 if $a > $b.
	 */
	function sa_position_compare( $a, $b ) {
		if ( $a['position'] == $b['position'] ) { // phpcs:ignore
			return 0;
		}
		if ( $a['position'] < $b['position'] ) {
			return -1;
		}
		return 1;
	}
}
