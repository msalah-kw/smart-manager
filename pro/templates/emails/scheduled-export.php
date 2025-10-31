<?php
/**
 * Scheduled exports email template
 *
 * @package   smart-manager-for-wp-e-commerce/pro/templates/
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$email_heading = sprintf(/* translators: %s: date */
	_x( 'Orders Export for %s', 'Email heading for scheduled export', 'smart-manager-for-wp-e-commerce' ),
	$date
);
$current_user = wp_get_current_user();
$display_name = ( ! empty( $current_user ) && ( is_object( $current_user ) ) && ( ! empty( $current_user->display_name ) ) ) ? $current_user->display_name : _x( 'there', 'scheduled export email user display name', 'smart-manager-for-wp-e-commerce' );
if ( function_exists( 'wc_get_template' ) ) {
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
} else if ( function_exists( 'woocommerce_get_template' ) ) {
	woocommerce_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
}

add_filter( 'wp_mail_content_type','sm_beta_pro_set_scheduled_export_content_type' );
if ( ! function_exists( 'sm_beta_pro_set_scheduled_export_content_type' ) ) {
	function sm_beta_pro_set_scheduled_export_content_type(){
		return "text/html";
	}
}

?>
<style type="text/css">
	.container {
		max-width: 37.5rem;
		margin: 0 auto;
	}
	.content {
		padding: 1.25rem;
	}
	.footer {
		padding: 1.25rem;
		text-align: center;
		font-size: 0.75rem;
		color: #777777;
	}
	a.download-link {
		background: <?php echo get_option( 'woocommerce_email_base_color', '#96588a' ); ?>;
		color: #ffffff;
		padding: 1rem 1.5rem;
		text-decoration: none;
		border-radius: 0.25rem;
		margin-bottom: 0.5rem;
		font-size: 1rem;
	}
</style>
<?php

echo '
<div class="container">
	<div class="content">
		<p>' . sprintf( /* translators: %s: user display name */
			_x( 'Hi %s,', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ), $display_name ) . '</p>
		<p>' . sprintf( /* translators: 1: Site title, 2: Date */
			_x( 'Smart Manager has successfully completed your scheduled orders export for <strong>%1$s</strong>  on <strong>%2$s</strong>.', 'scheduled export email message', 'smart-manager-for-wp-e-commerce' ), esc_html( $site_name ), $date ) . '</p>
		<p>' . _x( 'You can download your CSV file using the link below:', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ) . '</p>
		<p style="text-align:center; margin:1.5rem 0rem;">
			<a class="download-link" href="' . esc_url($csv_url) . '" download=true>
				' . _x( 'Download CSV File', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ) . '
			</a>
		</p>
		<p>' . sprintf( /* translators: %s: contact us link */
			_x( 'If you have any questions or need help, feel free to reach out to our <a href="%s">support team</a>.', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ), "https://www.storeapps.org/support/contact-us/?utm_source=sm&utm_medium=email&utm_campaign=sm_scheduled_exports" ) . '</p>
		<p style="margin-bottom:0">' . _x( 'Best regards,', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ) . '</p>
		<p>' . _x( 'The Smart Manager Team', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ) . '</p>
	</div>
	<br/>
	<div style="color:#9e9b9b;font-size:0.95em;text-align: center;"> <div> '. _x('If you like', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ) .' <strong>'. _x('Smart Manager', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ) .'</strong>'. _x(', please leave us a', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ) .' <a href="https://wordpress.org/support/view/plugin-reviews/smart-manager-for-wp-e-commerce?filter=5#postform" target="_blank" data-rated="Thanks :)">★★★★★</a> '._x('rating. A huge thank you from StoreApps in advance!', 'scheduled export email content', 'smart-manager-for-wp-e-commerce' ).'</div>
</div>';
echo '<br>';

if ( function_exists( 'wc_get_template' ) ) {
	wc_get_template( 'emails/email-footer.php' );
} else if ( function_exists( 'woocommerce_get_template' ) ) {
	woocommerce_get_template( 'emails/email-footer.php' );
}
