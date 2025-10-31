<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Acf_Field' ) ) {
	class Smart_Manager_Pro_Acf_Field extends Smart_Manager_Pro_Base {
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
			add_filter('sa_sm_dashboard_model',array(&$this,'dashboard_model'),10,2);
		}

		public function dashboard_model ($dashboard_model, $dashboard_model_saved) {
			
			$visible_columns = array( 'ID', 'post_excerpt', 'post_title', 'post_name', 'post_content', 'post_parent' );

            $column_titles = array(
                    'post_title'        => __( 'Label', 'smart-manager-for-wp-e-commerce' ),
                    'post_excerpt'      => __( 'Name', 'smart-manager-for-wp-e-commerce' ),
                    'post_content'      => __( 'Config Options', 'smart-manager-for-wp-e-commerce' ),
                    'post_name'         => __( 'Key', 'smart-manager-for-wp-e-commerce' ),
                    'post_parent'       => __( 'Group Id', 'smart-manager-for-wp-e-commerce' ),
            );

            $serialized_columns = array( 'post_content' );
			$numeric_columns = array( 'id', 'post_author', 'post_parent', 'menu_order', 'comment_count', 'post_id' );
			$datetime_columns = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt');

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

                        if( ! empty( $column_titles[ $src ] ) ){
                            $column['name'] = $column['key'] = $column_titles[ $src ];
                        }

                        if ( in_array( $src, $numeric_columns ) ) {
							$column['type'] = 'numeric';
							$column['editor'] = 'customNumericEditor';
						} elseif ( in_array( $src, $datetime_columns ) ) {
							$column['type'] = $column['editor'] = 'sm.datetime';
						} elseif ( in_array( $src, $serialized_columns ) ) {
							$column['type'] = $column['editor'] = 'sm.serialized';
                            $column['editor_schema'] = false;
						} elseif( 'post_excerpt' === $src ){
							$column['type'] = $column['editor'] = 'text';
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
