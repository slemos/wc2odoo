<?php
/**
 * @file setting-form-fields.php - WooCommerce to Odoo Integration
 *
 * @package wc2odoo
 */

$val     = array();
// xdebug_break();
$mapping = $this->get_option( 'tax_account_mapping' );
if ( is_array( $mapping ) ) {
	foreach ( $mapping as $key => $value ) {
		[$option, $field] = explode( '_', $value );
		$val[ $field ]    = $option;
	}
}
?>
<?php foreach ( $taxes as $key => $odoo_tax ) { ?>
	<tr valign="top" class="tax_account_row">
		<th scope="row" class="titledesc">
			<label for="woocommerce_woocommmerce_odoo_integration_tax_account_mapping">Select Account For tax <?php echo esc_attr( $odoo_tax['name'] ); ?> </label>
		</th>
		<td class="forminp">
			<fieldset>
				<legend class="screen-reader-text"><span>Select Account For tax <?php echo esc_html( $odoo_tax['name'] ); ?></span></legend>
				<select data-id="<?php echo esc_attr( $odoo_tax['id'] ); ?>" class="select" id="woocommerce_woocommmerce_odoo_integration_tax_account_mapping[<?php echo esc_attr( $odoo_tax['id'] ); ?>]" style="" required>
					<option value="">Please select</option>
				<?php foreach ( $accounts as $account_key => $odoo_account ) { ?>
					<?php if ( isset( $val[ $odoo_tax['id'] ] ) && $val[ $odoo_tax['id'] ] === $account_key ) { ?>
					<option value="<?php echo esc_attr( $account_key ); ?>" selected><?php echo esc_html( $odoo_account ); ?></option>
					<?php } else { ?>
					<option value="<?php echo esc_attr( $account_key ); ?>" ><?php echo esc_html( $odoo_account ); ?></option>
					<?php } ?>
				<?php } ?>
				</select>
				<p class="description">Select Account For tax <?php echo esc_html( $odoo_tax['name'] ); ?></p>
			</fieldset>
		</td>
	</tr>
<?php } ?>

<?php

$return_val     = array();
$return_mapping = $this->get_option( 'return_tax_account_mapping' );
if ( is_array( $return_mapping ) ) {
	foreach ( $return_mapping as $r_key => $return_map ) {
		[$r_option, $r_field]   = explode( '_', $return_map );
		$return_val[ $r_field ] = $r_option;
	}
}
?>
<?php foreach ( $taxes as $key => $odoo_tax ) { ?>
	<tr valign="top" class="tax_account_row">
		<th scope="row" class="titledesc">
			<label for="woocommerce_woocommmerce_odoo_integration_return_tax_account_mapping">Select Refund Account For tax <?php echo esc_html( $odoo_tax['name'] ); ?> </label>
		</th>
		<td class="forminp">
			<fieldset>
				<legend class="screen-reader-text"><span>Select Refund Account For tax <?php echo esc_html( $odoo_tax['name'] ); ?></span></legend>
				<select data-id="<?php echo esc_attr( $odoo_tax['id'] ); ?>" class="select" id="woocommerce_woocommmerce_odoo_integration_return_tax_account_mapping[<?php echo esc_attr( $odoo_tax['id'] ); ?>]" style="" required>
					<option value="">Please select</option>
				<?php foreach ( $accounts as $account_key => $odoo_account ) { ?>
					<?php if ( isset( $return_val[ $odoo_tax['id'] ] ) && $return_val[ $odoo_tax['id'] ] === $account_key ) { ?>
					<option value="<?php echo esc_attr( $account_key ); ?>" selected><?php echo esc_html( $odoo_account ); ?></option>
					<?php } else { ?>
					<option value="<?php echo esc_attr( $account_key ); ?>" ><?php echo esc_html( $odoo_account ); ?></option>
					<?php } ?>
				<?php } ?>
				</select>
				<p class="description">Select Refund Account For tax <?php echo esc_html( $odoo_tax['name'] ); ?></p>
			</fieldset>
		</td>
	</tr>
<?php } ?>
