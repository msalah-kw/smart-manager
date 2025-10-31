<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Settings' ) ) {
	class Smart_Manager_Pro_Settings extends Smart_Manager_Settings {

        function __construct() {
			parent::__construct();
        }

		/**
		 * Function to handle hooks.
		 */
		public static function init(){
			add_filter( 'sm_settings_default', array( 'Smart_Manager_Pro_Settings', 'get_pro_defaults' ) );
			add_filter( 'sm_setting_value', array( 'Smart_Manager_Pro_Settings', 'format_setting_value' ), 10, 2 );
		}

		/**
		 * Function to add pro default settings
		 *
         * @param array $defaults Default settings array
		 * @return array $defaults Updated default settings array
		 */
		public static function get_pro_defaults( $defaults = array() ){
			if( empty( $defaults['general'] ) ){
				return $defaults;
			}

			if( empty( $defaults['general']['toggle'] ) ){
				$defaults['general']['toggle'] = array();
			}

			$defaults['general']['toggle']['show_tasks_title_modal'] = 'yes';

			if( empty( $defaults['general']['image'] ) ){
				$defaults['general']['image'] = array();
			}

			$defaults['general']['image']['company_logo_for_print_invoice'] = 0;
			$defaults['general']['toggle']['delete_media_when_permanently_deleting_post_type_records'] = 'no';
			$defaults['general']['toggle']['generate_sku'] = 'no';
			$defaults['general']['select']['ai_integration_settings'] = array(
				'cohere'=> array('label'=>'Cohere','api_key'=>''),
			);
			return $defaults;
		}

		/**
		 * Function to format value for pro settings
		 *
         * @param string $value Setting value
         * @param array $args Array containing setting meta info.
		 * @return array/string $value updated value
		 */
		public static function format_setting_value( $value = '', $args = array() ){
			if( empty( $value ) || empty( $args['type'] ) || ( ! empty( $args['type'] ) && 'image' !== $args['type'] ) ){
				return $value;	
			}

			if( is_array( $value ) && ! empty( $value['id'] ) ){
				return intval( $value['id'] );
			} else if( !is_array( $value ) ){
				return array( 'id' => intval( $value ),
								'url' => wp_get_attachment_image_url( intval( $value ), 'full' ) );
			}

			return $value;
		}
	}
}
Smart_Manager_Pro_Settings::init();
