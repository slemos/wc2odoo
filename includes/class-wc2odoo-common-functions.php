<?php
/**
 * @file WC2ODOO_Common_Functions class
 *
 * @subpackage includes
 * @package WC2ODOO
 */

/**
 * Class WC2ODOO_Common_Functions
 * This class contains common functions used throughout the plugin.
 *
 * @since 1.0.0
 */
class WC2ODOO_Common_Functions {

	/**
	 * The URL of the Odoo instance.
	 *
	 * @var string
	 */
	public $odoo_url;

	/**
	 * The name of the Odoo database.
	 *
	 * @var string
	 */
	public $odoo_db;

	/**
	 * @var string the username for the Odoo account
	 */
	public $odoo_username;

	/**
	 * The password for the Odoo instance.
	 *
	 * @var string
	 */
	public $odoo_password;

	/**
	 * The credentials for the user.
	 *
	 * @var array
	 */
	public $creds;

	/**
	 * The Odoo tax object.
	 *
	 * @var object
	 */
	public $odoo_tax;

	/**
	 * @var mixed the tax applied to Odoo shipping
	 */
	public $odoo_shipping_tax;

	/**
	 * The Odoo invoice journal.
	 *
	 * @var string
	 */
	public $odoo_invoice_journal;

	/**
	 * Access token for wc2odoo integration.
	 *
	 * @var string
	 */
	public $wc2odoo_access_token;

	/**
	 * Indicates whether the settings have been changed or not.
	 *
	 * @var bool
	 */
	public $settings_changed;

