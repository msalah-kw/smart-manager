<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_WC_User_Membership' ) ) {
	class Smart_Manager_Pro_WC_User_Membership extends Smart_Manager_Pro_Base {
		public $dashboard_key = '',
				$plugin_path = '';

		protected static $_instance = null;

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {

			parent::__construct($dashboard_key);
			$this->hooks();

			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();

			$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );

		}

		public static function actions() {
		}

		public function hooks() {
			add_filter('sa_sm_dashboard_model',array(&$this,'wc_user_membership_dashboard_model'),10,2);
			add_filter('sm_data_model',array(&$this,'wc_user_membership_data_model'),10,2);
			add_filter( 'sm_before_search_string_process', array( $this, 'map_plan_name_search_operators' ), 12, 1 );
		}

		public static function generate_members_custom_column_model( $column_model ) {

			$custom_columns = array( 'name', 'email', 'plan' );
			$index = sizeof($column_model);

			foreach( $custom_columns as $col ) {

				$src = 'custom/'.$col;

				$col_index = sa_multidimesional_array_search ($src, 'src', $column_model);

				if( empty( $col_index ) ) {
					$column_model [$index]                   = array();
					$column_model [$index]['src']            = $src;
					$column_model [$index]['data']           = sanitize_title(str_replace('/', '_', $column_model [$index]['src'])); // generate slug using the wordpress function if not given
					$column_model [$index]['name']           = __(ucwords(str_replace('_', ' ', $col)), 'smart-manager-for-wp-e-commerce');
					$column_model [$index]['key']            = $column_model [$index]['name'];
					$column_model [$index]['type']           = 'text';
					$column_model [$index]['hidden']         = false;
					$column_model [$index]['editable']       = false;
					$column_model [$index]['editor']         = false;
					$column_model [$index]['batch_editable'] = false;
					$column_model [$index]['sortable']       = true;
					$column_model [$index]['resizable']      = true;
					$column_model [$index]['allow_showhide'] = true;
					$column_model [$index]['exportable']     = true;
					$column_model [$index]['searchable']     = ( 'plan' === $col ) ? true : false;
					$column_model [$index]['save_state']     = true;
					$column_model [$index]['values']         = array();
					$column_model [$index]['search_values']  = array();
					$index++;
				}
			}

			return $column_model;
		}

		public function wc_user_membership_dashboard_model ($dashboard_model, $dashboard_model_saved) {
			global $wpdb;
			$visible_columns = array( 'ID', 'name', 'email', 'plan', 'post_date', 'post_status', '_start_date', '_end_date', '_renewal_login_token', 'link' );

			$readonly_columns = array( 'name', 'email', 'plan', 'link' );

			$numeric_columns = array( 'id', 'post_author', 'post_parent', 'menu_order', 'comment_count', '_edit_last', 'post_id' );
			$datetime_columns = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt', '_end_date', '_start_date' );

			$post_status_col_index = sa_multidimesional_array_search('posts_post_status', 'data', $dashboard_model['columns']);
			$user_membership_statuses = $this->get_user_membership_statuses();
			$order_statuses_keys = ( !empty( $user_membership_statuses ) ) ? array_keys($user_membership_statuses) : array();

			$dashboard_model['columns'][$post_status_col_index]['defaultValue'] = ( !empty( $user_membership_statuses_keys[0] ) ) ? $user_membership_statuses_keys[0] : 'wc-pending';

			$dashboard_model['columns'][$post_status_col_index]['save_state'] = true;
			$dashboard_model['columns'][$post_status_col_index]['values'] = $user_membership_statuses;
			$dashboard_model['columns'][$post_status_col_index]['selectOptions'] = $user_membership_statuses; //for inline editing

			$color_codes = array( 'green' => array( 'wcm-active', 'wcm-free_trial', 'wcm-complimentary' ),
									'red' => array( 'wcm-expired', 'wcm-cancelled' ),
									'orange' => array( 'wcm-delayed', 'wcm-pending', 'wcm-paused' ) );

			$dashboard_model['columns'][$post_status_col_index]['colorCodes'] = apply_filters( 'sm_'.$this->dashboard_key.'_status_color_codes', $color_codes );

			$dashboard_model['columns'][$post_status_col_index]['search_values'] = array();
			foreach ($user_membership_statuses as $key => $value) {
				$dashboard_model['columns'][$post_status_col_index]['search_values'][] = array('key' => $key, 'value' => $value);
			}

			if( is_callable( array( 'Smart_Manager_Pro_WC_User_Membership', 'generate_members_custom_column_model' ) ) ) {
				$dashboard_model['columns'] = self::generate_members_custom_column_model( $dashboard_model['columns'] );
			}

			$column_model = &$dashboard_model['columns'];

			foreach ( $column_model as $key => &$column ) {
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

				if ( empty( $dashboard_model_saved ) ) {
					//Code for unsetting the position for hidden columns
					if ( ! empty( $column['position'] ) ) {
						unset( $column['position'] );
					}

					$position = array_search( $src, $visible_columns );

					if ( $position !== false ) {
						$column['position'] = $position + 1;
						$column['hidden'] = false;
					} else {
						$column['hidden'] = true;
					}
				}

				if ( ! empty( $src ) ) {
					if ( 'plan' === $src ) {
						$column['type'] = 'dropdown';
						$column['values'] = array();
						$column['table_name'] = $wpdb->prefix.'custom';
						$column['col_name'] = 'plan';
					}
					if ( empty( $dashboard_model_saved ) ) {
						if ( in_array( $src, $numeric_columns ) ) {
							$column['type'] = 'numeric';
							$column['editor'] = 'customNumericEditor';
						} elseif ( in_array( $src, $datetime_columns ) ) {
							$column['type'] = 'sm.datetime';
							$column['editor'] = $column['type'];
						} elseif ( in_array( $src, $readonly_columns ) ) {
							$column['editor'] = false;
						}
						switch ( $src ) {
							case '_start_date':
								$column['name'] = __( 'Member since', 'smart-manager-for-wp-e-commerce' );
								$column['key'] = __( 'Member since', 'smart-manager-for-wp-e-commerce' );
								break;

							case '_end_date':
								$column['name'] = __( 'Expires' , 'smart-manager-for-wp-e-commerce');
								$column['key'] = __( 'Expires' , 'smart-manager-for-wp-e-commerce');
								break;

						}
					}
				}

			}

			if (!empty($dashboard_model_saved)) {
				$col_model_diff = sa_array_recursive_diff( $dashboard_model_saved, $dashboard_model );
			}

			//clearing the transients before return
			if (!empty($col_model_diff)) {
				delete_transient( 'sa_sm_'.$this->dashboard_key );
			}

			return $dashboard_model;
		}

		public static function generate_members_custom_column_data( $data_model, $params ) {

			global $wpdb;

			$query = "SELECT ID, post_author, post_parent
						FROM {$wpdb->posts}
							WHERE 1";

			$member_ids = ( ! empty( $data_model['items'] ) ) ? wp_list_pluck( $data_model['items'], 'posts_id' ) : array();

			$how_many      = count( $member_ids );
			$placeholders  = array_fill( 0, $how_many, '%d' );
			$query        .= $wpdb->prepare( ' AND ID IN ( ' . implode( ',', $placeholders ) . ' )', $member_ids ); // phpcs:ignore

			$results = $wpdb->get_results( $query, ARRAY_A );

			$membership_to_user = ( ! empty( $results ) ) ? wp_list_pluck( $results, 'post_author', 'ID' ) : array();
			$membership_to_plan = ( ! empty( $results ) ) ? wp_list_pluck( $results, 'post_parent', 'ID' ) : array();

			$user_ids = array_unique( array_values( $membership_to_user ) );
			$plan_ids = array_unique( array_values( $membership_to_plan ) );

			$authors_data = self::get_authors_data( $user_ids );
			$plan_data    = self::get_membership_plan_data( $plan_ids );

			if ( !empty( $data_model['items'] ) ) {
            	foreach( $data_model['items'] as $key => $member_data ) {
            		$member_id = ( ! empty( $member_data['posts_id'] ) ) ? $member_data['posts_id'] : 0;

					$data_model['items'][ $key ]['custom_name']  = ( ! empty( $authors_data[ $membership_to_user[ $member_id ] ]['name'] ) ) ? $authors_data[ $membership_to_user[ $member_id ] ]['name'] : '';
					$data_model['items'][ $key ]['custom_email'] = ( ! empty( $authors_data[ $membership_to_user[ $member_id ] ]['email'] ) ) ? $authors_data[ $membership_to_user[ $member_id ] ]['email'] : '';
					$data_model['items'][ $key ]['custom_plan']  = ( ! empty( $plan_data[ $membership_to_plan[ $member_id ] ]['plan_name'] ) ) ? $plan_data[ $membership_to_plan[ $member_id ] ]['plan_name'] : '';
            	}
            }

			return $data_model;
		}

		public function wc_user_membership_data_model( $data_model, $data_col_params ) {
			if( is_callable( array( 'Smart_Manager_Pro_WC_User_Membership', 'generate_members_custom_column_data' ) ) ) {
				$data_model = self::generate_members_custom_column_data( $data_model, $this->req_params );
			}

			return $data_model;
		}

		public function get_user_membership_statuses() {
			$user_membership_statuses = ( function_exists( 'wc_memberships_get_user_membership_statuses' ) ) ? wc_memberships_get_user_membership_statuses() : array();
			$statuses = array();
			if ( ! empty( $user_membership_statuses ) ) {
				foreach ( $user_membership_statuses as $slug => $status ) {
					$statuses[ $slug ] = $status['label'];
				}
			}
			return $statuses;
		}

		public static function get_authors_data( $user_ids = array() ) {

			$authors_data = array();

			if ( ! empty( $user_ids ) ) {
				global $wpdb;

				$query = "SELECT ID AS user_id,
								display_name AS name,
								user_email AS email
							FROM {$wpdb->users}
							WHERE 1";

				$how_many      = count( $user_ids );
				$placeholders  = array_fill( 0, $how_many, '%d' );
				$query        .= $wpdb->prepare( ' AND ID IN ( ' . implode( ',', $placeholders ) . ' )', $user_ids ); // phpcs:ignore

				$results = $wpdb->get_results( $query, ARRAY_A );

				if ( ! empty( $results ) ) {
					foreach ( $results as $result ) {
						if ( empty( $authors_data[ $result['user_id'] ] ) || ! is_array( $authors_data[ $result['user_id'] ] ) ) {
							$authors_data[ $result['user_id'] ] = array();
						}
						$authors_data[ $result['user_id'] ]['name']  = ( ! empty( $result['name'] ) ) ? $result['name'] : '';
						$authors_data[ $result['user_id'] ]['email'] = ( ! empty( $result['email'] ) ) ? $result['email'] : '';
					}
				}

			}

			return $authors_data;
		}

		public static function get_membership_plan_data( $plan_ids = array() ) {

			$plan_data = array();

			if ( ! empty( $plan_ids ) ) {
				global $wpdb;

				$query = "SELECT ID AS plan_id,
								post_title AS plan_name
							FROM {$wpdb->posts}
							WHERE 1";

				$how_many      = count( $plan_ids );
				$placeholders  = array_fill( 0, $how_many, '%d' );
				$query        .= $wpdb->prepare( ' AND ID IN ( ' . implode( ',', $placeholders ) . ' )', $plan_ids ); // phpcs:ignore

				$results = $wpdb->get_results( $query, ARRAY_A );

				if ( ! empty( $results ) ) {
					foreach ( $results as $result ) {
						if ( empty( $plan_data[ $result['plan_id'] ] ) || ! is_array( $plan_data[ $result['plan_id'] ] ) ) {
							$plan_data[ $result['plan_id'] ] = array();
						}
						$plan_data[ $result['plan_id'] ]['plan_name'] = ( ! empty( $result['plan_name'] ) ) ? $result['plan_name'] . ' (#' . $result['plan_id'] . ')' : '';
					}
				}

			}

			return $plan_data;

		}

		/**
		 * Update rule type and operator for custom 'plan' field in advanced search.
		 *
		 * Converts 'custom.plan' type to 'posts.post_parent'.
		 *
		 * @param array $rule_group Search rule group.
		 * @return array Modified rule group.
		 */
		public function map_plan_name_search_operators( $rule_group = array() ) {
			if ( ( empty( $rule_group ) ) || ( ! is_array( $rule_group ) ) ) {
				return $rule_group;
			}
			global $wpdb;
			foreach ( $rule_group['rules'] as &$rule ) {
				if ( ( empty( $rule ) ) || ( ! is_array( $rule ) ) || ( empty( $rule['operator'] ) ) || ( empty( $rule['type'] ) ) || ( $wpdb->prefix.'custom.plan' !== $rule['type'] ) ) {
					continue;
				}
				$rule['type'] = $wpdb->prefix.'posts.post_parent';
				$rule['operator'] = ( 'is' === $rule['operator'] ) ? 'eq' : 'neq';
			}
			return $rule_group;
		}
	}

}
