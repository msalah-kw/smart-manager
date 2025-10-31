<?php
/**
 * This function will update the WC lookup table introduced in WC 3.6 for the edited product fields in SM
 *
 * Since SM 4.2.3
 * For WC 3.6+
 */
function sm_update_product_lookup_table( $product_ids ) {

	if ( empty( $product_ids ) ) {
		return;
	}

	global $wpdb;

	$query = "SELECT post_id, meta_key, meta_value
				FROM {$wpdb->prefix}postmeta 
				WHERE meta_key IN ( '_sku', '_virtual', '_downloadable', '_regular_price', '_sale_price', '_price', '_manage_stock', '_stock', '_stock_status', '_wc_rating_count', '_wc_average_rating', 'total_sales'
				". ( ( !empty( Smart_Manager::$sm_is_woo40 ) ) ? ", '_tax_status', '_tax_class'" : '' ) . ( ( !empty( Smart_Manager::$sm_is_woo92 ) ) ? ", '_global_unique_id'" : '' ) ." ) 
				AND post_id IN (".implode(",", $product_ids).")
					GROUP BY post_id, meta_key";

	$results = $wpdb->get_results( $query, 'ARRAY_A' );

	$sm_cache_update = array();
	$sm_update_wc_lookup_table = array();
	$temp = array();

	// Preparing data
	foreach ( $results as $result ) {

		$meta_key = ( !empty( $result['meta_key'] ) ) ? $result['meta_key'] : '';
		if( empty( $meta_key ) ) {
			continue;
		}
		$meta_value = ( !empty( $result['meta_value'] ) ) ? $result['meta_value'] : '';

		$product_id = absint( $result['post_id'] );
		if( empty( $sm_cache_update[$product_id] ) ) {
			$sm_cache_update[$product_id] = array();
		}

		$price_meta = (array) ( $meta_key == '_price' ? $meta_value : false );

		$sm_cache_update[$product_id]['product_id'] 	= ( empty( $sm_cache_update[$product_id]['product_id'] ) ) ? $product_id : $sm_cache_update[$product_id]['product_id'];
		$sm_cache_update[$product_id]['sku'] 			= ( empty( $sm_cache_update[$product_id]['sku'] ) ) ? ( ( $meta_key == '_sku' ) ? $meta_value : '' ) : $sm_cache_update[$product_id]['sku'];
		$sm_cache_update[$product_id]['virtual'] 		= ( empty( $sm_cache_update[$product_id]['virtual'] ) ) ? ( ( $meta_key == '_virtual' && 'yes' === $meta_value ) ? 1 : 0 ) : $sm_cache_update[$product_id]['virtual'];
		$sm_cache_update[$product_id]['downloadable'] 	= ( empty( $sm_cache_update[$product_id]['downloadable'] ) ) ? ( ( $meta_key == '_downloadable' && 'yes' === $meta_value ) ? 1 : 0 ) : $sm_cache_update[$product_id]['downloadable'];
		$sm_cache_update[$product_id]['min_price'] 		= ( empty( $sm_cache_update[$product_id]['min_price'] ) ) ? ( reset( $price_meta ) ) : $sm_cache_update[$product_id]['min_price'];
		$sm_cache_update[$product_id]['max_price'] 		= ( empty( $sm_cache_update[$product_id]['max_price'] ) ) ? ( end( $price_meta ) ) : $sm_cache_update[$product_id]['max_price'];
		$sm_cache_update[$product_id]['onsale'] 		= ( empty( $sm_cache_update[$product_id]['onsale'] ) ) ? ( wc_format_decimal( ( $meta_key == '_sale_price' && !empty( $meta_value ) ) ? 1 : 0 ) ) : $sm_cache_update[$product_id]['onsale'];
		$sm_cache_update[$product_id]['stock_quantity'] = ( empty( $sm_cache_update[$product_id]['stock_quantity'] ) ) ? ( wc_stock_amount( ( $meta_key == '_stock' ) ? $meta_value : null ) ) : $sm_cache_update[$product_id]['stock_quantity'];
		$sm_cache_update[$product_id]['stock_status'] 	= ( empty( $sm_cache_update[$product_id]['stock_status'] ) ) ? ( ( $meta_key == '_stock_status' ) ? $meta_value : '' ) : $sm_cache_update[$product_id]['stock_status'];
		$sm_cache_update[$product_id]['rating_count'] 	= ( empty( $sm_cache_update[$product_id]['rating_count'] ) ) ? ( ( $meta_key == '_wc_rating_count' && is_array( maybe_unserialize( $meta_value ) ) ) ? array_sum( maybe_unserialize( $meta_value ) ) : 0 ) : $sm_cache_update[$product_id]['rating_count'];
		$sm_cache_update[$product_id]['average_rating'] = ( empty( $sm_cache_update[$product_id]['average_rating'] ) ) ? ( ( $meta_key == '_wc_average_rating' ) ? $meta_value : 0 ) : $sm_cache_update[$product_id]['average_rating'];
		$sm_cache_update[$product_id]['total_sales'] 	= ( empty( $sm_cache_update[$product_id]['total_sales'] ) ) ? ( ( $meta_key == 'total_sales' ) ? $meta_value : 0 ) : $sm_cache_update[$product_id]['total_sales'];
		$sm_cache_update[$product_id]['tax_status'] 	= ( empty( $sm_cache_update[$product_id]['tax_status'] ) ) ? ( ( $meta_key == '_tax_status' ) ? $meta_value : '' ) : $sm_cache_update[$product_id]['tax_status'];
		$sm_cache_update[$product_id]['tax_class'] 	= ( empty( $sm_cache_update[$product_id]['tax_class'] ) ) ? ( ( $meta_key == '_tax_class' ) ? $meta_value : '' ) : $sm_cache_update[$product_id]['tax_class'];
		if ( ! empty( Smart_Manager::$sm_is_woo92 ) ) {
			$sm_cache_update[ $product_id ][ 'global_unique_id' ] 	= ( empty( $sm_cache_update[ $product_id ][ 'global_unique_id' ] ) ) ? ( ( '_global_unique_id' === $meta_key ) ? $meta_value : '' ) : $sm_cache_update[ $product_id ][ 'global_unique_id' ];
		}
		$temp = $sm_cache_update;
		$temp[$product_id]['sku'] = (string) $temp[$product_id]['sku'];
		$temp[$product_id]['stock_status'] = (string) $temp[$product_id]['stock_status'];

		$sm_update_wc_lookup_table[$product_id] = "('".implode( "','", $temp[$product_id] )."')";

	}

	// Updating lookup table
	if ( ! empty( $sm_update_wc_lookup_table ) ) {
		$query = "REPLACE INTO {$wpdb->prefix}wc_product_meta_lookup
					VALUES ";
		$query .= implode( ",", $sm_update_wc_lookup_table );
		$wpdb->query( $query );
	}

	// wp_cache_set for lookup table
	if ( ! empty( $sm_cache_update ) ) {
		foreach ( $sm_cache_update as $update_data ) {
			wp_cache_set( 'lookup_table', $update_data, 'object_' . $update_data['product_id'] );
		}
	}
}
//Function to detect whether a string is timestamp or not
function isTimestamp( $string ) {
    try {
        new DateTime('@' . $string);
    } catch(Exception $e) {
        return false;
    }

    if( $string < strtotime('-30 years') || $string > strtotime('+30 years') ) {
       return false;
    }

	return true;
}

