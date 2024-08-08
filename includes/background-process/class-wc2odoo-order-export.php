<?php
/**
 * @file This is a PHP file for the WC2ODOO_Order_Export class.
 * @subpackage includes
 *
 * @package wc2odoo
 */

/**
 * Export Products from Odoo to Woo in background process.
 */
class WC2ODOO_Order_Export extends \WP_Background_Process {

	/**
	 * Action for the cron job to export products to Odoo.
	 *
	 * @var string
	 */
	protected $action = 'cron_odoo_export_order_process';

	/**
	 * Empty the data array.
	 */
	public function empty_data() {
		$this->data = array();
	}

	/**
	 * Export Products to Odoo.
	 *
	 * @param  int $item Order ID.
	 *
	 * @return bool false if the task was successful.
	 */
	public function task( $item ) {
		$odoo_object = new WC2ODOO_Functions();

		$odoo_object->get_odoo_api()->add_log( 'Exporting Order to Odoo: ' . $item );
		$result = false;
		try {
			$result = $odoo_object->order_create( $item );
		}
		catch ( Exception $e ) {
			$odoo_object->get_odoo_api()->add_log( '** Exception: ' . $e->getMessage() );
		}
		if ( $result ) {
			$odoo_object->get_odoo_api()->add_log( '-- Order Exported to Odoo');
		} else {
			$odoo_object->get_odoo_api()->add_log( '-- Order Export Failed');
		}
		$synced_order = get_option( 'wc2odoo_order_export_remaining_count' );

		if ( $synced_order < 1 ) {
			update_option( 'wc2odoo_order_export_remaining_count', 0 );
		} else {
			update_option( 'wc2odoo_order_export_remaining_count',	$synced_order - 1 );
		}

		return false;
	}

	/**
	 * Check if the process is running.
	 * @return bool
	 */
	public function is_processing() {
		return $this->is_process_running();
	}
}
