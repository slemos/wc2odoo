<?php
/**
 * Plugin Name:       WC2odoo
 * Plugin URI:        https://github.com/slemos
 * Description:       Plugin for integrating WooCommerce with Odoo.
 * Author:            Sebastian Lemos
 * Author URI:        https://github.com/slemos
 * Text Domain:       wc2odoo
 * Domain Path:       /languages
 * Version:           0.7.5
 * Requires at least: 5.6
 * Requires PHP:      8.1
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package           WC2odoo
 */

// Your code starts here.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'wc2odoo_load_textdomain');

/**
 * Load textdomain for plugin
 *
 * @return void
 */
function wc2odoo_load_textdomain():void
{
    load_plugin_textdomain('wc2odoo_Integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/* run the install scripts upon plugin activation */
register_activation_hook(__FILE__, 'install_odoo_integration_plugin');

/**
 * Function for creating log table "in8sync_log"
 */
function install_odoo_integration_plugin()
{
    /**
     * Check if WooCommerce is active
     *
     * @since  1.3.4
     */
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
        die(esc_html_e('WooCommerce plugin is missing. Odoo for WooCommerce plugin requires WooCommerce.', 'wc2odoo'));
    }

    try {
        require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';
        require_once plugin_dir_path(__FILE__) . '/includes/class-wc2odoo-api.php';
        $odoo_api = new WC2ODOO_API();
        $odoo_api->get_client()->flush();
    }
    catch (Exception $e) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="error"><p>' . esc_html_e('Odoo API is not installed. Please install the Odoo API.', 'wc2odoo') . '</p></div>';
        die();
    }
}

