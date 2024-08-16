<?php
/**  PHPUnit to test the WC2ODOO_Funtions class
*/

// Define required constants
DEFINE('WC2ODOO_INTEGRATION_PLUGINDIR', __DIR__ . '/../');

require_once WC2ODOO_INTEGRATION_PLUGINDIR . 'vendor/autoload.php';
require_once WC2ODOO_INTEGRATION_PLUGINDIR . 'includes/class-wc2odoo-functions.php';

use PHPUnit\Framework\TestCase;

class WC2ODOO_Functions_Test extends TestCase
{
    public function testGetOption()
    {
        
        $this->assertNotNull(get_option('woocommerce_woocommmerce_odoo_integration_settings'));
    }

    public function testGetOdooApi()
    {
        $wc2odoo = new WC2ODOO_Functions();
        $odoo_api = $wc2odoo->get_odoo_api();
        $this->assertNotNull($odoo_api);
        $this->assertNotNull($odoo_api->generate_token());
    }


    public function testGetWCOrder()
    {
        $wc2odoo = new WC2ODOO_Functions();
        $this->assertNotNull($wc2odoo);
    }

    //test call to get_last_l10n_latam_document_number
    public function testGetLastL10nLatamDocumentNumber()
    {
        $wc2odoo = new WC2ODOO_Functions();
        $this->assertTrue($wc2odoo->get_last_l10n_latam_document_number(-1) == '000000');
        $this->assertTrue( is_numeric( $wc2odoo->get_last_l10n_latam_document_number(1) ));
        $this->assertTrue( is_numeric( $wc2odoo->get_last_l10n_latam_document_number(5) ));
    }


    public function testFormat_Rut()
    {
        $wc2odoo = new WC2ODOO_Functions();
        $this->assertTrue($wc2odoo->format_rut('12345678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12.345.678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12.345.678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12,345-678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12.345.678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12.345.678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12.345.678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12.345.678-9') == '12345678-9');
        $this->assertTrue($wc2odoo->format_rut('12.345.678-9') == '12345678-9');
    }

}
