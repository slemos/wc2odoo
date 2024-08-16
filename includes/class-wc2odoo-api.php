<?php //phpcs:ignore Squiz.Commenting.FileComment.Missing
/**
 * @file WC2ODOO_API class
 *
 * This class provides methods to interact with the Odoo API.
 *
 * @package WC2ODOO
 */

require_once 'class-odoo-client.php';

/**
 * Class WC2ODOO_API
 *
 * This class provides methods to interact with the Odoo API.
 */
class WC2ODOO_API {

	/**
	 * The URL of the Odoo instance.
	 *
	 * @var string
	 */
	protected $odoo_url;

	/**
	 * The database name of the Odoo instance.
	 *
	 * @var string
	 */
	protected $odoo_db;

	/**
	 * The username of the Odoo instance.
	 *
	 * @var string
	 */
	protected $odoo_username;

	/**
	 * The password of the Odoo instance.
	 *
	 * @var string
	 */
	protected $odoo_password;

	/**
	 * The Odoo credentials available.
	 *
	 * @var bool
	 */
	protected $odoo_creds_available = false;

	/**
	 * The settings changed.
	 *
	 * @var string
	 */
	protected $settings_changed;

	/**
	 * The Odoo client.
	 *
	 * @var Odoo_Client
	 */
	protected $client;

	/**
	 * The debug mode.
	 *
	 * @var string
	 */
	protected $debug_mode;

