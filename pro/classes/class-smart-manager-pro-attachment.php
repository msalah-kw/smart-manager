<?php
/**
 * Smart Manager Pro - Attachment Handler
 *
 * @package Smart_Manager_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Smart_Manager_Pro_Attachment
 *
 * Handles attachment-related data for Smart Manager Pro.
 */
if ( ! class_exists( 'Smart_Manager_Pro_Attachment' ) ) {

	class Smart_Manager_Pro_Attachment extends Smart_Manager_Pro_Base {

		/**
		 * Dashboard key identifier.
		 *
		 * @var string
		 */
		public $dashboard_key = '';

		/**
		 * Request parameters.
		 *
		 * @var array
		 */
		public $req_params = array();

		/**
		 * Plugin path.
		 *
		 * @var string
		 */
		public $plugin_path = '';

		/**
		 * Singleton instance.
		 *
		 * @var Smart_Manager_Pro_Attachment|null
		 */
		protected static $instance = null;

		/**
		 * Get singleton instance.
		 *
		 * @param string $dashboard_key Dashboard key.
		 * @return Smart_Manager_Pro_Attachment
		 */
		public static function instance( $dashboard_key ) {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self( $dashboard_key );
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @param string $dashboard_key Dashboard key.
		 */
		public function __construct( $dashboard_key ) {
			$this->dashboard_key = $dashboard_key;
			$this->req_params = ( ! empty( $_REQUEST ) ) ? $_REQUEST : array();
			parent::__construct( $dashboard_key );
			$this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
			add_filter( 'sm_data_model', array( $this, 'data_model' ), 99, 2 );
			add_filter( 'sa_sm_dashboard_model', array( $this, 'dashboard_model' ), 99, 2 );
		}

		/**
		 * Extract file names from attachment meta.
		 *
		 * @param array $data_model     The data model array.
		 * @param array $data_col_params Column parameter data.
		 * @return array Modified data model.
		 */
		public function data_model( $data_model = array(), $data_col_params = array() ) {
			if ( empty( $data_model ) || ! is_array( $data_model ) || empty( $data_model['items'] ) ) {
				return $data_model;
			}
			foreach ( $data_model['items'] as &$item ) {
				if ( ( empty( $item ) ) || ( ! is_array( $item ) ) || ( empty( $item['postmeta_meta_key__wp_attached_file_meta_value__wp_attached_file'] ) )) {
					continue;
				}
				// Convert path to just filename (e.g., 2025/05/image.jpg → image.jpg).
				$item['postmeta_meta_key__wp_attached_file_meta_value__wp_attached_file'] = basename(
					$item['postmeta_meta_key__wp_attached_file_meta_value__wp_attached_file']
				);
			}
			return $data_model;
		}

		/**
		 * Disable edit for file names.
		 *
		 * @param array $store_model     The store model array.
		 * @param array $store_model_transient Store modal transient data.
		 * @return array Modified store model.
		 */
		public function dashboard_model( $store_model = array(), $store_model_transient = array() ) {
			if ( ( empty( $store_model ) ) || ( ! is_array( $store_model ) ) || ( empty( $store_model['columns'] ) ) ) {
				return $store_model;
			}
			foreach ( $store_model['columns'] as &$column ) {
				if ( ( ! is_array( $column ) ) || ( empty( $column['data'] ) ) || ( 'postmeta_meta_key__wp_attached_file_meta_value__wp_attached_file' !== $column['data'] ) ) {
					continue;
				}
				$column['editor'] = false;
				$column['batch_editable'] = false;
			}
			return $store_model;
		}
	}
}