//Function to generate the column state using store model
function sa_sm_generate_column_state( $store_model = array() ) {

	$column_model_transient = array( 'columns' => array(), 'sort_params' => array() );

	if( !empty( $store_model['columns'] ) ) {
		foreach( $store_model['columns'] as $key => $col ) {
			if( empty( $col['hidden'] ) && ! empty( $col['save_state'] ) && ! empty( $col['data'] ) ) {
				$column_model_transient['columns'][ $col['data'] ] = array( 'width' => ( !empty( $col['width'] ) ? $col['width'] : '' ),
																			'position' => ( !empty( $col['position'] ) ? $col['position'] : '' ) );
			}
		}
	}

	$column_model_transient['sort_params'] = ( !empty( $store_model['sort_params'] ) ) ? $store_model['sort_params'] : array();
	if ( ! empty( $store_model['search_params'] ) ) {
		$column_model_transient['search_params'] = $store_model['search_params'];
	}
	if( isset( $store_model['treegrid'] ) ) {
		$column_model_transient['treegrid'] = $store_model['treegrid'];
	}

	$column_model_transient = apply_filters( 'sm_generate_column_state', $column_model_transient, $store_model );

	return $column_model_transient;
}

//Function to update recent dashboards
function sa_sm_update_recent_dashboards( $meta_key = 'post_types', $slug = '' ) {
	if( empty( $meta_key ) || empty( $slug ) ) {
		return;
	}

	$recent_dashboards = get_user_meta( get_current_user_id(), 'sa_sm_recent_'.$meta_key, true );
	if( ! empty( $recent_dashboards ) ){
		$index = array_search( $slug, $recent_dashboards );
		if( false !== $index ) {
			array_splice( $recent_dashboards, $index, 1 );
		}
		array_unshift( $recent_dashboards, $slug );
		$recent_dashboards = array_slice( $recent_dashboards, 0, 3 );
	} else {
		$recent_dashboards = array( $slug );
	}

	if( is_array( $recent_dashboards ) ) {
		update_user_meta( get_current_user_id(), 'sa_sm_recent_'.$meta_key, $recent_dashboards );
	}
}

