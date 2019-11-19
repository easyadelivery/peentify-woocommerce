<?php
/**
 * Plugin Name: Peentify Woocommerce
 * Plugin URI:  https://github.com/abderrazzak-oxa/peentify-woocommerce
 * Description: <em>Peentify</em> for link your peentify system to woocommerce store
 * Version:     1.0.0
 * Author:      Abderrazzak OXA
 * Author URI:  https://github.com/abderrazzak-oxa
 * License:     GPLv2 or later
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in tcheck if exists post peentify_responsible_id if not exhe hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * @package Peentify Woocommerce
 * @version 1.0.0
 */

/**
 * Link your peentify system with your woocommerce store
 *
 * Plugin for peentify.com system
 *
 * @since 1.0.0
 */
class PeentifyWooCommerce {

	/**
	 * Create responsible id field
	 *
	 * @version 1.0.0
	 * @since 1.0.0
	 */
	public function createResponsibleIdField()
	{
		$args = array(
			'id' => 'peentify_responsible_id',
			'label' => 'Peentify responsible ID',
			'class' => 'py-responsible-id-field',
			'desc_tip' => true,
			'description' => 'Enter The responsible ID from your peentify system',
		);
		woocommerce_wp_text_input( $args );
	}

	/**
	 * Save the responsible id value in meta data of order
	 *
	 * @param $product_id int
	 * @version 1.0.0
	 * @since 1.0.0
	 */
	function saveResponsibleIdField( $product_id ) {
		$product = wc_get_product( $product_id );

		# check if exists post peentify_responsible_id if not exist or it's not integer will be empty
		if ( isset( $_POST['peentify_responsible_id'] ) )
		    $py_responsible_id = is_integer($_POST['peentify_responsible_id'])? $_POST['peentify_responsible_id']: '';
		else
		    $py_responsible_id = '';

		# save the peentify_responsible_id meta
		$product->update_meta_data( 'peentify_responsible_id', $py_responsible_id );
		$product->save();
	}

	/**
	 * Create integrate with peentify field
	 *
	 * @version 1.0.0
	 * @since 1.0.0
	 */
	public function createIntegrateWithPeentifyCheckBox()
	{
		$args = array(
			'id' => 'peentify_integrate_with_system',
			'label' => 'Integrate With Peentify',
			'class' => 'py-integrate-with-peentify-checkbox',
			'desc_tip' => true,
		);
		woocommerce_wp_checkbox( $args );
	}

	/**
	 * Save the integrate with peentify value in meta data of order
	 *
	 * @param $product_id int
	 * @version 1.0.0
	 * @since 1.0.0
	 */
	function saveIntegrateWithPeentifyCheckBox( $product_id ) {
		$product = wc_get_product( $product_id );

		# check if exists post peentify_responsible_id if not exist or it's not integer will be empty
		if (isset( $_POST['peentify_integrate_with_system'] ))
			$py_integrate_with_system = (boolean) $_POST['peentify_integrate_with_system'];
		else
			$py_integrate_with_system = false;

		# save the peentify_integrate_with_system meta
		$product->update_meta_data( 'peentify_integrate_with_system', $py_integrate_with_system );
		$product->save();
	}

	/**
	 * After checkout created order
	 *
	 * @param $order WC_Order
	 * @version 1.0.0
	 * @since 1.0.0
     * @return void
	 */
	public function afterCheckoutCreateOrder($order) {

	    # check if WC_Order class is exists and if $order is instance of WC_Order class
	    if (class_exists('WC_Order')) {
			if (!$order instanceof WC_Order) return;
        } else {
			return;
        }

		# check if peentify status is not active
	    if (!get_option('peentify_status'))
	        return;


	    $products = $order->get_items();
		foreach ($products as $item) {
			$product = wc_get_product($item->get_product());

			if (!$product->get_meta('peentify_integrate_with_system') || !$product->get_sku()) {
				continue;
			}

			# All of this fields is already validated and sanitized with woocommerce.
			$body = [
				'name' => sanitize_text_field($order->get_formatted_billing_full_name()?$order->get_formatted_billing_full_name():$order->get_formatted_shipping_address()),
				'address' => sanitize_text_field($order->has_billing_address()?$order->get_billing_address_1():$order->get_shipping_address_1()),
				'phone' => sanitize_text_field($order->get_billing_phone()),
				'city' => sanitize_text_field($order->get_billing_city()),
				'customer_note' => sanitize_text_field($order->get_customer_note()),
				'email' => sanitize_email($order->get_billing_email()),
				'quantity' => (int) $item->get_quantity(),
				'total_price' => sanitize_text_field($product->get_price() - ($order->get_total_discount()/ count($products))),
				'product_id' => sanitize_text_field($product->get_sku()),
				'customer_ip_address' => sanitize_text_field($order->get_customer_ip_address()),
				'customer_user_agent' => sanitize_text_field($order->get_customer_user_agent()),
				'responsible_id' => $product->get_meta('peentify_responsible_id')? (int) $product->get_meta('peentify_responsible_id'): '',
			];

			$key = get_option('peentify_api_key');
			$secret = get_option('peentify_api_secret');
			$base_url = trim(get_option('peentify_main_url'), '/');
			$url = $base_url . "/admin/api/orders?key=$key&secret=$secret";

			# sent post data to peentify system
			$response = wp_remote_post($url, [
				'body' => $body,
                'timeout' => 15
			]);

			# get the body of response
			$response_body = json_decode($response['body']);

			# unset the variables
			unset($response, $key, $secret, $base_url, $url, $body, $product);

			# check if the order is stored
			if (!isset($response_body->status) || $response_body->status != 'success')
			    die(json_encode([
					'message' => 'Error processing checkout. Please try again.',
					'status' => 'error',
                    'error_response' => $response_body
				]));
		}
	}

