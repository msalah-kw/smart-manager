<?php
/*
* Plugin Name: Smart Manager - Advanced WooCommerce Bulk Edit & Inventory Management
* Plugin URI: https://www.storeapps.org/product/smart-manager/
* Description: <strong>Pro Version Installed</strong>. The #1 tool for WooCommerce inventory management, stock management, bulk edit, export, delete, duplicate...from one place using an Excel-like sheet editor.
* Version: 8.73.0
* Author: StoreApps
* Author URI: https://www.storeapps.org/
* Text Domain: smart-manager-for-wp-e-commerce
* Domain Path: /languages/
* Requires at least: 5.0
* Tested up to: 6.8.3
* Requires PHP: 5.6+
* WC requires at least: 3.0.0
* WC tested up to: 10.3.3
* Copyright (c) 2010 - 2025 StoreApps. All rights reserved.
* License: GNU General Public License v2.0
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

update_option( '_storeapps_connector_access_token', 'yes', 'yes' );
update_option( '_storeapps_connected', 'yes', 'yes' );
update_option( '_storeapps_connector_status', 1 );

if ( ! defined( 'SM_PLUGIN_FILE' ) ) {
	define( 'SM_PLUGIN_FILE', __FILE__ );
}

if ( ! class_exists( 'Smart_Manager' ) && file_exists( ( dirname( __FILE__ ) ) . '/class-smart-manager.php' ) ) {
	include_once 'class-smart-manager.php';
}
