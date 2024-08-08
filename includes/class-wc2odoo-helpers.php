<?php
/**
 * @file This is a PHP file for the WC2ODOO_Helpers class.
 * @subpackage includes
 *
 * @package wc2odoo
 */

if ( ! class_exists( 'WC2ODOO_Helpers' ) ) {
	/**
	 * Class WC2ODOO_Helpers.
	 *
	 * This class contains helper functions for the Odoo for WooCommerce plugin.
	 */
	class WC2ODOO_Helpers {

		/**
		 * The credentials for the user.
		 *
		 * @var array
		 */
		public $creds;

		/**
		 * @var array an array that stores the default mapping for fields between Odoo and WooCommerce
		 */
		public $default_mapping = array(
			'processing' => 'in_payment',
			'completed'  => 'paid',
			'pending'    => 'quote_order',
			'failed'     => 'cancelled',
			'on-hold'    => 'quote_only',
			'cancelled'  => 'cancelled',
			'refunded'   => 'refunded',
		);

		/**
		 * @var array an array to store custom mappings
		 */
		public $custom_mapping = array();

		/**
		 * FILEPATH: /home/sebas/odoo-for-woocommerce/includes/class-helpers-functions.php.
		 *
		 * This private static variable holds an instance of the class.
		 *
		 * @var object
		 */
		private static $obj;

		/**
		 * The Odoo API object.
		 *
		 * @var WC2ODOO_API
		 */
		private $odoo_api;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			$this->creds = get_option( 'woocommerce_woocommmerce_odoo_integration_settings' );

			if ( isset( $this->creds['odoo_woo_order_status'], $this->creds['odoo_payment_status'] ) ) {
				$woo_status  = ( '' !== $this->creds['odoo_woo_order_status'] ) ? $this->creds['odoo_woo_order_status'] : '';
				$odoo_states = ( '' !== $this->creds['odoo_payment_status'] ) ? $this->creds['odoo_payment_status'] : '';

				if ( '' !== $woo_status || '' !== $odoo_states ) {
					foreach ( $woo_status as $key => $value ) {
						$this->custom_mapping[ str_replace( 'wc-', '', $value ) ] = $odoo_states[ $key ];
					}
				}
			}

			require_once WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-functions.php';
			$odoo_function = new WC2ODOO_Functions();

			add_action( 'woocommerce_order_status_changed', array( $odoo_function, 'wc2odoo_order_status' ), 10, 4 );
		}

		/**
		 * Get the helper instance.
		 *
		 * @return WC2ODOO_Helpers The helper instance.
		 */
		public static function get_helper() {
			if ( ! isset( self::$obj ) ) {
				self::$obj = new WC2ODOO_Helpers();
			}

			return self::$obj;
		}

		/**
		 * Uploads the product image.
		 *
		 * @param mixed $product The product.
		 * @return string The base64 encoded image.
		 */
		public function upload_product_image( $product ) {
			$image_id   = $product->get_image_id();
			$image_path = get_attached_file( $image_id );
			$image      = $this->convert_product_image( $image_path );

			return base64_encode( $image ); // phpcs:ignore
		}

		/**
		 * Convert the product image to PNG format.
		 *
		 * @param string $original_image The path to the original image.
		 * @return string The converted image in PNG format.
		 */
		public function convert_product_image( $original_image ) {
			try {
				$image = new \Imagick( $original_image );
				$image->setImageFormat( 'png' );

				return $image->getImageBlob();
			} catch ( \Exception $err ) {
				return $err->getMessage();
			}
		}

		/**
		 * Determines if the image can be uploaded for the product.
		 *
		 * @param mixed $product The product.
		 * @return bool True if the image can be uploaded, false otherwise.
		 */
		public function can_upload_image( $product ) {
			if ( ! empty( $product->get_image_id() ) ) {
				if ( $product->get_image_id() !== get_post_meta( $product->get_id(), '_odoo_image_id', true ) ) {
					return true;
				}

				return false;
			}

			return false;
		}

		/**
		 * Determines if the invoice should be exported.
		 *
		 * @return bool True if the invoice should be exported, false otherwise.
		 */
		public function is_export_inv() {
			if ( $this->creds['odoo_export_invoice'] ) {
				return true;
			}

			return false;
		}

		/**
		 * Determines if the invoice should be marked as paid.
		 *
		 * @return bool True if the invoice should be marked as paid, false otherwise.
		 */
		public function is_inv_mark_paid() {
			if ( 'yes' === $this->creds['odoo_mark_invoice_paid'] ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns the version of Odoo.
		 *
		 * @return int The version of Odoo.
		 */
		public function odoo_version() {
			return $this->creds['odooVersion'] ?? 15;
		}

		/**
		 * Returns the Odoo states.
		 *
		 * @param mixed $value The value.
		 */
		public function odoo_states( $value ) {
			$states = array(
				'quote_only'  => array(
					'order_state'    => 'sent',
					'invoice_state'  => '',
					'payment_state'  => '',
					'invoice_status' => 'to invoice',
				),
				'quote_order' => array(
					'order_state'    => 'sale',
					'invoice_state'  => '',
					'payment_state'  => '',
					'invoice_status' => 'to invoice',
				),
				'in_payment'  => array(
					'order_state'    => 'sale',
					'invoice_state'  => 'posted',
					'payment_state'  => 'in_payment',
					'invoice_status' => 'invoiced',
				),
				'paid'        => array(
					'order_state'    => 'sale',
					'invoice_state'  => 'posted',
					'payment_state'  => 'paid',
					'invoice_status' => 'invoiced',
				),
				'cancelled'   => array(
					'order_state'    => 'cancel',
					'invoice_state'  => 'posted',
					'payment_state'  => 'cancelled',
					'invoice_status' => 'no',
				),
				'refunded'    => array(
					'order_state'   => 'sale',
					'invoice_state' => 'posted',
					'payment_state' => 'reversed',
					'invoice_status' => 'invoiced',
					'rev_invoice'   => array(
						'state'         => 'posted',
						'payment_state' => 'paid',
					),
				),
			);

			return $states[ $value ];
		}

		/**
		 * Get the Odoo state based on the status.
		 *
		 * @param string $status The status.
		 * @return mixed The state.
		 */
		public function get_state( $status ) {
			if ( 'yes' === $this->creds['odoo_status_mapping'] && count( $this->custom_mapping ) > 0 ) {
				if ( array_key_exists( $status, $this->custom_mapping ) ) {
					return $this->custom_mapping[ $status ];
				}

				return $this->default_mapping[ $status ];
			}

			return $this->default_mapping[ $status ];
		}

		/**
		 * Verifies the order items.
		 *
		 * @param int $order_id The order ID.
		 * @return bool True if the order items are verified, false otherwise.
		 */
		public function verify_order_items( $order_id ) {
			// Mejorar este codigo, ver si conviene pasar por parametros $order y $odoo_api.
			$order    = new \WC_Order( $order_id );
			$odoo_api = $this->get_odoo_api();
			$odoo_api->add_log( 'Order Items Verification Called' );
			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();

				if ( ! $product || null === $product ) {
					$error_msg = 'Invalid Product For Order ' . $order_id;
					$odoo_api->add_log( $error_msg );

					return false;
				}
				if ( ! $product || '' === $product->get_sku() ) {
					$error_msg = 'Error for order\'s product =>' . $product->get_id() . ' Msg : Invalid SKU';
					$odoo_api->add_log( $error_msg );

					return false;
				}
			}
			$odoo_api->add_log( 'Order Items Verified' );
			return true;
		}

		/**
		 * Get file extension from mime type.
		 *
		 * @param string $mime file mimetype.
		 *
		 * @return string return extension
		 */
		public function mime2ext( $mime ) {
			$mime_map = array(
				'video/3gpp2'                          => '3g2',
				'video/3gp'                            => '3gp',
				'video/3gpp'                           => '3gp',
				'application/x-compressed'             => '7zip',
				'audio/x-acc'                          => 'aac',
				'audio/ac3'                            => 'ac3',
				'application/postscript'               => 'ai',
				'audio/x-aiff'                         => 'aif',
				'audio/aiff'                           => 'aif',
				'audio/x-au'                           => 'au',
				'video/x-msvideo'                      => 'avi',
				'video/msvideo'                        => 'avi',
				'video/avi'                            => 'avi',
				'application/x-troff-msvideo'          => 'avi',
				'application/macbinary'                => 'bin',
				'application/mac-binary'               => 'bin',
				'application/x-binary'                 => 'bin',
				'application/x-macbinary'              => 'bin',
				'image/bmp'                            => 'bmp',
				'image/x-bmp'                          => 'bmp',
				'image/x-bitmap'                       => 'bmp',
				'image/x-xbitmap'                      => 'bmp',
				'image/x-win-bitmap'                   => 'bmp',
				'image/x-windows-bmp'                  => 'bmp',
				'image/ms-bmp'                         => 'bmp',
				'image/x-ms-bmp'                       => 'bmp',
				'application/bmp'                      => 'bmp',
				'application/x-bmp'                    => 'bmp',
				'application/x-win-bitmap'             => 'bmp',
				'application/cdr'                      => 'cdr',
				'application/coreldraw'                => 'cdr',
				'application/x-cdr'                    => 'cdr',
				'application/x-coreldraw'              => 'cdr',
				'image/cdr'                            => 'cdr',
				'image/x-cdr'                          => 'cdr',
				'zz-application/zz-winassoc-cdr'       => 'cdr',
				'application/mac-compactpro'           => 'cpt',
				'application/pkix-crl'                 => 'crl',
				'application/pkcs-crl'                 => 'crl',
				'application/x-x509-ca-cert'           => 'crt',
				'application/pkix-cert'                => 'crt',
				'text/css'                             => 'css',
				'text/x-comma-separated-values'        => 'csv',
				'text/comma-separated-values'          => 'csv',
				'application/vnd.msexcel'              => 'csv',
				'application/x-director'               => 'dcr',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
				'application/x-dvi'                    => 'dvi',
				'message/rfc822'                       => 'eml',
				'application/x-msdownload'             => 'exe',
				'video/x-f4v'                          => 'f4v',
				'audio/x-flac'                         => 'flac',
				'video/x-flv'                          => 'flv',
				'image/gif'                            => 'gif',
				'application/gpg-keys'                 => 'gpg',
				'application/x-gtar'                   => 'gtar',
				'application/x-gzip'                   => 'gzip',
				'application/mac-binhex40'             => 'hqx',
				'application/mac-binhex'               => 'hqx',
				'application/x-binhex40'               => 'hqx',
				'application/x-mac-binhex40'           => 'hqx',
				'text/html'                            => 'html',
				'image/x-icon'                         => 'ico',
				'image/x-ico'                          => 'ico',
				'image/vnd.microsoft.icon'             => 'ico',
				'text/calendar'                        => 'ics',
				'application/java-archive'             => 'jar',
				'application/x-java-application'       => 'jar',
				'application/x-jar'                    => 'jar',
				'image/jp2'                            => 'jp2',
				'video/mj2'                            => 'jp2',
				'image/jpx'                            => 'jp2',
				'image/jpm'                            => 'jp2',
				'image/jpeg'                           => 'jpeg',
				'image/pjpeg'                          => 'jpeg',
				'application/x-javascript'             => 'js',
				'application/json'                     => 'json',
				'text/json'                            => 'json',
				'application/vnd.google-earth.kml+xml' => 'kml',
				'application/vnd.google-earth.kmz'     => 'kmz',
				'text/x-log'                           => 'log',
				'audio/x-m4a'                          => 'm4a',
				'audio/mp4'                            => 'm4a',
				'application/vnd.mpegurl'              => 'm4u',
				'audio/midi'                           => 'mid',
				'application/vnd.mif'                  => 'mif',
				'video/quicktime'                      => 'mov',
				'video/x-sgi-movie'                    => 'movie',
				'audio/mpeg'                           => 'mp3',
				'audio/mpg'                            => 'mp3',
				'audio/mpeg3'                          => 'mp3',
				'audio/mp3'                            => 'mp3',
				'video/mp4'                            => 'mp4',
				'video/mpeg'                           => 'mpeg',
				'application/oda'                      => 'oda',
				'audio/ogg'                            => 'ogg',
				'video/ogg'                            => 'ogg',
				'application/ogg'                      => 'ogg',
				'font/otf'                             => 'otf',
				'application/x-pkcs10'                 => 'p10',
				'application/pkcs10'                   => 'p10',
				'application/x-pkcs12'                 => 'p12',
				'application/x-pkcs7-signature'        => 'p7a',
				'application/pkcs7-mime'               => 'p7c',
				'application/x-pkcs7-mime'             => 'p7c',
				'application/x-pkcs7-certreqresp'      => 'p7r',
				'application/pkcs7-signature'          => 'p7s',
				'application/pdf'                      => 'pdf',
				'application/octet-stream'             => 'pdf',
				'application/x-x509-user-cert'         => 'pem',
				'application/x-pem-file'               => 'pem',
				'application/pgp'                      => 'pgp',
				'application/x-httpd-php'              => 'php',
				'application/php'                      => 'php',
				'application/x-php'                    => 'php',
				'text/php'                             => 'php',
				'text/x-php'                           => 'php',
				'application/x-httpd-php-source'       => 'php',
				'image/png'                            => 'png',
				'image/x-png'                          => 'png',
				'application/powerpoint'               => 'ppt',
				'application/vnd.ms-powerpoint'        => 'ppt',
				'application/vnd.ms-office'            => 'ppt',
				'application/msword'                   => 'doc',
				'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
				'application/x-photoshop'              => 'psd',
				'image/vnd.adobe.photoshop'            => 'psd',
				'audio/x-realaudio'                    => 'ra',
				'audio/x-pn-realaudio'                 => 'ram',
				'application/x-rar'                    => 'rar',
				'application/rar'                      => 'rar',
				'application/x-rar-compressed'         => 'rar',
				'audio/x-pn-realaudio-plugin'          => 'rpm',
				'application/x-pkcs7'                  => 'rsa',
				'text/rtf'                             => 'rtf',
				'text/richtext'                        => 'rtx',
				'video/vnd.rn-realvideo'               => 'rv',
				'application/x-stuffit'                => 'sit',
				'application/smil'                     => 'smil',
				'text/srt'                             => 'srt',
				'image/svg+xml'                        => 'svg',
				'application/x-shockwave-flash'        => 'swf',
				'application/x-tar'                    => 'tar',
				'application/x-gzip-compressed'        => 'tgz',
				'image/tiff'                           => 'tiff',
				'font/ttf'                             => 'ttf',
				'text/plain'                           => 'txt',
				'text/x-vcard'                         => 'vcf',
				'application/videolan'                 => 'vlc',
				'text/vtt'                             => 'vtt',
				'audio/x-wav'                          => 'wav',
				'audio/wave'                           => 'wav',
				'audio/wav'                            => 'wav',
				'application/wbxml'                    => 'wbxml',
				'video/webm'                           => 'webm',
				'image/webp'                           => 'webp',
				'audio/x-ms-wma'                       => 'wma',
				'application/wmlc'                     => 'wmlc',
				'video/x-ms-wmv'                       => 'wmv',
				'video/x-ms-asf'                       => 'wmv',
				'font/woff'                            => 'woff',
				'font/woff2'                           => 'woff2',
				'application/xhtml+xml'                => 'xhtml',
				'application/excel'                    => 'xl',
				'application/msexcel'                  => 'xls',
				'application/x-msexcel'                => 'xls',
				'application/x-ms-excel'               => 'xls',
				'application/x-excel'                  => 'xls',
				'application/x-dos_ms_excel'           => 'xls',
				'application/xls'                      => 'xls',
				'application/x-xls'                    => 'xls',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
				'application/vnd.ms-excel'             => 'xlsx',
				'application/xml'                      => 'xml',
				'text/xml'                             => 'xml',
				'text/xsl'                             => 'xsl',
				'application/xspf+xml'                 => 'xspf',
				'application/x-compress'               => 'z',
				'application/x-zip'                    => 'zip',
				'application/zip'                      => 'zip',
				'application/x-zip-compressed'         => 'zip',
				'application/s-compressed'             => 'zip',
				'multipart/x-zip'                      => 'zip',
				'text/x-scriptzsh'                     => 'zsh',
			);

			return $mime_map[ $mime ] ?? false;
		}
		// }

		/**
		 * Save the image.
		 *
		 * @param array $data The image data.
		 */
		public function save_image( $data ) {
			$base64_img = base64_decode( $data['image_1024'] ); // phpcs:ignore
			$f          = finfo_open();
			$mime_type  = finfo_buffer( $f, $base64_img, FILEINFO_MIME_TYPE );
			$title      = str_replace( ' ', '_', $data['name'] ) . '_' . $data['id'];
			// Upload dir.
			$upload_dir  = wp_upload_dir();
			$upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;

			$helper = self::get_helper();

			$decoded         = $base64_img;
			$filename        = $title . '.' . $this->mime2ext( $mime_type );
			$file_type       = $mime_type;
			$hashed_filename = md5( $filename . microtime() ) . '_' . $filename;
			// Save the image in the uploads directory.
			$upload_file = file_put_contents( $upload_path . $hashed_filename, $decoded ); // phpcs:ignore

			$attachment = array(
				'post_mime_type' => $file_type,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $hashed_filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => $upload_dir['url'] . '/' . basename( $hashed_filename ),
			);

			$image_path = $upload_dir['path'] . '/' . $hashed_filename;
			$attach_id  = wp_insert_attachment( $attachment, $image_path );

			$imagenew     = get_post( $attach_id );
			$fullsizepath = get_attached_file( $imagenew->ID );

			require_once ABSPATH . 'wp-admin/includes/image.php';
			// Generate and save the attachment metas into the database.
			$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			return $attach_id;
		}

		/**
		 * Get the Odoo API instance.
		 *
		 * @return WC2ODOO_API The Odoo API instance.
		 */
		private function get_odoo_api() {
			if ( ! $this->odoo_api ) {
				$this->odoo_api = new WC2ODOO_API();
			}

			return $this->odoo_api;
		}
	}
}
