<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

class StoreApps_Marketplace {
	public static function init() {
                ?>
                <div class="wrap about-wrap sm-marketplace">
                        <h1 class="page-title"><?php esc_html_e( 'Smart Manager Marketplace', 'smart-manager-for-wp-e-commerce' ); ?></h1>
                        <p><?php esc_html_e( 'Smart Manager runs in a privacy-first mode and does not embed external marketing panels inside your dashboard.', 'smart-manager-for-wp-e-commerce' ); ?></p>

                        <?php if ( function_exists( 'sm_security_mode_enabled' ) && sm_security_mode_enabled() ) : ?>
                                <p class="sm-offline-help"><?php esc_html_e( 'Offline mode is active, so no remote promotions are loaded.', 'smart-manager-for-wp-e-commerce' ); ?></p>
                        <?php else : ?>
                                <p><?php esc_html_e( 'You can browse the StoreApps marketplace from their website if you would like to explore additional extensions.', 'smart-manager-for-wp-e-commerce' ); ?></p>
                                <p><a class="button button-primary" href="https://www.storeapps.org/woocommerce-plugins/?utm_source=sm&utm_medium=in_app_marketplace&utm_campaign=in_app_marketplace" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open StoreApps marketplace (external site)', 'smart-manager-for-wp-e-commerce' ); ?></a></p>
                        <?php endif; ?>
                </div>
                <?php
        }

}

new StoreApps_Marketplace();
