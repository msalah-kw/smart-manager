<?php
/**
 * Welcome Page Class
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SM_Admin_Welcome class
 */
class Smart_Manager_Admin_Welcome {

        /**
         * Hook in tabs.
         */
        public $sm_redirect_url, $plugin_url;

        static $text_domain, $prefix, $sku, $plugin_file;

        /**
         * Render a gated external help button.
         *
         * @param string $url    External destination.
         * @param string $label  Button label.
         * @param string $class  Optional CSS classes.
         *
         * @return string
         */
        private function render_external_button( $url, $label, $class = 'button button-secondary' ) {
                if ( empty( $url ) || empty( $label ) ) {
                        return '';
                }

                if ( function_exists( 'sm_security_mode_enabled' ) && sm_security_mode_enabled() ) {
                        return '<p class="sm-offline-help">' . esc_html__( 'External help is disabled while Smart Manager offline mode is active.', 'smart-manager-for-wp-e-commerce' ) . '</p>';
                }

                return sprintf(
                        '<p><a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></p>',
                        esc_attr( $class ),
                        esc_url( $url ),
                        esc_html( $label )
                );
        }

	public function __construct() {

		$this->sm_redirect_url = admin_url( 'admin.php?page=smart-manager' );

		self::$text_domain = (defined('SM_TEXT_DOMAIN')) ? SM_TEXT_DOMAIN : 'smart-manager-for-wp-e-commerce';
		self::$prefix = (defined('SM_PREFIX')) ? SM_PREFIX : 'sa_smart_manager';
		self::$sku = (defined('SM_SKU')) ? SM_SKU : 'sm';
		self::$plugin_file = (defined('SM_PLUGIN_FILE')) ? SM_PLUGIN_FILE : '';

		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_init', array( $this, 'smart_manager_welcome' ), 11 );
		add_action( 'admin_footer', array( $this, 'smart_manager_support_ticket_content' ) );

		$this->plugin_url = plugins_url( '', __FILE__ );
	}

	/**
	 * Handle welcome page
	 */
	public function show_welcome_page() {
		
		if( empty($_GET['landing-page']) ) {
			return;
		}
		
		switch ( $_GET['landing-page'] ) {
			case 'sm-about' :
				$this->about_screen();
			break;
			case 'sm-faqs' :
				$this->faqs_screen();
			break;
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {
				jQuery('#toplevel_page_smart-manager').find('.wp-first-item').closest('li').removeClass('current');
				jQuery('#toplevel_page_smart-manager').find('a[href$=sm-about]').closest('li').addClass('current');
				jQuery('#toplevel_page_smart-manager').find('a[href$=sm-faqs]').closest('li').addClass('current');
				jQuery('#sa_smart_manager_beta_post_query_table').find('input[name="include_data"]').attr('checked', true);
			});
		</script>
		<?php

	}

	/**
	 * Add styles just for this page, and remove dashboard page links.
	 */
	public function admin_head() {

		if ( ! ( isset( $_GET['page'] ) && ( "smart-manager" === $_GET['page'] ) && ( isset( $_GET['landing-page'] ) ) ) ) {
			return;
		}
		?>
		<style type="text/css">
			/*<![CDATA[*/
			.sm-welcome.about-wrap {
				max-width: unset !important;
			}
			.sm-welcome.about-wrap h3 {
				margin-top: 1em;
				margin-right: 0em;
				margin-bottom: 0.1em;
				font-size: 1.25em;
				line-height: 1.3em;
			}
			.sm-welcome.about-wrap .button-primary {
				margin-top: 18px;
			}
			.sm-welcome.about-wrap .button-hero {
				color: #FFF!important;
				border-color: #03a025!important;
				background: #03a025 !important;
				box-shadow: 0 1px 0 #03a025;
				font-size: 1em;
				font-weight: bold;
			}
			.sm-welcome.about-wrap .button-hero:hover {
				color: #FFF!important;
				background: #0AAB2E!important;
				border-color: #0AAB2E!important;
			}
			.sm-welcome.about-wrap p {
				margin-top: 0.6em;
				margin-bottom: 0.8em;
				line-height: 1.6em;
				font-size: 14px;
			}
			.sm-welcome.about-wrap .feature-section {
				padding-bottom: 5px;
			}
			#sm_promo_msg_content a {
				color: #A3B745 !important;
			}
			#sm_promo_msg_content .button-primary {
				background: #a3b745 !important;
				border-color: #829237 #727f30 #727f30 !important;
				color: #fff !important;
				box-shadow: 0 1px 0 #727f30 !important;
				text-shadow: 0 -1px 1px #727f30, 1px 0 1px #727f30, 0 1px 1px #727f30, -1px 0 1px #727f30 !important;

