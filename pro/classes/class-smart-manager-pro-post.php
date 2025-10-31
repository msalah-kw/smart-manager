<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Post' ) ) {
	class Smart_Manager_Pro_Post extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
				$plugin_path = '';

		protected static $_instance = null;
		public $post = '';


		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			parent::__construct($dashboard_key);

			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );

			if ( file_exists(SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-post.php') ) {
				include_once SM_PLUGIN_DIR_PATH . '/classes/class-smart-manager-post.php';
				$this->post = new Smart_Manager_Post( $dashboard_key );
			}
		}

		public static function actions() {

		}

		public function __call( $function_name, $arguments = array() ) {

			if( empty( $this->post ) ) {
				return;
			}

			if ( ! is_callable( array( $this->post, $function_name ) ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $this->post, $function_name ), $arguments );
			} else {
				return call_user_func( array( $this->post, $function_name ) );
			}
		}


	}

}