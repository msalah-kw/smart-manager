<?php
/**
 * Smart Manager custom views
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;  // Exit if accessed directly.
}

/**
 * Class for handling in app offer for StoreApps
 */
class Smart_Manager_Pro_Views {

    /**
     * Variable to hold instance of this class
     *
     * @var $instance
     */
    private static $instance = null;
    public $req_params = array();

	function __construct() {
		$this->req_params = (!empty($_REQUEST)) ? $_REQUEST : array();
		$this->check_if_table_exists();
	}

    /**
	 * Get single instance of this class
	 *
	 * @param array $args Configuration.
	 * @return Singleton object of this class
	 */
	public static function get_instance() {
		// Check if instance is already exists.
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
    }

    /**
	 * Function to insert custom views
	 *
	 * @param array $args Configuration.
	 * @return int $inserted_id ID of the inserted view
	 */
	public function is_view_available( $params = array() ) {
		global $wpdb;
	
		// Set default response.
		$response = array( 'ACK' => 'Failed', 'is_available' => false );
		$name = ( ! empty( $this->req_params['name'] ) ) ? sanitize_title( wp_unslash( $this->req_params['name'] ) ) : '';
		if ( empty( $name ) ) {
			wp_send_json( $response );
		}
		// Default values.
		$defaults = array(
			'name'              => $name,
			'view_types'        => array( '0', '1' ),
			'is_return_bool_value' => false,
			'and_clause'        => array(),
		);
		// Merge defaults with function params.
		$params = array_merge( $defaults, $params );
		if( empty( $params ) ){
			wp_send_json( $response );
		}
		// Ensure view_types is an array and sanitize.
		$view_types = ( ( ! empty( $params['view_types'] ) ) && ( is_array( $params['view_types'] ) ) ) ? $params['view_types'] : array( '0', '1' );
		$types = implode( "', '", array_map( 'esc_sql', $view_types ) );
		// Base query
		$query = "SELECT COUNT(id) FROM {$wpdb->prefix}sm_views WHERE slug = %s AND type IN ( '$types' )";
		// Add additional AND conditions from `and_clause`.
		$query_conditions = array();
		$query_values = array( $name );
		if ( ! empty( $params['and_clause'] ) && is_array( $params['and_clause'] ) ) {
			foreach ( $params['and_clause'] as $key => $value ) {
				$query_conditions[] = esc_sql( $key ) . " = %s";
				$query_values[] = sanitize_text_field( $value );
			}
		}
		// Append AND conditions if any.
		if ( ! empty( $query_conditions ) ) {
			$query .= ' AND ' . implode( ' AND ', $query_conditions );
		}
		// Execute query.
		$row_count = $wpdb->get_var( $wpdb->prepare( $query, ...$query_values ) );
		if ( ! empty( $params['is_return_bool_value'] ) ) {
			return ! empty( $row_count );
		}
		$response = array( 'ACK' => 'Success', 'is_available' => empty( $row_count ) );
		wp_send_json( $response );
	}
	

