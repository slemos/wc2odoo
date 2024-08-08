<?php
/**
 * @file This is a PHP file for the Odoo_Client class.
 * @subpackage includes
 *
 * @package wc2odoo
 */

/**
 * Extends the Client class to add custom API calls.
 */
class Odoo_Client extends \OdooClient\Client {

	/**
	 * Custom API call.
	 *
	 * @param string $model Model to call.
	 * @param string $action Action to perform.
	 * @param array  $data Data to send.
	 *
	 * @return array Response from the API.
	 */
	public function custom_api_call( $model, $action, $data = array() ) {
		// phpcs:ignore
		return $this->getClient( 'object' )->execute_kw( // phpcs:ignore
			$this->database,
			$this->uid(),
			$this->password,
			$model,
			$action,
			$data
		);
	}

	/**
	 * Get the user ID.
	 *
	 * @return int The user ID.
	 */
	public function get_uid() {
		return $this->uid();
	}

	/**
	 * Read fields from a model with specified attributes.
	 *
	 * @param string $model The model to read fields from.
	 * @param array  $fields The fields to read.
	 * @param array  $attrs The attributes of the fields.
	 *
	 * @return array The response from the API.
	 */
	public function read_fields( $model, $fields, $attrs ) {
		try {
			// phpcs:ignore
			$response = $this->getClient( 'object' )->execute_kw( // phpcs:ignore
				$this->database,
				$this->uid(),
				$this->password,
				$model,
				'fields_get',
				$fields,
				array( 'attributes' => $attrs )
			);
			return $response;
		} catch ( \Exception $e ) {
			$response = array(
				'faultCode'   => $e->getCode(),
				'faultString' => $e->getMessage(),
			);

			return $response;
		}
	}
}
