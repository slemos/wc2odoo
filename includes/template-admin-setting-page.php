  <style type="text/css">
  .tabset > input[type="radio"] {
    position: absolute;
    left: -200vw;
  }
  .tabset .tab-panel {
    display: none;
  }
  .tabset > input:first-child:checked ~ .tab-panels > .tab-panel:first-child,
  .tabset > input:nth-child(3):checked ~ .tab-panels > .tab-panel:nth-child(2),
  .tabset > input:nth-child(5):checked ~ .tab-panels > .tab-panel:nth-child(3),
  .tabset > input:nth-child(7):checked ~ .tab-panels > .tab-panel:nth-child(4),
  .tabset > input:nth-child(9):checked ~ .tab-panels > .tab-panel:nth-child(5),
  .tabset > input:nth-child(11):checked ~ .tab-panels > .tab-panel:nth-child(6) {
    display: block;
  }
  /*
  Styling
  */
  body {
    font: 16px/1.5em "Overpass", "Open Sans", Helvetica, sans-serif;
    color: #333;
    font-weight: 300;
  }
  .tabset > label {
    position: relative;
    display: inline-block;
    padding: 15px 15px 25px;
    border: 1px solid transparent;
    border-bottom: 0;
    cursor: pointer;
    font-weight: 600;
  }
  .tabset > input:checked + label {
    border-color: #ccc;
    border-bottom: 1px solid #fff;
    margin-bottom: -1px;
  }
  .tab-panel {
    padding: 30px 0;
    border-top: 1px solid #ccc;
  }
  .switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
  }
  .switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }
  .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    -webkit-transition: .4s;
    transition: .4s;
  }
  .slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    -webkit-transition: .4s;
    transition: .4s;
  }
  input:checked + .slider {
    background-color: #05a4e8;
  }
  input:focus + .slider {
    box-shadow: 0 0 1px #2196F3;
  }
  input:checked + .slider:before {
    -webkit-transform: translateX(26px);
    -ms-transform: translateX(26px);
    transform: translateX(26px);
  }
  .slider.round {
    border-radius: 34px;
  }
  .slider.round:before {
    border-radius: 50%;
  }
  .product-rt-section {
    display: inline-block;
  }
  .product-lt-section {
    display: inline-block;
  }
  .inner-product-function {
    display: block;
  }
  .product-function {
    display: flex;
    width: 100%;
  }
  .product-function-lt, .product-function-rt {
    width: 50%;
  }
  .inner-product-function {
    display: block;
    padding-right: 30%;
  }
  .product-rt-section {
    display: inline-block;
    float: right;
    margin-top: 11px;
  }
  .product-lt-section.order-function-section p {
    display: inline-block;
  }
  .product-lt-section.order-function-section {
    display: inline-block;
    width: 80%;
    }.product-lt-section.order-function-section input {
      width: 20%;
      margin: 0 20px;
    }
    .form-section-tm {
      display: flex;
      padding: 20px 0;
      align-items: baseline;
    }
    .form-section-lt {
      width: 20%;
    }
    .form-section-rt input {
      width: 50%;
      height: 40px;
      line-height: 40px;
    }
    .form-section-rt select {
      width: 100%;
      height: 40px;
    }
    .form-section-rt {
      width: 50%;
    }
    .form-section-rt input {
      width: 100%;
    }
    .form-section-lt label {
      font-size: 18px;
      font-weight: 600;
    }
    .form-section-rt span {
      font-size: 16px;
      font-weight: 400;
      color: #818181;
    }
    .product-function-heading {
      color: #05a4e8;
    }
    .btn-sections button {
      width: 49%;
      margin-top: 30px;
      background-color: #05a4e8;
      color: #fff;
      padding: 15px;
      border-radius: 4px;
      border: none;
      font-size: 18px;
    }
    .product-function-lt {
      margin-right: 5%;
      }.product-function-rt {
        margin-left: 5%;
      }
      .product-function .select {
        width: 100% !important;
      }
      p.submit {
        margin-left: 20px;
      }
      .io-field input[type="text"] {
        display: inline-block;
        width: 110px!important;
        margin-right: 10px!important;
        vertical-align: middle!important;
      }
      .select-repeater{
          display: flex;
          justify-content: space-between;
          position: relative;
          margin-bottom: 10px;
      }
      button#acAdder0 {
          position: absolute;
          right: -30px;
          top: 40px;
      }
      .woocommerce table.form-table .select-repeater fieldset:first-child {
          margin-top: 4px;
          margin-right: 4px
      }
      .mappingBlock td.forminp {
          vertical-align: top;
          padding-top: 0;
      }
      .cron-button.trigger_cron {
          margin: 0.5em 0 0.5em 0.75em;
      }
      .opmc-odoo-check {
        font-size: .85rem;
        display: inline-block;
        border: 2px solid #dcdcdc;
        padding: 0.25rem 0.5rem;
        cursor: default;
        font-weight: 600;
        border-radius: 0.25rem;
    }
    .opmc-odoo-check.opmc-success {
        background: #fff;
        color: #30952c;
        border-color: #30952c;
    }
    .opmc-odoo-check.opmc-error {
        background: #fff;
        color: #d60909;
        border-color: #d60909;
    }
    .opmc-odoo-check.opmc-warning {
        color: #f5b50a;
        background: #fff;
        border-color: #f5b50a;
    }
    .opmc-odoo-check.opmc-warning .dashicons{
        margin-right: 3px;
    }

    </style>
    <?php
    // xdebug_break();
    $freq_options = [
        'hourly' => __('Every Hour', 'wc2odoo'),
        'twicedaily' => __('Twice A Day', 'wc2odoo'),
        'daily' => __('Once A Day', 'wc2odoo'),
    ];
    $fields = $this->allowed_html();
    ?>
    <div class="tabset">
      <!-- Tab 1 -->
      <input type="radio" name="tabset" id="tab1" aria-controls="odoo_creds_settings" checked>
      <label for="tab1"><?php echo esc_html__('Settings', 'wc2odoo'); ?></label>
      <!-- Tab 2 -->
      <input type="radio" name="tabset" id="tab2" aria-controls="odoo_import">
      <label for="tab2"><?php echo esc_html__('Import', 'wc2odoo'); ?></label>
      <!-- Tab 3 -->
      <input type="radio" name="tabset" id="tab3" aria-controls="odoo_export">
      <label for="tab3"><?php echo esc_html__('Export', 'wc2odoo'); ?></label>
      <div class="tab-panels">
        <section id="odoo_creds_settings" class="tab-panel">
          <h2><?php echo esc_html__('ODOO Integration', 'wc2odoo'); ?> <span class="opmc-odoo-check <?php echo esc_attr($wc2odoo_indicator['class']); ?>"> <span class="dashicons <?php echo esc_attr($wc2odoo_indicator['icon']); ?>"></span><?php echo $wc2odoo_indicator['value']; ?></span></h2>
          <table class="form-table">
            <?php
            foreach ($tab1 as $sk => $sv) {
                $input_type = $this->get_field_type($sv);
                if (method_exists($this, 'generate_'.$input_type.'_html')) {
                    $html = $this->{'generate_'.$input_type.'_html'}($sk, $sv);
                } else {
                    $html = $this->generate_text_html($sk, $sv);
                }
                // echo $html;
                echo wp_kses($html, $fields); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
    ?>
          </table>
        </section>
        <section id="odoo_import" class="tab-panel">

          <div class="product-function">
           <div class="product-function-lt">
            <h2 class="product-function-heading"><?php echo esc_html__('Product Functions', 'wc2odoo'); ?></h2>
            <table class="form-table">

              <?php

        foreach ($tab2 as $sk => $sv) {
            $input_type = $this->get_field_type($sv);
            if (method_exists($this, 'generate_'.$input_type.'_html')) {
                $html = $this->{'generate_'.$input_type.'_html'}($sk, $sv);
            } else {
                $html = $this->generate_text_html($sk, $sv);
            }
            // echo $html;
            echo wp_kses($html, $fields); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    ?>
            </table>
            
          </div>
          <div class="product-function-rt">
            <h2 class="product-function-heading"><?php echo esc_html__('Discount Functions', 'wc2odoo'); ?></h2>
            <table class="form-table">
              <?php
    foreach ($tab2_3 as $sk => $sv) {
        $input_type = $this->get_field_type($sv);
        if (method_exists($this, 'generate_'.$input_type.'_html')) {
            $html = $this->{'generate_'.$input_type.'_html'}($sk, $sv);
        } else {
            $html = $this->generate_text_html($sk, $sv);
        }
        // echo $html;
        echo wp_kses($html, $fields); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>
            </table>
          </div>
        </div>
        <div class="product-function">
         <div class="product-function-lt">
          <div class="inner-product-function">
            <h2 class="product-function-heading"><?php echo esc_html__('Order Functions', 'wc2odoo'); ?></h2>
          </div>
          <table class="form-table">
            <tbody>
              <tr valign="top">
                <th scope="row" class="titledesc">
                  <label for="woocommerce_woocommmerce_odoo_integration_odoo_import_coupon_frequency"><?php echo esc_html__('Customer Frequency', 'wc2odoo'); ?> </label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html__('Customer Frequency', 'wc2odoo'); ?></span></legend>
                    <select class="select " name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer_frequency'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer_frequency'); ?>" style="">
                      <?php foreach ($freq_options as $option_key_inner => $option_value_inner) { ?>
                        <option value="<?php echo esc_attr($option_key_inner); ?>" <?php selected((string) $option_key_inner, esc_attr($this->get_option('odoo_import_customer_frequency'))); ?>><?php echo $option_value_inner; ?></option>
                      <?php } ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Select Customer Cron Frequency to Sync Customer', 'wc2odoo'); ?></p>
                  </fieldset>
                </td>
              </tr>
              <tr valign="top">
                <th scope="row" class="titledesc">
                  <label for="woocommerce_woocommmerce_odoo_integration_odoo_import_coupon"><?php echo esc_html__('Import/Update Customers', 'wc2odoo'); ?></label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html__('Import/Update Customers', 'wc2odoo'); ?></span></legend>
                    <label class="switch">
                      <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer'); ?>" value="<?php $this->get_option('odoo_import_customer'); ?>" <?php checked($this->get_option('odoo_import_customer'), 'yes'); ?>>
                      <span class="slider round"></span>
                    </label>
                  </fieldset>
                </td>
              </tr>
              <tr valign="top">
                <th scope="row" class="titledesc">
                  <label for="woocommerce_woocommmerce_odoo_integration_odoo_import_coupon_frequency"><?php echo esc_html__('Order Frequency', 'wc2odoo'); ?> </label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html__('Order Frequency', 'wc2odoo'); ?></span></legend>
                    <select class="select " name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order_frequency'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order_frequency'); ?>" style="">
                      <?php foreach ($freq_options as $option_key_inner => $option_value_inner) { ?>
                        <option value="<?php echo esc_attr($option_key_inner); ?>" <?php selected((string) $option_key_inner, esc_attr($this->get_option('odoo_import_order_frequency'))); ?>><?php echo $option_value_inner; ?></option>
                      <?php } ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Select Order Cron Frequency to Sync Order', 'wc2odoo'); ?></p>
                  </fieldset>
                </td>
              </tr>
              <tr valign="top">
                <th scope="row" class="titledesc">
                  <label for="woocommerce_woocommmerce_odoo_integration_odoo_import_coupon_update"><?php echo esc_html__('Import Orders', 'wc2odoo'); ?></label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html__('Import Orders', 'wc2odoo'); ?></span></legend>
                    <div class="io-field">
                      <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order_from_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order_from_date'); ?>" placeholder="<?php esc_html__('From', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('odoo_import_order_from_date')); ?>" class="datepicker_min">

                      <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order_to_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order_to_date'); ?>" placeholder="<?php esc_html__('To', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('odoo_import_order_to_date')); ?>" class="datepicker_max">
                      <label class="switch">

                        <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_order'); ?>" value="<?php $this->get_option('odoo_import_order'); ?>" <?php checked($this->get_option('odoo_import_order'), 'yes'); ?>>
                        <span class="slider round"></span>
                      </label>
                    </div>
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                  <label for="woocommerce_woocommmerce_odoo_integration_odoo_import_coupon_frequency"><?php echo esc_html__('Order Refund Frequency', 'wc2odoo'); ?> </label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html__('Order Refund Frequency', 'wc2odoo'); ?></span></legend>
                    <select class="select " name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_refund_order_frequency'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_refund_order_frequency'); ?>" style="">
                      <?php foreach ($freq_options as $option_key_inner => $option_value_inner) { ?>
                        <option value="<?php echo esc_attr($option_key_inner); ?>" <?php selected((string) $option_key_inner, esc_attr($this->get_option('odoo_import_refund_order_frequency'))); ?>><?php echo $option_value_inner; ?></option>
                      <?php } ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Select Refund Order Cron Frequency to Sync Refund Order', 'wc2odoo'); ?></p>
                  </fieldset>
                </td>
              </tr>
              <tr valign="top">
                <th scope="row" class="titledesc">
                  <label for="woocommerce_woocommmerce_odoo_integration_odoo_import_coupon_update"><?php echo esc_html__('Import Refund Orders', 'wc2odoo'); ?></label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html__('Import Refund Orders', 'wc2odoo'); ?></span></legend>
                    <div class="io-field">
                      <label class="switch">
                        <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_refund_order'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_refund_order'); ?>" value="<?php $this->get_option('odoo_import_refund_order'); ?>" <?php checked($this->get_option('odoo_import_refund_order'), 'yes'); ?>>
                        <span class="slider round"></span>
                      </label>
                    </div>
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_customer_from_date"><?php echo esc_html__('Odoo Customer Sync', 'wc2odoo'); ?></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Odoo Customer Sync', 'wc2odoo'); ?></span></legend>
                  <div class="io-field">
                    <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer_from_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer_from_date'); ?>" placeholder="<?php esc_html__('From', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('_odoo_import_customer_from_date')); ?>" class="datepicker_min">

                    <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer_to_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_import_customer_to_date'); ?>" placeholder="<?php esc_html__('To', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('_odoo_import_customer_to_date')); ?>" class="datepicker_max">

                    <button type="button" name="odoo_import_customer_sync" value="submit" id="odoo_import_customer_sync"><?php echo esc_html__('Submit', 'wc2odoo'); ?></button>
                    <button type="button" id="odoo_import_customer_sync_loading" style="display:none;"><?php echo esc_html__('Please wait.', 'wc2odoo'); ?></button>
                    <span class="odoo_import_customer_sync_message"></span>
                  </label>
                </div>
              </fieldset>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
      <div class="product-function-rt"></div>
    </div>
  </section>
  <section id="odoo_export" class="tab-panel">
    <div class="product-function">
      <div class="product-function-lt">
        <h2 class="product-function-heading"><?php echo esc_html__('Product Functions', 'wc2odoo'); ?></h2>
        <table class="form-table">
          <?php
            foreach ($tab3 as $sk => $sv) {
                $input_type = $this->get_field_type($sv);
                if (method_exists($this, 'generate_'.$input_type.'_html')) {
                    $html = $this->{'generate_'.$input_type.'_html'}($sk, $sv);
                } else {
                    $html = $this->generate_text_html($sk, $sv);
                }
                // echo $html;
                echo wp_kses($html, $fields); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
    ?>
        </table>
      </div>
      <div class="product-function-rt">
        <h2 class="product-function-heading"><?php echo esc_html__('Discount Functions', 'wc2odoo'); ?></h2>
        <table class="form-table">
          <?php
    foreach ($tab3_3 as $sk => $sv) {
        $input_type = $this->get_field_type($sv);
        if (method_exists($this, 'generate_'.$input_type.'_html')) {
            $html = $this->{'generate_'.$input_type.'_html'}($sk, $sv);
        } else {
            $html = $this->generate_text_html($sk, $sv);
        }
        // echo $html;
        echo wp_kses($html, $fields); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>
        </table>
      </div>
    </div>
    <div class="product-function">
      <div class="product-function-lt">
        <div class="inner-product-function">
          <h2 class="product-function-heading"><?php echo esc_html__('Order Functions', 'wc2odoo'); ?></h2>
        </div>
        <table class="form-table">
          <tbody>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon"><?php echo esc_html__('Export Order On Checkout', 'wc2odoo'); ?></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Export Order On Checkout', 'wc2odoo'); ?></span></legend>
                  <label class="switch">
                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_on_checkout'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_on_checkout'); ?>" value="<?php $this->get_option('odoo_export_order_on_checkout'); ?>" <?php checked($this->get_option('odoo_export_order_on_checkout'), 'yes'); ?>>
                    <span class="slider round"></span>
                  </label>
                </fieldset>
              </td>
            </tr>
              <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon"><?php echo esc_html__('Export Invoice', 'wc2odoo'); ?></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Export Invoice', 'wc2odoo'); ?></span></legend>
                  <label class="switch">
                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_invoice'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_invoice'); ?>" value="<?php $this->get_option('odoo_export_invoice'); ?>" <?php checked($this->get_option('odoo_export_invoice'), 'yes'); ?>>
                    <span class="slider round"></span>
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon"><?php echo esc_html__('Mark Invoice Paid', 'wc2odoo'); ?> <span class="dashicons dashicons-info-outline" title="<?php echo esc_html__('If this setting is enabled the invoice will be marked as completed regardless of the order status. Status mapping will be skipped.', 'wc2odoo'); ?>"></span></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Mark Invoice Paid', 'wc2odoo'); ?></span></legend>
                  <label class="switch">
                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_mark_invoice_paid'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_mark_invoice_paid'); ?>" value="<?php $this->get_option('odoo_mark_invoice_paid'); ?>" <?php checked($this->get_option('odoo_mark_invoice_paid'), 'yes'); ?>>
                    <span class="slider round"></span>
                  </label>
                  <p class="description"><?php echo esc_html__('Export Invoice should be enabled for this.', 'wc2odoo'); ?></p>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon"><?php echo esc_html__('Export Refund Order', 'wc2odoo'); ?></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Export Refund Order', 'wc2odoo'); ?></span></legend>
                  <label class="switch">
                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_refund_order'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_refund_order'); ?>" value="<?php $this->get_option('odoo_export_refund_order'); ?>" <?php checked($this->get_option('odoo_export_refund_order'), 'yes'); ?>>
                    <span class="slider round"></span>
                  </label>
                  <p class="description"><?php echo esc_html__('Export Invoice should be enabled for this.', 'wc2odoo'); ?></p>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon"><?php echo esc_html__('Status Mapping', 'wc2odoo'); ?></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Status Mapping', 'wc2odoo'); ?></span></legend>
                  <label class="switch">
                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_status_mapping'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_status_mapping'); ?>" value="<?php $this->get_option('odoo_status_mapping'); ?>" <?php checked($this->get_option('odoo_status_mapping'), 'yes'); ?>>
                    <span class="slider round"></span>
                  </label>
                  <p class="description"><?php echo esc_html__('Custom Status Mapping.', 'wc2odoo'); ?></p>
                </fieldset>
              </td>
            </tr>
            <?php
        $statuses = wc_get_order_statuses();
    unset($statuses['wc-refunded']);
    $odoo_payment_states = [
        'quote_only' => __('Quote Only', 'wc2odoo'),
        'quote_order' => __('Quote and Sales Order', 'wc2odoo'),
        'in_payment' => __('In Payment Invoice', 'wc2odoo'),
        'paid' => __('Paid Invoice', 'wc2odoo'),
        'cancelled' => __('Cancelled', 'wc2odoo'),
    ];
    $odoo_states_desc = [
        'quote_only' => __('This will only create Quote on Odoo. No Sales Order and invoice.', 'wc2odoo'),
        'quote_order' => __('Quote and Sales Order will be created. Invoice will not be created.', 'wc2odoo'),
        'in_payment' => __('Quote, Sales Order and Invoice will be created. Invoice will be in “In Payment“ State.', 'wc2odoo'),
        'paid' => __('Quote, Sales Order and Invoice will be created. The invoice will be marked as PAID', 'wc2odoo'),
        'cancelled' => __('Order will be marked as canceled', 'wc2odoo'),
    ];
    ?>
            <tr valign="top" class="order_mapping_block">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_woo_order_status"><?php echo esc_html__('Order Status Mapping', 'wc2odoo'); ?>:</label>
              </th>
            </tr>
            <tr valign="top" class="order_mapping_block">
              <th scope="column" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_woo_order_status"><?php echo esc_html__('Woo Order Status', 'wc2odoo'); ?></label>
              </th>
              <th scope="column" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_payment_status"><?php echo esc_html__('Odoo Order State', 'wc2odoo'); ?></label>
              </th>
            </tr>
            
                <?php
    if ($this->get_option('odoo_woo_order_status') > 0 && $this->get_option('odoo_payment_status')) {
        $mapped_woo_status = $this->get_option('odoo_woo_order_status');
        $mapped_odoo_payment_states = $this->get_option('odoo_payment_status');
        ?>
                    <?php foreach ($mapped_woo_status as $map_key => $value) { ?>
                          <tr valign="top" class="mappingBlock" data-index="<?php echo esc_attr($map_key); ?>" data-max_rows="<?php echo count($statuses); ?>">
                              <td class="forminp">
                                  <fieldset>
                                      <legend class="screen-reader-text"><span><?php echo esc_html__('Woo Order Status', 'wc2odoo'); ?></span></legend>
                                      <select class="select odoo_woo_order_status" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_woo_order_status['.$map_key.']'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_woo_order_status_'.$map_key); ?>">
                                          <option value=""><?php echo esc_html__('Select Woo Status', 'wc2odoo'); ?></option>
                                        <?php foreach ($statuses as $key => $value) { ?>
                                            <?php if ('wc-refunded' != $key) { ?> 
                                                  <option value="<?php echo esc_attr($key); ?>" <?php selected((string) $key, esc_attr($mapped_woo_status[$map_key])); ?>><?php echo $value; ?></option>
                                            <?php } ?>
                                        <?php } ?>
                                      </select>
                                      <p class="description"><?php echo esc_html__('Woo Status to Map.', 'wc2odoo'); ?></p>
                                  </fieldset>
                              </td>
                              <td class="forminp">
                                  <fieldset>
                                      <legend class="screen-reader-text"><span><?php echo esc_html__('Odoo Order State', 'wc2odoo'); ?></span></legend>
                                      <select class="select odoo_payment_status" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_payment_status['.$map_key.']'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_payment_status_'.$map_key); ?>">
                                          <option value="" data-desc="<?php echo esc_html__('Selected state\'s description.', 'wc2odoo'); ?>"><?php echo esc_html__('Select Odoo State', 'wc2odoo'); ?></option>
                                        <?php foreach ($odoo_payment_states as $key => $value) { ?>
                                              <option value="<?php echo esc_attr($key); ?>" <?php selected((string) $key, esc_attr($mapped_odoo_payment_states[$map_key])); ?> data-desc="<?php echo $odoo_states_desc[$key]; ?>" ><?php echo $value; ?></option>
                                        <?php } ?>
                                      </select>
                                      <p class="description">
                                        <?php echo ('' != $mapped_odoo_payment_states[$map_key]) ? $odoo_states_desc[$mapped_odoo_payment_states[$map_key]] : esc_html__('Selected state\'s description.', 'wc2odoo'); ?>
                                      </p>
                                  </fieldset>
                              </td>
                          </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr valign="top" class="mappingBlock" data-index="1" data-max_rows="<?php echo count($statuses); ?>">
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php echo esc_html__('Woo Order Status', 'wc2odoo'); ?></span></legend>
                                <select class="select odoo_woo_order_status" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_woo_order_status[1]'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_woo_order_status_1'); ?>">
                                    <option value=""><?php echo esc_html__('Select Woo Status', 'wc2odoo'); ?></option>
                                    <?php foreach ($statuses as $key => $value) { ?>
                                        <?php if ('wc-refunded' != $key) { ?> 
                                            <option value="<?php echo esc_attr($key); ?>" ><?php echo $value; ?></option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                                <p class="description"><?php echo esc_html__('Woo Status to Map.', 'wc2odoo'); ?></p>
                            </fieldset>
                        </td>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php echo esc_html__('Odoo Order State', 'wc2odoo'); ?></span></legend>
                                <select class="select odoo_payment_status" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_payment_status[1]'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_payment_status_1'); ?>">
                                    <option value="" data-desc="<?php echo esc_html__('Selected state\'s description.', 'wc2odoo'); ?>"><?php echo esc_html__('Select Odoo State', 'wc2odoo'); ?></option>
                                    <?php foreach ($odoo_payment_states as $key => $value) { ?>
                                        <option value="<?php echo esc_attr($key); ?>" data-desc="<?php echo $odoo_states_desc[$key]; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                                <p class="description"><?php echo esc_html__('Selected state\'s description.', 'wc2odoo'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                <?php } ?>
                  <tr valign="top" class="order_mapping_block">
                      <td colspan="2" align="right">    
                          <input type="button" class="btn btn-primary" value="<?php echo esc_html__('(+) Add More Mapping', 'wc2odoo'); ?>" id="addMoreMappingRows" />
                      </td>
                  </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon_frequency"><?php echo esc_html__('Customer Frequency', 'wc2odoo'); ?> </label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Customer Frequency', 'wc2odoo'); ?></span></legend>
                  <select class="select " name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer_frequency'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer_frequency'); ?>" style="">
                    <?php foreach ($freq_options as $option_key_inner => $option_value_inner) { ?>
                      <option value="<?php echo esc_attr($option_key_inner); ?>" <?php selected((string) $option_key_inner, esc_attr($this->get_option('odoo_export_customer_frequency'))); ?>><?php echo $option_value_inner; ?></option>
                    <?php } ?>
                  </select>
                  <p class="description"><?php echo esc_html__('Select Customer Cron Frequency to Sync Customer', 'wc2odoo'); ?></p>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon"><?php echo esc_html__('Export/Update Customers', 'wc2odoo'); ?></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Export/Update Customers', 'wc2odoo'); ?></span></legend>
                  <label class="switch">
                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer'); ?>" value="<?php $this->get_option('odoo_export_customer'); ?>" <?php checked($this->get_option('odoo_export_customer'), 'yes'); ?>>
                    <span class="slider round"></span>
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon_frequency"><?php echo esc_html__('Order Frequency', 'wc2odoo'); ?> </label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Order Frequency', 'wc2odoo'); ?></span></legend>
                  <select class="select " name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_frequency'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_frequency'); ?>" style="">
                    <?php foreach ($freq_options as $option_key_inner => $option_value_inner) { ?>
                      <option value="<?php echo esc_attr($option_key_inner); ?>" <?php selected((string) $option_key_inner, esc_attr($this->get_option('odoo_export_order_frequency'))); ?>><?php echo $option_value_inner; ?></option>
                    <?php } ?>
                  </select>
                  <p class="description"><?php echo esc_html__('Select Order Cron Frequency to Sync Order', 'wc2odoo'); ?></p>
                </fieldset>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row" class="titledesc">
                <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_coupon_update"><?php echo esc_html__('Export Orders', 'wc2odoo'); ?></label>
              </th>
              <td class="forminp">
                <fieldset>
                  <legend class="screen-reader-text"><span><?php echo esc_html__('Export Orders', 'wc2odoo'); ?></span></legend>
                  <div class="io-field">
                    <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_from_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_from_date'); ?>" placeholder="<?php esc_html__('From', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('odoo_export_order_from_date')); ?>" class="datepicker_min">

                    <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_to_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order_to_date'); ?>" placeholder="<?php esc_html__('To', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('odoo_export_order_to_date')); ?>" class="datepicker_max">

                    <label class="switch">
                      <input type="checkbox" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order'); ?>" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_order'); ?>" value="<?php $this->get_option('odoo_export_order'); ?>" <?php checked($this->get_option('odoo_export_order'), 'yes'); ?>>
                      <span class="slider round"></span>
                        </label>
                        <button type="button" name="odoo_export_order_sync" value="submit" id="odoo_export_order_sync"><?php echo esc_html__('Submit', 'wc2odoo'); ?></button>
                        <button type="button" id="odoo_export_order_sync_loading" style="display:none;"><?php echo esc_html__('Please wait.', 'wc2odoo'); ?></button>
                        <span class="odoo_export_order_sync_message"></span>
                    </div>
                </fieldset>
                </td>
            </tr>
            <tr valign="top">
                  <th scope="row" class="titledesc">
                    <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_product_from_date"><?php echo esc_html__('Product Sync', 'wc2odoo'); ?></label>
                  </th>
                  <td class="forminp">
                    <fieldset>
                      <legend class="screen-reader-text"><span><?php echo esc_html__('Product Sync', 'wc2odoo'); ?></span></legend>
                      <div class="io-field">
                        <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_product_from_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_product_from_date'); ?>" placeholder="<?php esc_html__('From', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('_odoo_export_product_from_date')); ?>" class="datepicker_min">

                        <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_product_to_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_product_to_date'); ?>" placeholder="<?php esc_html__('To', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('_odoo_export_product_to_date')); ?>" class="datepicker_max">

                        <button type="button" name="odoo_export_product_sync" value="submit" id="odoo_export_product_sync"><?php echo esc_html__('Submit', 'wc2odoo'); ?></button>
                        <button type="button" id="odoo_export_product_sync_loading" style="display:none;"><?php echo esc_html__('Please wait.', 'wc2odoo'); ?></button>
                        <span class="odoo_export_product_sync_message"></span>
                      </label>
                    </div>
                  </fieldset>
                </td>
            </tr>
            <tr valign="top">
                  <th scope="row" class="titledesc">
                    <label for="woocommerce_woocommmerce_odoo_integration_odoo_export_customer_from_date"><?php echo esc_html__('Customer Sync', 'wc2odoo'); ?></label>
                  </th>
                  <td class="forminp">
                    <fieldset>
                      <legend class="screen-reader-text"><span><?php echo esc_html__('Customer Sync', 'wc2odoo'); ?></span></legend>
                      <div class="io-field">
                        <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer_from_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer_from_date'); ?>" placeholder="<?php esc_html__('From', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('_odoo_export_customer_from_date')); ?>" class="datepicker_min">

                        <input type="text" id="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer_to_date'); ?>" name="<?php echo esc_attr($this->plugin_id.$this->id.'_odoo_export_customer_to_date'); ?>" placeholder="<?php esc_html__('To', 'wc2odoo'); ?>" value="<?php echo esc_attr($this->get_option('_odoo_export_customer_to_date')); ?>" class="datepicker_max">

                        <button type="button" name="odoo_export_customer_sync" value="submit" id="odoo_export_customer_sync"><?php echo esc_html__('Submit', 'wc2odoo'); ?></button>
                        <button type="button" id="odoo_export_customer_sync_loading" style="display:none;"><?php echo esc_html__('Please wait.', 'wc2odoo'); ?></button>
                        <span class="odoo_export_customer_sync_message"></span>
                      </label>
                    </div>
                  </fieldset>
                </td>
            </tr>
          
          
        </tbody>
      </table>
    </div>
    <div class="product-function-rt"></div>
  </div>
