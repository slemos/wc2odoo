<?php
/**
 * @file This is a PHP file for the WC2ODOO_Cron class.
 * @subpackage includes
 *
 * @package wc2odoo
 */

/**
 * Class WC2ODOO_Cron
 *
 * This class handles the cron jobs for syncing data between Odoo and WooCommerce.
 */
class WC2ODOO_Cron {

	/**
	 * The Odoo settings.
	 *
	 * @var mixed
	 */
	private $odoo_settings;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_option_woocommerce_woocommmerce_odoo_integration_settings', array( $this, 'add_option_cron' ), 10, 2 );
		add_action( 'update_option_woocommerce_woocommmerce_odoo_integration_settings', array( $this, 'update_product_cron' ), 10, 3 );
		add_action( 'odoo_process_order_refund', array( $this, 'update_woo_refunded_order' ) );
		add_action( 'odoo_process_import_product_create', array( $this, 'do_odoo_import_product_create' ) );
		add_action( 'odoo_process_import_product_update', array( $this, 'do_odoo_import_product_update' ) );
		add_action( 'odoo_process_import_create_categories', array( $this, 'do_odoo_import_create_categories' ) );
		add_action( 'odoo_process_import_create_attributes', array( $this, 'do_odoo_import_create_attributes' ) );
		add_action( 'odoo_process_import_order', array( $this, 'do_odoo_import_order' ) );
		add_action( 'odoo_process_import_refund_order', array( $this, 'do_odoo_import_refund_order' ) );
		add_action( 'odoo_process_import_coupon', array( $this, 'do_odoo_import_coupon' ) );
		add_action( 'odoo_process_import_customer', array( $this, 'do_odoo_import_customer' ) );
		add_action( 'odoo_process_export_customer', array( $this, 'do_odoo_export_customer' ) );
		add_action( 'odoo_process_export_product_create', array( $this, 'do_odoo_export_product_create' ) );
		add_action( 'odoo_process_export_create_categories', array( $this, 'do_odoo_export_create_categories' ) );
		add_action( 'odoo_process_export_create_attributes', array( $this, 'do_odoo_export_create_attributes' ) );
		add_action( 'odoo_process_export_order', array( $this, 'do_odoo_export_order' ) );
		add_action( 'odoo_process_export_refund_order', array( $this, 'do_odoo_export_refund_order' ) );
		add_action( 'odoo_process_export_coupon', array( $this, 'do_odoo_export_coupon' ) );

		add_action( 'wp_ajax_wc2odoo_product_import', array( $this, 'do_odoo_import_product_create' ) );
		add_action( 'wp_ajax_wc2odoo_product_export', array( $this, 'do_odoo_export_product_create' ) );