if (!class_exists('wc2odoo_Integration')) :
    /* Constants */
    $plugin_data    = get_file_data(__FILE__, ['Version' => 'Version'], false);
    $plugin_version = $plugin_data['Version'] ?? '1.5.1';
    define('WC2ODOO_INTEGRATION_INIT_VERSION', $plugin_version);
    define('WC2ODOO_INTEGRATION_PLUGINURL', plugin_dir_url(__FILE__));
    define('WC2ODOO_INTEGRATION_PLUGINDIR', plugin_dir_path(__FILE__));

    /**
     * Represents an integration between WooCommerce and Odoo.
     * This class provides functionality for syncing data between the two systems.
     */
    class wc2odoo_Integration
    {
        /**
         * The singleton instance of the wc2odoo_Integration plugin.
         *
         * @var wc2odoo_Integration|null
         */
        protected static $instance = null;

        /**
         * The unique identifier for the wc2odoo_Integration integration.
         *
         * @var string
         */
        private $id = 'wc2odoo_integration';

        /**
         * The Odoo object used for communication with the Odoo server.
         *
         * @var string
         */
        private $odoo_object = '';

        public static function get_instance()
        {
            // If the single instance hasn't been set, set it now.
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function __construct()
        {
            // phpcs:ignore WordPress.Security.NonceVerification
            if (isset($_GET['clear_odoo_db']) && 1 === $_GET['clear_odoo_db']) {
                $this->clear_odoo_db();
            }
            if (!class_exists('WooCommerce')) {
                add_action('admin_init', [$this, 'wc2odoo_deactivate']);
                add_action('admin_notices', [$this, 'wc2odoo_admin_notice']);
            }
            /* Checks if WooCommerce is installed.*/
            if (class_exists('WC_Integration')) {
                require_once \WC2ODOO_INTEGRATION_PLUGINDIR . '/vendor/autoload.php';
                require_once \WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-api.php';
                require_once \WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-helpers.php';
                require_once \WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-common-functions.php';
                add_filter('plugin_action_links', [$this, 'add_plugin_links'], 10, 5);

                add_action('admin_menu', [$this, 'odoo_metabox']);
                add_action('save_post', [$this, 'wc2odoo_save_meta'], 10, 2);
                add_action( 'odoo_process_import_update_stocks', array( $this, 'do_odoo_import_update_stocks' ) );

                $odoo_api = new WC2ODOO_API();
                $helper   = WC2ODOO_Helpers::get_helper();
                if (is_admin()) {
                    require_once \WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-cron.php';
                    require_once \WC2ODOO_INTEGRATION_PLUGINDIR . '/includes/class-wc2odoo-integration-settings.php';
                    /* Register the integration. */
                    add_filter('woocommerce_integrations', [$this, 'add_integration']);
                    add_action('wp_ajax_odoo_test_cron', [$this, 'odoo_test_cron']);
                    add_action('wp_ajax_odoo_export_product_by_date', [$this, 'odoo_export_product_by_date']);
                    add_action('wp_ajax_odoo_export_customer_by_date', [$this, 'odoo_export_customer_by_date']);
                    add_action('wp_ajax_odoo_export_order_by_date', [$this, 'odoo_export_order_by_date']);
                    add_action('wp_ajax_odoo_import_customer_by_date', [$this, 'odoo_import_customer_by_date']);

                    $c = new WC2ODOO_Common_Functions();
                    add_action('admin_notices', [$c, 'getting_started']);
                    add_action('admin_notices', [$c, 'wc2odoo_admin_notice']);
                } else {
                    if (is_checkout()) {
                        /* Include our integration class.*/
                        if ($odoo_api->is_authenticate()) {
                            require_once \WC2ODOO_INTEGRATION_PLUGINDIR . 'includes/class-wc2odoo-functions.php';
                        }
                    }
                }
            } else {

                die();
            }

            if ('' === get_option('is_wc2odoo_installed') || null === get_option('is_wc2odoo_installed')) {
                global $wpdb;
                $wpdb->query("DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE '%wc2odoo_Integration%'");
                $wpdb->query("DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE '%wc2odoo_Integration%'");
                $wpdb->query("DELETE FROM `{$wpdb->termmeta}` WHERE `meta_key` LIKE '%wc2odoo_Integration%'");
                $wpdb->query("DELETE FROM `{$wpdb->order_itemmeta}` WHERE `meta_key` LIKE '%_order_line_id%'");
                $wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%wc2_odoo%'");
                update_option('is_wc2odoo_installed', 'yes');
            }

            /* End here */
        }

        public function wc2odoo_deactivate()
        {
            deactivate_plugins(plugin_basename(__FILE__));
        }

        public function wc2odoo_admin_notice()
        {
            echo '<div class="error">';
            echo '<p><strong>wc2odoo_Integration plugin deactivated.</strong></p>';
            echo '<p><strong>WooCommerce</strong> must be active to use <strong>wc2odoo_Integration</strong> plugin.</p></div>';
            // phpcs:ignore WordPress.Security.NonceVerification
            if (isset($_GET['activate'])) {
                // phpcs:ignore WordPress.Security.NonceVerification
                unset($_GET['activate']);
            }
        }

        public function odoo_test_cron()
        {
            require_once \WC2ODOO_INTEGRATION_PLUGINDIR . 'includes/class-wc2odoo-functions.php';
            $odoo_object = new WC2ODOO_Functions();
            $odoo_object->do_import_products();
        }

        public function clear_odoo_db()
        {
            global $wpdb;
            $wpdb->query("DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE '%odoo%'");
            $wpdb->query("DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE '%odoo%'");
            $wpdb->query("DELETE FROM `{$wpdb->termmeta}` WHERE `meta_key` LIKE '%odoo%'");
            $wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%odoo_%'");
            //$wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%wc2odoo_shipping_product_id%'");
            //$wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%is_wc2odoo_creds_validated%'");
            //$wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%is_wc2odoo_authenticated%'");
            //$wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%wc2odoo_integration_settings%'");
            $url = add_query_arg(
                ['page'    => 'wc-settings', 'tab'     => 'integration', 'section' => 'wc2odoo_integration'],
                admin_url('admin.php')
            );

            wp_safe_redirect($url);
        }

        public function odoo_export_product_by_date()
        {
            require_once \WC2ODOO_INTEGRATION_PLUGINDIR . 'includes/class-wc2odoo-functions.php';
            $odoo_function = new WC2ODOO_Functions();
            $odoo_function->odoo_export_product_by_date_background();
        }

        public function odoo_export_customer_by_date()
        {
            require_once \WC2ODOO_INTEGRATION_PLUGINDIR . 'includes/class-wc2odoo-functions.php';
            $odoo_function = new WC2ODOO_Functions();
            $odoo_function->odoo_export_customer_by_date();
        }

        public function odoo_export_order_by_date()
        {
            require_once \WC2ODOO_INTEGRATION_PLUGINDIR . 'includes/class-wc2odoo-functions.php';
            $odoo_function = new WC2ODOO_Functions();
            $odoo_function->odoo_export_order_by_date_background();
        }

        public function odoo_import_customer_by_date()
        {
            require_once \WC2ODOO_INTEGRATION_PLUGINDIR . 'includes/class-wc2odoo-functions.php';
            $odoo_function = new WC2ODOO_Functions();
            $odoo_function->odoo_import_customer_by_date();
        }

        /**
         * [updateWooInventory Update and create the product on the woocommerce from the Odoo data].
         */
        public function do_odoo_import_update_stocks() {
            $odoo_api = new \WC2ODOO_API();
            $odoo_api->add_log( 'CRON: do_odoo_import_update_stocks started' );
            $common_functions = new WC2ODOO_Common_Functions();
            if ( $common_functions->is_authenticate() ) {
                $odoo_object = new WC2ODOO_Functions();
                $odoo_object->inventory_sync();
            }
            $odoo_api->add_log( 'CRON: do_odoo_import_update_stocks finished' );
        }
        /**
         * Add a new integration to WooCommerce.
         *
         * @param array $integrations An array of existing integrations.
         * @return array An array of integrations, including the new integration.
         */
        public function add_integration(array $integrations): array
        {
            $integrations[] = 'wc2odoo_Integration_Settings';
            return $integrations;
        }

        public function add_plugin_links($actions, $plugin_file)
        {
            static $plugin;

            if (!isset($plugin)) {
                $plugin = plugin_basename(__FILE__);
            }
            if ($plugin === $plugin_file) {

                $settings  = ['settings' => '<a href=" ' . $this->get_settings_url() . '">' . __('Settings', 'wc2odoo') . '</a>'];
                $site_link = ['support' => '<a href="https://docs.woocommerce.com/document/odoo-for-woocommerce/" target="_blank">' . __('Support', 'wc2odoo') . '</a>'];

                $actions = array_merge($settings, $actions);
                $actions = array_merge($site_link, $actions);
            }
            return $actions;
        }

        /**
         * Add Odoo Meta box to product
         */
        public function odoo_metabox(): void
        {
            add_meta_box(
                'odoo_metabox',
                __('Odoo Sync', 'wc2odoo'),
                [$this, 'wc2odoo_metabox_callback'],
                'product',
                'side',
                'high'
            );
        }

        public function wc2odoo_metabox_callback($post)
        {
            $odoo_product_id      = get_post_meta($post->ID, '_odoo_id', true);
            $odoo_exclude_product = get_post_meta($post->ID, '_exclude_product_to_sync', true);
            if (isset($odoo_exclude_product) && 'yes' === $odoo_exclude_product) {
                $odoo_product_id = __('Product Not to be synced', 'wc2odoo');
            } else {
                if (empty($odoo_product_id)) {
                    $odoo_product_id = __('Not synced', 'wc2odoo');
                }
            }
            wp_nonce_field('odoometa', '_odoononce');
            ?>
			<p>
				<label for="_exclude_product_to_sync">
				<input type="checkbox" name="_exclude_product_to_sync" id="_exclude_product_to_sync" value="yes" 
				<?php
                if (!empty($odoo_exclude_product)) {
                    checked($odoo_exclude_product, 'yes');
                }
            ?>
				/>
				<?php echo esc_html_e('Exclude from Oddo', 'wc2odoo'); ?>
				</label>
			</p>
			<?php
        }

        public function wc2odoo_save_meta($post_id, $post)
        {
            // nonce check
            if (!isset($_POST['_odoononce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_odoononce'])), 'odoometa')) {
                return $post_id;
            }

            // check current user permissions
            $post_type = get_post_type_object($post->post_type);

            if (!current_user_can($post_type->cap->edit_post, $post_id)) {
                return $post_id;
            }

            // Do not save the data if autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $post_id;
            }

            // define your own post type here
            if ($post->post_type !== 'product') {
                return $post_id;
            }

            if (isset($_POST['_exclude_product_to_sync'])) {
                update_post_meta($post_id, '_exclude_product_to_sync', sanitize_text_field(wp_unslash($_POST['_exclude_product_to_sync'])));
            } else {
                delete_post_meta($post_id, '_exclude_product_to_sync');
            }

            return $post_id;
        }

        /**
         * Generate a URL to our Odoo settings screen.
         *
         * @since  1.3.4
         * @return string Generated URL.
         */
        public function get_settings_url()
        {
            return add_query_arg(
                ['page'    => 'wc-settings', 'tab'     => 'integration', 'section' => $this->id],
                admin_url('admin.php')
            );
        }
    }

add_action('plugins_loaded', ['wc2odoo_Integration', 'get_instance']);
endif;