	/**
	 * add actions
	 */
	public function addActions()
	{
		add_action( 'admin_init', [$this, 'checkIfWooCommerceIsActivated'] );

		add_action( 'woocommerce_product_options_general_product_data', [$this, 'createIntegrateWithPeentifyCheckBox'] );

		add_action( 'woocommerce_process_product_meta', [$this, 'saveIntegrateWithPeentifyCheckBox'] );

		add_action( 'woocommerce_product_options_general_product_data', [$this, 'createResponsibleIdField'] );

		add_action( 'woocommerce_process_product_meta', [$this, 'saveResponsibleIdField'] );

		add_action('woocommerce_checkout_create_order', [$this, 'afterCheckoutCreateOrder']);

	}

	/**
	 * add admin settings page
	 *
	 */
	public function addAdminSettingsPage()
	{
		add_action( 'admin_init', [$this, 'registerSettings'] );

        add_action( 'admin_menu', [$this, 'registerOptionsPage']);
	}


	/**
	 * register options page
	 *
	 */
	public function registerSettings() {
	    # add options
		add_option( 'peentify_main_url', '');
		add_option( 'peentify_api_key', '');
		add_option( 'peentify_api_secret', '');
		add_option( 'peentify_status', 0);

		# register settings
		register_setting( 'peentify_settings_group', 'peentify_main_url', [
		        'sanitize_callback' => 'esc_url',
		]);
		register_setting( 'peentify_settings_group', 'peentify_api_key', [
		    'sanitize_callback' => 'sanitize_text_field',
		]);
		register_setting( 'peentify_settings_group', 'peentify_api_secret', [
			'sanitize_callback' => 'sanitize_text_field',
		]);
		register_setting( 'peentify_settings_group', 'peentify_status', [
			'sanitize_callback' => 'rest_sanitize_boolean',
		]);
	}

	/**
	 * register options page
	 *
	 */
	public function registerOptionsPage() {
		add_options_page('Peentify Settings', 'Peentify', 'manage_options', 'peentify-settings', [$this, 'peentifyAdminPageCallback']);
	}

	/**
     * admin options page
     */
	public function peentifyAdminPageCallback(){
		?>
		<div class="wrap">
			<h1>Peentify Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'peentify_settings_group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="peentify_main_url">Main URL</label></th>
						<td><input type="text" id="peentify_main_url" name="peentify_main_url" value="<?php echo esc_url( get_option('peentify_main_url') ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="peentify_api_key">API Key</label></th>
						<td><input type="text" id="peentify_api_key" name="peentify_api_key" value="<?php echo esc_attr( get_option('peentify_api_key') ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="peentify_api_secret">API Secret</label></th>
						<td><input type="text" id="peentify_api_secret" name="peentify_api_secret" value="<?php echo esc_attr( get_option('peentify_api_secret') ); ?>" class="regular-text" /></td>
					</tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <fieldset><legend class="screen-reader-text"><span>Status</span></legend>
                                <label for="peentify_status">
                                    <input name="peentify_status" type="checkbox" id="peentify_status" <?php echo rest_sanitize_boolean( get_option('peentify_status') )? 'checked': ''?>>
                                    Enable
                                </label>
                            </fieldset>
                        </td>
                    </tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
	<?php }

	/**
     * check if woocommerce is activated
     */
	public function checkIfWooCommerceIsActivated()
	{
		if (! class_exists( 'woocommerce' ) ) {
			add_action('admin_notices', [$this, 'notActivatedWooCommerceNotice']);
		}
	}

	/**
	 * Not Activated Woocommerce Notice
     */
	public function notActivatedWooCommerceNotice()
	{
		echo '<div class="notice notice-error"><p>WooCommerce is not activated, please activate it to use <b>Peentify WooCommerce Plugin</b></p></div>';
	}

	/**
	 * init
     */
	public function init()
	{
		$this->addActions();
		$this->addAdminSettingsPage();
	}

}

$peentify = new PeentifyWooCommerce();
$peentify->init();
