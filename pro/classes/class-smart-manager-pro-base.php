<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Base' ) ) {
	class Smart_Manager_Pro_Base extends Smart_Manager_Base {

		public $dashboard_key = '';
		public static $post_table_cols = array();
		protected static $sm_beta_background_updater_action;
		public static $dashboard = '';
		public $common_pro_base = null;
		protected static $_instance = null;

		/**
		 * Get the singleton instance of the class.
		 *
		 * @param string $dashboard_key dashboard key for constructor.
		 * @return self
		 */
		public static function instance( $dashboard_key ) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self( $dashboard_key );
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			$this->dashboard_key = $dashboard_key;
			parent::__construct($dashboard_key);
			self::$dashboard = $dashboard_key;
			$this->advanced_search_operators = array_merge( $this->advanced_search_operators, array(
				'startsWith' => 'like',
				'endsWith' => 'like',
				'anyOf' => 'like',
				'notStartsWith' => 'not like',
				'notEndsWith' => 'not like',
				'notAnyOf' => 'not like'
			 ) );

			add_filter( 'sa_sm_dashboard_model', array( &$this, 'pro_dashboard_model' ), 11, 2 );
			add_filter( 'sm_data_model', array( &$this, 'pro_data_model' ), 11, 2);
			add_filter( 'sm_inline_update_pre', array( &$this, 'pro_inline_update_pre' ), 11, 1);
			add_filter( 'sm_default_dashboard_model_postmeta_cols', array( &$this, 'pro_custom_postmeta_cols' ), 11, 1 );
			remove_action( 'transition_post_status', '_update_term_count_on_transition_post_status', 10, 3 ); //removed because taking time in bulk edit, when assign terms to post.
			//map inline terms update data
			add_filter( 'sm_process_inline_terms_update', array( &$this, 'map_inline_terms_update_data' ), 10, 1);
			add_filter( 'sm_inline_update_post_data', 'SA_Manager_Pro_Base::update_posts' );
			add_action( 'sm_update_posts_after_update_actions', 'SA_Manager_Pro_Base::update_posts_after_update_actions' );
			// Code for handling of `starts with/ends with` advanced search operators
			$advanced_search_filter_tables = array( 'posts', 'postmeta', 'terms' );
			switch(  $this->advanced_search_table_types ) {
				case ( ! empty( $this->advanced_search_table_types['flat'] ) && ! empty( $this->advanced_search_table_types['meta'] ) ):
					$advanced_search_filter_tables = array_merge( array_merge( array_keys( $this->advanced_search_table_types['flat'] ), array_keys( $this->advanced_search_table_types['meta'] ) ), array( 'terms' ) );
					break;
				case ( ! empty( $this->advanced_search_table_types['flat'] ) && empty( $this->advanced_search_table_types['meta'] ) ):
					$advanced_search_filter_tables = array_merge( array_keys( $this->advanced_search_table_types['flat'] ), array( 'terms' ) );
					break;
				case ( empty( $this->advanced_search_table_types['flat'] ) && ! empty( $this->advanced_search_table_types['meta'] ) ):
					$advanced_search_filter_tables = array_merge( array_keys( $this->advanced_search_table_types['meta'] ), array( 'terms' ) );
					break;
			}
			if( ! empty( $advanced_search_filter_tables ) && is_array( $advanced_search_filter_tables ) ){
				foreach( $advanced_search_filter_tables as $table ){
					add_filter( 'sm_search_format_query_' . $table . '_col_value', array( &$this, 'format_search_value' ), 11, 2 );
					add_filter( 'sm_search_'. $table .'_cond', array( __CLASS__, 'modify_search_cond' ), 11, 2 );
				}
			}
			add_filter(
				'sm_get_process_names_for_adding_tasks',
				function() {
					return array(
						'bulk_edit',
					);
				}
			);
			if ( 'yes' === Smart_Manager_Settings::get( 'delete_media_when_permanently_deleting_post_type_records' ) ) {
				add_action( 'before_delete_post', array( &$this, 'delete_attached_media' ), 11, 2 );
			}
			add_filter( 'sm_get_previous_data_for_batch_update', __CLASS__. '::get_previous_data_for_batch_update', 10, 2 );
			add_filter( 'sm_handle_post_processing_batch_update', __CLASS__. '::handle_post_processing_batch_update' );
			add_action( 'sm_update_params_post_processing_batch_update', array( &$this, 'update_params_post_processing_batch_update' ) );
			add_filter( 'sa_sm_get_entire_store_ids',array( &$this, 'get_entire_store_ids' ) );
			if (file_exists(SM_PLUGIN_DIR_PATH . '/pro/common-pro/classes/class-sa-manager-pro-base.php')) {
				include_once SM_PLUGIN_DIR_PATH . '/pro/common-pro/classes/class-sa-manager-pro-base.php';
				$this->common_pro_base = SA_Manager_Pro_Base::instance( $this->sa_manager_common_params );
			}
			add_filter(
				'sa_manager_batch_update_params',
				function ( $params = array() ) {
					return array_merge( $params, array(
						'SM_IS_WOO30' => $this->req_params['SM_IS_WOO30']
					) );
				}
			);
			add_filter( 'sm_update_params_before_processing_batch_update', __CLASS__ . '::update_task_params_before_batch_update' );
			add_action( 'sa_manager_update_meta_action_details', __CLASS__ . '::update_meta_action_details' );
			add_action( 'sa_manager_update_action_params', __CLASS__ . '::update_action_params' );
			add_action( 'sm_after_update_post_term', __CLASS__ . '::after_update_post_term' );
		}

		public function __call( $function_name, $arguments = array() ) {

			if( empty( $this->common_pro_base ) ) {
				return;
			}

			if ( ! is_callable( array( $this->common_pro_base, $function_name ) ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $this->common_pro_base, $function_name ), $arguments );
			} else {
				return call_user_func( array( $this->common_pro_base, $function_name ) );
			}
		}

		public function get_yoast_meta_robots_values() {
			return array( '-'            => __( 'Site-wide default', 'smart-manager-for-wp-e-commerce' ),
						'none'         => __( 'None', 'smart-manager-for-wp-e-commerce' ),
						'noimageindex' => __( 'No Image Index', 'smart-manager-for-wp-e-commerce' ),
						'noarchive'    => __( 'No Archive', 'smart-manager-for-wp-e-commerce' ),
						'nosnippet'    => __( 'No Snippet', 'smart-manager-for-wp-e-commerce' ) );
		}

		public function get_rankmath_robots_values() {
			return array( 'index'      => __( 'Index', 'smart-manager-for-wp-e-commerce' ),
						'noindex'      => __( 'No Index', 'smart-manager-for-wp-e-commerce' ),
						'nofollow'     => __( 'No Follow', 'smart-manager-for-wp-e-commerce' ),
						'noarchive'    => __( 'No Archive', 'smart-manager-for-wp-e-commerce' ),
						'noimageindex' => __( 'No Image Index', 'smart-manager-for-wp-e-commerce' ),
						'nosnippet'    => __( 'No Snippet', 'smart-manager-for-wp-e-commerce' ) );
		}

		public function get_rankmath_seo_score_class( $score ) {
			if ( $score > 80 ) {
				return 'great';
			}

			if ( $score > 51 && $score < 81 ) {
				return 'good';
			}

			return 'bad';
		}

		//Filter to add custom columns
		public function pro_custom_postmeta_cols( $postmeta_cols ) {

			$yoast_pm_cols = $rank_math_pm_cols = array();

			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}

			if ( ( in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true ) || array_key_exists( 'wordpress-seo/wp-seo.php', $active_plugins ) ) ) {
				$yoast_pm_cols = array('_yoast_wpseo_metakeywords','_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_meta-robots-noindex','_yoast_wpseo_primary_product_cat','_yoast_wpseo_focuskw_text_input','_yoast_wpseo_linkdex','_yoast_wpseo_focuskw','_yoast_wpseo_redirect','_yoast_wpseo_primary_category','_yoast_wpseo_content_score','_yoast_wpseo_meta-robots-nofollow','_yoast_wpseo_primary_kbe_taxonomy','_yoast_wpseo_opengraph-title','_yoast_wpseo_opengraph-description','_yoast_wpseo_primary_wpm-testimonial-category','_yoast_wpseo_twitter-title','_yoast_wpseo_twitter-description', '_yoast_wpseo_opengraph-image', '_yoast_wpseo_opengraph-image-id', '_yoast_wpseo_twitter-image', '_yoast_wpseo_twitter-image-id', '_yoast_wpseo_focuskeywords');
			}

			if( !empty( $yoast_pm_cols ) ) {
				foreach( $yoast_pm_cols as $meta_key ) {
					if( !isset( $postmeta_cols[ $meta_key ] ) ) {
						$postmeta_cols[ $meta_key ] = array( 'meta_key' => $meta_key, 'meta_value' => '' );
					}
				}
			}

			if ( ( in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true ) || array_key_exists( 'seo-by-rank-math/rank-math.php', $active_plugins ) ) ) {
				$rank_math_pm_cols = array('rank_math_title','rank_math_description','rank_math_focus_keyword','rank_math_canonical_url','rank_math_facebook_title','rank_math_facebook_description','rank_math_twitter_title','rank_math_twitter_description','rank_math_breadcrumb_title', 'rank_math_robots', 'rank_math_seo_score', 'rank_math_facebook_image', 'rank_math_twitter_image_id', 'rank_math_twitter_image', 'rank_math_twitter_image_id', 'rank_math_primary_product_cat');
			}

			if( !empty( $rank_math_pm_cols ) ) {
				foreach( $rank_math_pm_cols as $meta_key ) {
					if( !isset( $postmeta_cols[ $meta_key ] ) ) {
						$postmeta_cols[ $meta_key ] = array( 'meta_key' => $meta_key, 'meta_value' => '' );
					}
				}
			}

			return $postmeta_cols;
		}

		//Function to handle custom fields common in more than 1 post type
		public function pro_dashboard_model( $dashboard_model, $dashboard_model_saved ) {

			$colum_name_titles = array( 	'_yoast_wpseo_title' => __( 'Yoast SEO Title', 'smart-manager-for-wp-e-commerce' ),
					 						'_yoast_wpseo_metadesc' => __( 'Yoast Meta Description', 'smart-manager-for-wp-e-commerce' ),
					 						'_yoast_wpseo_metakeywords' => __( 'Yoast Meta Keywords', 'smart-manager-for-wp-e-commerce' ),
					 						'_yoast_wpseo_focuskw' => __( 'Yoast Focus Keyphrase', 'smart-manager-for-wp-e-commerce' ),
			 						);

			$html_columns = array( '_yoast_wpseo_content_score' => __( 'Yoast Readability Score', 'smart-manager-for-wp-e-commerce' ),
									'_yoast_wpseo_linkdex' => __( 'Yoast SEO Score', 'smart-manager-for-wp-e-commerce' ),
									'rank_math_seo_score' => __( 'Rank Math SEO Score', 'smart-manager-for-wp-e-commerce' ) );

			$product_cat_index = sa_multidimesional_array_search('terms_product_cat', 'data', $dashboard_model['columns']);

			$column_model = &$dashboard_model['columns'];

			foreach( $column_model as $key => &$column ) {
				if ( empty( $column['src'] ) ) continue;

				$src_exploded = explode("/",$column['src']);

				if (empty($src_exploded)) {
					$col_nm = $column['src'];
				}

				if ( sizeof($src_exploded) > 2 ) {
					$col_table = $src_exploded[0];
					$cond = explode("=",$src_exploded[1]);

					if (sizeof($cond) == 2) {
						$col_nm = $cond[1];
					}
				} else {
					$col_nm = $src_exploded[1];
					$col_table = $src_exploded[0];
				}

				switch( $col_nm ) {
					case '_yoast_wpseo_meta-robots-noindex':
						$column['key'] = $column['name'] = sprintf(
							/* translators: %1$s: dashboard title */
							__( 'Allow search engines to show this %1$s in search results?', 'smart-manager-for-wp-e-commerce' ), rtrim( $this->dashboard_title, 's' ) );
						$yoast_noindex = array( '0' => __( 'Default', 'smart-manager-for-wp-e-commerce'),
														'2' => __( 'Yes', 'smart-manager-for-wp-e-commerce' ),
														'1' => __( 'No', 'smart-manager-for-wp-e-commerce' ) );

						$column = $this->generate_dropdown_col_model( $column, $yoast_noindex );
						break;

					case '_yoast_wpseo_meta-robots-nofollow':
						$column['key'] = $column['name'] = sprintf(
							/* translators: %1$s: dashboard title */
							__( 'Should search engines follow links on this %1$s?', 'smart-manager-for-wp-e-commerce' ), rtrim( $this->dashboard_title, 's' ) );
						$yoast_nofollow = array('0' => __( 'Yes', 'smart-manager-for-wp-e-commerce' ),
												'1' => __( 'No', 'smart-manager-for-wp-e-commerce' ) );

						$column = $this->generate_dropdown_col_model( $column, $yoast_nofollow );
						break;
					case '_yoast_wpseo_meta-robots-adv':
						$column['key'] = $column['name'] = __( 'Meta robots advanced', 'smart-manager-for-wp-e-commerce' );
						$values = $this->get_yoast_meta_robots_values();
						$column = $this->generate_multilist_col_model( $column, $values );
						break;
					case 'rank_math_robots':
						$column['key'] = $column['name'] = __( 'Robots Meta', 'smart-manager-for-wp-e-commerce' );
						$values = $this->get_rankmath_robots_values();
						$column = $this->generate_multilist_col_model( $column, $values );
						break;
					case ($col_nm == '_yoast_wpseo_primary_product_cat' || $col_nm == 'rank_math_primary_product_cat'):

						$product_cat_values = array();

						$taxonomy_terms = get_terms('product_cat', array('hide_empty'=> 0,'orderby'=> 'id'));


						if( !empty( $taxonomy_terms ) ) {
							foreach ($taxonomy_terms as $term_obj) {
								$product_cat_values[$term_obj->term_id] = array();
								$product_cat_values[$term_obj->term_id]['term'] = $term_obj->name;
								$product_cat_values[$term_obj->term_id]['parent'] = $term_obj->parent;
							}
						}

						$values = $parent_cat_term_ids = array();
						foreach( $product_cat_values as $term_id => $obj ) {

							$values[ $term_id ] = $obj['term'];

							if( !empty( $obj['parent'] ) ) {
								$values[ $term_id ] = ( ! empty( $product_cat_values[ $obj['parent'] ] ) ) ? $product_cat_values[ $obj['parent'] ]['term']. ' > ' .$values[ $term_id ] : $values[ $term_id ];
								if( in_array( $obj['parent'], $parent_cat_term_ids ) === false ) {
									$parent_cat_term_ids[] = $obj['parent'];
								}
							}
						}

						//Code for unsetting the parent category ids
						if( !empty( $parent_cat_term_ids ) ) {
							foreach( $parent_cat_term_ids as $parent_id ) {
								if( isset( $values[ $parent_id ] ) ) {
									unset( $values[ $parent_id ] );
								}
							}
						}

						$column = $this->generate_dropdown_col_model( $column, $values );
						break;
					case ( !empty( $colum_name_titles[ $col_nm ] ) ):
						$column['key'] = $column['name'] = $colum_name_titles[ $col_nm ];
						break;
					case ( !empty( $html_columns[ $col_nm ] ) ):
						$column['key'] = $column['name'] = $html_columns[ $col_nm ];
						$column['type'] = 'text';
						$column['renderer']= 'html';
						$column['frozen'] = false;
						$column['sortable'] = false;
						$column['exportable'] = true;
						$column['searchable'] = false;
						$column['editable'] = false;
						$column['editor'] = false;
						$column['batch_editable'] = false;
						$column['hidden'] = true;
						$column['allow_showhide'] = true;
						$column['width'] = 200;
						break;
				}
			}

			if (!empty($dashboard_model_saved)) {
				$col_model_diff = sa_array_recursive_diff($dashboard_model_saved,$dashboard_model);
			}

			//clearing the transients before return
			if (!empty($col_model_diff)) {
				delete_transient( 'sa_sm_'.$this->dashboard_key );
			}

			return $dashboard_model;
		}

		public function pro_data_model ($data_model, $data_col_params) {

			if( !class_exists('WPSEO_Rank') && file_exists( WP_PLUGIN_DIR. '/wordpress-seo/inc/class-wpseo-rank.php' ) ) {
				include_once WP_PLUGIN_DIR. '/wordpress-seo/inc/class-wpseo-rank.php';
			}

			if( empty( $data_model['items'] ) ) {
				return $data_model;
			}

			foreach ($data_model['items'] as $key => $data) {
				if (empty($data['posts_id'])) continue;

				//Code for handling data for Yoast Readability Score
				if( !empty( $data['postmeta_meta_key__yoast_wpseo_content_score_meta_value__yoast_wpseo_content_score'] ) && is_callable( array( 'WPSEO_Rank', 'from_numeric_score' ) ) ) {

					$rank  = WPSEO_Rank::from_numeric_score( (int)$data['postmeta_meta_key__yoast_wpseo_content_score_meta_value__yoast_wpseo_content_score'] );
					$title = $rank->get_label();
					$data_model['items'][$key]['postmeta_meta_key__yoast_wpseo_content_score_meta_value__yoast_wpseo_content_score'] = '<div aria-hidden="true" title="' . esc_attr( $title ) . '" class="wpseo-score-icon ' . esc_attr( $rank->get_css_class() ) . '"></div><span class="screen-reader-text wpseo-score-text">' . $title . '</span>';
				}

				//Code for handling data for Yoast SEO Score
				if( !empty( $data['postmeta_meta_key__yoast_wpseo_linkdex_meta_value__yoast_wpseo_linkdex'] ) && is_callable( array( 'WPSEO_Rank', 'from_numeric_score' ) ) ) {

					$rank  = WPSEO_Rank::from_numeric_score( (int)$data['postmeta_meta_key__yoast_wpseo_linkdex_meta_value__yoast_wpseo_linkdex'] );
					$title = $rank->get_label();
					$data_model['items'][$key]['postmeta_meta_key__yoast_wpseo_linkdex_meta_value__yoast_wpseo_linkdex'] = '<div aria-hidden="true" title="' . esc_attr( $title ) . '" class="wpseo-score-icon ' . esc_attr( $rank->get_css_class() ) . '"></div><span class="screen-reader-text wpseo-score-text">' . $title . '</span>';
				}

				//Code for handling Yoast Meta Robots
				if( isset( $data['postmeta_meta_key__yoast_wpseo_meta-robots-adv_meta_value__yoast_wpseo_meta-robots-adv'] ) ) {
					$actual_values = $this->get_yoast_meta_robots_values();
					if( !empty( $data['postmeta_meta_key__yoast_wpseo_meta-robots-adv_meta_value__yoast_wpseo_meta-robots-adv'] ) ) {

						$current_values = explode( ',', $data['postmeta_meta_key__yoast_wpseo_meta-robots-adv_meta_value__yoast_wpseo_meta-robots-adv'] );

						$formatted_value = array();

						foreach( $current_values as $value ) {

							if( !empty( $actual_values[ $value ] ) ) {
								$formatted_value[] = $actual_values[ $value ];
							}
						}

						$data_model['items'][$key]['postmeta_meta_key__yoast_wpseo_meta-robots-adv_meta_value__yoast_wpseo_meta-robots-adv'] = implode(', <br>', $formatted_value);
					} else {
						$data_model['items'][$key]['postmeta_meta_key__yoast_wpseo_meta-robots-adv_meta_value__yoast_wpseo_meta-robots-adv'] = $actual_values['-'];
					}
				}

				//Code for handling Yoast Meta Robots
				if( isset( $data['postmeta_meta_key_rank_math_robots_meta_value_rank_math_robots'] ) ) {
					$actual_values = $this->get_rankmath_robots_values();
					if( !empty( $data['postmeta_meta_key_rank_math_robots_meta_value_rank_math_robots'] ) ) {

						$current_values = maybe_unserialize( $data['postmeta_meta_key_rank_math_robots_meta_value_rank_math_robots'] );

						$formatted_value = array();

						foreach( $current_values as $value ) {

							if( !empty( $actual_values[ $value ] ) ) {
								$formatted_value[] = $actual_values[ $value ];
							}
						}

						$data_model['items'][$key]['postmeta_meta_key_rank_math_robots_meta_value_rank_math_robots'] = implode(', <br>', $formatted_value);
					} else {
						$data_model['items'][$key]['postmeta_meta_key_rank_math_robots_meta_value_rank_math_robots'] = $actual_values['index'];
					}
				}

				//Code for handling data for Rank Math SEO Score
				if( isset( $data['postmeta_meta_key_rank_math_seo_score_meta_value_rank_math_seo_score'] ) ) {

					$score = ( !empty( $data['postmeta_meta_key_rank_math_seo_score_meta_value_rank_math_seo_score'] ) ) ? $data['postmeta_meta_key_rank_math_seo_score_meta_value_rank_math_seo_score'] : 0;
					$class     = $this->get_rankmath_seo_score_class( $score );
					$score = $score . ' / 100';

					$data_model['items'][$key]['postmeta_meta_key_rank_math_seo_score_meta_value_rank_math_seo_score'] = '<span class="rank-math-seo-score '.$class.'">
						<strong>'.$score.'</strong></span>';
				}
			}

			return $data_model;
		}

		public function pro_inline_update_pre( $edited_data ) {
			if (empty($edited_data)) return $edited_data;

			foreach ($edited_data as $id => $edited_row) {

				if( empty( $id ) ) {
					continue;
				}

				//Code for handling Yoast SEO meta robots editing
				if( !empty( $edited_row['postmeta/meta_key=_yoast_wpseo_meta-robots-adv/meta_value=_yoast_wpseo_meta-robots-adv'] ) ) {
					$actual_values = $this->get_yoast_meta_robots_values();
					$current_values = explode( ', <br>', $edited_row['postmeta/meta_key=_yoast_wpseo_meta-robots-adv/meta_value=_yoast_wpseo_meta-robots-adv'] );

					$formatted_value = array();

					foreach( $current_values as $value ) {

						$key = array_search( $value, $actual_values );

						if( $key !== false ) {
							$formatted_value[] = $key;
						}
					}

					$edited_data[$id]['postmeta/meta_key=_yoast_wpseo_meta-robots-adv/meta_value=_yoast_wpseo_meta-robots-adv'] = implode(',', $formatted_value);
				}

				// Code for handling Rank Math robots editing
				if( !empty( $edited_row['postmeta/meta_key=rank_math_robots/meta_value=rank_math_robots'] ) ) {
					$actual_values = $this->get_yoast_meta_robots_values();
					$current_values = explode( ', <br>', $edited_row['postmeta/meta_key=rank_math_robots/meta_value=rank_math_robots'] );
					$formatted_value = array();

					foreach( $current_values as $value ) {

						$key = array_search( $value, $actual_values );

						if( $key !== false ) {
							$formatted_value[] = $key;
						}
					}

					$edited_data[$id]['postmeta/meta_key=rank_math_robots/meta_value=rank_math_robots'] = $formatted_value;
				}

			}

			return $edited_data;
		}

		public function generate_multilist_col_model( $colObj, $values = array() ) {

			$colObj ['values'] = array();

			foreach( $values as $key => $value ) {
				$colObj ['values'][$key] = array( 'term' => $value, 'parent' => 0 );
			}

			//code for handling values for advanced search
			$colObj['search_values'] = array();
			foreach( $values as $key => $value ) {
				$colObj['search_values'][] = array( 'key' => $key, 'value' => $value );
			}

			$colObj ['type'] = $colObj ['editor'] = 'sm.multilist';
			$colObj ['strict'] 			= true;
			$colObj ['allowInvalid'] 	= false;
			$colObj ['editable']		= false;

			return $colObj;
		}

		public function generate_dropdown_col_model( $colObj, $dropdownValues = array() ) {

			$dropdownKeys = ( !empty( $dropdownValues ) ) ? array_keys( $dropdownValues ) : array();
			$colObj['defaultValue'] = ( !empty( $dropdownKeys[0] ) ) ? $dropdownKeys[0] : '';
			$colObj['save_state'] = true;

			$colObj['values'] = $dropdownValues;
			$colObj['selectOptions'] = $dropdownValues; //for inline editing

			$colObj['search_values'] = array();
			foreach( $dropdownValues as $key => $value) {
				$colObj['search_values'][] = array('key' => $key, 'value' => $value);
			}

			$colObj['type'] = 'dropdown';
			$colObj['strict'] = true;
			$colObj['allowInvalid'] = false;
			$colObj['editor'] = 'select';
			$colObj['renderer'] = 'selectValueRenderer';

			return $colObj;
		}

		//function to handle serialized values for copy from field operator
		public static function handle_serialized_data( $args = array() ) {

			if( empty( $args['date_type'] ) || empty( $args['new_value'] ) ) {
				return '';
			}

			switch( true ) {
				case( 'sm.serialized' === $args['date_type'] ):
					return maybe_unserialize( $args['new_value'] );
				case( 'sm.serialized' !== $args['date_type'] && 'sm.serialized' === $args['copy_field_data_type'] ):
					return maybe_serialize( $args['new_value'] );
				default:
					return $args['new_value'];
			}
		}

		//Function to generate the data for print_invoice
		public function get_print_invoice() {

			global $smart_manager_beta;

			ini_set('memory_limit','512M');
			set_time_limit(0);

			$purchase_id_arr = ( ! empty( $this->req_params['selected_ids'] ) ) ? json_decode( stripslashes( $this->req_params['selected_ids'] ), true ) : array();
			if ( ( ! empty( $this->req_params['storewide_option'] ) ) && ( 'entire_store' === $this->req_params['storewide_option'] ) && ( ! empty( $this->req_params['active_module'] ) ) ) { //code for fetching all the ids
				$purchase_id_arr = $this->get_entire_store_ids();
			}

			$sm_text_domain = 'smart-manager-for-wp-e-commerce';
			$sm_is_woo30 = ( ! empty( Smart_Manager::$sm_is_woo30 ) && 'true' === Smart_Manager::$sm_is_woo30 ) ? true : false;
			$sm_is_woo44 = ( ! empty( Smart_Manager::$sm_is_woo44 ) && 'true' === Smart_Manager::$sm_is_woo44 ) ? true : false;

			ob_start();
			if ( function_exists( 'wc_get_template' ) ) {
				$template = 'order-invoice.php';
				wc_get_template(
					$template,
					array( 'purchase_id_arr' => $purchase_id_arr,
							'sm_text_domain' => $sm_text_domain,
							'sm_is_woo30' => $sm_is_woo30,
							'sm_is_woo44' => $sm_is_woo44,
							'smart_manager_beta' => $smart_manager_beta
						),
					$this->get_template_base_dir( $template ),
					SM_PLUGIN_DIR_PATH .'/pro/templates/'
				);
			} else {
				include( apply_filters( 'sm_beta_pro_batch_order_invoice_template', SM_PRO_URL.'templates/order-invoice.php' ) );
			}
			echo ob_get_clean();
			exit;
		}

		//function to handle duplicate records functionality
		public function duplicate_records() {
			$get_selected_ids_and_entire_store_flag = apply_filters(
				'get_selected_ids_and_entire_store_flag', array() );
			$selected_ids = ( ! empty( $get_selected_ids_and_entire_store_flag['selected_ids'] ) ) ? $get_selected_ids_and_entire_store_flag['selected_ids'] : array();
			$is_entire_store = ( ! empty( $get_selected_ids_and_entire_store_flag['entire_store'] ) ) ? $get_selected_ids_and_entire_store_flag['entire_store'] : false;
			SA_Manager_Pro_Base::send_to_background_process( array( 'process_name' => _x( 'Duplicate Records', 'process name', 'smart-manager-for-wp-e-commerce' ),
														'process_key' => 'duplicate_records',
														'callback' => array( 'class_path' => $this->req_params['class_path'],
																			'func' => array( $this->req_params['class_nm'], 'process_duplicate_record' ) ),
														'selected_ids' => $selected_ids,
														'entire_task' => $this->entire_task,
														'storewide_option' => $this->req_params['storewide_option'],'active_module' => $this->req_params['active_module'],
														'entire_store' => $is_entire_store,
														'dashboard_key' => $this->dashboard_key,
														'dashboard_title' => $this->dashboard_title,
														'class_path' => $this->req_params['class_path'],
														'class_nm' => $this->req_params['class_nm'],
														'backgroundProcessRunningMessage' => $this->req_params['backgroundProcessRunningMessage'],
														'SM_IS_WOO30' => $this->req_params['SM_IS_WOO30']
													)
											);
		}

		public static function get_duplicate_record_settings() {

			$defaults = array(
				'status' => 'same',
				'type' => 'same',
				'timestamp' => 'current',
				'title' => '('.__('Copy', SM_TEXT_DOMAIN).')',
				'slug' => 'copy',
				'time_offset' => false,
				'time_offset_days' => 0,
				'time_offset_hours' => 0,
				'time_offset_minutes' => 0,
				'time_offset_seconds' => 0,
				'time_offset_direction' => 'newer'
			);

			$settings = apply_filters( 'sm_beta_duplicate_records_settings', $defaults );

			return $settings;
		}


		//function to process duplicate records logic
		public static function process_duplicate_record( $params ) {
			$original_id = ( !empty( $params['id'] ) ) ? $params['id'] : '';

			do_action('sm_beta_pre_process_duplicate_records', $original_id );

			//code for processing logic for duplicate records
			if( empty( $original_id ) ) {
				return false;
			}

			global $wpdb;

			// Get the post as an array
			$duplicate = get_post( $original_id, 'ARRAY_A' );

			$settings = self::get_duplicate_record_settings();

			// Modify title
			$appended = ( $settings['title'] != '' ) ? ' '.$settings['title'] : '';
			$duplicate['post_title'] = $duplicate['post_title'].' '.$appended;
			$duplicate['post_name'] = sanitize_title($duplicate['post_name'].'-'.$settings['slug']);

			// Set the post status
			if( $settings['status'] != 'same' ) {
				$duplicate['post_status'] = $settings['status'];
			}

			// Set the post type
			if( $settings['type'] != 'same' ) {
				$duplicate['post_type'] = $settings['type'];
			}

			// Set the post date
			$timestamp = ( $settings['timestamp'] == 'duplicate' ) ? strtotime($duplicate['post_date']) : current_time('timestamp',0);
			$timestamp_gmt = ( $settings['timestamp'] == 'duplicate' ) ? strtotime($duplicate['post_date_gmt']) : current_time('timestamp',1);

			if( $settings['time_offset'] ) {
				$offset = intval($settings['time_offset_seconds']+$settings['time_offset_minutes']*60+$settings['time_offset_hours']*3600+$settings['time_offset_days']*86400);
				if( $settings['time_offset_direction'] == 'newer' ) {
					$timestamp = intval($timestamp+$offset);
					$timestamp_gmt = intval($timestamp_gmt+$offset);
				} else {
					$timestamp = intval($timestamp-$offset);
					$timestamp_gmt = intval($timestamp_gmt-$offset);
				}
			}
			$duplicate['post_date'] = date('Y-m-d H:i:s', $timestamp);
			$duplicate['post_date_gmt'] = date('Y-m-d H:i:s', $timestamp_gmt);
			$duplicate['post_modified'] = date('Y-m-d H:i:s', current_time('timestamp',0));
			$duplicate['post_modified_gmt'] = date('Y-m-d H:i:s', current_time('timestamp',1));

			// Remove some of the keys
			unset( $duplicate['ID'] );
			unset( $duplicate['guid'] );
			unset( $duplicate['comment_count'] );

			// Insert the post into the database
			$duplicate_id = wp_insert_post( $duplicate );

			// Duplicate all the taxonomies/terms
			$taxonomies = get_object_taxonomies( $duplicate['post_type'] );
			foreach( $taxonomies as $taxonomy ) {
				$terms = wp_get_post_terms( $original_id, $taxonomy, array('fields' => 'names') );
				wp_set_object_terms( $duplicate_id, $terms, $taxonomy );
			}

			// Duplicate all the custom fields
			$custom_fields = get_post_custom( $original_id );

			$postmeta_data = array();

			foreach ( $custom_fields as $key => $value ) {
			  if( is_array($value) && count($value) > 0 ) { //TODO: optimize
					foreach( $value as $i=>$v ) {
						$postmeta_data[] = '('.$duplicate_id.',\''.$key.'\',\''.$v.'\')';
					}
				}
			}

			if( !empty($postmeta_data) ) {

				$q = "INSERT INTO {$wpdb->prefix}postmeta(post_id, meta_key, meta_value) VALUES ". implode(",", $postmeta_data);
				$query = $wpdb->query("INSERT INTO {$wpdb->prefix}postmeta(post_id, meta_key, meta_value) VALUES ". implode(",", $postmeta_data));
			}

			do_action( 'sm_beta_post_process_duplicate_records', array( 'original_id' => $original_id, 'duplicate_id' => $duplicate_id, 'settings' => $settings, 'duplicate' => $duplicate ) );
			if( is_wp_error($duplicate_id) ) {
				if ( is_callable( 'sa_manager_log' ) ) {
					sa_manager_log( 'error', _x( 'Duplicate process failed', 'duplicate process', 'smart-manager-for-wp-e-commerce' ) );
				}
				return false;
			} else {
				return true;
			}

		}

		/**
		 * Function to handle deletion via background process
		 */
		public function delete_all() {
			$get_selected_ids_and_entire_store_flag = apply_filters(
				'get_selected_ids_and_entire_store_flag',
				array()
			);
			$selected_ids = ( ! empty( $get_selected_ids_and_entire_store_flag['selected_ids'] ) ) ? $get_selected_ids_and_entire_store_flag['selected_ids'] : array();
			$is_entire_store = ( ! empty( $get_selected_ids_and_entire_store_flag['entire_store'] ) ) ? $get_selected_ids_and_entire_store_flag['entire_store'] : false;
			$process_name = _x( 'Move to trash', 'process name', 'smart-manager-for-wp-e-commerce' );
			$process_key = 'move_to_trash';
			$callback_func = 'sm_process_move_to_trash_records';
			if ( ! empty( $this->req_params['deletePermanently'] ) ) {
				$process_name = _x( 'Delete All Records', 'process name', 'smart-manager-for-wp-e-commerce' );
				$process_key = 'delete_all_records';
				$callback_func = 'sm_delete_records_permanently';
			}
			$default_delete_process = apply_filters( 'sm_pro_default_process_delete_records', true );
			if ( empty( $default_delete_process ) ) {
				$process_name = _x( 'Delete '. $this->dashboard_title . ' records', 'process name', 'smart-manager-for-wp-e-commerce' );
				$process_key = 'delete_non_post_type_records';
			}
			$callback_func = ( ! empty( $default_delete_process ) ) ? $callback_func : 'sm_process_delete_non_posts_records';
			SA_Manager_Pro_Base::send_to_background_process( array( 'process_name' => $process_name,
													'process_key' => $process_key,
														'callback' => array( 'class_path' => $this->req_params['class_path'],
																			'func' => array( $this->req_params['class_nm'], $callback_func ) ),
														'callback_params' => array ( 'delete_permanently' => $this->req_params['deletePermanently'] ),
														'selected_ids' => $selected_ids,
														'entire_task' => $this->entire_task,
														'storewide_option' => $this->req_params['storewide_option'],'active_module' => $this->req_params['active_module'],
														'entire_store' => $is_entire_store,
														'dashboard_key' => $this->dashboard_key,
														'dashboard_title' => $this->dashboard_title,
														'class_path' => $this->req_params['class_path'],
														'class_nm' => $this->req_params['class_nm'],
														'backgroundProcessRunningMessage' => $this->req_params['backgroundProcessRunningMessage'],
														'SM_IS_WOO30' => $this->req_params['SM_IS_WOO30'],
														'default_delete_process' => $default_delete_process
													)
											);
		}

		/**
		 * Function to handle move to trash functionality
		 *
		 * @param  array $params Required params array.
		 * @return WP_Post|false|null Post data on success, false or null on failure.
		 */
		public static function sm_process_move_to_trash_records( $args = array() ) {
			if ( empty( $args['selected_ids'] ) || ( ! is_array( $args['selected_ids'] ) ) ) {
				return;
			}
			global $wpdb;
			$force_delete = false; // Setting this to false since `sm_process_move_to_trash_records()` trash the records.
			// Sanitize and prepare the selected post IDs
			$selected_post_ids = array_map( 'intval', $args['selected_ids'] );
			// Prepare a placeholder string for the post IDs
			$post_id_placeholders = implode( ',', array_fill( 0, count( $selected_post_ids ), '%d' ) );
			// Delete posts if trash is disabled.
			if ( ! EMPTY_TRASH_DAYS ) {
				return self::sm_delete_records_permanently( $args );
			}
			// Fetch posts and check status.
			$post_results = $wpdb->get_results(
			   	$wpdb->prepare(
					"SELECT ID, post_status FROM {$wpdb->prefix}posts WHERE ID IN ( $post_id_placeholders )", $selected_post_ids
				),
				'ARRAY_A'
			);
			if ( ( empty( $post_results ) || ( is_wp_error( $post_results ) ) ) && is_callable( 'sa_manager_log' ) || ( ! is_array( $post_results ) ) ) {
				sa_manager_log( 'error', _x( 'Move to trash failed', 'move to trash process', 'smart-manager-for-wp-e-commerce' ) );
				return false;
			}
			if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Post_Data' ) ) {
				remove_action( 'wp_trash_post', array( 'WC_Post_Data', 'trash_post' ) );
			}
			// Loop through results to build lists of IDs
			$ids_to_trash = array();
			$previous_statuses = array();
			foreach ( $post_results as $post_result ) {
				if ( 'trash' === $post_result['post_status'] ) {
					continue;
				}
				$ids_to_trash[] = $post_result['ID'];
				$previous_statuses[$post_result['ID']] = $post_result['post_status'];
				// Filters whether a post trashing should take place.
				$check = apply_filters( 'pre_trash_post', null, $post_result, $post_result['post_status'] );
				if ( null !== $check ) {
					return $check;
				}
				do_action( 'wp_trash_post', $post_result['ID'], $post_result['post_status'] );
			}
			if ( ( ! isset( $args['move_to_trash_pre_action'] ) ) && empty( $args['move_to_trash_pre_action'] ) ) { // Shouldn't trigger below in case of call to this function from products_pre_process_move_to_trash_records() for varitions.
				do_action( 'sm_pro_pre_process_move_to_trash_records', array(
					'selected_post_ids' => $selected_post_ids,
					'post_id_placeholders' => $post_id_placeholders
				) );
			}
			if ( empty( $ids_to_trash ) || ( ! is_array( $ids_to_trash ) ) || ( empty( $previous_statuses ) || ( ! is_array( $previous_statuses ) ) ) ) {
				return false;
			}
			$ids_to_trash_placeholder = implode( ', ', array_fill( 0, count( $ids_to_trash ), '%d' ) );
			// Insert metadata for previous status and trash time.
			$values = array();
			foreach ( $ids_to_trash as $id ) {
				if ( empty( $previous_statuses[ $id ] ) ) {
					continue;
				}
				$previous_status = $previous_statuses[ $id ];
				$values[] = $wpdb->prepare("(%d, '_wp_trash_meta_status', %s), (%d, '_wp_trash_meta_time', %d)", $id, $previous_status, $id, time());
			}
			if ( ! empty( $values ) && ( is_array( $values ) ) ) {
				$wpdb->query(
					"INSERT INTO {$wpdb->prefix}postmeta (post_id, meta_key, meta_value) VALUES " . implode( ', ', $values )
				);
			}
			// Update status to "trash".
			$wpdb->query(
			   	$wpdb->prepare(
					"UPDATE {$wpdb->prefix}posts SET post_status = 'trash' WHERE ID IN ( $ids_to_trash_placeholder )", $ids_to_trash
				)
			);
			// Delete comments related to these posts.
			$wpdb->query(
			   	$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}comments WHERE comment_post_ID IN ( $post_id_placeholders )", $selected_post_ids
				)
			);
			foreach ( $post_results as $post_result ) {
				if ( 'trash' === $post_result['post_status'] ) {
					continue;
				}
				do_action( 'trashed_post', $post_result['ID'], $post_result['post_status'] );
			}
			return true;
		}

		/**
		 * Function to get template base directory for Smart Manager templates
		 *
		 * @param  string $template_name Template name.
		 * @return string $template_base_dir Base directory for Smart Manager templates.
		 */
		public function get_template_base_dir( $template_name = '' ) {

			$template_base_dir = '';
			$sm_dir_name = SM_PLUGIN_DIR . '/';
			$sm_base_dir    = 'woocommerce/' . $sm_dir_name;

			// First locate the template in woocommerce/smart-manager-for-wp-e-commerce folder of active theme.
			$template = locate_template(
				array(
					$sm_base_dir . $template_name,
				)
			);

			if ( ! empty( $template ) ) {
				$template_base_dir = $sm_base_dir;
			} else {
				// If not found then locate the template in smart-manager-for-wp-e-commerce folder of active theme.
				$template = locate_template(
					array(
						$sm_dir_name . $template_name,
					)
				);
				if ( ! empty( $template ) ) {
					$template_base_dir = $sm_dir_name;
				}
			}

			$template_base_dir = apply_filters( 'sm_template_base_dir', $template_base_dir, $template_name );

			return $template_base_dir;
		}

		/**
		 * Function to get modify the search cond for `any of/not any of` search operators
		 *
		 * @param  string $cond Search condition.
		 * @param  array $search_params Advanced search params.
		 *
		 * @return string $cond Updated search condition.
		 */
		public static function modify_search_cond( $cond = '', $search_params = array() ) {

			$operator = ( ! empty( $search_params['selected_search_operator'] ) ) ? $search_params['selected_search_operator'] : '';

			if( empty( $operator ) ){
				return $cond;
			}

			$val = ( ! empty( $search_params['search_value'] ) ) ? $search_params['search_value'] : '';
			$col = ( ! empty( $search_params['search_col'] ) ) ? $search_params['search_col'] : '';

			if( ! in_array( $operator, array( 'anyOf', 'notAnyOf' ) ) || empty( $val ) || empty( $col ) ){
				return $cond;
			}

			$val = explode( "|", $val );

			if( ! is_array( $val ) ){
				return $cond;
			}

			$addln_cond = '';
			if( ! empty( $search_params['is_meta_table'] ) ){
				$col = ( ! empty( $search_params['skip_placeholders'] ) ) ? "'". trim( $col ) . "'": ("'%". trim( $col ) . "%'");
				$addln_cond = $search_params['table_nm'] . ".meta_key LIKE " . $col . " AND ";
				$col = 'meta_value';
			}
			$col = $search_params['table_nm'] . "." . $col;
			$cond = array_reduce( $val, function( $carry, $item ) use( $col, $operator, $addln_cond, $search_params ) {
				$condition = " ( " . $addln_cond . " " . $col . " " .
							( ( 'notAnyOf' === $operator ) ? 'NOT ' : '' ) .
							"LIKE" .
							( ! empty( $search_params['skip_placeholders'] ) ?
								( " '%" . trim( $item ) . "%'" ) :
								" %s" ) .
							" ) ";
				$condition .= ( 'notAnyOf' === $operator ) ? 'AND' : 'OR';
				return $carry . $condition;

			}, '' );
			return ( 'notAnyOf' === $operator ) ? ( ( " AND" === substr( $cond, -4 ) ) ? "( " . substr( $cond, 0, -4 ) . " )" : $cond ) : ( ( " OR" === substr( $cond, -3 ) ) ? "( " . substr( $cond, 0, -3 ) . " )" : $cond );
		}

		/**
		 * Function to get format the search value for `starts with/ends with` search operators
		 *
		 * @param  string $search_value Searched value.
		 * @param  array $search_params Advanced search params.
		 *
		 * @return string $search_value Formatted searched value.
		 */
		public function format_search_value( $search_value = '', $search_params = array() ) {

			$operator = ( ! empty( $search_params['selected_search_operator'] ) ) ? $search_params['selected_search_operator'] : '';

			if( empty( $operator ) ){
				return $search_value;
			}

			switch( true ) {
				case( in_array( $operator, array( 'startsWith', 'notStartsWith' ) ) ):
					return $search_value. '%';
				case( in_array( $operator, array( 'endsWith', 'notEndsWith' ) ) ):
					return '%'. $search_value;
				default:
					return $search_value;
			}
		}

		/**
		 * Function update the edited column titles for the specific dashboard
		 *
		 * @param  array $args request params array.
		 * @return void
		 */
		public static function update_column_titles( $args = array() ){
			( ! empty( $args['edited_column_titles'] ) && ! empty( $args['state_option_name'] ) ) ? update_option( $args['state_option_name'] .'_columns', array_merge( get_option( $args['state_option_name'] .'_columns', array() ), $args['edited_column_titles'] ), 'no' ) : '';
		}

		/**
		 * Before deleting a post, do some cleanup like removing attached media.
		 *
		 * @param int $order_id Order ID.
		 * @param WP_Post $post Post data.
		 */
		public function delete_attached_media( $post_id = 0, $post = null ) {
			if ( empty( intval( $post_id ) ) ) {
				return;
			}
			global $wpdb;
			$attachments = get_children( array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
				'numberposts' => -1,
				'post_status' => 'any'
		  	) );
			if ( empty( $attachments ) || ! is_array( $attachments ) ) {
				return;
			}
			$attached_media_post_ids = array();
			$post_ids = array();
			foreach ( $attachments as $attachment ) {
				$attachment_id = $attachment->ID;
				if ( empty( intval( $attachment_id ) ) ) {
					continue;
				}
				$attached_media_post_ids = $wpdb->get_col(
											$wpdb->prepare( "SELECT DISTINCT post_id 
											FROM {$wpdb->prefix}postmeta WHERE post_id <> %d AND meta_key = %s AND meta_value = %s", $post_id, '_thumbnail_id', $attachment_id )
										);
				$attached_media_post_ids = apply_filters( 'sm_delete_attachment_get_matching_gallery_images_post_ids', $attached_media_post_ids, array(
					'post_id' => $post_id,
					'attachment_id' => $attachment_id
				) );
				$post_ids = $wpdb->get_col(
									$wpdb->prepare( "SELECT DISTINCT ID 
									FROM {$wpdb->prefix}posts WHERE ID <> %d AND post_content LIKE '%wp-image-" . $attachment_id . "%' OR post_excerpt LIKE '%wp-image-" . $attachment_id . "%' OR post_content LIKE '%wp:image {\"id\":$attachment_id%' OR post_excerpt LIKE '%wp:image {\"id\":$attachment_id%'", $post_id )
									);
			}
			if ( empty( ( is_array( $attached_media_post_ids ) && is_array( $post_ids ) ) && array_merge( $attached_media_post_ids, $post_ids ) ) ) {
				wp_delete_attachment( $attachment_id, true );
				wp_delete_post( $attachment_id, true );
			}
		}

		/**
		 * Deletes specified posts from the database.
		 *
		 * @param array $args An array of required params.
		 * @return array $deleted_posts deleted post ids on successful deletion, false on failure.
		 */
		public static function sm_delete_records_permanently( $args = array() ) {
			if ( empty( $args['selected_ids'] ) ) {
				return;
			}
			global $wpdb;
			$deleted_posts = array();
			$force_delete = true; // Setting this to true since `sm_delete_records()` deletes the records permanently.
			// Sanitize and prepare the selected post IDs
			$selected_post_ids = array_map( 'intval', $args['selected_ids'] );
			$num_ids = count( $selected_post_ids );
			if ( 0 === $num_ids ) {
				return; // No valid post IDs
			}
			// Prepare a placeholder string for the post IDs
			$post_id_placeholders = implode( ',', array_fill( 0, $num_ids, '%d' ) );
			$args = array(
				'selected_post_ids' => $selected_post_ids,
				'post_id_placeholders' => $post_id_placeholders
			);
			if ( class_exists( 'WooCommerce' ) ) {
				remove_action( 'delete_post', array( 'WC_Post_Data', 'delete_post' ) );
			}
			remove_action( 'delete_post', '_wp_delete_post_menu_item' );
			$posts = self::get_post_obj_from_ids( $selected_post_ids );
			if ( empty( $posts ) || ( ! is_array( $posts ) ) ) {
				return;
			}
			// Pre-deletion actions
			foreach ( $posts as $post ) {
				// Filters whether a post deletion should take place.
				$check = apply_filters( 'pre_delete_post', null, $post, $force_delete );
				if ( null !== $check ) {
					return $check;
				}
				// Actions before deletion
				do_action( 'before_delete_post', $post->ID, $post );
				do_action( "delete_post_{$post->post_type}", $post->ID, $post );
				do_action( 'delete_post', $post->ID, $post );
			}
			// Handling a menu item when its original object is deleted.
			self::sm_delete_post_menu_item( $args );
 			do_action( 'sm_pro_pre_process_delete_records', $args );
			// Delete misc postmeta
			$wpdb->query(
			   $wpdb->prepare(
				   "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ( $post_id_placeholders ) AND meta_key IN (%s, %s)",
				   array_merge( $selected_post_ids, array('_wp_trash_meta_status', '_wp_trash_meta_time') )
			   )
			);
			// Delete term relationships for the specified post IDs.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}term_relationships
					WHERE object_id IN ( $post_id_placeholders )",
					$selected_post_ids
				)
			);
			// Delete childrens.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}posts WHERE post_parent IN ( $post_id_placeholders ) AND post_type <> %s",
					array_merge( $selected_post_ids, array('attachment') )
				)
			);
			wp_defer_comment_counting( true );
			// Get all comment IDs for the given post IDs
			$comment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->prefix}comments WHERE comment_post_ID IN ( $post_id_placeholders )",
					$selected_post_ids
				)
			);
			// Check if there are comment IDs to delete
			if ( ! empty( $comment_ids ) ) {
				// Prepare a string of comma-separated comment IDs for the delete query.
				$comment_ids_placeholder = implode( ',', array_fill( 0, count( $comment_ids ), '%d' ) );
				// Delete comments from the comments table
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}comments WHERE comment_ID IN ( $comment_ids_placeholder )",
						$comment_ids
					)
				);
				// Optionally, delete comment meta if needed.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}commentmeta WHERE comment_id IN ( $comment_ids_placeholder )",
						$comment_ids
					)
				);
			}
			wp_defer_comment_counting( false );
			// Delete postmeta.
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ( $post_id_placeholders )", $selected_post_ids )
			);
			// Delete the posts
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$wpdb->prefix}posts WHERE ID IN ( $post_id_placeholders )", $selected_post_ids )
			);
			// Final deletion actions
			foreach ( $posts as $post ) {
				do_action( "deleted_post_{$post->post_type}", $post->ID, $post );
				do_action( 'deleted_post', $post->ID, $post );
				// Clean post cache
				clean_post_cache( $post );
				// Handle children cache if the post is hierarchical
				if ( is_post_type_hierarchical( $post->post_type ) ) {
					$children = get_children( array( 'post_parent' => $post->ID ) );
					foreach ( $children as $child ) {
						clean_post_cache( $child );
					}
				}
				wp_clear_scheduled_hook( 'publish_future_post', array( $post->ID ) );
				do_action( 'after_delete_post', $post->ID, $post );
				// Collect deleted post ID
				$deleted_posts[] = $post->ID;
			}
			if ( ( empty( $deleted_posts ) || ( is_wp_error( $deleted_posts ) ) ) && is_callable( 'sa_manager_log' ) ) {
				sa_manager_log( 'error', _x( 'Delete records permanently failed', 'delete permanently', 'smart-manager-for-wp-e-commerce' ) );
				return false;
			}
			return true;
		}

		/**
		 * Function for handling a menu item when its original object is deleted.
		 *
		 * @param array $args Array of selected post IDs and placeholders.
		 */
		public static function sm_delete_post_menu_item( $args = array() ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['selected_post_ids'] ) || empty( $args['post_id_placeholders'] ) ) {
				return;
			}
			$menu_item_ids = self::sm_get_associated_nav_menu_items( $args, 'post_type' );
			if ( empty( $menu_item_ids ) || ( ! is_array( $menu_item_ids ) ) ) {
				return;
			}
			self::sm_delete_records_permanently( array(
				'selected_ids' => $menu_item_ids
				)
			);
		}

		/**
		 * Returns the menu items associated with a particular object.
		 *
		 *
		 * @param array    $args   Array of required params.
		 * @param string $object_type Optional. The type of object, such as 'post_type' or 'taxonomy'.
		 *                            Default 'post_type'.
		 * @param string $taxonomy    Optional. If $object_type is 'taxonomy', $taxonomy is the name
		 *                            of the tax that $object_id belongs to. Default empty.
		 * @return int[] The array of menu item IDs; empty array if none.
		 */
		public static function sm_get_associated_nav_menu_items( $args = array(), $object_type = 'post_type', $taxonomy = '' ) {
			if ( empty( $args ) || ( ! is_array( $args ) ) || empty( $args['selected_post_ids'] ) || empty( $args['post_id_placeholders'] ) ) {
				return;
			}
			global $wpdb;
			$metu_items_query = $wpdb->prepare("
				SELECT DISTINCT p.ID,
					pm.meta_key as meta_key,
					(CASE 
						WHEN pm.meta_key = '_menu_item_object' THEN pm.meta_value 
						WHEN pm.meta_key = '_menu_item_object_id' THEN pm.meta_value
					END) as meta_value
				FROM {$wpdb->prefix}postmeta as pm
				JOIN {$wpdb->prefix}posts p 
					ON (p.ID = pm.post_id
						AND p.post_type = 'nav_menu_item'
						AND pm.meta_key IN ('_menu_item_object', '_menu_item_object_id'))
				WHERE pm.post_id IN (
					SELECT post_id
					FROM {$wpdb->prefix}postmeta
					WHERE meta_key = '_menu_item_object_id'
						AND meta_value IN (" . $args['post_id_placeholders'] . ")
				)
			", $args['selected_post_ids'] );

			// Additional conditions based on object type
			if ( 'post_type' === $object_type ) {
				$metu_items_query .= " AND EXISTS (
					SELECT 1
					FROM {$wpdb->prefix}postmeta pm2
					WHERE pm2.post_id = p.ID
					AND pm2.meta_key = '_menu_item_type'
					AND pm2.meta_value = 'post_type'
				)";
			} elseif ( 'taxonomy' === $object_type ) {
				$metu_items_query .= $wpdb->prepare("
					AND EXISTS (
						SELECT 1
						FROM {$wpdb->prefix}postmeta pm2
						WHERE pm2.post_id = p.ID
						AND pm2.meta_key = '_menu_item_type'
						AND pm2.meta_value = 'taxonomy'
						AND (
							SELECT pm3.meta_value
							FROM {$wpdb->prefix}postmeta pm3
							WHERE pm3.post_id = p.ID
							AND pm3.meta_key = '_menu_item_object'
						) = %s
					)
				", $taxonomy );
			}
			$results = $wpdb->get_col( $metu_items_query );
			if ( is_wp_error( $results ) || ( empty( $results ) ) || ( ! is_array( $results ) ) ) {
				return;
			}
			// Remove duplicate IDs and return unique values
			return array_unique( $results );
		}

		/**
		 * Function to handle delete of a single record
		 *
		 * @param  integer $deleting_id The ID of the record to be deleted.
		 * @return boolean
		 */
		public static function sm_process_delete_non_posts_records( $params = array() ) {
			$deleting_id = ( ! empty( $params['id'] ) ) ? $params['id'] : 0;
			do_action( 'sm_pro_pre_process_delete_non_posts_records', array( 'deleting_id' => $deleting_id, 'source' => __CLASS__ ) );
			if ( empty( $deleting_id ) ) {
				return false;
			}
			$force_delete = ( ! empty( $params['delete_permanently'] ) ) ? true : false;
			$result = false;
			$params[ $force_delete ] = $force_delete;
			$result = apply_filters( 'sm_pro_default_process_delete_records_result', $result, $deleting_id, $params );
			do_action( 'sm_pro_post_process_delete_non_posts_records', array( 'deleting_id' => $deleting_id, 'source' => __CLASS__ ) );
			if ( empty( $result ) ) {
				if ( is_callable( 'sa_manager_log' ) ) {
					sa_manager_log( 'error', _x( 'Delete process failed', 'delete process', 'smart-manager-for-wp-e-commerce' ) );
				}
				return false;
			}
			return true;
		}

		/**
		 * Retrieves an array of WP_Post objects based on post IDs.
		 *
		 * @param array $post_ids Array of post IDs to retrieve.
		 * @return array|null Array of WP_Post objects or void if input is invalid.
		*/
		public static function get_post_obj_from_ids( $post_ids = array() )
		{
			if ( empty( $post_ids ) || ( ! is_array( $post_ids ) ) ) {
				return;
			}
			$num_ids = count( $post_ids );
			if ( empty( $num_ids ) ) {
				return;
			}
			$post_ids              = array_map( 'intval', $post_ids ); // Sanitize ids.
			$post_ids_placeholders = implode( ',', array_fill( 0, $num_ids, '%d' ) );
			global $wpdb;
			$results = $wpdb->get_results(// phpcs:ignore
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}posts WHERE ID IN ( $post_ids_placeholders )", $post_ids
				)
			); // phpcs:ignore
			if ( is_wp_error( $results ) || empty( $results ) || ( ! is_array( $results ) ) ) {
				return;
			}
			return array_map( function ( $result ) {
				return new WP_Post( $result );
			}, $results);
		}

		/**
		 * Merges new data into existing post data, with new fields overwriting old ones.
		 *
		 * @param WP_Post|null $post The post object to update.
		 * @param array        $args New post data fields to merge.
		 * @return void
		*/
		public static function process_post_update_data( $post = null, $args = array() )
		{
			if ( empty( $post ) || empty( $args ) || ( ! is_array( $args ) ) ) {
				return;
			}
			// First, get all of the original fields.
			$current_post_data = get_post( $post->ID, ARRAY_A );
			// Escape data pulled from DB.
			$current_post_data = wp_slash( $current_post_data );
			// Passed post category list overwrites existing category list if not empty.
			$post_cats = ( ! empty( $args['post_category'] ) && is_array( $args['post_category'] ) && count( $args['post_category'] ) > 0 ) ? $args['post_category'] : $current_post_data['post_category'];
			// Drafts shouldn't be assigned a date unless explicitly done so by the user.
			$clear_date = ( ! empty( $current_post_data['post_status'] ) && in_array( $current_post_data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) && empty( $args['edit_date'] ) && ( '0000-00-00 00:00:00' === $current_post_data['post_date_gmt'] ) ) ? true : false;
			// Merge old and new fields with new fields overwriting old ones.
			$args                  = ( ( ! empty( $current_post_data ) ) && ( is_array( $current_post_data ) ) ) ? array_merge( $current_post_data, $args ) : $args;
			$args['post_category'] = $post_cats;
			if ( $clear_date ) {
				$args['post_date']     = current_time( 'mysql' );
				$args['post_date_gmt'] = '';
			}
			return $args;
		}

		/**
		 * Deletes multiple metadata entries in bulk.
		 *
		 * @param string $meta_type  The type of object metadata is for (e.g., 'post', 'user').
		 * @param array  $meta_data  An array of metadata to delete. Each item should be an associative array with keys:
		 *                           'object_id', 'meta_key', 'meta_value' (optional).
		 * @return bool|null True on success else null
		*/
		public static function delete_metadata( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || (  empty( $args[ 'meta_type' ] ) ) || (  empty( $args[ 'meta_data' ] ) ) || (  ! is_array( $args[ 'meta_data' ] ) ) ) {
				return;
			}
			$meta_type = $args[ 'meta_type' ];
			$meta_data = $args[ 'meta_data' ];
			global $wpdb;
			if ( empty( $meta_type ) || empty( $meta_data ) ) {
				return;
			}
			$table = _get_meta_table( $meta_type );
			if ( ! $table ) {
				return;
			}
			$type_column = sanitize_key( $meta_type . '_id' );
			$id_column   = ( 'user' === $meta_type ) ? 'umeta_id' : 'meta_id';
			// Prepare query for bulk selection.
			$select_placeholders = array();
			$select_params     = array();
			$object_ids = array();
			foreach ( $meta_data as $data ) {
				$object_id  = ( ! empty( $data['object_id'] ) ) ? absint( $data['object_id'] ) : 0;
				$meta_key   = ( ! empty( $data['meta_key'] ) ) ? wp_unslash( $data['meta_key'] ) : '';
				if ( empty( $object_id ) || empty( $meta_key ) ) {
					continue;
				}
				$meta_value = ( ! empty( $data['meta_value'] ) ) ? maybe_serialize( wp_unslash( $data['meta_value'] ) ) : '';
				// Short-circuit filter
				$check = apply_filters( "delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, false );
				if ( null !== $check ) {
					continue;
				}
				// Add conditions for query building.
				$select_placeholders[] = "(meta_key = %s AND $type_column = %d" . ( ( ! empty( $meta_value ) ) ? " AND meta_value = %s" : "" ) . ")";
				$select_params[]       = $meta_key;
				$select_params[]       = $object_id;
				if ( ( ! empty( $meta_value ) ) ) {
					$select_params[] = $meta_value;
				}
			}
			if ( empty( $select_placeholders ) ) {
				return;
			}
			//Run query to fetch meta data
			$object_id_col = $meta_type . "_id";
			$query = "SELECT " . str_replace($object_id_col, "$object_id_col AS object_id", implode(', ', array ( 'meta_id', 'meta_key', 'meta_value', $object_id_col ) ) ) . " FROM $table WHERE " . implode( ' OR ', $select_placeholders );
			$result = $wpdb->get_results( $wpdb->prepare( $query, $select_params ) );
			if ( ( empty( $result ) ) || ( is_wp_error( $result ) ) ) {
				return;
			}
			//map data for post and pre actions.
			$grouped_meta_data = self::group_meta_data_to_delete( $result );
			if ( empty( $grouped_meta_data ) || ( ! is_array( $grouped_meta_data ) ) || empty( $grouped_meta_data['meta_ids_to_delete'] ) || empty( $grouped_meta_data['meta_data_for_actions'] ) ) {
				return;
			}
			$meta_ids_to_delete = ( ! empty( $grouped_meta_data['meta_ids_to_delete'] ) ) ? $grouped_meta_data['meta_ids_to_delete'] : array();
			$meta_data_for_actions = ( ! empty( $grouped_meta_data['meta_data_for_actions'] ) ) ? $grouped_meta_data['meta_data_for_actions'] : array();
			//Fire pre actions.
			foreach ( $meta_data_for_actions as $data ) {
				if ( ( empty( $data ) ) || ( empty( $data['meta_ids'] ) ) || ( empty($data['object_id']) ) || ( empty( $data['meta_key'] ) ) || ( ! isset( $data['meta_value'] ) ) ) {
					continue;
				}
				$object_ids[] = $object_id;
				do_action( "delete_{$meta_type}_meta", $data['meta_ids'], $data['object_id'], $data['meta_key'], $data['meta_value'] );
				if ( 'post' === $meta_type ) {
					do_action( 'delete_postmeta', $data['meta_ids'] );
				}
			}
			// Run delete query.
			$query = "DELETE FROM $table WHERE $id_column IN ( " . implode( ',', array_map( 'absint', $meta_ids_to_delete ) ) . " )";
			$result = $wpdb->query( $query );
			if ( empty( $result ) || ( is_wp_error( $result ) ) ) {
				return;
			}
			//clear cache.
			if ( ( ! empty( $object_ids ) ) ) {
				wp_cache_delete_multiple( $object_ids, $meta_type . '_meta' );
			}
			//Fire post actions.
			foreach ( $meta_data_for_actions as $data ) {
				if ( ( empty( $data ) ) || ( empty( $data['meta_ids'] ) ) || ( empty( $data['object_id'] ) ) || ( empty( $data['meta_key'] ) ) || ( ! isset( $data['meta_value'] ) ) ) {
					continue;
				}
				do_action( "deleted_{$meta_type}_meta", $data['meta_ids'], $data['object_id'], $data['meta_key'], $data['meta_value'] );
				if ( 'post' === $meta_type ) {
					do_action( 'deleted_postmeta', $data['meta_ids'] );
				}
			}
			return true;
		}

		/**
		 * Deletes multiple metadata entries in bulk.
		 *
		 * @param object $meta_objects  The post metadata.
		 *
		 * @return array|void array of grouped meta data else void
		*/
		public static function group_meta_data_to_delete( $meta_objects = array() ) {
			if ( ( empty( $meta_objects ) ) || ( ! is_array( $meta_objects ) ) ) {
				return;
			}
			$meta_ids_to_delete    = array();
			$meta_data_for_actions = array();
			foreach ( $meta_objects as $meta ) {
				if ( ( empty( $meta ) ) || ( empty( $meta->meta_id ) ) ) {
					continue;
				}
				$key = $meta->meta_key . '|' . $meta->meta_value . '|' . $meta->object_id;
				$meta_data_for_actions[ $key ]['meta_key']   = ( ! empty( $meta->meta_key ) ) ? sanitize_key( $meta->meta_key ) : '' ;
				$meta_data_for_actions[ $key ]['meta_value'] = ( isset( $meta->meta_value ) ) ? maybe_serialize( $meta->meta_value ) : '' ;
				$meta_data_for_actions[ $key ]['object_id']  = ( ! empty( $meta->object_id ) ) ? absint( $meta->object_id ) : 0 ;
				$meta_data_for_actions[ $key ]['meta_ids'][] = ( ! empty( $meta->meta_id ) ) ? absint( $meta->meta_id ) : 0 ;
				$meta_ids_to_delete[]                        =  $meta->meta_id;
			}
			return array(
				'meta_data_for_actions' => ( ! empty( $meta_data_for_actions ) ) ? array_values( $meta_data_for_actions ) : array(),
				'meta_ids_to_delete' => array_unique( $meta_ids_to_delete )
			);
		}

		/**
		 * Function to update posts count for terms of the taxonomy.
		 *
		 * @param array $taxonomy_count_data  array taxonomy data containing terms.
		 *
		 * @return void
		*/
		public static function update_term_count( $taxonomy_count_data = array() ) {
			if( ( empty( $taxonomy_count_data ) ) || ( ! is_array( $taxonomy_count_data ) ) ){
				return;
			}
			//update terms count for each taxonomy.
			foreach ($taxonomy_count_data as $taxonomy => $terms) {
				if( ( empty( $terms ) ) || ( empty( $taxonomy ) ) || ( ! is_array( $terms ) ) ){
					continue;
				}
				$terms = array_map( 'intval', $terms );

				$taxonomy = get_taxonomy( $taxonomy );
				if ( ( empty( $taxonomy ) ) ) {
					return;
				}
				if ( ( ! empty( $taxonomy->update_count_callback ) ) ) {
					//handle product taxonomies terms count.
					if ( ( '_wc_term_recount' === $taxonomy->update_count_callback ) && ( class_exists( 'Smart_Manager_Pro_Product' ) ) && ( is_callable( array( 'Smart_Manager_Pro_Product', 'products_taxonomy_term_recount' ) ) ) ) {
						Smart_Manager_Pro_Product::products_taxonomy_term_recount( $terms, $taxonomy );
					}else{
						call_user_func( $taxonomy->update_count_callback, $terms, $taxonomy );
					}
				} else {
					$object_types = (array) $taxonomy->object_type;
					foreach ( $object_types as &$object_type ) {
						if ( str_starts_with( $object_type, 'attachment:' ) ) {
							list( $object_type ) = explode( ':', $object_type );
						}
					}

					if ( array_filter( $object_types, 'post_type_exists' ) == $object_types ) {
						// Only post types are attached to this taxonomy.
						self::update_post_term_count( $terms, $taxonomy );
					} else {
						// Default count updater.
						self::update_generic_term_count( $terms, $taxonomy );
					}
				}
				clean_term_cache( $terms, '', false );
			}
		}

		/**
		 * Function to update posts count for terms.
		 *
		 * @param array $terms  array of terms.
		 * @param object  $taxonomy  Taxonomy object.
		 *
		 * @return void
		*/
		public static function update_post_term_count( $terms = array(), $taxonomy = null ) {
			if( ( empty( $terms ) ) || ( empty( $taxonomy ) ) || ( ! is_array( $terms ) ) ){
				return;
			}
			global $wpdb;

			$object_types = (array) $taxonomy->object_type;

			foreach ( $object_types as &$object_type ) {
				list( $object_type ) = explode( ':', $object_type );
			}

			$object_types = array_unique( $object_types );

			$check_attachments = array_search( 'attachment', $object_types, true );
			if ( false !== $check_attachments ) {
				unset( $object_types[ $check_attachments ] );
				$check_attachments = true;
			}
			$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );
			$post_statuses = esc_sql(
				apply_filters( 'update_post_term_count_statuses', array( 'publish' ), $taxonomy )
			);
			// Prepare the placeholders for the terms in a single query.
			$placeholders = implode( ',', array_fill( 0, count( $terms ), '%d' ) );
			$counts = array();
			// Query for attachment counts, if applicable.
			if ( $check_attachments ) {
				$attachment_counts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT term_taxonomy_id, COUNT(*) AS count 
						 FROM $wpdb->term_relationships
						 INNER JOIN $wpdb->posts AS p1 ON p1.ID = $wpdb->term_relationships.object_id 
						 WHERE term_taxonomy_id IN ($placeholders) 
						 AND ( post_status IN ('" . implode( "', '", $post_statuses ) . "') 
							 OR ( post_status = 'inherit' 
							 AND post_parent > 0 
							 AND (SELECT post_status FROM $wpdb->posts WHERE ID = p1.post_parent) IN ('" . implode( "', '", $post_statuses ) . "') ) ) 
						 AND post_type = 'attachment' 
						 GROUP BY term_taxonomy_id",
						$terms
					),
					OBJECT_K
				);
				$counts = array_merge( $counts, $attachment_counts );
			}
			// Query for other object types.
			if ( $object_types ) {
				$post_counts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT term_taxonomy_id, COUNT(*) AS count 
						 FROM $wpdb->term_relationships
						 INNER JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->term_relationships.object_id 
						 WHERE term_taxonomy_id IN ($placeholders) 
						 AND post_status IN ('" . implode( "', '", $post_statuses ) . "') 
						 AND post_type IN ('" . implode( "', '", $object_types ) . "') 
						 GROUP BY term_taxonomy_id",
						$terms
					),
					OBJECT_K
				);

				$counts = array_merge_recursive( $counts, $post_counts );
			}
			$updates = [];
			foreach ( (array) $terms as $term ) {
				if ( ( empty( $term ) ) ) {
					continue;
				}
				// Pre-action for the term.
				do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
				$count = 0;
				// Iterate over the array of objects in $counts to sum matching counts.
				foreach ( $counts as $object ) {
					if ( ( ! empty( $object->term_taxonomy_id ) ) && (int) $object->term_taxonomy_id === $term ) {
						$count += ( ! empty( $object->count ) ) ? (int) $object->count : 0;
					}
				}
				// Collect update data.
				$updates[] = $wpdb->prepare( "(%d, %d)", $term, $count );
			}

			// Perform bulk update query.
			if ( empty( $updates ) ) {
				return;
			}
			$query = "
				INSERT INTO $wpdb->term_taxonomy (term_taxonomy_id, count)
				VALUES " . implode( ', ', $updates ) . "
				ON DUPLICATE KEY UPDATE count = VALUES(count)";
			$result = $wpdb->query( $query );
			if ( ( empty( $result ) ) || ( is_wp_error( $result ) ) ) {
				return;
			}
			// Post-action for the term count update.
			foreach ( (array) $terms as $term ) {
				do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
			}
		}

		/**
		 * Function to update other post types terms count apart from posts .
		 *
		 * @param array $terms  array of terms.
		 * @param object  $taxonomy  Taxonomy object.
		 *
		 * @return void
		*/
		public static function update_generic_term_count( $terms = array(), $taxonomy = null ) {
			if( ( empty( $terms ) ) || ( empty( $taxonomy ) ) || ( ! is_array( $terms ) ) ){
				return;
			}
			global $wpdb;

			// Prepare the placeholders for the terms in a single query.
			$placeholders = implode( ',', array_fill( 0, count( $terms ), '%d' ) );

			// Fetch counts for all terms in one query.
			$counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_taxonomy_id, COUNT(*) AS count 
					 FROM $wpdb->term_relationships 
					 WHERE term_taxonomy_id IN ($placeholders) 
					 GROUP BY term_taxonomy_id",
					$terms
				),
				OBJECT_K
			);

			$updates = array();
			foreach ( (array) $terms as $term ) {
				if ( ( empty( $term ) ) ) {
					continue;
				}
				$count = 0;
				// Iterate over the array of objects in $counts to sum matching counts.
				foreach ( $counts as $object ) {
					if ( ( empty( $object ) ) ) {
						continue;
					}
					if ( isset( $object->term_taxonomy_id ) && (int) $object->term_taxonomy_id === $term ) {
						$count += isset( $object->count ) ? (int) $object->count : 0;
					}
				}
				// Pre-action for the term.
				do_action( 'edit_term_taxonomy', $term, $taxonomy->name );

				// Collect update data.
				$updates[] = $wpdb->prepare( "(%d, %d)", $term, $count );
			}

			// Perform bulk update query.
			if ( ! empty( $updates ) ) {
				$query = "INSERT INTO $wpdb->term_taxonomy (term_taxonomy_id, count) VALUES " . implode( ', ', $updates ) . " ON DUPLICATE KEY UPDATE count = VALUES(count)";
				$result = $wpdb->query( $query );
				if ( ( empty( $result ) ) || ( is_wp_error( $result ) ) ) {
					return;
				}
				foreach ( (array) $terms as $term ) {
					// Post-action for the term.
					do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
				}
			}
		}

		/* Function to get terms undo details
		 *
		 * @param array $existing_relationships
		 * @param array $args
		 *
		 * @return void|array{action: string, prev_val: mixed, updated_val: mixed}
		*/
		public static function get_terms_undo_details( $existing_relationships = array(), $args = array() ) {
			if ( ( empty( $args[ 'col_nm' ] ) ) || ( empty( $existing_relationships ) ) || ( empty( $existing_relationships[ $args[ 'id' ] ] ) ) || empty( $existing_relationships[ $args[ 'id' ] ][ $args[ 'col_nm' ] ] ) ) {
				return;
			}
			$action = 'set_to';
			$prev_vals = $existing_relationships[ $args[ 'id' ] ][ $args[ 'col_nm' ] ];
			if ( ( 'remove_from' === $args['operator'] ) ) {
				if ((is_array($prev_vals)) && ( ! in_array($args['value'],$prev_vals) )) {
					return;
				}
				$action = 'add_to';
			}
			if ( ( 'add_to' === $args['operator'] ) ) {
				if ( ( is_array( $prev_vals ) ) && ( in_array( $args['value'], $prev_vals ) ) ) {
					return;
				}
				$action = 'remove_from';
			}
			return(
				array(
					'action'=>$action,
					'prev_val'=>$prev_vals,
					'updated_val'=>$args['value'],
				)
			);
		}

		/**
		 * Function to get Term By ID form the array of term objects.
		 *
		 * @param array $terms  array of term objects.
		 * @param int  $term_id  ID of the terms to get.
		 *
		 * @return object|void term object on success else void.
		*/
		public static function get_term_by_id( $terms = array(), $term_id = 0 ) {
			if ( empty( $terms ) || empty( $term_id ) || ( ! is_array( $terms ) ) ) {
				return;
			}
			foreach ( $terms as $term ) {
				if ( empty( $term ) ) {
					continue;
				}
				if ( ( ! empty( $term->term_id ) ) && ( absint( $term->term_id ) === absint( $term_id ) ) ) {
					return $term;
				}
			}
		}

		/**
	     * Function to map inline terms update data.
		 *
		 * @param  array $args array data.
		 *
		 * @return array $args array data.
		 */
		public static function map_inline_terms_update_data( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args[ 'id' ] ) ) || ( ! isset( $args[ 'taxonomies' ] ) ) || ( ! is_array( $args[ 'taxonomies' ] ) ) || ( ! isset( $args[ 'value' ] ) ) || ( empty ( $args[ 'term_ids' ] ) ) || ( empty ( $args[ 'update_column' ] ) ) ) {
				return $args;
			}
			if( ( ! in_array( $args[ 'update_column' ], $args[ 'taxonomies' ] ) ) ){
				$args[ 'taxonomies' ][] = $args[ 'update_column' ];
			}
			if ( ( empty( absint( $args[ 'value' ] ) ) ) ) {
				$default_term = absint( get_option( 'default_'.$args[ "update_column" ], 0 ) );
				if ( ( ! empty( $default_term ) ) ) {
					$args[ 'term_ids' ] = array( $default_term );
				}
			}
			$args[ 'taxonomy_data_to_update' ][ $args['id'] ][ $args[ 'update_column' ] ] = array(
				'term_ids_set' => $args[ 'term_ids' ],
				'taxonomy' => $args[ 'update_column' ],
				'append' => false,
				'remove_all_terms' => ( empty( absint( $args[ 'value' ] ) ) ) ? true : false,
			);
			return $args;
		}

		/**
		 * Processes a scheduled export CSV file and sends an email with the download link.
		 *
		 * @param array $args Parameters for processing the CSV export.
		 * @return void
		 */
		public static function process_scheduled_csv_email_export($args = array())
		{
			if ((empty($args)) || (! is_array($args)) || (empty($args['scheduled_export_params'])) || (empty($args['csv_file_name'])) || (empty($args['file_data'])) || (empty($args['file_data']['upload_dir'])) || (! is_array($args['file_data']['upload_dir'])) || (empty($args['file_data']['file_content'])) || (empty($args['scheduled_export_params']['schedule_export_email']))) {
				sa_manager_log('error', _x('Export CSV: Missing required CSV file data.', 'process scheduled export file data', 'smart-manager-for-wp-e-commerce'));
				return;
			}
			$csv_upload_dir = trailingslashit($args['file_data']['upload_dir']['basedir']) . 'woocommerce_uploads/';
			if ((! file_exists($csv_upload_dir))) {
				if (false === wp_mkdir_p($csv_upload_dir)) {
					/* translators: %s: Directory path */
					sa_manager_log('error', sprintf(_x('Export CSV: unable to create directory %s', 'export file data', 'smart-manager-for-wp-e-commerce'), $csv_upload_dir));
					return;
				};
			}
			$csv_file_name  = sanitize_file_name($args['csv_file_name']);
			$full_file_path = trailingslashit($csv_upload_dir) . $csv_file_name;
			//check file write permissions.
			if (false === file_put_contents($full_file_path, $args['file_data']['file_content'])) {
				/* translators: %s: File path */
				sa_manager_log('error', sprintf(_x('Export CSV: unable to write file to %s', 'process scheduled export file data', 'smart-manager-for-wp-e-commerce'), $full_file_path));
				return;
			}

			$filetype = wp_check_filetype($csv_file_name, null);
			if ((empty($filetype)) || (! is_array($filetype)) || (empty($filetype['type']))) {
				sa_manager_log('error', _x('Export CSV: error in checking file type', 'process scheduled export file data', 'smart-manager-for-wp-e-commerce'));
				return;
			}
			$attachment_id = wp_insert_attachment(array(
				'guid'           => trailingslashit($args['file_data']['upload_dir']['baseurl']) . 'woocommerce_uploads/' . $csv_file_name,
				'post_mime_type' => $filetype['type'],
				'post_title'     => $csv_file_name,
				'post_status'    => 'inherit'
			), $full_file_path);

			if ((empty($attachment_id)) || (is_wp_error($attachment_id))) {
				/* translators: %s: File path */
				sa_manager_log('error', sprintf(_x('Export CSV: failed to insert attachment for file %s', 'process scheduled export file data', 'smart-manager-for-wp-e-commerce'), $full_file_path));
				return;
			}
			// Update attachment meta to mark as a scheduled export file.
			update_post_meta($attachment_id, 'sa_sm_is_scheduled_export_file', true);
			//generate media URL and send email.
			$csv_url  = wp_get_attachment_url($attachment_id);
			if (empty($csv_url)) {
				sa_manager_log('error', _x('Export CSV: error in getting csv url', 'process scheduled export file data', 'smart-manager-for-wp-e-commerce'));
				return;
			}
			// Preparing email content.
			$site_name = get_bloginfo();
			$date = date_i18n(get_option('date_format'), current_time('timestamp'));
			$email_subject = sprintf(/* translators: 1: Site title, 2: Date */
				_x('Your Scheduled Orders Export from %1$s on %2$s Is Ready', 'Email subject for scheduled export', 'smart-manager-for-wp-e-commerce'),
				$site_name,
				$date
			);
			ob_start();
			include(apply_filters('sm_beta_pro_scheduled_export_email_template', SM_EMAIL_TEMPLATE_PATH . '/scheduled-export.php'));
			$email_message = ob_get_clean();
			//send email.
			SA_Manager_Pro_Base::send_email(array(
				'email' => sanitize_email($args['scheduled_export_params']['schedule_export_email']),
				'subject' => (! empty($email_subject)) ? $email_subject : '',
				'message' => (! empty($email_message)) ? $email_message : ''
			));
		}

		/**
		 * Schedule scheduled exports file deletion after x number of days
		 *
		 * @return void
		 */
		public static function schedule_scheduled_exports_cleanup()
		{
			if (! function_exists('as_schedule_recurring_action') ||  ! function_exists('as_next_scheduled_action')) {
				return;
			}
			if (as_next_scheduled_action('storeapps_smart_manager_scheduled_export_cleanup')) {
				return;
			}
			$file_deletion_days = intval(get_option('sa_sm_scheduled_export_file_expiration_days'));
			if (empty($file_deletion_days)) {
				$file_deletion_days = intval(apply_filters('sa_sm_scheduled_export_file_expiration_days', 30));
				if (empty($file_deletion_days)) {
					return;
				}
				update_option('sa_sm_scheduled_export_file_expiration_days', $file_deletion_days, 'no');
			}
			$timestamp = strtotime(date('Y-m-d H:i:s', strtotime("+" . $file_deletion_days . " Days")));
			if (empty($timestamp)) {
				return;
			}
			// Schedule the recurring action to run daily.
			as_schedule_recurring_action($timestamp, DAY_IN_SECONDS, 'storeapps_smart_manager_scheduled_export_cleanup');
		}

		/**
		 * Retrieve parameters needed to create a scheduled export action.
		 *
		 * @param array $params Input parameters.
		 *
		 * @return array Filtered parameters for the scheduled export action.
		 */
		public static function get_scheduled_export_action_params($params = array())
		{
			if (empty($params) || ! is_array($params)) {
				return;
			}
			return array_intersect_key($params, array_flip(array(
				'action',
				'cmd',
				'active_module',
				'pro',
				'SM_IS_WOO30',
				'is_scheduled_export',
				'scheduled_export_params',
				'class_nm',
				'class_path',
				'dashboard_key',
				'table_model',
				'sort_params'
			)));
		}

		/**
		 * Handles the batch update process for post-processing bacth update.
		 *
		 * @param bool $update Optional. Default value to return if no update is performed. Default true.
		 *
		 * @return bool|mixed Returns the result of `Smart_Manager_Task::task_details_update()` if callable,
		 *                    otherwise returns the `$update` parameter.
		 */
		public static function handle_post_processing_batch_update( $update = true ) {
			if ( empty( Smart_Manager_Base::$update_task_details_params ) ) {
				return $update;
			}
			apply_filters('sm_task_details_update_by_prev_val', Smart_Manager_Base::$update_task_details_params);
			// For updating task details table
			if ((! empty(Smart_Manager_Base::$update_task_details_params)) && is_callable(array('Smart_Manager_Task', 'task_details_update'))) {
				return Smart_Manager_Task::task_details_update();
			}
		}

		/**
		 * Handles post-processing of parameters after a batch update operation.

		 * @param array $args Optional. An associative array of arguments for post-processing.
		 * Default is an empty array.
		 *
		 * @return void
		 */
		public static function update_params_post_processing_batch_update( $args = array() ) {
			if ( empty( $args ) || ( empty( $args['task_id'] ) ) || ( empty( property_exists('Smart_Manager_Base', 'update_task_details_params') ) ) ) {
				return;
			}
			$action = 'set_to';
			if (in_array($args['operator'], array('add_to', 'remove_from'))) {
				$action = apply_filters('sm_task_update_action', $args['operator'], $args);
			}
			//Special handling for add_to and remove_from operations for terms table.
			if (('terms' === $args['table_nm'])) {
				$existing_relationships = ((! empty($args['update_result']['taxonomies_update_result'])) && (! empty($args['update_result']['taxonomies_update_result']['existing_relationships']))) ? $args['update_result']['taxonomies_update_result']['existing_relationships'] : array();

				if ((empty($args['col_nm'])) || (empty($existing_relationships)) || (empty($existing_relationships[$args['id']])) || empty($existing_relationships[$args['id']][$args['col_nm']])) {
					return;
				}
				$terms_undo_details = self::get_terms_undo_details($existing_relationships, $args);
				if ((empty($terms_undo_details)) || (! is_array($terms_undo_details)) || (empty($terms_undo_details['action'])) || (empty($terms_undo_details['prev_val'])) || (empty($terms_undo_details['updated_val']))) {
					return;
				}
				$action = $terms_undo_details['action'];
				$args['prev_val'] =  $terms_undo_details['prev_val'];
				$args['value'] =  $terms_undo_details['updated_val'];
				if (in_array($args['operator'], array('add_to', 'remove_from'))) {
					list($args['prev_val'], $args['value']) = [$args['value'], $args['prev_val']];
				}
			}
			Smart_Manager_Base::$update_task_details_params[] = array(
				'task_id' => $args['task_id'],
				'action' => $action,
				'status' => 'completed',
				'record_id' => $args['id'],
				'field' => $args['type'],
				'prev_val' => ((! empty($args['col_nm'])) && (! empty($args['date_type']))) ? sa_sm_format_prev_val(
					array(
						'prev_val' => $args['prev_val'],
						'update_column' => $args['col_nm'],
						'col_data_type' => $args['date_type'],
						'updated_val' => $args['value']
					)
				) : $args['prev_val'],
				'updated_val' => $args['value'],
				'operator' => $args['operator'],
			);
		}

		/**
		 * Get entire store ids.
		 */
		public function get_entire_store_ids()
		{
			global $wpdb;

			$selected_ids = array();

			if (!empty($this->req_params['filteredResults'])) {
				$post_ids = get_transient('sa_sm_search_post_ids');
				$selected_ids = (!empty($post_ids)) ? explode(",", $post_ids) : array();
				$selected_ids = apply_filters('sa_sm_search_results_selected_ids', $selected_ids, $this->req_params );
			} else {
				$post_type = (!empty($this->req_params['table_model']['posts']['where'])) ? $this->req_params['table_model']['posts']['where'] : array('post_type' => $this->dashboard_key);

				if (!empty($this->req_params['table_model']['posts']['where']['post_type'])) {
					$post_type = (is_array($this->req_params['table_model']['posts']['where']['post_type'])) ? $this->req_params['table_model']['posts']['where']['post_type'] : array($this->req_params['table_model']['posts']['where']['post_type']);
				}
				$this->req_params['post_type'] = $post_type;

				$select = "SELECT ID ";
				$from = " FROM {$wpdb->prefix}posts ";
				$where = " WHERE post_type IN ('" . implode("','", $post_type) . "') ";

				$update_trash_records = apply_filters('sm_update_trash_records', ('yes' === get_option('sm_update_trash_records', 'no')));
				if (empty($update_trash_records) && (is_callable(array($this, 'is_show_trash_records')) && empty($this->is_show_trash_records()))) {
					$where .= " AND post_status != 'trash'";
				}

				$select	= apply_filters('sm_beta_background_entire_store_ids_select', $select, $this->req_params);
				$from	= apply_filters('sm_beta_background_entire_store_ids_from', $from, $this->req_params);
				$where	= apply_filters('sm_beta_background_entire_store_ids_where', $where, $this->req_params);

				$query = apply_filters('sm_beta_background_entire_store_ids_query', $wpdb->prepare( $select . $from . " " . $where . " AND 1=%d", 1));
				//Filter to allow using get_results instead of get_col for select entire store ids query.
				$use_get_results = apply_filters( 'sa_sm_use_get_results_in_select_entire_store_ids_query', false, $this->req_params );
				$selected_ids = ( ! empty( $use_get_results ) ) ? $wpdb->get_results( $query, ARRAY_A ) : $wpdb->get_col( $query );
			}
			return $selected_ids;
		}

		/**
		 * Updates task parameters before performing a batch update.
		 *
		 * @param array $params An array of parameters for the batch update.
		 *
		 * @return array|null The modified parameters with task ID included in actions, or null if invalid input.
		 */
		public static function update_task_params_before_batch_update($params = array())
		{
			if (empty($params) || (! is_array($params))) {
				return;
			}
			$process_names = apply_filters('sm_get_process_names_for_adding_tasks', $params['process_key']);
			if (empty($process_names) || (! is_array($process_names)) || (! in_array($params['process_key'], $process_names))) {
				return $params;
			}
			$task_id = 0;
			if (function_exists('sm_task_update') && (isset($params['title']) && (! empty($params['title']))) && (! empty($params['dashboard_key'])) && (! empty($params['actions'])) && (! empty($params['selected_ids']) && is_array($params['selected_ids']))) {
				$task_id = sm_task_update(
					array(
						'title' => $params['title'],
						'created_date' => date('Y-m-d H:i:s'),
						'completed_date' => '0000-00-00 00:00:00',
						'post_type' => $params['dashboard_key'],
						'type' => 'bulk_edit',
						'status' => 'in-progress',
						'actions' => (! empty($params['is_scheduled']) && is_array($params['actions'])) ? array_merge($params['actions'], array('is_scheduled' => $params['is_scheduled'])) : $params['actions'],
						'record_count' => count($params['selected_ids']),
					)
				);
			}
			$params['actions'] = array_map(function ($params_action) use ($task_id) {
				$params_action['task_id'] = $task_id;
				return $params_action;
			}, $params['actions']);
			return $params;
		}

		/**
		 * Update meta action details
		 *
		 * @param array $params task details.
		 * @return void
		 */
		public static function update_meta_action_details( $params = array() )
		{
			if ( empty( $params ) || ( ! is_array( $params ) ) || empty( $params['task_id'] ) || empty( $params['id'] ) || empty( $params['meta_key'] ) ) {
				return;
			}
			$id = $params['id'];
			$meta_key = $params['meta_key'];
			$disable_task_details_update = apply_filters(
				'sm_disable_task_details_update',
				false,
				array(
					'prev_vals' => ( 'postmeta/meta_key=_product_attributes/meta_value=_product_attributes' === $params['field_names'][ $id ][ $meta_key ] ) ? Smart_Manager_Base::$previous_vals : $params['prev_postmeta_values'],
					'field_name' => ( ! empty( $params['field_names'][ $id ][ $meta_key ] ) ) ? $params['field_names'][ $id ][ $meta_key ] : '',
					'data' => ( ! empty( $params ) ) ? $params : array(),
					'record_id' => ( ! empty( $id ) ) ? intval( $id ) : 0,
				)
			);
			if ( empty( $params['task_id'] ) || empty( $id ) || empty( $meta_key ) || ( ! isset( $params['field_names'][$id][$meta_key] ) ) || ( ! empty( $disable_task_details_update ) ) ) {
				return;
			}
			Smart_Manager_Base::$update_task_details_params[] = array(
				'task_id' => $params['task_id'],
				'action' => 'set_to',
				'status' => 'completed',
				'record_id' => $id,
				'field' => $params['field_names'][$id][$meta_key],
				'prev_val' => $params['prev_postmeta_values'][$id][$meta_key],
				'updated_val' => $params['meta_value']
			);
		}

		/**
		 * Update task details
		 *
		 * @param array $params task details.
		 * @return void
		 */
		public static function update_action_params( $params = array() )
		{
			if ( empty( $params ) || ( ! is_array( $params ) ) || ( defined('SMPRO') && empty( SMPRO ) ) ) {
				return;
			}
			Smart_Manager_Base::$update_task_details_params[] = $params;
		}

		/**
		 * Retrieves a list of subscriptions for the specified product IDs.
		 *
		 * @param array $product_ids An array of product IDs to filter the subscriptions. Default empty array.
		 * @return array List of subscriptions associated with the given product IDs.
		 */
		public static function get_products_subscriptions( $product_ids = array() ) {
			if ( ( empty( $product_ids ) ) || ( ! is_array( $product_ids ) ) ) {
				return;
			}
			// Sanitize product IDs using absint.
			$product_ids = array_map( 'absint', $product_ids );
			global $wpdb;
			$args = array(
				'exclude_subscription_status' => array( 'expired', 'cancelled' ),
				'limit'                       => -1,
				'offset'                      => 0,
				'auto_renew_only'             => true,
				'stripe_only'                 => true,
			);

			$is_hpos = function_exists( 'wcs_is_custom_order_tables_usage_enabled' ) && wcs_is_custom_order_tables_usage_enabled();
			$orders_table = $is_hpos ? 'wc_orders' : 'posts';
			$order_id_col = $is_hpos ? 'id' : 'ID';
			$order_type_col = $is_hpos ? 'type' : 'post_type';
			$order_status_col = $is_hpos ? 'status' : 'post_status';
			$order_meta_table = $is_hpos ? 'wc_orders_meta' : 'postmeta';
			$payment_column   = $is_hpos ? 'payment_method' : 'meta_value';

			$where = array(
				"orders.{$order_type_col} = 'shop_subscription'",
				"order_items.order_item_type = 'line_item'",
				"itemmeta.meta_key IN ('_product_id', '_variation_id')",
			);

			$product_placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
			$where[] = "itemmeta.meta_value IN ( $product_placeholders )";

			if ( ! in_array( 'any', $args['exclude_subscription_status'], true ) ) {
				$statuses = array_map( 'wcs_sanitize_subscription_status_key', $args['exclude_subscription_status'] );
				$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
				$where[] = "orders.{$order_status_col} NOT IN ( $status_placeholders )";
				$product_ids = array_merge( $product_ids, $statuses );
			}

			$auto_renew_join = '';
			$meta_join_column = $is_hpos ? 'order_id' : 'post_id';
			if ( ! empty( $args['auto_renew_only'] ) ) {
				$auto_renew_join = "LEFT JOIN {$wpdb->prefix}{$order_meta_table} AS autorenew ON autorenew.{$meta_join_column} = orders.{$order_id_col}";
				$where[]         = "(autorenew.meta_key = '_schedule_next_payment' AND autorenew.meta_value > 0)";
			}
			// Stripe-only filter.
			$stripe_join = '';
			if ( ! empty( $args['stripe_only'] ) ) {
				if ( $is_hpos ) {
					// HPOS: Use payment_method column.
					$where[] = "orders.{$payment_column} = 'stripe'";
				} else {
					// Non-HPOS: Join postmeta and filter _payment_method.
					$stripe_join = "LEFT JOIN {$wpdb->prefix}postmeta AS paymeta ON paymeta.post_id = orders.{$order_id_col}";
					$where[]     = "(paymeta.meta_key = '_payment_method' AND paymeta.meta_value = 'stripe')";
				}
			}
			$where_sql = implode( ' AND ', $where );
			$limit_sql  = ( $args['limit'] > 0 ) ? $wpdb->prepare( 'LIMIT %d', $args['limit'] ) : '';
			$offset_sql = ( $args['limit'] > 0 && $args['offset'] > 0 ) ? $wpdb->prepare( 'OFFSET %d', $args['offset'] ) : '';

			$results = $wpdb->get_results( 
				$wpdb->prepare( 
					"SELECT DISTINCT order_items.order_id AS subscription_id, itemmeta.meta_value AS product_id
					FROM {$wpdb->prefix}woocommerce_order_items AS order_items
					LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON order_items.order_item_id = itemmeta.order_item_id
					LEFT JOIN {$wpdb->prefix}{$orders_table} AS orders ON order_items.order_id = orders.{$order_id_col}
					$auto_renew_join
					$stripe_join
					WHERE $where_sql
					$limit_sql $offset_sql", 
					$product_ids 
				), 
				ARRAY_A 
			);
			if ( ( empty( $results ) ) || ( is_wp_error( $results ) ) ) {
				return;
			}
			$subcriptions = array();
			foreach ( $results as $row ) {
				if ( ( empty( absint( $row['subscription_id'] ) ) ) || ( empty( absint( $row['product_id'] ) ) ) ) {
					continue;
				}
				$subcriptions[ absint( $row['subscription_id'] ) ][] = absint( $row['product_id'] );
			}
			return array_map(
				function( $sub_id ) use ( $subcriptions ) {
					return array( $sub_id => $subcriptions[ $sub_id ] );
				},
				array_keys( $subcriptions )
			);
		}

		/**
		 * Trigger WPML terms translations sync after updating post terms via inline edit.
		 *
		 * @param array $args update arguments.
		 * @return void
		 */
		public static function after_update_post_term( $args = array() ) {
			if ( ( empty( $args ) ) || ( ! is_array( $args ) ) || ( empty( $args['id'] ) ) || ( ! class_exists( 'SA_Manager_Pro_Base' ) ) || ( ! is_callable( array( 'SA_Manager_Pro_Base', 'sync_wpml_terms_translations' ) ) ) ) {
				return;
			}
			SA_Manager_Pro_Base::sync_wpml_terms_translations( $args['id'] );
		}
	}
}