				animation-duration: 5s;
				animation-iteration-count: infinite;
				animation-name: shake-hv;
				animation-timing-function: ease-in-out;
			}
			div#TB_window {
				background: lightgrey;
			}
			@keyframes shake-hv {
				0%, 80% {
					transform: translate(0, 0) rotate(0); }
				60%, 70% {
					transform: translate(0, -0.5px) rotate(2.5deg); }
				62%, 72% {
					transform: translate(0, 1.5px) rotate(-0.5deg); }
				65%, 75% {
					transform: translate(0, -1.5px) rotate(2.5deg); }
				67%, 77% {
					transform: translate(0, 2.5px) rotate(-1.5deg); } }

			#sm_promo_msg_content input[type=checkbox]:checked:before {
				color: #A3B745 !important;
			}
			#sm_promo_valid_msg {
				text-align: center;
				padding-left: 0.5em;
				font-size: 0.8em;
				float: left;
				padding-top: 0.25em;
				font-style: italic;
				color: #A3B745;
			}
			.update-nag, .updated, .error {
				display: none;
			}

			.sm-video-container {
				position: relative;
				padding-top: 56.25%;
			}

			.sm-video-iframe {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
			}

			/*]]>*/
		</style>
		<script type="text/javascript">
			jQuery(function($) {
				$(document).ready(function() {
					$('#sm_promo_msg').insertBefore('.sm-welcome');
				});
			});
		</script>
		<?php
	}

	/**
	 * Smart Manager's Support Form
	 */
	function smart_manager_support_ticket_content() {

		if ( !( isset( $_GET['page'] ) && ( "smart-manager" === $_GET['page'] ) && ( isset( $_GET['landing-page'] ) && "sm-faqs" === $_GET['landing-page'] ) ) ) {
			return;
		}

		global $smart_manager_beta;

		if (!wp_script_is('thickbox')) {
			if (!function_exists('add_thickbox')) {
				require_once ABSPATH . 'wp-includes/general-template.php';
			}
			add_thickbox();
		}

		if( !is_callable( array( $smart_manager_beta, 'get_latest_upgrade_class' ) ) ){
			return;
		}

		$latest_upgrade_class = $smart_manager_beta->get_latest_upgrade_class();

		if ( ! method_exists( $latest_upgrade_class, 'support_ticket_content' ) ) return;

		$plugin_data = get_plugin_data( self::$plugin_file );
		$license_key = get_site_option( self::$prefix.'_license_key' );

		$latest_upgrade_class::support_ticket_content( self::$prefix, self::$sku, $plugin_data, $license_key, 'smart-manager-for-wp-e-commerce' );
	}

	/**
	 * Intro text/links shown on all about pages.
	 */
	private function intro() {

		$version = '';
		if( is_callable( array( 'Smart_Manager', 'get_version' ) ) ) {
			$version = Smart_Manager::get_version();
		}
		?>
		<h1><?php printf( 
		/* translators: %s: Plugin version number */
		__( 'Thank you for installing Smart Manager %s!', 'smart-manager-for-wp-e-commerce' ),
		$version ); ?></h1>

		<div style="margin-top:0.3em;"><?php _e( "Glad to have you onboard. We hope Smart Manager adds to your desired success 🏆", 'smart-manager-for-wp-e-commerce' ); ?></div>

		<div id="sm_welcome_feature_section" class="has-2-columns is-fullwidth feature-section col two-col">
			<div class="column col">
				<a href="<?php echo $this->sm_redirect_url; ?>" class="button button-hero"><?php _e( 'Get started with Smart Manager', 'smart-manager-for-wp-e-commerce' ); ?></a>
			</div>
                        <div class="column col last-feature">
                                <div class="sm-welcome-actions" style="text-align:right;">
                                        <?php if ( SMPRO === true ) { ?>
                                                <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=smart-manager#!/settings' ) ); ?>"><?php _e( 'Settings', 'smart-manager-for-wp-e-commerce' ); ?></a></p>
                                        <?php } ?>
                                        <?php
                                        $offline_mode_active = function_exists( 'sm_security_mode_enabled' ) && sm_security_mode_enabled();

                                        if ( $offline_mode_active ) {
                                                echo '<p class="sm-offline-help">' . esc_html__( 'Offline mode is active. External documentation links are hidden to keep your store self-contained.', 'smart-manager-for-wp-e-commerce' ) . '</p>';
                                        } else {
                                                echo $this->render_external_button( 'https://www.storeapps.org/support/contact-us/?utm_source=sm&utm_medium=welcome_page&utm_campaign=view_docs', __( 'Open support form (external site)', 'smart-manager-for-wp-e-commerce' ), 'button button-secondary' );
                                                echo $this->render_external_button( 'https://www.storeapps.org/knowledgebase_category/smart-manager/?utm_source=sm&utm_medium=welcome_page&utm_campaign=view_docs', __( 'Open Smart Manager documentation', 'smart-manager-for-wp-e-commerce' ), 'button button-primary' );
                                        }
                                        ?>
                                </div>
                        </div>
		</div>
		<br>
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab <?php if ( $_GET['landing-page'] == 'sm-about' ) echo 'nav-tab-active'; ?>" href="<?php echo esc_url( add_query_arg( array( 'landing-page' => 'sm-about' ), $this->sm_redirect_url ) ); ?>">
				<?php _e( "Know Smart Manager", 'smart-manager-for-wp-e-commerce' ); ?>
			</a>
			<a class="nav-tab <?php if ( $_GET['landing-page'] == 'sm-faqs' ) echo 'nav-tab-active'; ?>" href="<?php echo esc_url( add_query_arg( array( 'landing-page' => 'sm-faqs' ), $this->sm_redirect_url ) ); ?>">
				<?php _e( "FAQ's", 'smart-manager-for-wp-e-commerce' ); ?>
			</a>
		</h2>
		<?php
	}

	/**
	 * Output the about screen.
	 */
        public function about_screen() {
                ?>
                <div class="wrap sm-welcome about-wrap">

                        <?php $this->intro(); ?>

                        <div class="sm-about-summary">
                                <p><?php echo esc_html__( 'Smart Manager centralizes product, order, and customer management into a spreadsheet-style dashboard so you can work faster without leaving WordPress.', 'smart-manager-for-wp-e-commerce' ); ?></p>
                                <ul class="ul-disc">
                                        <li><?php echo esc_html__( 'Bulk update prices, inventory, and other fields across hundreds of records at once.', 'smart-manager-for-wp-e-commerce' ); ?></li>
                                        <li><?php echo esc_html__( 'Search and filter any column instantly to find exactly the records you need.', 'smart-manager-for-wp-e-commerce' ); ?></li>
                                        <li><?php echo esc_html__( 'Track edits with undo tasks and background processing to keep long-running operations safe.', 'smart-manager-for-wp-e-commerce' ); ?></li>
                                        <li><?php echo esc_html__( 'Stay in control with role-based access, column visibility rules, and store-wide audit tools.', 'smart-manager-for-wp-e-commerce' ); ?></li>
                                </ul>
                        </div>

                        <?php if ( function_exists( 'sm_security_mode_enabled' ) && sm_security_mode_enabled() ) : ?>
                                <p class="sm-offline-help"><?php echo esc_html__( 'Offline mode is enabled. Refer to the on-site Smart Manager help panels for guidance—no external resources are loaded in this mode.', 'smart-manager-for-wp-e-commerce' ); ?></p>
                        <?php else : ?>
                                <?php echo $this->render_external_button( 'https://www.storeapps.org/knowledgebase_category/smart-manager/?utm_source=sm&utm_medium=welcome_page&utm_campaign=sm_know', __( 'Open full Smart Manager documentation', 'smart-manager-for-wp-e-commerce' ), 'button button-primary' ); ?>
                        <?php endif; ?>
                </div>
                <?php
        }

	/**
	 * Output the FAQ's screen.
	 */
	public function faqs_screen() {
                ?>
                <div class="wrap sm-welcome about-wrap">

                        <?php $this->intro(); ?>

                        <h3 class="aligncenter"><?php echo __( 'FAQ / Common Problems', 'smart-manager-for-wp-e-commerce' ); ?></h3>

                        <div class="feature-section one-col" style="margin:0 auto;">
                                <div class="col">
                                        <h4><?php echo esc_html__( 'Smart Manager dashboard is empty', 'smart-manager-for-wp-e-commerce' ); ?></h4>
                                        <p><?php echo esc_html__( 'Confirm that Smart Manager and WooCommerce are both up to date and active. If the grid still appears blank, temporarily disable other plugins to check for conflicts.', 'smart-manager-for-wp-e-commerce' ); ?></p>

                                        <h4><?php echo esc_html__( 'Search does not return expected results', 'smart-manager-for-wp-e-commerce' ); ?></h4>
                                        <p><?php echo esc_html__( 'After changing search settings, reload the dashboard to clear cached filters. Ensure the columns you are searching are visible in the current column set.', 'smart-manager-for-wp-e-commerce' ); ?></p>

                                        <h4><?php echo esc_html__( 'How can I reach support?', 'smart-manager-for-wp-e-commerce' ); ?></h4>
                                        <p><?php echo esc_html__( 'Collect recent logs and screenshots so our team can review the issue quickly. Use the support form when external access is permitted.', 'smart-manager-for-wp-e-commerce' ); ?></p>

                                        <?php if ( function_exists( 'sm_security_mode_enabled' ) && sm_security_mode_enabled() ) : ?>
                                                <p class="sm-offline-help"><?php echo esc_html__( 'Offline mode is active. Enable external connectivity temporarily if you need to submit a support request from this page.', 'smart-manager-for-wp-e-commerce' ); ?></p>
                                        <?php else : ?>
                                                <?php echo $this->render_external_button( 'https://www.storeapps.org/support/contact-us/?utm_source=sm&utm_medium=welcome_page&utm_campaign=sm_faqs', __( 'Open support request form', 'smart-manager-for-wp-e-commerce' ) ); ?>
                                        <?php endif; ?>
                                </div>
                        </div>
                </div>

                <?php
        }
/**
	 * Sends user to the welcome page on first activation.
	 */
	public function smart_manager_welcome() {

		if ( ! get_transient( '_sm_activation_redirect' ) ) {
			return;
		}
		
		// Delete the redirect transient
		delete_transient( '_sm_activation_redirect' );

		wp_redirect( admin_url( 'admin.php?page=smart-manager&landing-page=sm-about' ) );
		
		exit;

	}
}

$GLOBALS['smart_manager_admin_welcome'] = new Smart_Manager_Admin_Welcome();
