<?php
/**
 * @file This file is responsible for uninstalling the plugin.
 *
 * @package OdooForWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

global $wpdb;

$wpdb->query( "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE '%odoo%'" );
$wpdb->query( "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE '%odoo%'" );
$wpdb->query( "DELETE FROM `{$wpdb->termmeta}` WHERE `meta_key` LIKE '%odoo%'" );
$wpdb->query( "DELETE FROM `{$wpdb->order_itemmeta}` WHERE `meta_key` LIKE '%_order_line_id%'" );
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%odoo_shipping_product_id%'" );
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%wc2odoo%'" );
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%woocommerce_woocommmerce_odoo_integration_settings%'" );
