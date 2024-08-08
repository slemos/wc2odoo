<?php
/**
 * @file This is a PHP file for the WC2ODOO_Product_Import class.
 * @subpackage includes
 *
 * @package wc2odoo
 */

/**
 * Import Products from Odoo to Woo in background process.
 */
class WC2ODOO_Product_Import extends \WP_Background_Process {

	/**
	 * The action for the cron job to import Odoo products.
	 *
	 * @var string
	 */
	protected $action = 'cron_odoo_import_product_process';

	/**
	 * Task to be executed for each item in the background process.
	 *
	 * @param mixed $item_id The ID of the item.
	 */
	protected function task( $item_id ) {
		$odoo_object = new WC2ODOO_Functions();
		$odoo_api    = new WC2ODOO_API();
		$item_data   = $odoo_api->fetch_record_by_id( 'product.template', $item_id, array() );
		$odoo_api->add_log( ' Starting task for item: ' . print_r( $item_id, true ) );
		$odoo_api->add_log( ' Products data id : ' . print_r( $item_data, true ) );

		if ( is_array( $item_data ) ) {
			$template = $item_data[0];
			$attr_v   = array();

			if ( $template['product_variant_count'] > 1 ) {
				if ( 0 === count( $attr_v ) ) {
					$attr_response = $odoo_api->read_all( 'product.template.attribute.value', /* $attrs */ array(), array( 'name', 'id' ) );

					$attr_values = json_decode( wp_json_encode( $attr_response ), true );
					foreach ( $attr_values as $key => $value ) {
						$attr_v[ $value['id'] ] = $value['name'];
					}
					$odoo_object->odoo_attr_values = $attr_v;
				}
				$products = $odoo_api->fetch_record_by_ids( 'product.product', $template['product_variant_ids'], array() );

				$attrs = $odoo_api->fetch_record_by_ids( 'product.template.attribute.line', $template['attribute_line_ids'], array( 'display_name', 'id', 'product_template_value_ids' ) );
				$attrs = json_decode( wp_json_encode( $attrs ), true );
				foreach ( $products as $pkey => $product ) {
					$attr_and_value = array();
					foreach ( $product['product_template_attribute_value_ids'] as $attr => $attr_value ) {
						foreach ( $attrs as $key => $attr ) {
							foreach ( $attr['product_template_value_ids'] as $key => $value ) {
								if ( $value === $attr_value ) {
									$attr_and_value[ $attr['display_name'] ] = $attr_v[ $value ];
								}
							}
						}
						$products[ $pkey ]['attr_and_value']            = $attr_and_value;
						$products[ $pkey ]['attr_value'][ $attr_value ] = $attr_v[ $attr_value ];
					}
				}

				$products['attributes'] = $attrs;

				$product_id = $odoo_object->sync_product_from_odoo( $template, false );
			} else {
				$product_id = $odoo_object->sync_product_from_odoo( $template );
			}
		}

		$synced_product = get_option( 'wc2odoo_product_remaining_import_count' );

		if ( $synced_product < 1 ) {
			update_option( 'wc2odoo_product_remaining_import_count', 0 );
		} else {
			update_option( 'wc2odoo_product_remaining_import_count', $synced_product - 1 );
		}

		return false;
	}

	public function is_processing() {
		return $this->is_process_running();
	}
}
