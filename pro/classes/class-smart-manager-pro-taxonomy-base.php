<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Taxonomy_Base' ) ) {
	class Smart_Manager_Pro_Taxonomy_Base extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
		$prev_taxonomy_values = array();

		protected static $_instance = null;
		public static $term_taxonomy_update_cols = array( 'name', 'slug', 'term_group', 'description', 'parent' );

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		/**
		 * Constructor.
		 */
		function __construct($dashboard_key) {

			add_filter( 'sm_search_table_types', array( &$this, 'search_table_types' ), 12, 1 ); // should be kept before calling the parent class constructor

			parent::__construct($dashboard_key);
			self::actions();

			$this->dashboard_key = $dashboard_key;
			$this->post_type = $dashboard_key;
			$this->req_params  	= ( ! empty( $_REQUEST ) ) ? $_REQUEST : array();

            add_filter( 'sm_load_default_store_model', function() { return false; } );
            add_filter( 'sm_default_dashboard_model', array( $this, 'default_taxonomy_dashboard_model' ) );

			add_filter( 'sm_beta_load_default_data_model', function() { return false; } );
			add_filter( 'sm_data_model', array( $this, 'default_taxonomy_data_model' ), 10, 2 );

			add_filter( 'terms_clauses',  array( $this, 'handle_terms_clauses' ), 999, 3 );
			
			// hooks for inline update
			add_filter( 'sm_default_inline_update', function() { return false; } );
			add_action( 'sm_inline_update_post', array( $this,'taxonomy_inline_update' ), 10, 2 );

			add_filter( 'sm_beta_background_entire_store_ids_query', array( $this,'get_entire_store_ids_query' ), 12, 1 );
        }

		/**
		 * Static function for defining background process actions.
		 */
        public static function actions() {
			// hooks for delete functionality
			add_filter( 'sm_pro_default_process_delete_records', function() { return false; } );
			add_filter( 'sm_pro_default_process_delete_records_result', __CLASS__. '::process_delete_terms', 12, 3 );

			// hooks for bulk edit functionality
			add_filter( 'sm_batch_update_prev_value', __CLASS__. '::terms_batch_update_prev_value', 10, 2 );
			add_filter( 'sm_default_batch_update_db_updates', function() { return false; } );
			add_filter( 'sm_post_batch_update_db_updates', __CLASS__. '::terms_post_batch_update_db_updates', 10, 2 );
        }

		/**
		 * Function for modifying terms query clauses when using 'get_terms'.
		 *
		 * @param array $clauses array of query clauses.
		 * @param array $taxonomies array of taxonomies.
		 * @param array $args array of query args.
		 * @return array $clauses updated array of query clauses.
		 */
        public function handle_terms_clauses( $clauses = array(), $taxonomies = array(), $args = array() ) {
			global $wpdb;

			$join = ( ! empty( $clauses['join'] ) ) ? $clauses['join'] : '';
			$where = ( ! empty( $clauses['where'] ) ) ? $clauses['where'] : '';
			if ( ( ! empty( $this->req_params[ 'selected_ids' ] ) && '[]' !== $this->req_params[ 'selected_ids' ] ) && empty( $this->req_params['storewide_option'] ) && ( ! empty( $this->req_params[ 'cmd' ] ) && ( 'get_export_csv' === $this->req_params[ 'cmd' ] ) ) ) {
				$selected_ids = json_decode( stripslashes( $this->req_params[ 'selected_ids' ] ) );
				$where .= ( ! empty( $selected_ids ) ) ? " AND t.term_id IN (" . implode( ",", $selected_ids ) . ")" : $where;
			}

			// Code to add join condition for termmeta
			if( ( ! empty( $this->req_params['search_text'] ) ) && false === strpos( $join, 'termmeta' ) ){
				$join .= ' LEFT JOIN '. $wpdb->prefix .'termmeta ON ( tt.term_id = '. $wpdb->prefix .'termmeta.term_id ) ';
				$clauses['distinct'] = 'DISTINCT';
			}

			// Code for handling sorting for termmeta cols
			if( ! empty( $args['sort_by_meta_key'] ) && ! empty( $taxonomies ) ){
				$order_by = ( ! empty( $args['orderby']  ) && 'meta_value_num' === $args['orderby'] ) ? 'tm.meta_value+0' : 'tm.meta_value';
				$order = ( ! empty( $args['order'] ) ) ? $args['order'] : 'ASC';

				$query = "SELECT DISTINCT tt.term_id 
				FROM {$wpdb->prefix}term_taxonomy AS tt
					LEFT JOIN {$wpdb->prefix}termmeta AS tm
						ON (tt.term_id = tm.term_id
							AND tm.meta_key = '". $args['sort_by_meta_key'] ."')
				WHERE tt.taxonomy IN ('". implode("','", $taxonomies) ."')
				ORDER BY ". $order_by ." ". $order;

				// TODO: improve code
				$term_ids = $wpdb->get_col( "SELECT DISTINCT tt.term_id 
											FROM {$wpdb->prefix}term_taxonomy AS tt
												LEFT JOIN {$wpdb->prefix}termmeta AS tm
													ON (tt.term_id = tm.term_id
														AND tm.meta_key = '". $args['sort_by_meta_key'] ."')
											WHERE tt.taxonomy IN ('". implode("','", $taxonomies) ."')
											ORDER BY ". $order_by ." ". $order 
										);

				$option_name = 'sm_data_model_sorted_term_ids';
				update_option( $option_name, implode( ',', $term_ids ), 'no' );

				$clauses['orderby'] = " ORDER BY FIND_IN_SET( t.term_id, ( SELECT option_value FROM ".$wpdb->prefix."options WHERE option_name = '".$option_name."' ) ) ";
				$clauses['order'] = 'ASC';
			}

			//Code for handling simple search
			if( ! empty( $this->req_params['search_text'] ) ) {

				$matched_results = array();
				$col_model = $this->get_col_model( $this->dashboard_key );
				
				$search_text = $wpdb->_real_escape( $this->req_params['search_text'] );
				$simple_search_ignored_cols = apply_filters('sm_simple_search_ignored_terms_columns', array(), $col_model);
				$alias = array(
					'terms'			=> 't',
					'term_taxonomy'	=>	'tt'
				);
				//Code for creating search condition
	        	if( !empty( $col_model ) ) {
	        		foreach( $col_model as $col ) {
	        			if( empty( $col['src'] ) ) continue;

						$src_exploded = explode("/",$col['src']);
						if( !empty( $src_exploded[0] ) && in_array( $src_exploded[0], array( 'terms', 'term_taxonomy' ) ) && !in_array($src_exploded[1], $simple_search_ignored_cols) ) {

							if( !empty( $col['selectOptions'] ) ) {
								$matched_results = preg_grep('/'. ucfirst($search_text) .'.*/', $col['selectOptions']);
							}

							if( is_array( $matched_results ) && !empty( $matched_results ) ) {
								foreach( array_keys( $matched_results ) as $search ) {
									if( false === strpos( $where, $alias[$src_exploded[0]].".".$src_exploded[1]." LIKE '%".$search."%'" ) ) {
										$where_cond[] = "( ".$alias[$src_exploded[0]].".".$src_exploded[1]." LIKE '%".$search."%' )";									
									}
								}
							} else {
								if( false === strpos( $where, $alias[$src_exploded[0]].".".$src_exploded[1]." LIKE '%".$search_text."%'" ) ) {
									$where_cond[] = "( ".$alias[$src_exploded[0]].".".$src_exploded[1]." LIKE '%".$search_text."%' )";
								}
							}
						}
					}
				}
				
				$where .= ( strpos( $where, 'meta_value LIKE' ) === false || ( !empty( $where_cond ) ) ) ? ' AND ( ' : '';
				$where .= ( strpos( $where, "meta_value LIKE '%".$search_text."%" ) === false ) ? " ({$wpdb->prefix}termmeta.meta_value LIKE '%".$search_text."%') " : '';
				$where .= ( ( !empty( $where_cond ) ) ? ' OR '. implode(" OR ", $where_cond) : '' );
				$where .= ( strpos( $where, 'meta_value LIKE' ) === true || ( !empty( $where_cond ) ) ) ? ' ) ' : '';
				
			}

			//Code for handling clauses for advanced search
			if( !empty( $this->req_params ) && !empty( $this->req_params['advanced_search_query'] ) && '[]' !== $this->req_params['advanced_search_query'] ) {
				$join .= ( false === strpos( $join, 'sm_advanced_search_temp' ) ) ? " JOIN {$wpdb->base_prefix}sm_advanced_search_temp
                            	ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = t.term_id)" : '';
				$where .= ( false === strpos( $where,'sm_advanced_search_temp.flag > 0' ) ) ? " AND {$wpdb->base_prefix}sm_advanced_search_temp.flag > 0" : '';
			}

			$clauses['join'] = $join;
			$clauses['where'] = $where;

			return $clauses;
		}

		/**
		 * Function for creating taxonomy dashboard model.
		 *
		 * @param array $dashboard_model array of default dashboard model.
		 * @return array updated default taxonomy dashboard model.
		 */
        public function default_taxonomy_dashboard_model( $dashboard_model = array() ) {

			global $wpdb;

            $visible_taxonomy_cols = array( 'description', 'parent', 'count' );
            $visible_term_cols = array( 'term_id', 'name', 'slug', 'term_group' );
            
			$uneditable_cols = array( 'count', 'term_id' );
			$numeric_cols = array( 'parent', 'count', 'term_id' );
			
			$visible_cols = array_merge( $visible_term_cols, $visible_taxonomy_cols );

			$col_model = array();

            foreach( $visible_cols as $col ){
				$args = array(
					'table_nm' 	=> ( in_array( $col, $visible_taxonomy_cols ) ) ? 'term_taxonomy' : 'terms',
					'col'		=> $col,
					'type'		=> ( in_array( $col, $numeric_cols ) ) ? 'numeric' : 'text',
					'editable'	=> ( in_array( $col, $uneditable_cols ) ) ? false : true,
				);
				$col_model[] = $this->get_default_column_model( $args );
            }

			//Code to get columns from termmeta table
			$results = $wpdb->get_results(
											$wpdb->prepare( "SELECT DISTINCT {$wpdb->prefix}termmeta.meta_key,
																{$wpdb->prefix}termmeta.meta_value
															FROM {$wpdb->prefix}termmeta 
																JOIN {$wpdb->prefix}term_taxonomy 
																ON ({$wpdb->prefix}term_taxonomy.term_id = {$wpdb->prefix}termmeta.term_id
																	AND {$wpdb->prefix}term_taxonomy.taxonomy = %s)
															WHERE {$wpdb->prefix}termmeta.meta_key != ''
															GROUP BY {$wpdb->prefix}termmeta.meta_key",
															$this->dashboard_key
														), 
											'ARRAY_A'
										);
			$num_rows = $wpdb->num_rows;

			if ($num_rows > 0) {
				$meta_keys = array();
				$meta_cols = array();

				foreach ($results as $key => $col) {
					if ( empty( $col['meta_value'] ) || $col['meta_value'] == '1' || $col['meta_value'] == '0.00' ) {
						$meta_keys [] = $col['meta_key']; //TODO: if possible store in db instead of using an array
					}

					$meta_cols[$col['meta_key']] = $col;
				}

				//not in 0 added for handling empty date columns
				if (!empty($meta_keys)) {
					$results = $wpdb->get_results ( "SELECT {$wpdb->prefix}termmeta.meta_key,
																	{$wpdb->prefix}termmeta.meta_value
																FROM {$wpdb->prefix}termmeta 
																	JOIN {$wpdb->prefix}term_taxonomy 
																		ON ({$wpdb->prefix}term_taxonomy.term_id = {$wpdb->prefix}termmeta.term_id
																		AND {$wpdb->prefix}term_taxonomy.taxonomy = '". $this->dashboard_key ."')
																WHERE {$wpdb->prefix}termmeta.meta_value NOT IN ('','0','0.00','1')
																	AND {$wpdb->prefix}termmeta.meta_key IN ('".implode("','",$meta_keys)."')
																GROUP BY {$wpdb->prefix}termmeta.meta_key"
															, 'ARRAY_A');
					
					$num_rows = $wpdb->num_rows;

					if( $num_rows > 0 ) {
						foreach( $results as $col ) {
							if ( isset( $meta_cols [$col['meta_key']] ) ) {
								$meta_cols[$col['meta_key']]['meta_value'] = $col['meta_value'];
							}
						}
					}
				}

				//Filter to add custom termmeta columns for custom plugins
				$meta_cols = apply_filters( 'sm_default_dashboard_model_termmeta_cols', $meta_cols );
				$meta_count = 0;

				foreach ($meta_cols as $col) {
					
					$meta_key = ( !empty( $col['meta_key'] ) ) ? $col['meta_key'] : '';
					$meta_value = ( !empty( $col['meta_value'] ) || $col['meta_value'] == 0 ) ? $col['meta_value'] : '';

					$args = array(
						'table_nm' 	=> 'termmeta',
						'col'		=> $meta_key,
						'is_meta'	=> true,
						'col_value'	=> $meta_value,
						'hidden'	=> ( $meta_count > 5 ) ? true : false
					);

					$col_model[] = $this->get_default_column_model( $args );

					$meta_count++;
				}
			}

			return array( 
						'display_name' => __(ucwords(str_replace('_', ' ', $this->dashboard_key)), 'smart-manager-for-wp-e-commerce'),
						'tables' => array(
										'term_relationships' 	=> array(
																		'pkey' => 'object_id',
																		'join_on' => 'term_relationships.object_id = posts.ID',
																		'where' => array()
																	),

										'term_taxonomy' 		=> array(
																		'pkey' => 'term_taxonomy_id',
																		'join_on' => 'term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id',
																		'where' => array()
																	),

										'terms' 				=> array(
																		'pkey' => 'term_id',
																		'join_on' => 'terms.term_id = term_taxonomy.term_id',
																		'where' => array()
																	),
										
										'termmeta' 				=> array(
																		'pkey' => 'term_id',
																		'join_on' => 'termmeta.term_id = term_taxonomy.term_id',
																		'where' => array()
																	)

										), 
						'columns' => $col_model,
						'sort_params' 	=> array ( //WP_Query array structure
												'orderby' => 'term_id', //multiple list separated by space
												'order' => 'DESC',
												'default' => true ),

						'per_page_limit' 	=> '', // blank, 0, -1 all values refer to infinite scroll
						'treegrid'			=> false // flag for setting the treegrid
			);
        }

		/**
		 * Function for creating taxonomy data model.
		 *
		 * @param array $data_model array of default data model.
		 * @param array $data_col_params array of dashboard column params.
		 * @return array $data_model updated default taxonomy data model.
		 */
		public function default_taxonomy_data_model( $data_model = array(), $data_col_params = array() ){
			
			if( ! isset( $data_col_params['limit'] ) || ! isset( $data_col_params['offset'] ) ){
				return $data_model;
			}
			
			global $wpdb;

			$valid_orderby_fields = array( 'name', 'slug', 'term_group', 'term_id', 'id', 'description', 'parent', 'term_order', 'count', 'meta_value', 'meta_value_num' );

			// Code for getting cols
			$col_model = ( ! empty( $data_col_params['col_model'] ) ) ? $data_col_params['col_model'] : array();
			$required_cols = ( ! empty( $data_col_params['required_cols'] ) ) ? $data_col_params['required_cols'] : array();
			$data_cols = ( ! empty( $data_col_params['data_cols'] ) ) ? $data_col_params['data_cols'] : array();
			$total_pages = 0;

			$termmeta_cols = $taxonomy_cols = $numeric_termmeta_cols = $numeric_termmeta_cols_decimal_places = $image_termmeta_cols = array();

			$search_cols_type = array(); //array for col & its type for advanced search

			if (!empty($col_model)) {
				foreach ($col_model as $col) {
					if( !empty( $col['hidden'] ) && !empty( $col['data'] ) && array_search($col['data'], $required_cols) === false ) {
						continue;
					}

					$validator = ( !empty( $col['validator'] ) ) ? $col['validator'] : '';
					$type = ( !empty( $col['type'] ) ) ? $col['type'] : '';

					if( ! empty( $col['table_name'] ) && ! empty( $col['col_name'] ) ){
						// added $validator condition for spl cols like '_regular_price', '_sale_price', etc.
						$search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ] = ( "customNumericTextEditor" === $validator && "text" == $type ) ? 'numeric' : $type;
						$search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ] = ( ! empty( $col['search_type'] ) ) ? $col['search_type'] : $search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ]; //Code to handle sp. search data type passed for any col
					}

					$col_exploded = (!empty($col['src'])) ? explode("/", $col['src']) : array();

					if (empty($col_exploded)) continue;
					
					if ( sizeof($col_exploded) > 2) {
						$col_meta = explode("=",$col_exploded[1]);
						$col_nm = $col_meta[1];
					} else {
						$col_nm = $col_exploded[1];
					}

					if( !empty( $col_exploded[0] ) ) {
						if( 'term_taxonomy' === $col_exploded[0] ){
							$taxonomy_cols[] = $col_nm;
						}
						else if( 'termmeta' === $col_exploded[0] ){
							$termmeta_cols[] = $col_nm;

							if( ( $type == 'number' || $type == 'numeric' || $validator == 'customNumericTextEditor' ) && 'sm.image' !== $type ) {
								if( isset( $col['decimalPlaces'] ) ) {
									$numeric_termmeta_cols_decimal_places[ $col_nm ] = $col['decimalPlaces'];
								}
								$numeric_termmeta_cols[] = $col_nm;
							}

							if( 'sm.image' === $type ){
								$image_termmeta_cols[] = $col_nm;
							}
						}
					}
				}
			}

			$args = array( 'hide_empty' 	=> false,
							'orderby'		=> 'term_id',
							'order'			=> 'DESC',
						);

			if( 0 < $data_col_params['limit'] ){
				$args = array_merge( $args, array(
					'number'		=> $data_col_params['limit'],
					'offset'		=> $data_col_params['offset']
				) );
			}

			// Code for applying sorting to query
			if( ! empty( $this->req_params['sort_params'] ) ){
				$sort_params = $this->build_query_sort_params( array( 'sort_params' => $this->req_params['sort_params'],
																		'numeric_meta_cols' => $numeric_termmeta_cols,
'data_cols' => $data_cols
															) );
				if( ! empty( $sort_params ) ){
					$col = ( ! empty( $sort_params['column_nm'] ) ) ? $sort_params['column_nm'] : '';
					if( ! empty( $col ) && in_array( $col, $valid_orderby_fields ) ){
						$args = array_merge( $args, array(
							'orderby'	=> $col,
							'order'		=> ( ! empty( $sort_params['sortOrder'] ) ) ? $sort_params['sortOrder'] : ''
						) );
						if( ! empty( $sort_params['sort_by_meta_key'] ) ){
							$args['sort_by_meta_key'] = $sort_params['sort_by_meta_key'];
						}
					}
				}
			}

			//Code to clear the advanced search temp table
			if( empty( $this->req_params['advanced_search_query'] ) ) {
				$wpdb->query( "DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp" );
				delete_option( 'sm_advanced_search_query' );
			}

			//Code for handling advanced search functionality
			if( !empty( $this->req_params['advanced_search_query'] ) && $this->req_params['advanced_search_query'] != '[]' ) {

				$this->req_params['advanced_search_query'] = json_decode(stripslashes($this->req_params['advanced_search_query']), true);

				if (!empty($this->req_params['advanced_search_query'])) {
					$this->process_search_cond( array( 'taxonomy' => $this->dashboard_key,
													'search_query' => (!empty($this->req_params['advanced_search_query'])) ? $this->req_params['advanced_search_query'] : array(),
													'SM_IS_WOO30' => (!empty($this->req_params['SM_IS_WOO30'])) ? $this->req_params['SM_IS_WOO30'] : '',
													'search_cols_type' => $search_cols_type,
													'data_col_params' => $data_col_params ) );

				}

			}

			$terms = get_terms( $this->dashboard_key, $args );
			
			$total_count = 0;

			if( ! empty( $terms ) && ! is_wp_error( $terms ) ){

				$total_pages = 1;

				//Code for saving the post_ids in case of search
				if( ( defined( 'SMPRO' ) && true === SMPRO ) && ! empty( $this->req_params['search_text'] ) || ( ! empty( $this->req_params['advanced_search_query'] ) && '[]' !== $this->req_params['advanced_search_query'] ) ) {
					$all_term_ids = get_terms( $this->dashboard_key, array(
						'hide_empty' 	=> false,
						'fields'		=> 'ids'
					) );
					if( ! empty( $all_term_ids ) && ! is_wp_error( $all_term_ids ) ){
						set_transient( 'sa_sm_search_post_ids', implode( ",", $all_term_ids ) , WEEK_IN_SECONDS );
					}	
				}

				$total_count = get_terms( $this->dashboard_key, array(
					'hide_empty' 	=> false,
					'fields'		=> 'count'
				) );

				if ( ! is_wp_error( $terms ) && $total_count > $data_col_params['limit'] ) {
					$total_pages = ceil( $total_count/$data_col_params['limit'] );
				}
			}

			$items = $term_id_indexes = $term_ids = array();
			$index = 0;

			foreach( $terms as $term_obj ){
				$term_arr = (array)$term_obj;
				$id = ( ! empty( $term_arr['term_id'] ) ) ? $term_arr['term_id'] : 0;

				if( empty( $id ) ){
					continue;
				}

				foreach( $term_arr as $key => $value ){
					if( in_array( $key, $data_cols ) ){
						$terms_key = ( ( in_array( $key, $taxonomy_cols ) ) ? 'term_taxonomy_' : 'terms_').strtolower( str_replace( ' ', '_', $key ) );
						$items[$index][$terms_key] = $value;
					}
				}
				$term_ids[] = $id;
				$term_id_indexes[$id] = $index;
				$index++;
			}

			if( ! empty( $term_ids ) && ! empty( $termmeta_cols ) ){
				$termmeta_data = array();
				
				if( count( $term_ids ) > 100 ) {
					$term_id_chunks = array_chunk( $term_ids, 100 );

					foreach( $term_id_chunks as $id_chunk ){
						$results = $wpdb->get_results( $wpdb->prepare( "SELECT term_id as term_id,
																				meta_key AS meta_key,
																				meta_value AS meta_value
																		FROM {$wpdb->prefix}termmeta
																		WHERE term_id IN (". implode(",",$id_chunk) .")
																			AND meta_key IN ('". implode("','", $termmeta_cols) ."')
																			AND 1=%d
																		GROUP BY term_id, meta_key", 1 ), 'ARRAY_A' );
						
						if( ! empty( $results ) ) {
							$termmeta_data = array_merge( $termmeta_data, $results );
						}
					}
			
				} else {
					$termmeta_data = $wpdb->get_results( $wpdb->prepare( "SELECT term_id as term_id,
																				meta_key AS meta_key,
																				meta_value AS meta_value
																		FROM {$wpdb->prefix}termmeta
																		WHERE term_id IN (". implode(",",$term_ids) .")
																			AND meta_key IN ('". implode("','", $termmeta_cols) ."')
																			AND 1=%d
																		GROUP BY term_id, meta_key", 1 ), 'ARRAY_A' );
				}

				if( ! empty( $termmeta_data ) ){
					foreach( $termmeta_data as $data ){
						$index = ( isset( $term_id_indexes[$data['term_id']] ) ) ? $term_id_indexes[$data['term_id']] : '';

						if( '' === $index ) {
							continue;
						}

						$meta_key = ( isset( $data['meta_key'] ) ) ? $data['meta_key'] : '';

						if( empty( $meta_key ) ) {
							continue;
						}

						$meta_value = ( isset( $data['meta_value'] ) ) ? $data['meta_value'] : '';

						//Code for handling image fields
						if( in_array( $meta_key, $image_termmeta_cols ) ) {
							if( ! empty( $meta_value ) ){
								$attachment = wp_get_attachment_image_src($meta_value, 'full');
								if ( is_array( $attachment ) && ! empty( $attachment[0] ) ) {
									$meta_value = $attachment[0];
								} else {
									$meta_value = '';
								}
							}
						}

						$meta_key = sanitize_title($meta_key);

						$meta_key = 'termmeta_meta_key_'.$meta_key.'_meta_value_'.$meta_key;
						$items [$index][$meta_key] = $meta_value;
					}
				}
			}

			$data =  array(
				'items'			=> ( !empty( $items ) ) ? $items : array(),
				'start'			=> $data_col_params['offset'] + $data_col_params['limit'],
				'page'			=> $data_col_params['current_page'],
				'total_pages'	=> $total_pages,
				'total_count'	=> $total_count
			);
			return ( ! empty( $data_model ) && is_array( $data_model ) ) ? array_merge( $data_model, $data ) : $data;
		}

		/**
		 * Function for modifying table types for advanced search.
		 *
		 * @param array $table_types array of table types.
		 * @return array $table_types updated array of table types.
		 */
		public function search_table_types( $table_types = array() ){
			$table_types['flat'] = array_merge( array(
				'terms'  		=> 'term_id',
				'term_taxonomy'	=>  'term_id'
			), ( !empty( $table_types['flat'] ) ? $table_types['flat'] : array() ) );
			$table_types['meta']['termmeta'] =  'term_id';
			return $table_types;
		}

		/**
		 * Function for modifying edited data before updating.
		 *
		 * @param array $edited_data array of edited rows.
		 * @param array $params array of additional params for inline edit.
		 * @return void.
		 */
		public function taxonomy_inline_update( $edited_data = array(), $params = array() ) {
			
			if( empty( $edited_data ) ){
				return $edited_data;
			}

			$term_taxonomy_update_args = array();
			$term_meta_update_args = array();

			foreach( $edited_data as $id => $edited_row ) {
				$id = intval( $id );

				if( empty( $id ) && empty( $edited_row['terms/name'] ) ){
					continue;
				} 

				if( empty( $id ) ){
					$term_meta_update_args = array();
				}

				foreach( $edited_row as $key => $value ) {
					$prev_val = '';
					$edited_value_exploded = explode("/", $key);
					
					if( empty( $edited_value_exploded ) ) continue;

					$update_table = $edited_value_exploded[0];
					$update_column = $edited_value_exploded[1];
					$this->field_names[ $id ][ $update_column ] = $key;
					if ( 'termmeta' === $update_table && sizeof( $edited_value_exploded ) > 2 ) {
						$update_column_exploded = explode("=",$edited_value_exploded[2]);
						$update_column = $update_column_exploded[1];
						$prev_taxonomy_values =  self:: terms_batch_update_prev_value( $prev_val, array( 'id' => $id, 'table_nm' => $update_table, 'col_nm' => $update_column, 'dashboard_key' => $this->dashboard_key ) );

						if( in_array( $update_column, $params['data_cols_timestamp'] ) ) {
    						$value = strtotime( $value );
    					}

						if( empty( $id ) ){
							$term_meta_update_args[ $update_column ] = $value;
							continue;
						}
						$result = update_term_meta( $id, $update_column, $value );
						if ( empty( $result ) || is_wp_error( $result ) || ( ! property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) || empty( $this->task_id ) || empty( $id ) ) {
			    				continue;
			    			}
				    		Smart_Manager_Base::$update_task_details_params[] = array(
				    			'task_id' => $this->task_id,
							    'action' => 'set_to',
							    'status' => 'completed',
							    'record_id' => $id,
							    'field' => $key,                                                               
							    'prev_val' => $prev_taxonomy_values,
							    'updated_val' => $value,
						       	);	
					} else if( in_array( $update_column, self::$term_taxonomy_update_cols ) ) {
						$term_taxonomy_update_args[ $update_column ] = $value;
						$this->prev_taxonomy_values[ $id ][ $update_column ] = self::terms_batch_update_prev_value( $prev_val, array( 'id' => $id, 'table_nm' => $update_table, 'col_nm' => $update_column, 'dashboard_key' => $this->dashboard_key ) );
					}
				}
				if( ! empty( $term_taxonomy_update_args ) ){

					// Code for newly added row
					if( empty( $id ) && ! empty( $term_taxonomy_update_args['name'] ) ){
						$result = wp_insert_term( $term_taxonomy_update_args['name'], $this->dashboard_key, $term_taxonomy_update_args );

						if( is_wp_error( $result ) || empty( $term_meta_update_args ) ){
							continue;
						}

						foreach( $term_meta_update_args as $key => $value ){
							update_term_meta( $id, $update_column, $value );
						}

						continue;
					}

					$result = wp_update_term( $id, $this->dashboard_key, $term_taxonomy_update_args );

					if( is_wp_error( $result ) ){
						// TODO
					} else {
						if ( ( ! property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) || empty( $this->task_id ) ) {
			    				continue;
			    			}
						foreach ( $term_taxonomy_update_args as $key => $value ) {
							if ( ( ( empty( $key ) ) ) || ! isset( $this->field_names[ $id ][ $key ] ) ) {
		    						continue;
		    					}
			    				Smart_Manager_Base::$update_task_details_params[] = array(
			    					'task_id' => $this->task_id,
						        	'action' => 'set_to',
						        	'status' => 'completed',
						        	'record_id' => $id,
						        	'field' => $this->field_names[ $id ][ $key ],                                                               
						        	'prev_val' => $this->prev_taxonomy_values[ $id ][ $key ],
						        	'updated_val' => $value,
						        );	
						}
					}
				}
			}
		}

		/**
		 * Function for handling delete functionality.
		 *
		 * @param boolean $result result of the delete process.
		 * @param int $id term_id of the record to be deleted.
		 * @param array $params array of additional params for delete functionality.
		 * @return boolean $result result of the delete process.
		 */
		public static function process_delete_terms( $result = false, $id = 0, $params = array() ) {
			$id = intval( $id );
			if( empty( $id ) || empty( $params ) || ( ! empty( $params ) && empty( $params['dashboard_key'] ) ) ){
				return $result;	
			}
			
			$result = wp_delete_term( $id, $params['dashboard_key'] );

			return ( is_wp_error( $result ) || ( ! is_wp_error( $result ) && empty( $result ) ) ) ? false : true; 
		}

		/**
		 * Function for modifying query for getting ids in case of 'entire store' option.
		 *
		 * @param string $query query for fetching the ids when entire store option is selected.
		 * @return string updated query for fetching the ids when entire store option is selected.
		 */
		public function get_entire_store_ids_query( $query = '' ) {
			global $wpdb;
			return $wpdb->prepare( "SELECT term_id FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy = %s", $this->dashboard_key );
		}

		/**
		 * Static function for fetching the field previous value in case of bulk edit.
		 *
		 * @param string $prev_val field previous value.
		 * @param array $args bulk edit params.
		 * @return string $prev_val updated field previous value.
		 */
		public static function terms_batch_update_prev_value( $prev_val = '', $args = array() ) {
			if( empty( $args ) || empty( $args['table_nm'] ) || empty( $args['id'] ) || empty( $args['col_nm'] ) ){
				return $prev_val;
			}
			if( 'termmeta' === $args['table_nm'] ) {
				return get_term_meta( $args['id'], $args['col_nm'], true );
			} else {
				$terms = get_term_by( 'term_id', $args['id'], $args['dashboard_key'], 'ARRAY_A' );
				return ( ! empty( $terms ) && ! empty( $terms[ $args['col_nm'] ] ) ) ? $terms[ $args['col_nm'] ] : $prev_val;
			}
			return $prev_val;
		}
		/**
		 * Static function for processing db updates for bulk edit.
		 *
		 * @param boolean $update_flag flag to determine if the update was successful or not.
		 * @param array $args bulk edit params.
		 * @return boolean $update_flag result of the db update.
		 */
		public static function terms_post_batch_update_db_updates( $update_flag = false, $args = array() ) {
			$id = ( !empty( $args['id'] ) ) ? $args['id'] : 0;
			$col_nm = ( !empty( $args['col_nm'] ) ) ? $args['col_nm'] : ''; 
			$value = ( !empty( $args['value'] ) ) ? $args['value'] : '';

			if( ! empty( $args['copy_from_operators'] ) && in_array( $args['operator'], $args['copy_from_operators'] ) ) {
				$value = ( is_callable( array( 'Smart_Manager_Pro_Taxonomy_Base', 'terms_batch_update_new_value' ) ) ) ? self::terms_batch_update_new_value( $args ) : $value;	
			}

			if( ! empty( $col_nm ) && in_array( $col_nm, self::$term_taxonomy_update_cols ) ) {
				$result = wp_update_term( $id, $args['dashboard_key'], array( $col_nm => $value ) );
				return ( is_wp_error( $result ) ) ? false : true;
			} else if ( !empty( $args['table_nm'] ) && 'termmeta' === $args['table_nm'] ) {
				update_term_meta( $id, $col_nm, $value );
				return true;
			}

			return $update_flag;
		}

		/**
		 * Static function to get new value for copy from operators for bulk edit.
		 *
		 * @param array $args bulk edit params.
		 * @return string updated new value.
		 */
		public static function terms_batch_update_new_value( $args = array() ) {

			global $wpdb;

			if( empty( $args['selected_table_name'] ) || empty( $args['selected_column_name'] ) || empty( $args['selected_value'] ) ) {
				return '';
			}
			$args['new_value'] = '';
			if( 'termmeta' === $args['selected_table_name'] ) {
				$args['new_value'] = get_term_meta( $args['selected_value'], $args['selected_column_name'], true );
			} else {
				$terms = get_term_by( 'term_id', $args['selected_value'], $args['dashboard_key'], 'ARRAY_A' );
				$args['new_value'] = ( ! empty( $terms ) && ! empty( $terms[ $args['selected_column_name'] ] ) ) ? $terms[ $args['selected_column_name'] ] : $args['new_value'];
			}

			return ( ( 'copy_from_field' === $args['operator'] && ( ! empty ( $args['copy_field_data_type'] ) ) ) && is_callable( array( 'Smart_Manager_Pro_Base', 'handle_serialized_data' ) ) ) ? Smart_Manager_Pro_Base::handle_serialized_data( $args ) : $args['new_value'];	
		}

		/**
		 * Function to get taxonomy values for copy from operator for bulk edit.
		 *
		 * @param array $args bulk edit params.
		 * @return string|array json encoded string or array of taxonomy values.
		 */
		public function get_batch_update_copy_from_record_ids( $args = array() ) {

			global $wpdb;
			$data = array();

			$is_ajax = ( isset( $args['is_ajax'] )  ) ? $args['is_ajax'] : true;

			$search_term = ( ! empty( $this->req_params['search_term'] ) ) ? $this->req_params['search_term'] : ( ( ! empty( $args['search_term'] ) ) ? $args['search_term'] : '' );
			$select = apply_filters( 'sm_batch_update_copy_from_term_ids_select', "SELECT t.term_id AS id, t.name AS title", $args );
			$search_cond = ( ! empty( $search_term ) ) ? " AND ( t.term_id LIKE '%".$search_term."%' OR t.name LIKE '%".$search_term."%' ) " : '';
			$search_cond_ids = ( !empty( $args['search_ids'] ) ) ? " AND t.term_id IN ( ". implode(",", $args['search_ids']) ." ) " : '';
			$results = $wpdb->get_results( $select . " FROM {$wpdb->prefix}terms as t JOIN  {$wpdb->prefix}term_taxonomy as tt ON (tt.term_id = t.term_id AND tt.taxonomy = '". $this->req_params['active_module'] ."')  WHERE 1=1 ". $search_cond ." ". $search_cond_ids, 'ARRAY_A' );

			if( count( $results ) > 0 ) {
				foreach( $results as $result ) {
					$data[ $result['id'] ] = trim($result['title']);
				}
			}

			$data = apply_filters( 'sm_batch_update_copy_from_term_ids', $data );
			
			if( $is_ajax ){
				wp_send_json( $data );
			} else {
				return $data;
			}
		}
    }
}