	/**
	 * Class constructor.
	 *
	 * @param string $url      The URL of the Odoo instance.
	 * @param string $db       The database name of the Odoo instance.
	 * @param string $username The username of the Odoo instance.
	 * @param string $password The password of the Odoo instance.
	 * @param string $debug    The debug mode.
	 */
	public function __construct( $url = '', $db = '', $username = '', $password = '', $debug = 'no' ) {
		if ( ! empty( $url ) && ! empty( $db ) && ! empty( $username ) && ! empty( $password ) ) {
			$this->odoo_creds_available = true;
			// Set internal properties with passed values.
			$this->odoo_url      = $url;
			$this->odoo_db       = $db;
			$this->odoo_username = $username;
			$this->odoo_password = $password;
			$this->debug_mode    = $debug;
		} else {
			$creds          = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );
			$this->odoo_url = isset( $creds['client_url'] ) ? rtrim( $creds['client_url'], '/' ) : '';
			$this->odoo_db  = $creds['client_db'] ?? '';

			$this->odoo_username = $creds['client_username'] ?? '';
			$this->odoo_password = $creds['client_password'] ?? '';
			$this->debug_mode    = $creds['debug'] ?? 'no';
			if ( ! empty( $this->odoo_url ) && ! empty( $this->odoo_db ) && ! empty( $this->odoo_username ) && ! empty( $this->odoo_password ) ) {
				$this->odoo_creds_available = true;
			}
			$this->settings_changed = get_option( 'is_wc2odoo_settings_changed' ) ?: 0;
		}
	}

	/**
	 * Generate a token.
	 */
	public function generate_token() {
		return $this->get_client()->get_uid();
	}

	/**
	 * Search record in Odoo crm.
	 *
	 * @param string $type       [search record type].
	 * @param array  $conditions [search condition in the form of array].
	 *
	 * @return bool|mixed [record id as mixed or boolean value]
	 */
	public function search_record( $type, $conditions = array() ) {
		$record = $this->get_client()->search( $type, $conditions );
		if ( isset( $record['faultCode'] ) ) {
			$this->add_log( 'Unable To Search Record Msg : ' . print_r( $record, true ) );

			return false;
		}
		if ( isset( $record[0] ) ) {
			return $record[0];
		} else {
			return false;
		}
	}

	/**
	 * Search for records of a given type in Odoo.
	 *
	 * @param string $type       the type of record to search for.
	 * @param array  $conditions an array of conditions to filter the search results.
	 * @param array  $pagination an array containing the offset and limit for pagination.
	 *
	 * @return mixed returns an array of records if successful, or false if an error occurred
	 */
	public function search( $type, $conditions = array(), $pagination = array(), $order = '' ) {

		$record = $this->get_client()->search( $type, $conditions, $pagination['offset'], $pagination['limit'], $order );

		if ( isset( $record['faultCode'] ) ) {
			return false;
		}

		return $record;
	}

	/**
	 * Search for records of a given type in Odoo.
	 *
	 * @param string     $type       the type of record to search for.
	 * @param null|array $conditions Optional. An array of conditions to filter the search results. Defaults to null.
	 * @param array      $fields     Optional. An array of fields to include in the search results. Defaults to an empty array.
	 *
	 * @return array|false an array of records matching the search criteria, or false if an error occurred
	 */
	public function search_records( $type, $conditions = null, $fields = array() ) {
		$record = $this->get_client()->search_read( $type, $conditions, $fields );

		if ( isset( $record['faultCode'] ) ) {
			return false;
		}

		return $record;
	}

	/**
	 * Search records in Odoo crm.
	 *
	 * @param string $type       search record type.
	 * @param array  $conditions search condition in the form of array.
	 *
	 * @return array|bool array of id´s or 0
	 */
	public function search_count( $type, $conditions = null ) {

		$record = $this->get_client()->search_count( $type, $conditions );

		if ( isset( $record['faultCode'] ) ) {
			return 0;
		}

		return $record;
	}

	/**
	 * Fetches a record by its ID from Odoo.
	 *
	 * @param string $type   the type of record to fetch.
	 * @param array  $ids    the IDs of the records to fetch.
	 * @param array  $fields Optional. The fields to fetch for the record. Defaults to an empty array.
	 *
	 * @return array|false the fetched record, or false if it could not be fetched
	 */
	public function fetch_record_by_id( $type, $ids, $fields = array() ) {

		$record = $this->get_client()->read( $type, $ids, $fields );
		if ( isset( $record['faultCode'] ) ) {
			$this->add_log( 'Unable To Fetch Record Msg : ' . print_r( $record, true ) );

			return false;
		}
		else if ( empty( $record ) ) {
			$this->add_log( 'Record not found' );

			return false;
		}
		//$this->add_log( 'Record fetched : ' . print_r( $record, true ) );
		return $record[0];
	}

	/**
	 * Fetches a record by its ID(s) from Odoo.
	 *
	 * @param string    $type   the type of record to fetch.
	 * @param array|int $ids    the ID(s) of the record(s) to fetch.
	 * @param array     $fields Optional. An array of fields to fetch for the record(s).
	 *
	 * @return array|bool an array of records if found, false otherwise
	 */
	public function fetch_record_by_ids( $type, $ids, $fields = array() ) {

		$record = $this->get_client()->read( $type, $ids, $fields );
		if ( isset( $record['faultCode'] ) ) {
			return false;
		}

		return $record;
	}

	/**
	 * [create_record description].
	 *
	 * @param string $type search record type.
	 * @param array  $data record to create the data.
	 *
	 * @return array|int Record id or array with faultCode and faultString
	 */
	public function create_record( $type, $data ) {

		// try/catch block to catch the error.
		try {
			return $this->get_client()->create( $type, $data );
		} catch ( \Exception $e ) {
			$this->add_log( 'Unable To Create Record Msg : ' . print_r( $e->getMessage(), true ) );

			return array(
				'fail' => true,
				'msg'  => $e->getMessage(),
			);
		}
	}

	/**
	 * Fetch all fields of records from odoo.
	 *
	 * @param string $type      records type.
	 * @param array  $condition conditions for the record fetch.
	 * @param array  $fields    fields need to fetch.
	 * @param int    $limit     limit of records.
	 * @param int	 $order     order of records.
	 *
	 * @return array array of records or an array with faultCode and faultString
	 */
	public function read_all( $type, $condition, $fields = array(), $limit = 1000, $order = '') {

		// try/catch block to catch the error.
		try {
			return $this->get_client()->search_read( $type, $condition, $fields, $limit, $order );
		} catch ( \Exception $e ) {
			$this->add_log( 'Unable To Fetch Record Msg : ' . print_r( $e->getMessage(), true ) );

			return array(
				'fail' => true,
				'msg'  => $e->getMessage(),
			);
		}
	}

	/**
	 * Fetches the product inventory based on the given criteria.
	 *
	 * @param mixed $criteria Optional. The criteria to filter the product inventory.
	 *
	 * @return array|bool An array of product inventory if found, false otherwise.
	 */
	public function fetch_product_inventory( $criteria = null ) {

		$fields = array( 'name', 'free_qty', 'qty_available', 'barcode', 'list_price', 'default_code', 'barcode' );

		try {
			$record = $this->get_client()->search_read( 'product.product', $criteria, $fields, 1 );
		} catch ( \Exception $e ) {
			$this->add_log( 'Unable To Fetch Inventory Product' . $e->getMessage() );

			return array(
				'fail' => true,
				'msg'  => $e->getMessage(),
			);
		}

		if ( isset( $record['faultCode'] ) ) {
			$this->add_log( 'Unable To Fetch Inventory Product' . print_r( $record, true ) );

			return array(
				'fail' => true,
				'msg'  => $record['faultString'],
			);
		}
		if ( count( $record ) > 0 ) {
			return $record[0];
		}

		return false;
	}

	/**
	 * Update a record in Odoo.
	 *
	 * @param string $type   the type of record to update.
	 * @param int  $id    the IDs of the records to update.
	 * @param array  $fields the fields to update for the records.
	 *
	 * @return array the response from the Odoo API
	 */
	public function update_record( $type, $id, $fields ) {

		$ret_val = $this->get_client()->write( $type, (int) $id, $fields );
		if ( isset( $ret_val['faultCode'] ) ) {
			$this->add_log( 'Unable To Update Record' . print_r( $ret_val, true ) );
			
			return array(
				'fail' => true,
				'msg'  => $ret_val['faultString'],
			);
		}

		return $ret_val;
	}

	/**
	 * Reads fields from a given Odoo model.
	 *
	 * @param string $model  the name of the Odoo model to read from.
	 * @param array  $fields An array of field names to read. Defaults to an empty array.
	 * @param array  $attrs  An array of attributes to pass to the read method. Defaults to an empty array.
	 *
	 * @return mixed returns the record if successful, or false if unsuccessful
	 */
	public function read_fields( $model, $fields = array(), $attrs = array() ) {

		$record = $this->get_client()->read_fields( $model, $fields, $attrs );
		if ( $record ) {
			return $record;
		}
		else {
			// add log call to log the error.
			$this->add_log( 'Unable To Fetch Record Msg : ' . print_r( $record, true ) );
		}

		return false;
	}

	/**
	 * Add a log message.
	 *
	 * @param string $message The log message.
	 */
	public function add_log( $message ) {
		if ( 'yes' === $this->debug_mode ) {
			$wc_logger = wc_get_logger();
			$bt = debug_backtrace();
			$log = array_shift( $bt );
			$log = array_shift( $bt );
			unset( $log['object'] );
			unset( $log['type'] );
			$wc_logger->log( 'warning', $message, $log );
		}
	}

	/**
	 * Returns an instance of the Odoo XML-RPC client.
	 *
	 * If the client has not been instantiated yet, it will be created with the credentials
	 * stored in the class properties. The authenticated user ID is retrieved from the
	 * WordPress options table.
	 *
	 * @return Odoo_Client an instance of the Odoo XML-RPC client
	 */
	public function get_client() {
		if ( ! $this->client ) {
			$this->client = new \Odoo_Client( $this->odoo_url . '/xmlrpc/2', $this->odoo_db, $this->odoo_username, $this->odoo_password );
		}

		return $this->client;
	}

	/**
	 * Returns the version of the Odoo XML-RPC client.
	 *
	 * @return string the version of the Odoo XML-RPC client
	 */
	public function version() {
		try {
			$record = $this->get_client()->version();
			if ( $record ) {
				return $record;
			}
			$this->add_log( 'invalid creds' );

			return null;
		} catch ( \Exception $e ) {
			$this->add_log( 'invalid creds' );

			return null;
		}
	}

	/**
	 * Checks if the user is authenticated.
	 *
	 * @return bool true if the user is authenticated, false otherwise.
	 */
	public function is_authenticate() {

		$is_authenticated = get_option( 'is_wc2odoo_authenticated', null );
		if ( null === $is_authenticated ) {
			return false;
		}

		return $is_authenticated;
	}

	/**
	 * Custom API call.
	 *
	 * @param string $model The model name.
	 * @param string $action The action to perform.
	 * @param array  $data The data to send.
	 * @return mixed The result of the API call.
	 */
	public function custom_api_call( $model, $action, $data ) {
		$this->add_log( 'Custom api call: ' . $model . ' ' . $action . ' ' . print_r( $data, true ) );
		$ret_val = $this->get_client()->custom_api_call( $model, $action, $data );
		if ( isset( $ret_val['faultCode'] ) ) {
			$msg = 'Unable To Custom API Call - Model: ' . print_r( $model, true ) . ' - Action: ' . print_r( $action, true ) . ' - Msg: ' . print_r( $ret_val['faultString'], true );
			$this->add_log( $msg );
			$this->add_log( '** Using input: ' . print_r( $data, true ) );

			return array(
				'fail' => true,
				'msg'  => $ret_val['faultString'],
			);
		}
		return $ret_val;
	}
}
