<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Settings' ) ) {
	class Smart_Manager_Settings {
        
        /**
		 * Singleton class
		 *
		 * @var object
		 */
        protected static $_instance = null;
		
        /**
		 * Database settings option name
		 *
		 * @var string
		 */
        public static $db_option_key = 'sa_sm_settings';

        /**
		 * Saved settings array
		 *
		 * @var array
		 */
        public static $saved_settings = array();

        /**
		 * Instance of the class
		 *
		 * @return object
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Constructor is called when the class is instantiated
		 *
		 * @return void
		 */
		function __construct() {
        }

        /**
		 * Function to get default settings
		 *
		 * @return array Default settings array
		 */
        public static function get_defaults(){
            return apply_filters( 'sm_settings_default', array(
                'general' => array(
                    'toggle' => array(
                        'wp_force_collapse_admin_menu'                  => 'yes',
                        'use_number_field_for_numeric_cols'             => 'yes',
                        'use_date_picker_for_date_or_datetime_cols'     => 'yes',
                        'view_trash_records'                            => 'no',
                        'show_manage_with_smart_manager_button'         => 'yes',
                        'show_smart_manager_menu_in_admin_bar'          => 'yes'
                    ),
                    'numeric' => array(
                        'per_page_record_limit' => 50
                    ),
                    'text'  => array(
                        'grid_row_height' => '50px'
                    )
                )
            ) );
        }

        /**
		 * Function to merge settings
		 *
         * @param array $defaults Default settings array
         * @param array $all_settings Settings array
         * @param string $setting_nm Setting name
		 * @return array/string Merged settings array or specific setting value
		 */
        public static function merge( $defaults = array(), $all_settings = array(), $setting_nm = '' ){
            if( empty( $defaults ) ){
                return ( empty( $setting_nm ) ) ? array() : '';
            }
            
            foreach( $defaults as $group => $settings ){
                if( empty( $settings ) ){
                    continue;
                }
                foreach( $settings as $type => $values ){
                    if( empty( $values ) || ! is_array( $values ) ){
                        continue;
                    }
                    foreach( $values as $setting => $value ){
                        $value = ( ! empty( $all_settings ) && ! empty( $all_settings[$group] ) && ! empty( $all_settings[$group][$type] ) && isset( $all_settings[$group][$type][$setting] ) ) ? $all_settings[$group][$type][$setting] : $value;
                        $defaults[$group][$type][$setting] = apply_filters( 'sm_setting_value', $value, array( 'group' => $group,
                                                                                                                'type'  => $type,
                                                                                                                'setting' => $setting ) );
                    }
                    if( ! empty( $setting_nm ) && isset( $defaults[$group][$type][$setting_nm] ) ){
                        return $defaults[$group][$type][$setting_nm];
                    }
                }
            }
            return ( empty( $setting_nm ) ) ? $defaults : '';
        }

        /**
		 * Function to get saved settings
		 *
         * @param string $setting_nm Setting name
		 * @return array/string Settings array or specific setting value
		 */
        public static function get( $setting_nm = '' ){
            self::$saved_settings = ( empty( self::$saved_settings ) ) ? get_option( self::$db_option_key, array() ) : self::$saved_settings;
            return self::merge( self::get_defaults(), self::$saved_settings, $setting_nm );
        }

        /**
		 * Function to update settings
		 *
         * @param array $settings Settings array
		 * @return boolean Flag to determine whether the update was successful or not
		 */
        public static function update( $settings = array() ){
            if( empty( $settings ) ){
                return false;
            }
            $default_settings = self::get_defaults();
            if( empty( $default_settings ) || ( ! empty( $default_settings ) && empty( $default_settings['general'] ) ) || empty( $settings['general'] ) ){
                return false;
            }
            return update_option( self::$db_option_key, self::merge( $default_settings, $settings ), 'no' );
            
        }
	}
}
