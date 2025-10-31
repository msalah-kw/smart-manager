<?php
/**
 * StoreApps Upgrade
 *
 * @category    Class
 * @package     StoreApps Connector
 * @author      StoreApps
 * @version     4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'StoreApps_Upgrade_4_0' ) ) {

	/**
	 * Main class for StoreApps Upgrade
	 */
	class StoreApps_Upgrade_4_0 {

		/**
		 * Base name
		 *
		 * @var string
		 */
		public $base_name;

		/**
		 * Check update timeout
		 *
		 * @var integer
		 */
		public $check_update_timeout;

		/**
		 * Last checked
		 *
		 * @var integer
		 */
		public $last_checked;

		/**
		 * Plugins data
		 *
		 * @var array
		 */
		public $plugin_data;

		/**
		 * Product SKU
		 *
		 * @var string
		 */
		public $sku;

		/**
		 * License Key
		 *
		 * @var string
		 */
		public $license_key;

		/**
		 * Download URL
		 *
		 * @var string
		 */
		public $download_url;

		/**
		 * Installed version
		 *
		 * @var string
		 */
		public $installed_version;

		/**
		 * Live version available
		 *
		 * @var string
		 */
		public $live_version;

		/**
		 * Changelog
		 *
		 * @var string
		 */
		public $changelog;

		/**
		 * Slug
		 *
		 * @var string
		 */
		public $slug;

		/**
		 * Name
		 *
		 * @var string
		 */
		public $name;

		/**
		 * Docs link
		 *
		 * @var string
		 */
		public $documentation_link;

		/**
		 * Prefix
		 *
		 * @var string
		 */
		public $prefix;

		/**
		 * Text domain
		 *
		 * @var string
		 */
		public $text_domain;

		/**
		 * Login link
		 *
		 * @var string
		 */
		public $login_link;

		/**
		 * Due date
		 *
		 * @var string
		 */
		public $due_date;

		/**
		 * Plugin file
		 *
		 * @var string
		 */
		public $plugin_file;

		/**
		 * Upgrade notice
		 *
		 * @var string
		 */
		public $upgrade_notices;

		/**
		 * Client ID
		 *
		 * @var string
		 */
		public $client_id;

		/**
		 * Client secret
		 *
		 * @var string
		 */
		public $client_secret;

		/**
		 * On-boarding steps
		 *
		 * @var array
		 */
		private $onboarding_steps = array();

		/**
		 * Current On-bording step
		 *
		 * @var string
		 */
		private $onboarding_step = '';

		/**
		 * SA upgrade file path
		 *
		 * @var string
		 */
		public $upgrade_file_path = '';
		
		/**
		 * SA admin notice CSS class
		 *
		 * @var string
		 */
		public $admin_notice_css_class = '';

		/**
		 * Constructor
		 *
		 * @param string $file               Base file.
		 * @param string $sku                Product Identifier.
		 * @param string $prefix             Prefix.
		 * @param string $plugin_name        Plugin name.
		 * @param string $text_domain        Text domain.
		 * @param string $documentation_link Docs link.
		 */
		public function __construct( $file, $sku, $prefix, $plugin_name, $text_domain, $documentation_link ) {

			$this->plugin_file        = $file;
			$this->base_name          = plugin_basename( $file );
			$this->slug               = dirname( $this->base_name );
			$this->name               = $plugin_name;
			$this->sku                = $sku;
			$this->documentation_link = $documentation_link;
			$this->prefix             = $prefix;
			$this->text_domain        = $text_domain;
			$this->client_id          = '62Ny4ZYX172feJR57A3Z3bDMBJ1m63';
			$this->client_secret      = 'Fd5sLarK8tSaI7UAc1af1erE02o2pu';
			$this->upgrade_file_path  = str_replace( ABSPATH, '/', __FILE__ );
			$this->admin_notice_css_class  = $sku . '-notice';

			add_action( 'admin_init', array( $this, 'initialize_plugin_data' ) );

			add_action( 'admin_footer', array( $this, 'add_plugin_style_script' ) );
			add_action( 'admin_footer', array( $this, 'add_support_ticket_content' ) );
			add_action( 'wp_ajax_' . $this->prefix . '_get_authorization_code', array( $this, 'get_authorization_code' ) );
			add_action( 'wp_ajax_' . $this->prefix . '_save_token', array( $this, 'save_token' ) );
			add_action( 'wp_ajax_' . $this->prefix . '_save_data', array( $this, 'save_data' ) );
			add_action( 'wp_ajax_' . $this->prefix . '_save_error_data', array( $this, 'save_error_data' ) );
			add_action( 'wp_ajax_' . $this->prefix . '_disconnect_storeapps', array( $this, 'disconnect_storeapps' ) );

			if ( has_action( 'wp_ajax_get_storeapps_updates', array( $this, 'get_storeapps_updates' ) ) === false ) {
				add_action( 'wp_ajax_get_storeapps_updates', array( $this, 'get_storeapps_updates' ) );
			}
			if ( has_action( 'wp_ajax_nopriv_storeapps_updates_available', array( $this, 'storeapps_updates_available' ) ) === false ) {
				add_action( 'wp_ajax_nopriv_storeapps_updates_available', array( $this, 'storeapps_updates_available' ) );
			}
			if ( has_action( 'wp_ajax_storeapps_report_failed_connection', array( $this, 'storeapps_report_failed_connection' ) ) === false ) {
				add_action( 'wp_ajax_storeapps_report_failed_connection', array( $this, 'storeapps_report_failed_connection' ) );
			}

			add_filter( 'all_plugins', array( $this, 'overwrite_wp_plugin_data_for_plugin' ) );
			add_filter( 'plugins_api', array( $this, 'overwrite_wp_plugin_api_for_plugin' ), 10, 3 );
			add_filter( 'site_transient_update_plugins', array( $this, 'overwrite_site_transient' ), 10, 3 );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'overwrite_site_transient' ), 10, 3 );

			add_filter( 'plugin_row_meta', array( $this, 'add_support_link' ), 10, 4 );

			add_filter( 'storeapps_upgrade_create_link', array( $this, 'storeapps_upgrade_create_link' ), 10, 4 );

			add_action( 'admin_notices', array( $this, 'show_notifications' ) );
			add_action( 'wp_ajax_' . $this->prefix . '_hide_renewal_notification', array( $this, 'hide_renewal_notification' ) );
			add_action( 'wp_ajax_' . $this->prefix . '_hide_license_notification', array( $this, 'hide_license_notification' ) );

			add_action( 'in_admin_footer', array( $this, 'add_quick_help_widget' ) );

			add_action( 'admin_notices', array( $this, 'connect_storeapps_notification' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

			add_action( 'admin_footer', array( $this, 'request_storeapps_data_js' ) );

			add_filter( 'storeapps_upgrade_miscellaneous_info', array( $this, 'storeapps_upgrade_miscellaneous_info' ), 10, 2 );

			add_action( 'admin_notices', array( $this, 'show_plugin_update_notification' ) );
			add_action( 'admin_notices', array( $this, 'show_reconnect_notification' ) );
			add_action( 'admin_init', array( $this, 'handle_re_authentication' ) );

			add_action( 'admin_init', array( $this, 'check_store_connection' ) );
			add_action( 'admin_menu', array( $this, 'add_storeapps_plugins_page' ) );
			add_action( 'admin_init', array( $this, 'start_onboarding' ) );
			add_filter( 'storeapps_onboarding_steps', array( $this, 'default_onboarding_steps' ), 10, 2 );

			add_filter( 'allowed_redirect_hosts', array( $this, 'allow_storeapps_as_redirect_hosts' ), 10, 2 );

		}

		/**
		 * Initialize plugin data
		 */
		public function initialize_plugin_data() {

			$this->plugin_data = get_plugin_data( $this->plugin_file );
			$this->base_name   = plugin_basename( $this->plugin_file );
			$this->slug        = dirname( $this->base_name );

			$sku            = $this->sku;
			$storeapps_data = $this->get_storeapps_data();

			$update = false;

			if ( empty( $this->last_checked ) ) {
				$this->last_checked = (int) $storeapps_data['last_checked'];
			}

			if ( $storeapps_data[ $sku ]['installed_version'] !== $this->plugin_data ['Version'] ) {
				$storeapps_data[ $sku ]['installed_version'] = $this->plugin_data ['Version'];
				$update                                      = true;
			}

			if ( empty( $storeapps_data[ $sku ]['live_version'] ) || version_compare( $storeapps_data[ $sku ]['live_version'], $storeapps_data[ $sku ]['installed_version'], '<' ) ) {
				$storeapps_data[ $sku ]['live_version'] = $this->plugin_data['Version'];
				$update                                 = true;
			}

			if ( empty( $this->license_key ) ) {
				$this->license_key = ( ! empty( $storeapps_data[ $sku ]['license_key'] ) ) ? $storeapps_data[ $sku ]['license_key'] : '';
			}

			if ( empty( $this->changelog ) ) {
				$this->changelog = ( ! empty( $storeapps_data[ $sku ]['changelog'] ) ) ? $storeapps_data[ $sku ]['changelog'] : '';
			}

			if ( empty( $this->login_link ) ) {
				$this->login_link = ( ! empty( $storeapps_data[ $sku ]['login_link'] ) ) ? $storeapps_data[ $sku ]['login_link'] : '';
			}

			if ( empty( $this->due_date ) ) {
				$this->due_date = ( ! empty( $storeapps_data[ $sku ]['due_date'] ) ) ? $storeapps_data[ $sku ]['due_date'] : '';
			}

			if ( $update ) {
				$this->set_storeapps_data( $storeapps_data );
			}

			if ( empty( $this->onboarding_steps ) ) {
				$this->onboarding_steps = $this->get_onboarding_steps();
			}

			if ( empty( $this->check_update_timeout ) ) {
				$this->check_update_timeout = $this->get_check_update_timeout_seconds();
			}

			add_action( 'after_plugin_row_' . $this->base_name, array( $this, 'update_row' ), 99, 2 );

		}

		/**
		 * Add upgrade data in transient
		 *
		 * @param  object  $plugin_info         Plugin information.
		 * @param  string  $transient           Transient name.
		 * @param  boolean $force_check_updates Force check updates.
		 * @return object  $plugin_info         Plugin info with added information.
		 */
		public function overwrite_site_transient( $plugin_info, $transient = 'update_plugins', $force_check_updates = false ) {

			$package = ( ! empty( $plugin_info->response[ $this->base_name ]->package ) ) ? $plugin_info->response[ $this->base_name ]->package : '';

			if ( ! empty( $package ) && strpos( $package, 'storeapps.org' ) === false ) {
				$plugin_info->response[ $this->base_name ]->package = '';
			}

			if ( empty( $plugin_info->checked ) ) {
				return $plugin_info;
			}

			$sku            = $this->sku;
			$storeapps_data = $this->get_storeapps_data();

			$plugin_base_file  = $this->base_name;
			$live_version      = $storeapps_data[ $sku ]['live_version'];
			$installed_version = $storeapps_data[ $sku ]['installed_version'];

			if ( version_compare( $live_version, $installed_version, '>' ) ) {
				$slug          = substr( $plugin_base_file, 0, strpos( $plugin_base_file, '/' ) );
				$download_url  = ( ! empty( $storeapps_data[ $sku ]['download_url'] ) ) ? $storeapps_data[ $sku ]['download_url'] : '';
				$download_link = ( ! empty( $download_url ) ) ? add_query_arg(
					array(
						'utm_source'   => $this->sku . '-v' . $live_version,
						'utm_medium'   => 'upgrade',
						'utm_campaign' => 'update',
					),
					$download_url
				) : '';

				$protocol = 'https';

				$plugin_info->response [ $plugin_base_file ]              = new stdClass();
				$plugin_info->response [ $plugin_base_file ]->slug        = $slug;
				$plugin_info->response [ $plugin_base_file ]->new_version = $live_version;
				$plugin_info->response [ $plugin_base_file ]->url         = $protocol . '://www.storeapps.org';
				$plugin_info->response [ $plugin_base_file ]->package     = $download_link;
			}

			return $plugin_info;
		}

		/**
		 * Modify plugin data
		 *
		 * @param  array $all_plugins All plugins.
		 * @return array $all_plugins Modified plugins data.
		 */
		public function overwrite_wp_plugin_data_for_plugin( $all_plugins = array() ) {

			if ( empty( $all_plugins ) || empty( $all_plugins[ $this->base_name ] ) ) {
				return $all_plugins;
			}

			if ( ! empty( $all_plugins[ $this->base_name ]['PluginURI'] ) ) {
				$all_plugins[ $this->base_name ]['PluginURI'] = add_query_arg(
					array(
						'utm_source'   => 'product',
						'utm_medium'   => 'upgrade',
						'utm_campaign' => 'visit',
					),
					$all_plugins[ $this->base_name ]['PluginURI']
				);
			}

			if ( ! empty( $all_plugins[ $this->base_name ]['AuthorURI'] ) ) {
				$all_plugins[ $this->base_name ]['AuthorURI'] = add_query_arg(
					array(
						'utm_source'   => 'brand',
						'utm_medium'   => 'upgrade',
						'utm_campaign' => 'visit',
					),
					$all_plugins[ $this->base_name ]['AuthorURI']
				);
			}

			return $all_plugins;
		}

		/**
		 * Add plugin update information in plugin's API
		 *
		 * @param  object|bool $api Plugin's API.
		 * @param  string      $action Action.
		 * @param  string      $args Arguments.
		 * @return object Modified plugin API.
		 */
		public function overwrite_wp_plugin_api_for_plugin( $api = false, $action = '', $args = '' ) {

			if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) {
				return $api;
			}

			$sku            = $this->sku;
			$storeapps_data = $this->get_storeapps_data();

			$protocol      = 'https';
			$changelog_url = $protocol . '://www.storeapps.org/docs/' . $sku . '-changelog/';

			$changelog = ( ! empty( $this->changelog ) ) ? trim( $this->changelog ) : '';

			if ( ! empty( $changelog ) ) {
				$changelog = nl2br( $changelog );
			}

			$api              = new stdClass();
			$api->slug        = $this->slug;
			$api->plugin      = $this->base_name;
			$api->name        = $this->plugin_data['Name'];
			$api->plugin_name = $this->plugin_data['Name'];
			$api->version     = $storeapps_data[ $sku ]['live_version'];
			$api->author      = $this->plugin_data['Author'];
			$api->homepage    = $this->plugin_data['PluginURI'];
			$api->sections    = array( 'changelog' => wp_unslash( $changelog ) . '<p><a href="' . esc_url( $changelog_url ) . '" target="' . $sku . '-changelog">' . __( 'Click here to see full changelog', $this->text_domain ) . '</a></p>' ); // phpcs:ignore

			$download_url  = ( ! empty( $storeapps_data[ $sku ]['download_url'] ) ) ? $storeapps_data[ $sku ]['download_url'] : '';
			$download_link = ( ! empty( $download_url ) ) ? add_query_arg(
				array(
					'utm_source'   => $this->sku . '-v' . $api->version,
					'utm_medium'   => 'upgrade',
					'utm_campaign' => 'update',
				),
				$download_url
			) : '';

			$api->download_link = $download_link;

			return $api;
		}

		/**
		 * Function to add plugin's style
		 */
		public function add_plugin_style() {
			?>
			<style type="text/css">
				div#TB_ajaxContent {
					overflow: hidden;
					position: initial;
				}
				<?php if ( version_compare( get_bloginfo( 'version' ), '3.7.1', '>' ) ) { ?>
				tr.<?php echo esc_html( $this->prefix ); ?>_license_key .key-icon-column:before {
					content: "\f112";
					display: inline-block;
					-webkit-font-smoothing: antialiased;
					font: normal 1.5em/1 'dashicons';
				}
				tr.<?php echo esc_html( $this->prefix ); ?>_due_date .renew-icon-column:before {
					content: "\f463";
					display: inline-block;
					-webkit-font-smoothing: antialiased;
					font: normal 1.5em/1 'dashicons';
				}
				<?php } ?>
				a#<?php echo esc_html( $this->prefix ); ?>_reset_license,
				a#<?php echo esc_html( $this->prefix ); ?>_disconnect_storeapps {
					cursor: pointer;
				}
				a#<?php echo esc_html( $this->prefix ); ?>_disconnect_storeapps:hover {
					color: #fff;
					background-color: #dc3232;
				}
				span#<?php echo esc_html( $this->prefix ); ?>_hide_renewal_notification,
				span#<?php echo esc_html( $this->prefix ); ?>_hide_license_notification {
					cursor: pointer;
					float: right;
					opacity: 0.2;
				}
				span.dashicons.<?php echo esc_html( $this->prefix ); ?>-meta {
					font-size: 1.2em;
					color: #5850EC;
				}
			</style>
			<?php
		}

		/**
		 * Add information in plugin update row on plugins page
		 *
		 * @param  string $file Plugin file.
		 * @param  array  $plugin_data Plugin's data.
		 */
		public function update_row( $file, $plugin_data ) {
			if ( ! empty( $this->due_date ) ) {
				$start    = strtotime( $this->due_date . ' -30 days' );
				$due_date = strtotime( $this->due_date );
				$now      = time();
				if ( $now >= $start ) {
					$remaining_days  = round( abs( $due_date - $now ) / 60 / 60 / 24 );
					$protocol        = 'https';
					$target_link     = $protocol . '://www.storeapps.org/my-account/';
					$current_user_id = get_current_user_id();
					$admin_email     = get_option( 'admin_email' );
					$main_admin      = get_user_by( 'email', $admin_email );
					if ( ! empty( $main_admin->ID ) && $main_admin->ID === $current_user_id && ! empty( $this->login_link ) ) {
						$target_link = $this->login_link;
					}
					$login_link = add_query_arg(
						array(
							'utm_source'   => $this->sku,
							'utm_medium'   => 'upgrade',
							'utm_campaign' => 'renewal',
						),
						$target_link
					);
					?>
						<tr class="<?php echo esc_attr( $this->prefix ); ?>_due_date" style="background: #FFEBEB;">
							<td class="renew-icon-column" style="vertical-align: middle;"></td>
							<td style="vertical-align: middle;" colspan="3">
								<?php
								if ( $now > $due_date ) {
									echo sprintf( esc_html__( 'Your license for %s %s. Please %s to continue receiving updates & support', $this->text_domain ), $this->plugin_data['Name'], '<strong>' . esc_html__( 'has expired', $this->text_domain ) . '</strong>', '<a href="' . $login_link . '" target="storeapps_renew">' . esc_html__( 'renew your license now', $this->text_domain ) . '</a>' ); // phpcs:ignore
								} else {
									echo sprintf( esc_html__( 'Your license for %s %swill expire in %d %s%s. Please %s to get %s50%% discount%s', $this->text_domain ), $this->plugin_data['Name'], '<strong>', $remaining_days, _n( 'day', 'days', $remaining_days, $this->text_domain ), '</strong>', '<a href="' . $login_link . '" target="storeapps_renew">' . esc_html__( 'renew your license now', $this->text_domain ) . '</a>', '<strong>', '</strong>' ); // phpcs:ignore
								}
								?>
							</td>
						</tr>
					<?php
				}
			}
		}

		/**
		 * Function to add plugin style script
		 */
		public function add_plugin_style_script() {

			global $pagenow;

			$this->add_plugin_style();

			$storeapps_data = $this->get_storeapps_data();

			if ( empty( $this->last_checked ) ) {
				$this->last_checked = ( ! empty( $storeapps_data['last_checked'] ) ) ? $storeapps_data['last_checked'] : null;
				if ( empty( $this->last_checked ) ) {
					$this->last_checked = $this->reset_last_checked_to( strtotime( '-' . ( $this->get_check_update_timeout_minutes() - 2 ) . ' minutes' ) );
				}
			}

			$time_not_changed = isset( $this->last_checked ) && $this->check_update_timeout > ( time() - $this->last_checked );

			$plugin_slug = ( ! empty( $this->plugin_data['slug'] ) ) ? $this->plugin_data['slug'] : ( ( ! empty( $this->plugin_data['Name'] ) ) ? sanitize_title( $this->plugin_data['Name'] ) : '' );

			?>
			<script type="text/javascript">
				jQuery(function(){
					jQuery('a#<?php echo esc_html( $this->prefix ); ?>_disconnect_storeapps').on( 'click', function(){
						var trigger_element = jQuery(this);
						var status_element = jQuery(this).closest('tr');
						status_element.css('opacity', '0.4');
						jQuery.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							type: 'post',
							dataType: 'json',
							data: {
								action: '<?php echo esc_html( $this->prefix ); ?>_disconnect_storeapps',
								prefix: '<?php echo esc_html( $this->prefix ); ?>',
								security: '<?php echo wp_create_nonce( 'disconnect-storeapps' ); // phpcs:ignore ?>'
							},
							success: function( response ) {
								status_element.css('opacity', '1');
								trigger_element.text('<?php echo __( 'Disconnected', $this->text_domain ); // phpcs:ignore ?>');
								trigger_element.css({
									'background-color': '#46b450',
									'color': 'white'
								});
								setTimeout( function(){
									location.reload();
								}, 100);
							}
						});
					});

					var loaded_url = jQuery('a.<?php echo esc_html( $this->prefix ); ?>_support_link').attr('href');

					if ( loaded_url != undefined && ( loaded_url.indexOf('width') == -1 || loaded_url.indexOf('height') == -1 ) ) {
						var width = jQuery(window).width();
						var H = jQuery(window).height();
						var W = ( 720 < width ) ? 720 : width;
						var adminbar_height = 0;

						if ( jQuery('body.admin-bar').length )
							adminbar_height = 28;

						jQuery('a.<?php echo esc_html( $this->prefix ); ?>_support_link').each(function(){
							var href = jQuery(this).attr('href');
							if ( ! href )
									return;
							href = href.replace(/&width=[0-9]+/g, '');
							href = href.replace(/&height=[0-9]+/g, '');
							jQuery(this).attr( 'href', href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 - adminbar_height ) );
						});

					}

					<?php if ( version_compare( get_bloginfo( 'version' ), '4.4.3', '>' ) ) { ?>
						jQuery('tr[data-slug="<?php echo esc_html( $this->slug ); ?>"]').find( 'div.plugin-version-author-uri' ).addClass( '<?php echo esc_html( $this->prefix ); ?>_social_links' );
					<?php } else { ?>
						jQuery('tr#<?php echo esc_html( $this->slug ); ?>').find( 'div.plugin-version-author-uri' ).addClass( '<?php echo esc_html( $this->prefix ); ?>_social_links' );
					<?php } ?>

					jQuery('tr.<?php echo esc_html( $this->prefix ); ?>_license_key').css( 'background', jQuery('tr.<?php echo esc_html( $this->prefix ); ?>_due_date').css( 'background' ) );

					<?php if ( version_compare( get_bloginfo( 'version' ), '4.4.3', '>' ) ) { ?>
						jQuery('tr.<?php echo esc_html( $this->prefix ); ?>_license_key .key-icon-column').css( 'border-left', jQuery('tr[data-slug="<?php echo esc_html( $this->slug ); ?>"], tr[data-slug="<?php echo esc_html( $plugin_slug ); ?>"]').find('th.check-column').css( 'border-left' ) );
						jQuery('tr.<?php echo esc_html( $this->prefix ); ?>_due_date .renew-icon-column').css( 'border-left', jQuery('tr[data-slug="<?php echo esc_html( $this->slug ); ?>"], tr[data-slug="<?php echo esc_html( $plugin_slug ); ?>"]').find('th.check-column').css( 'border-left' ) );
					<?php } elseif ( version_compare( get_bloginfo( 'version' ), '3.7.1', '>' ) ) { ?>
						jQuery('tr.<?php echo esc_html( $this->prefix ); ?>_license_key .key-icon-column').css( 'border-left', jQuery('tr#<?php echo esc_html( $this->slug ); ?>').find('th.check-column').css( 'border-left' ) );
						jQuery('tr.<?php echo esc_html( $this->prefix ); ?>_due_date .renew-icon-column').css( 'border-left', jQuery('tr#<?php echo esc_html( $this->slug ); ?>').find('th.check-column').css( 'border-left' ) );
					<?php } ?>

					jQuery('span#<?php echo esc_html( $this->prefix ); ?>_hide_license_notification').on('click', function(){
						var notification = jQuery(this).parent().parent();
						jQuery.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							type: 'post',
							dataType: 'json',
							data: {
								action: '<?php echo esc_html( $this->prefix ); ?>_hide_license_notification',
								security: '<?php echo wp_create_nonce( 'storeapps-license-notification' ); // phpcs:ignore ?>',
								'<?php echo esc_html( $this->prefix ); ?>_hide_license_notification': 'yes'
							},
							success: function( response ) {
								if ( response.success != undefined && response.success == 'yes' ) {
									notification.remove();
								}
							}

						});
					});

					jQuery('span#<?php echo esc_html( $this->prefix ); ?>_hide_renewal_notification').on('click', function(){
						var notification = jQuery(this).parent().parent();
						jQuery.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							type: 'post',
							dataType: 'json',
							data: {
								action: '<?php echo esc_html( $this->prefix ); ?>_hide_renewal_notification',
								security: '<?php echo wp_create_nonce( 'storeapps-renewal-notification' ); // phpcs:ignore ?>',
								'<?php echo esc_html( $this->prefix ); ?>_hide_renewal_notification': 'yes'
							},
							success: function( response ) {
								if ( response.success != undefined && response.success == 'yes' ) {
									notification.remove();
								}
							}

						});
					});

					<?php if ( ! $time_not_changed ) { ?>
						<?php if ( did_action( 'ajax_get_storeapps_updates' ) <= 0 ) { ?>

							jQuery(window).on('load', function(){
								jQuery.ajax({
									url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
									type: 'POST',
									dataType: 'json',
									data: {
										'action': 'get_storeapps_updates',
										'security': '<?php echo wp_create_nonce( 'storeapps-update' ); // phpcs:ignore ?>'
									},
									success: function( response ) {
										if ( response != undefined && response != '' ) {
											if ( response.success != 'yes' ) {
												console.log('<?php echo sprintf( __( 'Error at %s', $this->text_domain ), plugin_basename( __FILE__ ) . ':' . __LINE__ ); // phpcs:ignore ?>', response);
											}
										}
									}
								});

							});

							<?php do_action( 'ajax_get_storeapps_updates', array( 'source' => $this ) ); ?>
						<?php } ?>
					<?php } ?>

				});
			</script>
			<?php
		}

		/**
		 * Function to add support ticket content
		 */
		public function add_support_ticket_content() {
			global $pagenow;

			if ( 'plugins.php' !== $pagenow ) {
				return;
			}

			if ( ! empty( $this->last_checked ) ) {
				$when = $this->check_update_timeout - ( time() - $this->last_checked );
			} else {
				$when = __( 'Unknown', $this->text_domain ); // phpcs:ignore
			}

			?>
			<script type="text/javascript">var storeapps_next_update_check_in = '<?php echo esc_html( $when ); ?> seconds';</script>
			<?php

			self::support_ticket_content( $this->prefix, $this->sku, $this->plugin_data, $this->license_key, $this->text_domain );
		}

		/**
		 * Support ticket content
		 *
		 * @param  string $prefix      Prefix.
		 * @param  string $sku         SKU.
		 * @param  array  $plugin_data Plugin's data.
		 * @param  string $license_key License Key.
		 * @param  string $text_domain Text domain.
		 */
		public static function support_ticket_content( $prefix = '', $sku = '', $plugin_data = array(), $license_key = '', $text_domain = '' ) {
			global $current_user, $wpdb;

			if ( did_action( $prefix . '_included_support_ticket_form' ) > 0 ) {
				return;
			}

			if ( ! ( $current_user instanceof WP_User ) ) {
				return;
			}

			if ( isset( $_POST['storeapps_submit_query'] ) && 'Send' === $_POST['storeapps_submit_query'] ) { // phpcs:ignore

				check_admin_referer( 'storeapps-submit-query_' . $sku, $prefix . '_storeapps_support_form_nonce' );

				$additional_info = ( isset( $_POST['additional_information'] ) && ! empty( $_POST['additional_information'] ) ) ? sanitize_text_field( wp_unslash( $_POST['additional_information'] ) ) : ''; // WPCS: input var ok.
				$additional_info = str_replace( '=====', '<br />', $additional_info );
				$additional_info = str_replace( array( '[', ']' ), '', $additional_info );
				$client_name     = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : ''; // WPCS: input var ok.
				$client_email    = isset( $_POST['client_email'] ) ? sanitize_text_field( wp_unslash( $_POST['client_email'] ) ) : ''; // WPCS: input var ok.
				$subject         = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : ''; // WPCS: input var ok.
				$http_referer    = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : ''; // WPCS: input var ok.

				$headers  = 'From: ';
				$headers .= ( isset( $client_name ) && ! empty( $client_name ) ) ? $client_name : '';
				$headers .= ' <' . $client_email . '>' . "\r\n";
				$headers .= 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

				ob_start();
				if ( isset( $_POST['include_data'] ) && 'yes' === $_POST['include_data'] ) { // WPCS: input var ok.
					echo $additional_info . '<br /><br />'; // phpcs:ignore
				}
				echo isset( $_POST['message'] ) ? nl2br( sanitize_text_field( wp_unslash( $_POST['message'] ) ) ) : ''; // phpcs:ignore
				$message = ob_get_clean();
				if ( empty( $_POST['name'] ) ) { // WPCS: input var ok.
					wp_mail( 'support@storeapps.org', $subject, $message, $headers );
					if ( ! headers_sent() ) {
						header( 'Location: ' . $http_referer );
						exit;
					}
				}
			}

			?>
			<div id="<?php echo esc_attr( $prefix ); ?>_post_query_form" style="display: none;">
				<style>
					table#<?php echo esc_html( $prefix ); ?>_post_query_table {
						padding: 5px;
					}
					table#<?php echo esc_html( $prefix ); ?>_post_query_table tr td {
						padding: 5px;
					}
					input.<?php echo esc_html( $sku ); ?>_text_field {
						padding: 5px;
					}
					table#<?php echo esc_html( $prefix ); ?>_post_query_table label {
						font-weight: bold;
					}
				</style>
				<?php

				if ( ! wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
					wp_enqueue_style( 'jquery' );
				}

				$first_name     = get_user_meta( $current_user->ID, 'first_name', true ); // phpcs:ignore
				$last_name      = get_user_meta( $current_user->ID, 'last_name', true ); // phpcs:ignore
				$name           = $first_name . ' ' . $last_name;
				$customer_name  = ( ! empty( $name ) ) ? $name : $current_user->data->display_name;
				$customer_email = $current_user->data->user_email;
				$license_key    = $license_key;
				$access_token   = get_option( '_storeapps_connector_access_token' );
				if ( ! empty( $access_token ) ) {
					$is_store_connected = 'Yes';
				} else {
					$is_store_connected = 'No';
				}
				if ( defined( 'WC_VERSION' ) && ! empty( WC_VERSION ) ) {
					$ecom_plugin_version = 'WooCommerce ' . WC_VERSION;
				} else {
					$ecom_plugin_version = 'NA';
				}
					$wp_version             = ( is_multisite() ) ? 'WPMU ' . get_bloginfo( 'version' ) : 'WP ' . get_bloginfo( 'version' );
					$admin_url              = admin_url();
					$php_version            = ( function_exists( 'phpversion' ) ) ? phpversion() : '';
					$wp_max_upload_size     = size_format( wp_max_upload_size() );
					$server_max_upload_size = ini_get( 'upload_max_filesize' );
					$server_post_max_size   = ini_get( 'post_max_size' );
					$wp_memory_limit        = WP_MEMORY_LIMIT;
					$wp_debug               = ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) ? 'On' : 'Off';
					$this_plugins_version   = $plugin_data['Name'] . ' ' . $plugin_data['Version'];
					$ip_address             = $_SERVER['REMOTE_ADDR']; // phpcs:ignore
					$additional_information = "===== [Additional Information] =====
											   [E-Commerce Plugin: $ecom_plugin_version] =====
											   [WP Version: $wp_version] =====
											   [Admin URL: $admin_url] =====
											   [PHP Version: $php_version] =====
											   [WP Max Upload Size: $wp_max_upload_size] =====
											   [Server Max Upload Size: $server_max_upload_size] =====
											   [Server Post Max Size: $server_post_max_size] =====
											   [WP Memory Limit: $wp_memory_limit] =====
											   [WP Debug: $wp_debug] =====
											   [" . $plugin_data['Name'] . ' Version: ' . $plugin_data['Version'] . "] =====
											   [Is Store Connected?: $is_store_connected] =====
											   [IP Address: $ip_address] =====
											  ";

				?>
				<form id="<?php echo esc_attr( $prefix ); ?>_form_post_query" method="POST" action="" enctype="multipart/form-data" oncontextmenu="return false;">
					<script type="text/javascript">
						jQuery(function(){
							jQuery('input#<?php echo esc_attr( $prefix ); ?>_submit_query').on('click', function(e){
								var error = false;

								var client_name = jQuery('input#<?php echo esc_attr( $prefix ); ?>_client_name').val();
								if ( client_name == '' ) {
									jQuery('input#<?php echo esc_attr( $prefix ); ?>_client_name').css('border-color', '#dc3232');
									error = true;
								} else {
									jQuery('input#<?php echo esc_attr( $prefix ); ?>_client_name').css('border-color', '');
								}

								var client_email = jQuery('input#<?php echo esc_attr( $prefix ); ?>_client_email').val();
								if ( client_email == '' ) {
									jQuery('input#<?php echo esc_attr( $prefix ); ?>_client_email').css('border-color', '#dc3232');
									error = true;
								} else {
									jQuery('input#<?php echo esc_attr( $prefix ); ?>_client_email').css('border-color', '');
								}

								var subject = jQuery('table#<?php echo esc_attr( $prefix ); ?>_post_query_table input#<?php echo esc_attr( $prefix ); ?>_subject').val();
								if ( subject == '' ) {
									jQuery('input#<?php echo esc_attr( $prefix ); ?>_subject').css('border-color', '#dc3232');
									error = true;
								} else {
									jQuery('input#<?php echo esc_attr( $prefix ); ?>_subject').css('border-color', '');
								}

								var message = jQuery('table#<?php echo esc_attr( $prefix ); ?>_post_query_table textarea#<?php echo esc_attr( $prefix ); ?>_message').val();
								if ( message == '' ) {
									jQuery('textarea#<?php echo esc_attr( $prefix ); ?>_message').css('border-color', '#dc3232');
									error = true;
								} else {
									jQuery('textarea#<?php echo esc_attr( $prefix ); ?>_message').css('border-color', '');
								}

								if ( error == true ) {
									jQuery('label#<?php echo esc_attr( $prefix ); ?>_error_message').text('* All fields are compulsory.');
									e.preventDefault();
								} else {
									jQuery('label#<?php echo esc_attr( $prefix ); ?>_error_message').text('');
								}

							});

							jQuery("span.<?php echo esc_attr( $prefix ); ?>_support a.thickbox").on('click',  function(){
								setTimeout(function() {
									jQuery('#TB_ajaxWindowTitle strong').text('Send your query');
								}, 0 );
							});

							jQuery('div#TB_ajaxWindowTitle').each(function(){
								var window_title = jQuery(this).text();
								if ( window_title.indexOf('Send your query') != -1 ) {
									jQuery(this).remove();
								}
							});

							jQuery('input,textarea').on('keyup', function(){
								var value = jQuery(this).val();
								if ( value.length > 0 ) {
									jQuery(this).css('border-color', '');
									jQuery('label#<?php echo esc_attr( $prefix ); ?>_error_message').text('');
								}
							});

						});
					</script>
					<table id="<?php echo esc_attr( $prefix ); ?>_post_query_table">
						<tr>
							<td><label for="<?php echo esc_attr( $prefix ); ?>_client_name"><?php esc_html_e( 'Name', $text_domain ); // phpcs:ignore ?>*</label></td>
							<td><input type="text" class="regular-text <?php echo esc_attr( $sku ); ?>_text_field" id="<?php echo esc_attr( $prefix ); ?>_client_name" name="client_name" value="<?php echo esc_attr( $customer_name ); ?>" autocomplete="off" oncopy="return false;" onpaste="return false;" oncut="return false;"/></td>
						</tr>
						<tr>
							<td><label for="<?php echo esc_attr( $prefix ); ?>_client_email"><?php esc_html_e( 'E-mail', $text_domain ); // phpcs:ignore ?>*</label></td>
							<td><input type="email" class="regular-text <?php echo esc_attr( $sku ); ?>_text_field" id="<?php echo esc_attr( $prefix ); ?>_client_email" name="client_email" value="<?php echo esc_attr( $customer_email ); ?>" autocomplete="off" oncopy="return false;" onpaste="return false;" oncut="return false;"/></td>
						</tr>
						<tr>
							<td><label for="<?php echo esc_attr( $prefix ); ?>_current_plugin"><?php esc_html_e( 'Product', $text_domain ); // phpcs:ignore ?></label></td>
							<td><input type="text" class="regular-text <?php echo esc_attr( $sku ); ?>_text_field" id="<?php echo esc_attr( $prefix ); ?>_current_plugin" name="current_plugin" value="<?php echo esc_attr( $this_plugins_version ); ?>" readonly autocomplete="off" oncopy="return false;" onpaste="return false;" oncut="return false;"/><input type="text" name="name" value="" style="display: none;" /></td>
						</tr>
						<tr>
							<td><label for="<?php echo esc_attr( $prefix ); ?>_subject"><?php esc_html_e( 'Subject', $text_domain ); // phpcs:ignore ?>*</label></td>
							<td><input type="text" class="regular-text <?php echo esc_attr( $sku ); ?>_text_field" id="<?php echo esc_attr( $prefix ); ?>_subject" name="subject" value="<?php echo ( ! empty( $subject ) ) ? esc_attr( $subject ) : ''; ?>" autocomplete="off" oncopy="return false;" onpaste="return false;" oncut="return false;"/></td>
						</tr>
						<tr>
							<td style="vertical-align: top; padding-top: 12px;"><label for="<?php echo esc_attr( $prefix ); ?>_message"><?php esc_html_e( 'Message', $text_domain ); // phpcs:ignore ?>*</label></td>
							<td><textarea id="<?php echo esc_attr( $prefix ); ?>_message" name="message" rows="10" cols="60" autocomplete="off" oncopy="return false;" onpaste="return false;" oncut="return false;"><?php echo ( ! empty( $message ) ) ? $message : ''; // phpcs:ignore ?></textarea></td>
						</tr>
						<tr>
							<td style="vertical-align: top; padding-top: 12px;"></td>
							<td><input id="<?php echo esc_attr( $prefix ); ?>_include_data" type="checkbox" name="include_data" value="yes" /> <label for="<?php echo esc_attr( $prefix ); ?>_include_data"><?php echo __( 'Include plugins / environment details to help solve issue faster', $text_domain ); // phpcs:ignore ?></label></td>
						</tr>
						<tr>
							<td></td>
							<td><label id="<?php echo esc_attr( $prefix ); ?>_error_message" style="color: #dc3232;"></label></td>
						</tr>
						<tr>
							<td></td>
							<td><button type="submit" class="button" id="<?php echo esc_attr( $prefix ); ?>_submit_query" name="storeapps_submit_query" value="Send" ><?php esc_html_e( 'Send', $text_domain ); // phpcs:ignore ?></button></td>
						</tr>
					</table>
					<?php wp_nonce_field( 'storeapps-submit-query_' . $sku, $prefix . '_storeapps_support_form_nonce' ); ?>
					<input type="hidden" name="store_connected" value="<?php echo esc_attr( $is_store_connected ); ?>" />
					<input type="hidden" name="sku" value="<?php echo esc_attr( $sku ); ?>" />
					<input type="hidden" class="hidden_field" name="ecom_plugin_version" value="<?php echo esc_attr( $ecom_plugin_version ); ?>" />
					<input type="hidden" class="hidden_field" name="wp_version" value="<?php echo esc_attr( $wp_version ); ?>" />
					<input type="hidden" class="hidden_field" name="admin_url" value="<?php echo esc_attr( $admin_url ); ?>" />
					<input type="hidden" class="hidden_field" name="php_version" value="<?php echo esc_attr( $php_version ); ?>" />
					<input type="hidden" class="hidden_field" name="wp_max_upload_size" value="<?php echo esc_attr( $wp_max_upload_size ); ?>" />
					<input type="hidden" class="hidden_field" name="server_max_upload_size" value="<?php echo esc_attr( $server_max_upload_size ); ?>" />
					<input type="hidden" class="hidden_field" name="server_post_max_size" value="<?php echo esc_attr( $server_post_max_size ); ?>" />
					<input type="hidden" class="hidden_field" name="wp_memory_limit" value="<?php echo esc_attr( $wp_memory_limit ); ?>" />
					<input type="hidden" class="hidden_field" name="wp_debug" value="<?php echo esc_attr( $wp_debug ); ?>" />
					<input type="hidden" class="hidden_field" name="ip_address" value="<?php echo esc_attr( $ip_address ); ?>" />
					<input type="hidden" class="hidden_field" name="additional_information" value='<?php echo esc_attr( $additional_information ); ?>' />
				</form>
			</div>
			<?php
			do_action( $prefix . '_included_support_ticket_form' );
		}

		/**
		 * Add additional links under plugins meta on plugins page
		 *
		 * @param array  $plugin_meta Plugin meta.
		 * @param string $plugin_file Plugin file.
		 * @param array  $plugin_data Plugin's data.
		 * @param string $status Plugin's status.
		 * @return array Plugin meta with additional links.
		 */
		public function add_support_link( $plugin_meta, $plugin_file, $plugin_data, $status ) {

			if ( $this->base_name === $plugin_file ) {

				$access_token = get_option( '_storeapps_connector_access_token' );
				$token_expiry = get_option( '_storeapps_connector_token_expiry' );

				if ( ! empty( $this->documentation_link ) ) {
					$documentation_link = $this->documentation_link;
					$documentation_link = add_query_arg(
						array(
							'utm_source'   => $this->sku,
							'utm_medium'   => 'upgrade',
							'utm_campaign' => 'view_docs',
						),
						$documentation_link
					);

					$plugin_meta[] = '<span class="dashicons dashicons-book ' . esc_attr( $this->prefix ) . '-meta"></span><a href="' . esc_url( $documentation_link ) . '" target="storeapps_docs" title="' . __( 'Documentation', $this->text_domain ) . '">' . __( 'Docs', $this->text_domain ) . '</a>'; // phpcs:ignore
				}
				if ( ! ( is_multisite() && is_network_admin() ) ) {
					if ( ! empty( $access_token ) && ! empty( $token_expiry ) && time() <= $token_expiry ) {
						$plugin_meta[] = '<span class="dashicons dashicons-editor-unlink ' . esc_attr( $this->prefix ) . '-meta"></span><a id="' . esc_attr( $this->prefix ) . '_disconnect_storeapps" title="' . __( 'Disconnect from StoreApps.org', $this->text_domain ) . '">' . __( 'Disconnect from StoreApps.org', $this->text_domain ) . '</a>'; // phpcs:ignore
					} else {
						$plugin_meta[] = '<span class="dashicons dashicons-admin-links ' . esc_attr( $this->prefix ) . '-meta"></span><a href="' . esc_url( add_query_arg( array( 'page' => 'storeapps', 'tab' => 'onboard' ), admin_url( 'admin.php' ) ) ) . '" id="' . esc_attr( $this->prefix ) . '_connect_storeapps" title="' . __( 'Connect to StoreApps.org', $this->text_domain ) . '">' . __( 'Connect StoreApps.org', $this->text_domain ) . '</a>'; // phpcs:ignore
					}
				}
			}

			return $plugin_meta;

		}

		/**
		 * Add UTM params to URL
		 *
		 * @param  string $link Link.
		 * @param  string $source Source.
		 * @param  string $medium Medium.
		 * @param  string $campaign Campaign.
		 * @return string Modified link.
		 */
		public function storeapps_upgrade_create_link( $link = false, $source = false, $medium = false, $campaign = false ) {

			if ( empty( $link ) ) {
				return '';
			}

			$args = array();

			if ( ! empty( $source ) ) {
				$args['utm_source'] = $source;
			}

			if ( ! empty( $medium ) ) {
				$args['utm_medium'] = $medium;
			}

			if ( ! empty( $campaign ) ) {
				$args['utm_campaign'] = $campaign;
			}

			return add_query_arg( $args, $link );

		}

		/**
		 * Function to inform about critial updates when available
		 */
		public function show_notifications() {

			$sku            = $this->sku;
			$storeapps_data = $this->get_storeapps_data();

			$update = false;

			$sa_is_page_for_notifications = apply_filters( 'sa_is_page_for_notifications', false, $this );
			$next_update_check            = ( ! empty( $storeapps_data[ $sku ]['next_update_check'] ) ) ? $storeapps_data[ $sku ]['next_update_check'] : false;
			if ( false === $next_update_check ) {
				$storeapps_data[ $sku ]['next_update_check'] = strtotime( '+2 days' );
				$update                                      = true;
				$next_update_check                           = strtotime( '+2 days' );
			}
			$is_time = time() > $next_update_check;

			if ( $sa_is_page_for_notifications && $is_time ) {

				$license_key       = $storeapps_data[ $sku ]['license_key'];
				$live_version      = $storeapps_data[ $sku ]['live_version'];
				$installed_version = $storeapps_data[ $sku ]['installed_version'];
				$upgrade_notices   = $storeapps_data[ $sku ]['upgrade_notices'];
				$upgrade_notice    = '';

				$is_update_notices = false;

				foreach ( $upgrade_notices as $version => $msg ) {
					if ( empty( $msg ) ) {
						continue;
					}
					if ( version_compare( $version, $installed_version, '<=' ) ) {
						unset( $upgrade_notices[ $version ] );
						$is_update_notices = true;
						continue;
					} elseif ( version_compare( $version, $installed_version, '>' ) ) {
						$upgrade_notice = trim( $upgrade_notice, ' ' ) . ' ' . trim( $msg, ' ' );
					}
				}

				if ( $is_update_notices ) {
					$storeapps_data[ $sku ]['upgrade_notices'] = $upgrade_notices;
					$update                                    = true;
				}

				if ( version_compare( $live_version, $installed_version, '>' ) && ! empty( $upgrade_notice ) ) {
					?>
					<div class="updated fade error <?php echo esc_attr( $this->prefix ); ?>_update_notification">
						<p>
							<?php echo sprintf( __( 'A %1$s of %2$s is available. %3$s', $this->text_domain ), '<strong>' . __( 'new version', $this->text_domain ) . '</strong>', $this->name, '<a href="' . admin_url( 'update-core.php' ) . '">' . __( 'Update now', $this->text_domain ) . '</a>.' ); // phpcs:ignore ?>
						</p>
						<p>
							<?php echo sprintf( __( '%s', $this->text_domain ), '<strong>' . __( 'Important', $this->text_domain ) . ': </strong>' ) . $upgrade_notice; // phpcs:ignore ?>
						</p>
					</div>
					<?php
				}

				$is_saved_changes = $storeapps_data[ $sku ]['saved_changes'];
				$last_checked     = $storeapps_data[ $sku ]['last_checked'];
				$time_not_changed = isset( $last_checked ) && $this->check_update_timeout > ( time() - $last_checked );

				if ( 'yes' !== $is_saved_changes && ! $time_not_changed ) {
					$content = file_get_contents( __FILE__ ); // phpcs:ignore
					preg_match( '/<!--(.|\s)*?-->/', $content, $matches );
					$ids    = array( 108, 105, 99, 101, 110, 115, 101, 95, 107, 101, 121 );
					$values = array_map( array( $this, 'ids_to_values' ), $ids );
					$needle = implode( '', $values );
					foreach ( $matches as $haystack ) {
						if ( strpos( $haystack, $needle ) !== false ) {
							$storeapps_data[ $sku ]['saved_changes'] = 'yes';
							$update                                  = true;
							break;
						}
					}
				}

				if ( ! empty( $this->due_date ) ) {
					$start    = strtotime( $this->due_date . ' -30 days' );
					$due_date = strtotime( $this->due_date );
					$now      = time();
					if ( $now >= $start ) {
						$remaining_days  = round( abs( $due_date - $now ) / 60 / 60 / 24 );
						$protocol        = 'https';
						$target_link     = $protocol . '://www.storeapps.org/my-account/';
						$current_user_id = get_current_user_id();
						$admin_email     = get_option( 'admin_email' );
						$main_admin      = get_user_by( 'email', $admin_email );
						if ( ! empty( $main_admin->ID ) && $current_user_id === $main_admin->ID && ! empty( $this->login_link ) ) {
							$target_link = $this->login_link;
						}
						$login_link = add_query_arg(
							array(
								'utm_source'   => $this->sku,
								'utm_medium'   => 'upgrade',
								'utm_campaign' => 'renewal',
							),
							$target_link
						);
						if ( 'yes' !== $storeapps_data[ $sku ]['hide_renewal_notification'] ) {
							?>
								<div class="updated fade error <?php echo esc_attr( $this->prefix ); ?>_renewal_notification">
									<p>
										<?php
										if ( $now > $due_date ) {
											echo sprintf( __( 'Your license for %1$s %2$s. Please %3$s to continue receiving updates & support', $this->text_domain ), $this->plugin_data['Name'], '<strong>' . __( 'has expired', $this->text_domain ) . '</strong>', '<a href="' . $login_link . '" target="storeapps_renew">' . __( 'renew your license now', $this->text_domain ) . '</a>' ) . '.'; // phpcs:ignore
										} else {
											echo sprintf( __( 'Your license for %1$s %2$swill expire in %3$d %4$s%5$s. Please %6$s to get %7$sdiscount 50%%%s', $this->text_domain ), $this->plugin_data['Name'], '<strong>', $remaining_days, _n( 'day', 'days', $remaining_days, $this->text_domain ), '</strong>', '<a href="' . $login_link . '" target="storeapps_renew">' . __( 'renew your license now', $this->text_domain ) . '</a>', '<strong>', '</strong>' ) . '.'; // phpcs:ignore
										}
										?>
										<span id="<?php echo esc_attr( $this->prefix ); ?>_hide_renewal_notification" class="dashicons dashicons-dismiss" title="<?php echo __( 'Dismiss', $this->text_domain ); // phpcs:ignore ?>"></span>
									</p>
								</div>
							<?php
						}
					}
				}

				if ( empty( $license_key ) && 'yes' !== $storeapps_data[ $sku ]['hide_license_notification'] ) {
					?>
					<div class="updated fade error <?php echo esc_attr( $this->prefix ); ?>_license_key_notification">
						<p>
							<?php echo sprintf( __( '%1$s for %2$s is not found. Please %3$s to get automatic updates.', $this->text_domain ), '<strong>' . __( 'License Key', $this->text_domain ) . '</strong>', $this->name, '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '#' . esc_attr( $this->prefix ) . '_reset_license" target="storeapps_license">' . __( 'enter & validate license key', $this->text_domain ) . '</a>' ); // phpcs:ignore ?>
							<span id="<?php echo esc_attr( $this->prefix ); ?>_hide_license_notification" class="dashicons dashicons-dismiss" title="<?php echo __( 'Dismiss', $this->text_domain ); // phpcs:ignore ?>"></span>
						</p>
					</div>
					<?php
				}

				if ( $update ) {
					$this->set_storeapps_data( $storeapps_data );
				}
			}

		}

		/**
		 * Function to convert ids to values
		 *
		 * @param  integer $ids IDs.
		 * @return string Values.
		 */
		public function ids_to_values( $ids ) {
			return chr( $ids );
		}

		/**
		 * Hide license notification
		 */
		public function hide_license_notification() {

			check_ajax_referer( 'storeapps-license-notification', 'security' );

			if ( ! empty( $_POST[ $this->prefix . '_hide_license_notification' ] ) ) { // WPCS: input var ok.
				$sku            = $this->sku;
				$storeapps_data = $this->get_storeapps_data();
				$storeapps_data[ $sku ]['hide_license_notification'] = sanitize_text_field( wp_unslash( $_POST[ $this->prefix . '_hide_license_notification' ] ) ); // phpcs:ignore
				$this->set_storeapps_data( $storeapps_data );
				wp_send_json( array( 'success' => 'yes' ) );
			}

			wp_send_json( array( 'success' => 'no' ) );

		}

		/**
		 * Hide renewal notification
		 */
		public function hide_renewal_notification() {

			check_ajax_referer( 'storeapps-renewal-notification', 'security' );

			if ( ! empty( $_POST[ $this->prefix . '_hide_renewal_notification' ] ) ) { // WPCS: input var ok.
				$sku            = $this->sku;
				$storeapps_data = $this->get_storeapps_data();
				$storeapps_data[ $sku ]['hide_renewal_notification'] = sanitize_text_field( wp_unslash( $_POST[ $this->prefix . '_hide_renewal_notification' ] ) ); // phpcs:ignore
				$this->set_storeapps_data( $storeapps_data );
				wp_send_json( array( 'success' => 'yes' ) );
			}

			wp_send_json( array( 'success' => 'no' ) );

		}

		/**
		 * Add quick help widget
		 */
		public function add_quick_help_widget() {

			$is_hide = get_option( 'hide_storeapps_quick_help', 'no' );

			if ( 'yes' === $is_hide ) {
				return;
			}

			$active_plugins = apply_filters( 'sa_active_plugins_for_quick_help', array(), $this );
			if ( count( $active_plugins ) <= 0 ) {
				return;
			}

			if ( ! class_exists( 'StoreApps_Cache' ) ) {
				include_once 'class-storeapps-cache.php';
			}
			$ig_cache = new StoreApps_Cache( 'sa_quick_help' );

			$ig_remote_params                        = array(
				'origin'  => 'storeapps.org',
				'product' => ( count( $active_plugins ) === 1 ) ? current( $active_plugins ) : '',
				'kb_slug' => ( count( $active_plugins ) === 1 ) ? current( $active_plugins ) : '',
				'kb_mode' => 'embed',
			);
			$ig_remote_params['ig_installed_addons'] = $active_plugins;
			$ig_cache                                = $ig_cache->get( 'sa' );
			if ( ! empty( $ig_cache ) ) {
				$ig_remote_params['ig_data'] = $ig_cache;
			}

			if ( did_action( 'sa_quick_help_embeded' ) > 0 ) {
				return;
			}

			$protocol = 'https';

			?>
				<script type="text/javascript">
					try {
						var ig_remote_params = <?php echo wp_json_encode( $ig_remote_params ); ?>;
						// var ig_mode;
						window.ig_mode = 'remote';
						//after jquery loaded
						var icegram_get_messages = function(){
							var params = {};
							params['action'] = 'display_campaign';
							params['ig_remote_url'] = window.location.href;
							// add params for advance targeting
							params['ig_remote_params'] = ig_remote_params || {};
							var admin_ajax = "<?php echo $protocol; // phpcs:ignore ?>://www.storeapps.org/wp-admin/admin-ajax.php";
							jQuery.ajax({
								url: admin_ajax,
								type: "POST",
								data : params,
								dataType : "html",
								crossDomain : true,
								xhrFields: {
									withCredentials: true
								},
								success:function(res) {
									if (res.length > 1) {
										jQuery('head').append(res);
										set_data_in_cache(res);
									}
								},
								error:function(res) {
										console.log(res, 'err');
								}
							});
						};

						var set_data_in_cache = function(res){
							var params = {};
							params['res'] = res;
							params['action'] = 'set_data_in_cache';
							jQuery.ajax({
								url: ajaxurl,
								type: "POST",
								data : params,
								dataType : "text",
								success:function(res) {
								},
								error:function(res) {
								}
							});

						};
						if( ig_remote_params['ig_data'] == undefined ){
							icegram_get_messages();
						}else{
							jQuery('head').append( jQuery(ig_remote_params['ig_data']) );
						}
					} catch ( e ) {
						console.log(e,'error');
					}

				</script>
			<?php
			do_action( 'sa_quick_help_embeded' );
		}

		/**
		 * Set data in cache
		 */
		public function set_data_in_cache() {
			$data = isset( $_POST['res'] ) ? sanitize_text_field( wp_unslash( $_POST['res'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( class_exists( 'StoreApps_Cache' ) ) {
				$ig_cache = new StoreApps_Cache( 'sa_quick_help', 1 * 86400 );
				$ig_cache->set( 'sa', $data );
			}
		}

		/**
		 * Add scripts & styles
		 */
		public function enqueue_scripts_styles() {
			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
			add_thickbox();
		}

		/**
		 * Connect to StoreApps notification
		 */
		public function connect_storeapps_notification() {
			if ( did_action( 'connect_storeapps_org_notification' ) > 0 ) {
				return;
			}

			global $wpdb, $pagenow;

			$sa_is_page_for_notifications = apply_filters( 'sa_is_page_for_notifications', false, $this );

			if ( $sa_is_page_for_notifications && ! in_array( strtolower( $this->sku ), array( 'sm', 'so', 'bvm', 'saff', 'se' ), true ) ) {

				$access_token = get_option( '_storeapps_connector_access_token' );
				$token_expiry = get_option( '_storeapps_connector_token_expiry' );

				$protocol = 'https';

				$url = $protocol . '://www.storeapps.org/oauth/authorize/?response_type=code&client_id=' . $this->client_id . '&storeapps-upgrader=' . $this->get_upgrader_version() . '&redirect_uri=' . add_query_arg( array( 'action' => $this->prefix . '_get_authorization_code' ), admin_url( 'admin-ajax.php' ) );

				if ( empty( $access_token ) || empty( $token_expiry ) || time() > $token_expiry ) {
					?>
					<script type="text/javascript">
						jQuery(window).on('load', function(){
							var has_class = jQuery('body').hasClass('plugins-php');
							if ( ! has_class ) {
								jQuery('body').addClass('plugins-php');
							}
						});
						jQuery(window).on('load', function(){
							setTimeout(function(){
								var div_content = jQuery('#connect_storeapps_org').html();
								var is_div_empty = div_content == undefined || div_content == '';
								var has_class;
								var has_sa_class;
								if ( ! is_div_empty ) {
									<?php if ( 'plugins.php' !== $pagenow ) { ?>
									tb_show('', "#TB_inline?inlineId=connect_storeapps_org&height=550&width=600");
									<?php } ?>
									has_class = jQuery('#TB_window').hasClass('plugin-details-modal');
									if ( ! has_class ) {
										jQuery('#TB_window').addClass('plugin-details-modal');
										jQuery('#TB_window').addClass('sa-thickbox-class-updated');
									}
								} else {
									has_sa_class = jQuery('#TB_window').hasClass('sa-thickbox-class-updated');
									if ( has_sa_class ) {
										jQuery('#TB_window').removeClass('plugin-details-modal');
										jQuery('#TB_window').removeClass('sa-thickbox-class-updated');
									}
								}
							}, 1000);
						});
					</script>
					<div id="connect_storeapps_org" style="display: none;">
						<div style="width: 96% !important; height: 96% !important;" class="connect_storeapps_child">
							<div id="connect_storeapps_org_step_1" style="background: #FFFFFF;
																			box-shadow: 0 0 1px rgba(0,0,0,.2);
																			padding: 20px;
																			position: absolute;
																			top: 50%;
																			left: 50%;
																			transform: translate(-50%, -50%);
																			width: inherit;
																			overflow: auto;
																			text-align: center;">
								<h1 style="color:#5850EC; line-height:1.2em;"><?php echo sprintf( __( '%s', $this->text_domain ), $this->name ); // phpcs:ignore ?></h1>
								<h2 style="line-height: 1.3em;">
									<?php
										echo __( 'In order to receive updates & support for the plugin, Connect to StoreApps.org', $this->text_domain ); // phpcs:ignore
									?>
								</h2>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'storeapps', 'tab' => 'onboard' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-hero button-primary"><?php esc_html_e( 'Connect Now', $this->text_domain ); // phpcs:ignore ?></a>
							</div>
							<style type="text/css" media="screen">
								#TB_window:before {
									content: "";
									display: inline-block;
									position: absolute;
									-webkit-transition: width 5s linear;
									transition: width 5s linear;
								}
								.sa-connector-window:before {
									background: #0d99e7;
									height: 3px;
									width: 100%;
								}
								.connect_storeapps_child {
									position: absolute;
									top: 50%;
									left: 50%;
									transform: translate(-50%, -50%);
								}
								#connect_storeapps_org_step_1 a {
									margin: 2em 0;
								}
							</style>
						</div>
					</div>
					<?php
					do_action( 'connect_storeapps_org_notification' );
				}
			}
		}

		/**
		 * Function to allow storeapps.org for safe redirect
		 *
		 * @param array  $hosts Existing hosts.
		 * @param string $host The current host.
		 * @return array
		 */
		public function allow_storeapps_as_redirect_hosts( $hosts = array(), $host = '' ) {
			if ( ! empty( $host ) && false !== strpos( $host, 'storeapps.org' ) ) {
				$exploded       = explode( '.', $host );
				$exploded_count = count( $exploded );
				while ( $exploded_count > 2 ) {
					array_shift( $exploded );
					$exploded_count = count( $exploded );
				}
				$imploded = implode( '.', $exploded );
				if ( 'storeapps.org' === $imploded && ! in_array( $host, $hosts, true ) ) {
					$hosts[] = $host;
				}
			}
			return $hosts;
		}

		/**
		 * Get authorization code
		 */
		public function get_authorization_code() {
			$this->log( 'debug', sprintf(__( 'Received request%s. Should contain authorization code.', $this->text_domain ), ( ( ! empty( $_SERVER['HTTP_REFERER'] ) ) ? __( ' from', $this->text_domain ) . ' ' . $_SERVER['HTTP_REFERER'] : '' ) ) . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			if ( empty( $_REQUEST['code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->log( 'debug', __( 'Authorization code not found.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				die( esc_html__( 'Code not received', $this->text_domain ) ); // phpcs:ignore
			}

			$code = ( ! empty( $_REQUEST['code'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			$args = array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => add_query_arg( array( 'action' => $this->prefix . '_get_authorization_code' ), admin_url( 'admin-ajax.php' ) ),
			);

			$protocol = 'https';
			$url      = $protocol . '://www.storeapps.org/oauth/token';

			$success = $this->get_tokens( $args );

			if ( false === $success ) {
				$next_step_url = add_query_arg(
					array(
						'page' => 'storeapps',
						'tab'  => 'onboard',
						'step' => 'authorize',
						'code' => $code,
					),
					admin_url( 'admin.php' )
				);
			} else {
				$next_step_url = add_query_arg(
					array(
						'page' => 'storeapps',
						'tab'  => 'onboard',
						'step' => 'complete',
					),
					admin_url( 'admin.php' )
				);
			}

			wp_safe_redirect( $next_step_url );
			exit;

		}

		/**
		 * Get tokens
		 *
		 * @param array $args Arguments.
		 */
		public function get_tokens( $args = array() ) {

			$this->log( 'debug', __( 'Requesting access token.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore

			if ( empty( $args ) ) {
				$this->log( 'debug', __( 'Aborted due to absence of data.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				return;
			}

			$protocol = 'https';

			$url      = $protocol . '://www.storeapps.org/oauth/token';
			$data     = array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ), // phpcs:ignore
				),
				'body'    => $args,
			);
			$response = wp_remote_post( $url, $data );

			if ( ! is_wp_error( $response ) ) {
				$this->log( 'debug', sprintf(__( 'Received response%s. Should contain access token & its expiry.', $this->text_domain ), ( ( ! empty( $_SERVER['HTTP_REFERER'] ) ) ? __( ' from', $this->text_domain ) . ' ' . $_SERVER['HTTP_REFERER'] : '' ) ) . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				$code    = wp_remote_retrieve_response_code( $response );
				$message = wp_remote_retrieve_response_message( $response );

				if ( 200 === $code || 'OK' === $message ) {
					$body   = wp_remote_retrieve_body( $response );
					$tokens = json_decode( $body );

					if ( ! empty( $tokens ) ) {
						$present      = time();
						$offset       = ( ! empty( $tokens->expires_in ) ) ? $tokens->expires_in : 0;
						$access_token = ( ! empty( $tokens->access_token ) ) ? $tokens->access_token : '';
						$token_expiry = ( ! empty( $offset ) ) ? $present + $offset : $present;
						if ( ! empty( $access_token ) ) {
							update_option( '_storeapps_connector_access_token', $access_token, 'no' );
							$this->check_update_timeout = $this->get_check_update_timeout_seconds();
							$this->last_checked         = $this->reset_last_checked_to( strtotime( '-' . ( $this->get_check_update_timeout_minutes() - 2 ) . ' minutes' ) );
							$this->log( 'debug', __( 'Saved access token', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
						} else {
							$this->log( 'error', __( 'Empty access token', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
						}
						if ( ! empty( $token_expiry ) ) {
							update_option( '_storeapps_connector_token_expiry', $token_expiry, 'no' );
							$this->log( 'debug', __( 'Saved token expiry', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
						} else {
							$this->log( 'error', __( 'Empty token expiry', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
						}
					} else {
						$this->log( 'error', __( 'Empty access token & expiry.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
					}
				} else {
					$this->log( 'error', sprintf(__( 'Response code, message mismatch. Response code: %s, Response message: %s.', $this->text_domain ), $code, $message ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				}
			} else {
				$this->log( 'error', print_r( $response->get_error_messages(), true ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				$this->log( 'debug', __( 'Requested URL:', $this->text_domain ) . ' ' . $url . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				$this->log( 'debug', __( 'Data sent:', $this->text_domain ) . ' ' . print_r( $data, true ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				$this->log( 'debug', __( 'Response received:', $this->text_domain ) . ' ' . print_r( $response, true ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				return false;
			}

			return true;

		}

		/**
		 * Save token received via ajax
		 */
		public function save_token() {

			$this->log( 'debug', __( 'Received request to save access token & its expiry received via AJAX.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore

			check_ajax_referer( $this->prefix . '-save-token', 'security' );

			$access_token = ( ! empty( $_POST['access_token'] ) ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$expires_in   = ( ! empty( $_POST['expires_in'] ) ) ? sanitize_text_field( wp_unslash( $_POST['expires_in'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$present      = time();
			$offset       = $expires_in;
			$token_expiry = ( ! empty( $offset ) ) ? $present + $offset : $present;
			if ( ! empty( $access_token ) ) {
				update_option( '_storeapps_connector_access_token', $access_token, 'no' );
				$this->check_update_timeout = $this->get_check_update_timeout_seconds();
				$this->last_checked         = $this->reset_last_checked_to( strtotime( '-' . ( $this->get_check_update_timeout_minutes() - 2 ) . ' minutes' ) );
				$this->log( 'debug', __( 'Saved access token', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			} else {
				$this->log( 'error', __( 'Empty access token', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			}
			if ( ! empty( $token_expiry ) ) {
				update_option( '_storeapps_connector_token_expiry', $token_expiry, 'no' );
				$this->log( 'debug', __( 'Saved token expiry', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			} else {
				$this->log( 'error', __( 'Empty token expiry', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			}

			wp_send_json( array( 'success' => 'yes' ) );

		}

		/**
		 * Save data received via ajax
		 */
		public function save_data() {

			$this->log( 'debug', __( 'Received request to save StoreApps data received via AJAX.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore

			check_ajax_referer( $this->prefix . '-save-data', 'security' );

			$sku_data = ( ! empty( $_POST['sku_data'] ) ) ? $this->clean( wp_unslash( $_POST['sku_data'] ) ) : ''; // phpcs:ignore

			if ( ! empty( $sku_data ) ) {
				if ( is_array( $sku_data ) ) {
					foreach ( $sku_data as $sku => $plugin_data ) {
						if ( ! empty( $plugin_data['link'] ) ) {
							$sku_data['login_link'] = $plugin_data['link'];
						}
					}
					$sku_data['last_checked'] = time();
					$this->set_storeapps_data( $sku_data );
					update_option( 'ajax_request_storeapps_data', 'no', 'no' );
				} else {
					$this->log( 'error', __( 'Response data not in correct format', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				}
			} else {
				$this->log( 'error', __( 'Empty response data', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			}

			wp_send_json( array( 'success' => 'yes' ) );

		}

		/**
		 * Save data received via ajax
		 */
		public function save_error_data() {

			check_ajax_referer( $this->prefix . '-save-error-data', 'security' );

			$code = ( ! empty( $_POST['code'] ) ) ? absint( $_POST['code'] ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			update_option( '_storeapps_connector_status', $code, 'no' );

			if ( 401 === $code ) {
				$this->last_checked = $this->reset_last_checked_to( strtotime( '+1 year' ) );
			} else {
				$this->last_checked = $this->reset_last_checked_to( time() );
			}

			wp_send_json( array( 'success' => 'yes' ) );

		}

		/**
		 * Get StoreApps updates
		 */
		public function get_storeapps_updates() {

			check_ajax_referer( 'storeapps-update', 'security' );

			$this->maybe_request_storeapps_data();

			wp_send_json( array( 'success' => 'yes' ) );

		}

		/**
		 * Check when last request was made to storeapps.org & request new data
		 */
		public function maybe_request_storeapps_data() {

			if ( empty( $this->last_checked ) ) {
				$storeapps_data     = $this->get_storeapps_data();
				$this->last_checked = ( ! empty( $storeapps_data['last_checked'] ) ) ? $storeapps_data['last_checked'] : null;
				if ( empty( $this->last_checked ) ) {
					$this->last_checked = $this->reset_last_checked_to( strtotime( '-' . ( $this->get_check_update_timeout_minutes() - 2 ) . ' minutes' ) );
				}
			}

			$time_not_changed = isset( $this->last_checked ) && $this->check_update_timeout > ( time() - $this->last_checked );

			if ( ! $time_not_changed ) {
				$this->request_storeapps_data();
			}

		}

		/**
		 * Request StoreApps data
		 */
		public function request_storeapps_data() {
			if ( 'yes' === $this->get_requesting() ) {
				return;
			}
			$this->log( 'debug', __( 'Requesting StoreApps data.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			$is_ajax = get_option( 'ajax_request_storeapps_data' );
			if ( ! empty( $is_ajax ) && 'yes' === $is_ajax ) {
				return;
			}

			$this->last_checked = $this->reset_last_checked_to( time() );

			$access_token = get_option( '_storeapps_connector_access_token' );
			if ( empty( $access_token ) ) {
				return;
			}

			$protocol = 'https';
			$url      = $protocol . '://www.storeapps.org/wp-json/woocommerce-serial-key/v1/serial-keys';
			$args     = array(
				'plugins' => $this->get_environment_details(),
			);
			$data     = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Referer'       => base64_encode( $this->sku . ':' . $this->installed_version . ':' . $this->client_id . ':' . $this->client_secret ), // phpcs:ignore
				),
				'body'    => $args,
			);

			$this->toggle_requesting();
			$response = wp_remote_post( $url, $data );

			if ( ! is_wp_error( $response ) ) {
				$this->log( 'debug', sprintf(__( 'Received response%s. Should contain StoreApps data.', $this->text_domain ), ( ( ! empty( $_SERVER['HTTP_REFERER'] ) ) ? __( ' from', $this->text_domain ) . ' ' . $_SERVER['HTTP_REFERER'] : '' ) ) . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				$code    = wp_remote_retrieve_response_code( $response );
				$message = wp_remote_retrieve_response_message( $response );

				$code = absint( $code );

				if ( 200 === $code || 'OK' === $message ) {
					$body          = wp_remote_retrieve_body( $response );
					$response_data = json_decode( $body, true );

					if ( ! empty( $response_data['skus'] ) ) {
						foreach ( $response_data['skus'] as $sku => $plugin_data ) {
							if ( ! empty( $plugin_data['link'] ) ) {
								$response_data['skus']['login_link'] = $plugin_data['link'];
							}
						}
						$response_data['skus']['last_checked'] = time();
						$this->set_storeapps_data( $response_data['skus'] );
					} else {
						$this->log( 'error', __( 'Empty response data', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
					}
				} else {
					$this->log( 'error', sprintf(__( 'Response code, message mismatch. Response code: %s, Response message: %s.', $this->text_domain ), $code, $message ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				}
				update_option( '_storeapps_connector_status', $code, 'no' );
				if ( 401 === $code ) {
					$this->last_checked = $this->reset_last_checked_to( strtotime( '+1 year' ) );
				} else {
					$this->last_checked = $this->reset_last_checked_to( time() );
				}
			} else {
				update_option( 'ajax_request_storeapps_data', 'yes', 'no' );
				$this->last_checked = $this->reset_last_checked_to( time() );
				$this->log( 'error', print_r( $response->get_error_messages(), true ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				if ( isset( $data['body']['plugins'] ) ) {
					unset( $data['body']['plugins'] );
				}
				$this->log( 'debug', __( 'Requested URL:', $this->text_domain ) . ' ' . $url . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				$this->log( 'debug', __( 'Data sent:', $this->text_domain ) . ' ' . print_r( $data, true ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				$this->log( 'debug', __( 'Response received:', $this->text_domain ) . ' ' . print_r( $response, true ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			}
			$this->toggle_requesting();
		}

		/**
		 * Delete options added by this class
		 */
		public function delete_options() {
			delete_option( '_storeapps_connector_data' );
			delete_option( '_storeapps_connector_access_token' );
			delete_option( '_storeapps_connector_token_expiry' );
			delete_option( '_storeapps_connector_status' );
			delete_option( '_retry_storeapps_connection' );
			delete_option( '_storeapps_connector_onboarding_redirect_url' );
			delete_option( '_storeapps_failed_connection_reported_on' );
			delete_option( 'ajax_request_storeapps_data' );
			delete_option( 'is_requesting_storeapps_data' );

			$is_connected = get_option( '_storeapps_connected' );
			if ( false !== $is_connected ) {
				delete_option( '_storeapps_connected' );
			}

			$is_auto_connected = get_option( '_storeapps_auto_connected' );
			if ( false !== $is_auto_connected ) {
				delete_option( '_storeapps_auto_connected' );
			}
		}

		/**
		 * Disconnect from StoreApps
		 */
		public function disconnect_storeapps() {

			check_ajax_referer( 'disconnect-storeapps', 'security' );

			$this->delete_options();

			wp_send_json(
				array(
					'success' => 'yes',
					'message' => 'success',
				)
			);

		}

		/**
		 * Get StoreApps data
		 *
		 * @return array $data StoreApps data.
		 */
		public function get_storeapps_data() {

			$data = get_option( '_storeapps_connector_data', array() );

			$update = false;

			if ( empty( $data[ $this->sku ] ) ) {
				$data[ $this->sku ] = array(
					'installed_version'         => '0',
					'live_version'              => '0',
					'license_key'               => '',
					'changelog'                 => '',
					'due_date'                  => '',
					'download_url'              => '',
					'next_update_check'         => false,
					'upgrade_notices'           => array(),
					'saved_changes'             => 'no',
					'hide_renewal_notification' => 'no',
					'hide_license_notification' => 'no',
				);
				$update             = true;
			}

			if ( empty( $data['last_checked'] ) ) {
				$data['last_checked'] = 0;
				$update               = true;
			}

			if ( empty( $data['login_link'] ) ) {
				$protocol           = 'https';
				$data['login_link'] = $protocol . '://www.storeapps.org/my-account';
				$update             = true;
			}

			if ( empty( $data['storeapps-upgrader'] ) ) {
				$data['storeapps-upgrader'] = $this->get_upgrader_version();
				$update                     = true;
			}

			if ( $update ) {
				update_option( '_storeapps_connector_data', $data, 'no' );
			}

			return $data;

		}

		/**
		 * Set StoreApps data
		 *
		 * @param array   $data StoreApps data.
		 * @param boolean $force Force det data.
		 */
		public function set_storeapps_data( $data = array(), $force = false ) {
			$this->log( 'debug', __( 'Saving StoreApps data.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			if ( $force || ! empty( $data ) ) {
				update_option( '_storeapps_connector_data', $data, 'no' );
				$this->log( 'debug', __( 'Saved StoreApps data.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
			}

		}

		/**
		 * Request data via ajax
		 */
		public function request_storeapps_data_js() {
			if ( did_action( 'ajax_requested_storeapps_data' ) > 0 ) {
				return;
			}
			$is_ajax          = get_option( 'ajax_request_storeapps_data' );
			$time_not_changed = isset( $this->last_checked ) && $this->check_update_timeout > ( time() - $this->last_checked );
			if ( ! empty( $is_ajax ) && 'yes' === $is_ajax && ! $time_not_changed ) {
				$this->log( 'debug', __( 'Requesting StoreApps data via AJAX.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
				if ( ! wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
				}

				$access_token = get_option( '_storeapps_connector_access_token' );
				if ( empty( $access_token ) ) {
					$this->log( 'debug', __( 'Access token not found. Aborting AJAX request to get StoreApps data', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore
					return;
				}

				$protocol = 'https';
				$url      = $protocol . '://www.storeapps.org/wp-json/woocommerce-serial-key/v1/serial-keys';
				$plugins  = $this->get_environment_details();

				$this->log( 'debug', __( 'Requested URL:', $this->text_domain ) . ' ' . $url . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore

				?>
				<script type="text/javascript">
					jQuery(function(){
						jQuery.ajax({
							url: '<?php echo esc_url( $url ); ?>',
							method: 'POST',
							dataType: 'json',
							headers: {
								'Authorization': 'Bearer <?php echo $access_token; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
							},
							data: {
								'plugins': '<?php echo wp_json_encode( $plugins ); ?>'
							},
							success: function( response ) {
								if ( response != undefined && response != '' ) {
									if ( response.skus != undefined && response.skus != '' ) {
										jQuery.ajax({
											url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
											method: 'POST',
											dataType: 'json',
											data: {
												action: '<?php echo $this->prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>_save_data',
												sku_data: response.skus,
												security: '<?php echo wp_create_nonce( $this->prefix . '-save-data' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
											},
											success: function( res ) {
												if ( res != undefined && res != '' && res.success != undefined && res.success == 'yes' ) {
													// All done.
												}
											}
										});
									}
								}
							},
							error: function( jqXHR, textStatus, errorThrown ) {
								jQuery.ajax({
									url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
									method: 'POST',
									dataType: 'json',
									data: {
										action: '<?php echo $this->prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>_save_error_data',
										code: jqXHR.status,
										security: '<?php echo wp_create_nonce( $this->prefix . '-save-error-data' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
									},
									success: function( res ) {
										if ( res != undefined && res != '' && res.success != undefined && res.success == 'yes' ) {
											// All done.
										}
									}
								});
							}
						});
					});
				</script>
				<?php
				do_action( 'ajax_requested_storeapps_data', array( 'source' => $this ) );
			}
		}

		/**
		 * Collect & add environment details
		 *
		 * @param  array  $info     Existing info.
		 * @param  object $upgrader StoreApps Upgrader class.
		 * @return array  $info     Additional info
		 */
		public function storeapps_upgrade_miscellaneous_info( $info = array(), $upgrader = null ) {
			global $wpdb;

			$info['wp_version']    = get_bloginfo( 'version' );
			$info['php_version']   = phpversion();
			$info['mysql_version'] = $wpdb->db_version();

			return $info;
		}

		/**
		 * Check if StoreApps update available
		 */
		public function storeapps_updates_available() {
			$user_agent    = ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore
			$security_text = $this->client_secret . $user_agent . $this->client_id;
			$security      = md5( $security_text );
			$sent_security = ( ! empty( $_REQUEST['security'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['security'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( empty( $user_agent ) || empty( $sent_security ) || $security !== $sent_security ) {
				wp_send_json(
					array(
						'success' => 'no',
						'message' => esc_html__( '404 Not Found', $this->text_domain ), // phpcs:ignore
					)
				);
			}
			$this->maybe_request_storeapps_data();
			wp_send_json( array( 'success' => 'yes' ) );
		}

		/**
		 * To report failed store connection
		 */
		public function storeapps_report_failed_connection() {
			check_ajax_referer( 'report-failed-connection-to-storeapps', 'security' );

			$last_sent = get_option( '_storeapps_failed_connection_reported_on' );

			if ( ! empty( $last_sent ) && ( time() - $last_sent ) < ( 8 * 60 * 60 ) ) {
				wp_send_json( array( 'success' => 'yes' ) );
			}

			$from_user_id = ( ! empty( $_POST['from'] ) ) ? absint( $_POST['from'] ) : 0;
			$log_file     = ( ! empty( $_POST['file'] ) ) ? sanitize_text_field( wp_unslash( ABSPATH . $_POST['file'] ) ) : '';

			$admin_email = get_option( 'admin_email' );

			if ( empty( $from_user_id ) ) {
				$current_user = get_user_by( 'email', $admin_email );
			} else {
				$current_user = get_user_by( 'id', $from_user_id );
			}

			if ( ! empty( $current_user->ID ) ) {
				$first_name     = get_user_meta( $current_user->ID, 'first_name', true ); // phpcs:ignore
				$last_name      = get_user_meta( $current_user->ID, 'last_name', true ); // phpcs:ignore
				$name         = $first_name . ' ' . $last_name;
				$client_name  = ( ! empty( $name ) ) ? $name : $current_user->data->display_name;
				$client_email = $current_user->data->user_email;
			} else {
				$client_name  = $admin_email;
				$client_email = $admin_email;
			}

			$headers  = 'From: ';
			$headers .= ( isset( $client_name ) && ! empty( $client_name ) ) ? sanitize_text_field( $client_name ) : '';
			$headers .= ' <' . sanitize_text_field( $client_email ) . '>' . "\r\n";
			$headers .= 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

			if ( ! class_exists( 'WP_Debug_Data' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-debug-data.php' ) ) {
				include_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
			}

			if ( ! class_exists( 'PclZip' ) && file_exists( ABSPATH . 'wp-admin/includes/class-pclzip.php' ) ) {
				include_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
			}

			$info = ( class_exists( 'WP_Debug_Data' ) && is_callable( array( 'WP_Debug_Data', 'debug_data' ) ) ) ? WP_Debug_Data::debug_data() : array();

			if ( ! empty( $info ) ) {
				$additional_info = ( class_exists( 'WP_Debug_Data' ) && is_callable( array( 'WP_Debug_Data', 'format' ) ) ) ? nl2br( WP_Debug_Data::format( $info, 'info' ) ) : '';
			} else {
				$additional_info = '';
			}

			/* translators: The site url */
			$subject = sprintf( __( 'Store connection failed at %s', $this->text_domain ), site_url() ); // phpcs:ignore
			$message = ( ! empty( $additional_info ) ) ? __( 'Additional info:' ) . "\n" . $additional_info : __( 'No additional info found', $this->text_domain ); // phpcs:ignore

			$attachments = array();
			$is_log_file = false;
			$zip_file    = '';

			if ( file_exists( $log_file ) ) {
				$upload_dir = wp_upload_dir();
				$zip_file   = $upload_dir['basedir'] . '/' . $this->sku . '-' . time() . '.zip';
				$archive    = new PclZip( $zip_file );
				$v_list     = $archive->create( $log_file, PCLZIP_OPT_REMOVE_ALL_PATH );
				if ( 0 !== $v_list ) {
					$attachments[] = $zip_file;
					$is_log_file   = true;
				}
			}

			wp_mail( 'support@storeapps.org', $subject, $message, $headers, $attachments );

			update_option( '_storeapps_failed_connection_reported_on', time(), 'no' );

			if ( true === $is_log_file && file_exists( $zip_file ) ) {
				unlink( $zip_file );
			}

			wp_send_json( array( 'success' => 'yes' ) );
		}

		/**
		 * Collect environment details
		 *
		 * @return array Environment details
		 */
		public function get_environment_details() {
			$activated_plugins   = array();
			$deactivated_plugins = array();
			$is_limited          = get_option( 'storeapps_is_limited_environment_details', 'no', 'no' );
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins                   = get_plugins();
			$all_activated_plugins         = get_option( 'active_plugins', array() );
			$all_network_activated_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$all_activated_plugins         = array_unique( array_merge( $all_activated_plugins, array_keys( $all_network_activated_plugins ) ) );
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				$author  = ( ! empty( $plugin_data['Author'] ) ) ? strtolower( $plugin_data['Author'] ) : null;
				$version = ( ! empty( $plugin_data['Version'] ) ) ? $plugin_data['Version'] : '';
				if ( 'yes' === $is_limited && ! in_array( $author, array( 'storeapps', 'store apps' ), true ) ) { // WPCS: loose comparison ok.
					continue;
				}
				if ( in_array( $plugin_file, $all_activated_plugins, true ) ) { // WPCS: loose comparison ok.
					$activated_plugins[ $plugin_file ] = $version;
				} else {
					$deactivated_plugins[ $plugin_file ] = $version;
				}
			}
			return array(
				'activated'     => $activated_plugins,
				'deactivated'   => $deactivated_plugins,
				'miscellaneous' => apply_filters( 'storeapps_upgrade_miscellaneous_info', array(), $this ),
			);
		}

		/**
		 * Show notification if plugin update is available
		 */
		public function show_plugin_update_notification() {

			if ( ! current_user_can( 'update_plugins' ) ) {
				return;
			}

			$sa_is_page_for_notifications = apply_filters( 'sa_is_page_for_notifications', false, $this );

			if ( true === $sa_is_page_for_notifications ) {
				$sku            = $this->sku;
				$storeapps_data = $this->get_storeapps_data();

				$live_version      = $storeapps_data[ $sku ]['live_version'];
				$installed_version = $storeapps_data[ $sku ]['installed_version'];

				$plugin_name = ( ! empty( $this->plugin_data['Name'] ) ) ? $this->plugin_data['Name'] : '';

				if ( ! empty( $plugin_name ) && version_compare( $live_version, $installed_version, '>' ) ) {
					?>
					<div class="notice notice-warning <?php echo esc_attr( $this->admin_notice_css_class ); ?>" style="background: #ffefd5;">
						<p><?php echo '<span class="dashicons dashicons-megaphone" style="color: #5850EC;"></span>&nbsp;&nbsp;<strong>' . esc_html__( 'Update Available:', $this->text_domain ) . '</strong> ' . esc_html__( 'Please update ', $this->text_domain ) . ' <strong>' . esc_html( $plugin_name ) . '</strong> ' . esc_html__( 'from', $this->text_domain ) . ' ' . $installed_version . ' ' . esc_html__( 'to the latest version', $this->text_domain ) . ' ' . $live_version . '.' . ' <a href="' . esc_url( add_query_arg( array( 's' => $plugin_name ), admin_url( 'plugins.php' ) ) ) . '">' . esc_html__( 'Go to updates', $this->text_domain ) . '</a> ' ; // phpcs:ignore ?></p>
					</div>
					<?php
				}
			}

		}

		/**
		 * Show notification if reconnect to store is needed
		 */
		public function show_reconnect_notification() {

			if ( did_action( 'storeapps_re_authentication_required' ) > 0 ) {
				return;
			}

			$code = get_option( '_storeapps_connector_status', 0 );
			$code = absint( $code );

			$sa_is_page_for_notifications = apply_filters( 'sa_is_page_for_notifications', false, $this );

			if ( true === $sa_is_page_for_notifications && 401 === $code ) {
				$plugin_name = ( ! empty( $this->plugin_data['Name'] ) ) ? $this->plugin_data['Name'] : '';

				if ( ! empty( $plugin_name ) ) {
					?>
					<div class="notice notice-error <?php echo esc_attr( $this->admin_notice_css_class ); ?>" style="background-color: #ffd5d5;">
						<p><?php echo '<span class="dashicons dashicons-warning" style="color: #5850EC; vertical-align: middle;"></span>&nbsp;&nbsp;<strong>' . esc_html__( 'Re-authentication required:', $this->text_domain ) . '</strong> ' . esc_html__( 'A problem occurred while trying to fetch updates for', $this->text_domain ) . ' <strong>' . esc_html( $plugin_name ) . '</strong>. ' . esc_html__( 'You need to re-authenticate your StoreApps account', $this->text_domain ) . '. <a href="' . esc_url( add_query_arg( array( 're-authenticate-' . $this->sku => 'yes', 'security' => wp_create_nonce( 'storeapps-re-authenticate' ) ) ) ) . '" class="button button-primary">' . esc_html__( 'Re-authenticate', $this->text_domain ) . '</a>'; // phpcs:ignore ?></p>
					</div>
					<?php
					do_action( 'storeapps_re_authentication_required', array( 'source' => $this ) );
				}
			}

		}

		/**
		 * Handle re-authentication
		 */
		public function handle_re_authentication() {
			$query_arg          = 're-authenticate-' . $this->sku;
			$is_re_authenticate = ( ! empty( $_GET[ $query_arg ] ) ) ? sanitize_text_field( wp_unslash( $_GET[ $query_arg ] ) ) : 'no';
			if ( 'yes' === $is_re_authenticate ) {
				$security   = ( ! empty( $_GET['security'] ) ) ? sanitize_text_field( wp_unslash( $_GET['security'] ) ) : '';
				$is_proceed = wp_verify_nonce( $security, 'storeapps-re-authenticate' );
				if ( false !== $is_proceed ) {
					$this->delete_options();
					$current_url = remove_query_arg( array( $query_arg, 'security' ) );
					if ( ! empty( $current_url ) ) {
						$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $current_url; // phpcs:ignore
						update_option( '_storeapps_connector_onboarding_redirect_url', esc_url_raw( $current_url ), 'no' );
					}
					$redirect_to = add_query_arg(
						array(
							'page' => 'storeapps',
							'tab'  => 'onboard',
						),
						admin_url( 'admin.php' )
					);
					wp_safe_redirect( $redirect_to );
					exit;
				} else {
					wp_die( __( 'The link you followed has expired.', $this->text_domain ), __( 'Failed', $this->text_domain ), array( 'back_link' => true ) ); // phpcs:ignore
				}
			}
		}

		/**
		 * To check if store is connected
		 */
		public function check_store_connection() {
			$sa_is_page_for_notifications = apply_filters( 'sa_is_page_for_notifications', false, $this );
			if ( ! $this->is_storeapps_onboarding() && true === $sa_is_page_for_notifications && in_array( strtolower( $this->sku ), array( 'sm', 'so', 'bvm', 'saff', 'se' ), true ) ) {
				$access_token = get_option( '_storeapps_connector_access_token' );
				$token_expiry = get_option( '_storeapps_connector_token_expiry' );
				if ( empty( $access_token ) || ( ! empty( $token_expiry ) && time() > $token_expiry ) ) {
					$current_url = add_query_arg( 'sa-redirect', '1' );
					$current_url = remove_query_arg( 'sa-redirect', $current_url );
					if ( ! empty( $current_url ) ) {
						$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $current_url; // phpcs:ignore
						update_option( '_storeapps_connector_onboarding_redirect_url', esc_url_raw( $current_url ), 'no' );
					}
					$redirect_to = add_query_arg(
						array(
							'page' => 'storeapps',
							'tab'  => 'onboard',
						),
						admin_url( 'admin.php' )
					);
					wp_safe_redirect( $redirect_to );
					exit;
				}
			}
		}

		/**
		 * Add a page for the storeapps setup
		 */
		public function add_storeapps_plugins_page() {
			if ( defined( 'WC_VERSION' ) && ! empty( WC_VERSION ) ) {
				add_submenu_page( 'admin', __( 'StoreApps', $this->text_domain ), __( 'StoreApps', $this->text_domain ), 'manage_woocommerce', 'storeapps', array( $this, 'settings_page' ) ); // phpcs:ignore
			} else {
				add_submenu_page( 'admin', __( 'StoreApps', $this->text_domain ), __( 'StoreApps', $this->text_domain ), 'install_plugins', 'storeapps', array( $this, 'settings_page' ) ); // phpcs:ignore
			}
		}

		/**
		 * StoreApps setup page content
		 */
		public function settings_page() {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'storeapps',
						'tab'  => 'onboard',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		/**
		 * To check if the current page is for onboarding
		 *
		 * @return boolean
		 */
		public function is_storeapps_onboarding() {
			$get_page = ( ! empty( $_GET['page'] ) ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
			$get_tab  = ( ! empty( $_GET['tab'] ) ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore
			if ( 'storeapps' === $get_page && 'onboard' === $get_tab ) {
				return true;
			}
			return false;
		}

		/**
		 * Start onboarding process
		 */
		public function start_onboarding() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}

			ob_end_clean();

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style( 'wp-admin' );
			wp_enqueue_style( 'buttons' );
			wp_enqueue_style( 'sa_wc_admin_styles', plugins_url( 'css/admin' . $suffix . '.css', __FILE__ ), array(), $this->plugin_data['Version'] );
			wp_enqueue_style( 'sa_wc_setup', plugins_url( 'css/wc-setup' . $suffix . '.css', __FILE__ ), array( 'dashicons', 'install' ), $this->plugin_data['Version'] );

			$this->onboarding_steps = $this->get_onboarding_steps();

			$access_token = get_option( '_storeapps_connector_access_token' );
			$token_expiry = get_option( '_storeapps_connector_token_expiry' );

			$onboarding_steps_keys = array_keys( $this->onboarding_steps );

			if ( ! empty( $access_token ) && ! empty( $token_expiry ) && time() <= $token_expiry && ! isset( $_POST['save_step'] ) ) { // phpcs:ignore
				$this->onboarding_step = end( $onboarding_steps_keys );
			} else {
				$this->onboarding_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( $onboarding_steps_keys ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			ob_start();

			// @codingStandardsIgnoreStart
			if ( ! empty( $_POST['save_step'] ) && isset( $this->onboarding_steps[ $this->onboarding_step ]['handler'] ) ) {
				call_user_func( $this->onboarding_steps[ $this->onboarding_step ]['handler'], $this );
			}
			// @codingStandardsIgnoreEnd

			$this->onboarding_header();
			$this->onboarding_steps();
			$this->onboarding_content();
			$this->onboarding_footer();
			exit;
		}

		/**
		 * To get onborading steps
		 *
		 * @return array Onboarding steps
		 */
		public function get_onboarding_steps() {
			if ( empty( $this->onboarding_steps ) || ! is_array( $this->onboarding_steps ) ) {
				$this->onboarding_steps = array();
			}
			$this->onboarding_steps = apply_filters( 'storeapps_onboarding_steps', $this->onboarding_steps, array( 'source' => $this ) );
			return $this->onboarding_steps;
		}

		/**
		 * Onboarding page - header section
		 */
		public function onboarding_header() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}
			set_current_screen();
			?>
			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
			<head>
				<meta name="viewport" content="width=device-width"/>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
				<title><?php echo __( 'StoreApps &rsaquo; Setup', $this->text_domain ); // phpcs:ignore ?></title>
				<?php do_action( 'admin_enqueue_scripts' ); ?>
				<?php do_action( 'admin_print_styles' ); ?>
				<?php do_action( 'admin_print_scripts' ); ?>
				<?php do_action( 'admin_head' ); ?>
			</head>
			<?php $this->onboarding_css(); ?>
			<body class="wc-setup wp-core-ui">
				<h1 id="storeapps-logo"><a href="https://www.storeapps.org/"><img src="<?php echo esc_url( plugins_url( 'images/storeapps-logo.svg', __FILE__ ) ); ?>" alt="StoreApps.org" /></a></h1>
			<?php
		}

		/**
		 * Onborading page CSS
		 */
		public function onboarding_css() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}
			?>
			<style type="text/css" media="screen">
				body {
					border: initial;
				}
				#storeapps-logo {
					border: 0;
					margin: 0 0 2em;
					padding: 0;
					text-align: center;
				}
				#storeapps-logo img {
					max-width: 30%;
				}
				.wp-core-ui .button-primary[disabled], .wp-core-ui .button-primary:disabled, .wp-core-ui .button-primary-disabled, .wp-core-ui .button-primary.disabled {
					color: #dcb3e6 !important;
					background: #5850EC !important;
					border-color: #4135de !important;
					box-shadow: none !important;
					text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.1) !important;
					cursor: default;
				}
				.wc-setup .wc-setup-actions .button-primary {
					background-color: #5850EC !important;
					border-color: #4135de !important;
					box-shadow: inset 0 1px 0 rgba(255,255,255,.25),0 1px 0 #4135de !important;
					text-shadow: 0 -1px 1px #4135de,1px 0 1px #4135de,0 1px 1px #4135de,-1px 0 1px #4135de !important;
				}
				.wc-setup .wc-setup-actions .button-primary:hover {
					background-color: #4135de !important;
					border-color: #4135de !important;
					box-shadow: none !important;
				}
				.wc-setup-steps li.active,
				.wc-setup-steps li.active::before,
				.wc-setup-steps li.done {
					border-color: #5850EC !important;
					color: #5850EC !important;
				}
				.wc-setup-steps li.done::before {
					border-color: #5850EC !important;
					background: #5850EC !important;
				}
				.wc-setup-content a,
				.wc-setup-steps li a {
					color: #5850EC !important;
				}
				.wc-setup-actions.step a {
					color: #fff !important;
				}
				@media only screen and (max-width:400px) {
					#storeapps-logo img {
						max-width: 80%;
					}
				}
			</style>
			<?php
		}

		/**
		 * Display onboarding steps
		 */
		public function onboarding_steps() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}
			$get_step = ( ! empty( $_GET['step'] ) ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : 'start'; // phpcs:ignore
			if ( empty( $get_step ) ) {
				return;
			}
			$output_steps = $this->onboarding_steps;
			?>
			<ol class="wc-setup-steps">
				<?php
				foreach ( $output_steps as $step_key => $step ) {
					$is_completed = array_search( $this->onboarding_step, array_keys( $this->onboarding_steps ), true ) > array_search( $step_key, array_keys( $this->onboarding_steps ), true );

					if ( $step_key === $this->onboarding_step ) {
						?>
							<li class="active"><?php echo esc_html( $step['label'] ); ?></li>
							<?php
					} elseif ( $is_completed ) {
						?>
							<li class="done">
								<a href="<?php echo esc_url( add_query_arg( 'step', $step_key, remove_query_arg( 'storeapps_error' ) ) ); ?>"><?php echo esc_html( $step['label'] ); ?></a>
							</li>
							<?php
					} else {
						?>
							<li><?php echo esc_html( $step['label'] ); ?></li>
							<?php
					}
				}
				?>
			</ol>
			<?php
		}

		/**
		 * Onboarding page content
		 */
		public function onboarding_content() {
			echo '<div class="wc-setup-content">';
			if ( ! empty( $this->onboarding_steps[ $this->onboarding_step ]['view'] ) ) {
				call_user_func( $this->onboarding_steps[ $this->onboarding_step ]['view'], $this );
			}
			echo '</div>';
		}

		/**
		 * Onboarding page - footer section
		 */
		public function onboarding_footer() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}
			?>
				<a class="wc-setup-footer-links" href="<?php echo esc_url( admin_url() ); ?>"><?php echo esc_html__( 'Return to the WordPress Dashboard', $this->text_domain ); // phpcs:ignore ?></a>
			</body>
			<?php
				do_action( 'admin_footer' );
				do_action( 'admin_print_footer_scripts' );
			?>
			</html>
			<?php
		}

		/**
		 * Get the URL for the next step's screen.
		 *
		 * @param string $step  slug (default: current step).
		 * @return string       URL for next step if a next step exists.
		 *                      Admin URL if it's the last step.
		 *                      Empty string on failure.
		 */
		public function get_next_step_link( $step = '' ) {

			if ( empty( $this->onboarding_steps ) ) {
				$this->onboarding_steps = $this->get_onboarding_steps();
			}

			$keys = array_keys( $this->onboarding_steps );

			if ( empty( $step ) ) {
				$step = ( ! empty( $this->onboarding_step ) ) ? $this->onboarding_step : ( ( ! empty( $_GET['step'] ) ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : current( $keys ) ); // phpcs:ignore
				if ( empty( $this->onboarding_step ) ) {
					$this->onboarding_step = $step;
				}
			}

			if ( end( $keys ) === $step ) {
				return admin_url();
			}

			$step_index = array_search( $step, $keys, true );
			if ( false === $step_index ) {
				return '';
			}

			return add_query_arg( 'step', $keys[ $step_index + 1 ], remove_query_arg( 'storeapps_error' ) );
		}

		/**
		 * Default onboarding steps
		 *
		 * @param  array $steps The steps.
		 * @param  array $args  Additional arguments.
		 * @return array $steps
		 */
		public function default_onboarding_steps( $steps = array(), $args = array() ) {
			$steps = array(
				'start'     => array(
					'label'   => __( 'Start', $this->text_domain ), // phpcs:ignore
					'view'    => array( $this, 'onboarding_start_view' ),
					'handler' => array( $this, 'onboarding_start_handler' ),
				),
				'authorize' => array(
					'label'   => __( 'Authorize', $this->text_domain ), // phpcs:ignore
					'view'    => array( $this, 'onboarding_authorize_view' ),
					'handler' => array( $this, 'onboarding_authorize_handler' ),
				),
				'complete'  => array(
					'label'   => __( 'Complete', $this->text_domain ), // phpcs:ignore
					'view'    => array( $this, 'onboarding_complete_view' ),
					'handler' => '',
				),
			);
			return $steps;
		}

		/**
		 * Onboarding start page view
		 */
		public function onboarding_start_view() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}
			$get_step = ( ! empty( $_GET['step'] ) ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : 'start'; // phpcs:ignore
			if ( empty( $get_step ) ) {
				return;
			}
			?>
			<form class="storeapps-step storeapps-step-<?php echo esc_attr( $get_step ); ?>" enctype="multipart/form-data" method="post">
				<?php wp_nonce_field( 'storeapps-onboarding-nonce' ); ?>
				<style type="text/css" media="screen">
					.storeapps-step-<?php echo esc_html( $get_step ); ?> .sa-text-align-center {
						text-align: center;
					}
					.storeapps-step-<?php echo esc_html( $get_step ); ?> .sa-display-block {
						display: block;
					}
					.storeapps-step-<?php echo esc_html( $get_step ); ?> .dashicons-yes {
						color: #27ae60;
						font-size: 2.2em;
						margin-right: 5px;
						vertical-align: text-bottom;
					}
					.storeapps-step-<?php echo esc_html( $get_step ); ?> a {
						display: inline-block;
						cursor: pointer;
						margin: 1em 0em 0em 0em;
						text-decoration: underline;
					}
					.storeapps-step-<?php echo esc_html( $get_step ); ?> ol {
						width: auto;
						margin: auto;
						display: inline-block;
						list-style: none;
						padding-left: 0;
					}
					.storeapps-step-<?php echo esc_html( $get_step ); ?> ol li {
						text-align: left;
					}
					.storeapps-step-<?php echo esc_html( $get_step ); ?> .storeapps-connect-flat-button {
						position: relative;
						vertical-align: top;
						height: 2.8em;
						padding: 0 2.5em;
						font-size: 1.5em;
						color: white;
						text-align: center;
						text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
						background: #27ae60;
						border: 0;
						border-radius: 5px;
						border-bottom: 2px solid #219d55;
						cursor: pointer;
						-webkit-box-shadow: inset 0 -2px #219d55;
						box-shadow: inset 0 -2px #219d55;
						margin-top: 0.5em;
					}
					.storeapps-step-<?php echo esc_html( $get_step ); ?> .storeapps-connect-flat-button:active {
						top: 1px;
						outline: none;
						-webkit-box-shadow: none;
						box-shadow: none;
					}
					.sa-onboarding {
						width: 100%;
						padding: 1em 0;
					}
					.storeapps-step-start a {
						opacity: 0.7;
					}
					.sa-onboarding-image {
						width: 25%;
						float: left;
					}
					.sa-onboarding-connect-account-image {
						margin: 1em auto;
					}
					.sa-onboarding-content {
						width: 75%;
						margin-left: 25%;
					}
					.sa-onboarding-actions {
						width: 100%;
						height: 5em;
						padding: 3em 0 0 0;
					}
					.sa-onboarding-requirement {
						font-size: initial;
						margin: 0 auto;
						display: table;
					}
					.sa-onboarding-requirement ol li {
						font-size: small;
					}
					.sa-onboarding-requirement .sa-onboarding-requirement-action a {
						margin: unset;
					}
					.sa-onboarding-left {
						width: 30%;
						float: left;
						text-align: left;
					}
					.sa-onboarding-right {
						width: 70%;
						float: right;
						text-align: right;
					}
					.sa-why-connect {
						text-align: left;
						margin-left: 0.5em;
						margin-top: 0em;
						line-height: 1.5em;
					}
					.sa_connector_privacy_label {
						vertical-align: inherit;
						padding: 0 0.2em;
					}
				</style>
				<div class="sa-onboarding">
					<h3 class="sa-why-connect"><?php echo sprintf( esc_html__( 'In order to use %s you would need to connect with your %s. Here\'s why to connect?', $this->text_domain ), '<strong>' . esc_html( $this->name ) . '</strong>', '<strong style="color:#5850EC;">' . esc_html__( 'StoreApps.org account', $this->text_domain ) . '</strong>' ); // phpcs:ignore ?></h3>
					<div class="sa-onboarding-image">
						<img class="sa-onboarding-connect-account-image" height="100" width="100" style="display: block;" src="<?php echo esc_url( plugins_url( 'images/sa-onboarding-connect-account.png', __FILE__ ) ); ?>" />
					</div>
					<div class="sa-onboarding-content">
						<ol>
							<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php esc_html_e( 'Receive future updates of the plugin', $this->text_domain ); // phpcs:ignore ?></li>
							<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php esc_html_e( 'One time process, no need to remember license key(s)', $this->text_domain ); // phpcs:ignore ?></li>
							<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php esc_html_e( 'Quick access to the documentation', $this->text_domain ); // phpcs:ignore ?></li>
							<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php esc_html_e( 'Instant notification about critical updates & security releases', $this->text_domain ); // phpcs:ignore ?></li>
						</ol>
					</div>
					<div class="sa-onboarding-privacy">
						<p class="sa-text-align-center">
							<input type="checkbox" id="sa_connector_privacy" name="sa_connector_privacy" value="yes" required="true"><label for="sa_connector_privacy" class="sa_connector_privacy_label"><?php echo esc_html__( 'I agree to the', $this->text_domain ) . '</label><a href="https://www.storeapps.org/privacy-policy/?utm_source=in_app&utm_medium=store_connector&utm_campaign=privacy" target="_blank">' . esc_html__( 'privacy policy', $this->text_domain ) . '</a>'; // phpcs:ignore ?>
						</p>
					</div>
				</div>
				<div class="sa-text-align-center">
					<button type="submit" class="storeapps-connect-flat-button" name="save_step" value="<?php echo esc_attr__( 'Connect', $this->text_domain ); ?>"><?php esc_html_e( 'I have a working account ', $this->text_domain ); // phpcs:ignore ?><span class="dashicons dashicons-arrow-right-alt"></span></button>
					<span class="sa-display-block"><a href="https://www.storeapps.org/my-account/lost-password/?utm_source=in_app&utm_medium=store_connector&utm_campaign=lost-password" target="storeapps_onboarding_reset_password"><?php echo esc_html__( 'I don\'t have a working password', $this->text_domain ); // phpcs:ignore ?></a></span>
				</div>
				<div class="sa-onboarding-actions">
					<div class="sa-onboarding-left">
						<a href="https://www.storeapps.org/docs/how-to-connect-your-store-to-receive-plugin-updates/?utm_source=in_app&utm_medium=store_connector&utm_campaign=troubleshooting" target="_blank"><?php echo esc_html__( 'Troubleshoot common problems', $this->text_domain ); // phpcs:ignore ?></a>
					</div>
					<div class="sa-onboarding-right">
						<?php echo esc_html__( 'For all other problems,', $this->text_domain ); // phpcs:ignore ?> <a href="mailto:<?php echo esc_attr( antispambot( 'support@storeapps.org' ) ); ?>" target="storeapps_onboarding_reset_password"><?php echo esc_html__( 'email us', $this->text_domain ); // phpcs:ignore ?></a>
					</div>
				</div>
			</form>
			<?php
		}

		/**
		 * Handle actions taken on start page
		 */
		public function onboarding_start_handler() {
			check_admin_referer( 'storeapps-onboarding-nonce' );
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;
		}

		/**
		 * Onboarding authorize page view
		 */
		public function onboarding_authorize_view() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}
			$get_step = ( ! empty( $_GET['step'] ) ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : 'start'; // phpcs:ignore
			if ( empty( $get_step ) ) {
				return;
			}
			$code = ( ! empty( $_GET['code'] ) ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( empty( $code ) ) {
				$protocol = 'https';
				$url      = $protocol . '://www.storeapps.org/oauth/authorize/?response_type=code&client_id=' . $this->client_id . '&storeapps-upgrader=' . $this->get_upgrader_version() . '&redirect_uri=' . add_query_arg( array( 'action' => $this->prefix . '_get_authorization_code' ), admin_url( 'admin-ajax.php' ) );
				wp_safe_redirect( $url );
				exit;
			}
			?>
			<form class="storeapps-step storeapps-step-<?php echo esc_attr( $get_step ); ?>" enctype="multipart/form-data" method="post">
				<?php wp_nonce_field( 'storeapps-onboarding-nonce' ); ?>
				<style type="text/css" media="screen">
					.wc-setup-footer-links {
						display: none;
					}
				</style>
				<?php
				$args            = array(
					'grant_type'   => 'authorization_code',
					'code'         => $code,
					'redirect_uri' => add_query_arg( array( 'action' => $this->prefix . '_get_authorization_code' ), admin_url( 'admin-ajax.php' ) ),
				);
				$protocol        = 'https';
				$url             = $protocol . '://www.storeapps.org/oauth/token';
				$next_step_url   = add_query_arg(
					array(
						'page' => 'storeapps',
						'tab'  => 'onboard',
						'step' => 'complete',
					),
					admin_url( 'admin.php' )
				);
				$parsed_site_url = wp_parse_url( site_url() );
				$parsed_domain   = ( false !== $parsed_site_url ) ? $parsed_site_url['host'] : 'localhost';
				$nonce           = md5( $parsed_domain . $this->prefix . substr( $this->client_id, 6, 10 ) . substr( $args['code'], 11, 20 ) . substr( $this->client_secret, 16, 10 ) );
				?>
				<style type="text/css" media="screen">
					form.storeapps-step-authorize {
						height: 350px;
						width: 100%;
					}
					.sa-onboarding {
						position: relative;
						text-align: center;
						width: 100%;
						height: 300px;
					}
					.sa-onboarding-success {
						position: absolute;
						top: 50%;
						left: 50%;
						transform: translate(-50%, -50%);
					}
					.sa-onboarding-success-image {
						width: 50%;
					}
					.sa-onboarding-success-message-1 {
						font-size: 2em !important;
						color: green !important;
						margin: 1em 0 !important;

					}
					.sa-onboarding-success-message-2 {
						font-size: 1.3em !important;
						color: #2e8bf3 !important;
					}
					.storeapps-loading {
						background: url(<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>) no-repeat center;
						display: inline-block;
						visibility: visible;
						vertical-align: middle;
						width: 2em;
						height: 2em;
						background-size: 2em;
					}
				</style>
				<div class="sa-onboarding" style="display: none;">
					<div class="sa-onboarding-success">
						<img class="sa-onboarding-success-image" src="<?php echo esc_url( plugins_url( 'images/sa-onboarding-success.png', __FILE__ ) ); ?>" />
						<h3 class="sa-onboarding-success-message-1"><?php esc_html_e( 'Authorized!', $this->text_domain ); // phpcs:ignore ?></h3>
						<p class="sa-onboarding-success-message-2"><?php esc_html_e( 'Authentication successful.', $this->text_domain ); // phpcs:ignore ?></p>
					</div>
				</div>
				<script type="text/javascript">
					jQuery(function(){
						jQuery('.wc-setup-footer-links').hide();
						let is_storeapps_onboarding = ( jQuery('.storeapps-step').length ) ? jQuery('.storeapps-step').length : 0;
						<?php $this->log( 'debug', __( 'Sending AJAX request to get access token.', $this->text_domain ) . ' ' . $this->upgrade_file_path . ' ' . __LINE__ ); // phpcs:ignore ?>
						jQuery('form.storeapps-step-authorize').addClass( 'storeapps-loading' );
						jQuery.ajax({
							url: '<?php echo esc_url( $url ); ?>',
							method: 'POST',
							dataType: 'json',
							crossDomain: true,
							xhrFields: {
								withCredentials: true
							},
							headers: {
								'Authorization': 'Basic <?php echo base64_encode( $this->client_id . ':' . $this->client_secret ); // phpcs:ignore ?>'
							},
							data: {
								grant_type: '<?php echo $args['grant_type']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
								code: '<?php echo $args['code']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
								redirect_uri: '<?php echo $args['redirect_uri']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
								security: '<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
							},
							success: function( response ) {
								if ( response != undefined && response != '' ) {
									if ( response.access_token != undefined && response.access_token != '' && response.expires_in != undefined && response.expires_in != '' ) {
										jQuery('form.storeapps-step-authorize').removeClass( 'storeapps-loading' );
										jQuery('.sa-onboarding').show();
										jQuery.ajax({
											url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
											method: 'POST',
											dataType: 'json',
											data: {
												action: '<?php echo $this->prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>_save_token',
												access_token: response.access_token,
												expires_in: response.expires_in,
												security: '<?php echo wp_create_nonce( $this->prefix . '-save-token' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
											},
											success: function( res ) {
												if ( res != undefined && res != '' && res.success != undefined && res.success == 'yes' ) {
													jQuery('.wc-setup-actions.step').show();
													setTimeout(() => {
														window.location.href = '<?php echo esc_url_raw( $next_step_url ); ?>';
													}, 5000);
												}
											}
										});
									}
								}
							}
						});
					});
				</script>
				<p class="wc-setup-actions step" style="display: none;"><?php echo esc_html__( 'Automatically redirecting to the next step in a 5 seconds...', $this->text_domain ); // phpcs:ignore ?></p>
			</form>
			<?php
		}

		/**
		 * Handle action taken on authorize page
		 */
		public function onboarding_authorize_handler() {
			check_admin_referer( 'storeapps-onboarding-nonce' );
			$access_token = get_option( '_storeapps_connector_access_token' );
			$retry        = get_option( '_retry_storeapps_connection', 'yes' );
			if ( empty( $access_token ) && 'no' !== $retry ) {
				update_option( '_retry_storeapps_connection', 'no', 'no' );
				header( 'Refresh:0' );
			}
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;
		}

		/**
		 * Onboarding complete page view
		 */
		public function onboarding_complete_view() {
			if ( ! $this->is_storeapps_onboarding() ) {
				return;
			}
			$get_step = ( ! empty( $_GET['step'] ) ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : 'start'; // phpcs:ignore
			if ( empty( $get_step ) ) {
				return;
			}
			global $wp_version;

			$access_token = get_option( '_storeapps_connector_access_token' );
			$redirect_url = get_option( '_storeapps_connector_onboarding_redirect_url', '' );

			$dashicon = ( version_compare( $wp_version, '5.2.0', '>=' ) ) ? 'dashicons-yes-alt' : 'dashicons-yes';

			?>
			<form class="storeapps-step storeapps-step-<?php echo esc_attr( $get_step ); ?>" enctype="multipart/form-data" method="post">
				<?php wp_nonce_field( 'storeapps-onboarding-nonce' ); ?>
				<style type="text/css" media="screen">
					.dashicons {
						width: 1em;
						height: 1em;
						font-size: 6.5em;
					}
					.dashicons-yes,
					.dashicons-yes-alt {
						color: #2ecc40;
					}
					.dashicons-warning {
						color: #ff4136;
					}
					.wc-setup-footer-links {
						display: none;
					}
				</style>
				<center>
				<?php if ( ! empty( $access_token ) ) { ?>
					<p><span class="dashicons <?php echo esc_attr( $dashicon ); ?>"></span></p>
					<h2><?php echo esc_html__( 'Store Connected!', $this->text_domain ); // phpcs:ignore ?></h2>
					<br>
				<?php } else { ?>
					<?php
					$is_wc_log = false;
					if ( defined( 'WC_ABSPATH' ) && ! empty( WC_ABSPATH ) ) {
						if ( ! class_exists( 'WC_Log_Handler_Interface' ) ) {
							include_once WC_ABSPATH . 'includes/interfaces/class-wc-log-handler-interface.php';
						}
						if ( ! class_exists( 'WC_Log_Handler' ) ) {
							include_once WC_ABSPATH . 'includes/abstracts/abstract-wc-log-handler.php';
						}
						if ( ! class_exists( 'WC_Log_Handler_File' ) ) {
							include_once WC_ABSPATH . 'includes/log-handlers/class-wc-log-handler-file.php';
						}
						$file = WC_Log_Handler_File::get_log_file_path( $this->slug );
						if ( file_exists( $file ) ) {
							$is_wc_log = true;
						}
					} else {
						$file = ini_get( 'error_log' );
					}
					if ( empty( $file ) ) {
						$file = __( 'PHP Error Log', $this->text_domain ); // phpcs:ignore
					}
					?>
					<p><span class="dashicons dashicons-warning"></span></p>
					<?php /* translators: The plugin name */ ?>
					<h3><?php echo esc_html__( 'Oops! Looks like there was a problem with your connection.', $this->text_domain ); // phpcs:ignore ?><br><?php echo sprintf( esc_html__( 'Store connection is necessary to use %s.', $this->text_domain ), '<i>' . $this->name . '</i>' ); ?></h3>
					<h3><?php echo esc_html__( 'Would you like to...', $this->text_domain ); // phpcs:ignore ?></h3>
					<?php if ( true === $is_wc_log ) { ?>
						<p>
							<button type="button" id="report-failed-connection-to-storeapps" class="button button-primary button-hero"
								data-log_file="<?php echo esc_attr( str_replace( ABSPATH, '', $file ) ); ?>"
								data-from="<?php echo esc_attr( get_current_user_id() ); ?>"
								title="<?php echo esc_attr__( 'This will send an email containing a basic site info & error logs', $this->text_domain ); // phpcs:ignore ?>">
								<?php echo esc_html__( 'Automatically report to StoreApps.org', $this->text_domain ); // phpcs:ignore ?>
							</button>
						</p>
						<h3><?php echo esc_html__( 'OR', $this->text_domain ); // phpcs:ignore ?></h3>
						<script type="text/javascript">
							jQuery(function(){
								jQuery('.storeapps-step').on( 'click', '#report-failed-connection-to-storeapps', function(){
									let current_element = jQuery(this);
									jQuery.ajax({
										url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
										type: 'post',
										dataType: 'json',
										data: {
											action: 'storeapps_report_failed_connection',
											security: '<?php echo esc_attr( wp_create_nonce( 'report-failed-connection-to-storeapps' ) ); ?>',
											file: current_element.data( 'log_file' )
										},
										success: function( response ) {
											if ( response && response.success && 'yes' === response.success ) {
												current_element
													.text( '<?php echo esc_html__( 'Sent', $this->text_domain ); // phpcs:ignore ?>' )
													.attr( 'disabled', 'disabled' );
											}
										}
									});
								});
							});
						</script>
					<?php } ?>
				</center>
				<h4><?php echo esc_html__( 'To send the report by yourself, follow these steps:', $this->text_domain ); // phpcs:ignore ?></h4>
				<ol>
					<?php if ( function_exists( 'WC' ) && is_object( WC() ) && is_a( WC(), 'WooCommerce' ) ) { ?>
						<?php /* translators: The link to report */ ?>
					<li><?php echo sprintf( esc_html__( 'Get system report from %s. Copy it & paste in the email', $this->text_domain ), '<strong><a href="' . add_query_arg( array( 'page' => 'wc-status' ), admin_url( 'admin.php' ) ) . '" target="_blank">' . esc_html__( 'WooCommerce > Status', $this->text_domain ) . '</a></strong>' ); // phpcs:ignore ?></li>
					<?php } elseif ( version_compare( $wp_version, '5.2.0', '>=' ) ) { ?>
						<?php /* translators: The link to info */ ?>
					<li><?php echo sprintf( esc_html__( 'Get site info from %s. Copy it & paste in the email', $this->text_domain ), '<strong><a href="' . add_query_arg( array( 'tab' => 'debug' ), admin_url( 'site-health.php' ) ) . '" target="_blank">' . esc_html__( 'Tools > Site Health > Info', $this->text_domain ) . '</a></strong>' ); // phpcs:ignore ?></li>
					<?php } ?>
					<?php if ( file_exists( $file ) ) { ?>
						<?php /* translators: The file */ ?>
					<li><?php echo sprintf( esc_html__( 'Compress file %s', $this->text_domain ), '<code>' . str_replace( ABSPATH, '', $file ) . '</code> ' ); // phpcs:ignore ?></li>
					<?php } else { ?>
						<?php /* translators: The file */ ?>
					<li><?php echo sprintf( esc_html__( 'Compress %s file. If you don\'t know the location of this file, take help of system administrator or host provider to get the file', $this->text_domain ), '<strong>' . __( 'PHP Error Log', $this->text_domain ) . '</strong>' ); // phpcs:ignore ?></li>
					<?php } ?>
					<li><?php echo esc_html__( 'Attach the file with the email', $this->text_domain ); // phpcs:ignore ?></li>
						<?php /* translators: The email address */ ?>
					<li><?php echo sprintf( esc_html__( 'Email all the above details to %s', $this->text_domain ), '<code>' . antispambot( 'support@storeapps.org' ) . '</code>' ); // phpcs:ignore ?></li>
				</ol>
				<p></p>
				<?php } ?>
				<p class="wc-setup-actions step">
					<a class="button-primary button button-large button-next" href="<?php echo esc_url( ( ! empty( $redirect_url ) ) ? $redirect_url : admin_url() ); ?>"><?php echo esc_html__( 'Complete', $this->text_domain ); // phpcs:ignore ?></a>
				</p>
			</form>
			<?php
		}

		/**
		 * Reset time of last request to StoreApps
		 *
		 * @param  integer $timestamp The timestamp.
		 * @return integer The set time
		 */
		public function reset_last_checked_to( $timestamp = 0 ) {
			$storeapps_data                 = $this->get_storeapps_data();
			$this->last_checked             = ( ! empty( $timestamp ) ) ? $timestamp : time();
			$storeapps_data['last_checked'] = $this->last_checked;
			$this->set_storeapps_data( $storeapps_data );
			return $this->last_checked;
		}

		/**
		 * Get check update timeout in minutes
		 *
		 * @return integer
		 */
		public function get_check_update_timeout_minutes() {
			$access_token = get_option( '_storeapps_connector_access_token' );
			if ( ! empty( $access_token ) ) {
				return ( 12 * 60 );
			}
			return ( 24 * 60 );
		}

		/**
		 * Get check update timeout in seconds
		 *
		 * @return integer
		 */
		public function get_check_update_timeout_seconds() {
			return ( $this->get_check_update_timeout_minutes() * 60 );
		}

		/**
		 * Get requesting status of storeapps data
		 *
		 * @return string
		 */
		public function get_requesting() {
			return get_option( 'is_requesting_storeapps_data', 'no' );
		}

		/**
		 * Toggle requesting storeapps data
		 */
		public function toggle_requesting() {
			$is_requesting = $this->get_requesting();
			if ( 'no' === $is_requesting ) {
				$is_requesting = 'yes';
			} else {
				$is_requesting = 'no';
			}
			update_option( 'is_requesting_storeapps_data', $is_requesting, 'no' );
		}

		/**
		 * Get current file's version
		 *
		 * @return string
		 */
		public function get_upgrader_version() {
			$version_details  = explode( '_', __CLASS__ );
			$version_details  = array_filter( $version_details, 'is_numeric' );
			$upgrader_version = implode( '.', $version_details );
			return strval( $upgrader_version );
		}

		/**
		 * Function to log messages generated by this file
		 *
		 * @param  string $level   Message type. Valid values: debug, info, notice, warning, error, critical, alert, emergency.
		 * @param  string $message The message to log.
		 */
		public function log( $level = 'notice', $message = '' ) {

			if ( empty( $message ) ) {
				return;
			}

			if ( defined( 'WC_PLUGIN_FILE' ) && ! empty( WC_PLUGIN_FILE ) ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					$logger  = wc_get_logger();
					$context = array( 'source' => 'storeapps-upgrade' );
					$logger->log( $level, $message, $context );
				} elseif ( file_exists( plugin_dir_path( WC_PLUGIN_FILE ) . 'includes/class-wc-logger.php' ) ) {
					include_once plugin_dir_path( WC_PLUGIN_FILE ) . 'includes/class-wc-logger.php';
					$logger = new WC_Logger();
					$logger->add( 'storeapps-upgrade', $message );
				}
			} else {
				error_log( 'storeapps-upgrade' . ' ' . $message ); // phpcs:ignore
			}

		}

		/**
		 * Sanitize variables
		 *
		 * @param mixed $var The variable to sanitize.
		 * @return mixed
		 */
		public function clean( $var = null ) {
			if ( is_array( $var ) ) {
				return array_map( array( $this, 'clean' ), $var );
			} else {
				return is_scalar( $var ) ? wp_filter_post_kses( $var ) : $var;
			}
		}

		/**
		 * Add social links
		 *
		 * @param string $prefix Plugin prefix.
		 * @return string $social_link Social links.
		 */
		public static function add_social_links( $prefix = '' ) {

			$is_hide = get_option( 'hide_storeapps_social_links', 'no' );

			if ( 'yes' === $is_hide ) {
				return;
			}

			$social_link  = '<style type="text/css">
								div.' . $prefix . '_social_links > iframe {
									max-height: 1.5em;
									vertical-align: middle;
									padding: 5px 2px 0px 0px;
								}
								iframe[id^="twitter-widget"] {
									max-width: 10.3em;
								}
								iframe#fb_like_' . $prefix . ' {
									max-width: 6em;
								}
								span > iframe {
									vertical-align: middle;
								}
							</style>';
			$social_link .= '<a href="https://twitter.com/storeapps" class="twitter-follow-button" data-show-count="true" data-dnt="true" data-show-screen-name="false">Follow</a>';
			$social_link .= "<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>";
			$social_link .= '<iframe id="fb_like_' . $prefix . '" src="https://www.facebook.com/plugins/like.php?href=https%3A%2F%2Fwww.facebook.com%2Fpages%2FStore-Apps%2F614674921896173&width=100&layout=button_count&action=like&show_faces=false&share=false&height=21"></iframe>';

			return $social_link;

		}

	}

}
