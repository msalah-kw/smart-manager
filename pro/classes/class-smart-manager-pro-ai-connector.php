<?php
/**
 *AI Connector Class.
 *
 * @package Smart_Manager_Pro
 * @since   8.72.0
 * @version 8.72.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Smart_Manager_Pro_AI_Connector' ) ) {
	/**
	 * Class properties and methods will go here.
	 */
	class Smart_Manager_Pro_AI_Connector {
		/**
		 * Holds the singleton instance of the class.
		 *
		 * @var Smart_Manager_Pro_AI_Connector
		 */
		protected static $instance = null;

		/**
		 * AI provider client ID.
		 *
		 * @var string
		 */
		protected $client_id           = '62Ny4ZYX172feJR57A3Z3bDMBJ1m63';

		/**
		 * AI provider client secret.
		 *
		 * @var string
		 */
		protected $client_secret       = 'Fd5sLarK8tSaI7UAc1af1erE02o2pu';

		/**
		 * API base URL for the Cohere AI.
		 *
		 * @var string
		 */
		static $cohere_api_base_url    = 'https://api.cohere.com/v2';

		/**
		 * Endpoint URL to log AI data.
		 *
		 * @var string
		 */
		/**
		 * StoreApps API domain.
		 *
		 * @var string
		 */
		protected $storeapps_api_domain = 'https://www.storeapps.org';

		/**
		 * StoreApps API base path.
		 *
		 * @var string
		 */
		protected $storeapps_api_base   = '/wp-json/sa/v1/sm-ai';

		/**
		 * Endpoint URL to log AI data.
		 *
		 * @var string
		 */
		protected $storeapps_am_ai_create_log_route = '/logs';

		/**
		 * Get singleton instance of the class.
		 *
		 * @return Smart_Manager_Pro_AI_Connector
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'sm_advanced_search_cohere_ai_system_prompt', array( __CLASS__, 'products_advanced_search_ai_system_prompt' ), 10, 2 );
		}

		/**
		 * Defines the system prompt used by the AI Connector.
		 *
		 * This function returns the full text of the system prompt, which can then be passed to the AI model to guide its behavior when performing advanced search operations.
		 * 
		 *  @param string $system_prompt Default system prompt.
		 *  
		 *  @param string $args Additional arguments.
		 *
		 * @return string The system prompt text for the AI.
		*/
		public static function products_advanced_search_ai_system_prompt( $system_prompt = '', $args = array() ) {
			global $wpdb;
			return 'You are a helpful assistant that always generates valid query arguments as JSON objects, based on user instructions.

			Rules to follow strictly:
			- The response must be in valid JSON only.
			- Do not include explanations, just return the JSON object.
			- If the user provides a prompt that is not related to search or filtering, return the plain text error message: Please enter a valid prompt related to search or filtering. Do not return a JSON response.
			
			Respond only with a valid JSON object. Example format:

			1. regular price greater than 100 and sale price greater than 100
			[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'. $wpdb->prefix .'postmeta._regular_price","operator":"gt","value":"100"},{"type":"'. $wpdb->prefix .'postmeta._sale_price","operator":"gt","value":"100"}]}]}]

			2. (regular price less than 100 and sale price less than 100) OR name contains test
			[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'. $wpdb->prefix .'postmeta._regular_price","operator":"lt","value":"100"},{"type":"'. $wpdb->prefix .'postmeta._sale_price","operator":"lt","value":"100"}]},{"condition":"AND","rules":[{"type":"'. $wpdb->prefix .'posts.post_title","operator":"like","value":"test"}]}]}]

				- use meta query as defined in case of meta data related conditions in the prompt.

			3. stock status is instock and price is equal to 120
			[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'. $wpdb->prefix .'postmeta._stock_status","operator":"is","value":"instock"},{"type":"'. $wpdb->prefix .'postmeta._price","operator":"eq","value":"120"}]}]}]

			4. category query params example
			[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'. $wpdb->prefix .'terms.product_cat","operator":"is","value":"clothing"}]}]}]

			5. date query params example
			[{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"'. $wpdb->prefix .'posts.post_date","operator":"gt","value":"2025-05-31 12:10:03"},{"type":"'. $wpdb->prefix .'posts.post_date","operator":"lt","value":"2025-06-30 12:13:19"}]}]}]
				- use 00:00:00 in time when time is not specefied in the prompt.

			- Use these operators in query params, as per the condition mentiond in the user prompt:
				eq  = equals to 
				neq = not equals to 
				lt = less than
				gt  = greater than
				lte = less than or equals to 
				gte = greater than or equals to
				is = is
				like = Contains
				is not = is not
				not like = not contains
				startsWith = starts with
				endsWith = ends with
				anyOf = any of
				notStartsWith = not starts with
				notEndsWith = not ends with
				notAnyOf = not any of
			- common products types = simple, variable, simple-subscription, variable-subscription
				eg. [{"condition":"OR","rules":[{"condition":"AND","rules":[{"type":"db_terms.product_type","operator":"is","value":"variable"}]}]}]
			';
		}

		/**
		 * Handle Cohere API call for AI Connector
		 *
		 * @return void
		 */
		public function get_query_params_from_ai() {
			try {
				// Sanity check for prompt.
				if ( empty( $_POST['prompt'] ) ) {
					wp_send_json(
						array(
							'ACK'     => 'Failure',
							'msg' => _x( 'Prompt is empty', 'AI Connector Error', 'smart-manager-for-wp-e-commerce' ),
						)
					);
				}
				//Validate AI integration settings.
				$ai_integration_settings = Smart_Manager_Settings::get('ai_integration_settings');
				$selected_ai_model = ( ( is_array( $ai_integration_settings ) ) && ( ! empty( $ai_integration_settings['selectedModel'] ) ) ) ? $ai_integration_settings['selectedModel'] : '';
				if ( empty( $selected_ai_model ) ) {
					wp_send_json(
						array(
							'ACK' => 'Failure',
							'msg' => _x( 'Please configure AI Integration settings under Settings > General Settings to use this feature.', 'AI Connector Error', 'smart-manager-for-wp-e-commerce' ),
						)
					);
				}
				$api_key = ( ( ! empty( $ai_integration_settings['apiKey'] ) ) ) ? $ai_integration_settings['apiKey'] : '';
				if ( empty( $api_key ) ) {
					wp_send_json(
						array(
							'ACK'     => 'Failure',
							'msg' => _x( 'API key not found. Please add your API key in the settings to continue.', 'AI Connector Error', 'smart-manager-for-wp-e-commerce' ),
						)
					);
				}
				//Verify store connection.
				$access_token = get_option( '_storeapps_connector_access_token' );
				self::verify_store_connection( $access_token );
				//Make AI request.
				$prompt = sanitize_text_field( wp_unslash( $_POST['prompt'] ) );
				$response  = array();
				$response_time  = '';
				switch ($selected_ai_model) {
					case 'cohere':
						$start_time = microtime(true);
						$response  = $this->cohere_ai_request( $prompt, $api_key );
						$end_time = microtime(true);
						$response_time = round($end_time - $start_time, 3) . ' sec';
						break;
					
					default:
						sa_manager_log('error', 'Invalid AI model' );
						break;
				}
				//Validate response.
				if ( empty( $response ) || is_wp_error( $response ) ) {
					$error_msg = is_wp_error( $response ) && is_callable( $response, 'get_error_message' ) ? $response->get_error_message() : _x( 'Empty or invalid response from AI service.', 'AI Connector Error', 'smart-manager-for-wp-e-commerce' );
					sa_manager_log('error', 'AI Connector: Cohere API request error: ' . $error_msg  );
					wp_send_json(
						array(
							'ACK'     => 'Failure',
							'msg' => $error_msg,
						)
					);
				}
				$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
				//Log data.
				$current_user = wp_get_current_user();
				$dashboard_key = ( ! empty( $_POST['dashboard_key'] ) ) ? sanitize_key( wp_unslash( $_POST['dashboard_key'] ) ) : '';
				$data_to_log = array(
					'prompt'     => $prompt,
					'plugin_sku' => SM_SKU,
					'response'   => $response_data,
					'task_type'  => 'advanced-search',
					'meta'       => array(
						'ai' => array(
							'ai_model' => $selected_ai_model,
							'response_time' => $response_time
						),
						'sm' => array(
							'dashboard'        => $dashboard_key,
							'all_dashboards'   => ( ( ! empty( $_POST['all_dashboards'] ) ) && ( is_array( $_POST['all_dashboards'] ) ) ) ? $_POST['all_dashboards'] : array(),
							'is_custom_view'      => ( ( ! empty( $_POST['is_custom_view'] ) ) && ( 'true' === $_POST['is_custom_view'] ) ) ? true : false
						),
						'user' => array(
							'role' => ( ( ! empty( $current_user ) ) && ( is_object( $current_user ) ) && ( ! empty( $current_user->roles ) ) ) ? $current_user->roles : array(),
							'caps' => ( ( ! empty( $current_user ) ) && ( is_object( $current_user ) ) && ( ! empty( $current_user->caps ) ) ) ? $current_user->caps : array()
						),
						'site' => array(
							'domain' => get_site_url()
						)
					)
				);
				if ( 'product' === $dashboard_key ) {
					$data_to_log['meta']['sm']['show_variations'] = ( ! empty( $_POST['show_variations'] ) && ( 'true' === $_POST['show_variations'] ) ) ? true : false;
				}
				if ( empty ( $this->log_ai_response_data( $data_to_log, $access_token, $this->storeapps_api_domain . $this->storeapps_api_base . $this->storeapps_am_ai_create_log_route ) ) ) { 
					wp_send_json(
						array(
							'ACK' => 'Failure',
							'msg' => sprintf(
								_x( /* translators: %s: URL to contact page */
									'Authentication failed or something went wrong while processing the AI response. Please reconnect your StoreApps account and try again, or contact us from <a href="%s" target="_blank">here</a>.',
									'AI Connector Error',
									'smart-manager-for-wp-e-commerce'
								),
								esc_url( 'https://www.storeapps.org/contact-us/?utm_source=sm&utm_medium=in_app_modal&utm_campaign=store_connection' )
							)
						)
					);
				}
				// Check and return data.
				if ( ( is_array( $response_data ) ) && ( ! empty( $response_data['message']['content'] ) ) ) {
					if ( ( is_array( $response_data['message']['content'][0] ) ) && ( ! empty( $response_data['message']['content'][0]['text'] ) ) ) {
						$data_obj = json_decode( $response_data['message']['content'][0]['text'], true );
						if ( ( ! empty( $data_obj ) ) && ( is_array( $data_obj ) ) && ( ! empty( $data_obj['error'] ) ) ) {
							wp_send_json(
								array(
									'ACK'     => 'Failure',
									'msg' => _x( $data_obj['error'], 'AI Connector Error', 'smart-manager-for-wp-e-commerce' ),
								)
							);
						}
					}
					wp_send_json(
						array(
							'ACK'  => 'Success',
							'data' => is_array( $response_data['message']['content'] ) ? $response_data['message']['content'] : json_decode( $response_data['message']['content'], true ),
						)
					);
				}
				// Unexpected empty response.
				sa_manager_log('error', 'AI Connector: Cohere API returned empty response.' );
				wp_send_json(
					array(
						'ACK'     => 'Failure',
						'msg' => _x( 'Unexpected response from the AI service. Please verify your API key and try again.', 'AI Connector Error', 'smart-manager-for-wp-e-commerce' ),
					)
				);

			} catch ( Exception $e ) {
				// Catch and log unexpected errors.
				sa_manager_log('error', 'AI Connector: Exception in get_query_params_from_ai: ' . $e->getMessage() );
				wp_send_json(
					array(
						'ACK'     => 'Failure',
						'msg' => _x( 'An unexpected error occurred while processing your request. Please try again later.', 'AI Connector Error', 'smart-manager-for-wp-e-commerce' ),
					)
				);
			}
		}

		/**
		 * Logs the AI response data.
		 *
		 * @param array  $body         The response body data to be logged.
		 * @param string $access_token The access token used for the AI request.
		 * @param string $endpoint_url The endpoint URL to which the AI request was made.
		 *
		 * @return void|True True if data logged successfully else void. 
		 */
		public function log_ai_response_data( $body = array(), $access_token = '', $endpoint_url = '' ) {
			// Validate required parameters: body, access_token, endpoint_url.
			if ( empty( $body ) || ! is_array( $body ) || empty( $access_token ) || empty( $endpoint_url ) ) {
				sa_manager_log('error', 'AI Connector: log_ai_response_data called with invalid parameters. Params: ' . print_r( array(
					'body' => $body,
					'access_token' => $access_token,
					'endpoint_url' => $endpoint_url,
				), true ) );
				return;
			}
			//Send request.
			$response = wp_remote_post( esc_url( $endpoint_url ), array(
				'method'      => 'POST',
				'headers'     => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Referer'       => base64_encode( $this->client_id . ':' . $this->client_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'timeout'     => 30,
			) );
			// Check for errors.
			if ( is_wp_error( $response ) || empty( $response ) ) {
				sa_manager_log('error', 'AI Connector: Error in logging AI response: ' . print_r( $response, true ) );
				return;
			}
			// Decode JSON response.
			$response_arr = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ( empty( $response_arr ) ) || ( ! is_array( $response_arr ) ) ) {
				sa_manager_log('error', 'AI Connector: Error in logging AI response: invalid or malformed JSON response: ' . print_r( $response_arr, true ) );
				return;
			}
			if ( ( empty( $response_arr['success'] ) ) || ( true !== $response_arr['success'] ) ) {
				sa_manager_log('error', 'AI Connector: Error in logging AI response: ' . print_r( $response, true ) );
				return;
			}
			return true;
		}

		/**
		 * Verify Cohere API Key by calling a lightweight endpoint.
		 *
		 * @param string $api_key API key to validate.
		 * @return array Validation result with status and optional data.
		 */
		public static function verify_cohere_key( $api_key = '' ) {
			if ( empty( $api_key ) ) {
				return array( 'valid' => false );
			}

			try {
				$response = wp_remote_get(
					self::$cohere_api_base_url . '/models',
					array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $api_key,
							'Accept'        => 'application/json',
						),
						'timeout' => 30,
					)
				);

				// Check for WP error.
				if ( is_wp_error( $response ) ) {
					$error_msg = ( is_callable( $response, 'get_error_message' ) ) ? $response->get_error_message() : '';
					sa_manager_log( 'error', 'Cohere API: Request error in verify_cohere_key: ' . $error_msg );
					return array(
						'valid' => false,
						'error' => $error_msg,
					);
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				$body        = wp_remote_retrieve_body( $response );

				if ( 200 === $status_code ) {
					return array(
						'valid' => true,
						'data'  => json_decode( $body, true ),
					);
				}

				// Invalid or unauthorized.
				sa_manager_log( 'error', 'Cohere API: Invalid key or unauthorized. Status: ' . $status_code );
				return array(
					'valid'  => false,
					'status' => $status_code,
					'error'  => $body,
				);

			} catch ( Exception $e ) {
				sa_manager_log( 'error', 'Cohere API: Exception in verify_cohere_key: ' . $e->getMessage() );
				return array(
					'valid' => false,
					'error' => $e->getMessage(),
				);
			}
		}

		/**
		 * Verify the AI provider settings and credentials.
		 *
		 * @param array $ai_integration_settings AI integration settings.
		 * @return void
		 */
		public static function verify_AI_integration_settings( $ai_integration_settings = array() ) {
			if ( ( empty( $ai_integration_settings ) ) || ( ! is_array( $ai_integration_settings ) ) || ( empty( $ai_integration_settings['selectedModel'] ) ) ){
				return;
			}
			//Verify store connection.
			self::verify_store_connection( get_option( '_storeapps_connector_access_token' ) );
			//Verify Cohere AI API key.
			if ( ( 'cohere' === $ai_integration_settings['selectedModel'] ) ) {
				if ( empty( $ai_integration_settings['cohere']['api_key'] ) ) {
					wp_send_json( array( 'ACK' => 'Failure', 'msg' => _x( 'API key is missing. Please enter your API key.', 'ai provider settings validation message', 'smart-manager-for-wp-e-commerce' ) ) );
				}
				$validate_cohere_api_key = self::verify_cohere_key( $ai_integration_settings['cohere']['api_key'] );
				if ( ( ! is_array ( $validate_cohere_api_key ) ) || ( is_array( $validate_cohere_api_key ) && empty( $validate_cohere_api_key['valid'] ) ) ) {
					wp_send_json( array( 'ACK' => 'Failure', 'msg' => _x( 'Cohere API key verification failed, please enter valid API key', 'ai provider settings validation message', 'smart-manager-for-wp-e-commerce' ) ) );
				}
			}
		}

		/**
		 * Verify the StoreApps connector access token.
		 *
		 * @param string $access_token Access token to validate.
		 * @return bool|void True on valid token, JSON response on failure.
		 */
		public static function verify_store_connection( $access_token = '' ) {
			$token_expiry = get_option( '_storeapps_connector_token_expiry' );
			if ( empty( $access_token ) || ( ! empty( $token_expiry ) && time() > $token_expiry ) ) {
				wp_send_json( array(
					'ACK' => 'Failure',
					'msg' => sprintf(
						_x( /* translators: %s: URL to contact page */
							'You need to connect StoreApps account to access AI feature, please contact us from <a href="%s" target="_blank">here</a>.',
							'ai provider settings validation message',
							'smart-manager-for-wp-e-commerce'
						),
						esc_url( 'https://www.storeapps.org/contact-us/?utm_source=sm&utm_medium=in_app_modal&utm_campaign=store_connection' )
					)
				) );
			}
		}

		/**
		 * Sends a request to the Cohere AI API with the given prompt and API key.
		 *
		 * @param string $prompt   The prompt to send to the AI.
		 * @param string $api_key  The API key for authentication.
		 * @return mixed           The response from the Cohere AI API.
		 */
		public function cohere_ai_request( $prompt = '', $api_key = '' ) {
			if ( empty( $prompt ) || empty( $api_key ) ) {
				return;
			}
			// Make API request.
			return wp_remote_post(
				self::$cohere_api_base_url . '/chat',
				array(
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $api_key,
					),
					'body'    => wp_json_encode( 
						array(
							'model'           => ( ! empty( $_POST['model'] ) ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : 'command-a-03-2025',
							'temperature'     => 0.7,
							'messages'        => array(
								array(
									'role'    => 'system',
									'content' => apply_filters( 'sm_advanced_search_cohere_ai_system_prompt', '', array() ),
								),
								array(
									'role'    => 'user',
									'content' => $prompt,
								),
							),
							'response_format' => array( 'type' => 'json_object' ),
						) 
					),
					'timeout' => 60,
				)
			);
		}
	}
	Smart_Manager_Pro_AI_Connector::get_instance();
}
