<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Product' ) ) {
	class Smart_Manager_Product extends Smart_Manager_Base {
		public $dashboard_key = '',
			$default_store_model = array(),
			$prod_sort = false,
			$terms_att_search_flag = 0, //flag for handling attrbute search
			$product_visibility_visible_flag = 0, //flag for handling visibility search
			$product_old_title = array(), // array for storing the old product titles
			$product_total_count = 0, //for total products count on the grid
			$is_variations_enabled_advanced_search_condition = false; // For advanced search of 'Post Status' column for variations.

		function __construct($dashboard_key) {
			parent::__construct($dashboard_key);

			$this->dashboard_key = $dashboard_key;
			$this->post_type = array('product', 'product_variation');
			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();
			add_filter('sm_data_model',array(&$this,'products_data_model'),10,2);

			add_filter('sm_required_cols',array(&$this,'sm_beta_required_cols'),10,1);

			add_filter('sm_inline_update_pre',array(&$this,'products_inline_update_pre'),10,1);
			add_action('sm_inline_update_post',array(&$this,'products_inline_update'),10,2);

			// add_filter('posts_orderby',array(&$this,'sm_product_query_order_by'),10,2);

			add_filter( 'split_the_query', function() { return false; } ); //Filter to restrict splitting on WP_Query specially for `parent_sort_id` needed for proper display of variations
			add_filter( 'sm_posts_fields', array( &$this,'sm_product_query_post_fields' ), 100, 2 );
			add_filter( 'sm_posts_where', array( &$this,'sm_product_query_post_where_cond' ), 100, 1 );
			add_filter( 'sm_posts_orderby', array( &$this,'sm_product_query_order_by' ), 100, 2 );

			add_filter( 'sm_terms_sort_join_condition' ,array( &$this, 'sm_product_terms_sort_join_condition' ), 100, 2 );

			//filters for handling search
			add_filter('sm_search_postmeta_cond',array(&$this,'sm_search_postmeta_cond'),10,2);
			add_filter('sm_search_terms_cond',array(&$this,'sm_search_terms_cond'),10,2);

			//filter for modifying each of the search cond
			add_filter('sm_search_format_query_postmeta_col_value',array(&$this,'sm_search_format_query_postmeta_col_value'),10,2);
			add_filter('sm_search_format_query_terms_col_name',array(&$this,'sm_search_format_query_terms_col_name'),10,2);

			add_filter('sm_search_query_formatted',array(&$this,'sm_search_query_formatted'),10,2);

			add_filter('sm_search_query_terms_select',array(&$this,'sm_search_query_terms_select'),10,2);
			add_filter('sm_search_query_terms_from',array(&$this,'sm_search_query_terms_from'),10,2);
			add_filter('sm_search_query_terms_where',array(&$this,'sm_search_query_terms_where'),10,2);

			add_filter('sm_search_query_posts_where',array(&$this,'sm_search_query_posts_where'),10,2);

			add_action('sm_search_terms_condition_complete',array(&$this,'search_terms_condition_complete'),10,2);
			add_action('sm_search_terms_conditions_array_complete',array(&$this,'search_terms_conditions_array_complete'),10,1);

			add_filter('sm_search_query_postmeta_where',array(&$this,'sm_search_query_postmeta_where'),10,2);
			add_action( 'sm_search_postmeta_condition_complete', array( &$this,'search_postmeta_condition_complete' ), 10, 3 );

			add_filter('sm_batch_update_copy_from_ids_select',array(&$this,'sm_batch_update_copy_from_ids_select'),10,2);
			// add_action('admin_footer',array(&$this,'attribute_handling'));

			add_action( 'sm_found_posts', array( &$this,'product_found_posts' ), 99, 1 );

			add_filter( 'sm_generate_column_state', array( &$this, 'product_generate_column_state' ), 10, 2 );
			add_filter( 'sm_map_column_state_to_store_model', array( &$this, 'product_map_column_state_to_store_model' ), 10, 2 );
			add_filter( 'sm_filter_updated_edited_data', array( &$this, 'filter_updated_edited_data' ) );
			add_filter( 'sm_col_model_for_export', array( &$this, 'col_model_for_export' ), 12, 2 );
			add_filter( 'sm_search_posts_cond', array( &$this, 'sm_search_posts_cond' ), 10, 2 );
			add_filter( 'sm_simple_search_ignored_posts_columns', array( &$this, 'sm_simple_search_ignored_posts_columns' ), 10, 2 );
			add_filter( 'sm_can_optimize_dashboard_speed', function() { return true; } );
			add_filter( 'sm_posts_groupby', array( &$this, 'query_group_by' ), 100, 1 );
			add_filter( 'sm_posts_join_paged', array( &$this, 'query_join' ), 100, 2 );
			add_filter( 'sa_manager_dashboard_columns', array( $this, 'product_dashboard_columns' ), 10, 4 );
		}

		public function product_dashboard_columns( $column = array(), $src = array(), $visible_columns = array(), $dashboard_model_saved = array() )
		{
			if ( ( ! empty( $dashboard_model_saved ) ) || empty( $column ) || ( ! is_array( $column ) ) || empty( $src ) || empty( $visible_columns ) || ( ! is_array( $visible_columns ) ) ){
				return $column;
			}
			//Code for unsetting the position for hidden columns
			if ( ! empty( $column['position'] ) ) {
				unset( $column['position'] );
			}

			$position = array_search( $src, $visible_columns );

			if ($position !== false) {
				$column['position'] = $position + 1;
				$column['hidden'] = false;
			} else {
				$column['hidden'] = true;
			}
			return $column;
		}

		//Function for map the column state to include 'treegrid' for 'show_variations'
		public function product_map_column_state_to_store_model( $store_model, $column_model_transient ) {

			if( isset( $column_model_transient['treegrid'] ) ) {
				$store_model['treegrid'] = $column_model_transient['treegrid'];
			}

			return $store_model;
		}

		//Function for modifying the column state to include 'treegrid' for 'show_variations'
		public function product_generate_column_state( $column_model_transient, $store_model ) {

			if( isset( $store_model['treegrid'] ) ) {
				$column_model_transient['treegrid'] = $store_model['treegrid'];
			}

			return $column_model_transient;
		}

		/**
		 * Handles the product found posts query.
		 *
		 * @param string $query The query string to find product posts. Default is an empty string.
		 * @return void
		 */
		public function product_found_posts( $query = '' ) {
			if ( empty( $query ) ) {
				return;
			}
			global $wpdb;
			$query = str_replace(" ('product', 'product_variation')", "('product')", $query );
			$from_strpos = strpos( $query, 'FROM' );
			$from_pos = ( !empty( $from_strpos ) ) ? $from_strpos : 0;
			if( $from_pos > 0 ) {
				$query = substr( $query, $from_pos );
				$groupby_strpos = strpos( $query, 'GROUP' );
				$limit_pos = ( !empty( $groupby_strpos ) ) ? $groupby_strpos : 0;
				$query = substr( $query, 0, $limit_pos );
				if( !empty( $query ) ) {
					$this->product_total_count = $wpdb->get_var( 'SELECT COUNT( DISTINCT( '.$wpdb->prefix.'posts.id ) ) '. $query );
				}


			}
		}

		//Function for overriding the select clause for fetching the ids for batch update 'copy from' functionality
		public function sm_batch_update_copy_from_ids_select( $select, $args ) {

			$select = " SELECT ID AS id, 
							( CASE 
			            		WHEN (post_excerpt != '' AND post_type = 'product_variation') THEN CONCAT(post_title, ' - ( ', post_excerpt, ' ) ')
								ELSE post_title
			            	END ) as title ";

			return $select;
		}

		public function sm_beta_required_cols( $cols ) {
			$required_cols = array('posts_post_title', 'posts_post_parent', 'postmeta_meta_key__product_attributes_meta_value__product_attributes');
			return array_merge($cols, $required_cols);
		}

		//function to modify the terms search column name while forming the formatted search query
		public function sm_search_format_query_terms_col_name($search_col='', $search_params=array()) {

			if( !empty($search_col) && substr($search_col, 0, 10) == 'attribute_' ) {
				$search_col = substr($search_col, 10);
			}

			return $search_col;
		}

		//function to handle child ids for terms search
		public function search_terms_condition_complete($result_terms_search = array(), $search_params = array()) {

			global $wpdb;

			if( empty($search_params) ) {
				return;
			}

			//Code to handle child ids in case of category search
            if (!empty($result_terms_search) && !empty($search_params) && substr($search_params['cond_terms_col_name'], 0, 10) != 'attribute_' ) {

            	$flag = ( !empty($search_params['terms_search_result_flag']) ) ? $search_params['terms_search_result_flag'] : ', 0';

                //query when attr cond has been applied
                if ( $this->terms_att_search_flag == 1 ){
                    $query = "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
                            ( SELECT {$wpdb->prefix}posts.id ". $flag ." ,1
                                FROM {$wpdb->prefix}posts
                                JOIN {$wpdb->base_prefix}sm_advanced_search_temp AS temp1
                                    ON (temp1.product_id = {$wpdb->prefix}posts.id)
                                JOIN {$wpdb->base_prefix}sm_advanced_search_temp AS temp2
                                    ON (temp2.product_id = {$wpdb->prefix}posts.post_parent)
                                WHERE temp2.cat_flag = 1 )";
                } else {
                    //query when no attr cond has been applied
                    $query = "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
                            ( SELECT {$wpdb->prefix}posts.id ". $flag ." ,1
                                FROM {$wpdb->prefix}posts 
                                JOIN {$wpdb->base_prefix}sm_advanced_search_temp
                                    ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = {$wpdb->prefix}posts.post_parent)
                                WHERE {$wpdb->base_prefix}sm_advanced_search_temp.cat_flag = 1 
                                	AND {$wpdb->base_prefix}sm_advanced_search_temp.flag > 0
                            )";
                }

                $result = $wpdb->query ( $query );
            }

            if( !empty($search_params) && trim($search_params['cond_terms_col_name']) == 'product_visibility' && trim($search_params['cond_terms_operator']) == 'LIKE' && trim($search_params['cond_terms_col_value']) == 'visible' ) {
                $this->product_visibility_visible_flag = 1;
            }
		}


		//function to handle visibility search
		public function search_terms_conditions_array_complete($search_params = array()) {

			if( empty($search_params) ) {
				return;
			}

			global $wpdb;

			if( !empty($this->product_visibility_visible_flag) && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {

                $query_advanced_search_taxonomy_id = "SELECT {$wpdb->prefix}term_taxonomy.term_taxonomy_id
                                                      FROM {$wpdb->prefix}term_taxonomy
                                                        JOIN {$wpdb->prefix}terms
                                                            ON ( {$wpdb->prefix}terms.term_id = {$wpdb->prefix}term_taxonomy.term_id)
                                                      WHERE {$wpdb->prefix}term_taxonomy.taxonomy LIKE 'product_visibility' 
                                                            AND {$wpdb->prefix}terms.slug IN ('exclude-from-search', 'exclude-from-catalog')";
                $result_advanced_search_taxonomy_id = $wpdb->get_col ( $query_advanced_search_taxonomy_id );

                if( count($result_advanced_search_taxonomy_id) > 0 ) {
                    $result_taxonomy_ids = implode(",",$result_advanced_search_taxonomy_id);

                    $query_terms_visibility = " DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp
                                                WHERE product_id IN (SELECT DISTINCT {$wpdb->prefix}posts.id
                                                                    FROM {$wpdb->prefix}posts
                                                                        JOIN {$wpdb->prefix}term_relationships
                                                                        ON ({$wpdb->prefix}term_relationships.object_id = {$wpdb->prefix}posts.id) 
                                                                    WHERE {$wpdb->prefix}term_relationships.term_taxonomy_id IN (". $result_taxonomy_ids ."))";
                    $result_terms_visibility = $wpdb->query( $query_terms_visibility );
                }

            }
		}

		//function to handle custom postmeta conditions for advanced search
		public function sm_search_postmeta_cond($postmeta_cond = '', $search_params = array()) {
			if ( !empty($search_params) && !empty($search_params['search_col']) && $search_params['search_col'] == '_product_attributes' ) {
				if ($search_params['search_operator'] == 'is') {
					$postmeta_cond = " ( ". $search_params['search_string']['table_name'].".meta_key LIKE '". $search_params['search_col'] . "' AND ". $search_params['search_string']['table_name'] .".meta_value LIKE %s" . " )";
				} else if ($search_params['search_operator'] == 'is not') {
					$postmeta_cond = " ( ". $search_params['search_string']['table_name'].".meta_key LIKE '". $search_params['search_col'] . "' AND ". $search_params['search_string']['table_name'] .".meta_value NOT LIKE %s" . " )";
				}
			}

			return $postmeta_cond;
		}


		//function to handle custom terms conditions for advanced search
		public function sm_search_terms_cond($terms_cond = '', $search_params = array()) {

			global $wpdb;

			if( !empty($search_params) ) {

				$search_params['search_col'] = $this->sm_search_format_query_terms_col_name($search_params['search_col']);

				if ($search_params['search_operator'] == 'is') {
					if( $search_params['search_string']['value'] == "''" ) { //for handling empty search strings
						$empty_cond = ''; //variable for handling conditions for empty string

	                    // if( substr($search_params['search_col'],0,3) == 'pa_' ) { //for attributes column TODO in products
	                    //     $empty_cond = " AND ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '%pa_%' ";
	                    // }

	                    $terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE 'product_type' ". $empty_cond ." )";
					} else {

						if( ( 'product_visibility' === $search_params['search_col'] ) && ( ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) ) { //TODO in products

                            if( $search_params['search_value'] == 'visible' ) {
                                $terms_cond = " ( ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-search' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-catalog' ) OR ( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' ) )";
                            } else if( $search_params['search_value'] == 'hidden' ) {
                                $terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug = 'exclude-from-search' ) &&  ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug = 'exclude-from-catalog' ) ";
                            } else if( $search_params['search_value'] == 'catalog' ) { //TODO: Needs Improvement
                                $terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug = 'exclude-from-search' ) &&  ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-catalog' ) ";
                            } else if( $search_params['search_value'] == 'search' ) { //TODO: Needs Improvement
                                $terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug = 'exclude-from-catalog' ) &&  ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-search' ) ";
                            }

                        } else if( ( 'product_visibility_featured' === $search_params['search_col'] ) && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {
							$operator = ( 'yes' === $search_params['search_value'] ) ? '=' : '!=';
							$terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy $operator 'product_visibility' AND ". $wpdb->prefix ."terms.slug $operator 'featured' ) ";
                        }
					}
				} else if ($search_params['search_operator'] == 'is not') {
					if( $search_params['search_string']['value'] != "''" ) {
						$attr_cond = '';

                        if( substr($search_params['search_col'],0,3) == 'pa_' ) { //for attributes column
                            $attr_cond = " AND ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '%pa_%' ";
                        }

                        if( $search_params['search_col'] == 'product_visibility' && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {

                            if( $search_params['search_value'] == 'visible' ) {
                                $terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug = 'exclude-from-search' OR ". $wpdb->prefix ."terms.slug = 'exclude-from-catalog' )";
                            } else if( $search_params['search_value'] == 'hidden' ) {
                                $terms_cond = " ( ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-search' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-catalog' ) OR ( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' ) ) ";
                            } else if( $search_params['search_value'] == 'catalog' ) { //TODO: Needs Improvement
                                $terms_cond = " ( ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-search' ) OR ( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' ) )";
                            } else if( $search_params['search_value'] == 'search' ) { //TODO: Needs Improvement
                                $terms_cond = " ( ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE '". $search_params['search_col'] . "' AND ". $wpdb->prefix ."terms.slug != 'exclude-from-catalog' ) OR ( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' ) )";
                            }

                        } else if( $search_params['search_col'] == 'product_visibility_featured' && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {
							$operator = ( 'yes' === $search_params['search_value'] ) ? '!=' : '=';
							$terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy $operator 'product_visibility' AND ". $wpdb->prefix ."terms.slug $operator 'featured' ) ";
                        } else {
                            $terms_cond = " ( ". $wpdb->prefix ."term_taxonomy.taxonomy NOT LIKE '". $search_params['search_col'] . "' ". $attr_cond ." AND ". $wpdb->prefix ."terms.slug NOT LIKE %s" . " )";
                        }
					}
				}
			}

			return $terms_cond;

		}

		//function to modify the advanced search query formatted array
		public function sm_search_query_formatted($advanced_search_query = array(), $search_params = array()) {

			if( !empty($search_params) ) {
				if ($search_params['search_operator'] == 'is') {
					if( $search_params['search_string']['value'] != "''" ) {
						if( $search_params['search_col'] == 'product_visibility' && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {
							if( $search_params['search_value'] != 'visible' ) {
								$advanced_search_query['cond_terms_col_name'] .= $search_params['search_col'] ." AND "; //added only for this specific search condition
							}
						}
					}
				}
			}

			return $advanced_search_query;
		}

		//function to handle terms custom select clause
		public function sm_search_query_terms_select($sm_search_query_terms_select = '', $search_params = array()) {

			if ( !empty($search_params['cond_terms_col_name']) && substr($search_params['cond_terms_col_name'], 0, 10) == 'attribute_' ) {
		        $sm_search_query_terms_select .= " ,0";
		        $this->terms_att_search_flag = 1; //Flag to handle the child ids for cat advanced search
		    } else if ( !empty($search_params['cond_terms_col_name']) && substr($search_params['cond_terms_col_name'], 0, 10) != 'attribute_' ) {
		        $sm_search_query_terms_select .= " ,1  ";
		    }

			return $sm_search_query_terms_select;
		}

		//function to handle terms custom from clause
		public function sm_search_query_terms_from($sm_search_query_terms_from = '', $search_params = array()) {

			global $wpdb;

			if ( !empty($search_params['cond_terms_col_name']) && substr($search_params['cond_terms_col_name'], 0, 10) == 'attribute_' ) {
		        $sm_search_query_terms_from = " FROM {$wpdb->prefix}posts
	                                            LEFT JOIN {$wpdb->prefix}term_relationships
	                                                ON ({$wpdb->prefix}term_relationships.object_id = {$wpdb->prefix}posts.id)
	                                            JOIN {$wpdb->prefix}postmeta
	                                                ON ( {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}posts.id
	                                                	AND {$wpdb->prefix}posts.post_type IN ('". implode( "','", $search_params['post_type'] ) ."') )";
	            $this->terms_att_search_flag = 1; //Flag to handle the child ids for cat advanced search
		    } else if ( !empty($search_params['cond_terms_col_name']) && substr($search_params['cond_terms_col_name'], 0, 10) != 'attribute_' ) {
		        $sm_search_query_terms_from = "FROM {$wpdb->prefix}posts
                                                JOIN {$wpdb->prefix}term_relationships
                                                    ON ({$wpdb->prefix}term_relationships.object_id = {$wpdb->prefix}posts.id
                                                		AND {$wpdb->prefix}posts.post_type IN ('". implode( "','", $search_params['post_type'] ) ."') )";
		    }

			return $sm_search_query_terms_from;
		}

		//function to handle terms custom where clause
		public function sm_search_query_terms_where($sm_search_query_terms_where = '', $search_params = array()) {

			global $wpdb, $wp_version;

			$col_name = ( ! empty( $search_params['cond_terms_col_name'] ) ) ? $search_params['cond_terms_col_name'] : '';
			$col_op	= ( ! empty( $search_params['cond_terms_operator'] ) ) ? $search_params['cond_terms_operator'] : '';
			$col_value = ( ! empty( $search_params['cond_terms_col_value'] ) ) ? $search_params['cond_terms_col_value'] : '';

			//code to exlcude featured products when filter products that are not featured.
			if ( ( ! empty( $col_value ) ) && ( "product_visibility_featured" === $col_name ) && ( ( ( "LIKE" === $col_op ) && ( "no" === $col_value ) ) || ( ( "NOT LIKE" === $col_op ) && ( "yes" === $col_value ) ) ) ) {
				$featured_term = get_term_by( 'slug', "featured", "product_visibility" );
				if ( ! is_wp_error( $featured_term ) && ! empty( $featured_term->term_taxonomy_id ) ) {
					$sm_search_query_terms_where .= " AND {$wpdb->prefix}posts.ID NOT IN ( SELECT object_id FROM {$wpdb->prefix}term_relationships WHERE term_taxonomy_id IN (". $featured_term->term_taxonomy_id .") )";
				}
			}

			// Handle product visibility conditions for 'search' and 'catalog' values.
			if ( ( ! empty( $col_value ) ) && ( "product_visibility" === $col_name ) && ( "NOT LIKE" === $col_op ) ) {
				$visibility_tts = array(
					'search' => 'exclude-from-search',
					'catalog' => 'exclude-from-catalog',
				);
				if ( array_key_exists( $col_value, $visibility_tts ) ) {
					$term_info = get_term_by( 'slug', $visibility_tts[ $col_value ], 'product_visibility' );
					if ( ! is_wp_error( $term_info ) && ! empty( $term_info->term_taxonomy_id ) ) {
						$sm_search_query_terms_where .= " AND {$wpdb->prefix}posts.ID NOT IN ( SELECT object_id FROM {$wpdb->prefix}term_relationships WHERE term_taxonomy_id IN (". $term_info->term_taxonomy_id .") )";
					}
				}
			}

			if ( !empty($col_name) && substr($col_name, 0, 10) == 'attribute_' ) {

				$tt_ids_to_exclude = array();

				if( !empty($search_params['result_taxonomy_ids']) ) {
					$taxonomy_cond = " ({$wpdb->prefix}term_relationships.term_taxonomy_id IN (". $search_params['result_taxonomy_ids'] .")) ";
				}

				if( !empty($col_op) && 'NOT LIKE' === $col_op ) {
					$taxonomy = $this->sm_search_format_query_terms_col_name($col_name);

					if( "''" === $col_value || empty( $col_value ) ){
						if (version_compare ( $wp_version, '4.5', '>=' )) {
							$tt_ids_to_exclude = get_terms( array(
														'taxonomy' => $taxonomy,
														'fields' => 'tt_ids',
												));
						} else {
							$tt_ids_to_exclude = get_terms( $taxonomy, array(
														'fields' => 'tt_ids',
												));
						}
					} else {
						$term_meta = get_term_by( 'slug', $col_value, $taxonomy );
						if ( ! is_wp_error( $term_meta ) && ! empty( $term_meta->term_taxonomy_id ) ) {
							$tt_ids_to_exclude[] = $term_meta->term_taxonomy_id;
						}
					}
				}

				$taxonomy_cond = (!empty($taxonomy_cond)) ? ' ( '. $taxonomy_cond : '';

		        $sm_search_query_terms_where = " WHERE ". $taxonomy_cond;

		        if( $col_value != "''" && !empty( $col_value ) ) {
		        	$sm_search_query_terms_where .= " OR ({$wpdb->prefix}postmeta.meta_key ". ($col_value == "''" || empty( $col_value ) ? 'LIKE' : $col_op) ." '".trim($col_name) .
                                                        "' AND {$wpdb->prefix}postmeta.meta_value ". $col_op ." '". trim($col_value)."') ";
		        }

		        $sm_search_query_terms_where .= " ) ";

		        if( !empty($tt_ids_to_exclude) ) {
		        	$sm_search_query_terms_where .= " AND {$wpdb->prefix}posts.ID NOT IN ( SELECT object_id 
		        																			FROM {$wpdb->prefix}term_relationships
		        																			WHERE term_taxonomy_id IN (". implode(",", $tt_ids_to_exclude) .") )";
		        }

                $this->terms_att_search_flag = 1; //Flag to handle the child ids for cat advanced search
		    } else if( 'product_visibility' == $col_name && 'NOT LIKE' == $col_op && 'hidden' == $col_value ) { //Code to exclude 'hidden' products
				$taxonomy_ids = $wpdb->get_col (
									$wpdb->prepare( "SELECT tt.term_taxonomy_id
													FROM {$wpdb->prefix}term_taxonomy as tt
													JOIN {$wpdb->prefix}terms as t
														ON ( t.term_id = tt.term_id
															AND tt.taxonomy = %s)
													WHERE t.slug IN (%s, %s)",
													'product_visibility',
													'exclude-from-search',
													'exclude-from-catalog'
									)
								);
				if( ! empty( $taxonomy_ids ) && 2 == count( $taxonomy_ids ) ){
					$sm_search_query_terms_where .= " AND {$wpdb->prefix}posts.ID NOT IN ( SELECT tr1.object_id
																FROM {$wpdb->prefix}term_relationships as tr1
																	JOIN {$wpdb->prefix}term_relationships as tr2
																		ON(tr1.object_id = tr2.object_id
																			AND tr1.term_taxonomy_id = ". $taxonomy_ids[0] ."
																			AND tr2.term_taxonomy_id = ". $taxonomy_ids[1] .") )";
				}
			}

		    // else if ( !empty($col_name) && substr($col_name, 0, 10) != 'attribute_' ) {
		    // 	$sm_search_query_terms_where = (!empty($taxonomy_cond)) ? ' WHERE '. $taxonomy_cond : '';
		    // }

			return $sm_search_query_terms_where;
		}

		//function to handle postmeta custom where clause
		public function sm_search_query_postmeta_where($sm_search_query_postmeta_where = '', $search_params = array()) {

			global $wpdb;

			if(!empty( $search_params ) && !empty( $search_params['cond_postmeta_col_name'] ) ) {
				// if( $search_params['cond_postmeta_col_name'] == '_regular_price' || $search_params['cond_postmeta_col_name'] == '_sale_price' ) {
	            //    $sm_search_query_postmeta_where .= " AND {$wpdb->prefix}postmeta.post_id NOT IN (SELECT post_parent
	            //                                                       FROM {$wpdb->prefix}posts
	            //                                                       WHERE post_type IN ('product', 'product_variation')
	            //                                                         AND post_parent > 0) ";
	            // }

	            if( $search_params['cond_postmeta_col_name'] == '_product_attributes' ) {
	            	$index = strpos($sm_search_query_postmeta_where, 'WHERE');
		            if( $index !== false ){
		            	$sm_search_query_postmeta_where = substr($sm_search_query_postmeta_where, ($index + 5) );
		            }
		        	$sm_search_query_postmeta_where = " WHERE ( (". $sm_search_query_postmeta_where .") OR ({$wpdb->prefix}postmeta.meta_key LIKE 'attribute%' AND {$wpdb->prefix}postmeta.meta_value ". $search_params['cond_postmeta_operator'] ." '%". $search_params['cond_postmeta_col_value'] ."%') ) ";
	            }
			}

			return $sm_search_query_postmeta_where;

		}

		//function to handle postmeta condition complete
		public function search_postmeta_condition_complete( $result_terms_search = array(), $search_params = array(), $query_params = array() ) {

			global $wpdb;

			if( ! empty( $search_params ) && ! empty( $query_params ) && ! empty( $search_params['cond_postmeta_col_name'] ) ) {
				// code to insert parent ids in case of search for regular_price or sale_price
				if( $search_params['cond_postmeta_col_name'] == '_regular_price' || $search_params['cond_postmeta_col_name'] == '_sale_price' ) {
					$query_params['select'] = str_replace( 'postmeta.post_id', 'posts.post_parent', $query_params['select'] );

					$from_join_str = 'sm_advanced_search_temp.product_id = '.$wpdb->prefix.'postmeta.post_id';
					if( strpos( $query_params['from'], $from_join_str ) !== false ) {
						$query_params['from'] = str_replace( $from_join_str, 'sm_advanced_search_temp.product_id = '.$wpdb->prefix.'posts.post_parent', $query_params['from'] );
					}
					$search_val = ( ! empty( $search_params['cond_postmeta_col_value'] ) ) ? $search_params['cond_postmeta_col_value'] : '';
					$query_postmeta_search = $wpdb->prepare( "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
													(". $query_params['select'] ."
													". $query_params['from'] ."
													".$query_params['where'].")", $search_val );
					$result_postmeta_search = $wpdb->query ( $query_postmeta_search );
				}
			}
		}

		//function to handle posts custom where clause
		public function sm_search_query_posts_where($posts_advanced_search_where = '', $search_params = array()) {
			global $wpdb;
			if ( ! empty( $search_params['cond'] ) && FALSE !== strpos( $search_params['cond'],'post_status' ) && ! $this->is_variations_enabled_advanced_search_condition ) {
	            $posts_advanced_search_where .= " AND ".$wpdb->prefix."posts.post_parent = 0 ";
	        }
			return $posts_advanced_search_where;
		}

		/**
		 * Query post fields for 'Products' dashboard.
		 *
		 * @param string $fields The fields to be queried. Default is an empty string.
		 * @param array $sort_params The parameters for sorting the query. Default is an empty array.
		 * @return string The modified fields for the query.
		 */
		public function sm_product_query_post_fields ( $fields = '', $sort_params = array() ) {

			global $wpdb;

			$fields .= ',if('.$wpdb->prefix.'posts.post_parent = 0,'.$wpdb->prefix.'posts.id,'.$wpdb->prefix.'posts.post_parent - 1 + ('.$wpdb->prefix.'posts.id)/pow(10,char_length(cast('.$wpdb->prefix.'posts.id as char)))) as parent_sort_id';

			// Code for handling taxonomy sort
			if ( !empty( $sort_params ) && empty( $sort_params['default'] ) && ( ( !empty( $sort_params['column_nm'] ) && ( ( $sort_params['column_nm'] != 'ID' ) || ( $sort_params['column_nm'] == 'ID' && $sort_params['sortOrder'] == 'ASC' ) ) ) || empty( $sort_params['coumn_nm'] ) ) ) {

				if( empty( $sort_params['column_nm'] ) && ! empty( $sort_params['column'] ) ) {
					$col_exploded = explode( "/", $sort_params['column'] );

					$sort_params['table'] = $col_exploded[0];

					if ( sizeof($col_exploded) == 2) {
						$sort_params['column_nm'] = $col_exploded[1];
					}

					$sort_params['sortOrder'] = strtoupper( $sort_params['sortOrder'] );
				}


				if ( !empty( $sort_params['table'] ) && $sort_params['table'] == 'terms' && $sort_params['column_nm'] == 'product_type' ) {
					$fields .= " ,IFNULL(taxonomy_sort.term_name, 'Variation') as sort_term_name ";
				}
			}

			return $fields;
		}

		/**
		 * Adds custom conditions to the WHERE clause of the product query.
		 *
		 * @param string $where The existing WHERE clause of the product query.
		 * @return string The modified WHERE clause with custom conditions.
		 */
		public function sm_product_query_post_where_cond ( $where = '' ) {

			global $wpdb;
			$where_params = $this->get_where_clause_for_search(
				array(
					'where' => $where,
					'optimize_dashboard_speed' => true,
				)
			);
			$where = ( ! empty( $where_params['where'] ) ) ? $where_params['where'] : $where;
			$lang = apply_filters( 'wpml_current_language', NULL );
			// WPML Workaround to show current active lang products.
			if ( ( ! empty( $lang ) ) && ( class_exists( 'SitePress' ) ) && ( defined( 'ICL_SITEPRESS_VERSION' ) ) ) {//add class exist check
				$where.=$wpdb->prepare( "
					AND {$wpdb->posts}.ID IN (
						SELECT element_id
						FROM {$wpdb->prefix}icl_translations
						WHERE language_code = %s
						AND element_type LIKE 'post_product%%'
					)
				", $lang );
			}
			//Code to get the ids of all the products whose post_status is thrash
	        $query_trash = "SELECT ID FROM {$wpdb->prefix}posts 
	                        WHERE post_status = 'trash'
	                            AND post_type IN ('product')";
	        $results_trash = $wpdb->get_col( $query_trash );
	        $rows_trash = $wpdb->num_rows;

	        // Code to get all the variable parent ids whose type is set to 'simple'

	        //Code to get the taxonomy id for 'simple' product_type
	        // $query_taxonomy_ids = "SELECT taxonomy.term_taxonomy_id as term_taxonomy_id
	        //                             FROM {$wpdb->prefix}terms as terms
	        //                                 JOIN {$wpdb->prefix}term_taxonomy as taxonomy ON (taxonomy.term_id = terms.term_id)
	        //                             WHERE taxonomy.taxonomy = 'product_type'
	        //                             	AND terms.slug IN ('variable', 'variable-subscription')";
	        // $variable_taxonomy_ids = $wpdb->get_col( $query_taxonomy_ids );

	        // if ( !empty($variable_taxonomy_ids) ) {
	        // 	$query_post_parent_not_variable = "SELECT distinct products.post_parent 
			// 	                            FROM {$wpdb->prefix}posts as products 
			// 	                            WHERE NOT EXISTS (SELECT * 
			// 	                            					FROM {$wpdb->prefix}term_relationships 
			// 	                            					WHERE object_id = products.post_parent
			// 	                            						AND term_taxonomy_id IN (". implode(",",$variable_taxonomy_ids) ."))
			// 	                              AND products.post_parent > 0 
			// 	                              AND products.post_type = 'product_variation'";
		    //     $results_post_parent_not_variable = $wpdb->get_col( $query_post_parent_not_variable );
		    //     $rows_post_parent_not_variable = $wpdb->num_rows;

		    //     for ($i=sizeof($results_trash),$j=0;$j<sizeof($results_post_parent_not_variable);$i++,$j++ ) {
		    //         $results_trash[$i] = $results_post_parent_not_variable[$j];
		    //     }
	        // }

	        // if ($rows_trash > 0 || $rows_post_parent_not_variable > 0) {
	        if ($rows_trash > 0 ) {
	            $where .= " AND {$wpdb->prefix}posts.post_parent NOT IN (" .implode(",",$results_trash). ")";
	        }
			return array( 'sql' => $where, 'value' => ( ! empty( $where_params['where_cond'] ) && ( is_array( $where_params['where_cond'] ) ) && ( ! empty( $where_params['search_text'] ) ) ) ? array_fill( 0, sizeof( $where_params['where_cond'] ) + 1, '%' . $wpdb->esc_like( $where_params['search_text'] ) . '%' ) : ''  );
		}

		/**
		 * Joins conditions for sorting product terms.
		 *
		 * @param string $join_condition The existing join condition.
		 * @param array $sort_params Parameters for sorting.
		 * @return string The modified join condition.
		 */
		public function sm_product_terms_sort_join_condition ( $join_condition = '', $sort_params = array() ) {
			global $wpdb;
			if( !empty( $sort_params['column'] ) ) {
				$col_exploded = explode( "/", $sort_params['column'] );
				$sort_params['column_nm'] = ( ! empty( $col_exploded[1] ) ) ? $col_exploded[1] : '';
			}

			if( ! empty( $sort_params['column_nm'] ) && 'product_visibility_featured' === $sort_params['column_nm'] ) {
				return " AND ( ". $wpdb->prefix ."term_taxonomy.taxonomy LIKE 'product_visibility' AND ". $wpdb->prefix ."terms.slug = 'featured' ) ";
			}

			return $join_condition;
		}

		/**
		 * Orders the product query results based on specified criteria.
		 *
		 * @param string $order_by The column by which to order the results.
		 * @param array $params Additional parameters for the query.
		 * @return string $order_by The modified order by clause.
		 */
		public function sm_product_query_order_by ( $order_by = '', $params = array() ) {
			global $wpdb;
			$sort_params = $params['sort_params'];
			$order_by = $this->get_order_by_clause_for_sort( array( 'order_by' => $order_by, 'sort_params' => $sort_params ) );
			if ( ! empty( $sort_params ) && empty( $sort_params['default'] ) && ( ( ! empty( $sort_params['column_nm'] ) && ( ( $sort_params['column_nm'] != 'ID' ) || ( $sort_params['column_nm'] == 'ID' && $sort_params['sortOrder'] == 'ASC' ) ) ) || empty( $sort_params['coumn_nm'] ) ) ) {

				if( empty( $sort_params['column_nm'] ) && ! empty( $sort_params['column'] ) ) {
					$col_exploded = explode( "/", $sort_params['column'] );

					$sort_params['table'] = $col_exploded[0];

					if ( sizeof($col_exploded) == 2) {
						$sort_params['column_nm'] = $col_exploded[1];
					}

					$sort_params['sortOrder'] = strtoupper( $sort_params['sortOrder'] );
				}

				$sort_order = ( !empty( $sort_params['sortOrder'] ) ) ? $sort_params['sortOrder'] : 'ASC';

				if ( ( ! empty( $sort_params['table'] ) ) && 'posts' === $sort_params['table'] && ! empty( $sort_params['column_nm'] ) ) {
					$order_by = $sort_params['column_nm'] .' '. $sort_order;
				} else if ( ! empty( $sort_params['table'] ) && 'terms' === $sort_params['table'] && true === $this->terms_sort_join && ! empty( $sort_params['column_nm'] ) ) {
					$order_by = ( ( $sort_params['column_nm'] == 'product_type' ) ? ' sort_term_name ' : ' taxonomy_sort.term_name ' ) .''. $sort_order ;
				}

				$this->prod_sort = true;

			} else {
				$order_by = 'parent_sort_id DESC';
				$this->prod_sort = false;
			}

			return $order_by;
		}

		public function products_data_model ($data_model, $data_col_params) {

			global $wpdb, $current_user;

			$data_model ['display_total_count'] = ( !empty( $this->product_total_count ) ) ? $this->product_total_count : $data_model ['total_count'];

			//Code for loading the data for the attributes column

			if(empty($data_model) || empty($data_model['items'])) {
				return $data_model;
			}

			$current_store_model = get_transient( 'sa_sm_'.$this->dashboard_key );
			if( ! empty( $current_store_model ) && !is_array( $current_store_model ) ) {
				$current_store_model = json_decode( $current_store_model, true );
			}
			$col_model = (!empty($current_store_model['columns'])) ? $current_store_model['columns'] : array();

			if (!empty($col_model)) {

				//Code to get attr values by slug name
				$attr_val_by_slug = array();
				$attr_taxonomy_nm = get_object_taxonomies($this->post_type);

				if ( !empty($attr_taxonomy_nm) ) {
					foreach ( $attr_taxonomy_nm as $key => $attr_taxonomy ) {
						if ( substr($attr_taxonomy,0,13) != 'attribute_pa_' ) {
							unset( $attr_taxonomy_nm[$key] );
						}
					}

					$attr_terms = array();

					if( !empty($attr_taxonomy_nm) ) {
						$attr_terms = get_terms($attr_taxonomy_nm, array('hide_empty'=> 0,'orderby'=> 'id'));
					}

					if ( !empty($attr_terms) ){
						foreach ( $attr_terms as $attr_term ) {
							if (empty($attr_val_by_slug[$attr_term->taxonomy])) {
								$attr_val_by_slug[$attr_term->taxonomy] = array();
							}
							$attr_val_by_slug[$attr_term->taxonomy][$attr_term->slug] = $attr_term->name;
						}
					}
				}

				$taxonomy_nm = array();
				$term_taxonomy_ids = array();
				$post_ids = array();
				$parent_ids = array();
				$product_attributes_postmeta = array();
				$post_parent_hidden = 0;

				foreach ($col_model as $column) {
					if (empty($column['src'])) continue;

					$src_exploded = explode("/",$column['src']);

					if (!empty($src_exploded) && $src_exploded[1] == 'product_attributes') {
						$attr_values = $column['values'];

						if (!empty($attr_values)) {
							foreach ($attr_values as $key => $attr_value) {
								$taxonomy_nm[] = $key;
								$term_taxonomy_ids = $term_taxonomy_ids + $attr_value;
							}
						}
					} if( !empty($src_exploded) && $src_exploded[1] == 'post_parent' && !empty( $column['hidden'] ) ) {
						$post_parent_hidden = 1;
					}
				}

				// Code for fetching the parent ids incase the post_parent is hidden
				if( $post_parent_hidden == 1 ) {

					$ids = array();
					$post_parents = array();

					foreach( $data_model['items'] as $key => $data ) {
						if (empty($data['posts_id'])) continue;
						$ids[] = $data['posts_id'];
					}

					if( !empty($ids) ) {
						$results = $wpdb->get_results($wpdb->prepare("SELECT ID, post_parent FROM {$wpdb->prefix}posts WHERE 1=%d AND post_type IN ('product', 'product_variation') AND id IN (". implode(",",$ids) .")", 1), 'ARRAY_A');

						if( !empty( $results ) > 0 ) {
							foreach( $results as $result ) {
								$post_parents[ $result['ID'] ] = $result['post_parent'];
							}
						}
					}
				}

				$product_visibility_index = sa_multidimesional_array_search('terms/product_visibility', 'src', $col_model);
				$product_featured_index = sa_multidimesional_array_search('terms/product_visibility_featured', 'src', $col_model);
				$product_shop_url_index = sa_multidimesional_array_search('custom/product_shop_url', 'src', $col_model);

				$variation_ids = array();
				$key_post_ids = array();

				$parent_product_count = 0;
				foreach ($data_model['items'] as $key => $data) {

					if (empty($data['posts_id'])) continue;
					$post_ids[] = $data['posts_id'];

					if( isset( $data['posts_post_parent'] ) && 0 === intval( $data['posts_post_parent'] ) ) {
						$parent_product_count++;
					}

					if ( empty( $data['posts_post_parent'] ) ) {
						continue;
					}
					$variation_ids[] = $data['posts_id'];
					$key_post_ids[$data['posts_id']] = $key;
				}

				$data_model ['loaded_total_count'] = $parent_product_count;

				if( !empty( $variation_ids ) ) { //Code for fetching variation attributes for variation title
					$variation_attribute_results = $wpdb->get_results( $wpdb->prepare("SELECT post_id,
																			meta_key,
																			meta_value
																	FROM {$wpdb->prefix}postmeta
																	WHERE post_id IN (". implode(",", $variation_ids) .")
																		AND meta_key LIKE 'attribute_%'
																		AND 1=%d
																	GROUP BY post_id, meta_key", 1), 'ARRAY_A' );

					if( !empty( $variation_attribute_results ) ) {
						foreach( $variation_attribute_results as $result ) {

							$key = ( isset( $key_post_ids[$result['post_id']] ) ) ? $key_post_ids[$result['post_id']] : '';

							if( empty( $key ) && $key != 0 ) {
								continue;
							}

							$meta_key = 'postmeta_meta_key_'.$result['meta_key'].'_meta_value_'.$result['meta_key'];
							$data_model['items'][$key][$meta_key] = $result['meta_value'];
						}
					}
				}


				foreach ($data_model['items'] as $key => $data) {

					if (empty($data['posts_id'])) continue;
					$post_ids[] = $data['posts_id'];

					$data_model['items'][$key]['loaded'] = true;
					$data_model['items'][$key]['expanded'] = true;

					if( empty($data['posts_post_parent']) && !empty($post_parents[$data['posts_id']]) ) {
						$data['posts_post_parent'] = $post_parents[$data['posts_id']];
					}

					if( ! empty( $data_model['items'][$key]['postmeta_meta_key__regular_price_meta_value__regular_price'] ) ) {
						$decimal_separator = ( ! empty( $this->req_params['cmd'] ) && 'get_export_csv' == $this->req_params['cmd'] ) ? apply_filters( 'sm_decimal_separator_for_export', wc_get_price_decimal_separator(), array( 'col' => 'postmeta_meta_key__regular_price', 'data' => $data_model['items'][$key] ) ) : wc_get_price_decimal_separator();
						$data_model['items'][$key]['postmeta_meta_key__regular_price_meta_value__regular_price'] = number_format( (float)$data['postmeta_meta_key__regular_price_meta_value__regular_price'], wc_get_price_decimals(), $decimal_separator, '' );
					}

					if( ! empty( $data_model['items'][$key]['postmeta_meta_key__sale_price_meta_value__sale_price'] ) ) {
						$decimal_separator = ( ! empty( $this->req_params['cmd'] ) && 'get_export_csv' == $this->req_params['cmd'] ) ? apply_filters( 'sm_decimal_separator_for_export', wc_get_price_decimal_separator(), array( 'col' => 'postmeta_meta_key__sale_price', 'data' => $data_model['items'][$key] ) ) : wc_get_price_decimal_separator();
						$data_model['items'][$key]['postmeta_meta_key__sale_price_meta_value__sale_price'] = number_format( (float)$data['postmeta_meta_key__sale_price_meta_value__sale_price'], wc_get_price_decimals(), $decimal_separator, '' );
					}

					if ( !empty($data['posts_post_parent']) ) {

						$parent_key = sa_multidimesional_array_search($data['posts_post_parent'], 'posts_id', $data_model['items']);
						$parent_title  = '';

						// Code for the variation title on sorting
						// if ( $this->prod_sort === true ) {
							$parent_title = (!empty($data_model['items'][$parent_key]['posts_post_title'])) ? $data_model['items'][$parent_key]['posts_post_title'] : get_the_title($data['posts_post_parent']);
							$parent_title .= ( !empty($parent_title) ) ? ' - ' : '';
						// }

						$data_model['items'][$key]['parent'] = $data['posts_post_parent'];
						$data_model['items'][$key]['isLeaf'] = true;
						$data_model['items'][$key]['level'] = 1;
						$data_model['items'][$key]['terms_product_type'] = 'Variation';

						if( !empty( $data_model['items'][$key]['custom_edit_link'] ) ) {
							$data_model['items'][$key]['custom_edit_link'] = '';
						}

						//Code for modifying the variation name
						$variation_title = '';

						foreach ($data as $key1 => $value) {
							$start_pos = strrpos($key1, '_meta_value_attribute_');

							if ( $start_pos !== false ){

								$attr_nm = substr($key1, $start_pos+22);

								$data_model['items'][$key][$key1] = (empty($data_model['items'][$key][$key1])) ? 'any' : $data_model['items'][$key][$key1];

								if ( !empty($attr_values[$attr_nm]) ) {

									$attr_lbl = (!empty($attr_values[$attr_nm]['lbl'])) ? $attr_values[$attr_nm]['lbl'] : $attr_nm;
									$attr_val = ( !empty($attr_val_by_slug[$attr_nm][$data_model['items'][$key][$key1]]) ) ? $attr_val_by_slug[$attr_nm][$data_model['items'][$key][$key1]] : $data_model['items'][$key][$key1];
									$variation_title .= $attr_lbl . ': ' . $attr_val;

								} else {
									$variation_title .= $attr_nm . ': ' . $data_model['items'][$key][$key1];
								}
								$variation_title .= ' | ';
							}
						}

						$variation_title = ( ! empty( $data['posts_post_title'] ) && empty( $variation_title ) ) ? $data['posts_post_title'] : ( $parent_title .''. substr( $variation_title, 0, strlen( $variation_title ) - 2 ) );

						if( ! empty( $variation_title ) ){
							$data_model['items'][$key]['posts_post_title'] = ( ( ! empty( $this->req_params['cmd'] ) && 'get_export_csv' == $this->req_params['cmd'] ) || true === $this->prod_sort ) ? $variation_title : '<div style="margin-left: 2px;color: #469BDD;" class="dashicons dashicons-minus"></div>'.' <div>'.$variation_title.'</div>';
						}


					} else if ( !empty($data['terms_product_type']) ) {
						if ( $data['terms_product_type'] == 'simple' ) {
							$data_model['items'][$key]['icon_show'] = false;
						}
						$data_model['items'][$key]['parent'] = 'null';
						$data_model['items'][$key]['isLeaf'] = false;
						$data_model['items'][$key]['level'] = 0;
					}

					if ( $this->prod_sort === true ) {
						$data_model['items'][$key]['icon_show'] = false;
						$data_model['items'][$key]['parent'] = 'null';
						$data_model['items'][$key]['isLeaf'] = false;
						$data_model['items'][$key]['level'] = 0;
					}

					if ( empty($data['posts_post_parent']) ) {
						$parent_ids[] = $data['posts_id'];
					}

					// if ( ! empty( $data['postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id'] ) ) {
					// 	$thumbnail_id = $data['postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id'];
					// 	$attachment = wp_get_attachment_image_src($thumbnail_id, 'full');
					// 	if ( is_array( $attachment ) && ! empty( $attachment[0] ) ) {
					// 		$thumbnail = $attachment[0];
					// 	} else {
					// 		$thumbnail = '';
					// 	}
					// 	$data_model['items'][$key]['postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id'] = $thumbnail;
					// } else {
					// 	$data_model['items'][$key]['postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id'] = '';

					// 	// $data_model['items'][$key]['postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id'] = '<div title="' . __( 'Set', 'smart-manager-for-wp-e-commerce' ) . '" width="20" height="20">&nbsp;</div>';
					// }

					if( !empty($product_shop_url_index) ) { //for product url
						$data_model['items'][$key]['custom_product_shop_url'] = get_permalink($data['posts_id']);
					}

					if ( empty( $data['postmeta_meta_key__product_addons_exclude_global_meta_value__product_addons_exclude_global'] ) ) {
						$data_model['items'][$key]['postmeta_meta_key__product_addons_exclude_global_meta_value__product_addons_exclude_global'] = 0;
					}

					if ( empty( $data['postmeta_meta_key__wc_mmax_prd_opt_enable_meta_value__wc_mmax_prd_opt_enable'] ) ) {
						$data_model['items'][$key]['postmeta_meta_key__wc_mmax_prd_opt_enable_meta_value__wc_mmax_prd_opt_enable'] = 0;
					}

					if (empty($data['postmeta_meta_key__product_attributes_meta_value__product_attributes'])) continue;
					$product_attributes_postmeta[$data['posts_id']] = json_decode( $data['postmeta_meta_key__product_attributes_meta_value__product_attributes'], true );

				}

				$data_model['items'] = array_values($data_model['items']);

				if( !empty($parent_ids) && ( $product_visibility_index != '' || $product_featured_index != '' ) ) {
					$terms_objects = wp_get_object_terms( $parent_ids, 'product_visibility', 'orderby=none&fields=all_with_object_id' );

					$product_visibility = array();

					if (!empty($terms_objects)) {
						foreach ($terms_objects as $terms_object) {

							$post_id = $terms_object->object_id;
							$slug = $terms_object->slug;

							if (!isset($product_visibility[$post_id])){
								$product_visibility[$post_id] = array();
							}

							if (!isset($product_visibility[$post_id][$slug])){
								$product_visibility[$post_id][$slug] = '';
							}

						}
					}

					foreach ($data_model['items'] as $key => $data) {
						if ( empty($data['posts_id']) || !empty($data['posts_post_parent']) ) continue;

						$visibility = 'visible';
						$featured = 'no';

						if( isset($product_visibility[$data['posts_id']]['exclude-from-search']) && isset($product_visibility[$data['posts_id']]['exclude-from-catalog']) ) {
							$visibility = 'hidden';
						} else if( isset($product_visibility[$data['posts_id']]['exclude-from-search']) ) {
							$visibility = 'catalog';
						} else if( isset($product_visibility[$data['posts_id']]['exclude-from-catalog']) ) {
							$visibility = 'search';
						}

						if( isset($product_visibility[$data['posts_id']]['featured']) ) {
							$featured = 'yes';
						}

						$data_model['items'][$key]['terms_product_visibility'] = $visibility;
						$data_model['items'][$key]['terms_product_visibility_featured'] = $featured;
					}

				}

				$terms_objects = wp_get_object_terms( $post_ids, $taxonomy_nm, 'orderby=none&fields=all_with_object_id' );
				$attributes_val = array();
				$temp_attribute_nm = "";

				if (!empty($terms_objects)) {
					foreach ($terms_objects as $terms_object) {

						$post_id = $terms_object->object_id;
						$taxonomy = $terms_object->taxonomy;
						$term_id = $terms_object->term_id;

						if (!isset($attributes_val[$post_id])){
							$attributes_val[$post_id] = array();
						}

						if (!isset($attributes_val[$post_id][$taxonomy])){
							$attributes_val[$post_id][$taxonomy] = array();
						}

			            $attributes_val[$post_id][$taxonomy][$term_id] = $terms_object->name;
					}
				}

				//Query to get the attribute name
				$query_attribute_label = "SELECT attribute_name, attribute_label
		                                FROM {$wpdb->prefix}woocommerce_attribute_taxonomies";
		        $results_attribute_label = $wpdb->get_results( $query_attribute_label, 'ARRAY_A' );
		        $attribute_label_count = $wpdb->num_rows;

		        $attributes_label = array();

		        if($attribute_label_count > 0) {
			        foreach ($results_attribute_label as $results_attribute_label1) {
			            $attributes_label['pa_' . $results_attribute_label1['attribute_name']] = array();
			            $attributes_label['pa_' . $results_attribute_label1['attribute_name']] = $results_attribute_label1['attribute_label'];
			        }
		        }

				// $query_attributes = $wpdb->prepare("SELECT post_id as id,
				// 											meta_value as product_attributes
				// 										FROM {$wpdb->prefix}postmeta
				// 										WHERE meta_key = '%s'
				// 											AND meta_value <> '%s'
				// 											AND post_id IN (".implode(',', array_filter($post_ids,'is_int')).")
				// 										GROUP BY id",'_product_attributes','a:0:{}');

				// $product_attributes = $wpdb->get_results($query_attributes, 'ARRAY_A');
				// $product_attributes_count = $wpdb->num_rows;

				if (!empty($product_attributes_postmeta)) {


					foreach ($product_attributes_postmeta as $post_id => $prod_attr) {

						if (empty($prod_attr)) continue;

                    	// $prod_attr = json_decode($product_attribute,true);
                    	$update_index = sa_multidimesional_array_search ($post_id, 'posts_id', $data_model['items']);
                    	$attributes_list = "";

	                    //cond added for handling blank data
	                    if (is_array($prod_attr) && !empty($prod_attr)) {

	                    	$attributes_list = "";

	                    	foreach ($prod_attr as &$prod_attr1) {
								if ( empty( $prod_attr1 ) || ! is_array( $prod_attr1 ) ) {
									continue;
								}
	                    		if( !empty($attributes_list) ) {
	                    			$attributes_list .= ", <br>";
	                    		}

	                    		if ( isset( $prod_attr1['is_taxonomy'] ) && $prod_attr1['is_taxonomy'] == 0 ) {
	                    			$attributes_list .= ( ( ! empty( $prod_attr1['name'] ) ? $prod_attr1['name'] : '-' ) . ": [" . ( ! empty( $prod_attr1['value'] ) ? trim( $prod_attr1['value'] ) : '-' ) ) ."]";
		                    	} else {
		                    		$attributes_val_current = ( ! empty( $attributes_val[$post_id] ) && ! empty( $attributes_val[$post_id][$prod_attr1['name']] ) ) ? $attributes_val[$post_id][ $prod_attr1['name'] ] : array();
									if ( ! empty( $attributes_label[$prod_attr1['name']] ) && ! empty( $attributes_val_current ) && is_array( $attributes_val_current ) ) {
										$attributes_list .= $attributes_label[$prod_attr1['name']] . ": [" . implode(" | ",$attributes_val_current) . "]";
									}
                                    $prod_attr1['value'] = $attributes_val_current;
		                    	}
	                    	}

	                    	$data_model['items'][$update_index]['custom_product_attributes'] = $attributes_list;
	                    	$data_model['items'][$update_index]['postmeta_meta_key__product_attributes_meta_value__product_attributes'] = json_encode($prod_attr);
	                    }
					}
				}
			}
			return $data_model;
		}

		//function for modifying edited data before updating
		public function products_inline_update_pre( $edited_data = array() ) {
			if ( empty( $edited_data ) ) return $edited_data;
			global $wpdb;
			$prod_title_ids = array();
			$prev_val = '';
			// For getting current task_id
			if ( true === array_key_exists( 'task_id', $edited_data ) ) {
				$this->task_id = intval( $edited_data['task_id'] );
				unset( $edited_data['task_id'] );
			}
			foreach ( $edited_data as $key => $edited_row ) {
				if ( empty( $key ) ) {
					continue;
				}
				// Code to handle setting of 'regular_price' & 'sale_price' in proper way
				if( ! empty( $edited_row['postmeta/meta_key=_regular_price/meta_value=_regular_price'] ) || ! empty( $edited_row['postmeta/meta_key=_sale_price/meta_value=_sale_price'] ) ) {
					if( !empty( $edited_row['postmeta/meta_key=_regular_price/meta_value=_regular_price'] ) ) {
						$edited_data[$key]['postmeta/meta_key=_regular_price/meta_value=_regular_price'] = str_replace( wc_get_price_decimal_separator(), '.', $edited_data[$key]['postmeta/meta_key=_regular_price/meta_value=_regular_price']);
					}

					if( !empty( $edited_row['postmeta/meta_key=_sale_price/meta_value=_sale_price'] ) ) {
						$edited_data[$key]['postmeta/meta_key=_sale_price/meta_value=_sale_price'] = str_replace( wc_get_price_decimal_separator(), '.', $edited_data[$key]['postmeta/meta_key=_sale_price/meta_value=_sale_price']);
					}

					$regular_price = ( isset( $edited_data[$key]['postmeta/meta_key=_regular_price/meta_value=_regular_price'] ) ) ? $edited_data[$key]['postmeta/meta_key=_regular_price/meta_value=_regular_price'] : get_post_meta( $key, '_regular_price', true );
					$sale_price = ( isset( $edited_data[$key]['postmeta/meta_key=_sale_price/meta_value=_sale_price'] ) ) ? $edited_data[$key]['postmeta/meta_key=_sale_price/meta_value=_sale_price'] : get_post_meta( $key, '_sale_price', true );

					if ( $sale_price >= $regular_price ) {
						// For fetching previous value
						if ( is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) ) {
							$prev_val = Smart_Manager_Task::get_previous_data( $key, 'postmeta', '_sale_price' );
						}
						if ( isset( $edited_data[$key]['postmeta/meta_key=_sale_price/meta_value=_sale_price'] ) ) {
							unset( $edited_data[$key]['postmeta/meta_key=_sale_price/meta_value=_sale_price'] );
						}
						$sale_price_update = update_post_meta( $key, '_sale_price', '' );
						if ( ( defined('SMPRO') && ( ! empty( SMPRO ) ) ) && ! is_wp_error( $sale_price_update ) && ( ! empty( $this->task_id ) ) && ( ! empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) ) && ( ! empty( $key ) ) ) {
			    				Smart_Manager_Base::$update_task_details_params[] = array(
			    					'task_id' => $this->task_id,
								    'action' => 'set_to',
								    'status' => 'completed',
								    'record_id' => $key,
								    'field' => 'postmeta/meta_key=_sale_price/meta_value=_sale_price',
								    'prev_val' => $prev_val,
								    'updated_val' => ''
							    );
						}
					}

				} elseif ( ( ! empty( $edited_data[$key]['postmeta/meta_key=_sale_price_dates_from/meta_value=_sale_price_dates_from'] ) ) && ( ! empty( $edited_row['postmeta/meta_key=_sale_price_dates_from/meta_value=_sale_price_dates_from'] ) ) ) {
					$edited_data[$key]['postmeta/meta_key=_sale_price_dates_from/meta_value=_sale_price_dates_from'] .= ' 00:00:00';
				} elseif ( ( ! empty( $edited_data[$key]['postmeta/meta_key=_sale_price_dates_to/meta_value=_sale_price_dates_to'] ) ) && ( ! empty( $edited_row['postmeta/meta_key=_sale_price_dates_to/meta_value=_sale_price_dates_to'] ) ) ) {
					$edited_data[$key]['postmeta/meta_key=_sale_price_dates_to/meta_value=_sale_price_dates_to'] .= ' 23:59:59';
				}

				if( false !== strpos($key, 'sm_temp_') ) {
					continue;
				}

				if( !empty( $edited_row['posts/post_title'] ) && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {
					// if( strpos($key, 'sm_temp_') === false ) {
						$prod_title_ids[] = $key;
					// }
				}

				if ( isset( $edited_row['postmeta/meta_key=_stock/meta_value=_stock'] ) ) { //For handling product inventory updates
					// For fetching previous value.
					if( ! empty( $key ) && is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) ) {
						$prev_val = Smart_Manager_Task::get_previous_data( $key, 'postmeta', '_stock' );
					}
					$stock_status_update = sa_update_stock_status( $key, '_stock', $edited_row['postmeta/meta_key=_stock/meta_value=_stock'] );
					// Code for updating stock and it's status.
					if ( ( ! empty( $stock_status_update ) ) && ( ! empty( $this->task_id ) ) && ( ! empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) ) && ( ! empty( $key ) ) ) {
						Smart_Manager_Base::$update_task_details_params[] = array(
							'task_id' => $this->task_id,
							'action' => 'set_to',
							'status' => 'completed',
							'record_id' => $key,
							'field' => 'postmeta/meta_key=_stock/meta_value=_stock',
							'prev_val' => $prev_val,
							'updated_val' => $edited_row['postmeta/meta_key=_stock/meta_value=_stock']
						);
					}
				}
				if ( ! isset( $edited_row['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'] ) ) {
 					continue;
				}
				$saved_product_attributes = get_post_meta( $key, '_product_attributes', true );
				$product_attributes = array();
				if( ! empty( $edited_row['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'] ) ){
					$product_attributes = json_decode($edited_row['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'],true);
				}
				if ( is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) && ( ! empty( $saved_product_attributes ) ) && ( is_array( $saved_product_attributes ) ) ) {
					$term_ids = $prev_vals = array();
					if( ( ! empty( $product_attributes ) ) && ( is_array( $product_attributes ) ) ) {
						foreach ( $product_attributes as $attr => $attr_value ) {
							if ( ! empty( $attr_value['value'] ) && ( is_array( $attr_value['value'] ) ) ) {
								foreach ( $attr_value['value'] as $term_id => $term_value ) {
									$term_ids[ $attr ][] = $term_id;
								}
							}
						}
					}
					foreach ( $saved_product_attributes as $taxonomy_nm => $value ) {
						$attr_previous_vals = Smart_Manager_Base::$previous_vals[] = Smart_Manager_Task::get_previous_data( $key, 'terms', $taxonomy_nm );
						if ( ( is_wp_error( $attr_previous_vals ) ) || empty( $attr_previous_vals ) || ( ! is_array( $attr_previous_vals ) ) ) continue;
						foreach ( $attr_previous_vals as $prev_val ) {
							$attr_previous_vals['term_id'][ $prev_val ] = 'custom/product_attributes_add';
						}
						if ( empty( $attr_previous_vals['term_id'] ) ) continue;
						if ( isset( $term_ids[ $taxonomy_nm ] ) && is_array( $term_ids[ $taxonomy_nm ] ) ) {
							if ( count( $term_ids[ $taxonomy_nm ] ) > count( $attr_previous_vals['term_id'] ) ) {
								$prev_vals = array_diff( $term_ids[ $taxonomy_nm ], array_keys( $attr_previous_vals['term_id'] ) );
								if ( ( ! empty( $prev_vals ) ) && is_array( $prev_vals ) ) {
									foreach ( $prev_vals as $prev_val ) {
										$attr_previous_vals['term_id'][ $prev_val ] = 'custom/product_attributes_remove';
									}
								}
                            }elseif ( count( $term_ids[ $taxonomy_nm ] ) < count( $attr_previous_vals['term_id'] ) ) {
								$prev_vals = array_diff( array_keys( $attr_previous_vals['term_id'] ), $term_ids[ $taxonomy_nm ] );
								if ( ( ! empty( $prev_vals ) ) && is_array( $prev_vals ) ) {
									foreach ( $prev_vals as $prev_val ) {
										$attr_previous_vals['term_id'][ $prev_val ] = 'custom/product_attributes_add';
									}
								}
							}
						}
						foreach ( $attr_previous_vals['term_id'] as $term_id => $field_name ) {
							if ( ( defined('SMPRO') && ( ! empty( SMPRO ) ) ) && ( ! empty( $this->task_id ) ) && ( ! empty( $taxonomy_nm ) ) && ( ! empty( $key ) ) && ( ! empty( $field_name ) ) && ( ! empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) ) ) {
								Smart_Manager_Base::$update_task_details_params[] = array(
					            			'task_id' => $this->task_id,
									'action' => $taxonomy_nm,
									'status' => 'completed',
									'record_id' => $key,
									'field' => $field_name,
									'prev_val' => $term_id,
									'updated_val' => maybe_serialize( $term_ids )
								);
							}
						}
					}
				}
				if( ! empty( $saved_product_attributes ) ) {
					$removed_attributes = array_diff( array_keys( $saved_product_attributes ), array_keys( $product_attributes ) );
					if( ! empty( $removed_attributes ) ){
						array_walk(
							$removed_attributes,
							function( $taxonomy ) use( $key ) {
								wp_set_object_terms( $key, array(), $taxonomy );
							}
						);
					}
				}
				if (empty($product_attributes)) {
					continue;
				}
				foreach ($product_attributes as $attr => $attr_value) {
					if ($attr_value['is_taxonomy'] == 0) continue;
					$product_attributes[$attr]['value'] = '';
				}

				$product_attributes = sm_multidimensional_array_sort($product_attributes, 'position', SORT_ASC);

				$edited_data[$key]['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'] = json_encode($product_attributes);
			}

			if( !empty( $prod_title_ids ) && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {

		        $results = sa_get_current_variation_title( $prod_title_ids );

                if( count( $results ) > 0 ) {
                    foreach( $results as $result ) {
                        $this->product_old_title[ $result['id'] ] = $result['post_title'];
                    }
                }
			}

			return $edited_data;
		}

		//function for inline update of custom fields
		public function products_inline_update($edited_data, $params) {
			global $current_user, $wpdb;
			if(empty($edited_data)) return;

			$attr_values = array();
			// $current_store_model = get_transient( 'sa_sm_'.$this->dashboard_key );
			// if( ! empty( $current_store_model ) && !is_array( $current_store_model ) ) {
			// 	$current_store_model = json_decode( $current_store_model, true );
			// }
			$col_model = (!empty($params['col_model'])) ? $params['col_model'] : array(); //fetching col_model from $params as transient gets deleted in case of insert meta

			$product_visibility_index = sa_multidimesional_array_search('terms_product_visibility', 'data', $col_model);
			$product_featured_index = sa_multidimesional_array_search('terms_product_visibility_featured', 'data', $col_model);

			if (!empty($col_model)) {

				foreach ($col_model as $column) {
					if (empty($column['src'])) continue;

					$src_exploded = explode("/",$column['src']);

					if (!empty($src_exploded) && $src_exploded[1] == 'product_attributes') {
						$col_values = $column['values'];
						if (!empty($col_values)) {
							foreach ($col_values as $key => $col_value) {
								if ( empty( $key ) || empty( $col_value ) || ( false === is_array( $col_value ) ) || ( false === array_key_exists( 'val', $col_value ) ) || ( false === array_key_exists( 'type', $col_value ) ) || empty( $col_value['type'] ) ) continue;
								$attribute_name = ( false !== strpos( $key, 'pa_' ) ) ? substr( $key, 3 ) : '';
								if ( ! empty( $attribute_name ) ) {
									$attr_values[ $attribute_name ] = array(
																		'taxonomy_nm' => $key,
																		'val' => $col_value['val'],
																		'type' => $col_value['type']
																	);
								}
							}
						}
					}
				}
			}

			// if( empty($attr_values) && empty($product_visibility_index) && empty($product_featured_index) ) {
			// 	return;
			// }

			$price_update_ids = array();
			$post_title_update_ids = array();
			$new_title_update_case = array();
			$sm_update_lookup_table_ids = array();
			$sm_update_attribute_lookup_table_ids = array();

			foreach( $edited_data as $pid => $edited_row ) {

				if( !empty( $edited_row['posts/post_title'] ) && ( !empty( Smart_Manager::$sm_is_woo30 ) && Smart_Manager::$sm_is_woo30 == 'true' ) ) {
					if( !empty( $this->product_old_title[ $pid ] ) && $this->product_old_title[ $pid ] != $edited_row['posts/post_title'] ) {
						$post_title_update_ids[] = $pid;
                        $new_title_update_case[] = 'WHEN post_parent='. $pid .' THEN REPLACE(post_title, \''. $this->product_old_title[ $pid ] .'\', \''. $edited_row['posts/post_title'] .'\')';
                    }
				}

				$id = (!empty($edited_row['posts/ID'])) ? $edited_row['posts/ID'] : $pid;

				if (empty($id)) continue;

				//Code to update the '_price' for the products
				if ( isset($edited_row['postmeta/meta_key=_regular_price/meta_value=_regular_price']) || isset($edited_row['postmeta/meta_key=_sale_price/meta_value=_sale_price']) || isset($edited_row['postmeta/meta_key=_sale_price_dates_from/meta_value=_sale_price_dates_from']) || isset($edited_row['postmeta/meta_key=_sale_price_dates_to/meta_value=_sale_price_dates_to']) ) {
					if ( false === strpos( $id, 'sm_temp_' ) ) {// Skip IDs that contain "sm_temp_" as these are temporary product IDs for creation.
						$price_update_ids[] = $id;
					}
				}

				$sm_update_lookup_table_meta_keys = array( 'postmeta/meta_key=_sku/meta_value=_sku',  'postmeta/meta_key=_regular_price/meta_value=_regular_price', 'postmeta/meta_key=_price/meta_value=_price', 'postmeta/meta_key=_sale_price/meta_value=_sale_price', 'postmeta/meta_key=_virtual/meta_value=_virtual', 'postmeta/meta_key=_downloadable/meta_value=_downloadable', 'postmeta/meta_key=_stock/meta_value=_stock', 'postmeta/meta_key=_manage_stock/meta_value=_manage_stock', 'postmeta/meta_key=_stock_status/meta_value=_stock_status', 'postmeta/meta_key=_wc_rating_count/meta_value=_wc_rating_count', 'postmeta/meta_key=_wc_average_rating/meta_value=_wc_average_rating', 'postmeta/meta_key=total_sales/meta_value=total_sales');

				// WC 3.6+ compat

				if ( ! empty( Smart_Manager::$sm_is_woo36 ) && Smart_Manager::$sm_is_woo36 == 'true' && ! empty( $sm_update_lookup_table_meta_keys ) && ( ! empty( $edited_row ) ) ) {
					if ( ! empty( array_intersect( array_keys( $edited_row ), $sm_update_lookup_table_meta_keys ) ) ) {
						if ( false === strpos( $id, 'sm_temp_' ) ) {// Skip IDs that contain "sm_temp_" as these are temporary product IDs for creation.
							$sm_update_lookup_table_ids[] = $id;
						}
					}
				}

				if( isset( $edited_row['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'] ) ) {
					if ( false === strpos( $id, 'sm_temp_' ) ) {// Skip IDs that contain "sm_temp_" as these are temporary product IDs for creation.
						$sm_update_attribute_lookup_table_ids[] = $id;
					}
				}


				// Code for 'WooCommerce Product Stock Alert' plugin compat -- triggering `save_post` action
				if( empty( $params['posts_fields'] ) && ( isset( $edited_row['postmeta/meta_key=_stock/meta_value=_stock'] ) || isset( $edited_row['postmeta/meta_key=_manage_stock/meta_value=_manage_stock'] ) ) ){
					sa_update_post( $id );
				}

				if ( !empty($product_visibility_index) || !empty($product_featured_index) ) {
					//set the visibility taxonomy
					$visibility = ( ! empty($edited_row['terms/product_visibility'] ) ) ? $edited_row['terms/product_visibility'] : '';
					// For fetching previous value
					if ( is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) ) {
						$prev_val = Smart_Manager_Task::get_previous_data( $id, 'terms', 'product_visibility' );
						$prev_val = ( ! empty( $prev_val ) && ( ! empty( $params ) ) ) ? sa_sm_format_prev_val( array(
								'prev_val' => $prev_val,
								'update_column' => 'product_visibility',
								'col_data_type' => $params,
								'updated_val' => $visibility
							) ) : $prev_val;
					}
					if ( ! empty( $visibility ) ) {
						$product_visibility_update = $this->set_product_visibility( $id, $visibility );
						if ( ( defined('SMPRO') && ( ! empty( SMPRO ) ) ) && ( ! empty( $this->task_id ) ) && ( ! empty( $id ) ) && ( ! empty( $product_visibility_update ) ) && ( ! empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) ) ) {
			            	Smart_Manager_Base::$update_task_details_params[] = array(
			            		'task_id' => $this->task_id,
				                'action' => 'set_to',
				                'status' => 'completed',
				                'record_id' => $id,
				                'field' => 'terms/product_visibility',
				                'prev_val' => $prev_val,
				                'updated_val' => $visibility
			                );
			        	}
                    }

					//set the featured taxonomy
					$featured = (!empty($edited_row['terms/product_visibility_featured'])) ? $edited_row['terms/product_visibility_featured'] : '';

                    if( !empty($featured) ) {
                       	$result = ( $featured == "Yes" || $featured == "yes" ) ? wp_set_object_terms($id, 'featured', 'product_visibility', true) : wp_remove_object_terms( $id, 'featured', 'product_visibility' );
                       	if ( ! empty( $result ) ) {
							if ( ( defined('SMPRO') && ( ! empty( SMPRO ) ) ) && ! empty( $this->task_id ) && ! empty( $id ) && ! empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) && ! empty( $featured ) ) {
				            	Smart_Manager_Base::$update_task_details_params[] = array(
				            		'task_id' => $this->task_id,
					                'action' => 'set_to',
					                'status' => 'completed',
					                'record_id' => $id,
					                'field' => 'terms/product_visibility_featured',
					                'prev_val' => ( "Yes" === $featured || "yes" === $featured ) ? 'no' : 'yes',
					                'updated_val' => $featured
				                );
				        	}
	                    }
                    }
				}
				if ( isset( $edited_row['postmeta/meta_key=_backorders/meta_value=_backorders'] ) ) {
					// For fetching previous value.
					if ( ! empty( $pid ) && is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) ) {
						$prev_val = Smart_Manager_Task::get_previous_data( $pid, 'postmeta', '_backorders' );
					}
					$stock_status_update = sa_update_stock_status( $pid, '_backorders', $edited_row['postmeta/meta_key=_backorders/meta_value=_backorders'] );
					// Code for updating stock and it's status.
					if ( ( ! empty( $stock_status_update ) ) && ( ! empty( $this->task_id ) ) && ( ! empty( property_exists( 'Smart_Manager_Base', 'update_task_details_params' ) ) ) && ( ! empty( $pid ) ) ) {
						Smart_Manager_Base::$update_task_details_params[] = array(
							'task_id' => $this->task_id,
							'action' => 'set_to',
							'status' => 'completed',
							'record_id' => $pid,
							'field' => 'postmeta/meta_key=_backorders/meta_value=_backorders',
							'prev_val' => $prev_val,
							'updated_val' => $edited_row['postmeta/meta_key=_backorders/meta_value=_backorders']
						);
					}
				}
				$attr_edited = (!empty($edited_row['custom/product_attributes'])) ? $edited_row['custom/product_attributes'] : '';
				$attr_edited = array_filter(explode(', <br>',$attr_edited));

				if (empty($attr_edited)) continue;

				foreach ($attr_edited as $attr) {
					$attr_data = explode(': ',$attr);

					if (empty($attr_data)) continue;

					$taxonomy_nm = $attr_data[0];
					$attr_editd_val = (substr($attr_data[1], 0, 1) == '[') ? substr($attr_data[1], 1) : $attr_data[1];
					$attr_editd_val = (substr($attr_editd_val, -1) == ']') ? substr($attr_editd_val, 0, -1) : $attr_editd_val;

					if (!empty($attr_values[$taxonomy_nm])) {
						//Code for type=select attributes

						$attr_val = $attr_values[$taxonomy_nm]['val'];
						$attr_type = $attr_values[$taxonomy_nm]['type'];

						$taxonomy_nm = $attr_values[$taxonomy_nm]['taxonomy_nm'];
						$attr_editd_val = array_filter(explode(" | ",$attr_editd_val));
						// if (empty($attr_editd_val)) continue;
						$term_ids = array();

						foreach ($attr_editd_val as $attr_editd) {

							$term_id = array_search( htmlspecialchars( $attr_editd ), $attr_val );

							if ($term_id === false && $attr_type == 'text') {
								$new_term = wp_insert_term($attr_editd, $taxonomy_nm);

								if ( !is_wp_error( $new_term ) ) {
									$term_id = (!empty($new_term['term_id'])) ? $new_term['term_id'] : '';
								}
							}
							$term_ids [] = $term_id;
						}
						if( ! empty( $id ) && is_callable( array( 'Smart_Manager_Task', 'get_previous_data' ) ) ) {
							$prev_val = Smart_Manager_Task::get_previous_data( $id, 'terms', $taxonomy_nm );
						}
						wp_set_object_terms( $id, $term_ids, $taxonomy_nm );
					}
				}
			}


			if( ! empty ( $sm_update_attribute_lookup_table_ids ) ) {
            	sa_update_product_attribute_lookup_table( $sm_update_attribute_lookup_table_ids );
        	}

			if( !empty( $price_update_ids ) ) {
				sa_update_price_meta($price_update_ids);
				//Code For updating the parent price of the product
				sa_variable_parent_sync_price($price_update_ids);
			}

			// Update the post title for variations if parent is updated
			if( !empty( $new_title_update_case ) && !empty( $post_title_update_ids ) ) {
				sa_sync_variation_title( $new_title_update_case, $post_title_update_ids );
            }

            /**
             * To update wc_product_meta_lookup for WC 3.6+
             * Since SM 4.2.3
             */
            if ( !empty( $sm_update_lookup_table_ids ) ) {
            	sm_update_product_lookup_table( $sm_update_lookup_table_ids );
            }

            // Delete the product transients
            if( function_exists('wc_delete_product_transients') ) {
            	$pids = array_keys( $edited_data );
            	if( !empty( $pids ) ) {
            		foreach( $pids as $id ) {
            			wc_delete_product_transients( $id );
            		}
            	}
            }
		}

		public function inline_update_product_featured_image() {

		    if ( ! empty( $_POST['update_field'] ) && 'postmeta_meta_key__thumbnail_id_meta_value__thumbnail_id' === $_POST['update_field'] ) {
		    	$product_id = ( ! empty( $_POST['product_id'] ) && is_numeric( $_POST['product_id'] ) ) ? $_POST['product_id'] : 0;
		    	$attachment_id = ( ! empty( $_POST['selected_attachment_id'] ) && is_numeric( $_POST['selected_attachment_id'] ) ) ? $_POST['selected_attachment_id'] : 0;
		    	if ( ! empty( $product_id ) && ! empty( $attachment_id ) ) {

		    		update_post_meta( $product_id, '_thumbnail_id', $attachment_id );

		    		if( isset( $this->req_params['pro'] ) && empty( $this->req_params['pro'] ) ) {
						$sm_inline_update_count = get_option( 'sm_inline_update_count', 0 );
						$sm_inline_update_count += 1;
						update_option( 'sm_inline_update_count', $sm_inline_update_count, 'no' );
						$resp = array( 'sm_inline_update_count' => $sm_inline_update_count,
										'msg' => esc_html__( 'Featured Image updated successfully', 'smart-manager-for-wp-e-commerce' ) );
						$msg = json_encode($resp);
					} else {
						$msg = esc_html__( 'Featured Image updated successfully', 'smart-manager-for-wp-e-commerce' );
					}

					echo $msg;
		    	} else {
		    		echo esc_html( 'failed' );
		    	}
		    }

		    exit;

		}

		//Function to modify the postmeta search column value for postmeta cols
		public function sm_search_format_query_postmeta_col_value( $search_value='', $search_params=array() ) {

			$search_col = ( !empty( $search_params['search_col'] ) ) ? $search_params['search_col'] : '';
			if( empty( $search_col ) || ! is_numeric( $search_value ) || ( ! empty( $search_col ) && ! in_array( $search_col, array( '_sale_price_dates_from', '_sale_price_dates_to' ) ) ) ){
				return $search_value;
			}

			return $search_value + ( ( '_sale_price_dates_to' === $search_col ) ? (DAY_IN_SECONDS - 1) : 0 );
		}

		/**
	     * Function to filter updated edited data in case of editing stock value using inline edit.
		 * @param  array $updated_edited_data array of updated edited data.
		 * @return array $updated_edited_data filtered updated edited data array.
		 */
		public function filter_updated_edited_data( $updated_edited_data = array() ) {
			if ( ( ! is_array( $updated_edited_data ) ) || ( defined('SMPRO') && ( ! empty( SMPRO ) ) ) ) {
				return $updated_edited_data;
			}
			foreach ( $updated_edited_data as $key => $values ) {
			    foreach ( $values as $col => $value ) {
			        if ( 'postmeta/meta_key=_stock/meta_value=_stock' !== $col ) {
			            unset( $updated_edited_data[ $key ][ $col ] );
			        }
			    }
			}
			if ( empty( $updated_edited_data ) ) {
				return $updated_edited_data;
			}
			$this->req_params['title'] = _x( 'Edited Stock', 'Title for task', 'smart-manager-for-wp-e-commerce' );
			return $updated_edited_data;
		}

		/**
	     * Function to filter the column model for export CSV.
		 * @param  array $col_model column model data.
		 * @param  array $params request params array.
		 * @return array $col_model array of updated column model data.
		 */
		public function col_model_for_export( $col_model = array(), $params = array() ) {
			if ( empty( $col_model ) || ! is_array( $col_model ) || empty( $params ) || ! is_array( $params ) || ( ! empty( $params['storewide_option'] ) && 'entire_store' === $params['storewide_option'] ) || ( ! empty( $params['columnsToBeExported'] ) && 'visible' === $params['columnsToBeExported'] ) ) {
				return $col_model;
			}
			$stock_cols = array( 'ID', '_sku', 'post_title', '_manage_stock', '_stock_status', '_backorders', '_stock', 'product_type', 'post_parent' );
			foreach ( $col_model as $key => &$column ) {
				if ( empty( $column['src'] ) ) continue;
				$src_exploded = explode( "/", $column['src'] );
				if ( empty( $src_exploded ) ) {
					$src = $column['src'];
				}
				$src = $src_exploded[1];
				$col_table = $src_exploded[0];
				if ( sizeof( $src_exploded ) > 2 ) {
					$col_table = $src_exploded[0];
					$cond = explode( "=", $src_exploded[1] );
					if ( 2 === sizeof( $cond ) ) {
						$src = $cond[1];
					}
				}
				if ( empty( $src ) ) {
					continue;
				}
				if ( false === in_array( $src, $stock_cols ) ) {
					unset( $col_model[ $key ] );
					continue;
				}
				$column['hidden'] = false;
			}
			return $col_model;
		}

		/**
	     * Function to handle custom posts conditions for advanced search.
		 * @param  string $posts_cond posts search condition.
		 * @param  array $search_params search params.
		 * @return string $posts_cond updated posts condition.
		 */
		public function sm_search_posts_cond( $posts_cond = '', $search_params = array() ) {
			if ( empty( $search_params ) || empty( $search_params['search_operator'] ) || false === strpos( $posts_cond, 'variations_enabled' ) ) {
				return $posts_cond;
			}
			global $wpdb;
			$post_status = 'publish';
			switch ( $search_params['search_operator'] ) {
				case 'is':
					$post_status = strpos( $posts_cond, 'yes' ) ? 'publish'	: 'private';
					break;
				case 'is not':
					$post_status = strpos( $posts_cond, 'yes' ) ? 'private'	: 'publish';
					break;
			}
			$posts_cond = $wpdb->prefix."posts.post_status = '{$post_status}' AND ".$wpdb->prefix."posts.post_parent > 0";
			$this->is_variations_enabled_advanced_search_condition = true;
			return $posts_cond;
		}

		/**
	     * Function to ignore columns for simple search.
		 * @param  array $ignored_cols array of ignored columns.
		 * @param  array $col_model column model array.
		 * @return array $ignored_cols updated array of ignored columns.
		 */
		public function sm_simple_search_ignored_posts_columns( $ignored_cols = array(), $col_model = array() ) {
			if ( empty( $col_model ) || ! is_array( $col_model ) || ! is_array( $ignored_cols ) ) {
				return $ignored_cols;
			}
			foreach ( $col_model as $col ) {
				if ( empty( $col['src'] ) || empty( $col['col_name'] ) || ( ! empty( $col['col_name'] ) && 'variations_enabled' !== $col['col_name'] ) ) {
					continue;
				}
				$src_exploded = explode( "/", $col['src'] );
				if ( empty( $src_exploded ) || ! is_array( $src_exploded ) || empty( $src_exploded[0] ) || 'posts' !== $src_exploded[0] ) {
					return $ignored_cols;
				}
				return array_merge( $ignored_cols, array( $col['col_name'] ) );
			}
		}

		/**
		 * Modifies the GROUP BY clause of a query.
		 *
		 * @param string $group_by The existing GROUP BY clause.
		 * @return string The modified GROUP BY clause.
		 */
		public function query_group_by ( $group_by = '' ) {
			return $this->get_group_by_clause_for_search( array( 'group_by' => $group_by ) );
		}

		/**
		 * Adds custom JOIN clauses to the SQL query for products.
		 *
		 * @param string $join The existing JOIN clause.
		 * @param array|string $sort_params The sorting parameters, if any.
		 * @return string The modified JOIN clause.
		 */
		public function query_join ( $join = '', $sort_params = '' ) {
			return $this->get_join_clause_for_search( array( 'join' => $join, 'sort_params' => $sort_params ) );
		}

	} //End of Class
}