    /**
	 * Function to insert custom views
	 *
	 * @return int $inserted_id ID of the inserted view
	 */
	public function create() {
		global $wpdb;

		$response = array( 'ACK' => 'Failed' );

		$title = ( ! empty( $this->req_params['name'] ) ) ? sanitize_text_field( wp_unslash( $this->req_params['name'] ) ) : '';
		$slug = sanitize_title( $title );
		$view_state = ( ! empty( $this->req_params['currentView'] ) ) ? $this->req_params['currentView'] : '';
		$active_module = ( ! empty( $this->req_params['active_module'] ) ) ? $this->req_params['active_module'] : '';
		$is_view = ( ( ! empty( $this->req_params['is_view'] ) ) ? 1 : 0 );
		if( ! empty( $is_view ) && ! empty( $active_module ) ) {
			$active_module = $this->get_post_type( $active_module );
		}

		if( empty( $title ) || ( empty( $view_state ) && empty( $this->req_params['bulk_edit_params'] ) ) || empty( $active_module ) ) {
			$response[ 'msg' ] = _x( 'Required params missing.', 'create view response message', 'smart-manager-for-wp-e-commerce' );
		}
		$view_data = array();
		$view_json = array();
		//condition for saved bulk edits.
		if( ( ! empty( $this->req_params['bulk_edit_params'] ) ) ){
			if( $this->is_view_available( array( 'is_return_bool_value' => true, 'view_types' => array( '2' ), 'and_clause' => array( 'post_type' => $active_module, 'author' => get_current_user_id() ) ) ) ){
				$response[ 'msg' ] = _x( 'Saved action with this name already exists', 'create view response message', 'smart-manager-for-wp-e-commerce' );

				wp_send_json( $response );
			}
			$view_json = json_decode( stripslashes( $this->req_params['bulk_edit_params'] ), true );
			$view_data['view_type'] = '2';
		}else{
			if( $this->is_view_available( array( 'is_return_bool_value' => true ) ) ){
				$response[ 'msg' ] = _x( 'View already exists. Please try another name', 'create view response message', 'smart-manager-for-wp-e-commerce' );

				wp_send_json( $response );
			}
			// Code to map view state to required structure
			$view_data = $this->get_view_data_to_save( json_decode( stripslashes( $view_state ), true ), $this->req_params );
			if ( ( empty( $view_data ) ) || ( ! is_array( $view_data ) ) || ( empty( $view_data['view_state'] ) ) || ( ! isset( $view_data['view_type'] ) ) || ( ! in_array( $view_data['view_type'], array( "0", "1" ) ) ) ) {
				return;
			}
			$view_state = $view_data['view_state'];
			$view_json = sa_sm_generate_column_state( $view_state );
	
			if( ! empty( $view_state['search_params'] ) ) {
				$view_json['search_params'] = $view_state['search_params'];
			}
		}

		$wpdb->query(
				$wpdb->prepare(
								"INSERT INTO {$wpdb->prefix}sm_views(author, title, slug, params, is_public, post_type, type, created_date, modified_date)
								VALUES(%d, %s, %s, %s, %d, %s, %s, %d, %d)",
								get_current_user_id(),
								$title,
								$slug,
								json_encode( $view_json ),
								( ( ! empty( $this->req_params['isPublic'] ) && 'false' != $this->req_params['isPublic'] ) ? 1 : 0 ),
								$active_module,
								sanitize_text_field( $view_data['view_type'] ),
								time(),
								time()
				)
		);

		$insert_id = $wpdb->insert_id;

		if( ! is_wp_error( $insert_id ) ) {
			$response['ACK'] = 'Success';
			$response['id'] = $insert_id;
			$response['slug'] = $slug;
		}

		wp_send_json( $response );
    }

	/**
	 * Function to get view post_type based on slug
	 *
	 * @param string $slug view slug name for which the data is to be fetched.
	 * @return array $post_type post_type that the view is linked to.
	 */
	public function get_post_type( $slug = '' ) {
		global $wpdb;
		$post_type = '';

		if( empty( $slug ) ){
			return $post_type;
		}

		$post_type = $wpdb->get_var(
					$wpdb->prepare(
									"SELECT post_type
										FROM {$wpdb->prefix}sm_views
										WHERE slug = %s",
									$slug
					)
				);
		
		return $post_type;
	}

	/**
	 * Function to get view data based on slug or all views if slug is blank
	 *
	 * @param string $slug view slug name for which the data is to be fetched.
	 * @return array $data array containing the view data or list of all views
	 */
	public function get( $slug = '' ) {
		global $wpdb;
		$data = array();

		if( empty( $slug ) ){ // TODO: improve later
			$data = $wpdb->get_results(
				$wpdb->prepare(
								"SELECT title,
										slug
									FROM {$wpdb->prefix}sm_views
									WHERE 1=%d",
								1
				),
				'ARRAY_A'
			);
		} else {
			$data = $wpdb->get_row(
				$wpdb->prepare(
								"SELECT title,
										params,
										post_type
									FROM {$wpdb->prefix}sm_views
									WHERE slug = %s",
								$slug
				),
				'ARRAY_A'
			);
		}

		return $data;
	}