/**
 * Function to edit previous value format for particular column for storing it in task details table
 *
 * @param array $args array has update_column, data_type & prev_val.
 * @return returns the formatted previous value
 */
function sa_sm_format_prev_val( $args = array() ) {
	if ( empty( $args ) || empty( $args['update_column'] ) || empty( $args['col_data_type'] ) ) {
		return $args['prev_val'];
	}
	switch ( $args['col_data_type'] ) {
		case ( ( ( ! empty( $args['col_data_type']['data_cols_serialized'] ) ) && ( in_array( $args['update_column'], $args['col_data_type']['data_cols_serialized'], true ) ) ) ):
		case ( ! empty( $args['col_data_type'] ) && ( 'sm.serialized' === $args['col_data_type'] ) ):
			return maybe_serialize( $args['prev_val'] );
		case ( ( ! empty( $args['col_data_type']['data_cols_multiselect'] ) ) && ( in_array( $args['update_column'], $args['col_data_type']['data_cols_multiselect'], true ) ) && ( is_array( $args['prev_val'] ) ) ):
		case ( ( ! empty( $args['col_data_type']['data_cols_list'] ) ) && ( in_array( $args['update_column'], $args['col_data_type']['data_cols_list'], true ) ) && ( is_array( $args['prev_val'] ) ) ):
		case ( ( 'sm.multilist' === $args['col_data_type'] || 'dropdown' === $args['col_data_type'] ) ):
			return ( is_array( $args['prev_val'] ) ) ? implode( ',', $args['prev_val'] ) : $args['prev_val'];
		case ( ( ! empty( $args['col_data_type']['data_cols_checkbox'] ) && ( ! empty( $args['updated_val'] ) ) && in_array( $args['update_column'], $args['col_data_type']['data_cols_checkbox'], true ) ) || ( 'checkbox' === $args['col_data_type'] ) ):
			if ( in_array( $args['updated_val'], array( 'yes', 'no' ) ) ) {
				return ( 'yes' === $args['updated_val'] ) ? 'no' : 'yes';
			} else if ( in_array( $args['updated_val'], array( 'true', 'false' ) ) ) {
				return ( 'true' === $args['updated_val'] ) ? 'false' : 'true';
			}
		default:
			return $args['prev_val'];
	}
}

