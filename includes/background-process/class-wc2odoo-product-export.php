<?php
/**
 * @file This is a PHP file for the WC2ODOO_Product_Export class.
 * @subpackage includes
 *
 * @package wc2odoo
 */

/**
 * Export Products from Odoo to Woo in background process.
 */
class WC2ODOO_Product_Export extends \WP_Background_Process {

	/**
	 * Action for the cron job to export products to Odoo.
	 *
	 * @var string
	 */
	protected $action = 'cron_odoo_export_product_process';

	/**
	 * Empty the data array.
	 */
	public function empty_data() {
		$this->data = array();
	}

	/**
	 * Export Products to Odoo.
	 *
	 * @param  [type] $item Product to export.
	 *
	 * @return bool false if the task was successful.
	 */
	public function task( $item ) {
		$odoo_object = new WC2ODOO_Functions();
		$odoo_object->get_odoo_api()->add_log( 'Exporting Product to Odoo: ' . $item );
		try {
			$odoo_object->sync_to_odoo( $item );
		} catch ( Exception $e ) {
			$odoo_object->get_odoo_api()->add_log( '-- Error: ' . $e->getMessage() );
		}
		$synced_product = get_option( 'wc2odoo_product_export_remaining_count' );

		if ( $synced_product < 1 ) {
			update_option( 'wc2odoo_product_export_remaining_count', 0 );
		} else {
			update_option( 'wc2odoo_product_export_remaining_count', $synced_product - 1 );
		}
		$odoo_object->get_odoo_api()->add_log( 'End export for product: ' . $item );

		return false;
	}

	public function is_processing() {
		return $this->is_process_running();
	}
}
