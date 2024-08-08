<?php

/**
 *  WooCommerce ODOO Integration.
 */
if (!class_exists('WC2ODOO_Integration_Settings')) {
    /**
     * wc2odoo Integration Settings class.
     *
     * Extends the WooCommerce WC_Integration class to add custom settings for the wc2odoo integration.
     */
    class WC2ODOO_Integration_Settings extends WC_Integration
    {
        public $odoo_sku_mapping = 'default_code';
        public $companyFile = 1;
        public $odooAccount = 1;
        public $odooTax = 1;
        public $shippingOdooTax = 1;
        public $invoiceJournal = 1;
        public $odooInventorySync = 1;
        public $odooDebtorAccount = 1;
        public $createProductToOdoo = 1;
        public $odoo_fiscal_position = 1;
        public $odoo_version = 13;
        public $client_url = '';
        public $client_db = '';
        public $client_username = '';
        public $client_password = '';
        public $debug = 'no';
        public $form_fields = [];

        /**
         * Init and hook in the integration.
         */
        private $client_id = '';
        private $alert_msg = 0;

        /**
         * The Odoo API object.
         *
         * @var WC2ODOO_API
         */
        private $odoo_api;

        public function __construct()
        {
            global $woocommerce;
            $this->id = 'woocommmerce_odoo_integration';
            $this->method_title = __('ODOO Integration', 'wc2odoo');
            $this->method_description = '<div>'.__('Sync WooCommerce Customer,Product and Order to Odoo ERP.', 'wc2odoo').'</br><strong>'.__('Important Note', 'wc2odoo').':</strong><p>'.__('Fill The Address Details for company. (Settings -> General Settings -> Manage Companies)', 'wc2odoo').'</p><p>'.__('Change Invoice Quantity Setting. (Settings -> Sales -> Invoicing -> Select Invoice what is ordered )', 'wc2odoo').'</p></div>';

            if ($this->is_current_url()) {
                $this->process_admin_options();
            }
            // $this->init_settings();
            // Define user set variables.
            $this->odoo_version = $this->get_option('odooVersion');
            $this->client_url = rtrim($this->get_option('client_url'), '/');
            $this->client_db = $this->get_option('client_db');
            $this->client_username = $this->get_option('client_username');
            $this->client_password = $this->get_option('client_password');
            $this->companyFile = $this->get_option('companyFile');
            $this->odooAccount = $this->get_option('odooAccount');
            $this->odooTax = $this->get_option('odooTax');
            $this->shippingOdooTax = $this->get_option('shippingOdooTax');
            $this->invoiceJournal = $this->get_option('invoiceJournal');
            $this->odooInventorySync = $this->get_option('odooInventorySync');
            $this->debug = $this->get_option('debug');
            $this->odooDebtorAccount = $this->get_option('odooDebtorAccount');
            $this->createProductToOdoo = $this->get_option('createProductToOdoo');
            $this->odoo_fiscal_position = $this->get_option('odoo_fiscal_position');
            if ('' != $this->get_option('odooSkuMapping')) {
                $this->odoo_sku_mapping = $this->get_option('odooSkuMapping');
            }

            // Actions.
            add_action('woocommerce_update_options_integration_'.$this->id, [$this, 'process_admin_options']);

            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_'.$this->id, [$this, 'sanitize_settings']);

            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_script']);

            add_action('wp_ajax_load_odoo_extra_fields', [$this, 'load_odoo_taxes_fields']);
            add_action('wp_ajax_load_fiscal_positions', [$this, 'load_fiscal_positions']);
            // add_action( 'save_post', array( $this,'do_insert_product_in_odoo' ), 10, 2);
            // add_action('create_product_cat', array($this, 'sync_category_to_odoo'), 10, 2);
            // add_action('edit_product_cat', array($this, 'sync_category_to_odoo'), 10, 2);
            add_action('woocommerce_order_refunded', [$this, 'create_odoo_refund'], 10, 2);
            add_action('update_option_woocommerce_woocommmerce_odoo_integration_settings', [$this, 'creds_updated'], 10, 3);
        }

        public function generate_custom_settings_html($form_fields, $echo = true)
        {
            // echo "<pre>";
            // print_r($form_fields);
            if (empty($form_fields)) {
                $form_fields = $this->get_form_fields();
            }

            $fields_tabs = [];
            $second_tab_fields = [];
            foreach ($form_fields as $key => $form_field) {
                $fields_tabs[$form_field['tab']][$key] = $form_field;
            }
            extract($fields_tabs);
            $wc2odoo_access_token = get_option('wc2odoo_access_token', false);
            $wc2odoo_authenticated_uid = get_option('wc2odoo_authenticated_uid', false);
            $wc2odoo_access_error = get_option('_wc2odoo_access_error', false);

            // var_dump($wc2odoo_access_token);
            // var_dump($wc2odoo_authenticated_uid);die();

            if (!$wc2odoo_access_token && !$wc2odoo_authenticated_uid) {
                if ('INVALID_CREDS' == $wc2odoo_access_error) {
                    $wc2odoo_indicator = [
                        'value' => __('Odoo credentials are not valid.', 'wc2odoo'),
                        'class' => 'opmc-error',
                        'icon' => 'dashicons-no-alt',
                    ];
                } elseif ('INVALID_HOST' == $wc2odoo_access_error) {
                    $wc2odoo_indicator = [
                        'value' => __('Odoo Host url is not valid.', 'wc2odoo'),
                        'class' => 'opmc-error',
                        'icon' => 'dashicons-no-alt',
                    ];
                } else {
                    $wc2odoo_indicator = [
                        'value' => __('Please provide valid Odoo credentials to connect.', 'wc2odoo'),
                        'class' => 'opmc-warning',
                        'icon' => 'dashicons-info-outline',
                    ];
                }
            } else {
                $wc2odoo_indicator = [
                    'value' => __('Odoo account is connected.', 'wc2odoo'),
                    'class' => 'opmc-success',
                    'icon' => 'dashicons-yes',
                ];
            }

            // ob_start();
            include_once WC2ODOO_INTEGRATION_PLUGINDIR.'/includes/template-admin-setting-page.php';
            // $output = ob_get_contents();
            // ob_end_clean();
            // return $output;
        }

        public function creds_updated($old_values, $new_values, $option_name)
        {
            global $wpdb;
            if (empty($old_values)) {
                return;
            }
            if (($old_values['client_url'] != $new_values['client_url']) || ($old_values['client_db'] != $new_values['client_db']) || ($old_values['client_username'] != $new_values['client_username'])) {
                $common_functions = new WC2ODOO_Common_Functions();
                if ($common_functions->is_authenticate()) {
                    $wpdb->query("DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE '%odoo%'");
                    $wpdb->query("DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE '%odoo%'");
                    $wpdb->query("DELETE FROM `{$wpdb->termmeta}` WHERE `meta_key` LIKE '%odoo%'");
                    $wpdb->query("DELETE FROM `{$wpdb->order_itemmeta}` WHERE `meta_key` LIKE '%_order_line_id%'");
                    $wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%odoo_shipping_product_id%'");
                }
            }
        }

        public function admin_options()
        {
            $this->generate_custom_settings_html($this->get_form_fields(), false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        public function process_admin_options()
        {
            $saved = parent::process_admin_options();
            $this->init_form_fields();

            return $saved;
        }

        /**
         * Initialize integration settings form fields.
         */
        public function init_form_fields()
        {
            // $this->fetch_file_record_by_id('taxes','account.tax');
            $debug_label = __('Enable Logging', 'wc2odoo');
            $debug_description = __('Log ODOO events, such as API requests.', 'wc2odoo');

            if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.2', '>=')) {
                $debug_label = sprintf($debug_label, ' | <a href="'.esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file='.esc_attr($this->id).'-'.sanitize_file_name(wp_hash($this->id)).'.log')).'">'.__('View Log', 'wc2odoo').'</a>');
            } else {
                $debug_label = sprintf($debug_label, ' | '.__('View Log', 'wc2odoo').': <code>woocommerce/logs/'.$this->id.'-'.sanitize_file_name(wp_hash($this->id)).'.txt</code>');
            }

            $common_functions = new WC2ODOO_Common_Functions();
            $this->form_fields = [
                'odooVersion' => [
                    'title' => __('Select Odoo Version', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Odoo Version', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        '13' => 'Odoo 13.0',
                        '14' => 'Odoo 14.0',
                        '15' => 'Odoo 15.0',
                        '16' => 'Odoo 16.0',
                    ],
                    'description' => __('Select Odoo version for your CRM', 'wc2odoo'),
                    'class' => 'select_odoo_version',
                    'tab' => 'tab1',
                ],
                'client_url' => [
                    'title' => __('Server URL', 'wc2odoo'),
                    'type' => 'url',
                    'description' => __('Insert Database server URL. You can find it in your Odoo account', 'wc2odoo'),
                    'desc' => true,
                    'default' => '',
                    'tab' => 'tab1',
                ],
                'client_db' => [
                    'title' => __('Database Name', 'wc2odoo'),
                    'type' => 'text',
                    'description' => __('Insert database name. You can find it in your Odoo account.', 'wc2odoo'),
                    'desc' => true,
                    'default' => '',
                    'tab' => 'tab1',
                ],
                'client_username' => [
                    'title' => __('Username', 'wc2odoo'),
                    'type' => 'text',
                    'description' => __('Insert username. You can find it in your Odoo account.', 'wc2odoo'),
                    'desc' => true,
                    'default' => '',
                    'tab' => 'tab1',
                ],
                'client_password' => [
                    'title' => __('Password', 'wc2odoo'),
                    'type' => 'password',
                    'description' => __('Insert password for your API access user', 'wc2odoo'),
                    'desc' => true,
                    'default' => '',
                    'tab' => 'tab1',
                ],
                'debug' => [
                    'title' => __('Debug Log', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => $debug_label,
                    'default' => 'no',
                    'description' => $debug_description,
                    'tab' => 'tab1',
                ],
                'odooSkuMapping' => [
                    'title' => __('Odoo SKU Mapping', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Odoo SKU Mapping', 'wc2odoo'),
                    'default' => 'default_code',
                    'options' => [
                        'default_code' => __('Internal Reference', 'wc2odoo'),
                        'barcode' => __('Barcode', 'wc2odoo'),
                        // 'l10n_in_hsn_code'       => 'HSN/SAC Code'
                    ],
                    'description' => __('Odoo SKU Mapping for your CRM', 'wc2odoo'),
                    'class' => 'select_odoo_version',
                    'tab' => 'tab1',
                ],
                // 'odooInventorySync'   => array(
                // 'title'       => __( 'Enable Inventory Sync', 'wc2odoo' ),
                // 'type'        => 'checkbox',
                // 'label'       => 'Enable Inventory Sync',
                // 'default'     => 'no',
                // 'description' => 'Inventory Sync',
                // 'tab' => 'tab1'
                // ),
                // 'odooCronFrequency'   => array(
                // 'title'       => __( 'Cron Frequency', 'wc2odoo' ),
                // 'type'        => 'select',
                // 'label'       => 'Cron Frequency',
                // 'default'     => '',
                // 'options'     => array(
                // 'hourly' => 'Every Hour',
                // 'twicedaily' => 'Twice A Day',
                // 'daily' => 'Once A Day',
                // ),
                // 'description' => 'Select Cron Frequency to sync product',
                // 'tab' => 'tab1'
                // ),
                // 'odooOrderRefundSync'   => array(
                // 'title'       => __( 'Enable Order Refund Sync', 'wc2odoo' ),
                // 'type'        => 'checkbox',
                // 'label'       => 'Enable Order Refund Sync',
                // 'default'     => 'no',
                // 'description' => 'Order Refund Sync',
                // 'tab' => 'tab1'
                // ),
                // 'odooOrderRefundCronFrequency'   => array(
                // 'title'       => __( 'Order Refund Cron Frequency', 'wc2odoo' ),
                // 'type'        => 'select',
                // 'label'       => 'Order Refund Cron Frequency',
                // 'default'     => '',
                // 'options'     => array(
                // 'hourly' => 'Every Hour',
                // 'twicedaily' => 'Twice A Day',
                // 'daily' => 'Once A Day',
                // ),
                // 'description' => 'Select Order Refund Cron Frequency to sync product',
                // 'tab' => 'tab1'
                // ),
                // 'createProductToOdoo'   => array(
                // 'title'       => __( 'Sync Inventory on product create and update', 'wc2odoo' ),
                // 'type'        => 'checkbox',
                // 'label'       => 'Sync Inventory on product create and update',
                // 'default'     => 'no',
                // 'description' => 'Sync Inventory on product create and update',
                // 'tab' => 'tab1'
                // ),
                'odoo_import_create_product_frequency' => [
                    'title' => __('Import Products Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Import Products Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Import Products Data Frequency', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_create_product' => [
                    'title' => __('Import Products', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Import Products', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Import Products', 'wc2odoo'),
                    'custom_attributes' => [
                        'cron_link' => true,
                        'link_title' => __('Manual Import Products', 'wc2odoo'),
                        'link_url' => '#;',
                        'class' => 'trigger_cron wc2odoo_product_import',
                    ],
                    'tab' => 'tab2',
                ],
                'odoo_import_pos_product' => [
                    'title' => __('Exclude PoS Products', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Exclude PoS Products', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Exclude PoS Products', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_update_product' => [
                    'title' => __('Update Products', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Update Products', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Update Products', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_update_stocks' => [
                    'title' => __('Synchronize Stocks', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Synchronize Stocks', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Synchronize Stocks', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_update_price' => [
                    'title' => __('Synchronize Price', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Synchronize Price', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Synchronize Price', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_create_categories_frequency' => [
                    'title' => __('Import Categories Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Import Categories Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Category Sync Frequency', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_create_categories' => [
                    'title' => __('Import Categories', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Import Categories', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Import Categories', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_create_attributes_frequency' => [
                    'title' => __('Import Attribute Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Import Attribute Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Select Attribute Cron Frequency to Sync Attribute', 'wc2odoo'),
                    'tab' => 'tab2',
                ],
                'odoo_import_create_attributes' => [
                    'title' => __('Import Attribute', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Import Attribute', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Import Attribute', 'wc2odoo'),
                    'tab' => 'tab2',
                ],

                'odoo_import_update_order_status' => [
                    'title' => __('Update Order Status', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Update Order Status', 'wc2odoo'),
                    'default' => 'no',
                    'tab' => 'tab2_2',
                ],
                'odoo_import_update_order_status_frequency' => [
                    'title' => __('Update Order Status Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Update Order Status Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'tab' => 'tab2_2',
                ],
                'odoo_import_customer' => [
                    'title' => __('Import/Update Customer', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Import/Update Customer', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Import/Update Customer', 'wc2odoo'),
                    'tab' => 'tab2_2',
                ],
                'odoo_import_customer_frequency' => [
                    'title' => __('Import/Update Customer Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Import/Update Customer Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'tab' => 'tab2_2',
                ],
                'odoo_import_order' => [
                    'title' => __('Import Order', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Import Order', 'wc2odoo'),
                    'default' => '',
                    'tab' => 'tab2_2',
                ],
                'odoo_import_order_frequency' => [
                    'title' => __('Import Order Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Import Order Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'tab' => 'tab2_2',
                ],
                'odoo_import_order_from_date' => [
                    'type' => 'text',
                    'placeholder' => __('From', 'wc2odoo'),
                    'default' => '',
                    'tab' => 'tab2_2',
                ],
                'odoo_import_order_to_date' => [
                    'type' => 'text',
                    'placeholder' => __('To', 'wc2odoo'),
                    'default' => '',
                    'tab' => 'tab2_2',
                ],
                'odoo_import_refund_order' => [
                    'title' => __('Import Refund Order', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Import Refund Order', 'wc2odoo'),
                    'default' => '',
                    'tab' => 'tab2_2',
                ],
                'odoo_import_refund_order_frequency' => [
                    'title' => __('Import Refund Order Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Import Refund Order Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'tab' => 'tab2_2',
                ],
                'odoo_import_coupon_frequency' => [
                    'title' => __('Import Coupon Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Import Coupon Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Select Coupon Cron Frequency to sync Coupon', 'wc2odoo'),
                    'tab' => 'tab2_3',
                ],
                'odoo_import_coupon' => [
                    'title' => __('Import Coupon', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Import Coupon', 'wc2odoo'),
                    'default' => '',
                    'description' => __('Select Coupon Cron to sync Coupon', 'wc2odoo'),
                    'tab' => 'tab2_3',
                ],
                'odoo_import_coupon_update' => [
                    'title' => __('Update Coupon', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Update Coupon', 'wc2odoo'),
                    'default' => '',
                    'description' => __('Select Coupon Cron to sync Coupon', 'wc2odoo'),
                    'tab' => 'tab2_3',
                ],
                'odoo_export_create_product_frequency' => [
                    'title' => __('Export Products Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Export Products Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Export Products Frequency', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_exclude_product_category' => [
                    'title' => __('Exclude Product by Categories', 'wc2odoo'),
                    'type' => 'multiselect',
                    'label' => __('Exclude Products by Categories', 'wc2odoo'),
                    'default' => '',
                    'options' => $this->get_categories(),
                    'description' => __('Exclude products by categories not to export', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_create_product' => [
                    'title' => __('Export Products', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export Products', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Export Products', 'wc2odoo'),
                    'custom_attributes' => [
                        'cron_link' => true,
                        'link_title' => __('Manual Export Products', 'wc2odoo'),
                        'link_url' => '#;',
                        'class' => 'trigger_cron wc2odoo_product_export',
                    ],
                    'tab' => 'tab3',
                ],
                'odoo_export_update_product' => [
                    'title' => __('Update Products', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Update Products', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Update Products', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_update_stocks' => [
                    'title' => __('Synchronize Stocks', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Synchronize Stocks', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Synchronize Stocks', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_update_price' => [
                    'title' => __('Synchronize Price', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Synchronize Price', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Synchronize Price', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_create_categories_frequency' => [
                    'title' => __('Export Categories Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Export Categories Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Select Category Cron Frequency to sync Category', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_create_categories' => [
                    'title' => __('Export Categories', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export Categories', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Export Categories', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_create_attributes_frequency' => [
                    'title' => __('Export Attribute Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Export Attribute Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Select Attribute Cron Frequency to sync Attribute', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_create_attributes' => [
                    'title' => __('Export Attribute', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export Attribute', 'wc2odoo'),
                    'default' => 'no',
                    'description' => __('Export Attribute', 'wc2odoo'),
                    'tab' => 'tab3',
                ],
                'odoo_export_update_order_status' => [
                    'title' => __('Update Order Status', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Update Order Status', 'wc2odoo'),
                    'default' => 'no',
                    'tab' => 'tab3_2',
                ],
                'odoo_export_update_order_status_frequency' => [
                    'title' => __('Update Order Status Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Update Order Status Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'tab' => 'tab3_2',
                ],
                'odoo_export_order_on_checkout' => [
                    'title' => __('Export order On Checkout', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export order On Checkout', 'wc2odoo'),
                    'default' => 'yes',
                    'tab' => 'tab3_2',
                ],
                'odoo_export_invoice' => [
                    'title' => __('Export Invoice', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export Invoice', 'wc2odoo'),
                    'default' => 'yes',
                    'tab' => 'tab3_2',
                ],
                'odoo_mark_invoice_paid' => [
                    'title' => __('Mark Invoice Paid', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Mark Invoice Paid', 'wc2odoo'),
                    'default' => 'yes',
                    'description' => __('Export Invoice option should be enabled for this.', 'wc2odoo'),
                    'tab' => 'tab3_2',
                ],
                'odoo_export_refund_order' => [
                    'title' => __('Export Refund Order', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export Refund Order', 'wc2odoo'),
                    'default' => 'yes',
                    'description' => __('Export Invoice option should be enabled for this.', 'wc2odoo'),
                    'tab' => 'tab3_2',
                ],

                'odoo_export_customer' => [
                    'title' => __('Export/Update Customer', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export/Update Customer', 'wc2odoo'),
                    'default' => 'no',
                    'tab' => 'tab3_2',
                ],
                'odoo_export_customer_frequency' => [
                    'title' => __('Export/Update Customer Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Export/Update Customer Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'tab' => 'tab3_2',
                ],
                'odoo_export_order' => [
                    'title' => __('Export Order', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export Order', 'wc2odoo'),
                    'default' => '',
                    'tab' => 'tab3_2',
                ],

                'odoo_status_mapping' => [
                    'title' => __('Status Mapping', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Status Mapping', 'wc2odoo'),
                    'default' => 'no',
                    'tab' => 'tab3_2',
                ],
                'odoo_woo_order_status' => [
                    'title' => __('Order Status Mapping', 'wc2odoo'),
                    'type' => 'multiselect',
                    'label' => __('Order Status Mapping', 'wc2odoo'),
                    'default' => '',
                    'options' => wc_get_order_statuses(),
                    'tab' => 'tab3_2',
                ],
                'odoo_payment_status' => [
                    'title' => __('Order Status Mapping', 'wc2odoo'),
                    'type' => 'multiselect',
                    'label' => __('Order Status Mapping', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'quote_only' => __('Quote Only', 'wc2odoo'),
                        'quote_order' => __('Quote and Sales Order', 'wc2odoo'),
                        'in_payment' => __('In Payment Invoice', 'wc2odoo'),
                        'paid' => __('Paid Invoice', 'wc2odoo'),
                        'cancelled' => __('Cancelled', 'wc2odoo'),
                    ],
                    'tab' => 'tab3_2',
                ],
                'odoo_export_order_frequency' => [
                    'title' => __('Export Order Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Export Order Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'tab' => 'tab3_2',
                ],
                'odoo_export_order_from_date' => [
                    'type' => 'text',
                    'placeholder' => __('From', 'wc2odoo'),
                    'default' => '',
                    'tab' => 'tab3_2',
                ],
                'odoo_export_order_to_date' => [
                    'type' => 'text',
                    'placeholder' => __('To', 'wc2odoo'),
                    'default' => '',
                    'tab' => 'tab3_2',
                ],
                'odoo_export_coupon_frequency' => [
                    'title' => __('Export Coupon Frequency', 'wc2odoo'),
                    'type' => 'select',
                    'label' => __('Export Coupon Frequency', 'wc2odoo'),
                    'default' => '',
                    'options' => [
                        'hourly' => __('Every Hour', 'wc2odoo'),
                        'twicedaily' => __('Twice A Day', 'wc2odoo'),
                        'daily' => __('Once A Day', 'wc2odoo'),
                    ],
                    'description' => __('Select Coupon Cron Frequency to sync Coupon', 'wc2odoo'),
                    'tab' => 'tab3_3',
                ],
                'odoo_export_coupon' => [
                    'title' => __('Export Coupon', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Export Coupon', 'wc2odoo'),
                    'default' => '',
                    'description' => __('Select Coupon to sync Coupon', 'wc2odoo'),
                    'tab' => 'tab3_3',
                ],
                'odoo_export_coupon_update' => [
                    'title' => __('Update Coupon', 'wc2odoo'),
                    'type' => 'checkbox',
                    'label' => __('Update Coupon', 'wc2odoo'),
                    'default' => '',
                    'description' => __('Update Coupon to Odoo', 'wc2odoo'),
                    'tab' => 'tab3_3',
                ],
            ];
            if ($common_functions->is_authenticate()) {
                $company_id = ('' != $this->get_option('companyFile')) ? $this->get_option('companyFile') : 1;

                $companyList = $this->getcompany_files();
                $invoiceJouranlList = $this->get_all_live_sale_journal($company_id);
                $taxList = $this->get_all_live_taxes($company_id);
                $fiscalPositions = $this->get_fiscal_positions($company_id);
                // $gst_fields                   = $this->check_for_gst_treatment();

                $is_wc2odoo_update_configs = get_option('_wc2odoo_update_configs');
                if ($is_wc2odoo_update_configs) {
                    $this->companyFile = '';
                    $this->odooAccount = '';
                    $this->odooTax = '';
                    $this->shippingOdooTax = '';
                    $this->invoiceJournal = '';
                    $this->odoo_fiscal_position = '';
                    update_option('_wc2odoo_update_configs', 0);
                }
                // pr($companyList);die();
                $extraFields = [
                    'companyFile' => [
                        'title' => __('Select Company', 'wc2odoo'),
                        'type' => 'select',
                        'lable' => __('Select Company', 'wc2odoo'),
                        'default' => '',
                        'class' => 'companyFiles',
                        'options' => $companyList,
                        'description' => __('Select Company for your CRM', 'wc2odoo'),
                        'tab' => 'tab1',
                    ],
                    'invoiceJournal' => [
                        'title' => __('Select Sale Invoice Journal', 'wc2odoo'),
                        'type' => 'select',
                        'label' => __('Select Sale Invoice Journal', 'wc2odoo'),
                        'default' => '',
                        'class' => 'dependentOptions',
                        'options' => $invoiceJouranlList,
                        'description' => __('Select Sale Journal for Odoo invoices', 'wc2odoo'),
                        'tab' => 'tab1',
                    ],
                    'odooTax' => [
                        'title' => __('Select Tax Type', 'wc2odoo'),
                        'type' => 'select',
                        'label' => __('Select Tax Type', 'wc2odoo'),
                        'default' => '',
                        'class' => 'dependentOptions',
                        'options' => $taxList,
                        'description' => __('Select tax term for Odoo invoices', 'wc2odoo'),
                        'tab' => 'tab1',
                    ],
                    'shippingOdooTax' => [
                        'title' => __('Select Shipping Tax Type', 'wc2odoo'),
                        'type' => 'select',
                        'label' => __('Select Shipping Tax Type', 'wc2odoo'),
                        'default' => '',
                        'class' => 'dependentOptions',
                        'options' => $taxList,
                        'description' => __('Select tax term for Odoo invoices', 'wc2odoo'),
                        'tab' => 'tab1',
                    ],
                    'odoo_fiscal_position' => [
                        'title' => __('Use Fiscal Positions', 'wc2odoo'),
                        'type' => 'checkbox',
                        'label' => __('Use Fiscal Positions', 'wc2odoo'),
                        'default' => 'no',
                        'tab' => 'tab1',
                    ],
                    'odoo_fiscal_position_selected' => [
                        'title' => __('Select Fiscal Position', 'wc2odoo'),
                        'type' => 'select',
                        'label' => __('Select Fiscal Position', 'wc2odoo'),
                        'default' => '',
                        'class' => 'dependentOptions',
                        'options' => $fiscalPositions,
                        'description' => __('Select Fiscal Position', 'wc2odoo'),
                        'tab' => 'tab1',
                    ],
                ];

                foreach ($extraFields as $key => $extraField) {
                    $this->form_fields[$key] = $extraField;
                }
                /*
                if ( count( $gst_fields ) > 0 ) {
                    $this->form_fields['gst_treatment'] = array(
                        'title'       => __( 'Select GST Treatment', 'wc2odoo' ),
                        'type'        => 'select',
                        'label'       => __( 'Select GST Treatment', 'wc2odoo' ),
                        'default'     => '',
                        'options'     => $gst_fields,
                        'description' => __( 'Select GST Treatmnt for Odoo invoices', 'wc2odoo' ),
                        'tab'         => 'tab1',
                    );
                }
                */
            }
        }

        /**
         * Sanitize our settings.
         *
         * @see process_admin_options()
         *
         * @param mixed $settings
         */
        public function sanitize_settings($settings)
        {
            if (
                $settings['odooVersion'] != $this->odoo_version
                || rtrim($settings['client_url'], '/') != $this->client_url
                || $settings['client_db'] != $this->client_db
                || $settings['client_username'] != $this->client_username
                || $settings['client_password'] != $this->client_password
            ) {
                delete_option('wc2odoo_access_token');
                update_option('is_wc2odoo_settings_changed', 1);
            } else {
                update_option('is_wc2odoo_settings_changed', 0);
            }

            return $settings;
        }

        /**
         * Generate a URL to our Odoo settings screen.
         *
         * @since  1.3.4
         *
         * @return string generated URL
         */
        public function get_settings_url()
        {
            return add_query_arg(
                [
                    'page' => 'wc-settings',
                    'tab' => 'integration',
                    'section' => $this->id,
                ],
                admin_url('admin.php')
            );
        }

        /**
         * Enqueue admin scripts.
         */
        public function enqueue_admin_script()
        {
            if ((isset($_GET['tab']) && 'integration' == $_GET['tab']) && (isset($_GET['section']) && 'woocommmerce_odoo_integration' == $_GET['section'])) {
                wp_enqueue_script('admin-settings', WC2ODOO_INTEGRATION_PLUGINURL.'assets/build/app.js', [], WC2ODOO_INTEGRATION_INIT_VERSION, true);
                wp_set_script_translations('admin-settings', 'wc2odoo', WC2ODOO_INTEGRATION_PLUGINDIR.'languages');

                $creds = get_option('woocommerce_woocommmerce_odoo_integration_settings');
                $common_functions = new WC2ODOO_Common_Functions();
                wp_localize_script(
                    'admin-settings',
                    'odoo_admin',
                    [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'odoo_url' => isset($creds['client_url']) ? rtrim($creds['client_url'], '/') : '',
                        'odoo_db' => $creds['client_db'] ?? '',
                        'odoo_username' => $creds['client_username'] ?? '',
                        'odoo_password' => $creds['client_password'] ?? '',
                        'ajax_nonce' => wp_create_nonce('odoo_security'),
                        'is_creds_defined' => ($common_functions->is_authenticate()) ? 1 : 0,
                    ]
                );
            }
        }

        /**
         * Read data from the localfile to save the request time.
         *
         * @param string $file filename
         *
         * @return array|bool return data may be array or boolean
         */
        public function read_local_file($file)
        {
            $data = file_get_contents(WC2ODOO_INTEGRATION_PLUGINDIR.'/includes/'.$file.'.json');
            if (!empty($data)) {
                $array_data = json_decode($data, 1);
                if (JSON_ERROR_NONE === json_last_error()) {
                    return $array_data;
                }

                return false;
            }

            return false;
        }

        /**
         * Create file and save data to the local file.
         *
         * @param string $file            filename
         * @param mixed  $odoo_model_name
         * @param mixed  $conditions
         * @param mixed  $fields
         *
         * @return array $data response
         */
        public function create_and_read_local_file($odoo_model_name, $conditions = [], $fields = [], $file = [])
        {
            $odoo_api = $this->get_odoo_api();

            $data = $odoo_api->read_all($odoo_model_name, $conditions, $fields);
            // $odoo_api->add_log('response : '. print_r($data->data->items, true));
            if (!isset($data['faultCode'])) {
                $fp = fopen(WC2ODOO_INTEGRATION_PLUGINDIR.'/includes/'.$file.'.json', 'w');
                fwrite($fp, json_encode($data));
                fclose($fp);
            }

            return json_decode(json_encode($data), true);
        }

        public function load_odoo_taxes_fields()
        {
            if (wp_verify_nonce('test', 'wc_none')) {
                return true;
            }
            $company_id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : 1;
            $options = [];
            $options['journal'] = $this->get_all_live_sale_journal($company_id);
            $options['taxes'] = $this->get_all_live_taxes($company_id);
            echo json_encode($options);

            exit;
        }

        public function load_fiscal_positions()
        {
            if (wp_verify_nonce('test', 'wc_none')) {
                return true;
            }
            $company_id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : 1;
            $options = [];
            $options['fiscal_position'] = $this->get_fiscal_positions($company_id, true);
            echo json_encode($options);

            exit;
        }

        public function get_all_live_sale_journal($company_id)
        {
            $company_id = ('' == $company_id) ? 1 : $company_id;
            // $odoo_api                   = $this->get_odoo_api();
            $wc2odoo_accounts = get_option('_wc2odoo_journals');
            $is_wc2odoo_update_configs = get_option('_wc2odoo_update_configs');
            // xdebug_break();
            if ('' != $wc2odoo_accounts && !$is_wc2odoo_update_configs) {
                return $wc2odoo_accounts;
            }
            $newaccounts = ['' => __('-- Select Invoice Journal --', 'wc2odoo')];
            $conditions = [
                [
                    'company_id',
                    '=',
                    (int) $company_id,
                ],
                [
                    'type',
                    '=',
                    'sale',
                ],
            ];

            $accounts = $this->create_and_read_local_file('account.journal', $conditions, [], 'accounts');
            // $odoo_api->add_log('Accounts :' . print_r($accounts, true));
            if (is_array($accounts)) {
                foreach ((array) $accounts as $key => $account) {
                    $newaccounts[$account['id']] = $account['name'];
                }
                update_option('_wc2odoo_journals', $newaccounts);
            }

            return $newaccounts;
        }

        public function get_all_live_taxes($company_id)
        {
            $company_id = ('' == $company_id) ? 1 : $company_id;
            $wc2odoo_taxes = get_option('_wc2odoo_taxes');
            $is_wc2odoo_update_configs = get_option('_wc2odoo_update_configs');
            if ('' != $wc2odoo_taxes && !$is_wc2odoo_update_configs) {
                return $wc2odoo_taxes;
            }
            $newtaxes = ['' => __('-- Select Tax Type --', 'wc2odoo')];
            $conditions = [
                [
                    'company_id',
                    '=',
                    (int) $company_id,
                ],
            ];
            $taxes = $this->create_and_read_local_file('account.tax', $conditions, [], 'taxes');
            // $odoo_api->add_log('Taxes :' . print_r($taxes, true));
            if (is_array($taxes)) {
                foreach ($taxes as $key => $tax) {
                    if ('sale' == $tax['type_tax_use']) {
                        $newtaxes[$tax['id']] = $tax['name'];
                    }
                }
                update_option('_wc2odoo_taxes', $newtaxes);
            }

            return $newtaxes;
        }

        public function get_fiscal_positions($company_id, $ajax_call = false)
        {
            $company_id = ('' == $company_id) ? 1 : $company_id;
            $newtaxes = ['' => __('-- Select Fiscal Position --', 'wc2odoo')];
            $odoo_api = $this->get_odoo_api();
            $odoo_fp_enabled = get_option('odoo_fiscal_position', 'no');
            $odoo_api->add_log('fiscal postions enabled : '.print_r($odoo_fp_enabled, 1));
            if ('yes' == $odoo_fp_enabled || $ajax_call) {
                $wc2odoo_taxes = get_option('_wc2odoo_fiscal_positions');
                $is_wc2odoo_update_configs = get_option('_wc2odoo_update_configs');
                if ('' != $wc2odoo_taxes && !$is_wc2odoo_update_configs) {
                    return $wc2odoo_taxes;
                }

                $conditions = [
                    [
                        'company_id',
                        '=',
                        (int) $company_id,
                    ],
                ];

                $taxes = $this->create_and_read_local_file('account.fiscal.position', $conditions, [], 'fiscal');

                if (is_array($taxes)) {
                    foreach ($taxes as $key => $tax) {
                        if (1 == $tax['active']) {
                            $newtaxes[$tax['id']] = $tax['name'];
                        }
                    }
                    update_option('_wc2odoo_fiscal_positions', $newtaxes);
                }
            }

            return $newtaxes;
        }

        public function check_for_gst_treatment()
        {
            $gst_fields = [];
            $odoo_api = $this->get_odoo_api();

            //xdebug_break();
            $fields = $odoo_api->read_fields('sale.order', ['l10n_in_gst_treatment']);
            // $odoo_api->add_log('gst treatment : '. print_r($fields, true));
            if ((null != $fields) && (0 < count($fields))) {
                if (isset($fields['l10n_in_gst_treatment']['selection']) && is_array($fields['l10n_in_gst_treatment'])) {
                    foreach ($fields['l10n_in_gst_treatment']['selection'] as $key => $selection) {
                        $gst_fields[$selection[0]] = $selection[1];
                    }
                }
            }

            return $gst_fields;
        }

        public function getcompany_files()
        {
            $odoo_api = $this->get_odoo_api();
            $companies_files = get_option('_wc2odoo_company');
            $is_wc2odoo_update_configs = get_option('_wc2odoo_update_configs');

            if ('' != $companies_files && !$is_wc2odoo_update_configs) {
                // $odoo_api->add_log('Existing companies : ' . print_r($companies_files, true));
                return $companies_files;
            }

            $company_files = ['' => __('-- Select Company --', 'wc2odoo')];

            $fields = $odoo_api->search_records('res.company', [], ['id', 'name']);
            $odoo_api->add_log('companies response : '.print_r($fields[0], true));

            if (is_array($fields)) {
                foreach ($fields as $field) {
                    $company_files[$field['id']] = $field['name'];
                }
                update_option('_wc2odoo_company', $company_files);
                // $odoo_api->add_log('companies : ' . print_r($company_files, true));
            }

            // $odoo_api->add_log('new companies : ' . print_r($company_files, true));
            return $company_files;
        }

        public function get_categories()
        {
            global $wpdb;

            $product_cats = ['' => __('-- Select Categories --', 'wc2odoo')];

            $all_categories = $wpdb->get_results(
                "SELECT *
				FROM
				{$wpdb->terms}
				LEFT JOIN
				{$wpdb->term_taxonomy} ON
				{$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
				WHERE
				{$wpdb->term_taxonomy}.taxonomy = 'product_cat' order by {$wpdb->terms}.name ASC"
            );
            // pr($all_categories);
            foreach ($all_categories as $cat) {
                if (0 == $cat->parent) {
                    $product_cats[$cat->term_id] = $cat->name;
                }
            }

            return $product_cats;
        }

        public function do_insert_product_in_odoo($post_id)
        {
            $post_status = get_post_status($post_id);

            if ('auto-draft' == $post_status) {
                return;
            }
            // Autosave, do nothing
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }
            // Check user permissions
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
            if ('product' == get_post_type($post_id)) {
                $update = get_post_meta($post_id, '_odoo_id', true);
                if ($update) {
                    $this->sync_to_odoo($post_id, (int) $update);
                } else {
                    $this->sync_to_odoo($post_id);
                }
            }
        }

        public function sync_to_odoo($post_id, $odoo_product_id = 0)
        {
            $helper = WC2ODOO_Helpers::get_helper();
            $odoo_api = $this->get_odoo_api();
            $creds = get_option('woocommerce_woocommmerce_odoo_integration_settings');

            //xdebug_break();

            $product = wc_get_product($post_id);
            if ('' == $product->get_sku()) {
                $error_msg = 'Error for Search product =>'.$product->get_id().' Msg : Invalid SKU';
                $odoo_api->add_log($error_msg);

                return false;
            }
            $data = [
                'name' => $product->get_name(),
                'sale_ok' => true,
                'type' => 'product',
                'company_id' => ((isset($creds['companyFile']) && '' != $creds['companyFile']) ? $creds['companyFile'] : 1),
                $this->odoo_sku_mapping => $product->get_sku(),
                'description_sale' => $product->get_description(),
                'list_price' => $product->get_price(),
                'categ_id' => $this->get_category_id($product),
            ];
            // 'description' => $product->get_description(),
            $product_qty = number_format((float) $product->get_stock_quantity(), 2, '.', '');

            if ($helper->can_upload_image($product)) {
                $data['image_1920'] = $helper->upload_product_image($product->get_id());
            }

            if ($odoo_product_id > 0) {
                $data['id'] = $odoo_product_id;
                $response = $odoo_api->update_record('product.template', [$odoo_product_id], $data);
                $this->update_product_quantity($odoo_product_id, $product_qty);
                if (isset($response['faultCode'])) {
                    $error_msg = 'Error for Updating Product for Id  =>'.$product->get_id().'Msg : '.print_r($response['faultString'], true);
                    $odoo_api->add_log($error_msg);
                } else {
                    update_post_meta($product->get_id(), '_odoo_id', $odoo_product_id);
                    if ($product->get_image_id()) {
                        update_post_meta($product->get_id(), '_odoo_image_id', $product->get_image_id());
                    }
                }
            } else {
                $response = $odoo_api->create_record('product.template', $data);

                if (isset($response['faultCode'])) {
                    $error_msg = 'Error for Creating Product for Id  =>'.$product->get_id().'Msg : '.print_r($response['faultString'], true);
                    $odoo_api->add_log($error_msg);
                } else {
                    // $response = $odoo_api->create_record('product.product', $data);
                    $this->update_product_quantity($response, $product_qty);

                    update_post_meta($product->get_id(), '_odoo_id', $response);
                    if ($product->get_image_id()) {
                        update_post_meta($product->get_id(), '_odoo_image_id', $product->get_image_id());
                    }
                }
            }
        }

        public function sync_category_to_odoo($term_id, $taxonomy_term_id)
        {
            $odoo_api = $this->get_odoo_api();
            $term = get_term($term_id);
            $data = [
                'name' => $term->taxonomy,
            ];

            $odoo_term_id = get_term_meta($term_id, '_odoo_term_id', true);
            if ($odoo_term_id) {
                $data['id'] = $odoo_term_id;
                $response = $odoo_api->update_record('product.category', [$odoo_term_id], $data);
                if (isset($response['faultCode'])) {
                    $error_msg = 'Error for Updating category for Id  =>'.$term_id.'Msg : '.print_r($response['faultString'], true);
                    $odoo_api->add_log($error_msg);
                } else {
                    update_post_meta($term_id, '_odoo_id', $odoo_term_id);
                }
            } else {
                $response = $odoo_api->create_record('product.category', $data);

                if (isset($response['faultCode'])) {
                    $error_msg = 'Error for Creating category for Id  =>'.$term_id.'Msg : '.print_r($response['faultString'], true);
                    $odoo_api->add_log($error_msg);
                } else {
                    update_term_meta($term_id, '_odoo_term_id', $response);
                }
            }
        }

        public function get_category_id($product)
        {
            // $product   = wc_get_product( $product_id );
            $terms = wp_get_post_terms($product->id, 'product_cat', ['fields' => 'ids']);
            if (count($terms) > 0) {
                $cat_id = (int) $terms[0];

                $odoo_term_id = get_term_meta($cat_id, '_odoo_term_id', true);

                if ($odoo_term_id) {
                    return $odoo_term_id;
                }
                $odoo_api = $this->get_odoo_api();
                $term = get_term($cat_id);
                $data = [
                    'name' => $term->name,
                ];
                $odoo_term_id = $odoo_api->search_record('product.category', [['name', '=', $term->name]]);
                if ($odoo_term_id) {
                    /*
                     $error_msg = 'Error for Search Category =>' . $cat_id . 'Function search_record gives: ' . print_r($odoo_term_id, true);
                    $odoo_api->add_log($error_msg);
                    return false; */

                    return $odoo_term_id;
                }
                $error_msg = 'Error for Search Category =>'.$cat_id.'Function search_record gives: '.print_r($odoo_term_id, true);
                $odoo_api->add_log($error_msg);
                $response = $odoo_api->create_record('product.category', $data);

                if (!is_numeric($response)) {
                    $error_msg = 'Error for Creating category for Id  =>'.$cat_id.' Response : '.print_r($response, true);
                    $odoo_api->add_log($error_msg);
                } else {
                    update_term_meta($cat_id, '_odoo_term_id', $response);

                    return $response;
                }
            } else {
                return 1; // Sin categoria
            }
        }

        public function update_product_quantity($product_id, $quantity)
        {
            $creds = get_option('woocommerce_woocommmerce_odoo_integration_settings');
            if (isset($creds['createProductToOdoo']) && 'yes' == $creds['createProductToOdoo']) {
                $odoo_api = $this->get_odoo_api();

                $quantity_id = $odoo_api->create_record(
                    'stock.change.product.qty',
                    [
                        'new_quantity' => $quantity,
                        'product_tmpl_id' => $product_id,
                        'product_id' => $product_id,
                    ]
                );
                // $odoo_api->add_log('qty Id : ' . print_r($quantity_id, true));
                if (is_numeric($quantity_id)) {
                    $odoo_api->custom_api_call('stock.change.product.qty', 'change_product_qty', [$quantity_id]);
                }
            }
        }

        public function generate_checkbox_html($key, $data)
        {
            $field = $this->plugin_id.$this->id.'_'.$key;
            $value = $this->get_option($key);
            $defaults = [
                'class' => 'button-secondary',
                'css' => '',
                'custom_attributes' => [],
                'desc_tip' => false,
                'description' => '',
                'title' => '',
                'disable' => true,
            ];

            $allowed_html = [
                'a' => [
                    'href' => [],
                    'title' => [],
                ],
                'br' => [],
                'em' => [],
                'strong' => [],
            ];
            $data = wp_parse_args($data, $defaults);

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field); ?>"><?php echo esc_html(wp_kses_post($data['title'])); ?></label>
                    <?php echo esc_html($this->get_tooltip_html($data)); ?>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo esc_html(wp_kses_post($data['title'])); ?></span></legend>
                        <label class="switch">
                    <input type="checkbox" name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" value="<?php echo esc_html($value); ?>" <?php echo 'yes' == $value ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                  </label>
                    <?php if (!empty($data['custom_attributes']) && $data['custom_attributes']['cron_link']) { ?>
                        <a href="<?php echo esc_attr($data['custom_attributes']['link_url']); ?>" class="button button-secondary cron-button <?php echo esc_attr($data['custom_attributes']['class']); ?>"><?php echo esc_html($data['custom_attributes']['link_title']); ?></a>
                    <?php } ?>
                    </fieldset>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        public function is_current_url()
        {
            // $current_url = null;
            // if ( isset( $_SERVER['HTTP_HOST'] ) && isset($_SERVER['REQUEST_URI']) ) {
            // $current_url = is_ssl() ? 'https://' : 'http://';
            // $current_url .= esc_url_raw(wp_unslash( $_SERVER['HTTP_HOST'] )); // WPCS: sanitization okay
            // $current_url .= esc_url_raw(wp_unslash( $_SERVER['REQUEST_URI'] )); // WPCS: sanitization okay
            // }
            // return $current_url;
            if (isset($_GET['tab']) && 'integration' == $_GET['tab'] && isset($_GET['section']) && $this->id == $_GET['section']) {
                return true;
            }

            return false;
        }

        public function allowed_html()
        {
            $fields = wp_kses_allowed_html('post');
            $allowed_atts = [
                'align' => [],
                'class' => [],
                'type' => [],
                'id' => [],
                'dir' => [],
                'lang' => [],
                'style' => [],
                'xml:lang' => [],
                'src' => [],
                'alt' => [],
                'href' => [],
                'rel' => [],
                'rev' => [],
                'target' => [],
                'novalidate' => [],
                'value' => [],
                'name' => [],
                'tabindex' => [],
                'action' => [],
                'method' => [],
                'for' => [],
                'width' => [],
                'height' => [],
                'data' => [],
                'title' => [],
                'label' => [],
                'checked' => true,
                'select' => [],
                'option' => [],
                'selected' => [],
                'multiple' => [],
            ];
            $fields['form'] = [
                'action' => true,
                'accept' => true,
                'accept-charset' => true,
                'enctype' => true,
                'method' => [],
                'name' => true,
                'target' => true,
                'class' => true,
            ];
            $fields['input'] = $allowed_atts;
            $fields['select'] = $allowed_atts;
            $fields['option'] = $allowed_atts;
            $fields['optgroup'] = $allowed_atts;

            $fields['script'] = $allowed_atts;
            $fields['style'] = $allowed_atts;

            return $fields;
        }

        public function create_odoo_refund($order_id, $refund_id)
        {
            if (wp_verify_nonce('test', 'wc_none')) {
                return true;
            }
            if ('yes' == $this->get_option('odoo_export_refund_order') && 'yes' == $this->get_option('odoo_export_invoice')) {
                if (isset($_POST['action']) && 'woocommerce_refund_line_items' == $_POST['action']) { // phpcs:ignore
                    include WC2ODOO_INTEGRATION_PLUGINDIR.'/includes/class-wc2odoo-functions.php';
                    $function = new WC2ODOO_Functions();
                    $function->create_odoo_refund($order_id, $refund_id);
                }
            }
        }

        private function get_odoo_api()
        {
            if (!$this->odoo_api) {
                $this->odoo_api = new WC2ODOO_API();
            }

            return $this->odoo_api;
        }
    }
}