		$this->odoo_settings = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );
	}

	/**
	 * Add option cron.
	 *
	 * @param mixed $v The value.
	 * @param array $data The data.
	 */
	public function add_option_cron( $v, $data ) {
		$import_product_list = array( $data['odoo_import_create_product'] ?? '', $data['odoo_import_update_product'] ?? '', $data['odoo_import_update_stocks'] ?? '', $data['odoo_import_update_price'] ?? '' );
		if ( in_array( 'yes', $import_product_list, true ) ) {
			wp_schedule_event( time(), $data['odoo_import_create_product_frequency'], 'odoo_process_import_product_create' );
		}

		if ( isset( $data['odoo_import_create_categories'] ) && ( 'yes' === $data['odoo_import_create_categories'] && ! wp_next_scheduled( 'odoo_process_import_create_categories' ) ) ) {
			wp_schedule_event( time(), $data['odoo_import_create_categories_frequency'], 'odoo_process_import_create_categories' );
		}

		if ( isset( $data['odoo_import_create_attributes'] ) && ( 'yes' === $data['odoo_import_create_attributes'] && ! wp_next_scheduled( 'odoo_process_import_create_attributes' ) ) ) {
			wp_schedule_event( time(), $data['odoo_import_create_attributes_frequency'], 'odoo_process_import_create_attributes' );
		}

		if ( isset( $data['odoo_import_customer'] ) && ( 'yes' === $data['odoo_import_customer'] && ! wp_next_scheduled( 'odoo_process_import_customer' ) ) ) {
			wp_schedule_event( time(), $data['odoo_import_customer_frequency'], 'odoo_process_import_customer' );
		}

		$import_order_list = array( $data['odoo_import_update_order_status'] ?? '', $data['odoo_import_order'] ?? '' );
		if ( in_array( 'yes', $import_order_list, true ) ) {
			wp_schedule_event( time(), $data['odoo_import_order_frequency'], 'odoo_process_import_order' );
		}

		$import_coupon_list = array( $data['odoo_import_coupon'] ?? '', $data['odoo_import_coupon_update'] ?? '' );
		if ( in_array( 'yes', $import_coupon_list, true ) ) {
			wp_schedule_event( time(), $data['odoo_import_coupon_frequency'], 'odoo_process_import_coupon' );
		}

		$export_product_list = array( $data['odoo_export_create_product'] ?? '', $data['odoo_export_update_product'] ?? '', $data['odoo_export_update_stocks'] ?? '', $data['odoo_export_update_price'] ?? '' );
		if ( in_array( 'yes', $export_product_list, true ) ) {
			wp_schedule_event( time(), $data['odoo_export_create_product_frequency'], 'odoo_process_export_product_create' );
		}

		if ( isset( $data['odoo_export_create_categories'] ) && ( 'yes' === $data['odoo_export_create_categories'] && ! wp_next_scheduled( 'odoo_process_export_create_categories' ) ) ) {
			wp_schedule_event( time(), $data['odoo_export_create_categories_frequency'], 'odoo_process_export_create_categories' );
		}

		if ( isset( $data['odoo_export_create_attributes'] ) && ( 'yes' === $data['odoo_export_create_attributes'] && ! wp_next_scheduled( 'odoo_process_export_create_attributes' ) ) ) {
			wp_schedule_event( time(), $data['odoo_export_create_attributes_frequency'], 'odoo_process_export_create_attributes' );
		}

		if ( isset( $data['odoo_export_customer'] ) && ( 'yes' === $data['odoo_export_customer'] && ! wp_next_scheduled( 'odoo_process_export_customer' ) ) ) {
			wp_schedule_event( time(), $data['odoo_export_customer_frequency'], 'odoo_process_export_customer' );
		}

		$export_order_list = array( $data['odoo_export_update_order_status'] ?? '', $data['odoo_export_order'] ?? '' );
		if ( in_array( 'yes', $export_order_list, true ) ) {
			wp_schedule_event( time(), $data['odoo_export_order_frequency'], 'odoo_process_export_order' );
		}

		$export_coupon_list = array( $data['odoo_export_coupon'] ?? '', $data['odoo_export_coupon_update'] ?? '' );
		if ( in_array( 'yes', $export_coupon_list, true ) ) {
			wp_schedule_event( time(), $data['odoo_export_coupon_frequency'], 'odoo_process_export_coupon' );
		}

		if ( isset( $data['odoo_import_refund_order'] ) && 'yes' === $data['odoo_import_refund_order'] ) {
			wp_schedule_event( time(), $data['odoo_import_refund_order_frequency'], 'odoo_process_import_refund_order' );
		}

		if ( isset( $data['odoo_export_refund_order'] ) && 'yes' === $data['odoo_export_refund_order'] ) {
			wp_schedule_event( time(), $data['odoo_export_order_frequency'], 'odoo_process_export_refund_order' );
		}
	}

	/**
	 * Update the cron job for the product import and export.
	 *
	 * @param mixed $old_values The old values.
	 * @param mixed $new_values The new values.
	 */
	public function update_product_cron( $old_values, $new_values ) {
		$import_product_list = array( $new_values['odoo_import_create_product'], $new_values['odoo_import_update_product'], $new_values['odoo_import_update_price'] );

		if ( in_array( 'yes', $import_product_list, true ) ) {
			wp_clear_scheduled_hook( 'odoo_process_import_product_create' );
			wp_schedule_event( time(), $new_values['odoo_import_create_product_frequency'], 'odoo_process_import_product_create' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_product_create' );
		}

		if ( isset( $new_values['odoo_import_update_stocks'] ) && 'yes' === $new_values['odoo_import_update_stocks'] ) {
			wp_clear_scheduled_hook( 'odoo_process_import_update_stocks' );
			wp_schedule_event( time(), $new_values['odoo_import_create_product_frequency'], 'odoo_process_import_update_stocks' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_update_stocks' );
		}

		$export_product_list = array( $new_values['odoo_export_create_product'], $new_values['odoo_export_update_product'], $new_values['odoo_export_update_stocks'], $new_values['odoo_export_update_price'] );

		if ( in_array( 'yes', $export_product_list, true ) ) {
			wp_clear_scheduled_hook( 'odoo_process_export_product_create' );
			wp_schedule_event( time(), $new_values['odoo_export_create_product_frequency'], 'odoo_process_export_product_create' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_export_product_create' );
		}

		if ( 'yes' === $new_values['odoo_import_create_categories'] && ( ( ! wp_next_scheduled( 'odoo_process_import_create_categories' ) ) || ( $old_values['odoo_import_create_categories'] !== $new_values['odoo_import_create_categories'] ) ) ) {
			wp_clear_scheduled_hook( 'odoo_process_import_create_categories' );
			wp_schedule_event( time(), $new_values['odoo_import_create_categories_frequency'], 'odoo_process_import_create_categories' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_create_categories' );
		}

		if ( 'yes' === $new_values['odoo_export_create_categories'] && ( ( ! wp_next_scheduled( 'odoo_process_export_create_categories' ) ) || ( $old_values['odoo_export_create_categories'] !== $new_values['odoo_export_create_categories'] ) ) ) {
			wp_clear_scheduled_hook( 'odoo_process_export_create_categories' );
			wp_schedule_event( time(), $new_values['odoo_export_create_categories_frequency'], 'odoo_process_export_create_categories' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_export_create_categories' );
		}

		if ( 'yes' === $new_values['odoo_import_create_attributes'] ) {
			wp_clear_scheduled_hook( 'odoo_process_import_create_attributes' );
			wp_schedule_event( time(), $new_values['odoo_import_create_attributes_frequency'], 'odoo_process_import_create_attributes' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_create_attributes' );
		}

		if ( 'yes' === $new_values['odoo_export_create_attributes'] ) {
			wp_clear_scheduled_hook( 'odoo_process_export_create_attributes' );
			wp_schedule_event( time(), $new_values['odoo_export_create_attributes_frequency'], 'odoo_process_export_create_attributes' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_export_create_attributes' );
		}

		if ( 'yes' === $new_values['odoo_import_customer'] ) {
			wp_clear_scheduled_hook( 'odoo_process_import_customer' );
			wp_schedule_event( time(), $new_values['odoo_import_customer_frequency'], 'odoo_process_import_customer' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_customer' );
		}

		if ( 'yes' === $new_values['odoo_export_customer'] ) {
			wp_clear_scheduled_hook( 'odoo_process_export_customer' );
			wp_schedule_event( time(), $new_values['odoo_export_customer_frequency'], 'odoo_process_export_customer' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_export_customer' );
		}

		$import_coupon_list = array( $new_values['odoo_import_coupon'], $new_values['odoo_import_coupon_update'] );
		if ( in_array( 'yes', $import_coupon_list, true ) ) {
			wp_clear_scheduled_hook( 'odoo_process_import_coupon' );
			wp_schedule_event( time(), $new_values['odoo_import_coupon_frequency'], 'odoo_process_import_coupon' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_coupon' );
		}

		$export_coupon_list = array( $new_values['odoo_export_coupon'], $new_values['odoo_export_coupon_update'] );
		if ( in_array( 'yes', $export_coupon_list, true ) ) {
			wp_clear_scheduled_hook( 'odoo_process_export_coupon' );
			wp_schedule_event( time(), $new_values['odoo_export_coupon_frequency'], 'odoo_process_export_coupon' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_export_coupon' );
		}

		$new_values['odoo_import_update_order_status'] = 'no';
		$import_order_list                             = array( $new_values['odoo_import_update_order_status'], $new_values['odoo_import_order'] );
		if ( in_array( 'yes', $import_order_list, true ) ) {
			wp_clear_scheduled_hook( 'odoo_process_import_order' );
			wp_schedule_event( time(), $new_values['odoo_import_order_frequency'], 'odoo_process_import_order' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_order' );
		}

		$new_values['odoo_export_update_order_status'] = 'no';
		$export_order_list                             = array( $new_values['odoo_export_update_order_status'], $new_values['odoo_export_order'] );
		if ( in_array( 'yes', $export_order_list, true ) ) {
			wp_clear_scheduled_hook( 'odoo_process_export_order' );
			wp_schedule_event( time(), $new_values['odoo_export_order_frequency'], 'odoo_process_export_order' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_export_order' );
		}

		if ( isset( $new_values['odoo_import_refund_order'] ) && 'yes' === $new_values['odoo_import_refund_order'] ) {
			wp_clear_scheduled_hook( 'odoo_process_import_refund_order' );
			wp_schedule_event( time(), $new_values['odoo_import_refund_order_frequency'], 'odoo_process_import_refund_order' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_import_refund_order' );
		}

		if ( isset( $new_values['odoo_export_refund_order'] ) && 'yes' === $new_values['odoo_export_refund_order'] ) {
			wp_clear_scheduled_hook( 'odoo_process_export_refund_order' );
			wp_schedule_event( time(), $new_values['odoo_export_order_frequency'], 'odoo_process_export_refund_order' );
		} else {
			wp_clear_scheduled_hook( 'odoo_process_export_refund_order' );
		}
	}



	/**
	 * [update_woo_refunded_order Update the refunded order in WooCommerce].
	 */
	public function update_woo_refunded_order() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->sync_refund_order();
		}
	}

	/**
	 * Function do_odoo_import_product_create
	 * Perform the import of product creation from Odoo.
	 */
	public function do_odoo_import_product_create() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->import_product_odoo();
		}
	}

	/**
	 * [do_odoo_import_create_categories description]
	 */
	public function do_odoo_import_create_categories() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() && 'yes' === $this->odoo_settings['odoo_import_create_categories'] ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_import_categories();
		}
	}

	/**
	 * [do_odoo_import_create_attributes description]
	 */
	public function do_odoo_import_create_attributes() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_import_attributes();
		}
	}

	/**
	 * Function do_odoo_import_order
	 * Perform the import of orders from Odoo.
	 */
	public function do_odoo_import_order() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_import_order();
		}
	}

	/**
	 * [do_odoo_import_refund_order description]
	 */
	public function do_odoo_import_refund_order() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->sync_refund_order();
		}
	}

	/**
	 * [do_odoo_import_coupon import coupon from odoo]
	 */
	public function do_odoo_import_coupon() {
		$common_functions = new WC2ODOO_Common_Functions();

		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_import_coupon();
		}
	}

	/**
	 * Function do_odoo_import_customer
	 * Perform the import of customers from Odoo.
	 */
	public function do_odoo_import_customer() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_import_customer();
		}
	}

	/**
	 * Function do_odoo_export_product_create
	 * Perform the export of product creation to Odoo.
	 */
	public function do_odoo_export_product_create() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_export_product_odoo();
		}
	}

	/**
	 * Export categories to Odoo.
	 */
	public function do_odoo_export_create_categories() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_export_categories();
		}
	}

	/**
	 * Export attributes to Odoo.
	 */
	public function do_odoo_export_create_attributes() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_export_attributes();
		}
	}

	/**
	 * Export order to Odoo.
	 */
	public function do_odoo_export_order() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_export_order();
		}
	}

	/**
	 * Export refund order to Odoo.
	 */
	public function do_odoo_export_refund_order() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_export_refund_order();
		}
	}

	/**
	 * Export coupons to Odoo.
	 */
	public function do_odoo_export_coupon() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_export_coupon();
		}
	}

	/**
	 * Export customers to Odoo.
	 */
	public function do_odoo_export_customer() {
		$common_functions = new WC2ODOO_Common_Functions();
		if ( $common_functions->is_authenticate() ) {
			$odoo_object = new WC2ODOO_Functions();
			$odoo_object->do_export_customer();
		}
	}
}
new WC2ODOO_Cron();