</section>
</div>
</div>

<!-- <button type="button" id="RunCronJob" >Run Cron Job (added by k for cron job test)</button>-->

<script type="text/javascript">
  jQuery(function(){
    jQuery(".datepicker_min").datepicker({ dateFormat: 'yy-mm-dd' });
    jQuery(".datepicker_max").datepicker({ dateFormat: 'yy-mm-dd' });
    jQuery('#odoo_export_product_sync').click(function(e){
        var dateFrom = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_product_from_date').val();
        var dateTo = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_product_to_date').val();
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_product_from_date').css({'border':'1px solid #8c8f94'});
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_product_to_date').css({'border':'1px solid #8c8f94'});
        e.preventDefault();
        if(dateFrom !='' && dateTo != ''){
            if(confirm('Are you sure you want to sync the product with odoo? ')){
                    jQuery('#odoo_export_product_sync').css({'display':'none'});
                    jQuery('#odoo_export_product_sync_loading').css({'display':'inline-block'});
                    jQuery('.odoo_export_product_sync_message').html('');
                    jQuery.ajax({
                         data: {action: 'odoo_export_product_by_date', dateFrom:dateFrom,dateTo:dateTo, security:odoo_admin.ajax_nonce},
                         type: 'post',
                         url: ajaxurl,      
                         success: function(data) {
                             var obj = jQuery.parseJSON(data);
                             if(obj.result == 'success'){
                                jQuery('#odoo_export_product_sync').css({'display':'inline-block'});
                                jQuery('#odoo_export_product_sync_loading').css({'display':'none'});    
                                jQuery('.odoo_export_product_sync_message').html("<?php echo esc_html__('Product sync successfully.', 'wc2odoo'); ?>");
                             }else{
                                jQuery('#odoo_export_product_sync').css({'display':'inline-block'});
                                jQuery('#odoo_export_product_sync_loading').css({'display':'none'});
                             }
                        }
                    });
            }   
        }else{
            if(dateFrom === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_product_from_date').css({'border':'1px solid #c50a0a'});
            }
            if(dateTo === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_product_to_date').css({'border':'1px solid #c50a0a'});
            }
        }
    });
    
    jQuery('#odoo_export_customer_sync').click(function(e){
        e.preventDefault();
        var dateFrom = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_customer_from_date').val();
        var dateTo = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_customer_to_date').val();
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_customer_from_date').css({'border':'1px solid #8c8f94'});
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_customer_to_date').css({'border':'1px solid #8c8f94'});
        if(dateFrom !='' && dateTo != ''){
            if(confirm('Are you sure you want to sync the customer with odoo? ')){
                    jQuery('#odoo_export_customer_sync').css({'display':'none'});
                    jQuery('#odoo_export_customer_sync_loading').css({'display':'inline-block'});
                    jQuery('.odoo_export_customer_sync_message').html('');
                    jQuery.ajax({
                         data: {action: 'odoo_export_customer_by_date', dateFrom:dateFrom,dateTo:dateTo, security:odoo_admin.ajax_nonce},
                         type: 'post',
                         url: ajaxurl,
                         success: function(data) {
                             var obj = jQuery.parseJSON(data);
                             if(obj.result == 'success'){
                                jQuery('#odoo_export_customer_sync').css({'display':'inline-block'});
                                jQuery('#odoo_export_customer_sync_loading').css({'display':'none'});   
                                jQuery('.odoo_export_customer_sync_message').html("<?php echo esc_html__('Customer sync  successfully .', 'wc2odoo'); ?>");
                             }else{
                                jQuery('#odoo_export_customer_sync').css({'display':'inline-block'});
                                jQuery('#odoo_export_customer_sync_loading').css({'display':'none'});
                             }
                        }
                    });
            }
        }else{
            if(dateFrom === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_customer_from_date').css({'border':'1px solid #c50a0a'});
            }
            if(dateTo === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_customer_to_date').css({'border':'1px solid #c50a0a'});
            }
        }
    });
    
    jQuery('#odoo_export_order_sync').click(function(e){
        e.preventDefault();
        var dateFrom = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_order_from_date').val();
        var dateTo = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_order_to_date').val();
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_order_from_date').css({'border':'1px solid #8c8f94'});
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_order_to_date').css({'border':'1px solid #8c8f94'});
        if(dateFrom !='' && dateTo != ''){
            if(confirm('Are you sure you want to sync the order with odoo? ')){
                    jQuery('#odoo_export_order_sync').css({'display':'none'});
                    jQuery('#odoo_export_order_sync_loading').css({'display':'inline-block'});
                    jQuery('.odoo_export_order_sync_message').html('');
                    jQuery.ajax({
                         data: {action: 'odoo_export_order_by_date', dateFrom:dateFrom,dateTo:dateTo, security:odoo_admin.ajax_nonce},
                         type: 'post',
                         url: ajaxurl,
                         success: function(data) {
                             //var obj = jQuery.parseJSON(data);
                             console.log(data);
                             if(data.success){
                                jQuery('#odoo_export_order_sync').css({'display':'inline-block'});
                                jQuery('#odoo_export_order_sync_loading').css({'display':'none'});  
                                jQuery('.odoo_export_order_sync_message').html(data.data.message);
                             }else{
                                jQuery('#odoo_export_order_sync').css({'display':'inline-block'});
                                jQuery('#odoo_export_order_sync_loading').css({'display':'none'});
                             }
                        }
                    });
            }
        }else{
            if(dateFrom === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_order_from_date').css({'border':'1px solid #c50a0a'});
            }
            if(dateTo === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_export_order_to_date').css({'border':'1px solid #c50a0a'});
            }
        }
    });
    
    jQuery('#odoo_import_customer_sync').click(function(e){
        e.preventDefault();
        var dateFrom = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_import_customer_from_date').val();
        var dateTo = jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_import_customer_to_date').val();
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_import_customer_from_date').css({'border':'1px solid #8c8f94'});
        jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_import_customer_to_date').css({'border':'1px solid #8c8f94'});
        
        if(dateFrom !='' && dateTo != ''){
            if(confirm('Are you sure you want to sync the customer with odoo? ')){
                    jQuery('#odoo_import_customer_sync').css({'display':'none'});
                    jQuery('#odoo_import_customer_sync_loading').css({'display':'inline-block'});
                    jQuery('.odoo_import_customer_sync_message').html('');
                    jQuery.ajax({
                         data: {action: 'odoo_import_customer_by_date', dateFrom:dateFrom,dateTo:dateTo, security:odoo_admin.ajax_nonce},
                         type: 'post',
                         url: ajaxurl,
                         success: function(data) {
                             var obj = jQuery.parseJSON(data);
                             if(obj.result == 'success'){
                                jQuery('#odoo_import_customer_sync').css({'display':'inline-block'});
                                jQuery('#odoo_import_customer_sync_loading').css({'display':'none'});   
                                jQuery('.odoo_import_customer_sync_message').html("<?php echo esc_html__('Customer has been successfully sync.', 'wc2odoo'); ?>");
                             }else{
                                jQuery('#odoo_import_customer_sync').css({'display':'inline-block'});
                                jQuery('#odoo_import_customer_sync_loading').css({'display':'none'});
                             }
                        }
                    });
            }   
        }else{
            if(dateFrom === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_import_customer_from_date').css({'border':'1px solid #c50a0a'});
            }
            if(dateTo === ''){
                jQuery('#woocommerce_woocommmerce_odoo_integration_odoo_import_customer_to_date').css({'border':'1px solid #c50a0a'});
            }
        }
    });
    
    
  });
</script>