/**
 * Format term ids to names.
 *
 * @param  array  $term_ids Term IDs to format.
 * @param  string $taxonomy Taxonomy name.
 * @return string
 */
function sa_sm_format_term_ids( $term_ids = array(), $taxonomy = '' ) {
	$term_ids = wp_parse_id_list( $term_ids );

	if ( ! count( $term_ids ) ) {
		return '';
	}

	$formatted_terms = array();

	if ( is_taxonomy_hierarchical( $taxonomy ) ) {
		foreach ( $term_ids as $term_id ) {
			$formatted_term = array();
			$ancestor_ids   = array_reverse( get_ancestors( $term_id, $taxonomy ) );

			foreach ( $ancestor_ids as $ancestor_id ) {
				$term = get_term( $ancestor_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$formatted_term[] = $term->name;
				}
			}

			$term = get_term( $term_id, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				$formatted_term[] = $term->name;
			}

			$formatted_terms[] = implode( ' > ', $formatted_term );
		}
	} else {
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				$formatted_terms[] = $term->name;
			}
		}
	}

	return implode( ',', $formatted_terms );
}

//Function to compare column position
function sm_position_compare( $a, $b ){
	if ( $a['position'] == $b['position'] )
		return 0;
	if ( $a['position'] < $b['position'] ) {
		return -1;
	}
	return 1;
}


//Function to sort multidimesnional array based on any given key
function sm_multidimensional_array_sort($array, $on, $order=SORT_ASC){

	$sorted_array = array();
	$sortable_array = array();

	if (count($array) > 0) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $key2 => $value2) {
					if ($key2 == $on) {
						$sortable_array[$key] = $value2;
					}
				}
			} else {
				$sortable_array[$key] = $value;
			}
		}

		switch ($order) {
			case SORT_ASC:
				asort($sortable_array);
				break;
			case SORT_DESC:
				arsort($sortable_array);
				break;
		}

		foreach ($sortable_array as $key => $value) {
			$sorted_array[$key] = $array[$key];
		}
	}

	return $sorted_array;
}

/**
 * Retrieves the common parameters.
 *
 * @return array An associative array containing common parameters for the Smart Manager plugin.
 */
function get_sa_manager_common_params() {
	return array(
		'plugin_file'          => defined('SM_PLUGIN_FILE') ? SM_PLUGIN_FILE : '',
		'plugin_sku'           => defined('SM_SKU') ? SM_SKU : '',
		'plugin_prefix'        => defined('SM_PREFIX') ? SM_PREFIX : '',
		'plugin_name'          => defined('SM_PLUGIN_NAME') ? SM_PLUGIN_NAME : '',
		'plugin_pro_flag'      => (defined('SMPRO') && (true === SMPRO)) ? true : false,
		'plugin_main_class_nm' => 'Smart_Manager',
		'plugin_dir'           => defined('SM_PLUGIN_DIR_PATH') ? SM_PLUGIN_DIR_PATH : '',
		'plugin_obj_key'       => 'smart_manager',
		'folder_flag'          => '/pro'
	);
}

/**
 * Checks if the Stripe payment gateway is active.
 *
 * @return bool True if the Stripe gateway is active, false otherwise.
 */
function sm_is_stripe_gateway_active() {
	if ( ! function_exists( 'WC' ) || ! is_callable( 'WC' ) ) {
		return false;
	}
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	if ( empty( $gateways ) || ! is_array( $gateways ) ) {
		return false;
	}
	return ( ! empty( $gateways['stripe'] ) ) ? true : false;
}

/**
 * Updates a Smart Manager task based on provided parameters
 *
 * @param array $params . Parameters for updating the task. Default empty array.
 * @return bool Results of the task update operation
 */