	/**
	 * Function to get eligible user id for editing columns in custom view
	 */
	public function is_view_author() {	
		global $wpdb;
		$slug = ( ! empty( $this->req_params['slug'] ) ) ? sanitize_title( wp_unslash( $this->req_params['slug'] ) ) : '';
		if( empty( $slug ) )
		{
			wp_send_json( false );
		}

		$row_count = $wpdb->get_var(
						$wpdb->prepare("SELECT COUNT(id) 
										FROM {$wpdb->prefix}sm_views
										WHERE author = %d AND slug = %s",
										get_current_user_id(), $slug
										)
					);

		wp_send_json( ( ! empty( $row_count ) ) ? true : false );
	}

    /**
	 * Function to get all accessible views based on current user
	 *
	 * @param array $post_types array containing the valid post_types for the current user.
	 * @return array $accessible_views array containing the accessible views slug & title
	 */
	public function get_all_accessible_views( $post_types = array() ) {
		global $wpdb;
		$response = array( 'accessible_views' => array(), 'owned_views' => array(), 'public_views' => array(), 'view_post_types' => array(), 'saved_searches' => array() );
		$results = array();
		$view_results = array();
		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						title,
						slug,
						author,
						is_public,
						post_type,
						type,
						CASE 
							WHEN type = '1' THEN params 
							ELSE NULL 
						END AS params
					FROM {$wpdb->prefix}sm_views
					WHERE (
						post_type IN ('" . implode( "','", array_keys( $post_types ) ) . "')
						AND (is_public = 1 OR (is_public = 0 AND author = %d)) 
						AND type IN ('0', '1')
					)
					GROUP BY slug",
					get_current_user_id()
				),
				'ARRAY_A'
			);
		}
		if ( is_callable( array( 'Smart_Manager_Pro_Access_Privilege', 'get_current_user_access_privilege_settings' ) ) ) {
			$accessible_dashboards = Smart_Manager_Pro_Access_Privilege::get_current_user_access_privilege_settings();
			$view_results = $this->get_user_accessible_views( $accessible_dashboards );
		}
		$current_user_role = ( is_callable( array( 'Smart_Manager', 'get_current_user_role' ) ) ) ? Smart_Manager::get_current_user_role() : '';
		if ( ! empty( $current_user_role ) && 'administrator' === $current_user_role && is_array( $results ) && is_array( $view_results ) && count( $view_results ) > 0 ) {
			$results = array_merge( $results, $view_results );
		} elseif ( ( ! empty( $current_user_role ) && 'administrator' !== $current_user_role ) ) {
			$results = $view_results;
		}
		if ( empty( $results ) || ! is_array( $results ) ) {
			return $response;
		}
		foreach ( $results as $result ) {
			if ( ( empty( $result ) ) || ( ! is_array( $result ) ) || ( empty( $result['slug'] ) ) ) {
				continue;
			}
			if ( ( isset( $result['type'] ) ) && ( '0' === $result['type'] ) ) {
				$response['accessible_views'][ $result['slug'] ] = $result['title'];
			}
			if ( ! empty( $result['author'] ) && get_current_user_id() === intval( $result['author'] ) ) {
				$response['owned_views'][] = $result['slug'];
			}
			if ( ! empty( $result['is_public'] ) ) {
				$response['public_views'][] = $result['slug'];	
			}
			if ( ! empty( $result['post_type'] ) ) {
				$response['view_post_types'][ $result['slug'] ] = $result['post_type'];	
			}
			if ( ( ! empty( $result['type'] ) ) && ( '1' === $result['type'] ) ) {
				$response['saved_searches'][] = array( 'parent_post_type' => $result['post_type'], 'slug' => $result['slug'], 'title' => $result['title'], 'params'=> ( ! empty( $result['params'] ) ) ? json_decode( stripslashes( $result['params'] ), true ) : array() );	
			}
		}
		return $response;
    }

    /**
	 * Function to update custom views
	 *
	 * @return int $inserted_id ID of the inserted view
	 */
	public function update() {
		global $wpdb;

		$response = array( 'ACK' => 'Failed' );

		$title = ( ! empty( $this->req_params['name'] ) ) ? sanitize_text_field( wp_unslash( $this->req_params['name'] ) ) : '';
		$slug = sanitize_title( $title );
		$view_state = ( ! empty( $this->req_params['currentView'] ) ) ? $this->req_params['currentView'] : '';
		$active_module = ( ! empty( $this->req_params['active_module'] ) ) ? $this->req_params['active_module'] : '';

		if( empty( $title ) || empty( $view_state ) || empty( $active_module ) ) {
			$response[ 'msg' ] = _x( 'Required params missing.', 'update view response message', 'smart-manager-for-wp-e-commerce' );
			wp_send_json( $response );
		}

		// Code to map view state to required structure
		$view_data = $this->get_view_data_to_save( json_decode( stripslashes( $view_state ), true ), $this->req_params );
		if ( ( empty( $view_data ) ) || ( ! is_array( $view_data ) ) || ( empty( $view_data['view_state'] ) ) || ( ! isset( $view_data['view_type'] ) ) || ( ! in_array( $view_data['view_type'], array( "0", "1" ) ) ) ) {
			return;
		}
		$view_state = $view_data['view_state'];
		$view_json = sa_sm_generate_column_state( $view_state );

		if( ! empty( $view_state['search_params'] ) ) {
			$view_json['search_params'] = $view_state['search_params'];
		}

		$result = $wpdb->query( // phpcs:ignore
			$wpdb->prepare( // phpcs:ignore
				"UPDATE {$wpdb->prefix}sm_views
									SET title = %s,
										slug = %s,
										params = %s,
										is_public = %d,
										type = %s
									WHERE slug = %s",
				$title,
				$slug,
				json_encode( $view_json ),
				( ( ! empty( $this->req_params['isPublic'] ) && 'false' != $this->req_params['isPublic'] ) ? 1 : 0 ),
				sanitize_text_field( $view_data['view_type'] ),
				$active_module
			)
		);

		if( ! is_wp_error( $result ) ) {
			$response['ACK'] = 'Success';
			$response['slug'] = $slug;
		}

		wp_send_json( $response );
    }
 
	/**
	 * Processes view data based on request parameters.
	 *
	 * @param array $view_state Current view state data.
	 * @param array $req_params Request parameters for saving settings.
	 * @return array|void Updated view state and type or void on invalid input.
	*/
	public function get_view_data_to_save( $view_state = array(), $req_params = array() ) {
		if ( ( empty( $req_params ) ) || ( empty( $view_state ) ) || ( ! is_array( $req_params ) ) || ( ! is_array( $view_state ) ) ) {
			return;
		}
		$view_type = '0';
		if ( ( empty( $req_params['isSaveDashboardAndCols'] ) ) || ( ( ! empty( $req_params['isSaveDashboardAndCols'] ) ) && ( "false" === $req_params['isSaveDashboardAndCols'] ) ) ) {
			$view_type = '1';
			unset( $view_state['columns'] );
			unset( $view_state['sort_params'] );
		}
		if ( ( empty( $req_params['isSaveAdvancedSearch'] ) ) || ( ( ! empty( $req_params['isSaveAdvancedSearch'] ) ) && ( "false" === $req_params['isSaveAdvancedSearch'] ) ) ) {
			unset( $view_state['search_params'] );
		}
		return array( 'view_state' => $view_state, 'view_type' => $view_type );
	}

	/**
	 * Function to insert custom views
	 *
	 * @return int $inserted_id ID of the inserted view
	 */
	public function delete() {
		global $wpdb;

		$response = array( 'ACK' => 'Failed' );

		$active_module = ( ! empty( $this->req_params['active_module'] ) ) ? $this->req_params['active_module'] : '';

		if( empty( $active_module ) ) {
			$response[ 'msg' ] = _x( 'Required params missing.', 'delete view response message', 'smart-manager-for-wp-e-commerce' );
			wp_send_json( $response );
		}

		$result = $wpdb->query( // phpcs:ignore
			$wpdb->prepare( // phpcs:ignore
							"DELETE FROM {$wpdb->prefix}sm_views
							WHERE slug = %s",
				$active_module
			)
		);

		if( ! is_wp_error( $result ) ) {
			$response['ACK'] = 'Success';
		}

		wp_send_json( $response );
	}

	/**
	 * Function to check & create table for custom views if not exists
	 *
	 * @return void.
	 */
	public function check_if_table_exists(){
		global $wpdb;
		$table_nm = $wpdb->prefix. 'sm_views';
		if ( $table_nm === $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_nm ) ) ) {
			return;
		}
		if ( ! is_callable( array( 'Smart_Manager_Install', 'create_table_for_custom_views' ) ) ) {
			return;
		}
		$table_created = Smart_Manager_Install::create_table_for_custom_views();
		if( ! empty( $table_created ) && is_callable( array( 'Smart_Manager_Install', 'create_dummy_views' ) ) ){
			Smart_Manager_Install::create_dummy_views();
		}
	}
	
	/**
	 * Function to get saved bulk edits.
	 *
	 * @return void.
	 */
	public function get_saved_bulk_edits(){
		global $wpdb;
		$this->check_if_table_exists();
		$active_module = ( ! empty( $this->req_params['active_module'] ) ) ? $this->req_params['active_module'] : '';
		if( empty( $active_module ) ){
			wp_send_json( array( 'ACK' => 'Failed', 'msg' => _x( 'Required params missing.', 'get saved bulk edit response message', 'smart-manager-for-wp-e-commerce' ) ) );
		}
		// Prepare and execute query
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT title, slug, params FROM {$wpdb->prefix}sm_views WHERE post_type = %s AND type = %s AND author = %d ORDER BY id DESC", sanitize_text_field( $active_module ), '2', get_current_user_id() ), 'ARRAY_A' );
		wp_send_json( array( 'ACK' => 'Success', 'data' => $results ) );
	}

	/**
	 * Get view results based on current user's dashboard access privileges.
	 *
	 * @param array $accessible_dashboards User access settings.
	 * @return array View data.
	 */
	public function get_user_accessible_views( $accessible_dashboards = array() ) {
		global $wpdb;

		if ( empty( $accessible_dashboards ) || ! is_array( $accessible_dashboards ) ) {
			return;
		}

		$slugs   = ! empty( $accessible_dashboards['valid'] ) && is_array( $accessible_dashboards['valid'] )
			? $accessible_dashboards['valid']
			: ( empty( $accessible_dashboards['valid'] ) && ! empty( $accessible_dashboards['not_valid'] ) && is_array( $accessible_dashboards['not_valid'] )
				? $accessible_dashboards['not_valid']
				: array()
			);

		if ( empty( $slugs ) ) {
			return;
		}

		$operator = ! empty( $accessible_dashboards['valid'] ) ? 'IN' : 'NOT IN';
		$slug_list = implode( "','", array_map( 'esc_sql', $slugs ) );
		$where     = "slug {$operator} ('{$slug_list}') AND type IN ('0', '1')" . ( $operator === 'NOT IN' ? ' AND is_public = %d' : '' );

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT 
			title,
			slug,
			author,
			is_public,
			post_type,
			type,
			CASE WHEN type = '1' THEN params ELSE NULL END AS params
			FROM {$wpdb->prefix}sm_views
			WHERE ( {$where} AND 1 = %d )
			GROUP BY slug", 
			( $operator === 'NOT IN' ? array( 1, 1 ) : array( 1 ) ) ),
			'ARRAY_A'
		);
	}
}
