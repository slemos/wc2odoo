<?php
/**
 * @file This is a PHP file for the WC2ODOO_Functions class.
 * @subpackage includes
 *
 * @package wc2odoo
 */

if ( ! class_exists( 'WC2ODOO_Functions' ) ) {

	require_once WC2ODOO_INTEGRATION_PLUGINDIR . 'vendor/autoload.php';
	require_once WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-api.php';
	require_once WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-helpers.php';
	require_once WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-common-functions.php';
	require_once WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/background-process/class-wc2odoo-product-export.php';
	require_once WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/background-process/class-wc2odoo-product-import.php';
	require_once WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/background-process/class-wc2odoo-order-export.php';

	/**
	 * Class WC2ODOO_Functions.
	 *
	 * This class contains functions for exporting and importing products and creating customers and orders in Odoo.
	 */
	class WC2ODOO_Functions {

		/**
		 * The default SKU mapping for Odoo.
		 *
		 * @var string
		 */
		public $odoo_sku_mapping = 'default_code';

		/**
		 * Odoo attribute values.
		 *
		 * @var string
		 */
		public $odoo_attr_values = '';

		/**
		 * Odoo settings.
		 *
		 * @var array
		 */
		public $odoo_settings = array();

		/**
		 * Export products.
		 *
		 * @var mixed
		 */
		public $export_products;

		/**
		 * Import products.
		 *
		 * @var mixed
		 */
		public $import_products;

		/**
		 * Export orders.
		 *
		 * @var mixed
		 */
		public $export_orders;

		/**
		 * The Odoo API object.
		 *
		 * @var WC2ODOO_API
		 */
		private $odoo_api;

		/**
		 * @var $countries Country variable.
		 */
		private $countries;

		/**
		 * @var $states State variable.
		 */
		private $states;

		/**
		 * Constructor.
		 */
		public function __construct() {

			$odoo_integrations   = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );
			$this->odoo_settings = $odoo_integrations;
			if ( isset( $odoo_integrations['odooSkuMapping'] ) && '' !== $odoo_integrations['odooSkuMapping'] ) {
				$this->odoo_sku_mapping = $odoo_integrations['odooSkuMapping'];
			}

			$this->export_products = new WC2ODOO_Product_Export();
			$this->import_products = new WC2ODOO_Product_Import();
			$this->export_orders   = new WC2ODOO_Order_Export();
		}

		/**
		 * Create new order in odoo On Woo Checkout.
		 *
		 * @param  [int] $order_id [woocommerce order id].
		 */
		public function create_order_to_odoo( $order_id ) {
			if ( 'yes' === $this->odoo_settings['odoo_export_order_on_checkout'] ) {
				$this->order_create( $order_id );
			}
		}

		/**
		 * Create or update customer.
		 *
		 * @param mixed    $customer_data Customer data.
		 * @param int|null $customer_id   Customer ID.
		 */
		public function create_or_update_customer( $customer_data, $customer_id ) {
			$odoo_api = $this->get_odoo_api();
			$common   = new WC2ODOO_Common_Functions();

			if ( ! $common->is_authenticate() ) {
				return;
			}

			$all_meta_for_user = get_user_meta( $customer_data->ID );
			$state_county      = $this->get_state_and_country_codes( $all_meta_for_user['billing_state'][0], $all_meta_for_user['billing_country'][0] );
			$data              = array(
				'name'          => get_user_meta( $customer_data->ID, 'first_name', true ) . ' ' . get_user_meta( $customer_data->ID, 'last_name', true ),
				'display_name'  => get_user_meta( $customer_data->ID, 'first_name', true ) . ' ' . get_user_meta( $customer_data->ID, 'last_name', true ),
				'email'         => $customer_data->user_email,
				'customer_rank' => 1,
				'type'          => 'contact',
				'phone'         => $all_meta_for_user['billing_phone'][0],
				'street'        => $all_meta_for_user['billing_address_1'][0],
				'city'          => $all_meta_for_user['billing_city'][0],
				'l10n_latam_identification_type_id' => '4',
				'vat'           => $this->format_rut( $all_meta_for_user['billing_rut'][0] ),
				'l10n_cl_sii_taxpayer_type' => '1',
				'l10n_cl_dte_email' => $all_meta_for_user['billing_email'][0],
				'l10n_cl_activity_description' => $all_meta_for_user['billing_giro'][0] ?: 'Manicurista',
				'state_id'      => $state_county['state'],
				'country_id'    => $state_county['country'],
				'zip'           => $all_meta_for_user['billing_postcode'][0],
			);
			$odoo_api->add_log( 'Creating or updating customer' );

			if ( $customer_id ) {
				$response = $odoo_api->update_record( 'res.partner', $customer_id, $data );
			} else {
				$response = $odoo_api->create_record( 'res.partner', $data );
			}
			if ( is_numeric( $response ) ) {
				return $response;
			}
			$error_msg = 'Error for Create/Update customer => ' . $customer_data->user_email . ' Msg : ' . print_r( $response['faultString'], true );
			$odoo_api->add_log( $error_msg );

			return false;
		}

		/**
		 * Create new product in the Odoo.
		 *
		 * @param \WC_Product $product_data The product data to create in Odoo.
		 * @param bool        $for_order    Whether the product is being created for an order.
		 *
		 * @return bool|int The ID of the created product or false if there was an error
		 */
		public function create_product( $product_data, $for_order = false ) {
			$odoo_api = $this->get_odoo_api();
			if ( '' === $product_data->get_sku() ) {
				$error_msg = 'Error for Search product =>' . $product_data->get_id() . ' Msg : Invalid SKU';
				$odoo_api->add_log( $error_msg );

				return false;
			}
			$helper     = WC2ODOO_Helpers::get_helper();
			$attrs_res  = $odoo_api->read_all( 'product.attribute.value', array(), array( 'id', 'name', 'display_type', 'attribute_id', 'pav_attribute_line_ids' ) );
			$attrs      = json_decode( wp_json_encode( $attrs_res ), true );
			$odoo_attrs = array();

			if ( ! isset( $attrs_res['faultString'] ) ) {
				foreach ( $attrs as $akey => $attr ) {
					$odoo_attrs[ strtolower( $attr['attribute_id'][1] ) ][ strtolower( $attr['name'] ) ] = $attr;
				}
			} else {
				$error_msg = 'Error for Search product attribute =>' . $product_data->get_id() . ' Msg : ' . print_r( $attrs_res['faultString'], true );
				$odoo_api->add_log( $error_msg );

				return false;
			}

			$data = array(
				'name'                  => $product_data->get_name(),
				'sale_ok'               => true,
				'type'                  => 'product',
				$this->odoo_sku_mapping => $product_data->get_sku(),
				'attribute_line_ids'    => $this->get_attributes_line_ids( $odoo_attrs, $product_data->get_attributes() ),
				'weight'                => $product_data->get_weight(),
				'volume'                => (int) ( (int) $product_data->get_height() * (int) $product_data->get_length() * (int) $product_data->get_width() ),
			);
			if ( ! $for_order && 'yes' === $this->odoo_settings['odoo_export_create_categories'] ) {
				$data['categ_id'] = $this->get_category_id( $product_data );
			}
			if ( ! $for_order && 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
				$price = $product_data->get_sale_price() ?: $product_data->get_regular_price() ?: 0;
				$data['list_price'] = round( $price / 1.19, 0 );
			}
			if ( $helper->can_upload_image( $product_data ) ) {
				$data['image_1920'] = $helper->upload_product_image( $product_data );
			}
			// nico.
			$product_res = $odoo_api->create_record( 'product.product', $data );
			if ( ! isset( $product_res['faultString'] ) ) {
				return $product_res;
			}
			$error_msg = 'Error for Create product =>' . $product_data->get_id() . 'Msg : ' . print_r( $product_res['faultString'], true );
			$odoo_api->add_log( $error_msg );

			return false;
		}

		/**
		 * Syncs inventory with Odoo.
		 */
		public function inventory_sync() {
			global $wpdb;
			
			$product_count = $wpdb->get_row( "SELECT COUNT(*) as total_products FROM {$wpdb->posts} WHERE (post_type='product' OR post_type='product_variation') AND post_status='publish'" );
			$total_count   = $product_count->total_products;

			$limit            = 3;
			$total_loop_pages = ceil( $total_count / $limit );

			for ( $i = 0; $i < $total_loop_pages; ++$i ) {
				$sku_lot = $this->get_product_sku_lot( $i );
				foreach ( $sku_lot as $product_id => $woo_product_sku ) {
					$quantity = $this->search_item_and_update_inventory( $woo_product_sku, $product_id );
					if ( -1 === $quantity ) {
						$error_msg = 'Inventory Sync Error for product =>' . $woo_product_sku . ' Msg : Invalid quantity';
						$this->get_odoo_api()->add_log( $error_msg );
					}
				}
			}
		}

		/**
		 * Search product in Odoo and update the woo inventory.
		 *
		 * @param  string $item_sku   [woo product sku].
		 * @param  int    $product_id [woocommerce product id].
		 * 
		 * @return int
		 */
		public function search_item_and_update_inventory( $item_sku, $product_id ) {
			$quantity = -1;
			$odoo_api   = $this->get_odoo_api();
			try {
				$conditions = array( array( $this->odoo_sku_mapping, '=', $item_sku ) );
				$data     = $odoo_api->fetch_product_inventory( $conditions );
				if ( isset( $data['id'] ) ) {
					$quantity = $data['free_qty'];

					if ( is_numeric( $quantity ) ) {
						update_post_meta( $product_id, '_stock', $quantity );

						update_post_meta( $product_id, '_manage_stock', 'yes' );
						if ( $quantity > 0 ) {
							update_post_meta( $product_id, '_stock_status', 'instock' );
						} else {
							update_post_meta( $product_id, '_stock_status', 'outofstock' );
						}
					}
					else {
						update_post_meta( $product_id, '_stock', 0 );
						update_post_meta( $product_id, '_manage_stock', 'yes' );
						update_post_meta( $product_id, '_stock_status', 'outofstock' );
					}

				}
				// }
			} catch ( \Exception $e ) {
				$error_msg = 'Error for Searching  Product  for Sku =>' . $item_sku . 'Msg : ' . print_r( $e->getMessage(), true );
				$odoo_api->add_log( $error_msg );

				return -1;
			}

			return $quantity;
		}

		/**
		 * Create data for the odoo customer address.
		 *
		 * @param  [string]  $address_type [address type delivery/invoice].
		 * @param  [array]   $userdata    [user data].
		 * @param  [integer] $parent_id   [user_id ].
		 *
		 * @return [array]              [formated address data for the customer]
		 */
		public function create_address_data( $address_type, $userdata, $parent_id ) {
			$data     = array(
				'name'      => $userdata['first_name'] . ' ' . $userdata['last_name'],
				'email'     => $userdata['email'] ?? '',
				'street'    => $userdata['address_1'],
				'street2'   => $userdata['address_2'],
				'zip'       => $userdata['postcode'],
				'city'      => $userdata['city'] ?? '',
				'type'      => $address_type,
				'parent_id' => (int) $parent_id,
				'phone'     => $userdata['phone'] ?? false,
			);
			// $odoo_api = $this->get_odoo_api();
			if ( ! empty( $userdata['state'] ) || ! empty( $userdata['country'] ) ) {
				$state_county = $this->get_state_and_country_codes( $userdata['state'], $userdata['country'] );
				// $odoo_api->add_log( 'Odoo State : ' . print_r( $state_county, 1 ) );
				if ( ! empty( $state_county ) ) {
					$data['state_id']   = $state_county['state'];
					$data['country_id'] = $state_county['country'];
				}
			}

			return $data;
		}

		/**
		 * Create_invoice description.
		 *
		 * @param  [array] $odoo_customer [customer ids array].
		 * @param  [int]   $odoo_order_id    [order id].
		 *
		 * @return [int]                   [invoice Id]
		 */
		public function create_invoice_data( $odoo_customer, $odoo_order_id, $order_total ) {
			$odoo_api = $this->get_odoo_api();
			$order    = $odoo_api->fetch_record_by_id( 'sale.order', array( $odoo_order_id ), array( 'id', 'name', 'date_order' ) );
			$odoo_api->add_log( 'Preparing invoice data for order: ' . print_r( $order, true ) );
			$data              = array(
				'partner_id'               => (int) $odoo_customer['invoice_id'],
				'invoice_origin'           => $order['name'],
				'state'                    => 'draft',
				'type_name'                => 'Invoice',
				'invoice_payment_term_id'  => 1,
				'partner_shipping_id'      => (int) $odoo_customer['shipping_id'],
				'invoice_date'             => gmdate( 'Y-m-d', strtotime( $order['date_order'] ) ),
				'invoice_date_due'         => gmdate( 'Y-m-d', strtotime( $order['date_order'] ) ),
				'invoice_cash_rounding_id' => 1,
				// Rounding CLP.
				'currency_id'              => 44,
			);

			if ( 0 < $order_total) {
				$data['journal_id'] = $this->odoo_settings['invoiceJournal'];
			}
			else {
				$data['journal_id'] = 19;
			}

			$data['move_type']  = 'out_invoice';
			$odoo_api->add_log( 'Invoice data: ' . print_r( $data, true ) );
			return $data;
		}

		/**
		 * Create line item fo the invoice.
		 *
		 * @param  [int]   $odoo_invoice_id [invoice id].
		 * @param  [array] $products        [product array data].
		 *
		 * @return [int]  $invoice_line_item  [invoice line id]
		 */
		public function create_invoice_lines( $odoo_invoice_id, $products ) {
			$invoice_line_item = array();
			$odoo_api          = $this->get_odoo_api();
			$invoice           = $odoo_api->fetch_record_by_id( 'account.move', array( $odoo_invoice_id ) );

			foreach ( $products as $key => $product ) {
				$invoice_line_item[] = $odoo_api->create_record( 'account.move.line', $product );
			}

			return $invoice_line_item;
		}

		/**
		 * Create download url for the invoice.
		 *
		 * @param int $invoice_id odoo invoice id.
		 *
		 * @return string invoice downloadable url/''
		 */
		public function create_pdf_download_link( $invoice_id ) {
			$odoo_api = $this->get_odoo_api();
			$invoice  = $odoo_api->fetch_record_by_id( 'account.move', array( $invoice_id ), array( 'id', 'access_url', 'access_token' ) );

			$download_url = '';
			if ( isset( $invoice['id'] ) && $invoice['id'] === $invoice_id && isset( $invoice['access_token'] ) ) {
				$wc_setting = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );

				$host         = $wc_setting['client_url'];
				$access_token = $invoice['access_token'];
				$access_url   = $invoice['access_url'];
				$download     = true;
				$report_type  = 'pdf';
				$download_url = $host . $access_url . '?access_token=' . $access_token . '&report_type=' . $report_type . '&download=' . $download;
			}

			return $download_url;
		}

		/**
		 * Create refund invoice data.
		 *
		 * @param int $odoo_invoice_id Odoo invoice ID.
		 *
		 * @return array|false Refund invoice data or false.
		 */
		public function create_refund_invoice_data( $odoo_invoice_id ) {
			$odoo_api     = $this->get_odoo_api();
			$odoo_invoice = $odoo_api->fetch_record_by_id( 'account.move', array( $odoo_invoice_id ), array( 'id', 'name', 'partner_id', 'invoice_origin' ) );

			$odoo_api->add_log( 'Refund Invoice : ' . print_r( $odoo_invoice, 1 ) );
			if ( isset( $odoo_invoice['invoice_origin'] ) ) {
				$data = array(
					'reversed_entry_id' => (int) $odoo_invoice_id,
					'partner_id'        => $odoo_invoice['partner_id'][0],
					'journal_id'        => $this->odoo_settings['invoiceJournal'],
					'invoice_origin'    => $odoo_invoice['invoice_origin'],
					'type_name'         => 'Credit Note',
					'invoice_date'      => gmdate( 'Y-m-d' ),
					'invoice_date_due'  => gmdate( 'Y-m-d' ),
					'move_type'         => 'out_refund',
					'payment_state'     => 'not_paid',
				);

				return $data;
			}

			return false;
		}

		/**
		 * Search or create a guest user based on the order.
		 *
		 * @param \WC_Order $order The WooCommerce order object.
		 */
		public function search_or_create_guest_user( $order ) {
			$user_data = $order->get_address( 'billing' );
			$odoo_api  = $this->get_odoo_api();
			$odoo_api->add_log( 'Guest user email: ' . print_r( $user_data, true ) );
			$conditions  = array( array( 'email', '=', $user_data['email'] ) );
			$customer_id = $odoo_api->search_record( 'res.partner', $conditions );

			$odoo_api->add_log( print_r( $user_data['email'], true ) . ' customer found : ' . print_r( $customer_id, true ) );
			$state_county = $this->get_state_and_country_codes( $user_data['state'], $user_data['country'] );
			$data         = array(
				'name'          => $user_data['first_name'] . ' ' . $user_data['last_name'],
				'email'         => $user_data['email'] ?: '',
				'street'        => $user_data['address_1'],
				'street2'       => $user_data['address_2'],
				'zip'           => $user_data['postcode'],
				'city'          => $user_data['city'],
				'state_id'      => isset( $state_county['state'] ) ? $state_county['state'] : false,
				'country_id'    => isset( $state_county['country'] ) ? $state_county['country'] : 46,
				'type'          => 'contact',
				'phone'         => $user_data['phone'] ?: false,
				'customer_rank' => 1,
			);

			if ( ! $customer_id ) {
				$response = $odoo_api->create_record( 'res.partner', $data );

				if ( is_numeric( $response ) ) {
					$customer_id = $response;
				} else {
					$error_msg = 'Error for Create customer =>' . $user_data['email'] . 'Msg : ' . print_r( $response['faultString'], true );
					$odoo_api->add_log( $error_msg );

					return false;
				}
			} else {
				// Update the customer data in odoo.
				$response = $odoo_api->update_record( 'res.partner', $customer_id, $data );
				if ( isset( $response['faultString'] ) ) {
					$error_msg = 'Error for update customer =>' . $user_data['email'] . 'Msg : ' . print_r( $response, true );
					$odoo_api->add_log( $error_msg );

					return false;
				}
			}
			return $customer_id;
		}

		/**
		 * Get the state ID based on the state code and country code.
		 *
		 * @param string $state_code The state code.
		 * @param string $country_code The country code.
		 * @return array The state ID.
		 */
		public function get_state_and_country_codes( $state_code, $country_code ) {
			// Convert this variable into a singleton pattern.
			$odoo_api               = $this->get_odoo_api();
			$country_id             = $odoo_api->search_record( 'res.country', array( array( 'code', '=', $country_code ) ) );
			$state_codes            = array();
			$state_codes['country'] = $country_id;

			if ( 'Región Metropolitana de Santiago' === $state_code ) {
				$state_code = 'Metropolitana';
			}

			if ( $country_id ) {
				$state_codes['state'] = $odoo_api->search_record( 'res.country.state', array( array( 'name', 'like', "%{$state_code}%" ), array( 'country_id', '=', $country_id ) ) );
			} else {
				$state_codes['state'] = false;
			}

			return $state_codes;
		}

		/**
		 * Action to handle saving customer address in WooCommerce.
		 *
		 * @param int    $user_id       The user ID.
		 * @param string $load_address  The address type to load.
		 */
		public function action_woocommerce_customer_save_address( $user_id, $load_address ) {
			$odoo_user_exits = get_user_meta( $user_id, '_odoo_id', true );
			if ( isset( $odoo_user_exits ) ) {
				$user         = new \WC_Customer( $user_id );
				$address_type = ( 'shipping' === $load_address ) ? 'delivery' : 'invoice';
				$user_address = ( 'shipping' === $load_address ) ? $user->get_shipping() : $user->get_billing();

				if ( ! $this->can_create_address( $user_id, $user_address, $address_type ) ) {
					return false;
				}
				$address             = $this->create_address_data( $address_type, $user_address, $odoo_user_exits );
				$customer_address_id = get_user_meta( $user_id, '_odoo_' . $load_address . '_id', true );
				if ( ! $customer_address_id ) {
					$conditions          = array( array( 'parent_id', '=', $odoo_user_exits ), array( 'type', '=', $address_type ) );
					$customer_address_id = $this->search_odoo_customer( $conditions );
				}
				$odoo_api = $this->get_odoo_api();
				$odoo_api->add_log( 'Customer address id  : ' . print_r( $customer_address_id, true ) );

				if ( $customer_address_id ) {
					$updated = $odoo_api->update_record( 'res.partner', (int) $customer_address_id, $address );
					if ( isset( $updated['faultString'] ) ) {
						$error_msg = 'Unable To update customer Odoo Id ' . $customer_address_id . 'Msg : ' . print_r( $updated['faultString'], true );
						$odoo_api->add_log( $error_msg );

						return false;
					}
				} else {
					$address_res = $odoo_api->create_record( 'res.partner', $address );
					if ( ! isset( $address_res['faultString'] ) ) {
						update_user_meta( $user_id, '_odoo_' . $load_address . '_id', $address_res );
					} else {
						$error_msg = 'Unable To Create customer Id ' . $user_id . 'Msg : ' . print_r( $address_res['faultString'], true );
						$odoo_api->add_log( $error_msg );

						return false;
					}
				}
			}
		}



		/**
		 * Create invoice line based on tax.
		 *
		 * @param int    $invoice_id     The invoice ID.
		 * @param object $item           The item object.
		 * @param int    $product_id     The product ID.
		 * @param array  $customer_data  The customer data.
		 * @param array  $tax_data       The tax data.
		 */
		public function create_invoice_line_base_on_tax( $invoice_id, $item, $product_id, $customer_data, $tax_data ) {
			$wc_setting = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );

			$product      = $item->get_product();
			$price        = (string) $product->get_price() . '.00';
			$total_amount = $price * $item->get_quantity();
			$tax_amount   = $this->create_tax_amount( $tax_data, $total_amount );

			if ( 1 === $tax_data['price_include'] ) {
				$subtotal_amount = $total_amount - $tax_amount;
			} else {
				$subtotal_amount = $total_amount;
			}

			$invoice_line_data[] = array(
				'product_id'     => (int) $product_id,
				'name'           => $product->get_name(),
				'price_unit'     => $price + 0,
				'quantity'       => $item->get_quantity(),
				'move_id'        => $invoice_id,
				'account_id'     => (int) $wc_setting['odooAccount'],
				'partner_id'     => (int) $customer_data['invoice_id'],
				'tax_ids'        => array( array( 6, 0, array( (int) $tax_data['id'] ) ) ),
				'tax_tag_ids'    => array( array( 6, 0, array( 5 ) ) ),
				'price_subtotal' => $subtotal_amount,
			);

			$tax_amounts[] = $tax_amount;

			$invoice_line_data[] = array(
				'product_id'               => (int) $product_id,
				'name'                     => $tax_data['name'],
				'price_unit'               => abs( $tax_amount ),
				'price_subtotal'           => abs( $tax_amount ),
				'quantity'                 => 1.00,
				'move_id'                  => $invoice_id,
				'account_id'               => (int) $wc_setting['odooAccount'],
				'ref'                      => '',
				'partner_id'               => (int) $customer_data['invoice_id'],
				'exclude_from_invoice_tab' => true,
				'tax_base_amount'          => abs( $tax_amount ),
				'tax_tag_ids'              => array( array( 6, 0, array( 5, 30 ) ) ),
			);
			if ( 1 === $tax_data['price_include'] ) {
				$debtors_amount = -$total_amount;
			} else {
				$debtors_amount = -( array_sum( $tax_amounts ) + $total_amount );
			}

			$invoice_line_data[] = array(
				'product_id'               => (int) $product_id,
				'name'                     => 'INV' . gmdate( 'Y' ) . $invoice_id,
				'price_unit'               => $debtors_amount,
				'price_subtotal'           => $debtors_amount,
				'quantity'                 => 1.00,
				'move_id'                  => $invoice_id,
				'account_id'               => (int) $wc_setting['odooDebtorAccount'],
				'ref'                      => '',
				'partner_id'               => $customer_data['id'],
				'exclude_from_invoice_tab' => true,
			);

			return $invoice_line_data;
		}


		/**
		 * Create return invoice line based on tax.
		 *
		 * @param int    $invoice_id     The invoice ID.
		 * @param object $item           The item object.
		 * @param int    $product_id     The product ID.
		 * @param array  $customer_data  The customer data.
		 * @param array  $tax_data       The tax data.
		 *
		 * @return array $invoice_line_data
		 */
		public function create_return_invoice_line_base_on_tax( $invoice_id, $item, $product_id, $customer_data, $tax_data ) {
			$refunded_quantity      = $item->get_quantity();
			$refunded_line_subtotal = abs( $item->get_subtotal() );
			$refunded_item_id       = $item->get_meta( '_refunded_item_id' );

			$odoo_product_id = get_post_meta( $item->get_product_id(), '_odoo_id', true );

			$wc_setting = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );

			$product      = $item->get_product();
			$price        = round( $refunded_line_subtotal / $refunded_quantity, 2 );
			$invoice_id   = (int) $invoice_id;
			$total_amount = $refunded_line_subtotal;
			$gst_price    = round( ( $tax_data['amount'] / 100 ) * $total_amount, 2 );
			$tax_amount   = $this->create_tax_amount( $tax_data, $total_amount );

			if ( 1 === $tax_data['price_include'] ) {
				$subtotal_amount = $total_amount - $tax_amount;
			} else {
				$subtotal_amount = $total_amount;
			}

			$invoice_line_data[] = array(
				'product_id'     => (int) $odoo_product_id,
				'name'           => $product->get_name(),
				'price_unit'     => $price + 0,
				'quantity'       => $item->get_quantity(),
				'move_id'        => $invoice_id,
				'account_id'     => (int) $wc_setting['odooAccount'],
				'partner_id'     => (int) $customer_data['invoice_id'],
				'price_subtotal' => $subtotal_amount,
			);

			if ( 'no' === $this->odoo_settings['odoo_fiscal_position'] ) {
				$invoice_line_data['tax_ids'] = array( array( 6, 0, array( (int) $tax_data['id'] ) ) );
			}

			$tax_amounts[] = $tax_amount;

			$invoice_line_data[] = array(
				'product_id'               => (int) $product_id,
				'name'                     => $tax_data['name'],
				'price_unit'               => abs( $tax_amount ),
				'price_subtotal'           => abs( $tax_amount ),
				'quantity'                 => 1.00,
				'move_id'                  => $invoice_id,
				'account_id'               => (int) $wc_setting['odooAccount'],
				'ref'                      => '',
				'partner_id'               => (int) $customer_data['invoice_id'],
				'exclude_from_invoice_tab' => true,
				'tax_base_amount'          => abs( $tax_amount ),
			);
			if ( 1 === $tax_data['price_include'] ) {
				$debtors_amount = -$total_amount;
			} else {
				$debtors_amount = -( array_sum( $tax_amounts ) + $total_amount );
			}

			$invoice_line_data[] = array(
				'product_id'               => (int) $product_id,
				'name'                     => 'INV' . gmdate( 'Y' ) . $invoice_id,
				'price_unit'               => $debtors_amount,
				'price_subtotal'           => $debtors_amount,
				'quantity'                 => 1.00,
				'move_id'                  => $invoice_id,
				'account_id'               => (int) $wc_setting['odooDebtorAccount'],
				'ref'                      => '',
				'partner_id'               => $customer_data['id'],
				'exclude_from_invoice_tab' => true,
			);

			return $invoice_line_data;
		}

		/**
		 * Manage Customer Data.
		 *
		 * @param object $user  userdata.
		 * @param object $order order objects data.
		 *
		 * @return array|bool $customer_data  return customer data or false if error is found
		 */
		public function get_customer_data( $user, $order ) {
			$odoo_api      = $this->get_odoo_api();
			$customer_data = array();

			if ( $user && isset( $user->user_email ) ) {
				$customer_id = get_user_meta( $user->ID, '_odoo_id', true );
				$odoo_api->add_log( 'customer id : ' . print_r( $customer_id, 1 ) );

				// If user not exists in Odoo.
				if ( ! $customer_id ) {
					// Search record in the Odoo By email.
					$conditions  = array( array( 'email', '=', $user->user_email ), array( 'parent_id', '=', 'False' ) );
					$customer_id = $odoo_api->search_record( 'res.partner', $conditions );

					// If user not exists in Odoo then Create New Customer in odoo.
					if ( empty( $customer_id ) || false === $customer_id ) {
						$odoo_api->add_log( 'Error for Search customer =>' . $user->user_email . ' proceed to create it' );
						$customer_id = $this->create_or_update_customer( $user, null );
						update_user_meta( $user->ID, '_odoo_id', $customer_id );
					}
				}

				if ( is_numeric( $customer_id ) ) {
					$customer_data['id'] = $customer_id;

					$is_new_billing_address = true;
					$modified_addresses = false;

					$wc2odoo_billing_addresses = get_user_meta( $user->ID, '_wc2odoo_billing_addresses', true );

					$odoo_api->add_log( 'Billing address : ' . print_r( $wc2odoo_billing_addresses, true ) );

					$billing_address = $this->create_address_data( 'invoice', $order->get_address( 'billing' ), $customer_id );

					if ( ! empty( $wc2odoo_billing_addresses ) ) {
						foreach ( $wc2odoo_billing_addresses as $key => $wc2odoo_billing_address ) {
							if ( trim( strtolower( $wc2odoo_billing_address['street'] ) ) === trim( strtolower( $billing_address['street'] ) ) && $wc2odoo_billing_address['zip'] === $billing_address['zip'] ) {
								// Query Odoo to see if the address exists.
								$partner_invoice_id = $odoo_api->fetch_record_by_id( 'res.partner', array( $wc2odoo_billing_address['partner_invoice_id'] ) );
								if ( ! $partner_invoice_id ) {
									// remove the address from the list.
									$odoo_api->add_log( 'Removing billing address from the list: ' . print_r( $wc2odoo_billing_address, true ) );
									unset( $wc2odoo_billing_addresses[$key] );
									$modified_addresses = true;
								} else {
									$customer_data['invoice_id'] = $wc2odoo_billing_address['partner_invoice_id'];
									$is_new_billing_address      = false;
									break;
								}
							}
						}
						if ( $modified_addresses ) {
							$wc2odoo_billing_addresses = array_values( $wc2odoo_billing_addresses );
							update_user_meta( $user->ID, '_wc2odoo_billing_addresses', $wc2odoo_billing_addresses );
						}
					} else {
						$wc2odoo_billing_addresses = array();
					}

					if ( $is_new_billing_address ) {
						$billing_id = $odoo_api->create_record( 'res.partner', $billing_address );

						if ( $billing_id && ! isset( $billing_id['faultString'] ) ) {
							update_user_meta( $user->ID, '_odoo_billing_id', $billing_id );
							$customer_data['invoice_id'] = $billing_id;
						} else {
							$error_msg = 'Error for Creating  Billing Address for customer=>' . $customer_id . 'Msg : ' . print_r( $billing_id, true );
							$odoo_api->add_log( $error_msg );

							return false;
						}

						update_user_meta( $user->ID, '_odoo_billing_id', $billing_id );

						$billing_address['partner_invoice_id'] = $billing_id;

						$wc2odoo_billing_addresses[] = $billing_address;

						update_user_meta( $user->ID, '_wc2odoo_billing_addresses', $wc2odoo_billing_addresses );
					}

					$is_new_shipping_address = true;

					$wc2odoo_shipping_addresses = get_user_meta( $user->ID, '_wc2odoo_shipping_addresses', true );

					$shipping_address = $this->create_address_data( 'delivery', $order->get_address( 'shipping' ), $customer_id );

					if ( ! empty( $wc2odoo_shipping_addresses ) ) {
						foreach ( $wc2odoo_shipping_addresses as $wc2odoo_shipping_address ) {
							if ( trim( strtolower( $wc2odoo_shipping_address['street'] ) ) === trim( strtolower( $shipping_address['street'] ) ) && $wc2odoo_shipping_address['zip'] === $shipping_address['zip'] ) {
								$customer_data['shipping_id'] = $wc2odoo_shipping_address['partner_shipping_id'];
								$is_new_shipping_address      = false;

								break;
							}
						}
					} else {
						$wc2odoo_shipping_addresses = array();
					}

					if ( $is_new_shipping_address ) {
						$shipping_id = $odoo_api->create_record( 'res.partner', $shipping_address );

						if ( ! isset( $shipping_id['faultString'] ) ) {
							update_user_meta( $user->ID, '_odoo_billing_id', $shipping_id );
							$customer_data['shipping_id'] = $shipping_id;
						} else {
							$error_msg = 'Error for Creating Shipping Address for customer=> ' . $customer_id . ' Msg : ' . print_r( $shipping_id, true );
							$odoo_api->add_log( $error_msg );

							return false;
						}

						update_user_meta( $user->ID, '_odoo_shipping_id', $shipping_id );

						$shipping_address['partner_shipping_id'] = $shipping_id;

						$wc2odoo_shipping_addresses[] = $shipping_address;

						update_user_meta( $user->ID, '_wc2odoo_shipping_addresses', $wc2odoo_shipping_addresses );
					}
				}
			}

			if ( ! $user || false === $user ) {
				$customer = $this->search_or_create_guest_user( $order );

				if ( ! $customer ) {
					$error_msg = 'Error for Search customer =>' . $user->user_email . 'Msg : ' . print_r( $customer['msg'], true );
					$odoo_api->add_log( $error_msg );

					return false;
				}
				$customer_id                  = $customer;
				$customer_data['id']          = $customer_id;
				$customer_data['invoice_id']  = $billing_id ?? $customer_id;
				$customer_data['shipping_id'] = $shipping_id ?? $customer_id;
			}

			return $customer_data;
		}

		/**
		 * Create the tax amount based on the tax type and amount.
		 *
		 * @param array $tax    The tax details.
		 * @param float $amount The amount to calculate the tax for.
		 * @return float The calculated tax amount.
		 */
		public function create_tax_amount( $tax, $amount ) {
			$tax_amount = 0.00;

			switch ( $tax['amount_type'] ) {
				case 'fixed':
					$tax_amount = round( $tax['amount'], 2 );

					break;

				case 'percent':
					if ( 1 === $tax['price_include'] ) {
						$tax_included_price = round( $amount / ( 1 + $tax['amount'] / 100 ), 2 );
						$tax_amount         = $tax_included_price - $amount;
					} else {
						$tax_amount = round( ( $tax['amount'] / 100 ) * $amount, 2 );
					}

					break;

				case 'group':
					$tax_amount = round( ( $tax['amount'] / 100 ) * $amount, 2 );

					break;

				case 'division':
					$tax_included_price = round( $amount / ( 1 - $tax['amount'] / 100 ), 2 );
					$tax_amount         = $tax_included_price - $amount;

					break;

				default:
					break;
			}

			return $tax_amount;
		}

		/**
		 * Get the delivery product ID.
		 *
		 * @return int The delivery product ID.
		 */
		public function get_delivery_product_id() {
			$shpping_id = get_option( 'odoo_shipping_product_id' );
			if ( false !== $shpping_id ) {
				return $shpping_id;
			}

			return $this->create_shipping_product();
		}

		/**
		 * Create a shipping product in Odoo.
		 *
		 * @return int product id
		 */
		public function create_shipping_product() {
			$odoo_api = $this->get_odoo_api();
			$data     = array(
				'name'                  => __( 'WC Shipping Charge', 'wc2odoo' ),
				'service_type'          => 'manual',
				'sale_ok'               => false,
				'type'                  => 'service',
				$this->odoo_sku_mapping => 'wc_odoo_delivery',
				'description_sale'      => __( 'delivery product created by wc2odoo Integration', 'wc2odoo' ),
				'list_price'            => 0.00,
			);
			$id       = $odoo_api->create_record( 'product.product', $data );

			if ( ! isset( $id['faultString'] ) ) {
				add_option( 'odoo_shipping_product_id', $id, '', 'yes' );

				return $id;
			}
			return false;
		}

		/**
		 * Get the category ID for a product.
		 *
		 * @param object $product The product object.
		 *
		 * @return int The category ID.
		 */
		public function get_category_id( $product ) {
			$terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );

			if ( ! is_wp_error( $terms ) && count( $terms ) > 0 ) {
				$cat_id       = (int) $terms[0];
				$odoo_term_id = get_term_meta( $cat_id, '_odoo_term_id', true );

				if ( $odoo_term_id ) {
					return $odoo_term_id;
				}
				$odoo_api           = $this->get_odoo_api();
				$term               = get_term( $cat_id );
				$conditions         = array( array( 'name', '=', $term->name ) );
				$data               = array( 'name' => $term->name );
				$odoo_term_response = $odoo_api->search_record( 'product.category', $conditions );
				if ( false !== $odoo_term_response ) {
					$odoo_term_id = $odoo_term_response;
				} else {
					$error_msg = 'Error for Search Category =>' . $cat_id . 'Msg : ' . print_r( $odoo_term_response, true );
					$odoo_api->add_log( $error_msg );

					return false;
				}
				if ( is_array( $odoo_term_id ) ) {
					return $odoo_term_id[0];
				}
				$response = $odoo_api->create_record( 'product.category', $data );

				$odoo_api->add_log( 'Product categoy create response : ' . print_r( $response, true ) );

				if ( ! isset( $response['faultString'] ) ) {
					update_term_meta( $cat_id, '_odoo_term_id', $response );

					return $response;
				}
				$odoo_api->add_log( 'Error for Creating category for Id  =>' . $cat_id . 'Msg : ' . print_r( $response['faultString'], true ) );
			}
			return 1;
		}

		/**
		 * Sync refund order.
		 */
		public function sync_refund_order() {
			global $wpdb;
			$order_origins = $wpdb->get_results( "SELECT meta_value FROM {$wpdb->postmeta}  WHERE meta_key='_odoo_order_origin'", 'OBJECT_K' );

			$refunded_invoices = $wpdb->get_results( "SELECT meta_value FROM {$wpdb->postmeta}  WHERE meta_key='_odoo_return_invoice_id'", 'OBJECT_K' );

			$origins              = array_keys( $order_origins );
			$refunded_invoice_ids = array_keys( $refunded_invoices );

			$odoo_api      = $this->get_odoo_api();
			$odoo_settings = $this->odoo_settings;

			if ( isset( $odoo_settings['odooVersion'] ) && 13 === $odoo_settings['odooVersion'] ) {
				$conditions = array( array( 'type', '=', 'out_refund' ) );
			} else {
				$conditions = array( array( 'move_type', '=', 'out_refund' ) );
			}
			if ( count( $refunded_invoice_ids ) > 0 ) {
				$conditions[] = array( 'id', 'not in', $refunded_invoice_ids );
			}
			if ( count( $origins ) > 0 ) {
				$conditions[] = array( 'invoice_origin', 'in', $origins );
			}
			$conditions[]   = array( 'state', '=', 'posted' );
			$invoice_fields = array( 'id', 'invoice_origin', 'invoice_line_ids' );
			$invoices       = $odoo_api->search_records( 'account.move', $conditions, $invoice_fields );

			// TODO: Revisar si el foreach funciona o no.
			if ( $invoices ) {
				$invoices = json_decode( wp_json_encode( $invoices ), true );
				$odoo_api->add_log( 'Refund order : ' . print_r( $invoices, true ) );
				if ( 0 === count( $invoices ) ) {
					return;
				}
				foreach ( $invoices as $key => $invoice ) {
					$conditions          = array( array( 'id', 'in', $invoice['invoice_line_ids'] ) );
					$invoice_line_fields = array( 'price_total', 'price_subtotal', 'quantity', 'product_id', 'tax_ids' );
					$invoice_lines       = $odoo_api->search_records( 'account.move.line', $conditions, $invoice_line_fields );

					if ( $invoice_lines ) {
						$invoice_lines = json_decode( wp_json_encode( $invoice_lines ), true );
						if ( is_array( $invoice_lines ) ) {
							$inv_lines = array();
							foreach ( $invoice_lines as $ilkey => $invioce_line ) {
								if ( isset( $invioce_line['product_id'][0] ) ) {
									$product_id = $this->get_post_id_by_meta_key_and_value( '_odoo_id', $invioce_line['product_id'][0] );
									if ( $product_id ) {
										$inv_lines[ $product_id ]                  = $invioce_line;
										$inv_lines[ $product_id ]['wc_product_id'] = $product_id;
									}
								}
							}

							if ( ! empty( $invoice['invoice_origin'] ) ) {
								$conditions = array( array( 'name', '=', $invoice['invoice_origin'] ) );
								$order      = $odoo_api->search_record( 'sale.order', $conditions );
								$odoo_api->add_log( 'Sale ordder : ' . print_r( $order, true ) );

								if ( false !== $order ) {
									$order    = json_decode( wp_json_encode( $order ), true );
									$order_id = $this->get_post_id_by_meta_key_and_value( '_odoo_order_id', $order[0] );
									if ( $order_id ) {
										$return_id = get_post_meta( $order_id, '_odoo_return_invoice_id', true );
										if ( $return_id ) {
											$error_msg = 'Refund Already Synced For Order  => ' . $order_id;
											$odoo_api->add_log( $error_msg );

											return false;
										}
										$this->wc_order_refund( $order_id, $inv_lines, $invoice['id'] );
									}
								}
							}
						}
					}
				}
			}
		}

		/**
		 * Refunds the WC order.
		 *
		 * @param int   $order_id The ID of the WC order.
		 * @param array $inv_lines The invoice lines.
		 * @param int   $inv_id The ID of the invoice.
		 */
		public function wc_order_refund( $order_id, $inv_lines, $inv_id ) {
			$order    = wc_get_order( $order_id );
			$odoo_api = $this->get_odoo_api();

			// If it's something else such as a WC_Order_Refund, we don't want that.
			if ( ! is_a( $order, 'WC_Order' ) ) {
				$msg = 'Provided ID is not a WC Order : ' . $order_id;
				$odoo_api->add_log( $msg );

				return false;
			}
			if ( 'refunded' === $order->get_status() ) {
				$msg = 'Order has been already refunded : ' . $order_id;
				$odoo_api->add_log( $msg );

				return false;
			}
			if ( count( $order->get_refunds() ) > 0 ) {
				$msg = 'Order has been already refunded : ' . $order_id;
				$odoo_api->add_log( $msg );

				return false;
			}
			$refund_amount = 0;
			$line_items    = array();
			// get tax id from the admin setting.
			$tax_id = (int) $this->odoo_settings['odooTax'];

			$tax_data_odoo = $odoo_api->fetch_record_by_id( 'account.tax', array( $tax_id ) );

			$order_items = $order->get_items();
			if ( $order_items ) {
				foreach ( $order_items as $item_id => $item ) {
					if ( isset( $inv_lines[ $item->get_product_id() ] ) ) {
						$current_item = $inv_lines[ $item->get_product_id() ];
						if ( 1 === $tax_data_odoo['price_include'] ) {
							$refund_tax = abs( $this->create_tax_amount( $tax_data_odoo, $current_item['price_total'] ) );
						} else {
							$refund_tax = $this->create_tax_amount( $tax_data_odoo, $current_item['price_subtotal'] );
						}
						$refund_amount = wc_format_decimal( $refund_amount + $current_item['price_subtotal'] + $refund_tax );

						$line_items[ $item_id ] = array(
							'qty'          => abs( $current_item['quantity'] ),
							'refund_total' => wc_format_decimal( $current_item['price_subtotal'] ),
							'refund_tax'   => array( 1 => wc_format_decimal( abs( $refund_tax ) ) ),
						);
					}
				}
			}
			if ( $refund_amount < 1 ) {
				$msg = 'Refund Created For for' . $order_id . ' Msg Invalid Refund Amount ' . $refund_amount;
				$odoo_api->add_log( $msg );

				return false;
			}
			$refund_reason = 'Odoo Return';
			$refund_data   = array(
				'amount'         => $refund_amount,
				'reason'         => $refund_reason,
				'order_id'       => $order_id,
				'line_items'     => $line_items,
				'refund_payment' => false,
			);

			$refund = wc_create_refund( $refund_data );

			if ( ! is_wp_error( $refund ) ) {
				update_post_meta( $order_id, '_odoo_return_invoice_id', $inv_id );
			} else {
				$msg = 'Error In creating Refund for' . $order_id . 'msg' . print_r( array( $refund_data, $refund ), true );
				$odoo_api->add_log( $msg );

				return false;
			}
		}

		/**
		 * Retrieves the post ID by meta key and value.
		 *
		 * @param string $key   The meta key.
		 * @param string $value The meta value.
		 * @return int|false The post ID if found, false otherwise.
		 */
		public function get_post_id_by_meta_key_and_value( $key, $value ) {
			global $wpdb;
			$meta = $wpdb->get_results( 'SELECT post_id FROM `' . $wpdb->postmeta . "` WHERE meta_key='" . esc_sql( $key ) . "' AND meta_value='" . esc_sql( $value ) . "'" );
			if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {
				$meta = $meta[0];
			}
			if ( is_object( $meta ) ) {
				return $meta->post_id;
			}

			return false;
		}

		/**
		 * Retrieves the user ID by meta key and value.
		 *
		 * @param string $key   The meta key.
		 * @param string $value The meta value.
		 * @return int|false The user ID if found, false otherwise.
		 */
		public function get_user_id_by_meta_key_and_value( $key, $value ) {
			global $wpdb;
			$meta = $wpdb->get_results( 'SELECT user_id FROM `' . $wpdb->usermeta . "` WHERE meta_key='" . esc_sql( $key ) . "' AND meta_value='" . esc_sql( $value ) . "'" );
			if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {
				$meta = $meta[0];
			}
			if ( is_object( $meta ) ) {
				return $meta->user_id;
			}

			return false;
		}

		/**
		 * Update the quantity of a product.
		 *
		 * @param int $product_id The ID of the product.
		 * @param int $quantity The new quantity of the product.
		 * @param int $template The ID of the product template (optional).
		 */
		public function update_product_quantity( $product_id, $quantity, $template = 0 ) {
			$odoo_api = $this->get_odoo_api();

			$template_id = ( 0 === $template ) ? $product_id : $template;
			$quantity_id = $odoo_api->create_record(
				'stock.change.product.qty',
				array(
					'new_quantity'    => $quantity,
					'product_tmpl_id' => $template_id,
					'product_id'      => $product_id,
				)
			);
			if ( ! isset( $quantity_id['faultString'] ) ) {
				$ret_val = $odoo_api->custom_api_call( 'stock.change.product.qty', 'change_product_qty', array( $quantity_id ) );
			}
		}

		/**
		 * Import products from Odoo.
		 */
		public function import_product_odoo() {
			$odoo_api = $this->get_odoo_api();

			$module_condition        = array( array( 'application', '=', true ), array( 'state', '=', 'installed' ), array( 'name', '=', 'point_of_sale' ) );
			$installed_modules       = $odoo_api->search_count( 'ir.module.module', $module_condition );
			$is_pos_module_installed = false;
			if ( $installed_modules > 0 ) {
				$is_pos_module_installed = true;
			}
			$conditions = array( array( 'sale_ok', '=', true ), array( 'product_variant_count', '=', (int) 1 ) );

			if ( 'yes' === $this->odoo_settings['odoo_import_pos_product'] && $is_pos_module_installed ) {
				$conditions[] = array( 'available_in_pos', '=', false );
			}

			$total_products_count = $odoo_api->search_count( 'product.template', $conditions );
			$total_products       = $odoo_api->search(
				'product.template',
				$conditions,
				array(
					'offset' => 0,
					'limit'  => $total_products_count,
				)
			);
			$total_products       = json_decode( wp_json_encode( $total_products ), true );
			$odoo_api->add_log( 'Total Products : ' . print_r( $total_products, true ) );
			if ( false !== $total_products ) {
				foreach ( $total_products as $tkey => $product_id ) {
					$this->import_products->push_to_queue( $product_id );
				}
			}

			$this->import_products->save()->dispatch();
			update_option( 'wc2odoo_product_import_count', $total_products_count );
			update_option( 'wc2odoo_product_remaining_import_count', $total_products_count );

			wp_send_json_success(
				array(
					'status'  => 1,
					'message' => __(
						'Import process has started for ',
						'wc2odoo'
					) . $total_products_count . __(
						' products',
						'wc2odoo'
					),
				)
			);
		}

		/**
		 * Import products from Odoo.
		 */
		public function do_import_products() {
			$odoo_api = $this->get_odoo_api();

			$module_condition        = array( array( 'application', '=', true ), array( 'state', '=', 'installed' ) );
			$installed_modules       = $odoo_api->read_all( 'ir.module.module', $module_condition, array( 'name', 'state' ) );
			$is_pos_module_installed = false;
			foreach ( $installed_modules as $key => $installed_module ) {
				if ( 'point_of_sale' === $installed_module['name'] ) {
					$is_pos_module_installed = true;
				}
			}
			$conditions = array( array( 'sale_ok', '=', '1' ), array( 'product_variant_count', '=', '1' ) );

			if ( 'yes' === $this->odoo_settings['odoo_import_pos_product'] && $is_pos_module_installed ) {
				$conditions[] = array( 'available_in_pos', '=', false );
			}

			$templates = $odoo_api->read_all( 'product.template', $conditions, array() );
			$attr_v    = array();
			// TODO: a futuro corregir todo esto.
			if ( ! isset( $templates['faultCode'] ) && is_array( $templates ) && count( $templates ) > 0 ) {
				foreach ( $templates as $tkey => $template ) {
					if ( ! ( $template['product_variant_count'] > 1 ) ) {
						$product_id = $this->sync_product_from_odoo( $template );
					}
				}
			}
		}

		/**
		 * Sync product from Odoo.
		 *
		 * @param array $data The data of the product.
		 * @param bool  $for_order Whether the sync is for an order.
		 * @param array $variations The variations of the product.
		 * @return int|false The product ID.
		 */
		public function sync_product_from_odoo( $data, $for_order = false, $variations = array() ) {
			$odoo_api = $this->get_odoo_api();

			$post_data = array(
				'post_author'  => 1,
				'post_content' => $data['description_sale'] ?? '',
				'post_status'  => ( 1 === $data['active'] ) ? 'publish' : 'draft',
				'post_title'   => $data['name'] ?? '',
				'post_parent'  => 0,
				'post_type'    => 'product',
				'post_excerpt' => $data['name'] ?? '',
			);

			if ( isset( $data['id'] ) ) {
				// get Post id if record already exists in woocommerce.
				$post = $this->get_post_id_by_meta_key_and_value( '_odoo_id', $data['id'] );
				$odoo_api->add_log( print_r( $data['id'], true ) . ' Product id from meta by ID : ' . print_r( $post, true ) );
				if ( ! $post ) {
					if ( '' !== $data[ $this->odoo_sku_mapping ] ) {
						$post = $this->get_post_id_by_meta_key_and_value( '_sku', $data[ $this->odoo_sku_mapping ] );
						$odoo_api->add_log( print_r( $data['id'], true ) . ' Product id from meta by SKU : ' . print_r( $post, true ) );
					}
				}

				if ( $post ) {
					if ( ! $for_order && 'no' === $this->odoo_settings['odoo_import_update_product'] ) {
						return false;
					}

					$post_data['ID'] = $post;
					$new_slug        = sanitize_title( $data['name'] );
					// use this line if you have multiple posts with the same title.
					$new_slug               = wp_unique_post_slug( $new_slug, $post_data['ID'], $post_data['post_status'], $post_data['post_type'], 0 );
					$post_data['post_name'] = $data['name'];
					$post_id                = wp_update_post( $post_data );
				} else {
					$post_id = wp_insert_post( $post_data );
				}

				$odoo_api->add_log( 'Woo product Id : ' . print_r( $post_id, true ) );

				wp_set_object_terms( $post_id, ( count( $variations ) > 0 ) ? 'variable' : 'simple', 'product_type' );

				update_post_meta( $post_id, '_visibility', 'visible' );
				update_post_meta( $post_id, '_description', $data['description_sale'] );
				update_post_meta( $post_id, '_sku', $data[ $this->odoo_sku_mapping ] );
				update_post_meta( $post_id, '_product_attributes', array() );

				if ( ! $for_order && 'yes' === $this->odoo_settings['odoo_import_update_price'] ) {
					update_post_meta( $post_id, '_regular_price', $data['list_price'] );
					update_post_meta( $post_id, '_sale_price', $data['list_price'] );
					update_post_meta( $post_id, '_price', $data['list_price'] );

					if ( isset( $data['pricelist_item_count'] ) && $data['pricelist_item_count'] > 0 ) {
						$this->get_and_set_sale_price( $post_id, $data );
					}
				}

				if ( ! $for_order && 'yes' === $this->odoo_settings['odoo_import_update_stocks'] ) {
					update_post_meta( $post_id, '_manage_stock', 'yes' );
					update_post_meta( $post_id, '_stock', $data['qty_available'] );
					$stock_status = ( $data['qty_available'] > 0 ) ? 'instock' : 'outofstock';
					update_post_meta( $post_id, '_stock_status', wc_clean( $stock_status ) );
					update_post_meta( $post_id, '_saleunit', $data['uom_name'] ?: 'each' );
					update_post_meta( $post_id, '_stockunit', $data['uom_name'] ?: 'each' );
					wp_set_post_terms( $post_id, $stock_status, 'product_visibility', true );
				}

				// Stock Management Meta Fields.
				update_post_meta( $post_id, '_sold_individually', '' );
				update_post_meta( $post_id, '_weight', $data['weight'] );
				update_post_meta( $post_id, '_cube', $data['volume'] );
				update_post_meta( $post_id, '_odoo_id', $data['id'] );
				if ( isset( $data['categ_id'][1] ) ) {
					$category['complete_name'] = $data['categ_id'][1];
					$term_id                   = $this->create_wc_category( $category );
					wp_set_object_terms( $post_id, $term_id, 'product_cat' );
				}

				if ( '' !== $data['image_1024'] ) {
					$helper    = WC2ODOO_Helpers::get_helper();
					$attach_id = $helper->save_image( $data );
					set_post_thumbnail( $post_id, $attach_id );
				}

				if ( count( $variations ) > 0 ) {
					$odoo_api->add_log( 'Product has variations : ' . print_r( $variations, true ) );
				}

				return $post_id;
			}
			return false;
		}

		/**
		 * Import categories from Odoo.
		 */
		public function do_import_categories() {
			$odoo_api   = $this->get_odoo_api();
			$categories = $odoo_api->read_all( 'product.category', array(), array( 'id', 'name', 'complete_name', 'child_id', 'parent_id' ) );
			$new_cats   = array();
			if ( ! isset( $categories['faultString'] ) ) {
				$categories = json_decode( wp_json_encode( $categories ), true );

				if ( count( $categories ) > 0 ) {
					foreach ( $categories as $key => $category ) {
						if ( count( $category['child_id'] ) > 0 ) {
							foreach ( $category['child_id'] as $child ) {
								$childkey                       = array_search( $child, array_column( $categories, 'id' ), true );
								$categories[ $key ]['childs'][] = $categories[ $childkey ];
							}
						}
						$new_cats[ $category['id'] ] = $categories[ $key ];
					}
					ksort( $new_cats );
					foreach ( $new_cats as $key => $cat ) {
						$this->create_wc_product_category( $cat );
					}
				}
			} else {
				$odoo_api->add_log( 'No Categories found' . print_r( $categories, true ) );
			}
		}

		/**
		 * Create WooCommerce product category.
		 *
		 * @param array $category The category data.
		 */
		public function create_wc_product_category( $category ) {
			$cat_id = $this->create_wc_category( $category );
			if ( false !== $cat_id && isset( $category['childs'] ) && count( $category['childs'] ) > 0 ) {
				foreach ( $category['childs'] as $key => $child_cat ) {
					$this->create_wc_category( $child_cat, $cat_id );
				}
			}
		}

		/**
		 * Create WooCommerce category.
		 *
		 * @param array $category The category data.
		 * @param int   $parent_cat The parent category ID.
		 * @return int|false The created category ID or false on failure.
		 */
		public function create_wc_category( $category, $parent_cat = 0 ) {
			$termid   = false;
			$taxonomy = 'product_cat';
			$slug     = sanitize_title( $category['complete_name'] );
			$term     = get_term_by( 'slug', $slug, $taxonomy );
			$name     = $category['name'] ?? $category['complete_name'] ?? '';
			if ( isset( $term, $term->term_id ) ) {
				$termid = $term->term_id;
			} else {
				$term = wp_insert_term(
					$name,
					$taxonomy,
					array(
						'description' => $category['complete_name'],
						'parent'      => $parent_cat,
						'slug'        => $slug,
					)
				);

				if ( is_wp_error( $term ) ) {
					return false;
				}
				if ( isset( $term['term_id'] ) ) {
					$termid = $term['term_id'];
				}
			}

			return $termid;
		}

		/**
		 * Export categories.
		 */
		public function do_export_categories() {
			$taxonomy     = 'product_cat';
			$orderby      = 'term_id';
			$show_count   = 0;
			$pad_counts   = 0;
			$hierarchical = 1;
			$title        = '';
			$empty        = 0;

			$args = array(
				'taxonomy'     => $taxonomy,
				'orderby'      => $orderby,
				'show_count'   => $show_count,
				'pad_counts'   => $pad_counts,
				'hierarchical' => $hierarchical,
				'title_li'     => $title,
				'hide_empty'   => $empty,
			);

			$all_categories = get_categories( $args );
			$categories     = json_decode( wp_json_encode( $all_categories ), true );

			foreach ( $categories as $key => $cat ) {
				if ( 0 !== $cat['parent'] ) {
					$parent_key        = array_search( $cat['parent'], array_column( $categories, 'term_id' ), true );
					$cat['parent_cat'] = $categories[ $parent_key ];
				}
				$cat_id   = $cat['cat_ID'];
				$response = $this->create_category_to_odoo( $cat );
			}
		}

		/**
		 * Create category to Odoo.
		 *
		 * @param array $category The category data.
		 * @return int|false The created category ID or false on failure.
		 */
		public function create_category_to_odoo( $category ) {
			$odoo_term_id = false;
			$cat_id       = $category['cat_ID'];
			$odoo_term_id = get_term_meta( $cat_id, '_odoo_term_id', true );

			if ( $odoo_term_id ) {
				return $odoo_term_id;
			}
			$odoo_api     = $this->get_odoo_api();
			$conditions   = array( array( 'name', '=', $category['name'] ) );
			$odoo_term_id = $odoo_api->search_record( 'product.category', $conditions );
			if ( ! $odoo_term_id ) {
				$error_msg = 'Error for Search Category => ' . $cat_id;
				$odoo_api->add_log( $error_msg );

				return false;
			}

			if ( $odoo_term_id ) {
				return $odoo_term_id;
			}
			$data = array( 'name' => $category['name'] );
			if ( isset( $category['parent_cat'] ) ) {
				$response = $this->get_parent_category( $category['parent_cat'] );
				if ( $response ) {
					update_term_meta( $category['parent_cat']['cat_ID'], '_odoo_term_id', $response );
					$data['parent_id'] = (int) $response;
				} else {
					$error_msg = 'Error for Creating  Parent category for Id  =>' . $cat_id . 'Msg : ' . print_r( $response, true );
					$odoo_api->add_log( $error_msg );

					return $response;
				}
			}
			$response = $odoo_api->create_record( 'product.category', $data );
			if ( ! isset( $response['faultString'] ) ) {
				update_term_meta( $cat_id, '_odoo_term_id', $response );

				return $response;
			}
			$error_msg = 'Error for Creating category for Id  => ' . $cat_id . ' Msg : ' . print_r( $response['faultString'], true );
			$odoo_api->add_log( $error_msg );
			return false;
		}

		/**
		 * Get the parent category.
		 *
		 * @param array $category The category data.
		 * @return mixed The parent category.
		 */
		public function get_parent_category( $category ) {
			$odoo_api = $this->get_odoo_api();
			$odoo_api->add_log( 'Parent category : ' . print_r( $category, 1 ) );
			$cat_id       = $category['cat_ID'];
			$odoo_term_id = get_term_meta( $cat_id, '_odoo_term_id', true );

			if ( $odoo_term_id ) {
				return $odoo_term_id;
			}
			$conditions   = array( array( 'name', '=', $category['name'] ) );
			$odoo_term_id = $odoo_api->search_record( 'product.category', $conditions );
			if ( ! $odoo_term_id ) {
				$error_msg = 'Error for Search Category =>' . $cat_id;
				$odoo_api->add_log( $error_msg );

				return false;
			}
			if ( is_array( $odoo_term_id ) ) {
				return $odoo_term_id;
			}
			$data = array( 'name' => $category['name'] );
			if ( isset( $category['parent_cat'] ) ) {
				$response = $this->get_parent_category( $category['parent_cat'] );
				if ( $response ) {
					update_term_meta( $cat_id, '_odoo_term_id', $response->odoo_id );
					$data['parent_id'] = $response->odoo_id;

					return $response->odoo_id;
				}
				$error_msg = 'Error for Creating category for Id  =>' . $cat_id . 'Msg : ' . print_r( $response, true );
				$odoo_api->add_log( $error_msg );

				return $response;
			}
			$response = $odoo_api->create_record( 'product.category', $data );
			if ( ! isset( $response['faultString'] ) ) {
				return $response;
			}

			return false;
		}

		/**
		 * Export attributes to Odoo.
		 */
		public function do_export_attributes() {
			$attribute_taxonomies = wc_get_attribute_taxonomies();
			$taxonomy_terms       = array();
			if ( $attribute_taxonomies ) {
				foreach ( $attribute_taxonomies as $tax ) {
					if ( taxonomy_exists( wc_attribute_taxonomy_name( $tax->attribute_name ) ) ) {
						$taxonomy_terms[ $tax->attribute_name ] = get_terms( wc_attribute_taxonomy_name( $tax->attribute_name ) );
					}
					$taxonomy_terms[ $tax->attribute_name ]['attr'] = $tax;
				}
			}

			foreach ( $taxonomy_terms as $key => $taxonomy_term ) {
				$attr_id = $this->create_attributes_to_odoo( $taxonomy_term );
				unset( $taxonomy_term['attr'] );
				if ( false !== $attr_id && $attr_id > 0 ) {
					foreach ( $taxonomy_term as $taxonomy_value ) {
						$attr_value = $this->create_attributes_value_to_odoo( $attr_id, $taxonomy_value );
					}
				}
			}
		}

		/**
		 * Create attributes to Odoo.
		 *
		 * @param mixed $term The term.
		 */
		public function create_attributes_to_odoo( $term ) {
			$odoo_api = $this->get_odoo_api();
			if ( is_string( $term ) ) {
				$attr_name = $term;
				$attr_type = 'select';
				$attr_id   = $term;
			} else {
				$attribute = $term['attr'];
				$attr_name = $attribute->attribute_name;
				$attr_type = $attribute->attribute_type;
				$attr_id   = $attribute->attribute_id;
				unset( $term );
			}
			$conditions   = array( array( 'name', 'IN', array( $attr_name, ucfirst( $attr_name ) ) ) );
			$odoo_attr_id = $odoo_api->search_record( 'product.attribute', $conditions );
			if ( ! $odoo_attr_id ) {
				$error_msg = 'Error for Search attributes =>' . $attr_id;
				$odoo_api->add_log( $error_msg );

				return false;
			}

			if ( $odoo_attr_id ) {
				return $odoo_attr_id;
			}
			$data         = array(
				'name'           => $attr_name,
				'display_type'   => $attr_type,
				'create_variant' => 'always',
			);
			$odoo_attr_id = $odoo_api->create_record( 'product.attribute', $data );
			if ( isset( $odoo_attr_id['faultString'] ) ) {
				$error_msg = 'Error for Search attributes =>' . $attr_id . 'Msg : ' . print_r( $odoo_attr_id, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}

			return $odoo_attr_id;
		}

		/**
		 * Create attributes value to Odoo.
		 *
		 * @param mixed $attr_id The attribute ID.
		 * @param mixed $attr_value The attribute value.
		 * @return mixed The Odoo attribute value ID.
		 */
		public function create_attributes_value_to_odoo( $attr_id, $attr_value ) {
			$odoo_api = $this->get_odoo_api();
			if ( is_string( $attr_value ) ) {
				$value_name = $attr_value;
			} else {
				$value_name = $attr_value->name;
			}
			$conditions         = array( array( 'name', '=', $value_name ), array( 'attribute_id', '=', $attr_id ) );
			$odoo_attr_value_id = $odoo_api->search_record( 'product.attribute.value', $conditions );
			if ( false === $odoo_attr_value_id ) {
				$error_msg = 'Error for Search attributes value =>' . $value_name;
				$odoo_api->add_log( $error_msg );

				return false;
			}

			if ( $odoo_attr_value_id ) {
				return $odoo_attr_value_id;
			}
			$data               = array(
				'name'         => $value_name,
				'attribute_id' => $attr_id,
			);
			$odoo_attr_value_id = $odoo_api->create_record( 'product.attribute.value', $data );
			if ( isset( $odoo_attr_value_id['faultString'] ) ) {
				$error_msg = 'Error for Creating attributes value =>' . $value_name . 'Msg : ' . print_r( $odoo_attr_value_id, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}

			return $odoo_attr_value_id;
		}

		/**
		 * Import attributes.
		 */
		public function do_import_attributes() {
			$odoo_api    = $this->get_odoo_api();
			$attrs       = $odoo_api->read_all( 'product.attribute', array(), array( 'id', 'name', 'value_ids', 'display_type' ) );
			$attr_values = $odoo_api->read_all( 'product.attribute.value', array(), array( 'id', 'name', 'display_type' ) );
			$odoo_api->add_log( 'products atributes : ' . print_r( $attrs, true ) );
			$odoo_api->add_log( 'products atributes Values : ' . print_r( $attr_values, true ) );
			if ( ! isset( $attrs ) ) {
				$attrs = json_decode( wp_json_encode( $attrs ), true );
				if ( count( $attrs ) > 0 && $attr_values ) {
					$attr_values = json_decode( wp_json_encode( $attr_values ), true );
				}
			} else {
				$odoo_api->add_log( ' No Attributes found ' . print_r( $attrs, true ) );

				return false;
			}
			foreach ( $attrs as $attr ) {
				$attr_id = $this->create_attribute_to_wc( $attr );
				update_term_meta( $attr_id, '_odoo_attr_id', $attr['id'] );
				if ( $attr_id ) {
					$attribute = wc_get_attribute( $attr_id );
					foreach ( $attr['value_ids'] as $attr_term ) {
						$term_key = array_search( $attr_term, array_column( $attr_values, 'id' ), true );
						if ( isset( $attr_values[ $term_key ] ) ) {
							$attr_value_id = $this->create_attribute_value_to_wc( $attribute, $attr_values[ $term_key ] );
							if ( false !== $attr_value_id ) {
								update_term_meta( $attr_value_id, '_odoo_attr_id', $attr_term );
							}
						}
					}
				}
			}
		}

		/**
		 * Create attribute to WooCommerce.
		 *
		 * @param mixed $attr The attribute.
		 * @return mixed The attribute ID.
		 */
		public function create_attribute_to_wc( $attr ) {
			global $wc_product_attributes;
			$raw_name = $attr['name'];
			// Make sure caches are clean.
			delete_transient( 'wc_attribute_taxonomies' );
			\WC_Cache_Helper::incr_cache_prefix( 'woocommerce-attributes' );

			// These are exported as labels, so convert the label to a name if possible first.
			$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
			$attribute_name   = array_search( $raw_name, $attribute_labels, true );

			if ( ! $attribute_name ) {
				$attribute_name = wc_sanitize_taxonomy_name( $raw_name );
			}

			$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

			if ( ! $attribute_id ) {
				$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );

				// Degister taxonomy which other tests may have created...
				unregister_taxonomy( $taxonomy_name );

				$attribute_id = wc_create_attribute(
					array(
						'name'         => $raw_name,
						'slug'         => $attribute_name,
						'type'         => $attr['display_type'],
						'order_by'     => 'menu_order',
						'has_archives' => 0,
					)
				);

				// Register as taxonomy.
				register_taxonomy(
					$taxonomy_name,
					/**
					 * Object type with which the taxonomy should be associated.
					 *
					 * @since  1.3.4
					 */
					apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
					/**
					 * Array of arguments for registering taxonomy
					 *
					 * @since  1.3.4
					 */
					apply_filters(
						'woocommerce_taxonomy_args_' . $taxonomy_name,
						array(
							'labels'       => array( 'name' => $raw_name ),
							'hierarchical' => false,
							'show_ui'      => false,
							'query_var'    => true,
							'rewrite'      => false,
						)
					)
				);

				// Set product attributes global.
				$wc_product_attributes = array();

				foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
					$wc_product_attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
				}
			}

			if ( $attribute_id ) {
				return $attribute_id;
			}
		}

		/**
		 * Create attribute value to WooCommerce.
		 *
		 * @param mixed $attribute The attribute.
		 * @param mixed $term The term.
		 * @return mixed The term ID.
		 */
		public function create_attribute_value_to_wc( $attribute, $term ) {
			$result = term_exists( $term['name'], $attribute->slug );
			if ( ! $result ) {
				$result = wp_insert_term( $term['name'], $attribute->slug );
				if ( is_wp_error( $result ) ) {
					return false;
				}
				$term_id = $result['term_id'];
			} else {
				$term_id = $result['term_id'];
			}

			return $term_id;
		}


		/**
		 * Export products to Odoo.
		 */
		public function do_export_product_odoo() {
			if ( $this->export_products->is_process_running() ) {
				echo wp_json_encode(
					array(
						'status'  => 0,
						'message' => __(
							'Product export is already running.',
							'wc2odoo'
						),
					)
				);

				exit;
			}

			global $wpdb;
			$products = array();
			$odoo_api = $this->get_odoo_api();
			$odoo_api->add_log( 'Exclude categories : ' . print_r( $this->odoo_settings['odoo_exclude_product_category'], true ) );
			if ( 'yes' === $this->odoo_settings['odoo_export_create_product'] ) {
				$products = $wpdb->get_results(
					"SELECT {$wpdb->posts}.`ID`,{$wpdb->posts}.`post_type` FROM {$wpdb->posts} RIGHT JOIN  {$wpdb->term_relationships}  as t
						ON ID = t.object_id WHERE (post_type='product') AND post_status='publish' AND post_status='publish'"
				);
			} elseif ( 'no' === $this->odoo_settings['odoo_export_update_stocks'] ) {
				$products = $wpdb->get_results(
					"SELECT {$wpdb->posts}.`ID`,{$wpdb->posts}.`post_type` FROM {$wpdb->posts} RIGHT JOIN  {$wpdb->term_relationships}  as t
						ON ID = t.object_id WHERE (post_type='product') AND post_status='publish' AND NOT EXISTS (
              SELECT {$wpdb->postmeta}.`post_id` FROM {$wpdb->postmeta}
               WHERE {$wpdb->postmeta}.`meta_key` = '_odoo_id'
                AND {$wpdb->postmeta}.`post_id`={$wpdb->posts}.ID
            ) "
				);
			}
			$this->export_products->empty_data();

			// Remove duplicate products ids.
			$products = array_unique( $products, SORT_REGULAR );

			foreach ( $products as $key => $product_obj ) {
				$this->export_products->push_to_queue( $product_obj->ID );
			}
			$total_products_count = count( $products );
			update_option( 'wc2odoo_product_export_count', $total_products_count );
			update_option( 'wc2odoo_product_export_remaining_count', $total_products_count );

			$this->export_products->save()->dispatch();
			echo wp_json_encode(
				array(
					'status'  => 1,
					'message' => __(
						'Export process has started for ',
						'wc2odoo'
					) . $total_products_count . __(
						' products',
						'wc2odoo'
					),
				)
			);
		}


		/**
		 * Syncs the item to Odoo.
		 *
		 * @param mixed $item The item to sync.
		 */
		public function sync_to_odoo( $item ) {
			$product = wc_get_product( $item );
			if ( ! $product ) {
				return false;
			}
			$excluded_item  = false;
			$terms          = get_the_terms( $item, 'product_cat' );
			$odoo_api       = $this->get_odoo_api();
			/*$price_with_tax = $product->get_price();
			$price          = round( $price_with_tax, 2 );
			$product->set_regular_price( $price );
			$product->save();
*/
			$odoo_api->add_log( 'Export product:' . $item );
			if ( '' !== $terms && is_array( $this->odoo_settings['odoo_exclude_product_category'] ) ) {
				foreach ( $terms as $key => $term ) {
					if ( in_array( $term->term_id, $this->odoo_settings['odoo_exclude_product_category'], true ) ) {
						$excluded_item = true;
					}
				}
			}

			if ( $excluded_item ) {
				return false;
			}

			$syncable_product = get_post_meta( $product->get_id(), '_exclude_product_to_sync', true );

			if ( 'yes' === $syncable_product ) {
				return false;
			}

			if ( $product->has_child() ) {
				return false;
			}
			$odoo_product_id = get_post_meta( $product->get_id(), '_odoo_id', true );
			// Search Product on Odoo.
			if ( ! is_numeric( $odoo_product_id ) ) {
				$conditions      = array( array( $this->odoo_sku_mapping, '=', $product->get_sku() ) );
				$odoo_product_id = $this->search_odoo_product( $conditions, $product->get_id() );
				$odoo_api->add_log( 'Search_products : ' . print_r( $odoo_product_id, true ) );
			}

			if ( is_array( $odoo_product_id ) || is_numeric( $odoo_product_id ) ) {
				if ( is_array( $odoo_product_id ) ) {
					$odoo_product_id = $odoo_product_id[0];
				}
				$ret_val = $this->update_odoo_product( $odoo_product_id, $product );
			} else {
				$odoo_product_response = $this->create_product( $product );

				if ( is_numeric( $odoo_product_response ) ) {
					$odoo_product_id = $odoo_product_response;
				} else {
					$odoo_product_id = false;
				}
			}
			$odoo_api->add_log( 'Odoo product Id : ' . print_r( $odoo_product_id, true ) );
			if ( false === $odoo_product_id ) {
				$error_msg = 'Error false for Creating/Updating Product Id  =>' . $product->get_id() . ' Msg : Error';
				$odoo_api->add_log( $error_msg );

				return false;
			}

			// TODO: Estos ret_val sirven para agregar add_log en caso de error.
			$ret_val = update_post_meta( $product->get_id(), '_odoo_id', $odoo_product_id );
			if ( 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
				if ( $product->is_on_sale() ) {
					$odoo_extra_product = get_post_meta( $product->get_id(), '_product_extra_price_id', true );
					if ( $odoo_extra_product ) {
						$this->update_extra_price( $odoo_extra_product, $product );
					} else {
						$this->create_extra_price( $odoo_product_id, $product );
					}
				}
			}
			if ( 'yes' === $this->odoo_settings['odoo_export_update_stocks'] ) {
				if ( $product->get_stock_quantity() > 0 ) {
					$product_qty = number_format( (float) $product->get_stock_quantity(), 2, '.', '' );
					$this->update_product_quantity( $odoo_product_id, $product_qty );
				}
			}
			update_post_meta( $product->get_id(), '_odoo_image_id', $product->get_image_id() );

			return true;
		}

		/**
		 * Summary of do_export_product.
		 */
		public function do_export_product() {
			global $wpdb;
			$products = array();
			$odoo_api = $this->get_odoo_api();

			$odoo_api->add_log( 'Exclude categories : ' . print_r( $this->odoo_settings['odoo_exclude_product_category'], true ) );
			if ( 'yes' === $this->odoo_settings['odoo_export_create_product'] ) {
				$products = $wpdb->get_results(
					"SELECT {$wpdb->posts}.`ID`,{$wpdb->posts}.`post_type` FROM {$wpdb->posts} RIGHT JOIN  {$wpdb->term_relationships}  as t
						ON ID = t.object_id WHERE (post_type='product') AND post_status='publish'"
				);
			} elseif ( 'no' === $this->odoo_settings['odoo_export_update_stocks'] ) {
				$products = $wpdb->get_results(
					"SELECT {$wpdb->posts}.`ID`,{$wpdb->posts}.`post_type` FROM {$wpdb->posts} RIGHT JOIN  {$wpdb->term_relationships}  as t
						ON ID = t.object_id WHERE (post_type='product') AND post_status='publish' AND NOT EXISTS (
              SELECT {$wpdb->postmeta}.`post_id` FROM {$wpdb->postmeta}
               WHERE {$wpdb->postmeta}.`meta_key` = '_odoo_id'
                AND {$wpdb->postmeta}.`post_id`={$wpdb->posts}.ID
            ) "
				);
			}

			// Remove duplicate products ids.
			$products = array_unique( $products, SORT_REGULAR );

			foreach ( $products as $key => $product_obj ) {
				$product       = wc_get_product( $product_obj->ID );
				$excluded_item = false;

				$terms = get_the_terms( $product_obj->ID, 'product_cat' );

				$odoo_api->add_log( print_r( $product_obj->ID, true ) . ' categories : ' . print_r( $terms, true ) );
				if ( '' !== $terms && is_array( $this->odoo_settings['odoo_exclude_product_category'] ) ) {
					foreach ( $terms as $key => $term ) {
						if ( in_array( $term->term_id, $this->odoo_settings['odoo_exclude_product_category'], true ) ) {
							$excluded_item = true;
						}
					}
				}

				if ( $excluded_item ) {
					continue;
				}

				$not_syncable_product = get_post_meta( $product->get_id(), '_exclude_product_to_sync', true );

				if ( 'yes' === $not_syncable_product ) {
					continue;
				}

				if ( $product->has_child() ) {
					$odoo_template_id = get_post_meta( $product->get_id(), '_odoo_id', true );
					if ( $odoo_template_id ) {
						$this->do_export_variable_product_update( (int) $odoo_template_id, $product );
					} else {
						$this->do_export_variable_product( $product );
					}
				} else {
					$odoo_product_id = get_post_meta( $product->get_id(), '_odoo_id', true );

					// Search Product on Odoo.
					if ( ! $odoo_product_id ) {
						$conditions      = array( array( $this->odoo_sku_mapping, '=', $product->get_sku() ) );
						$odoo_product_id = $this->search_odoo_product( $conditions, $product->get_id() );
					}

					if ( $odoo_product_id ) {
						$this->update_odoo_product( (int) $odoo_product_id, $product );
					} else {
						$odoo_product_id = $this->create_product( $product );
					}
					if ( false === $odoo_product_id ) {
						$error_msg = 'Error for Creating/Updating  Product Id  =>' . $product->get_id() . 'Msg : Error';
						$odoo_api->add_log( $error_msg );

						continue;
					}
					update_post_meta( $product->get_id(), '_odoo_id', $odoo_product_id );
					if ( 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
						if ( $product->is_on_sale() ) {
							$odoo_extra_product = get_post_meta( $product->get_id(), '_product_extra_price_id', true );
							if ( $odoo_extra_product ) {
								$this->update_extra_price( $odoo_extra_product, $product );
							} else {
								$this->create_extra_price( $odoo_product_id, $product );
							}
						}
					}
					if ( 'yes' === $this->odoo_settings['odoo_export_update_stocks'] ) {
						if ( $product->get_stock_quantity() > 0 ) {
							$product_qty = number_format( (float) $product->get_stock_quantity(), 2, '.', '' );
							$this->update_product_quantity( $odoo_product_id, $product_qty );
						}
					}
					update_post_meta( $product->get_id(), '_odoo_image_id', $product->get_image_id() );
				}
			}
		}

		/**
		 * Export the updated variable product to Odoo.
		 *
		 * @param int                  $odoo_template_id The Odoo template ID.
		 * @param \WC_Product_Variable $product The variable product object.
		 */
		public function do_export_variable_product_update( $odoo_template_id, $product ) {
			$odoo_api   = $this->get_odoo_api();
			$attrs      = $odoo_api->read_all( 'product.attribute.value', array(), array( 'id', 'name', 'display_type', 'attribute_id', 'pav_attribute_line_ids' ) );
			$odoo_attrs = array();
			if ( ! isset( $attrs['faultString'] ) ) {
				foreach ( $attrs as $akey => $attr ) {
					$odoo_attrs[ strtolower( $attr['attribute_id'][1] ) ][ strtolower( $attr['name'] ) ] = $attr;
				}
			} else {
				$odoo_api->add_log( 'Error for fetching attributes : ' . print_r( $attrs, true ) );
			}

			$attr_values = $odoo_api->read_all( 'product.template.attribute.value', array(), array( 'id', 'name', 'attribute_line_id', 'attribute_id' ) );
			$aaa         = array();
			if ( ! isset( $attrs['faultString'] ) ) {
				foreach ( $attr_values as $avkey => $attr_value ) {
					$aaa[ strtolower( $attr_value['attribute_id'][1] ) ][] = $attr_value;
				}
			} else {
				$odoo_api->add_log( 'Error for fetching attributes : ' . print_r( $attrs, true ) );
			}
			$helper        = WC2ODOO_Helpers::get_helper();
			$template_data = array(
				'name'                  => $product->get_name(),
				'sale_ok'               => true,
				'type'                  => 'product',
				$this->odoo_sku_mapping => $product->get_sku(),
				'description_sale'      => $product->get_description(),
				'attribute_line_ids'    => $this->get_attributes_line_ids( $odoo_attrs, $product->get_attributes() ),
			);
			if ( 'yes' === $this->odoo_settings['odoo_export_create_categories'] ) {
				$template_data['categ_id'] = $this->get_category_id( $product );
			}
			if ( 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
				$price = $product->get_price() ?: 0;
				$template_data['list_price'] = round( $price / 1.19, 0 );
			}
			if ( $helper->can_upload_image( $product ) ) {
				$template_data['image_1920'] = $helper->upload_product_image( $product );
			}

			$template = $odoo_api->update_record( 'product.template', $odoo_template_id, $template_data );

			update_post_meta( $product->get_id(), '_odoo_id', $odoo_template_id );
			$odoo_products = $odoo_api->read_all( 'product.product', array(), array( array( 'product_tmpl_id', '=', $odoo_template_id ) ) );

			$pta_values_id = array_unique( call_user_func_array( 'array_merge', array_column( $odoo_products, 'product_template_attribute_value_ids' ) ) );
			sort( $pta_values_id );
			$pta_values = $odoo_api->fetch_record_by_ids( 'product.template.attribute.value', $pta_values_id, array( 'id', 'name', 'product_attribute_value_id', 'attribute_line_id', 'attribute_id' ) );

			foreach ( $product->get_children() as $key => $child ) {
				$child_product = wc_get_product( $child );
				foreach ( $odoo_products as $opkey => $odoo_product ) {
					foreach ( $odoo_product['product_template_attribute_value_ids'] as $value_id ) {
						$vkey                        = array_search( $value_id, array_column( $pta_values, 'id' ), true );
						$odoo_product['pta_value'][] = strtolower( $pta_values[ $vkey ]['name'] );
					}
					$wcav = $child_product->get_attributes();

					sort( $odoo_product['pta_value'] );
					sort( $wcav );

					if ( $odoo_product['pta_value'] === $wcav ) {
						$child_data = array( $this->odoo_sku_mapping => $child_product->get_sku() );

						if ( 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
							$price = $child_product->get_price() ?: 0;
							//TODO: Change hardcoded tax value, validate if product includes tax.
							$child_data['list_price'] = round( $price / 1.19, 0 );
						}
						if ( $helper->can_upload_image( $child_product ) ) {
							$child_data['image_1920'] = $helper->upload_product_image( $child_product );
						}
						$res = $odoo_api->update_record( 'product.product', $odoo_product['id'], $child_data );

						if ( 'yes' === $this->odoo_settings['odoo_export_update_stocks'] ) {
							if ( $child_product->get_stock_quantity() > 2 ) {
								$product_qty = number_format( (float) $child_product->get_stock_quantity(), 2, '.', '' );
								$this->update_product_quantity( $odoo_product['id'], $product_qty, $odoo_template_id );
							}
						}
						update_post_meta( $child_product->get_id(), '_odoo_id', $odoo_product['id'] );
						update_post_meta( $child_product->get_id(), '_odoo_image_id', $child_product->get_image_id() );
					}
					unset( $odoo_product['pta_value'], $wcav );
				}
			}
		}

		/**
		 * Update the Odoo product.
		 *
		 * @param int         $odoo_product_id The Odoo product ID.
		 * @param \WC_Product $product The WooCommerce product.
		 */
		public function update_odoo_product( $odoo_product_id, $product ) {
			$odoo_api = $this->get_odoo_api();
			if ( '' === $product->get_sku() ) {
				$error_msg = 'Error for Search product =>' . $product->get_id() . ' Msg : Invalid SKU';
				$odoo_api->add_log( $error_msg );

				return false;
			}
			$helper = WC2ODOO_Helpers::get_helper();
			$data   = array(
				'name'                  => $product->get_name(),
				'sale_ok'               => true,
				'type'                  => 'product',
				$this->odoo_sku_mapping => $product->get_sku(),
				'description_sale'      => '',
				'weight'                => $product->get_weight(),
				'volume'                => (int) ( (int) $product->get_height() * (int) $product->get_length() * (int) $product->get_width() ),
			);
			if ( 'yes' === $this->odoo_settings['odoo_export_create_categories'] ) {
				$data['categ_id'] = $this->get_category_id( $product );
			}
			// xdebug_break();
			
			if ( 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
				$price = $product->get_sale_price() ?: $product->get_regular_price() ?: 0;
				$data['list_price'] = round( ( $price ) / 1.19, 0 );

				if ( $product->is_on_sale() ) {
					$odoo_extra_product = get_post_meta( $product->get_id(), '_product_extra_price_id', true );
					if ( $odoo_extra_product ) {
						if ( ! $this->update_extra_price( $odoo_extra_product, $product ) ) {
							$this->create_extra_price( $odoo_product_id, $product );
						}
					} else {
						$this->create_extra_price( $odoo_product_id, $product );
					}
				}
			}
			if ( $helper->can_upload_image( $product ) ) {
				$data['image_1920'] = $helper->upload_product_image( $product );
			}
			return $odoo_api->update_record( 'product.product', $odoo_product_id , $data );
		}

		/**
		 * Export the customer data.
		 */
		public function do_export_customer() {
			$args          = array(
				'role'    => 'customer',
				'order'   => 'ASC',
				'orderby' => 'ID',
				'number'  => -1,
			);
			$wp_user_query = new \WP_User_Query( $args );
			$customers     = $wp_user_query->get_results();

			foreach ( $customers as $key => $customer ) {
				$customer_id = get_user_meta( $customer->ID, '_odoo_id', true );
				if ( ! $customer_id ) {
					$conditions  = array( array( 'type', '=', 'contact' ), array( 'email', '=', $customer->user_email ) );
					$customer_id = $this->search_odoo_customer( $conditions );
				}

				$customer_id = $this->create_or_update_customer( $customer, $customer_id );
				if ( false === $customer_id ) {
					continue;
				}
				update_user_meta( $customer->ID, '_odoo_id', $customer_id );
				$this->action_woocommerce_customer_save_address( $customer->ID, 'shipping' );
				$this->action_woocommerce_customer_save_address( $customer->ID, 'billing' );
			}
		}

		/**
		 * Export the order data.
		 */
		public function do_export_order() {
			global $wpdb;

			$from_date       = '';
			$to_date         = '';
			$date_conditions = '';

			if ( isset( $this->odoo_settings['odoo_export_order_from_date'] ) && ! empty( $this->odoo_settings['odoo_export_order_from_date'] ) ) {
				$from_date = $wpdb->prepare( ' AND p.post_date >= %s ', $this->odoo_settings['odoo_export_order_from_date'] );
			}
			if ( isset( $this->odoo_settings['odoo_export_order_to_date'] ) && ! empty( $this->odoo_settings['odoo_export_order_to_date'] ) ) {
				$to_date = $wpdb->prepare( ' AND p.post_date <= %s ', $this->odoo_settings['odoo_export_order_to_date'] );
			}

			$date_conditions = $from_date . $to_date;

			$orders = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT pm.post_id AS order_id
					FROM {$wpdb->prefix}postmeta AS pm
					LEFT JOIN {$wpdb->prefix}posts AS p
					ON pm.post_id = p.ID
					WHERE p.post_type = 'shop_order'
					%s
					AND pm.meta_key = '_customer_user'
					ORDER BY pm.meta_value ASC, pm.post_id DESC
					",
					$date_conditions
				)
			);

			foreach ( $orders as $key => $order ) {
				$order_id = get_post_meta( $order->order_id, '_odoo_order_id', true );
				if ( ! $order_id ) {
					$this->order_create( $order->order_id );
				}
			}
		}

		/**
		 * Export the refund order data.
		 */
		public function do_export_refund_order() {
			global $wpdb;

			$from_date = '';
			$to_date   = '';

			if ( isset( $this->odoo_settings['odoo_export_order_from_date'] ) && ! empty( $this->odoo_settings['odoo_export_order_from_date'] ) ) {
				$from_date = $wpdb->prepare( ' AND  p.post_date >= %s ', $this->odoo_settings['odoo_export_order_from_date'] );
			}
			if ( isset( $this->odoo_settings['odoo_export_order_to_date'] ) && ! empty( $this->odoo_settings['odoo_export_order_to_date'] ) ) {
				$to_date = $wpdb->prepare( ' AND  p.post_date <= %s ', $this->odoo_settings['odoo_export_order_to_date'] );
			}

			$orders = $wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT pm.post_id AS order_id
				FROM {$wpdb->prefix}postmeta AS pm
				LEFT JOIN {$wpdb->prefix}posts AS p
				ON pm.post_id = p.ID
				WHERE p.post_type = 'shop_order'
				%s %s
				AND pm.meta_key = '_customer_user'
				ORDER BY pm.meta_value ASC, pm.post_id DESC
				",
					$from_date,
					$to_date
				)
			);
			$orders = array_unique( $orders, SORT_REGULAR );
			foreach ( $orders as $key => $order ) {
				$order_id = get_post_meta( $order->order_id, '_odoo_order_id', true );
				if ( $order_id ) {
					$woo_order         = new \WC_Order( $order->order_id );
					$woo_order_refunds = $woo_order->get_refunds();

					foreach ( $woo_order_refunds as $woo_order_refund ) {
						$odoo_api = $this->get_odoo_api();

						$odoo_api->add_log( print_r( $order->order_id, 1 ) . ' order refund order : ' . print_r( $woo_order_refund->get_id(), 1 ) );
						$refund_id = $woo_order_refund->get_id();
						$this->create_odoo_refund( $order->order_id, $refund_id );
					}
				}
			}
		}

		/**
		 * Export the variable product data.
		 *
		 * @param mixed $product The product to export.
		 */
		public function do_export_variable_product( $product ) {
			$odoo_api   = $this->get_odoo_api();
			$attrs      = $odoo_api->read_all( 'product.attribute.value', array(), array( 'id', 'name', 'display_type', 'attribute_id', 'pav_attribute_line_ids' ) );
			$odoo_attrs = array();
			foreach ( $attrs as $akey => $attr ) {
				$odoo_attrs[ strtolower( $attr['attribute_id'][1] ) ][ strtolower( $attr['name'] ) ] = $attr;
			}

			$helper        = WC2ODOO_Helpers::get_helper();
			$template_data = array(
				'name'                  => $product->get_name(),
				'sale_ok'               => true,
				'type'                  => 'product',
				$this->odoo_sku_mapping => $product->get_sku(),
				'description_sale'      => $product->get_description(),
				'attribute_line_ids'    => $this->get_attributes_line_ids( $odoo_attrs, $product->get_attributes() ),
			);
			if ( 'yes' === $this->odoo_settings['odoo_export_create_categories'] ) {
				$template_data['categ_id'] = $this->get_category_id( $product );
			}
			if ( 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
				$price                       = $product->get_price() ?: 0;
				$template_data['list_price'] = round( $price / 1.19, 0);
			}
			if ( $helper->can_upload_image( $product ) ) {
				$template_data['image_1920'] = $helper->upload_product_image( $product );
			}

			$template = $odoo_api->create_record( 'product.template', $template_data );
			update_post_meta( $product->get_id(), '_odoo_id', $template );
			$odoo_products = $odoo_api->read_all( 'product.product', array(), array( array( 'product_tmpl_id', '=', $template ) ) );

			$pta_values_id = array_unique( call_user_func_array( 'array_merge', array_column( $odoo_products, 'product_template_attribute_value_ids' ) ) );
			sort( $pta_values_id );

			$pta_values = $odoo_api->fetch_record_by_ids( 'product.template.attribute.value', $pta_values_id, array( 'id', 'name', 'product_attribute_value_id', 'attribute_line_id', 'attribute_id' ) );

			foreach ( $product->get_children() as $key => $child ) {
				$child_product = wc_get_product( $child );

				foreach ( $odoo_products as $opkey => $odoo_product ) {
					foreach ( $odoo_product['product_template_attribute_value_ids'] as $value_id ) {
						$vkey                        = array_search( $value_id, array_column( $pta_values, 'id' ), true );
						$odoo_product['pta_value'][] = strtolower( $pta_values[ $vkey ]['name'] );
					}
					$wcav = $child_product->get_attributes();

					sort( $odoo_product['pta_value'] );
					sort( $wcav );
					$child_data = array();
					if ( $odoo_product['pta_value'] === $wcav ) {
						$child_data = array( $this->odoo_sku_mapping => $child_product->get_sku() );

						if ( $helper->can_upload_image( $child_product ) ) {
							$child_data['image_1920'] = $helper->upload_product_image( $child_product );
						}
						$res = $odoo_api->update_record( 'product.product', (int) $odoo_product['id'], $child_data );

						if ( 'yes' === $this->odoo_settings['odoo_export_update_stocks'] ) {
							$product_qty = number_format( (float) $child_product->get_stock_quantity(), 2, '.', '' );
							$this->update_product_quantity( $odoo_product['id'], $product_qty );
						}
						update_post_meta( $child_product->get_id(), '_odoo_id', $odoo_product['id'] );
						update_post_meta( $child_product->get_id(), '_odoo_varitaion_id', $odoo_product['id'] );
						update_post_meta( $child_product->get_id(), '_odoo_image_id', $child_product->get_image_id() );
					}
					unset( $odoo_product['pta_value'], $wcav );
				}
			}
		}

		/**
		 * Returns an array of attribute line IDs for a given product's attributes and attribute values.
		 *
		 * @param array $attr_values        an array of attribute values.
		 * @param array $product_attributes an array of product attributes.
		 *
		 * @return array an array of attribute line IDs.
		 */
		public function get_attributes_line_ids( $attr_values, $product_attributes ) {
			$odoo_attr_line = array();

			foreach ( $product_attributes as $key => $product_attribute ) {
				if ( is_object( $product_attribute ) ) {
					if ( $product_attribute->get_id() > 0 ) {
						$attr_name = strtolower( wc_get_attribute( $product_attribute->get_id() )->name );
					} else {
						$attr_name = $product_attribute->get_name();
					}

					$attr_val_ids = array();
					if ( isset( $attr_values[ $attr_name ] ) ) {
						$attr_id = reset( $attr_values[ $attr_name ] )['attribute_id'][0];
						foreach ( $product_attribute->get_options() as $okey => $option_id ) {
							$term = get_term( $option_id );
							if ( isset( $attr_values[ $attr_name ][ $term->name ] ) ) {
								$attr_val_ids[] = $attr_values[ $attr_name ][ $term->name ]['id'];
							} else {
								$attr_val_ids[] = $this->create_attributes_value_to_odoo( $attr_id, $term );
							}
						}
					} else {
						if ( null === wc_get_attribute( $product_attribute->get_id() ) ) {
							$attr_id = $this->create_attributes_to_odoo( $attr_name );
						} else {
							$attr_id = $this->create_attributes_to_odoo( wc_get_attribute( $product_attribute->get_id() ) );
						}

						foreach ( $product_attribute->get_options() as $okey => $option_id ) {
							if ( null === wc_get_attribute( $product_attribute->get_id() ) ) {
								$term = $option_id;
							} else {
								$term = get_term( $option_id );
							}
							$attr_val_ids[] = $this->create_attributes_value_to_odoo( $attr_id, $term );
						}
					}

					$odoo_attr_line[] = array(
						0,
						'virtual_' . implode( '', $attr_val_ids ),
						array(
							'attribute_id' => $attr_id,
							'value_ids'    => array( array( 6, false, $attr_val_ids ) ),
						),
					);
				}
			}

			return $odoo_attr_line;
		}

		/**
		 * Performs the import of coupons.
		 */
		public function do_import_coupon() {
			$odoo_api = $this->get_odoo_api();
			if ( $this->odoo_settings['odooVersion'] < 16 ) {
				$conditions = array( array( 'program_type', '=', 'coupon_program' ) );
				$coupons    = $odoo_api->search_records( 'loyalty.program', $conditions );
				$odoo_api->add_log( 'coupon for new version : ' . print_r( $coupons, true ) );
			} else {
				$conditions = array( array( 'program_type', '=', 'coupons' ) );
				$coupons    = $odoo_api->search_records( 'loyalty.program', $conditions );
				$odoo_api->add_log( 'coupon for old version : ' . print_r( $coupons, true ) );
			}

			if ( $coupons ) {
				$coupons = json_decode( wp_json_encode( $coupons ), true );
				if ( is_array( $coupons ) && count( $coupons ) > 0 ) {
					foreach ( $coupons as $key => $coupon ) {
						if ( $coupon['coupon_count'] > 0 ) {
							$this->create_coupon_to_wc( $coupon );
						}
					}
				}
			}
		}

		/**
		 * Creates a coupon in WooCommerce based on the provided Odoo coupon data.
		 *
		 * @param array $odoo_coupon The Odoo coupon data.
		 */
		public function create_coupon_to_wc( $odoo_coupon ) {
			$odoo_api = $this->get_odoo_api();
			if ( $this->odoo_settings['odooVersion'] < 16 ) {
				$coupons = $odoo_api->fetch_record_by_ids( 'coupon.coupon', $odoo_coupon['coupon_ids'] );
				$odoo_api->add_log( 'coupon coupon by id : ' . print_r( $coupons, 1 ) );
				if ( ! isset( $coupons['faultCode'] ) && is_array( $coupons ) && count( $coupons ) ) {
					foreach ( $coupons as $key => $coupon ) {
						$coupon_code = $coupon['code'];
						$amount      = $odoo_coupon['discount_percentage'];
						if ( 'percentage' === $odoo_coupon['discount_type'] ) {
							if ( 'on_order' === $odoo_coupon['discount_apply_on'] ) {
								$discount_type = 'percent';
							} elseif ( 'specific_products' === $odoo_coupon['discount_apply_on'] ) {
								$discount_type = 'percent_product';
							}
							$amount = $odoo_coupon['discount_percentage'];
						} elseif ( 'fixed_amount' === $odoo_coupon['discount_type'] ) {
							$discount_type = 'fixed_cart';
							$amount        = $odoo_coupon['discount_fixed_amount'];
						}

						// Type: fixed_cart, percent, fixed_product, percent_product.

						$coupon_data = array(
							'post_title'   => $coupon_code,
							'post_content' => '',
							'post_status'  => 'publish',
							'post_author'  => 1,
							'post_type'    => 'shop_coupon',
						);
						$coupon_id   = $this->get_post_id_by_meta_key_and_value( '_odoo_coupon_code_id', $coupon['id'] );

						if ( $coupon_id ) {
							if ( 'no' === $this->odoo_settings['odoo_import_coupon_update'] ) {
								continue;
							}
							$coupon_data['ID'] = $coupon_id;
							$new_coupon_id     = wp_update_post( $coupon_data );
						} else {
							$new_coupon_id = wp_insert_post( $coupon_data );
						}
						update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
						update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
						update_post_meta( $new_coupon_id, '_odoo_coupon_code_id', $coupon['id'] );
						update_post_meta( $new_coupon_id, '_odoo_coupon_id', $odoo_coupon['id'] );
						update_post_meta( $new_coupon_id, '_odoo_coupon_name', $odoo_coupon['name'] );
						if ( 'specific_products' === $odoo_coupon['discount_apply_on'] ) {
							update_post_meta( $new_coupon_id, 'product_ids', $odoo_coupon['discount_specific_product_ids'] );
						}
						update_post_meta( $new_coupon_id, 'usage_limit', 1 );
						update_post_meta( $new_coupon_id, 'free_shipping', 'no' );
					}
				}
			} else {
				$coupons = $odoo_api->fetch_record_by_ids( 'loyalty.card', $odoo_coupon['coupon_ids'] );
				$rewards = $odoo_api->fetch_record_by_ids( 'loyalty.reward', $odoo_coupon['reward_ids'] );
				if ( $coupons ) {
					$coupons = json_decode( wp_json_encode( $coupons ), true );
					if ( $rewards ) {
						$rewards = json_decode( wp_json_encode( $rewards ), true );
						$rewards = $rewards[0];
					}
					if ( is_array( $coupons ) && count( $coupons ) ) {
						foreach ( $coupons as $key => $coupon ) {
							$coupon_code = $coupon['code'];
							$amount      = $rewards['discount'];
							if ( 'percent' === $rewards['discount_mode'] ) {
								if ( 'order' === $rewards['discount_applicability'] ) {
									$discount_type = 'percent';
								} elseif ( 'specific' === $rewards['discount_applicability'] ) {
									$discount_type = 'percent_product';
								}
								$amount = $rewards['discount'];
							} elseif ( 'per_order' === $rewards['discount_mode'] ) {
								$discount_type = 'fixed_cart';
								$amount        = $rewards['discount_fixed_amount'];
							}

							// Type: fixed_cart, percent, fixed_product, percent_product.

							$coupon_data = array(
								'post_title'   => $coupon_code,
								'post_content' => '',
								'post_status'  => 'publish',
								'post_author'  => 1,
								'post_type'    => 'shop_coupon',
							);
							$odoo_api->add_log( 'coupons data : ' . print_r( $coupon_data, 1 ) );
							$coupon_id = $this->get_post_id_by_meta_key_and_value( '_odoo_coupon_code_id', $coupon['id'] );

							if ( $coupon_id ) {
								if ( 'no' === $this->odoo_settings['odoo_import_coupon_update'] ) {
									continue;
								}
								$coupon_data['ID'] = $coupon_id;
								$new_coupon_id     = wp_update_post( $coupon_data );
							} else {
								$new_coupon_id = wp_insert_post( $coupon_data );
							}
							update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
							update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
							update_post_meta( $new_coupon_id, '_odoo_coupon_code_id', $coupon['id'] );
							update_post_meta( $new_coupon_id, '_odoo_coupon_reward_id', $rewards['id'] );
							update_post_meta( $new_coupon_id, '_odoo_coupon_id', $odoo_coupon['id'] );
							update_post_meta( $new_coupon_id, '_odoo_coupon_name', $odoo_coupon['name'] );
							if ( 'specific' === $rewards['discount_applicability'] ) {
								update_post_meta( $new_coupon_id, 'product_ids', $rewards['all_discount_product_ids'] );
							}
							update_post_meta( $new_coupon_id, 'usage_limit', 1 );
							update_post_meta( $new_coupon_id, 'free_shipping', 'no' );
						}
					}
				}
			}
		}

		/**
		 * Export the coupon.
		 */
		public function do_export_coupon() {
			$odoo_api = $this->get_odoo_api();
			$common   = new WC2ODOO_Common_Functions();

			if ( ! $common->is_authenticate() ) {
				return;
			}

			$args = array(
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'asc',
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
			);

			$coupons = get_posts( $args );
			if ( $this->odoo_settings['odooVersion'] < 16 ) {
				foreach ( $coupons as $key => $coupon ) {
					$coupon_id   = get_post_meta( $coupon->ID, '_odoo_coupon_id', true );
					$coupon_data = $this->create_coupon_data( $coupon );
					if ( $coupon_id ) {
						if ( 'no' === $this->odoo_settings['odoo_export_coupon_update'] ) {
							continue;
						}
						$res = $odoo_api->update_record( 'coupon.program', (int) $coupon_id, $coupon_data );
					} else {
						$coupon_id = $odoo_api->create_record( 'coupon.program', $coupon_data );
						$odoo_api->add_log( 'coupan create result : ' . print_r( $coupon_id, 1 ) );
					}
					if ( ! isset( $coupon_id['faultString'] ) ) {
						update_post_meta( $coupon->ID, '_odoo_coupon_id', $coupon_id );
						$coupon_code_id = get_post_meta( $coupon->ID, '_odoo_coupon_code_id', true );

						$code_data = $this->create_coupon_code_data( $coupon, $coupon_id );
						if ( $coupon_code_id ) {
							$res_code = $odoo_api->update_record( 'coupon.coupon', (int) $coupon_code_id, $code_data );
						} else {
							$coupon_code_id = $odoo_api->create_record( 'coupon.coupon', $code_data );
						}
						if ( ! isset( $coupon_code_id['faultString'] ) ) {
							update_post_meta( $coupon->ID, '_odoo_coupon_code_id', $coupon_code_id );
						} else {
							$error_msg = 'Error for Creating/Updating Coupon Code Id  => ' . $coupon->ID . ' Msg : ' . print_r( $coupon_code_id, true );
							$odoo_api->add_log( $error_msg );

							continue;
						}
					} else {
						$error_msg = 'Error for Creating/Updating Coupon Id  => ' . $coupon->ID . ' Msg : ' . print_r( $coupon_id, true );
						$odoo_api->add_log( $error_msg );

						continue;
					}
				}
			} else {
				foreach ( $coupons as $key => $coupon ) {
					$coupon_id = get_post_meta( $coupon->ID, '_odoo_coupon_id', true );
					if ( $coupon_id ) {
						if ( 'no' === $this->odoo_settings['odoo_export_coupon_update'] ) {
							continue;
						}
					} else {
						$this->create_loyalty_program( $coupon );
					}
				}
			}
		}

		/**
		 * Create a loyalty program for the coupon.
		 *
		 * @param object $coupon The coupon object.
		 */
		public function create_loyalty_program( $coupon ) {
			$odoo_api    = $this->get_odoo_api();
			$coupon_name = ( '' !== $coupon->post_excerpt ) ? $coupon->post_excerpt : $coupon->name;
			$meta_data   = get_post_meta( $coupon->ID );
			if ( isset( $meta_data['discount_type'][0] ) ) {
				$expires = abs( time() - $meta_data['date_expires'][0] ) / 60 / 60 / 24;
			}
			$data = array(
				array(
					'name'         => $coupon_name,
					'active'       => 1,
					'program_type' => 'coupons',
					'display_name' => $coupon_name,
				),
			);

			$odoo_api->add_log( 'Coupon Data : ' . print_r( $data, 1 ) );
			$loyalty_response = $odoo_api->create_record( 'loyalty.program', $data );
			$odoo_api->add_log( 'coupon program : ' . print_r( $loyalty_response, 1 ) );
			if ( ! isset( $loyalty_response['faultString'] ) ) {
				$reward_data[]   = $this->create_reward_data( $coupon, $loyalty_response );
				$reward_response = $odoo_api->create_record( 'loyalty.reward', $reward_data );
				$odoo_api->add_log( 'coupon reward : ' . print_r( $reward_response, 1 ) );
				if ( ! isset( $reward_response['faultString'] ) ) {
					$coupon_data          = array(
						array(
							'code'   => $coupon->name,
							'points' => 1,
						),
					);
					$reward_card_response = $odoo_api->create_record( 'loyalty.card', $coupon_data );
					$odoo_api->add_log( 'coupon program : ' . print_r( $reward_card_response, 1 ) );
					if ( ! isset( $reward_card_response['faultString'] ) ) {
						update_post_meta( $coupon->ID, '_odoo_coupon_code_id', $reward_card_response );
						update_post_meta( $coupon->ID, '_odoo_coupon_reward_id', $reward_response );
						update_post_meta( $coupon->ID, '_odoo_coupon_id', $loyalty_response );
						update_post_meta( $coupon->ID, '_odoo_coupon_name', $coupon_name );
					}
				}
			}
		}

		/**
		 * Create reward data for the coupon.
		 *
		 * @param object $coupon The coupon object.
		 * @param int    $program_id The program ID.
		 * @return array The reward data.
		 */
		public function create_reward_data( $coupon, $program_id ) {
			$meta_data = get_post_meta( $coupon->ID );

			if ( isset( $meta_data['discount_type'][0] ) ) {
				$discount_type        = $meta_data['discount_type'][0];
				$data['program_id']   = $program_id;
				$data['program_type'] = 'coupons';
				$data['reward_type']  = 'discount';
				if ( 'fixed_cart' === $discount_type ) {
					$data['discount_mode']          = 'per_order';
					$data['discount_applicability'] = 'order';
					$data['discount']               = $meta_data['coupon_amount'][0];
				} elseif ( 'percent' === $discount_type ) {
					$data['discount_mode']          = 'percent';
					$data['discount']               = $meta_data['coupon_amount'][0];
					$data['discount_applicability'] = 'order';
				} else {
					$data['discount_mode']          = 'percent';
					$data['discount']               = $meta_data['coupon_amount'][0];
					$data['discount_applicability'] = 'order';
				}
			}

			if ( isset( $meta_data['minimum_amount'][0] ) ) {
				$data['discount_max_amount'] = $meta_data['minimum_amount'][0];
			}

			return $data;
		}

		/**
		 * Update the loyalty program for a coupon.
		 *
		 * @param object $coupon The coupon object.
		 * @param int    $coupon_id The coupon ID.
		 */
		public function update_loyalty_program( $coupon, $coupon_id ) {
			$data             = array(
				array(
					'name'         => $coupon->post_excerpt,
					'active'       => 1,
					'program_type' => 'coupons',
				),
			);
			$odoo_api         = $this->get_odoo_api();
			$loyalty_response = $odoo_api->update_record( 'loyalty.program', $coupon_id, $data );
			$odoo_api->add_log( 'update response : ' . print_r( $loyalty_response, 1 ) );
		}

		/**
		 * Create coupon data.
		 *
		 * @param object $coupon The coupon object.
		 * @return array The coupon data.
		 */
		public function create_coupon_data( $coupon ) {
			$odoo_api  = $this->get_odoo_api();
			$data      = array(
				'name'              => $coupon->post_name,
				'active'            => 1,
				'program_type'      => 'coupon_program',
				'rule_min_quantity' => 1,
			);
			$meta_data = get_post_meta( $coupon->ID );

			if ( isset( $meta_data['discount_type'][0] ) ) {
				$discount_type = $meta_data['discount_type'][0];
				if ( 'fixed_cart' === $discount_type ) {
					$data['discount_type']         = 'fixed_amount';
					$data['discount_apply_on']     = 'on_order';
					$data['discount_fixed_amount'] = $meta_data['coupon_amount'][0];
				} elseif ( 'percent' === $discount_type ) {
					$data['discount_type']       = 'percentage';
					$data['discount_percentage'] = $meta_data['coupon_amount'][0];
					$data['discount_apply_on']   = 'on_order';
				} else {
					$data['discount_type']       = 'percentage';
					$data['discount_percentage'] = $meta_data['coupon_amount'][0];
					$data['discount_apply_on']   = 'on_order';
				}
			}
			if ( isset( $meta_data['date_expires'][0] ) ) {
				$data['validity_duration'] = abs( time() - $meta_data['date_expires'][0] ) / 60 / 60 / 24;
			}
			if ( isset( $meta_data['minimum_amount'][0] ) ) {
				$data['rule_minimum_amount'] = $meta_data['minimum_amount'][0];
			}
			if ( isset( $meta_data['_odoo_coupon_name'][0] ) ) {
				$data['name'] = $meta_data['_odoo_coupon_name'][0];
			}

			return $data;
		}

		/**
		 * Create coupon code data.
		 *
		 * @param object $coupon The coupon object.
		 * @param int    $odoo_coupon_id The Odoo coupon ID.
		 */
		public function create_coupon_code_data( $coupon, $odoo_coupon_id ) {
			return array(
				'code'       => $coupon->post_name,
				'program_id' => $odoo_coupon_id,
			);
		}

		/**
		 * Import the customer data.
		 */
		public function do_import_customer() {
			$odoo_api   = $this->get_odoo_api();
			$conditions = array( array( 'type', '=', 'contact' ) );
			$customers  = $odoo_api->read_all( 'res.partner', $conditions, array( 'id', 'name', 'display_name', 'website', 'mobile', 'email', 'is_company', 'phone', 'image_medium', 'street', 'street2', 'zip', 'city', 'state_id', 'country_id', 'child_ids', 'type' ) );
			$customers  = json_decode( wp_json_encode( $customers ), true );

			if ( is_array( $customers ) && count( $customers ) ) {
				foreach ( $customers as $key => $customer ) {
					if ( isset( $customer['email'] ) && ! empty( $customer['email'] ) ) {
						$address_lists = array();
						if ( count( $customer['child_ids'] ) > 0 ) {
							foreach ( $customer['child_ids'] as $key => $child_ids ) {
								$address_res = $odoo_api->fetch_record_by_ids( 'res.partner', $child_ids, array( 'id', 'name', 'display_name', 'website', 'mobile', 'email', 'is_company', 'phone', 'image_medium', 'street', 'street2', 'zip', 'city', 'state_id', 'country_id', 'child_ids', 'type' ) );

								$address_res     = json_decode( wp_json_encode( $address_res ), true );
								$address_lists[] = $address_res[0];
							}
							if ( 0 > count( $address_lists ) ) {
								continue;
							}
						}
						$this->sync_customer_to_wc( $customer, $address_lists );
					}
				}
			}
		}

		/**
		 * Sync customer data to WooCommerce.
		 *
		 * @param array $customer The customer data.
		 * @param array $address_lists The list of addresses associated with the customer.
		 */
		public function sync_customer_to_wc( $customer, $address_lists ) {
			$user = get_user_by( 'email', $customer['email'] );

			if ( ! $user ) {
				$user_by_id = get_users(
					array(
						'meta_key'   => '_odoo_id', // phpcs:ignore
						'meta_value' => $customer['id'], // phpcs:ignore
					)
				);
				if ( 0 !== count( $user_by_id ) ) {
					$user = $user_by_id[0];
				}
			}

			if ( null !== $user && is_array( $user->roles ) && in_array( 'customer', $user->roles, true ) ) {
				$user_id = $user->ID;
			}
			$customer_name = $this->split_name( $customer['name'] );
			$userdata      = array(
				'user_login'    => $customer['email'],
				'user_nicename' => $customer_name['first_name'],
				'user_email'    => $customer['email'],
				'display_name'  => $customer['display_name'],
				'nickname'      => $customer_name['first_name'],
				'first_name'    => $customer_name['first_name'],
				'last_name'     => $customer_name['last_name'],
				'role'          => 'customer',
				'locale'        => '',
				'website'       => $customer['website'],
			);

			if ( isset( $user_id ) ) {
				$userdata['ID'] = $user_id;
				wp_update_user( $userdata );
			} else {
				$userdata['user_pass'] = 'gsf3213#$rtyu';
				$user_id               = wp_insert_user( $userdata );
			}

			update_user_meta( $user_id, '_odoo_id', $customer['id'] );

			$is_billing_updated = false;
			foreach ( $address_lists as $key => $address ) {
				if ( in_array( $address['type'], array( 'delivery', 'invoice' ), true ) ) {
					if ( 'invoice' === $address['type'] ) {
						$is_billing_updated = true;
					}
					$this->create_user_addres_to_wc( $user_id, $address, $address['type'] );
				}
			}
			if ( ! $is_billing_updated ) {
				$this->create_user_addres_to_wc( $user_id, $customer, 'invioce' );
			}

			return $user_id;
		}

		/**
		 * Create user address in WooCommerce.
		 *
		 * @param int    $user_id       The user ID.
		 * @param array  $address       The address data.
		 * @param string $address_type  The address type.
		 */
		public function create_user_addres_to_wc( $user_id, $address, $address_type = 'invioce' ) {
			$type          = ( 'delivery' === $address_type ) ? 'shipping' : 'billing';
			$customer_name = $this->split_name( $address['name'] );

			update_user_meta( $user_id, $type . '_first_name', $customer_name['first_name'] );
			update_user_meta( $user_id, $type . '_last_name', $customer_name['last_name'] );
			update_user_meta( $user_id, $type . '_address_1', $address['street'] );
			update_user_meta( $user_id, $type . '_address_2', $address['street2'] );
			update_user_meta( $user_id, $type . '_city', $address['city'] );
			if ( isset( $address['state_id'][1] ) ) {
				preg_match( '#\((.*?)\)#', $address['state_id'][1], $country );
				update_user_meta( $user_id, $type . '_country', $country[1] );
				$state = explode( ' (', $address['state_id'][1] );
				if ( '' !== $country[1] && null !== $country[1] && ! empty( $country[1] ) ) {
					$states_array = array_flip( WC()->countries->get_states( $country[1] ) );
					$state_name   = $states_array[ $state[0] ] ?? '';
				}
				update_user_meta( $user_id, $type . '_state', $state_name );
			}

			update_user_meta( $user_id, $type . '_postcode', $address['zip'] );
			update_user_meta( $user_id, $type . '_email', $address['email'] );
			update_user_meta( $user_id, $type . '_phone', $address['phone'] );
			update_user_meta( $user_id, '_odoo_' . $type . '_id', $address['id'] );
		}

		/**
		 * Splits a name into first name and last name.
		 *
		 * @param string $name The name to split.
		 * @return array An array containing the first name and last name.
		 */
		public function split_name( $name ) {
			$name       = trim( $name );
			$last_name  = ( false === strpos( $name, ' ' ) ) ? '' : preg_replace( '#.*\s([\w-]*)$#', '$1', $name );
			$first_name = trim( preg_replace( '#' . preg_quote( $last_name, '#' ) . '#', '', $name ) );

			return array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
			);
		}

		/**
		 * Import orders from Odoo.
		 */
		public function do_import_order() {
			$odoo_api   = $this->get_odoo_api();
			$conditions = array();

			if ( isset( $this->odoo_settings['odoo_import_order_from_date'] ) && ! empty( $this->odoo_settings['odoo_import_order_from_date'] ) ) {
				$conditions[] = array( 'date_order', '>=', $this->odoo_settings['odoo_import_order_from_date'] );
			}
			if ( isset( $this->odoo_settings['odoo_import_order_to_date'] ) && ! empty( $this->odoo_settings['odoo_import_order_to_date'] ) ) {
				$conditions[] = array( 'date_order', '<=', $this->odoo_settings['odoo_import_order_to_date'] );
			}

			$orders      = $odoo_api->read_all( 'sale.order', $conditions, array( 'id', 'name', 'origin', 'state', 'date_order', 'partner_id', 'partner_invoice_id', 'partner_shipping_id', 'order_line', 'invoice_ids', 'amount_total', 'amount_tax', 'type_name', 'display_name' ) );
			$odoo_orders = $odoo_api->read_fields( 'sale.order' );
			$orders      = json_decode( wp_json_encode( $orders ), true );

			if ( is_array( $orders ) && count( $orders ) > 0 ) {
				foreach ( $orders as $key => $order ) {
					$order_id = $this->get_post_id_by_meta_key_and_value( '_odoo_order_id', $order['id'] );
					if ( $order_id ) {
						$odoo_api->add_log( 'Order already Synced for Odoo Order Id : ' . $order['id'] );

						continue;
					}

					$user_id     = $this->get_user_id_by_meta_key_and_value( '_odoo_id', $order['partner_id'][0] );
					$partner_ids = array( $order['partner_invoice_id'][0], $order['partner_shipping_id'][0] );
					if ( ! $user_id ) {
						$partner_ids[] = $order['partner_id'][0];
					}
					$partners = $odoo_api->fetch_record_by_ids( 'res.partner', $partner_ids, array( 'id', 'name', 'display_name', 'website', 'mobile', 'email', 'is_company', 'phone', 'image_medium', 'street', 'street2', 'zip', 'city', 'state_id', 'country_id', 'type' ) );

					if ( $partners ) {
						$partners = json_decode( wp_json_encode( $partners ), true );
					} else {
						$odoo_api->add_log( 'User not found for Order Id : ' . $order['id'] );

						continue;
					}
					$users = array();

					foreach ( $partners as $key => $partner ) {
						$billing  = $this->create_customer_address_data( $partner );
						$shipping = $this->create_customer_address_data( $partner );
						if ( 'invoice' === $partner['type'] ) {
							$users['billing'] = $this->create_customer_address_data( $partner );
						} elseif ( 'delivery' === $partner['type'] ) {
							$users['shipping'] = $this->create_customer_address_data( $partner );
						} else {
							$users['user_id'] = ( false !== $user_id ) ? $user_id : $this->create_wc_customer( $partner );
						}
					}
					extract( $users ); //phpcs:ignore
					$order_lines = $odoo_api->fetch_record_by_ids( 'sale.order.line', $order['order_line'], array( 'id', 'name', 'invoice_status', 'price_subtotal', 'price_tax', 'price_total', 'product_id', 'product_uom_qty', 'price_unit' ) );

					if ( $order_lines ) {
						$order_lines = json_decode( wp_json_encode( $order_lines ), true );
					} else {
						$error_msg = 'Order Line found for Order Id  =>' . $order['id'] . 'Msg : ' . print_r( $order_lines, true );
						$odoo_api->add_log( $error_msg );

						continue;
					}
					$wc_order = wc_create_order( array( 'customer_id' => $user_id ) );
					$wc_order->update_meta_data( '_new_order_email_sent', 'true' );
					$wc_order->update_meta_data( '_customer_user', $user_id );
					$wc_order->update_meta_data( '_odoo_order_id', $order['id'] );
					$wc_order->update_meta_data( '_odoo_invoice_id', end( $order['invoice_ids'] ) );
					$wc_order->update_meta_data( '_odoo_order_origin', $order['name'] );
					foreach ( $order_lines as $key => $order_line ) {
						if ( isset( $order_line['product_id'][0] ) ) {
							$product_id = $this->get_post_id_by_meta_key_and_value( '_odoo_id', $order_line['product_id'][0] );
							if ( ! $product_id ) {
								$odoo_product = $odoo_api->fetch_record_by_id( 'product.product', array( $order_line['product_id'][0] ) );

								$product_id = $this->sync_product_from_odoo( $odoo_product[0], true );
							}
							$product = wc_get_product( $product_id );

							$product->set_price( $order_line['price_unit'] );
							$item_id = $wc_order->add_product( $product, $order_line['product_uom_qty'] );
							wc_update_order_item_meta( $item_id, '_order_line_id', $order_line['id'] );
						}
					}
					if ( isset( $billing ) ) {
						$wc_order->set_address( $billing, 'billing' );
					}
					if ( isset( $shipping ) ) {
						$wc_order->set_address( $shipping, 'shipping' );
					}
					$wc_order->calculate_totals();
					$wc_order->set_date_completed( $order['date_order'] );
					$wc_order->set_status( 'completed', __( 'Order Imported From Odoo', 'wc2odoo' ) );
					$wc_order->save();
				}
			}
		}

		/**
		 * Create customer address data.
		 *
		 * @param array $partner The partner data.
		 * @return array The customer address data.
		 */
		public function create_customer_address_data( $partner ) {
			$data = array(
				'first_name' => $this->split_name( $partner['name'] )['first_name'],
				'last_name'  => $this->split_name( $partner['name'] )['last_name'],
				'email'      => $partner['email'],
				'phone'      => $partner['phone'],
				'address_1'  => $partner['street'],
				'address_2'  => $partner['street2'],
				'city'       => $partner['city'],
				'state'      => $partner['state_id'][1] ?? '',
				'postcode'   => $partner['zip'],
				'country'    => $partner['country_id'][1] ?? '',
			);

			if ( 1 === $partner['is_company'] ) {
				$data['company'] = $partner['display_name'];
			}

			return $data;
		}

		/**
		 * Create a WooCommerce customer.
		 *
		 * @param array $customer The customer data.
		 */
		public function create_wc_customer( $customer ) {
			$user = get_user_by( 'email', $customer['email'] );

			if ( ! $user ) {
				$user_by_id = get_users(
					array(
						'meta_key'   => '_odoo_id', // phpcs:ignore
						'meta_value' => $customer['id'], // phpcs:ignore
					)
				);
				if ( 0 !== count( $user_by_id ) ) {
					$user = $user_by_id[0];
				}
			}

			$odoo_api = $this->get_odoo_api();
			$odoo_api->add_log( 'user : ' . print_r( $user->email, true ) );

			if ( null !== $user && is_array( $user->roles ) && in_array( 'customer', $user->roles, true ) ) {
				$user_id = $user->ID;
			}
			$customer_name = $this->split_name( $customer['name'] );
			$userdata      = array(
				'user_nicename' => $customer_name['first_name'],
				'user_email'    => $customer['email'],
				'display_name'  => $customer['display_name'],
				'nickname'      => $customer_name['first_name'],
				'first_name'    => $customer_name['first_name'],
				'last_name'     => $customer_name['last_name'],
				'role'          => 'customer',
				'locale'        => '',
				'website'       => $customer['website'],
			);

			if ( isset( $user_id ) ) {
				$userdata['ID'] = $user_id;
				wp_update_user( $userdata );
			} else {
				$userdata['user_pass']  = 'gsf3213#$rtyu';
				$userdata['user_login'] = $customer['email'];
				$user_id                = wp_insert_user( $userdata );
			}

			update_user_meta( $user_id, '_odoo_id', $customer['id'] );
			if ( ! is_wp_error( $user_id ) ) {
				return $user_id;
			}

			return false;
		}

		/**
		 * Create an order to Odoo
		 *
		 * @param int $order_id The order ID.
		 */
		public function order_create( $order_id ) {
			$odoo_settings = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );
			$odoo_api      = $this->get_odoo_api();

			// First validate that the order is not createad already in Odoo.
			$is_order_synced = get_post_meta( $order_id, '_odoo_order_id', true );
			$odoo_api->add_log( 'Working with order: ' . print_r( $order_id, true ) );
			if ( $is_order_synced ) {
				$error_msg = 'Warning: Order Already Synced For Id ' . $order_id . ' With Odoo Sale Order Id => ' . $is_order_synced;
				$odoo_api->add_log( $error_msg );
			}
			$order  = new \WC_Order( $order_id );
			// First validate that no record matches the order data in Odoo, by searching it with the order id.
			$order_odoo_id = $odoo_api->search_record( 'sale.order', array( array( 'origin', '=', $order_id ) ) );
			if ( $order_odoo_id ) {
				update_post_meta( $order_id, '_odoo_order_id', $order_odoo_id );
				$error_msg = 'Order Already Synced For Id ' . $order_id . ' With Odoo Sale Order Id => ' . $order_odoo_id;
				$odoo_api->add_log( $error_msg );
				// Let's confirm if the status is cancelled, if so, we need to cancel the order in Odoo.
				if ( 'cancelled' === $order->get_status() ) {
					$order_state = $odoo_api->fetch_record_by_id( 'sale.order', array( $order_odoo_id ), array( 'state' ) );
					if ( 'cancel' !== $order_state ) {
						$odoo_api->add_log( 'Order status in Odoo:' . print_r( $order_state, true )  );
						$this->cancel_order_odoo( $order_odoo_id );
					}
				}
				return false;
			}

			$common = new WC2ODOO_Common_Functions();
			$helper = WC2ODOO_Helpers::get_helper();

			if ( ! $common->is_authenticate() ) {
				return false;
			}
			$verify_order_items = $helper->verify_order_items( $order_id );
			if ( ! $verify_order_items ) {
				$odoo_api->add_log( 'Order Export aborted due to invalid/incomplete product. Please review the order product details.' );
				return false;
			}

			$woo_state = $helper->get_state( $order->get_status() );
			$statuses  = $helper->odoo_states( $woo_state );

			$odoo_api->add_log( print_r( $order_id, true ) . ' create order : ' . print_r( $statuses, true ) );

			if ( 'shop_order' !== $order->get_type() ) {
				return false;
			}
			// get user id associated with order.
			$user          = $order->get_user();
			$customer_data = $this->get_customer_data( $user, $order );

			if ( ! isset( $odoo_settings['odooTax'] ) ) {
				$error_msg = 'Invalid Tax Setting For Order Id ' . $order_id;
				$odoo_api->add_log( $error_msg );

				return false;
			}
			// get tax id from the admin setting.
			$tax_id   = (int) $odoo_settings['odooTax'];
			$tax_data = $odoo_api->fetch_record_by_id( 'account.tax', array( $tax_id ) );

			if ( isset( $tax_data['faultCode'] ) ) {
				$error_msg = 'Error For Fetching Tax data Msg : ' . print_r( $tax_data['msg'], true );
				$odoo_api->add_log( $error_msg );

				return false;
			}
			if ( empty( $customer_data['invoice_id'] ) ) {
				$customer_data['invoice_id'] = $customer_data['id'];
			}

			$order_data = array(
				'partner_id'         => (int) $customer_data['id'],
				'partner_invoice_id' => (int) $customer_data['invoice_id'],
				'state'              => $statuses['order_state'],
				'invoice_status'	 => $statuses['invoice_status'],
				'note'               => __( 'Woo Order Id : ', 'wc2odoo' ) . $order_id,
				'payment_term_id'    => 1,
				'origin'             => $order_id,
				'date_order'         => date_format( $order->get_date_created(), 'Y-m-d H:i:s' ),
			);

			if ( 'yes' === $odoo_settings['odoo_fiscal_position'] && ! empty( $odoo_settings['odoo_fiscal_position_selected'] ) ) {
				$order_data['fiscal_position_id'] = $odoo_settings['odoo_fiscal_position_selected'];
			}

			// Create Sale Order in the Odoo.
			$odoo_api->add_log( 'Order data: ' . print_r( $order_data, 1 ) );

			$order_odoo_id = $odoo_api->create_record( 'sale.order', $order_data );
			if ( isset( $order_odoo_id['faultString'] ) ) {
				$error_msg = 'Error for Creating  Order Id  =>' . $order_id . 'Msg : ' . print_r( $order_odoo_id, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}

			update_post_meta( $order_id, '_odoo_order_id', $order_odoo_id );

			$order_total = $order->get_total();
			$odoo_api->add_log( 'Create Order Total: ' . print_r( $order_total, true ) );

			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();

				$conditions = array( array( $this->odoo_sku_mapping, '=', $product->get_sku() ) );

				$product_id = $odoo_api->search_record( 'product.product', $conditions );

				if ( ! $product_id ) {
					$error_msg = 'Error for Search product =>' . $product->get_id() . ' Conditions:' . print_r( $conditions, true );
					$odoo_api->add_log( $error_msg );

					return false;
				}

				// Ver si es posible evitar esto.
				update_post_meta( $product->get_id(), '_odoo_id', $product_id );
				// If the order is 0 we set all products to 0.
				if ( 0 < $order_total ) {
					if ( 1 === $tax_data['price_include'] ) {
						$total_price = $item->get_total() + $item->get_total_tax();
					} else {
						$total_price = $item->get_total();
					}
				}
				else {
					$total_price = 0;
				}
				$unit_price = number_format( (float) ( $total_price / $item->get_quantity() ), 2, '.', '' );

				$order_line = array(
					'order_partner_id' => (int) $customer_data['id'],
					'order_id'         => $order_odoo_id,
					'product_uom_qty'  => $item->get_quantity(),
					'product_id'       => $product_id,
					'price_unit'       => $unit_price,
				);

				if ( 'no' === $this->odoo_settings['odoo_fiscal_position'] ) {
					if ( $item->get_total_tax() > 0 ) {
						$order_line['tax_id'] = array( array( 6, 0, array( (int) $tax_id ) ) );
					} else {
						$order_line['tax_id'] = array( array( 6, 0, array() ) );
					}
				}

				$order_line_id = $odoo_api->create_record( 'sale.order.line', $order_line );

				if ( isset( $order_line_id['faultString'] ) ) {
					$error_msg = 'Error for Creating  Order line for Product Id  =>' . $product->get_id() . 'Msg : ' . print_r( $order_line_id, true );
					$odoo_api->add_log( $error_msg );

					return false;
				}

				wc_update_order_item_meta( $item_id, '_order_line_id', $order_line_id );
			}
			$shipping_price = floatval( $order->get_shipping_total() ) ?: 0;
			if ( $shipping_price > 0 ) {
				$shipping_tax_id = (int) $odoo_settings['shippingOdooTax'];
				
				$order_line = array(
					'order_partner_id' => (int) $customer_data['id'],
					'order_id'         => $order_odoo_id,
					'product_uom_qty'  => 1,
					'product_id'       => (int) $this->get_delivery_product_id(),
					'tax_id'           => array( array( 6, 0, array( $shipping_tax_id ) ) ),
					'price_unit'       => round( $shipping_price, 0 ),
				);

				$order_line_id = $odoo_api->create_record( 'sale.order.line', $order_line );

				if ( isset( $order_line_id['faultString'] ) ) {
					$error_msg = 'Error for Creating  Order line for Product Id  =>' . $product->get_id() . 'Msg : ' . print_r( $order_line_id, true );
					$odoo_api->add_log( $error_msg );

					return false;
				}

				update_post_meta( $order_id, '_order_line_id', $order_line_id );
			}
			// calculate taxes if fiscal positions are enabled.
			if ( 'yes' === $odoo_settings['odoo_fiscal_position'] ) {
				$order_tax_calculations = $odoo_api->custom_api_call( 'sale.order', 'validate_taxes_on_sales_order', array( (int) $order_odoo_id ) );
			}

			if ( ! empty( $order->get_customer_note() ) ) {
				$order_line    = array(
					'order_partner_id' => (int) $customer_data['id'],
					'order_id'         => $order_odoo_id,
					'product_uom_qty'  => false,
					'product_id'       => false,
					'display_type'     => 'line_note',
					'name'             => $order->get_customer_note(),
				);
				$order_line_id = $odoo_api->create_record( 'sale.order.line', $order_line );

				if ( ! isset( $order_line_id['faultString'] ) ) {
					update_post_meta( $item_id, '_order_note_id', $order_line_id );
				} else {
					$error_msg = 'Error for Creating  Order Note For Woo Order  =>' . $order_id . 'Msg : ' . print_r( $order_line_id, true );
					$odoo_api->add_log( $error_msg );

					return false;
				}
			}
			if ( '' !== $statuses['invoice_state'] ) {
				if ( 'yes' === $this->odoo_settings['odoo_export_invoice'] ) {
					$odoo_api->add_log( 'Invoice state : ' . print_r( $statuses['invoice_state'], true ) );
					$invoice_id = $this->create_invoice( $order_id );
				}
				if ( '' === $invoice_id ) {
					$error_msg = 'Error for Creating Order Invoice For Woo Order  =>' . $order_id . 'Msg : ' . print_r( $invoice_id['msg'], true );
					$odoo_api->add_log( $error_msg );

					return false;
				}
			}
			$odoo_api->add_log( 'Finished working with order: ' . print_r( $order_id, true ) );

			return true;
		}

		/**
		 * [create_odoo_invoice].
		 *
		 * @param int $order_id refunded order id.
		 */
		public function create_invoice( $order_id ) {
			$odoo_api = $this->get_odoo_api();
			$order    = new \WC_Order( $order_id );
			//$odoo_api->add_log( 'Working with order: ' . print_r( $order, true ) );
			$common   = new WC2ODOO_Common_Functions();
			$helper   = WC2ODOO_Helpers::get_helper();

			if ( ! $common->is_authenticate() ) {
				return;
			}

			$order_odoo_id = get_post_meta( $order_id, '_odoo_order_id', true );

			$odoo_api->add_log( 'order_odoo_id : ' . print_r( $order_odoo_id, true ) );

			$woo_state = $helper->get_state( $order->get_status() );
			$statuses  = $helper->odoo_states( $woo_state );
			$odoo_ver  = $helper->odoo_version();

			// get user id assocaited with order.
			$user                 = $order->get_user();
			$customer_data        = $this->get_customer_data( $user, $order );
			$order_total          = $order->get_total();
			$odoo_api->add_log( 'Invoice order_total : ' . print_r( $order_total, true ) );

			$billing_type         = get_post_meta( $order_id, '_billing_invoice_type', true );
			$invoice_data         = $this->create_invoice_data( $customer_data, (int) $order_odoo_id, $order_total );
			if ( 0 < $order_total ) {
				if ( '' === $billing_type ) {
					$invoice_data['l10n_latam_document_type_id'] = 5;
				} else {
					$invoice_data['l10n_latam_document_type_id'] = 1;
				}
				$invoice_data['l10n_latam_document_number'] = $this->get_last_l10n_latam_document_number($invoice_data['l10n_latam_document_type_id']);
			}
			else {
				$invoice_data['name'] = 'WEB/' . $order_id;
			}
			// Create the record in the journal
			$invoice_id = $odoo_api->create_record( 'account.move', $invoice_data );

			if ( isset( $invoice_id['faultString'] ) ) {
				$error_msg = 'Error for Creating  Invoice Id  =>' . $order_id . 'Msg : ' . print_r( $invoice_id, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}

			if ( ! isset( $this->odoo_settings['odooTax'] ) ) {
				$error_msg = 'Invalid Tax Setting For Order Id ' . $order_id;
				$odoo_api->add_log( $error_msg );

				return false;
			}
			// get tax id from the admin setting.
			$tax_id = (int) $this->odoo_settings['odooTax'];

			$tax_data = $odoo_api->fetch_record_by_id( 'account.tax', array( $tax_id ) );

			if ( isset( $tax_data['faultCode'] ) ) {
				$error_msg = 'Error For Fetching Tax data Msg : ' . print_r( $tax_data['msg'], true );
				$odoo_api->add_log( $error_msg );

				return false;
			}
			$invoice_lines = array();

			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();

				$order_line_id = wc_get_order_item_meta( $item_id, '_order_line_id' );
				$odoo_api->add_log( 'order_line_id : ' . print_r( $order_line_id, true ) );
				// nico.
				$conditions = array( array( $this->odoo_sku_mapping, '=', $product->get_sku() ) );

				$product_id = $odoo_api->search_record( 'product.product', $conditions );
				if ( ! $product_id ) {
					$odoo_api->add_log( 'Product not found for Invoice!!' );

					return false;
				}
				if ( 0 < $order_total ) {
					if ( 1 === $tax_data['price_include'] ) {
						$total_price = $item->get_total() + $item->get_total_tax();
					} else {
						$total_price = $item->get_total();
					}
				}
				else {
					$total_price = 0;
				}
				$unit_price = round( number_format( (float) ( $total_price / $item->get_quantity() ), 0, '.', '' ) );

				if ( 'yes' === $this->odoo_settings['odoo_export_invoice'] ) {
					$invoice_line_data = array(
						'partner_id'    => (int) $customer_data['id'],
						'move_id'       => $invoice_id,
						'price_unit'    => $unit_price,
						'quantity'      => $item->get_quantity(),
						'product_id'    => $product_id,
						'sale_line_ids' => array( array( 6, 0, array( (int) $order_line_id ) ) ),
					);
					if ( 'no' === $this->odoo_settings['odoo_fiscal_position'] ) {
						$invoice_line_data['tax_ids'] = array( array( 6, 0, array( (int) $tax_id ) ) );

						if ( $item->get_total_tax() > 0 ) {
							$invoice_line_data['tax_ids'] = array( array( 6, 0, array( (int) $tax_id ) ) );
						} else {
							$invoice_line_data['tax_ids'] = array( array( 6, 0, array() ) );
						}
					}
					$invoice_lines[] = $odoo_api->create_record( 'account.move.line', $invoice_line_data );
				}
			}

			if ( $order->get_shipping_total() > 0 ) {
				$shipping_tax_id = (int) $this->odoo_settings['shippingOdooTax'];

				$order_line_id = get_post_meta( $order_id, '_order_line_id', true );

				$odoo_api->add_log( 'Shipping line : ' . print_r( $order_line_id, true ) );

				if ( 'yes' === $this->odoo_settings['odoo_export_invoice'] ) {
					$price             = $order->get_shipping_total() ?: 0;
					$invoice_line_data = array(
						'partner_id'    => (int) $customer_data['id'],
						'move_id'       => $invoice_id,
						'price_unit'    => round( $price, 0 ),
						'quantity'      => 1,
						'product_id'    => (int) $this->get_delivery_product_id(),
						'tax_ids'       => array( array( 6, 0, array( (int) $shipping_tax_id ) ) ),
						'sale_line_ids' => array( array( 6, 0, array( (int) $order_line_id ) ) ),
					);
					$invoice_lines[]   = $odoo_api->create_record( 'account.move.line', $invoice_line_data );
				}
			}

			if ( ! empty( $order->get_customer_note() ) ) {
				if ( 'yes' === $this->odoo_settings['odoo_export_invoice'] ) {
					$order_line_id = get_post_meta( $order_id, '_order_note_id', true );
					$odoo_api->add_log( 'order note line : ' . print_r( $order_line_id, true ) );
					$invoice_line_data = array(
						'partner_id'    => (int) $customer_data['id'],
						'move_id'       => $invoice_id,
						'price_unit'    => false,
						'quantity'      => false,
						'product_id'    => false,
						'sale_line_ids' => array( array( 6, 0, array( (int) $order_line_id ) ) ),
						'display_type'  => 'line_note',
						'name'          => $order->get_customer_note(),
					);
					$invoice_lines[]   = $odoo_api->create_record( 'account.move.line', $invoice_line_data );
				}
			}

			if ( count( $invoice_lines ) > 0 && ( 'yes' === $this->odoo_settings['odoo_export_invoice'] ) ) {
				$odoo_order = $odoo_api->update_record( 'sale.order', (int) $order_odoo_id, array( 'state' => $statuses['order_state'] ) );
				$odoo_api->add_log( 'Order update: ' . print_r( $odoo_order, true ) . ' - To stat: ' . print_r( $statuses['order_state'], true ) );

				if ( $helper->is_inv_mark_paid() ) {
					$invoice = $odoo_api->update_record( 'account.move', (int) $invoice_id, array( 'state' => $statuses['invoice_state'] ) );
					if ( 13 === $odoo_ver ) {
						$invoice = $odoo_api->update_record( 'account.move', (int) $invoice_id, array( 'invoice_payment_state' => $statuses['payment_state'] ) );
					} else {
						$invoice = $odoo_api->update_record( 'account.move', (int) $invoice_id, array( 'payment_state' => $statuses['payment_state'] ) );
					}
				} else {
					$invoice = $odoo_api->update_record( 'account.move', (int) $invoice_id, array( 'state' => 'draft' ) );
					if ( 13 === $odoo_ver ) {
						$invoice = $odoo_api->update_record( 'account.move', (int) $invoice_id, array( 'invoice_payment_state' => 'not_paid' ) );
					} else {
						$invoice = $odoo_api->update_record( 'account.move', (int) $invoice_id, array( 'payment_state' => 'not_paid' ) );
					}
				}

				if ( ! $invoice ) {
					$error_msg = 'Error for Creating  Invoice  for Order Id  =>' . $order_id . 'Msg : ' . print_r( $invoice, true );
					$odoo_api->add_log( $error_msg );

					return false;
				}

				$invoice_url = $this->create_pdf_download_link( $invoice_id );
				if ( isset( $invoice_data['invoice_origin'] ) && ! empty( $invoice_data['invoice_origin'] ) ) {
					$order_origin = $invoice_data['invoice_origin'];
					update_post_meta( $order_id, '_odoo_order_origin', $order_origin );
				}
				update_post_meta( $order_id, '_odoo_invoice_id', $invoice_id );
				update_post_meta( $order_id, '_odoo_invoice_url', $invoice_url );

				return $invoice_id;
			}
		}

		/**
		 * [create_odoo_refund description].
		 *
		 * @param int $order_id  refunded order id.
		 * @param int $refund_id refund id.
		 */
		public function create_odoo_refund( $order_id, $refund_id ) {
			$odoo_api = $this->get_odoo_api();

			$refund          = new \WC_Order_Refund( $refund_id );
			$odoo_invoice_id = get_post_meta( $refund->get_parent_id(), '_odoo_invoice_id', true );
			$odoo_order_id   = get_post_meta( $refund->get_parent_id(), '_odoo_order_id', true );

			if ( ! $odoo_order_id || ! $odoo_invoice_id ) {
				$odoo_api->add_log( 'Order found on Odoo!' );

				return;
			}

			$odoo_return_inv_id   = get_post_meta( $refund_id, '_odoo_return_invoice_id', true );
			$odoo_return_inv_url  = get_post_meta( $refund_id, '_odoo_return_invoice_url', true );
			$odoo_return_order_id = get_post_meta( $refund_id, '_odoo_return_order_id', true );

			if ( $odoo_return_inv_id ) {
				$odoo_api->add_log( 'Refund already exported' );

				return;
			}
			$odoo_return_inv_id   = get_post_meta( $order_id, '_odoo_return_invoice_id', true );
			$odoo_return_inv_url  = get_post_meta( $order_id, '_odoo_return_invoice_url', true );
			$odoo_return_order_id = get_post_meta( $order_id, '_odoo_return_order_id', true );
			if ( $odoo_return_inv_id ) {
				update_post_meta( $refund_id, '_odoo_return_invoice_id', $odoo_return_inv_id );
				update_post_meta( $refund_id, '_odoo_return_invoice_url', $odoo_return_inv_url );
				update_post_meta( $refund_id, '_odoo_return_order_id', $odoo_return_order_id );
				delete_post_meta( $order_id, '_odoo_return_invoice_id' );
				delete_post_meta( $order_id, '_odoo_return_invoice_url' );
				delete_post_meta( $order_id, '_odoo_return_order_id' );

				return;
			}

			$odoo_refund_invoice_data = $this->create_refund_invoice_data( $odoo_invoice_id );
			$odoo_api->add_log( 'refund invoice data : ' . print_r( $odoo_refund_invoice_data, 1 ) );

			$refund_order  = new \WC_Order( $refund->get_parent_id() );
			$user          = $refund_order->get_user();
			$customer_data = $this->get_customer_data( $user, $refund_order );

			$refund_item_id = true;
			if ( ! $refund->get_items() ) {
				$refund         = $refund_order;
				$refund_item_id = false;
			}

			$odoo_refund_invoice_id = $odoo_api->create_record( 'account.move', $odoo_refund_invoice_data );
			$odoo_api->add_log( 'refund invoice id : ' . print_r( $odoo_refund_invoice_id, 1 ) );
			if ( isset( $odoo_refund_invoice_id['faultString'] ) ) {
				$error_msg = 'Error for Creating refund Invoice Id  => ' . $order_id . ' Msg : ' . print_r( $odoo_refund_invoice_id, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}

			$wc_setting = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );

			$tax_id   = (int) $wc_setting['odooTax'];
			$tax_data = $odoo_api->fetch_record_by_id( 'account.tax', array( $tax_id ) );

			$refund_invoice_lines = array();
			foreach ( $refund->get_items() as $item_id => $item ) {
				if ( 0 === abs( $item->get_quantity() ) ) {
					$odoo_api->add_log( 'Refunded order Export canceled because the refund quantity is equal to 0. ' );

					continue;
				}

				$refunded_quantity      = $item->get_quantity();
				$refunded_line_subtotal = abs( $item->get_subtotal() );
				$refunded_item_id       = ( $refund_item_id ) ? $item->get_meta( '_refunded_item_id' ) : $item_id;
				$order_line_id          = wc_get_order_item_meta( $refunded_item_id, '_order_line_id', true );
				$odd_order_line_id      = wc_get_order_item_meta( $refunded_item_id, '_invoice_line_id', true );
				$odoo_product_id        = get_post_meta( $item->get_product_id(), '_odoo_id', true );

				if ( 1 === $tax_data['price_include'] ) {
					$total_price = abs( $item->get_total() ) + abs( $item->get_total_tax() );
				} else {
					$total_price = abs( $item->get_total() );
				}
				$unit_price = round( number_format( (float) ( $total_price / abs( $item->get_quantity() ) ), 2, '.', '' ) );

				$refund_invoice_line_data = array(
					'partner_id'    => $odoo_refund_invoice_data['partner_id'],
					'move_id'       => $odoo_refund_invoice_id,
					'price_unit'    => $unit_price,
					'quantity'      => absint( $item->get_quantity() ),
					'product_id'    => (int) $odoo_product_id,
					'sale_line_ids' => array( array( 6, 0, array( (int) $order_line_id ) ) ),
				);

				if ( 'no' === $this->odoo_settings['odoo_fiscal_position'] ) {
					if ( abs( $item->get_total_tax() ) > 0 ) {
						$refund_invoice_line_data['tax_ids'] = array( array( 6, 0, array( (int) $tax_id ) ) );
					} else {
						$refund_invoice_line_data['tax_ids'] = array( array( 6, 0, array() ) );
					}
				}
				$refund_invoice_line_id = $odoo_api->create_record( 'account.move.line', $refund_invoice_line_data );
				if ( ! isset( $refund_invoice_line_id['faultString'] ) ) {
					$refund_invoice_lines[] = $refund_invoice_line_id;
					wc_update_order_item_meta( $item_id, '_return_order_line_id', $refund_invoice_line_id );
				}
			}

			if ( isset( $this->odoo_settings['odoo_mark_invoice_paid'] ) && 'yes' === $this->odoo_settings['odoo_mark_invoice_paid'] ) {
				$odoo_refund_invoice = $odoo_api->update_record( 'account.move', $odoo_refund_invoice_id, array( 'state' => 'posted' ) );
			} else {
				$odoo_refund_invoice = $odoo_api->update_record( 'account.move', $odoo_refund_invoice_id, array( 'state' => 'draft' ) );
			}
			if ( isset( $odoo_refund_invoice['faultString'] ) ) {
				$error_msg = 'Error Update Refund Invoice For Invoice Id  => ' . $order_id . ' Msg : ' . print_r( $odoo_refund_invoice, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}

			if ( isset( $this->odoo_settings['odoo_mark_invoice_paid'] ) && 'yes' === $this->odoo_settings['odoo_mark_invoice_paid'] ) {
				if ( isset( $this->odoo_settings['odooVersion'] ) && 13 === $this->odoo_settings['odooVersion'] ) {
					$odoo_refund_invoice = $odoo_api->update_record( 'account.move', $odoo_refund_invoice_id, array( 'invoice_payment_state' => 'paid' ) );
				} else {
					$odoo_refund_invoice = $odoo_api->update_record( 'account.move', $odoo_refund_invoice_id, array( 'payment_state' => 'in_payment' ) );
				}
			} elseif ( isset( $this->odoo_settings['odooVersion'] ) && 13 === $this->odoo_settings['odooVersion'] ) {
					$odoo_refund_invoice = $odoo_api->update_record( 'account.move', $odoo_refund_invoice_id, array( 'invoice_payment_state' => 'not_paid' ) );
			} else {
				$odoo_refund_invoice = $odoo_api->update_record( 'account.move', $odoo_refund_invoice_id, array( 'payment_state' => 'not_paid' ) );
			}

			if ( ! $odoo_refund_invoice ) {
				$error_msg = 'Error for Creating  Invoice  for Order Id  => ' . $order_id . ' Msg : ' . print_r( $odoo_refund_invoice, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}
			$invoice_url = $this->create_pdf_download_link( $odoo_refund_invoice_id );
			update_post_meta( $refund_id, '_odoo_return_invoice_id', $odoo_refund_invoice_id );
			update_post_meta( $refund_id, '_odoo_return_invoice_url', $invoice_url );
			update_post_meta( $refund_id, '_odoo_return_order_id', $odoo_order_id );
		}

		/**
		 * Create an extra price for a product.
		 *
		 * @param int    $odoo_product_id The Odoo product ID.
		 * @param object $product The WooCommerce product object.
		 * @return bool Returns true on success, false on failure.
		 */
		public function create_extra_price( $odoo_product_id, $product ) {
			$price          = $product->get_sale_price() ?: 0;
			$data           = array(
				'fixed_price'     => round( $price / 1.19, 0 ),
				'pricelist_id'    => 1,
				'product_tmpl_id' => $odoo_product_id,
				'product_id'      => $odoo_product_id,
				'applied_on'      => '1_product',
			);
			$odoo_api       = $this->get_odoo_api();
			$extra_price_id = $odoo_api->create_record( 'product.pricelist.item', $data );
			if ( ! isset( $extra_price_id['faultString'] ) ) {
				update_post_meta( $product->get_id(), 'wc2odoo_product_extra_price_id', $extra_price_id );
			} else {
				$error_msg = 'Error for Creating  Extra Price For Product Id  => ' . $product->get_id() . 'Msg :  ' . print_r( $extra_price_id, true );
				$odoo_api->add_log( $error_msg );

				return false;
			}
			return true;
		}

		/**
		 * Update the extra price for a product.
		 *
		 * @param int    $extra_price_id The ID of the extra price.
		 * @param object $product        The WooCommerce product object.
		 * @return bool Returns true on success, false on failure.
		 */
		public function update_extra_price( $extra_price_id, $product ) {
			$price              = $product->get_sale_price() ?: 0;
			$data               = array( 'fixed_price' => round( $price, 2 ) );
			$odoo_api           = $this->get_odoo_api();
			$extra_price_update = $odoo_api->update_record( 'product.pricelist.item', (int) $extra_price_id, $data );
			if ( isset( $extra_price_update['fail'] ) ) {
				$error_msg = 'Error for Creating  Extra Price For Product Id  => ' . $product->get_id() . ' Msg :  ' . print_r( $extra_price_update, true );
				$odoo_api->add_log( $error_msg );
				update_post_meta( $product->get_id(), 'wc2odoo_product_extra_price_id', '' );
				return false;
			}

			return true;
		}

		/**
		 * Get and set the sale price for a product.
		 *
		 * @param int   $post_id      The post ID of the product.
		 * @param array $odoo_product The Odoo product data.
		 * @return bool Returns true on success, false on failure.
		 */
		public function get_and_set_sale_price( $post_id, $odoo_product ) {
			$odoo_api    = $this->get_odoo_api();
			$price_lists = $odoo_api->read_all( 'product.pricelist.item', array(), array( array( 'product_tmpl_id', '=', (int) $odoo_product['id'] ) ) );
			$price_lists = json_decode( wp_json_encode( $price_lists ), true );

			if ( ! isset( $price_lists['faultString'] ) ) {
				$error_msg = 'Unable to get Extra Price For Product Id  =>' . $post_id . 'Msg : ' . print_r( $price_lists['faultString'], true );
				$odoo_api->add_log( $error_msg );

				return false;
			}
			if ( isset( $price_lists[0]['fixed_price'] ) ) {
				if ( $odoo_product['list_price'] > $price_lists[0]['fixed_price'] ) {
					update_post_meta( $post_id, '_sale_price', $price_lists[0]['fixed_price'] );
					update_post_meta( $post_id, '_price', $price_lists[0]['fixed_price'] );
					update_post_meta( $post_id, 'wc2odoo_product_extra_price_id', $price_lists[0]['id'] );
				} else {
					$error_msg = 'Extra Price Is Greater than Regular Price For Product Id  => ' . $post_id;
					$odoo_api->add_log( $error_msg );

					return false;
				}
			}
			return true;
		}

		/**
		 * Search for an Odoo customer based on conditions and customer ID.
		 *
		 * @param array $conditions The search conditions.
		 * @return int Returns the customer data if found, false otherwise.
		 */
		public function search_odoo_customer( $conditions ) {
			$odoo_api = $this->get_odoo_api();
			$customer = $odoo_api->search_record( 'res.partner', $conditions );
			if ( is_numeric( $customer ) ) {
				return $customer;
			}
			$error_msg = 'Error In Customer Search Customer Id  =>' . print_r( $conditions, true );
			$odoo_api->add_log( $error_msg );

			return false;
		}

		/**
		 * Search for an Odoo product based on conditions and product ID.
		 *
		 * @param array $conditions The search conditions.
		 * @param int   $product_id The product ID.
		 * @return mixed Returns the product data if found, false otherwise.
		 */
		public function search_odoo_product( $conditions, $product_id ) {
			$odoo_api = $this->get_odoo_api();
			if ( ! isset( $conditions ) || ! is_array( $conditions ) ) {
				$product_response = $odoo_api->search_record( 'product.product', array( array( 'id', '=', $product_id ) ) );
			} else {
				$product_response = $odoo_api->search_record( 'product.product', $conditions );
			}
			$odoo_api->add_log( 'Search Products : ' . print_r( $product_response, true ) );
			if ( is_numeric( $product_response ) ) {
				return $product_response;
			}
			$error_msg = 'Error In product Search product Id  =>' . $product_id . ' With conditions: ' . print_r( $conditions, true );
			$odoo_api->add_log( $error_msg );

			return false;
		}

		/**
		 * Check if an address can be created for a user.
		 *
		 * @param int    $user_id  The user ID.
		 * @param array  $address  The address data.
		 * @param string $type     The type of address.
		 * @return bool Returns true if the address can be created, false otherwise.
		 */
		public function can_create_address( $user_id, $address, $type ) {
			if ( empty( $address['address_1'] ) || empty( $address['postcode'] ) || ( empty( $address['first_name'] ) && empty( $address['last_name'] ) ) ) {
				$odoo_api  = $this->get_odoo_api();
				$error_msg = 'Unable to create customer ' . $type . ' address for customer Id  => ' . $user_id . ' Msg : Required Fields are missing';
				$odoo_api->add_log( $error_msg );

				return false;
			}

			return true;
		}

		/**
		 * Update the order status in Odoo based on the transition from one status to another.
		 *
		 * @param int    $order_id    The order ID.
		 * @param string $from_status The previous order status.
		 * @param string $to_status   The new order status.
		 */
		public function wc2odoo_order_status( $order_id, $from_status, $to_status ) {
			$odoo_api          = $this->get_odoo_api();
			$odoo_api->add_log( 'Order ' . $order_id . ' status change from ' . $from_status . ' to ' . $to_status );
			$order = new \WC_Order( $order_id );
			if ( 'refunded' === $from_status ) {
				$order->update_status( 'refunded', __( 'Order status can\'t be changed from refunded.', 'wc2odoo' ) );

				return false;
			}
			
			$helper            = WC2ODOO_Helpers::get_helper();
			$woo_state         = $helper->get_state( $to_status );
			$statuses          = $helper->odoo_states( $woo_state );
			$export_inv_enable = $helper->is_export_inv();
			$inv_mark_paid     = $helper->is_inv_mark_paid();			

			if ( 'shop_order' !== $order->get_type() ) {
				return false;
			}
			if ( 'no' === $this->odoo_settings['odoo_export_order_on_checkout'] ) {
				return false;
			}


			$odoo_order_synced = $order->get_meta( '_odoo_order_id', true );
			$odoo_api->add_log( 'Odoo order Id : ' . print_r( $odoo_order_synced, 1 ) );
			$odoo_invoice_id   = $order->get_meta( '_odoo_invoice_id', true );

			if ( 'cancelled' === $to_status ) {
				// Let's cancell all associated documents first.
				$this->cancel_order_odoo( $odoo_order_synced );
			}
			else {
				if ( $export_inv_enable ) {
					if ( '' !== $odoo_order_synced && '' === $odoo_invoice_id ) {
						$odoo_order = $odoo_api->update_record( 'sale.order', (int) $odoo_order_synced, array( 'state' => $statuses['order_state'] ) );
						if ( '' !== $statuses['invoice_state'] && 'cancelled' !== $to_status) {
							$invoice = $this->create_invoice( $order_id );

							if ( false === $invoice ) {
								$error_msg = 'Error Create Invoice For order ID  =>' . $order_id . 'Msg : ' . print_r( $invoice, true );
								$odoo_api->add_log( $error_msg );

								return false;
							}
						}
					} elseif ( '' !== $odoo_order_synced && '' !== $odoo_invoice_id ) {
						$odoo_order = $odoo_api->update_record( 'sale.order', (int) $odoo_order_synced, array( 'state' => $statuses['order_state'] ) );

						if ( $inv_mark_paid && '' !== $statuses['invoice_state'] ) {
							$invoice = $odoo_api->update_record( 'account.move', (int) $odoo_invoice_id, array( 'state' => $statuses['invoice_state'] ) );
							$invoice = $odoo_api->update_record( 'account.move', (int) $odoo_invoice_id, array( 'payment_state' => $statuses['payment_state'] ) );
							
						} else {
							$invoice = $odoo_api->update_record( 'account.move', (int) $odoo_invoice_id, array( 'state' => 'draft' ) );
							$invoice = $odoo_api->update_record( 'account.move', (int) $odoo_invoice_id, array( 'payment_state' => 'not_paid' ) );
						}
					} else {
						$this->order_create( $order_id );
					}
				}
			}
		}

		/**
		 * Cancels an order in Odoo.
		 *
		 * @param int $order_id The ID of the order to be canceled.
		 * @return true on success, false on failure.
		 */
		public function cancel_order_odoo( $order_id ) {
			$odoo_api = $this->get_odoo_api();
			$helper            = WC2ODOO_Helpers::get_helper();
			$odoo_api->add_log( 'Starting to cancel order id: ' . $order_id );
			$woo_state         = $helper->get_state( 'cancelled' );
			$statuses          = $helper->odoo_states( $woo_state );

			$related_docs = $odoo_api->fetch_record_by_id( 'sale.order', [ (int) $order_id ], ['picking_ids', 'invoice_ids' ] ) ;
			$odoo_api->add_log( 'Related Docs : ' . print_r( $related_docs, true ) );
			if ( isset( $related_docs['picking_ids'] ) && ! empty( $related_docs['picking_ids'] ) ) {
				foreach ( $related_docs['picking_ids'] as $picking_id ) {
					$ret_val = $odoo_api->update_record( 'stock.picking', (int) $picking_id, array( 'state' =>  $statuses['order_state'] ) );
					$odoo_api->add_log( 'Picking Update Id: ' . print_r( $picking_id, true ) . ' Result: ' . print_r( $ret_val, true ) );
				}
			}
			if ( isset( $related_docs['invoice_ids'] ) && ! empty( $related_docs['invoice_ids'] ) ) {
				foreach ( $related_docs['invoice_ids'] as $invoice_id ) {
					$ret_val = $odoo_api->update_record( 'account.move', (int) $invoice_id, array( 'state' => $statuses['order_state'] ) );
					$odoo_api->add_log( 'Invoice Update Id: ' . print_r( $invoice_id, true ) . ' Result: ' . print_r( $ret_val, true ) );
				}
			}
			$ret_val = $odoo_api->update_record( 'sale.order', (int) $order_id, array( 'state' => $statuses['order_state'] ) );
			$odoo_api->add_log( 'Order update: ' . print_r( $order_id, true ) . ' Result: ' . print_r( $ret_val, true ) );
			$odoo_api->add_log( 'Order ' . $order_id . ' has been cancelled' );

			return $ret_val;
		}

		/**
		 * Export products by date to Odoo.
		 */
		public function odoo_export_product_by_date() {
			// Ver si esto sincroniza productos que ya existen en Odoo.

			if ( ! check_ajax_referer( 'odoo_security', 'security', false ) ) {
				wp_send_json_error(
					array(
						'threads' => array(),
						'subject' => '',
						'error'   => __( 'There was security vulnerability issues in your request.', 'wc2odoo' ),
					)
				);

				wp_die();
			}

			// global $wpdb;

			$date_from = ! empty( $_POST['dateFrom'] ) ? sanitize_text_field( wp_unslash( $_POST['dateFrom'] ) ) : '';
			$date_to   = ! empty( $_POST['dateTo'] ) ? sanitize_text_field( wp_unslash( $_POST['dateTo'] ) ) : '';
			if ( '' !== $date_from ) {
				$date_from = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $date_from ) ) );
			}
			if ( '' !== $date_to ) {
				$date_to = gmdate( 'Y-m-d', strtotime( '1 day', strtotime( $date_to ) ) );
			}

			$exlude_cats  = $this->odoo_settings['odoo_exclude_product_category'];
			$query_string = array(
				'post_type'      => 'product',
				'date_query'     => array(
					'column' => 'post_date',
					'after'  => $date_from,
					'before' => $date_to,
				),
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'order'          => 'ASC',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $exlude_cats,
						'operator' => 'NOT IN',
				),
			);

			$products_q = new \WP_Query( $query_string );
			$products   = $products_q->posts;

			$odoo_api       = $this->get_odoo_api();
			$product_added  = 0;
			$product_upated = 0;
			foreach ( $products as $key => $product_obj ) {
				$product = wc_get_product( $product_obj );

				$syncable_product = get_post_meta( $product->get_id(), '_exclude_product_to_sync', true );

				if ( 'yes' === $syncable_product ) {
					continue;
				}

				if ( $product->has_child() ) {
					$odoo_template_id = get_post_meta( $product->get_id(), 'wc2odoo_id', true );
					if ( $odoo_template_id ) {
						$this->do_export_variable_product_update( (int) $odoo_template_id, $product );
					} else {
						$this->do_export_variable_product( $product );
					}
				} else {
					$odoo_product_id = get_post_meta( $product->get_id(), 'wc2odoo_id', true );
					// Search Product on Odoo.
					if ( ! $odoo_product_id ) {
						$conditions      = array( array( $this->odoo_sku_mapping, '=', $product->get_sku() ) );
						$odoo_product_id = $this->search_odoo_product( $conditions, $product->get_id() );
					}

					if ( $odoo_product_id ) {
						$this->update_odoo_product( (int) $odoo_product_id, $product );
						++$product_upated;
					} else {
						$odoo_product_id = $this->create_product( $product );
						++$product_added;
					}
					if ( false === $odoo_product_id ) {
						$error_msg = 'Error for Creating/Updating  Product Id  =>' . $product->get_id();
						$odoo_api->add_log( $error_msg );

						continue;
					}
					if ( false === $odoo_product_id ) {
						continue;
					}
					update_post_meta( $product->get_id(), 'wc2odoo_id', $odoo_product_id );
					if ( 'yes' === $this->odoo_settings['odoo_export_update_price'] ) {
						if ( $product->is_on_sale() ) {
							$odoo_extra_product = get_post_meta( $product->get_id(), 'wc2odoo_product_extra_price_id', true );
							if ( $odoo_extra_product ) {
								$this->update_extra_price( $odoo_extra_product, $product );
							} else {
								$this->create_extra_price( $odoo_product_id, $product );
							}
						}
					}
					if ( 'yes' === $this->odoo_settings['odoo_export_update_stocks'] ) {
						if ( $product->get_stock_quantity() > 0 ) {
							$product_qty = number_format( (float) $product->get_stock_quantity(), 2, '.', '' );
							$this->update_product_quantity( $odoo_product_id, $product_qty );
						}
					}
					update_post_meta( $product->get_id(), 'wc2odoo_image_id', $product->get_image_id() );
				}
			}
			wp_send_json(
				array(
					'result'         => 'success',
					'product_added'  => $product_added,
					'product_upated' => $product_upated,
					'total_product'  => count( $products ),
				)
			);
		}

		/**
		 * Export orders to Odoo with background process.
		 */
		public function odoo_export_order_by_date_background() {
			if ( $this->export_orders->is_processing() ) {
				wp_send_json_error(
					array(
						'result'  => 'error',
						'message' => __(
							'Order export is already running.',
							'wc2odoo'
						),
					)
				);

				wp_die();
			}
			$odoo_api = $this->get_odoo_api();

			if ( ! check_ajax_referer( 'odoo_security', 'security', false ) ) {
				wp_send_json_error(
					array(
						'threads' => array(),
						'subject' => '',
						'error'   => 'There was security vulnerability issues in your request.',
					)
				);

				wp_die();
			}
			global $wpdb;

			if ( ! empty( $var = $_POST['dateFrom'] ) ) { //phpcs:ignore
				$date_from = gmdate( 'Y-m-d', strtotime( sanitize_text_field( $var ) ) );
			} else {
				$date_from = '1900-01-01';
			}

			if ( ! empty( $var = $_POST['dateTo'] ) ) { //phpcs:ignore
				$date_to = gmdate( 'Y-m-d', strtotime( sanitize_text_field( $var ) ) );
			} else {
				$date_to = '2900-01-01';
			}

			$odoo_api->add_log( 'Exporting order with date from: ' . print_r( $date_from, true ) . ' - ' . print_r( $date_to, true ) );

/*			$orders = $wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT distinct pm.post_id AS order_id
				FROM {$wpdb->prefix}postmeta AS pm
				LEFT JOIN {$wpdb->prefix}posts AS p
				ON pm.post_id = p.ID
				WHERE p.post_type = 'shop_order'
				AND pm.meta_key = '_customer_user'
				AND  p.post_date >= %s
				AND  p.post_date <=  %s
                AND p.ID not in (select pp.post_id from {$wpdb->prefix}postmeta pp where pp.meta_key = '_odoo_order_id')
				AND p.post_status like %s
				AND p.post_status != 'wc-cancelled'
				ORDER BY pm.post_id ASC
				",
					$date_from,
					$date_to,
					'wc-%'
				)
			);
*/
			$orders = $wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT distinct pm.post_id AS order_id
				FROM {$wpdb->prefix}postmeta AS pm
				LEFT JOIN {$wpdb->prefix}posts AS p
				ON pm.post_id = p.ID
				WHERE p.post_type = 'shop_order'
				AND pm.meta_key = '_customer_user'
				AND  p.post_date >= %s
				AND  p.post_date <=  %s
				AND p.post_status like %s
				ORDER BY pm.post_id ASC
				",
					$date_from,
					$date_to,
					'wc-%'
				)
			);

			$this->export_orders->empty_data();
			$counting_batch = 0;
			foreach ( $orders as $key => $order ) {
				$counting_batch++;
				$this->export_orders->push_to_queue( $order->order_id );
				if ( $counting_batch >= 2 ) {
					//Saving a batch of 3 orders to not overload the server.
					$this->export_orders->save();
					$counting_batch = 0;
				}
			}
			$orders_to_sync = count( $orders );

			update_option( 'wc2odoo_order_export_count', $orders_to_sync );
			update_option( 'wc2odoo_order_export_remaining_count', $orders_to_sync );

			$this->export_orders->save()->dispatch();
			wp_send_json_success(
				array(
					'message' => __(
						'Export process has started for ',
						'wc2odoo'
					) . $orders_to_sync . __(
						' orders',
						'wc2odoo'
					),
				)
			);
			wp_die();
		}

		/**
		 * Export products to Odoo with background process.
		 */
		public function odoo_export_product_by_date_background() {
			if ( $this->export_products->is_processing() ) {
				wp_send_json_error(
					array(
						'result'  => 'error',
						'message' => __(
							'Product export is already running.',
							'wc2odoo'
						),
					)
				);

				wp_die();
			}
			$odoo_api = $this->get_odoo_api();
			if ( false & ! check_ajax_referer( 'odoo_security', 'security', false ) ) {
				wp_send_json_error(
					array(
						'threads' => array(),
						'subject' => '',
						'error'   => 'There was security vulnerability issues in your request.',
					)
				);

				wp_die();
			}
			
			$date_from = ! empty( $_POST['dateFrom'] ) ? sanitize_text_field( wp_unslash( $_POST['dateFrom'] ) ) : '2020-01-01';
			$date_to   = ! empty( $_POST['dateTo'] ) ? sanitize_text_field( wp_unslash( $_POST['dateTo'] ) ) : '2200-01-01';

			$odoo_api->add_log( 'Exporting products with date from: ' . print_r( $date_from, true ) . ' - ' . print_r( $date_to, true ) );

			if ( '' !== $date_from ) {
				$date_from = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $date_from ) ) );
			}
			if ( '' !== $date_to ) {
				$date_to = gmdate( 'Y-m-d', strtotime( '1 day', strtotime( $date_to ) ) );
			}

			$exlude_cats  = $this->odoo_settings['odoo_exclude_product_category'];
			$query_string = array(
				'post_type'      => 'product',
				'date_query'     => array(
					'column' => 'post_date',
					'after'  => $date_from,
					'before' => $date_to,
				),
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'order'          => 'ASC',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $exlude_cats,
						'operator' => 'NOT IN',
				),
			);

			$products_q = new \WP_Query( $query_string );
			$products   = array_unique( $products_q->posts, SORT_REGULAR );
			$this->export_products->empty_data();
			
			$odoo_api->add_log( 'Total products to export: ' . array_count_values( $products ) );
			
			$counting_batch = 0;
			foreach ( $products as $key => $product ) {
				$counting_batch++;
				$this->export_products->push_to_queue( $product );
				if ( $counting_batch >= 3 ) {
					//Saving a batch of 3 orders to not overload the server.
					$this->export_products->save();
					$counting_batch = 0;
				}
			}
			$products_to_sync = count( $products );

			update_option( 'wc2odoo_order_export_count', $products_to_sync );
			update_option( 'wc2odoo_order_export_remaining_count', $products_to_sync );

			$this->export_products->save()->dispatch();
			wp_send_json_success(
				array(
					'message' => __(
						'Export process has started for ',
						'wc2odoo'
					) . $products_to_sync . __(
						' products',
						'wc2odoo'
					),
				)
			);
			wp_die();
		}


		/**
		 * Export customers by date.
		 */
		public function odoo_export_customer_by_date() {
			if ( ! check_ajax_referer( 'odoo_security', 'security', false ) ) {
				wp_send_json(
					array(
						'threads' => array(),
						'subject' => '',
						'error'   => 'There was security vulnerability issues in your request.',
					)
				);

				exit;
			}
			global $wpdb;

			$date_from = ! empty( $_POST['dateFrom'] ) ? sanitize_text_field( wp_unslash( $_POST['dateFrom'] ) ) : '';
			$date_to   = ! empty( $_POST['dateTo'] ) ? sanitize_text_field( wp_unslash( $_POST['dateTo'] ) ) : '';
			if ( '' !== $date_from ) {
				$date_from = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $date_from ) ) );
			}
			if ( '' !== $date_to ) {
				$date_to = gmdate( 'Y-m-d', strtotime( '1 day', strtotime( $date_to ) ) );
			}

			$args          = array(
				'role'           => 'customer',
				'date_query'     => array(
					'after'     => $date_from,
					'before'    => $date_to,
					'inclusive' => false,
				),
				'order'          => 'ASC',
				'orderby'        => 'ID',
				'posts_per_page' => -1,
			);
			$wp_user_query = new \WP_User_Query( $args );
			$customers     = $wp_user_query->get_results();

			$customer_added  = 0;
			$customer_upated = 0;
			$email           = array();
			foreach ( $customers as $key => $customer ) {
				if ( '' !== $customer->user_email ) {
					// No confiar en _odoo_id porque puede ser que el usuario haya sido creado en Odoo y no en WordPress.
					$customer_id = false;
					array_push( $email, $customer->user_email );

					$conditions  = array( array( 'type', '=', 'contact' ), array( 'email', '=', $customer->user_email ) );
					$customer_id = $this->search_odoo_customer( $conditions );

					if ( $customer_id ) {
						++$customer_upated;
					} else {
						++$customer_added;
					}
					$customer_id = $this->create_or_update_customer( $customer, $customer_id );

					if ( false === $customer_id ) {
						continue;
					}
					update_user_meta( $customer->ID, '_odoo_id', $customer_id );
					$this->action_woocommerce_customer_save_address( $customer->ID, 'shipping' );
					$this->action_woocommerce_customer_save_address( $customer->ID, 'billing' );
				}
			}

			wp_send_json(
				array(
					'result'          => 'success',
					'customer_added'  => $customer_added,
					'customer_upated' => $customer_upated,
					'total_customer'  => count( $customers ),
				)
			);
		}

		/**
		 * Export order by number.
		 */
		public function odoo_export_order_by_number() {
			$odoo_api = $this->get_odoo_api();

			if ( ! check_ajax_referer( 'odoo_security', 'security', false ) ) {
				wp_send_json(
					array(
						'threads' => array(),
						'subject' => '',
						'error'   => 'There was security vulnerability issues in your request.',
					)
				);

				exit;
			}

			if ( ! empty( $var = $_POST['orderNumber'] ) ) { //phpcs:ignore
				$order_id = sanitize_text_field( $var );
				$order    = wc_get_order( $order_id );

				if ( $this->order_create( $order->get_id() ) ) {
					$odoo_api->add_log( 'Order Created: ' . print_r( $order_id, true ) );
				} else {
					$odoo_api->add_log( 'Order Not Created: ' . print_r( $order_id, true ) );
				}
			}

			wp_send_json(
				array(
					'result'        => 'success',
					'order_created' => $order_id,
				)
			);
		}

		/**
		 * Import customers by date.
		 */
		public function odoo_import_customer_by_date() {
			if ( ! check_ajax_referer( 'odoo_security', 'security', false ) ) {
				wp_send_json(
					array(
						'threads' => array(),
						'subject' => '',
						'error'   => 'There was security vulnerability issues in your request.',
					)
				);

				exit;
			}
			global $wpdb;
			$date_from = ! empty( $_POST['dateFrom'] ) ? sanitize_text_field( wp_unslash( $_POST['dateFrom'] ) ) : '';
			$date_to   = ! empty( $_POST['dateTo'] ) ? sanitize_text_field( wp_unslash( $_POST['dateTo'] ) ) : '';
			$odoo_api  = $this->get_odoo_api();
			$customers = $odoo_api->read_all( 'res.partner', array( array( 'type', '=', 'contact' ), array( 'create_date', '>=', $date_from ), array( 'create_date', '<=', $date_to ) ), array( 'create_date', 'write_date', 'id', 'name', 'display_name', 'website', 'mobile', 'email', 'is_company', 'phone', 'image_medium', 'street', 'street2', 'zip', 'city', 'state_id', 'country_id', 'child_ids', 'type' ) );
			$email     = array();
			if ( ! isset( $customers['fail'] ) && is_array( $customers ) && count( $customers ) ) {
				foreach ( $customers as $key => $customer ) {
					if ( isset( $customer['email'] ) && ! empty( $customer['email'] ) ) {
						$address_lists = array();
						if ( count( $customer['child_ids'] ) > 0 ) {
							$address_lists = $odoo_api->fetch_record_by_ids( 'res.partner', $customer['child_ids'], array( 'id', 'name', 'display_name', 'website', 'mobile', 'email', 'is_company', 'phone', 'image_medium', 'street', 'street2', 'zip', 'city', 'state_id', 'country_id', 'child_ids', 'type' ) );
							if ( $address_lists ) {
								continue;
							}
						}
						$this->sync_customer_to_wc( $customer, $address_lists );
						array_push( $email, $customer['email'] );
					}
				}
			}

			echo wp_json_encode(
				array(
					'result'         => 'success',
					'error'          => $customers['msg'],
					'total_customer' => count( $customers ),
				)
			);

			exit;
		}

		/**
		 * Get the SKU and Lot information for products.
		 *
		 * @param int $page The page number.
		 * @return array The SKU and Lot information.
		 */
		private function get_product_sku_lot( $page = 0 ) {
			global $wpdb;

			$limit = 3;

			if ( 0 === $page ) {
				$offset = 0;
			} else {
				$offset = $limit * $page;
			}

			$products = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts}  WHERE (post_type='product' OR post_type='product_variation') AND post_status='publish' LIMIT %d , %d", $offset, $limit ) );
			$sku_lot  = array();

			foreach ( $products as $product ) {
				$sku = get_post_meta( $product->ID, '_sku', true ); // MANISH : CHANGE THIS TO CUSTOM FIELD.
				if ( ! empty( $sku ) ) {
					$sku_lot[ $product->ID ] = $sku;
				}
			}

			return $sku_lot;
		}

		/**
		 * Get the Odoo API instance.
		 *
		 * @return WC2ODOO_API The Odoo API instance.
		 */
		public function get_odoo_api() {
			if ( ! $this->odoo_api ) {
				$this->odoo_api = new WC2ODOO_API();
			}

			return $this->odoo_api;
		}

		/**
		 * Get the last l10n_latam_document_number of a l10n_latam_document_type_id.
		 * 
		 * @param int $l10n_latam_document_type_id The ID of the document type.
		 * @return string The last l10n_latam_document_number formated with 000000.
		 */
		public function get_last_l10n_latam_document_number( $l10n_latam_document_type_id ) {
			$odoo_api = $this->get_odoo_api();
			$last_number = $odoo_api->read_all('account.move', array(array( 'l10n_latam_document_type_id', '=', (int) $l10n_latam_document_type_id ), array( 'name', 'not like', 'False%' ), array( 'state', '!=', 'cancel'), array( 'journal_id', '=', (int) $this->odoo_settings['invoiceJournal'] ) ), array( 'l10n_latam_document_number' ), 1, 'name desc');
			
			if ( isset( $last_number['fail'] ) || !isset( $last_number[0] ) ) {
				$error_msg = 'Unable to get Last Document Number For Document Type Id  => ' . $l10n_latam_document_type_id . ' - Msg : ' . print_r( $last_number, true );
				$odoo_api->add_log( $error_msg );

				return '000000';
			}
			$new_number = (int) $last_number[0]['l10n_latam_document_number'];
			$new_number++;

			// Return a string with 6 digits, filled with zero to the left.
			return str_pad( $new_number, 6, '0', STR_PAD_LEFT );
		}

		public function format_rut($rut) {
			// Remove any non-numeric characters
			$rut = preg_replace('/[^0-9]/', '', $rut);
		
			// Insert the hyphen before the last character
			$formatted_rut = substr($rut, 0, -1) . '-' . substr($rut, -1);
		
			return $formatted_rut;
		}

	}

	new WC2ODOO_Functions();
}
