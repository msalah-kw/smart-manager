<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Llms_Order' ) ) {
	class Smart_Manager_Pro_Llms_Order extends Smart_Manager_Pro_LLMS_Base {
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
			$this->visible_columns = array();
            
            // Code for order statuses
            $post_status_col_index = sa_multidimesional_array_search('posts_post_status', 'data', $this->dashboard_model['columns']);
			
			if( function_exists('llms_get_order_statuses') ) {
				$order_statuses = llms_get_order_statuses();
			}

			$order_statuses_keys = ( !empty( $order_statuses ) ) ? array_keys($order_statuses) : array();

			$this->dashboard_model['columns'][$post_status_col_index]['defaultValue'] = ( !empty( $order_statuses_keys[0] ) ) ? $order_statuses_keys[0] : 'wc-pending';

			$this->dashboard_model['columns'][$post_status_col_index]['save_state'] = true;
			
			$this->dashboard_model['columns'][$post_status_col_index]['values'] = $order_statuses;
			$this->dashboard_model['columns'][$post_status_col_index]['selectOptions'] = $order_statuses; //for inline editing

			$this->dashboard_model['columns'][$post_status_col_index]['search_values'] = array();
			foreach ($order_statuses as $key => $value) {
				$this->dashboard_model['columns'][$post_status_col_index]['search_values'][] = array('key' => $key, 'value' => $value);
			}

			// Code for handling color codes for order statuses
			$color_codes = array( 'green' => array( 'llms-completed', 'llms-active' ),
									'red' => array( 'llms-expired', 'llms-cancelled', 'llms-failed', 'llms-refunded' ),
									'orange' => array( 'llms-on-hold', 'llms-pending' ),
									'blue' => array( 'llms-pending-cancel' )
								);

			$this->dashboard_model['columns'][$post_status_col_index]['colorCodes'] = apply_filters( 'sm_'.$this->dashboard_key.'_status_color_codes', $color_codes );

			return $this->llms_dashboard_model();
		}
	}
}
