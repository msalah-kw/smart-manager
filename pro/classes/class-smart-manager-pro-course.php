<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Course' ) ) {
	class Smart_Manager_Pro_Course extends Smart_Manager_Pro_LLMS_Base {
		public $dashboard_key = '',
				$req_params = array(),
				$plugin_path = '';

		protected static $_instance = null;

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			$this->dashboard_key = $dashboard_key;
			$this->post_type = $dashboard_key;
			$this->req_params = (!empty($_REQUEST)) ? $_REQUEST : array();
            $this->is_dashboard_class_called = true; //Flag for handling of actions in the LLMS_Base class

            parent::__construct($this->dashboard_key);

			add_filter( 'sa_sm_dashboard_model',array( &$this,'dashboard_model' ), 10, 2 );
		}

		public static function actions() {

		}

		//Function to override the dashboard model
		public function dashboard_model( $dashboard_model, $dashboard_model_saved ) {
			$this->dashboard_model = $dashboard_model;
            $this->dashboard_model_saved = $dashboard_model_saved;
			$this->visible_columns = array('ID', '_thumbnail_id', 'post_title', 'post_content', 'post_status', 'post_name', 'post_author', 'course_cat',
				'course_tag', 'course_difficulty', '_llms_instructors', 'llms_product_visibility', 'edit_link', 'view_link');
            $this->col_titles = array(
                'course_cat' => __('Category', 'smart-manager-for-wp-e-commerce' )
            );

            return $this->llms_dashboard_model();
		}
	}
}
