<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Access_Privilege' ) ) {
	class Smart_Manager_Pro_Access_Privilege {

		protected static $_instance = null;
		public static $access_privilege_option_start = 'sa_sm_';
		public static $access_privilege_option_end = '_dashboards';
		public $req_params = array();

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		function __construct() {
			$this->req_params  	= ( ! empty( $_REQUEST ) ) ? $_REQUEST : array();
			add_filter( 'sm_active_dashboards', array( $this, 'get_accessible_dashboards' ) );
			add_filter( 'sm_active_taxonomy_dashboards', array( $this, 'get_accessible_dashboards' ) );
		}

		public static function get_db_key( $role = '' ) {
			$start = ( empty( $role ) ) ? rtrim( self::$access_privilege_option_start, '_' ) : self::$access_privilege_option_start;
			return  $start . ( ( ! empty( $role ) ) ? trim( $role ) : '' ) . self::$access_privilege_option_end;
		}

		/**
		 * Function to update the privilege settings data
		 *
		 * @return string json encoded string of results.
		 */
		public function save_access_privilege_settings() {
			global $wpdb;
			$access_privileges = ( ! empty( $this->req_params ) && ! empty( $this->req_params['access_privileges'] ) ) ? json_decode( stripslashes( $this->req_params['access_privileges'] ), true ) : array();
			$results = array();
			if ( empty( $access_privileges ) || ! is_array( $access_privileges ) ) {
				wp_send_json( array( 'msg' => $results, 'ACK' => 'failure' ) );
			}
			foreach ( $access_privileges as $access_privilege ) {
				if ( empty( $access_privilege['rules'] ) || ! is_array( $access_privilege['rules'] ) ) {
					continue;
				}
				foreach ( $access_privilege['rules'] as $rule ) {
					if ( empty( $rule['type'] ) || empty( $rule['operator'] ) || empty( $rule['meta'] ) ) {
						continue;
					}
					if ( ! isset( $results[ $rule['type'] ] ) ) {
						$results[ $rule['type'] ] = array( 'valid' => array(), 'not_valid' => array() );
					}
					if ( isset( $rule['meta']['displayTitles'] ) ) {
						unset( $rule['meta']['displayTitles'] );
					}
					$operator = ( 'has_access' === $rule['operator'] ) ? 'valid' : 'not_valid';
					if ( isset( $rule['meta']['user_roles'] ) && ! empty( $rule['meta']['user_roles'] ) && is_array( $rule['meta']['user_roles'] ) ) {
						foreach ( $rule['meta']['user_roles'] as $slug => $values ) {
							$results[ $rule['type'] ][ $operator ] = $this->set_selected_dashboards( array( 
								'slug' => $slug,
								'values' => $values,
								'results' => $results[ $rule['type'] ][ $operator ]
							));
						}
					}
					if ( isset( $rule['meta']['user_emails'] ) && ! empty( $rule['meta']['user_emails'] ) && is_array( $rule['meta']['user_emails'] ) ) {
						foreach ( $rule['meta']['user_emails'] as $slug => $values ) {
							$results[ $rule['type'] ][ $operator ] = $this->set_selected_dashboards( array( 
								'slug' => $slug,
								'values' => $values,
								'results' => $results[ $rule['type'] ][ $operator ]
							));
						}
					}
					if ( empty( $results[ $rule['type'] ]['valid'] ) && empty( $results[ $rule['type'] ]['not_valid'] ) ) {
						unset( $results[ $rule['type'] ] );
					}
				}
			}
			$delete_settings_data = self::delete_access_settings_data();
			if ( empty( $results ) || ! is_array( $results ) ) {
				wp_send_json( array( 'msg' => $results, 'ACK' => 'failure' ) );
			}
			$update_values = array( 'usermeta' => array(), 'options' => array() );
			foreach ( $results as $key => $value ) {
				if ( is_numeric( $key ) ) {
					$update_values['usermeta'][] = "( ".$key.", '".self::get_db_key()."', '".maybe_serialize( $value ) ."' )";
					continue;
				}
				$update_values['options'][] = "( '". self::get_db_key( $key ) ."', '".maybe_serialize( $value ) ."', 'no' )";
			}
			if ( ! $delete_settings_data || ( empty( $update_values['usermeta'] ) && empty( $update_values['options'] ) ) )
			{
				wp_send_json( array( 'msg' => $results, 'ACK' => ( ! empty( $results ) ? 'success' : 'failure' ) ) );
			}
			$query_result = false;
			if ( ! empty( $update_values['options'] ) ) {
				$query_result = $wpdb->query( "INSERT INTO {$wpdb->prefix}options ( option_name, option_value, autoload ) VALUES ". implode( ", ", $update_values['options'] ) ."" );
			}
			if ( ! empty( $update_values['usermeta'] ) ) {
				$query_result = $wpdb->query( "INSERT INTO {$wpdb->usermeta} ( user_id, meta_key, meta_value ) VALUES ". implode( ", ", $update_values['usermeta'] ) ." ON DUPLICATE KEY UPDATE meta_value = VALUES ( meta_value )" ); 	
			}
			wp_send_json( array( 'msg' => $results, 'ACK' => ( ! empty( $query_result ) && ! is_wp_error( $query_result ) ) ? 'success' : 'failure' ) );
		}

		public static function delete_access_settings_data() {
			global $wpdb;
			$option_result= $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options 
	        							WHERE option_name LIKE %s
	        								AND option_name LIKE %s", '%' . $wpdb->esc_like( self::$access_privilege_option_start ) . '%', '%' . $wpdb->esc_like( self::$access_privilege_option_end ) . '%' ) );
			$usermeta_result= $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta}
	        							WHERE meta_key LIKE %s
	        								", '%' . $wpdb->esc_like( self::get_db_key() ) .'%' ) );
			return ( ! is_wp_error( $option_result ) && ! is_wp_error( $usermeta_result ) ) ? true : false;
		}

		public function get_users() {
			$term = ( ! empty( $this->req_params['term'] ) ) ? sanitize_text_field( wp_unslash( $this->req_params['term'] ) ) : ''; // phpcs:ignore
			$page = ( ! empty( $this->req_params['page'] ) ) ? $this->req_params['page'] : 1; //pagination
			$resultCount = apply_filters( '_sm_ajax_results_per_page', get_option( '_sm_ajax_results_per_page', 50 ) );
			$offset = ( ! empty( $this->req_params['offset'] ) ) ? $this->req_params['offset'] : ( $page - 1 ) * $resultCount;
			$all_roles = get_editable_roles();
			$previous_roles = ( ! empty( $this->req_params['previous_roles'] ) ) ? array_merge( $this->req_params['previous_roles'], array( "administrator" ) ): array( "administrator" );
			if ( ! empty( $previous_roles ) && ! empty( array_diff( array_keys( $all_roles ), $previous_roles ) ) ) {
				foreach ( $previous_roles as $previous_role ) {
					if ( in_array( $previous_role, array_keys( $all_roles ) ) ) {
	        			unset( $all_roles[ $previous_role ] );
	        		}
				}
			}
			if ( empty( $all_roles ) || ! is_array( $all_roles ) ) {
				wp_send_json( array(
					'data' => array( 'results' => array(), 
									'pagination' => array( "more" => false ) 
									),
					'additional_params' => array( 'offset' => 0,
												'previous_roles'=> array() )
							) );
			}
			$previous_roles= array();
			$optgroup = array();
			$users_count = 0;
			$morePages = false;
			foreach ( $all_roles as $role => $value )
			{	

				$all_users = get_users( array(
					'search'    	 => '*' . $term . '*', 
					'search_columns' => array( 'ID', 'user_login', 'user_email', 'display_name' ),
					'fields'		 => array( 'ID', 'display_name', 'user_email'),
					'number'     	 => $resultCount,
					'paged'      	 => $page,
					'offset'    	 => $offset,
					'role'			 => $role
				) );
				$role_title = ( ! empty( $role ) && ! empty( $all_roles[ $role ] ) && ! empty( $all_roles[ $role ]['name'] ) ) ? $all_roles[ $role ]['name'] : $role;
				if ( empty( $all_users ) || ! is_array( $all_users ) ) {
					continue;
				}
				foreach ( $all_users as $user ) {
					if ( empty( $user ) || ( !empty( $user ) && ( empty( $user->ID ) || empty( $user->user_email ) ) ) ) {
						continue;
					}
					if ( ! isset( $optgroup[ $role ] ) ) {
						$optgroup[ $role ] = array( 'text' => $role_title, 'children' => array() );
					}
					$optgroup[ $role ]['children'][] = array(
															'id'   => $user->ID,
															'text' => ( ( ! empty( $user->display_name ) ) ? $user->display_name : '#'.$user->ID ). ' ('.$user->user_email.')'
														);
				}
				$users_count = ( ! empty( $optgroup[ $role ]['children'] ) ) ? count( $optgroup[ $role ]['children'] ) : 0;
				$resultCount -= $users_count;
				if( $resultCount== 0 ){
					$morePages = true;
					break;
				}
				$offset= 0;
				$page= 1;
				$previous_roles[] = $role;
				$morePages = false;
			}	
			wp_send_json( array(
					'data' => array( 'results' => array_values( $optgroup ), 
									'pagination' => array( "more" => $morePages ) 
									),
					'additional_params' => array( 'offset' => $users_count,
												'previous_roles'=> $previous_roles )
							) );
		}

		public function get_user_roles() {
			$optgroup = array();
			$term = ( ! empty( $this->req_params['term'] ) ) ? sanitize_text_field( wp_unslash( $this->req_params['term'] ) ) : '';
			$all_roles = get_editable_roles();
			if ( isset( $all_roles['administrator'] ) ) {
				unset( $all_roles['administrator']);
			}
			if ( empty( $all_roles ) || ! is_array( $all_roles ) ) {
				wp_send_json( array( 'data' => array( 'results' => array() ) ) );
			}
			$groups = array(
				'default' => array(
								'title' => _x( 'Default', 'select group title', 'smart-manager-for-wp-e-commerce' ),
								'roles'	=> array( 'editor', 'author', 'contributor', 'subscriber' ),		
							),
				'woocommerce' => array(
								'title' => _x( 'WooCommerce', 'select group title', 'smart-manager-for-wp-e-commerce' ),
								'roles'	=> array( 'customer', 'shop_manager' ),		
							)
			);
			foreach ( $all_roles as $name => $role ) {
				if ( ! empty( $term ) && false === strpos( $name, $term ) ) {
					continue;
				}
				$group_name = _x( 'Other', 'select group title' ,'smart-manager-for-wp-e-commerce' );
				if ( empty( $groups ) || ! is_array( $groups ) ) {
					continue;
				}
				foreach ( $groups as $group ) {
					if ( ! empty( $group ) && ! empty( $group['roles'] ) && in_array( $name, $group['roles'] ) ) {
						$group_name = $group['title'];
						break;
					}
				}
				if ( ! isset( $optgroup[$group_name] ) ) {
					$optgroup[ $group_name ] = array( 'text' => $group_name, 'children' => array() );
				}
				$optgroup[ $group_name ]['children'][] = array(
															'id'   => $name,
															'text' => ( ! empty( $role['name'] ) ) ? $role['name'] : $name
														);
			}
			wp_send_json( array( 'data' => array( 'results' => array_values( $optgroup ) ) ) );
		}

		public function get_formatted_dashboard_groups( $args = array() ){
			if ( empty( $args ) || ( ! empty( $args ) && ( empty( $args['slug'] ) || empty( $args['title'] ) ) ) ) {
				return $args['data'];
			}
			if ( ! isset( $args['data'] ) ) {
				$args['data'] = array();
			}
			if ( ! isset( $args['data'][$args['slug']] ) ) {
				$args['data'][$args['slug']] = array( 'text' => $args['title'], 'children' => array() );
			}
			if ( ! empty( $args['dashboards'] ) && is_array( $args['dashboards'] ) ) {
				foreach ( $args['dashboards'] as $key => $title ) {
					if ( ! empty( $args['term'] ) && false === strpos( strtolower( $title ), $args['term'] ) ) {
						continue;
					}
					$args['data'][ $args['slug'] ]['children'][] = array(
									'id'   => $key,
									'text' => $title,
									'optgroup' => $args['slug']
								);
				}
			}
			if( empty( $args['data'][$args['slug']]['children'] ) ){
				unset( $args['data'][$args['slug']] );
			}
			return $args['data'];
		}

		public function get_dashboards() {
			$optgroup = array();
			$term = ( ! empty( $this->req_params['term'] ) ) ? sanitize_text_field( wp_unslash( $this->req_params['term'] ) ) : '';
			$post_type_dashboards = ( defined( 'SM_BETA_ALL_DASHBOARDS' ) ) ? json_decode( SM_BETA_ALL_DASHBOARDS, true ) : array();
			if ( ! empty( $post_type_dashboards ) ) {
				$optgroup = $this->get_formatted_dashboard_groups( array( 
					'data' 		 => $optgroup,
					'dashboards' => $post_type_dashboards,
					'slug'		 => 'post_types',
					'title'		 => _x( 'All Post Types', 'select group title', 'smart-manager-for-wp-e-commerce' ),
					'term'		 => $term
				 ) );
			}
			$taxonomy_dashboards = ( defined( 'SM_ALL_TAXONOMY_DASHBOARDS' ) ) ? json_decode( SM_ALL_TAXONOMY_DASHBOARDS, true ) : array();
			if ( ! empty( $taxonomy_dashboards ) ) {
				$optgroup = $this->get_formatted_dashboard_groups( array( 
					'data' 		 => $optgroup,
					'dashboards' => $taxonomy_dashboards,
					'slug'		 => 'taxonomies',
					'title'		 => _x( 'All Taxonomies', 'select group title', 'smart-manager-for-wp-e-commerce' ),
					'term'		 => $term
				 ) );
			}
			if ( class_exists( 'Smart_Manager_Pro_Views' ) && ( ! empty( $post_type_dashboards ) || ! empty( $taxonomy_dashboards ) ) ) {
				$view_obj = Smart_Manager_Pro_Views::get_instance();
				if ( is_callable( array( $view_obj, 'get_all_accessible_views' ) ) ) {
					$views = $view_obj->get_all_accessible_views( array_merge( $post_type_dashboards, $taxonomy_dashboards ) );
					if ( ! empty( $views ) ) {
						$sm_accessible_views = ( ! empty( $views['accessible_views'] ) ) ? $views['accessible_views'] : array();
						$sm_owned_views = ( ! empty( $views['owned_views'] ) ) ? $views['owned_views'] : array();
						$sm_public_views = ( ! empty( $views['public_views'] ) ) ? $views['public_views'] : array();
						$optgroup = $this->get_formatted_dashboard_groups( array( 
							'data' 		 => $optgroup,
							'dashboards' => $sm_accessible_views,
							'slug'		 => 'sm_views',
							'title'		 => _x( 'All saved views', 'select group title', 'smart-manager-for-wp-e-commerce' ),
							'term'		 => $term
						) );
					}
				}
			}
			wp_send_json( array( 'data' => array( 'results' => array_values( $optgroup ) ) ) );
		}

		public static function get_all_privileges() {
			global $wpdb;
			$default_rule = array( 'type' => '', 'operator' => '', 'value' => '', 'meta' => array());
			$user_role_rules = array();
			$user_rules = array();
			// get access privilege for user role.
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT LEFT(SUBSTR(option_name, %d), LOCATE(%s, SUBSTR(option_name, %d)) -1) as user_role,
															option_value as dashboards
															FROM {$wpdb->prefix}options 
															WHERE option_name LIKE %s 
															AND option_name LIKE %s", 
															strlen( self::$access_privilege_option_start ) + 1, 
															self::$access_privilege_option_end,
															strlen( self::$access_privilege_option_start ) + 1,
															$wpdb->esc_like( self::$access_privilege_option_start ) . '%',
															'%' . $wpdb->esc_like( self::$access_privilege_option_end ) ), 'ARRAY_A' );												
			
			// get access privileges for user.
			$um_results = $wpdb->get_results( $wpdb->prepare( "SELECT user_id,
																	meta_value as dashboards  
																FROM {$wpdb->usermeta} 
																WHERE meta_key = %s ", 
															self::get_db_key() ), 'ARRAY_A' );
			$post_type_dashboards = ( class_exists( 'Smart_Manager' ) && is_callable( array( 'Smart_Manager', 'get_dashboards' ) ) ) ? Smart_Manager::get_dashboards() : array();
			$taxonomy_dashboards = ( class_exists( 'Smart_Manager' ) && is_callable( array( 'Smart_Manager', 'get_taxonomies' ) ) ) ? Smart_Manager::get_taxonomies() : array();
			$all_dashboards = array_merge( $post_type_dashboards, $taxonomy_dashboards );
			if ( ! empty( $results ) ) {
				$user_roles = get_editable_roles();
				foreach ( $results as $result ) {
					$field_values = array();
					$role = ( ! empty( $result['user_role'] ) ) ? $result['user_role'] : '';
					$role_title = ( ! empty( $role ) && ! empty( $user_roles[ $role ] ) && ! empty( $user_roles[ $role ]['name'] ) ) ? $user_roles[ $role ]['name'] : $role;
					if ( empty( $role ) ){
						continue;
					}
					$dashboards = ( ! empty( $result['dashboards'] ) ) ? maybe_unserialize( $result['dashboards'] ) : array();
					if ( empty( $dashboards ) || ! is_array( $dashboards ) ) {
						continue;
					}
					$field_values = self::get_field_values( array(
						'dashboards' => $dashboards,
						'all_dashboards' => $all_dashboards
					) );
					if ( empty( $field_values ) || ! is_array( $field_values ) ) {
						continue;
					}
					foreach ( $field_values as $key => $field_value ) {						
						$user_role_rules[] =  array( 'type'=> $role, 'operator'=> ( 'valid' === $key ) ? 'has_access' : 'no_access', 'value'=> $field_value, 'meta' => array( 'displayTitles' => array( 'field' => $role_title ), 'user_roles' => $field_value ) );
					}
				}
			} else {
				$user_role_rules = array( $default_rule );
			}

			if ( ! empty( $um_results ) ) {
				foreach ( $um_results as $um_result ) {
					$operators= array();
					$field_values = array();
					$user_id = ( ! empty( $um_result['user_id'] ) ) ? intval( $um_result['user_id'] ) : '';
					$result_dashboards = ( ! empty( $um_result['dashboards'] ) ) ? $um_result['dashboards'] : '';
					$dashboards = ( ! empty( $result_dashboards ) ) ? maybe_unserialize( $um_result['dashboards'] ) : $result_dashboards;
					if ( empty( $dashboards ) || ! is_array( $dashboards ) ) {
						continue;
					}
					$field_values = self::get_field_values( array(
						'dashboards' => $dashboards,
						'all_dashboards' => $all_dashboards
					) );
					if ( empty( $field_values ) || ! is_array( $field_values ) ) {
						continue;
					}
					foreach( $field_values as $key => $field_value )
					{
						$the_user = get_user_by( 'id', $user_id ); 			
						$user_rules[] =  array( 'type'=> $user_id, 'operator'=> ( 'valid' === $key ) ? 'has_access' : 'no_access', 'value'=> $field_value, 'meta' => array( 'displayTitles' => array( 'field' => $the_user->display_name.' ('.$the_user->user_email.')' ), 'user_emails' => $field_value ) );
					}
				}
			} else {
				$user_rules = array( $default_rule );
			}
			$user_role_dashboard_privileges = array( array( 'condition'=>'AND', 'rules'=> ( ! empty( $user_rules ) ) ? $user_rules : $default_rule ), array('condition'=>'AND', 'rules'=> ( ! empty( $user_role_rules ) ) ? $user_role_rules : $default_rule ) );
			wp_send_json( $user_role_dashboard_privileges );
		}
		
		/**
		* Function to get current user accessible dashboards.
		*
		* return array $final_dashboards final dashboards array
		*/
		public static function get_current_user_access_privilege_settings() {
			$final_dashboards = array();
			$final_result = array();
			$current_user_role = ( is_callable( array( 'Smart_Manager', 'get_current_user_role' ) ) ) ? Smart_Manager::get_current_user_role() : '';
			$current_user_id = get_current_user_id();	
			if ( ( 'administrator' === $current_user_role ) ) return $final_dashboards;
			// query for get current user role's accessible dashboards.
			$get_user_role_accessible_dashboards =  maybe_unserialize( get_option( self::get_db_key( $current_user_role ), '' ) );
			$user_role_accessible_dashboards = ( ! empty( $get_user_role_accessible_dashboards ) ) ? $get_user_role_accessible_dashboards : array();
			// query for get current user's accessible dashboards.
			$get_user_accessible_dashboards = maybe_unserialize( get_user_meta( $current_user_id, self::get_db_key(), true ) );
			$user_accessible_dashboards = ( ! empty( $get_user_accessible_dashboards ) ) ? $get_user_accessible_dashboards : array();

			$valid_user_role_accessible_dashboards = ( ! empty( $user_role_accessible_dashboards['valid'] ) ) ? $user_role_accessible_dashboards['valid'] : array();
			$valid_user_accessible_dashboards = ( ! empty( $user_accessible_dashboards['valid'] ) ) ? $user_accessible_dashboards['valid'] : array();
			$final_result['valid'] = array_merge_recursive( $valid_user_role_accessible_dashboards, $valid_user_accessible_dashboards );

			$not_valid_user_role_accessible_dashboards = ( ! empty( $user_role_accessible_dashboards['not_valid'] ) ) ? $user_role_accessible_dashboards['not_valid'] : array();
			$not_valid_user_accessible_dashboards = ( ! empty( $user_accessible_dashboards['not_valid'] ) ) ? $user_accessible_dashboards['not_valid'] : array();
			$final_result['not_valid'] = array_merge_recursive( $not_valid_user_role_accessible_dashboards, $not_valid_user_accessible_dashboards );
			if ( ! empty ( $user_accessible_dashboards['not_valid'] ) ) {
				foreach ( $user_accessible_dashboards['not_valid'] as $key => $values ) {
					if ( ( empty( $values ) ) || ( empty( $key ) ) || ( ! is_array( $values ) ) ) continue;
					$result = false;
					foreach ( $values as $value ) {
						if ( empty( $value ) || empty( $user_role_accessible_dashboards['valid'][ $key ] ) || ! is_array( $user_role_accessible_dashboards['valid'][ $key ] ) ) continue;
						$result = array_search( $value, $final_result['valid'][ $key ] );
						if ( empty( $result ) ) continue;
						unset( $final_result['valid'][$key][$result] );
					}
				}
			}
			foreach ( $final_result['valid'] as $value ) {	
				if( ( empty( $value ) ) || ( ! is_array( $value ) ) ){
					continue;
				}			
				foreach( $value as $result ) {
					if( empty( $result ) ){
						continue;
					}
					$final_dashboards['valid'][] = $result;
				}				        	
			}
			foreach ( $final_result['not_valid'] as $value ) {		
				if( ( empty( $value ) ) || ( ! is_array( $value ) ) ){
					continue;
				}	
				foreach( $value as $result ) {
					if( empty( $result ) ){
						continue;
					}
					$final_dashboards['not_valid'][] = $result;
				}				        	
			}
           	return $final_dashboards;	
		}

		/**
		* Function to get accessible dashboards.
		*
		* @param  array $dashboards   dashboards array.
		* 
		* @return array $dashboards  updated dashboards array.
		*/
		public function get_accessible_dashboards( $dashboards = array() ) {
			if ( empty( $dashboards ) || ! is_array( $dashboards ) ) {
				return $dashboards;
			}
			$accessible_dashboards = self::get_current_user_access_privilege_settings();
			if ( empty( $accessible_dashboards ) || !is_array( $accessible_dashboards ) ) {
				return $dashboards;
			}
			foreach ( $accessible_dashboards as $accessible_dashboard ) {
	        	foreach ( $dashboards as $key => $dashboard ) {	 
					if (
						empty( $key ) ||
						( ! empty( $accessible_dashboards['valid'] ) && is_array( $accessible_dashboards['valid'] ) && in_array( $key, $accessible_dashboards['valid'], true ) ) ||
						( empty( $accessible_dashboards['valid'] ) && ! empty( $accessible_dashboards['not_valid'] ) && is_array( $accessible_dashboards['not_valid'] ) && ! in_array( $key, $accessible_dashboards['not_valid'], true ) )
					) {
						continue;
					}
					unset( $dashboards[ $key ] );
	        	}
			}
	        if ( empty( $dashboards ) && ! defined( 'SM_BETA_ACCESS' ) ) {
	        	define( 'SM_BETA_ACCESS', false );
	        } else if ( ! empty( $dashboards ) && ! defined( 'SM_BETA_ACCESS' ) ) {
	        	define( 'SM_BETA_ACCESS', true );
	        }
			return $dashboards;
		}

		/**
		* Function to get field values for getting all privileges.
		*
		* @param  array $args dashboard related array.
		* 
		* @return array $field_values field values array.
		*/
		public static function get_field_values( $args = array() ) {
			if ( empty( $args['dashboards'] ) ) {
				return array();
			}
			$field_values = array();
			foreach ( $args['dashboards'] as $key => $values ) {	
				if ( empty( $values ) || ! is_array( $values ) ) {
					continue;
				}
				foreach ( $values as $post_type => $post_type_values )
				{
					if ( empty( $post_type_values ) || ! is_array( $post_type_values ) ) {
						continue;
					}
					foreach ( $post_type_values as $id => $value ) {
						if ( empty( $value ) ) {
							continue;
						}
						$field_values[ $key ][ $post_type ][ $value ] = ( isset( $args['all_dashboards'] ) && ! empty( $args['all_dashboards'] ) && is_array( $args['all_dashboards'] ) && array_key_exists( $value, $args['all_dashboards'] ) ) ? $args['all_dashboards'][ $value ] : $value;
					}
				}
			}
			return $field_values;
		}

		/**
		* Function to set selected dashboards for saving access privilege settings.
		*
		* @param  array $args dashboard related array.
		* 
		* @return array $results results array.
		*/
		public function set_selected_dashboards( $args = array() ) {
			if ( empty( $args['slug'] ) || empty( $args['values'] ) || ! is_array( $args['values'] ) && isset( $args['results'] ) ) {
				return $args['results'];
			}
			$dashboard_slug = array_keys( $args['values'] );
			if ( empty( $dashboard_slug ) ) {
				return $args['results'];
			}
			return array_merge_recursive( $args['results'], array( $args['slug'] => $dashboard_slug ) );
		}
	}

}

$GLOBALS['smart_manager_pro_access_privilege'] = Smart_Manager_Pro_Access_Privilege::instance();
