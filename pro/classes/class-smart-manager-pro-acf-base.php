<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_ACF_Base' ) ) {
	class Smart_Manager_Pro_ACF_Base extends Smart_Manager_Pro_Base {
        public $dashboard_key = '';

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
            add_filter('sa_sm_dashboard_model',array(&$this,'get_acf_column_model'),12,2);
			add_filter('sm_data_model',array(&$this,'get_acf_column_data'),12,2);
		}

        public function get_data_type( $type ){
            
            $data_types = array( 
                                'true_false'        => 'checkbox',
                                'select'            => 'dropdown',
                                'date_picker'       => 'sm.date',
                                'time_picker'       => 'sm.time',
                                'date_time_picker'  => 'sm.datetime',
                                'image'             => 'sm.image',
                                'gallery'           => 'sm.multipleImage',
                                'wysiwyg'           => 'sm.longstring',
                                'checkbox'          => 'sm.multilist'
            );

            return ( ( ! empty( $data_types[ $type ] ) ) ? $data_types[ $type ] : 'text' );
        }

        public function get_acf_column_model( $dashboard_model, $dashboard_model_saved ) {
			global $wpdb;

            $fields = $this->get_field_groups();

            if( empty( $fields ) ){
                return $dashboard_model;    
            }

            $column_model = &$dashboard_model['columns'];

            foreach( $fields as $field ){

                $name = ( ! empty( $field['name'] ) ) ? $field['name'] : '';

                if( empty( $name ) ){
                    continue;
                }
                
                $src = 'postmeta/meta_key='. $name .'/meta_value='. $name;
                $title = ( ( ! empty( $field['label'] ) ) ? $field['label'] : $name );
                $title = __( ucwords( $title ), 'smart-manager-for-wp-e-commerce' );
                $type = ( ( ! empty( $field['type'] ) ) ? $this->get_data_type( $field['type'] ) : 'text' );

                // Code to check if field exists in column_model.
                $index = sa_multidimesional_array_search( $src, 'src', $column_model );
                if( $index >= 0 ){
                    unset( $column_model[$index] );
                    
                    // Code to remove the key columns for the same fields to avoid confusion.
                    $key_src = 'postmeta/meta_key=_'. $name .'/meta_value=_'. $name;
                    $key_index = sa_multidimesional_array_search( $key_src, 'src', $column_model );
                    if( $key_index >= 0 ){
                        unset( $column_model[$key_index] );
                    }

					$column_model = array_values( $column_model ); //added for recalculating the indexes of the array.
                }

                // insert field in column_model
                $index = sizeof( $column_model );

                $column_model [$index] = array( 
                                                'src'   => $src,
				                                'data'  => sanitize_title( str_replace( array('/','='), '_', $src ) ),
				                                'name'  => $title,
				                                'key'   => $title,
                                                'type'  => $type,
                                                'editor'    => $type,
				                                'hidden' => true,
                                                'editable' => true,
                                                'batch_editable' => true,
                                                'sortable' => true,
                                                'resizable' => true,
                                                'allow_showhide' => true,
                                                'exportable' => true,
                                                'searchable' => true,
                                                'wordWrap' => false, //For disabling word-wrap
                                                'table_name' => $wpdb->prefix.'postmeta',
                                                'col_name' => $name,
                                                'width' => 0,
                                                'save_state' => true,
                                                'values' => ( ( ! empty( $field['values'] ) ) ? $field['values'] : array() ),
                                                'search_values' => array(),
                );

                if( !empty( $column_model [$index]['values'] ) ) {
                    foreach( $column_model [$index]['values'] as $key => $value ) {
                        $column_model [$index]['search_values'][] = array( 'key' => $key, 'value' => $value );
                    }
                }

                if( 'dropdown' === $column_model [$index]['type'] ){
                    $column_model [$index]['editor'] = 'select';
                    $column_model [$index]['selectOptions'] = $column_model [$index]['values'];
                }
            }

            if (!empty($dashboard_model_saved)) {
				$col_model_diff = sa_array_recursive_diff( $dashboard_model_saved,$dashboard_model );	
			}

			//clearing the transients before return
			if (!empty($col_model_diff)) {
				delete_transient( 'sa_sm_'.$this->dashboard_key );	
			}

            return $dashboard_model;
        }

        public function get_acf_column_data( $data_model, $data_col_params ) {
			global $wpdb;

            return $data_model;
        }

        public function get_field_groups() {

            if( ! is_callable( 'acf_get_field_groups' ) ){
                return array();
            }

            add_filter( 'acf/location/rule_match/user_type', '__return_true', 16 );
            add_filter( 'acf/location/rule_match/page_type', '__return_true', 16 );
    
            switch ( $this->dashboard_key ) {
                case 'post' :
                    add_filter( 'acf/location/rule_match/post_format', '__return_true', 16 );
                    break;
                case 'page' :
                    add_filter( 'acf/location/rule_match/page', '__return_true', 16 );
                    add_filter( 'acf/location/rule_match/page_parent', '__return_true', 16 );
                    add_filter( 'acf/location/rule_match/page_template', '__return_true', 16 );
                    break;
            }
    
            add_filter( 'acf/location/rule_match/post', '__return_true', 16 );
            add_filter( 'acf/location/rule_match/post_category', '__return_true', 16 );
            add_filter( 'acf/location/rule_match/post_status', '__return_true', 16 );
            add_filter( 'acf/location/rule_match/post_taxonomy', '__return_true', 16 );
            add_filter( 'acf/location/rule_match/post_template', '__return_true', 16 );
    
            $groups = acf_get_field_groups( [ 'post_type' => $this->dashboard_key ] );
    
            remove_filter( 'acf/location/rule_match/user_type', '__return_true', 16 );
            remove_filter( 'acf/location/rule_match/page_type', '__return_true', 16 );
    
            remove_filter( 'acf/location/rule_match/post_format', '__return_true', 16 );
    
            remove_filter( 'acf/location/rule_match/page', '__return_true', 16 );
            remove_filter( 'acf/location/rule_match/page_parent', '__return_true', 16 );
            remove_filter( 'acf/location/rule_match/page_template', '__return_true', 16 );
            remove_filter( 'acf/location/rule_match/post_template', '__return_true', 16 );
    
            remove_filter( 'acf/location/rule_match/post', '__return_true', 16 );
            remove_filter( 'acf/location/rule_match/post_category', '__return_true', 16 );
            remove_filter( 'acf/location/rule_match/post_status', '__return_true', 16 );
            remove_filter( 'acf/location/rule_match/post_taxonomy', '__return_true', 16 );

            return $this->get_fields( $groups );
        }

        protected function get_fields( $groups ) {
            $fields = array();
    
            if( !is_callable( 'acf_get_fields' ) ){
                return $fields;
            }

            foreach ( $groups as $group_id => $group ) {
                
                $acf_fields = acf_get_fields( $group );

                foreach ( $acf_fields as $field ) {
                    if ( in_array( $field['type'], [ 'tab', 'message' ] ) ) {
                        continue;
                    }
    
                    // Clone is not supported
                    if ( isset( $field['_clone'] ) ) {
                        continue;
                    }

                    $name = ( ! empty( $field['name'] ) ) ? $field['name'] : '';

                    if( empty( $name ) ){
                        continue;
                    }
    
                    $fields[ $field['key'] ] = array( 
                                                        'name'          => $name,
                                                        'label'         => ( ( ! empty( $field['label'] ) ) ? $field['label'] : $name ),
                                                        'type'          => ( ( ! empty( $field['type'] ) ) ? $field['type'] : '' ),
                                                        'values'        => ( ( ! empty( $field['choices'] ) ) ? $field['choices'] : array() ),
                                                        'group_title'   => ( ( ! empty( $group['title'] ) ) ? $group['title'] : '' ),
                    );
                }
            }
    
            return $fields;
        }
    }
}
