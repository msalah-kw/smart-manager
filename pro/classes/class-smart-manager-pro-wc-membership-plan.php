<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_WC_Membership_Plan' ) ) {
	class Smart_Manager_Pro_WC_Membership_Plan extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
				$plugin_path = '';

		protected static $_instance = null;

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {

			parent::__construct($dashboard_key);
			$this->hooks();

			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );

		}

		public static function actions() {

		}

		public function hooks() {
			add_filter('sa_sm_dashboard_model',array(&$this,'wc_membership_plan_dashboard_model'),10,2);
		}

		public function wc_membership_plan_dashboard_model ($dashboard_model, $dashboard_model_saved) {
			
			$visible_columns = array( 'ID', 'post_title', 'post_name', 'post_status', '_access_method', '_access_length', '_access_start_date', '_access_end_date', 'link' );

			$readonly_columns = array( '_access_method','_access_length','link','post_author','_members_area_sections','_product_ids' );

			$numeric_columns = array( 'id', 'post_author', 'post_parent', 'menu_order', 'comment_count', '_edit_last', 'post_id' );
			$datetime_columns = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt', '_access_start_date', '_access_end_date' );

			$column_model = &$dashboard_model['columns'];

			foreach ( $column_model as $key => &$column ) {
				if (empty($column['src'])) continue;

				$src_exploded = explode("/",$column['src']);

				if (empty($src_exploded)) {
					$src = $column['src'];
				}

				if ( sizeof($src_exploded) > 2) {
					$col_table = $src_exploded[0];
					$cond = explode("=",$src_exploded[1]);

					if (sizeof($cond) == 2) {
						$src = $cond[1];
					}
				} else {
					$src = $src_exploded[1];
					$col_table = $src_exploded[0];
				}

				if ( empty( $dashboard_model_saved ) ) {
					//Code for unsetting the position for hidden columns
					if ( ! empty( $column['position'] ) ) {
						unset( $column['position'] );
					}

					$position = array_search( $src, $visible_columns );

					if ( $position !== false ) {
						$column['position'] = $position + 1;
						$column['hidden'] = false;
					} else {
						$column['hidden'] = true;
					}
				}

				if ( ! empty( $src ) ) {
					if ( empty( $dashboard_model_saved ) ) {
						if ( in_array( $src, $numeric_columns ) ) {
							$column['type'] = 'numeric';
							$column['editor'] = 'customNumericEditor';
						} elseif ( in_array( $src, $datetime_columns ) ) {
							$column['type'] = 'sm.datetime';
							$column['editor'] = $column['type'];
						} elseif ( in_array( $src, $readonly_columns ) ) {
							$column['editor'] = false;
						}
						switch ( $src ) {
							case 'post_name':
								$column['name'] = __( 'Slug', 'smart-manager-for-wp-e-commerce' );
								$column['key'] = __( 'Slug', 'smart-manager-for-wp-e-commerce' );
								break;
						}
					}
				}

			}

			if (!empty($dashboard_model_saved)) {
				$col_model_diff = sa_array_recursive_diff( $dashboard_model_saved, $dashboard_model );	
			}

			//clearing the transients before return
			if (!empty($col_model_diff)) {
				delete_transient( 'sa_sm_'.$this->dashboard_key );	
			}

			return $dashboard_model;
		}

	}

}