function sm_task_update( $params = array() ){
    global $wpdb;
    if ( empty( $params ) || ( ! is_array( $params ) ) ) {
        return;
    }
    if ( ( ! empty( $params['task_id'] ) ) && ( ( ! empty( $params['status'] ) ) || ( ! empty( $params['completed_date'] ) ) ) ) {
        $set_query = '';
        switch ( $params ) {
            case ( ! empty( $params['status'] ) && ( ! isset( $params['completed_date'] ) ) ):
                $set_query = "status = '{$params['status']}'";
                break;
            case ( ! isset( $params['status'] ) && ( ! empty( $params['completed_date'] ) ) ):
                $set_query = "completed_date = '{$params['completed_date']}'";
                break;
            default:
                $set_query = "status = '{$params['status']}', completed_date = '{$params['completed_date']}'";
            }
        if ( empty( $set_query ) ) {
            return;
        }
        return $wpdb->query( "UPDATE {$wpdb->prefix}sm_tasks SET " . $set_query . " WHERE id = " . $params['task_id'] . "" );
    } elseif ( ! empty( $params['title'] ) && ! empty( $params['post_type'] ) && ! empty( $params['type'] ) && ! empty( $params['actions'] ) && ! empty( $params['record_count'] ) ) {
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}sm_tasks( title, date, completed_date, post_type, author, type, status, actions, record_count)
                VALUES( %s, %s, %s, %s, %d, %s, %s, %s, %d )",
                $params['title'],
                ( ! empty( $params['created_date'] ) ) ? $params['created_date'] : '0000-00-00 00:00:00',
                '0000-00-00 00:00:00',
                $params['post_type'],
                get_current_user_id(),
                $params['type'],
                ( ! empty( $params['status'] ) ) ? $params['status'] : 'in-progress',
                json_encode( $params['actions'] ),
                $params['record_count']
            )
        );
    }
    return ( ! is_wp_error( $wpdb->insert_id ) ) ? $wpdb->insert_id : 0;
}

/**
 * Updates task details in the database and marks tasks as completed
 *
 * @param array $params Array of parameters containing task details to update
 * @return void
 */
function sm_task_details_update( $params = array() ) {
    if ( empty( $params ) && ( ! is_array( $params ) ) ) {
        return;
    }
    $task_id         = array();
    $task_details_id = array();
    global $wpdb;
    foreach ( $params as $param ) {
        if ( empty( $param['task_id'] ) || empty( $param['action'] ) || empty( $param['status'] ) || empty( $param['record_id'] ) || empty( $param['field'] ) ) {
            continue;
        }
        $task_id = array( $param['task_id'] );
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}sm_task_details( task_id, action, status, record_id, field, prev_val, updated_val )
                VALUES( %d, %s, %s, %d, %s, %s, %s )",
                $param['task_id'],
                $param['action'],
                $param['status'],
                $param['record_id'],
                $param['field'],
                ( isset( $param['prev_val'] ) ) ? $param['prev_val'] : '',
                ( isset( $param['updated_val'] ) ) ? maybe_serialize( $param['updated_val'] ) : ''
            )
        );
        $task_details_id[] = ( ! is_wp_error( $wpdb->insert_id ) ) ? $wpdb->insert_id : array();
    }
    if ( ( ! empty( $task_details_id ) ) && ( count( $params ) === count( $task_details_id ) ) ) {
        sm_task_update(
            array(
                'task_id' => implode( '', $task_id ),
                'status' => 'completed',
                'completed_date' => date( 'Y-m-d H:i:s' )
            )
        );
    }
}

/**
 * Get singular label of a post type.
 *
 * @param string $post_type Post type slug.
 * @return string Singular label or empty string if not found.
 */
function sm_get_post_type_singular_name( $post_type = '' ) {
	if ( empty( $post_type ) ) {
		return;
	}
	$post_type_obj = get_post_type_object( $post_type );
	if ( empty( $post_type_obj ) || ! is_object( $post_type_obj ) || empty( $post_type_obj->labels->singular_name ) ) {
		return;
	}
	return $post_type_obj->labels->singular_name;
}
