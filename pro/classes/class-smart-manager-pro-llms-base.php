<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_LLMS_Base' ) ) {
	class Smart_Manager_Pro_LLMS_Base extends Smart_Manager_Pro_Base {
		public $dashboard_key = '';

        public static $post_types =  array( 'course', 'section', 'lesson', 'llms_quiz', 'llms_question', 'llms_membership', 'llms_engagement', 'llms_order', 
        'llms_transaction', 'llms_achievement', 'llms_certificate', 'llms_my_certificate', 'llms_email', 'llms_coupon', 'llms_voucher', 'llms_review',
        'llms_access_plan' );

		protected static $_instance = null;

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			parent::__construct($dashboard_key);
			$this->dashboard_key = $dashboard_key;
            $this->strings_to_remove_from_title = array( '_llms', 'llms_', '_courses', 'course_', 'post_' , '_' );

            if( empty( $this->is_dashboard_class_called ) ){
                add_filter( 'sa_sm_dashboard_model',array( &$this,'base_dashboard_model' ), 10, 2 );
            }
		}

		public static function actions() {

		}

        public function base_dashboard_model( $dashboard_model, $dashboard_model_saved ){
            $this->dashboard_model = $dashboard_model;
            $this->dashboard_model_saved = $dashboard_model_saved;
			return $this->llms_dashboard_model();
        }

		//Function to override the dashboard model
		public function llms_dashboard_model() {

			if( !empty( $this->dashboard_model['columns'] ) ) {

				$column_model = &$this->dashboard_model['columns'];

				foreach( $column_model as $key => &$column ) {
					
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


					if( empty($this->dashboard_model_saved) && ! empty( $this->visible_columns ) ) {
						if (!empty($column['position'])) {
							unset($column['position']);
						}

						$position = array_search( $src, $this->visible_columns );

						if ($position !== false) {
							$column['position'] = $position + 1;
							$column['hidden'] = false;
						} else {
							$column['hidden'] = true;
						}
					}

                    switch( $src ) {
						case ( ! empty( $this->datetime_columns ) && in_array( $src, $this->datetime_columns ) ):
							$column['type'] = $column['editor'] = 'sm.datetime';
					  		$column['width'] = 102;
					  		break;
                        case ( ! empty( $this->numeric_columns ) && in_array( $src, $this->numeric_columns ) ):
					  		$column['type'] = 'numeric';
							$column['editor'] = 'customNumericEditor';
							$column['min'] = 0;
							$column['width'] = 50;
							$column['align'] = 'right';
							break;
                        case ( ! empty( $this->checkbox_empty_one_columns ) && in_array( $src, $this->checkbox_empty_one_columns ) ):
							$column['type'] = $column['editor'] = 'checkbox';
							$column['checkedTemplate'] = 1;
	      					$column['uncheckedTemplate'] = '';
							$column['width'] = 30;
							break;
					}

					// Code for updating the titles
                    $column['name'] = $column['key'] = ( ! empty( $this->col_titles ) && ! empty( $this->col_titles[$src] ) ) ?  $this->col_titles[$src] : __( ucwords( trim( str_replace( $this->strings_to_remove_from_title, ' ', $src ) ) ), 'smart-manager-for-wp-e-commerce' );
				}
			}


			if ( ! empty( $this->dashboard_model_saved ) ) {
				$col_model_diff = sa_array_recursive_diff( $this->dashboard_model_saved,$this->dashboard_model);	
			}

			//clearing the transients before return
			if ( ! empty( $col_model_diff ) ) {
				delete_transient( 'sa_sm_'.$this->dashboard_key );	
			}		

			return $this->dashboard_model;
		}
	}
}
