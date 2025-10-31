<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* translators: %d: records count */
$email_heading = ( ! empty( $background_process_params['updating_product_subscriptions_price'] ) ) ? sprintf( _x( 'Prices Updated for Existing %d Subscriptions', 'user name in bulk edit email content', 'smart-manager-for-wp-e-commerce' ),$background_process_params['id_count'] ) : str_replace('( ', '(', ucwords(str_replace('(', '( ', $records_str))) .' '.( ( $background_process_params['process_name'] == 'Duplicate Records' ) ? __('Duplicated', 'smart-manager-for-wp-e-commerce' ) : __('Updated', 'smart-manager-for-wp-e-commerce' ) );

$current_user = wp_get_current_user();
$display_name = ( ! empty( $current_user ) && ( is_object( $current_user ) ) && ( ! empty( $current_user->display_name ) ) ) ? $current_user->display_name : _x( 'there', 'scheduled export email user display name', 'smart-manager-for-wp-e-commerce' );
if ( function_exists( 'wc_get_template' ) ) {
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
} else if ( function_exists( 'woocommerce_get_template' ) ) {
	woocommerce_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
} else {
	echo $email_heading;
}

add_filter( 'wp_mail_content_type','sm_beta_pro_batch_set_content_type' );

function sm_beta_pro_batch_set_content_type(){
    return "text/html";
}

?>
<style type="text/css">
	.sm_code {
		padding: 3px 5px 2px;
		margin: 0 1px;
		background: rgba(0,0,0,.07);
	}
	#template_header {
		background-color: #7748AA !important;
		text-align: center !important;
	}
</style>
<?php
/* translators: %s: user display name */
$msg_body = '<p>'. sprintf( _x( 'Hi %s,', 'user name in bulk edit email content', 'smart-manager-for-wp-e-commerce' ), $display_name ) .'</p>';

$msg_body.= ( empty( $background_process_params['updating_product_subscriptions_price'] ) ) ? '<p>'. __( 'Smart Manager has successfully completed', 'smart-manager-for-wp-e-commerce'  ) .' \''. $background_process_params['process_name'] .'\' process on <span class="sm_code">'. get_bloginfo() .'</span>. </p>' : sprintf(
	'<p>%s</p><p>These updates were applied to subscriptions that:</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
	sprintf(
		_x( /* translators: %d: number of existing subscriptions updated */
			'Smart Manager has successfully updated prices for <strong>%d existing subscriptions</strong> based on your recent bulk edit action.',
			'subscription update notice',
			'smart-manager-for-wp-e-commerce'
		),
		$background_process_params['id_count']
	),
	_x(
		'Have <strong>auto-renewal enabled</strong>',
		'subscription condition',
		'smart-manager-for-wp-e-commerce'
	),
	_x(
		'Use supported payment methods like <strong>Stripe</strong>',
		'subscription condition',
		'smart-manager-for-wp-e-commerce'
	),
	_x(
		'Are in <strong>Active</strong>, <strong>On Hold</strong>, or <strong>Pending Cancellation</strong> status',
		'subscription condition',
		'smart-manager-for-wp-e-commerce'
	)
);
if( ( ! empty( $background_process_params['actions'] ) ) && ( empty( $background_process_params['updating_product_subscriptions_price'] ) ) ) {
	$msg_body .= '<p>'. __('Below are the lists of updates done:','smart-manager-for-wp-e-commerce' ) .'</p>
				<p> <table cellspacing="0" cellpadding="6" border="1" style="text-align:center;color:'.$email_text_color.' !important;margin-bottom: 25px;border: 1px solid #e5e5e5;">
					<tr style="font-weight:bold;color:'.$email_heading_color.' !important;">
					<th style="border: 1px solid #e5e5e5;">'. __('Field', 'smart-manager-for-wp-e-commerce' ) .'</th>
					<th style="border: 1px solid #e5e5e5;">'. __('Action', 'smart-manager-for-wp-e-commerce' ) .'</th>
					<th style="border: 1px solid #e5e5e5;">'. __('Value', 'smart-manager-for-wp-e-commerce' ) .'</th>
					<th style="border: 1px solid #e5e5e5;">'. __('Records Updated', 'smart-manager-for-wp-e-commerce' ) .'</th>
					</tr>';

	foreach ( $background_process_params['actions'] as $action ) {
		$msg_body .= '<tr style="font-size: 14px;">
						<td style="border: 1px solid #e5e5e5;">'. ( ! empty( $action['meta']['displayTitles']['field'] ) ? $action['meta']['displayTitles']['field'] : $action['type'] ) .'</td>
						<td style="border: 1px solid #e5e5e5;">'. ( ! empty( $action['meta']['displayTitles']['operator'] ) ? $action['meta']['displayTitles']['operator'] : $action['operator'] ) .'</td>
						<td style="border: 1px solid #e5e5e5;">'. ( ! empty( $action['value_display_text'] ) ? $action['value_display_text'] : ( is_array( $action['value'] ) ? $action['value'][0] : $action['value'] ) ) .'</td>
						<td style="border: 1px solid #e5e5e5;">'. $records_str .'</td>
						</tr>';
	}

	$msg_body .= '</table> </p>';
}

$msg_body .= '<br/>
				<p>
				<div style="color:#9e9b9b;font-size:0.95em;text-align: center;"> <div> '. __('If you like', 'smart-manager-for-wp-e-commerce' ) .' <strong>'. __('Smart Manager', 'smart-manager-for-wp-e-commerce' ) .'</strong>'. __(', please leave us a', 'smart-manager-for-wp-e-commerce' ) .' <a href="https://wordpress.org/support/view/plugin-reviews/smart-manager-for-wp-e-commerce?filter=5#postform" target="_blank" data-rated="Thanks :)">★★★★★</a> '.__('rating. A huge thank you from StoreApps in advance!', 'smart-manager-for-wp-e-commerce' ).'</div>';


echo $msg_body;
echo '<br>';

if ( function_exists( 'wc_get_template' ) ) {
	wc_get_template( 'emails/email-footer.php' );
} else if ( function_exists( 'woocommerce_get_template' ) ) {
	woocommerce_get_template( 'emails/email-footer.php' );
}
