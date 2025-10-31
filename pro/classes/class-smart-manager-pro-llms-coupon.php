<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Llms_Coupon' ) ) {
	class Smart_Manager_Pro_Llms_Coupon extends Smart_Manager_Pro_LLMS_Base {
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

            
            // Code for handling discount type for coupon
            $discount_type_col_index = sa_multidimesional_array_search('postmeta_meta_key__llms_discount_type_meta_value__llms_discount_type', 'data', $this->dashboard_model['columns']);

			$discount_types = array(
                'percent'   => __( 'Percentage Discount', 'lifterlms' ),
                'dollar'    => sprintf( _x( '%s Discount', 'flat rate coupon discount', 'lifterlms' ), ( function_exists( 'get_lifterlms_currency_symbol' ) ? html_entity_decode( get_lifterlms_currency_symbol() ) : '$' ) )
            );

			$this->dashboard_model['columns'][$discount_type_col_index]['type'] = 'dropdown';
			$this->dashboard_model['columns'][$discount_type_col_index]['editor'] = 'select2';
			$this->dashboard_model['columns'][$discount_type_col_index]['editable'] = false;
			$this->dashboard_model['columns'][$discount_type_col_index]['renderer'] = 'select2Renderer';
			$this->dashboard_model['columns'][$discount_type_col_index]['select2Options'] = array( 
																						'data'=> array(),
																						'dropdownCssClass'=> 'smSelect2Drop',
																	                	// 'allowClear'=> true,
																	                	'width'=> 'resolve' );
			$this->dashboard_model['columns'][$discount_type_col_index]['save_state'] = true;

			$this->dashboard_model['columns'][$discount_type_col_index]['search_values'] = array();
			foreach ($discount_types as $key => $value) {
				$this->dashboard_model['columns'][$discount_type_col_index]['search_values'][] = array('key' => $key, 'value' => $value);
				$this->dashboard_model['columns'][$discount_type_col_index]['select2Options']['data'][] = array('id' => $key, 'text' => $value);
			}
            return $this->llms_dashboard_model();
		}
	}
}