	/**
	 * @var bool whether the plugin has been updated or not
	 */
	public $plugin_updated;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->set_credentials();
		$this->wc2odoo_access_token = get_option( 'wc2odoo_access_token', false );
		add_filter( 'woocommerce_email_recipient_customer_completed_order', array( $this, 'wc2odoo_disable_import_order_email' ), 10, 2 );
	}

	/**
	 * Getting started function.
	 */
	public function getting_started() {
		$this->set_credentials();
		$url = $this->get_settings_url();
		if ( '' === $this->odoo_url || '' === $this->odoo_db || '' === $this->odoo_username || '' === $this->odoo_password ) {
			// translators: 1: Strong Tag start, 2: Strong Tag end, 3: link start 4: link end.
			echo '<div class="notice notice-error"><p>' . sprintf( esc_html__( '%1$s WooCommerce ODOO Integration is almost ready. %2$s To get started, %3$s go to ODOO Account Settings %4$s and Set Odoo details and click save button.', 'wc2odoo' ), '<strong>', '</strong>', '<a href="' . esc_url( $url ) . '">', '</a>' ) . '</p></div>' . "\n";
		} elseif ( false !== $this->wc2odoo_access_token && ( '' === $this->odoo_tax || '' === $this->odoo_shipping_tax || '' === $this->odoo_invoice_journal ) ) {
			// translators: 1: Strong Tag start, 2: Strong Tag end, 3: link start 4: link end.
			echo '<div class="notice notice-warning"><p>' . sprintf( esc_html__( '%1$s WooCommerce ODOO Integration is almost ready. %2$s To get started, %3$s go to ODOO Account Settings %4$s and %1$s Set Odoo Tax, Shipping Tax details and Sale Invoice Journal %2$s and click save button.', 'wc2odoo' ), '<strong>', '</strong>', '<a href="' . esc_url( $url ) . '">', '</a>' ) . '</p></div>' . "\n";
		}
	}

	/**
	 * Checks if the credentials are defined.
	 *
	 * @return bool
	 */
	public function is_creds_defined() {
		if ( ! empty( $this->odoo_url ) && ! empty( $this->odoo_db ) && ! empty( $this->odoo_username ) && ! empty( $this->odoo_password ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Disable import order email for WC2ODOO integration.
	 *
	 * @param string $recipient The email recipient.
	 * @param object $order The order object.
	 * @return string The modified email recipient.
	 */
	public function wc2odoo_disable_import_order_email( $recipient, $order ) {
		$wc2odoo_ordder_id = get_post_meta( $order->id, '_odoo_order_id', true );
		if ( $wc2odoo_ordder_id ) {
			$recipient = '';
		}

		return $recipient;
	}

	/**
	 * Checks if the user is authenticated.
	 *
	 * @return bool True if the user is authenticated, false otherwise.
	 */
	public function is_authenticate() {
		$this->set_credentials();
		if ( $this->is_creds_defined() ) {
			$odoo_api = new WC2ODOO_API( $this->odoo_url, $this->odoo_db, $this->odoo_username, $this->odoo_password );
			$odoo_api->add_log( 'Credentials defined!!' );
			$odoo_api->add_log( 'Settings changed : ' . print_r( $this->settings_changed, true ) );
			$odoo_api->add_log( 'wc2odoo_access_token : ' . print_r( $this->wc2odoo_access_token, true ) );

			if ( ! $this->wc2odoo_access_token ) {
				if ( $this->settings_changed ) {
					delete_option( 'wc2odoo_access_token' );
					delete_option( 'wc2odoo_authenticated_uid' );
					delete_option( 'wc2odoo_access_error' );
					$response = $odoo_api->generate_token();
					$odoo_api->add_log( 'Response : ' . print_r( $response, true ) );
					update_option( 'is_wc2odoo_settings_changed', 0 );
					update_option( '_wc2odoo_update_configs', 1 );
					$this->settings_changed = 0;
					if ( is_numeric( $response ) ) {
						update_option( 'wc2odoo_access_token', $response );
						update_option( 'wc2odoo_authenticated_uid', $response );

						return true;
					}
					update_option( 'wc2odoo_access_error', $response );

					return false;
				} elseif ( $this->plugin_updated ) {
					update_option( 'is_wc2odoo_settings_changed', 1 );
					$response = $odoo_api->generate_token();
					$odoo_api->add_log( 'response : ' . print_r( $response, true ) );
					update_option( 'is_wc2odoo_settings_changed', 0 );
					update_option( '_wc2odoo_update_configs', 1 );
					delete_option( 'wc_wc2odoo_update_state' );
					$this->settings_changed = 0;
					if ( is_numeric( $response ) ) {
						update_option( 'wc2odoo_access_token', $response );
						update_option( 'wc2odoo_authenticated_uid', $response );

						return true;
					}
					update_option( 'wc2odoo_access_error', $response );

					return false;
				}
				$wc2odoo_access_error = get_option( 'wc2odoo_access_error', false );
				if ( $wc2odoo_access_error ) {
					return false;
				}

				return true;
			}
			$wc2odoo_access_error = get_option( 'wc2odoo_access_error', false );
			if ( $wc2odoo_access_error ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Displays an admin notice for WooCommerce Odoo Integration.
	 */
	public function wc2odoo_admin_notice() {
		$error_code = get_option( 'wc2odoo_access_error', false );
		$url        = $this->get_settings_url();
		if ( 'INVALID_HOST' === $error_code ) {
			// translators: 1: Strong Tag start, 2: Strong Tag end, 3: link start 4: link end.
			echo '<div class="notice notice-error"><p>' . sprintf( esc_html__( '%1$s WooCommerce ODOO Integration Server URL is Invalid. %2$s Please verify ODOO Credentials & %3$s go to ODOO Account Settings %4$s and Set Odoo details and click save button.', 'wc2odoo' ), '<strong>', '</strong>', '<a href="' . esc_url( $url ) . '">', '</a>' ) . '</p></div>' . "\n";
		}
		if ( 'INVALID_CREDS' === $error_code ) {
			// translators: 1: Strong Tag start, 2: Strong Tag end, 3: link start 4: link end.
			echo '<div class="notice notice-error"><p>' . sprintf( esc_html__( '%1$s WooCommerce ODOO Integration credentials are Invalid. %2$s Please verify ODOO credentials & %3$s go to ODOO Account Settings %4$s and Set Odoo details and click save button.', 'wc2odoo' ), '<strong>', '</strong>', '<a href="' . esc_url( $url ) . '">', '</a>' ) . '</p></div>' . "\n";
		}
	}

	/**
	 * Get the URL for the settings page.
	 *
	 * @return string The URL for the settings page.
	 */
	public function get_settings_url() {
		return add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'integration',
				'section' => 'woocommmerce_odoo_integration',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Sets the credentials.
	 */
	private function set_credentials() {
		$this->creds = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );
		if ( is_array( $this->creds ) ) {
			// Solo cambiar parámetros si existe la opción.
			$this->odoo_url             = isset( $this->creds['client_url'] ) ? rtrim( $this->creds['client_url'], '/' ) : '';
			$this->odoo_db              = $this->creds['client_db'] ?? '';
			$this->odoo_username        = $this->creds['client_username'] ?? '';
			$this->odoo_password        = $this->creds['client_password'] ?? '';
			$this->odoo_tax             = $this->creds['odooTax'] ?? '';
			$this->odoo_shipping_tax    = $this->creds['shippingOdooTax'] ?? '';
			$this->odoo_invoice_journal = $this->creds['invoiceJournal'] ?? '';
		}
		// Asumimos que si no hay get_option las opciones cambiaron.
		$this->settings_changed = get_option( 'is_wc2odoo_settings_changed' ) ?: true;
		$this->plugin_updated   = true;
	}
}
