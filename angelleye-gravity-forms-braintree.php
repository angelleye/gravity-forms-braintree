<?php
/**
 * Plugin Name: Gravity Forms Braintree Payments
 * Plugin URI: https://angelleye.com/products/gravity-forms-braintree-payments
 * Description: Allow your customers to purchase goods and services through Gravity Forms via Braintree Payments.
 * Author: Angell EYE
 * Version: 4.0.7
 * Author URI: https://angelleye.com
 * Text Domain: angelleye-gravity-forms-braintree

 *************
 * Attribution
 *************
 * This plugin is a derivative work of the code from Plugify,
 * which is licensed with GPLv2.
 */

// Ensure WordPress has been bootstrapped
if( !defined( 'ABSPATH' ) ) {
    exit;
}

if (!defined('AEU_ZIP_URL')) {
    define('AEU_ZIP_URL', 'https://updates.angelleye.com/ae-updater/angelleye-updater/angelleye-updater.zip');
}

if (!defined('GRAVITY_FORMS_BRAINTREE_ASSET_URL')) {
    define('GRAVITY_FORMS_BRAINTREE_ASSET_URL', plugin_dir_url(__FILE__));
}

if (!defined('GRAVITY_FORMS_BRAINTREE_DIR_PATH')) {
    define('GRAVITY_FORMS_BRAINTREE_DIR_PATH', plugin_dir_path(__FILE__));
}

if (!defined('PAYPAL_FOR_WOOCOMMERCE_PUSH_NOTIFICATION_WEB_URL')) {
    define('PAYPAL_FOR_WOOCOMMERCE_PUSH_NOTIFICATION_WEB_URL', 'https://www.angelleye.com/');
}

require_once dirname(__FILE__) . '/includes/angelleye-gravity-braintree-activator.php';
require_once dirname(__FILE__) . '/includes/angelleye-plugin-requirement-checker.php';

class AngelleyeGravityFormsBraintree{

    protected static $instance = null;
    public static $plugin_base_file;
    public static $version = '4.0.7';

    public static function getInstance()
    {
        self::$plugin_base_file = plugin_basename(__FILE__);
        if(self::$instance==null)
            self::$instance = new AngelleyeGravityFormsBraintree();

        return self::$instance;
    }

    public function __construct()
    {
        register_activation_hook( __FILE__, array(AngelleyeGravityBraintreeActivator::class,"InstallDb") );
        register_deactivation_hook( __FILE__, array(AngelleyeGravityBraintreeActivator::class,"DeactivatePlugin") );
        register_uninstall_hook( __FILE__, array(AngelleyeGravityBraintreeActivator::class,'Uninstall'));

        add_action('plugins_loaded', [$this, 'requirementCheck']);
	    add_action( 'wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_gform_payment_preview_html', [ $this, 'gform_payment_preview_html']);
        add_action('wp_ajax_nopriv_gform_payment_preview_html', [ $this, 'gform_payment_preview_html']);
        add_action('gform_entry_field_value', [ $this, 'gform_entry_field_value'], 10, 4);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('gravity-forms-braintree', GRAVITY_FORMS_BRAINTREE_ASSET_URL . 'assets/css/gravity-forms-braintree-public.css');
	    wp_register_script('braintreegateway-dropin', "https://js.braintreegateway.com/web/dropin/1.26.0/js/dropin.min.js");
	    wp_enqueue_script('braintreegateway-dropin');
    }

	public function requirementCheck() {
		$checker = new Angelleye_Plugin_Requirement_Checker('Gravity Forms Braintree Payments', self::$version, self::$plugin_base_file);
		$checker->setPHP('7.2');
		$checker->setRequiredClasses(['GFForms' => 'The Gravity Forms plugin is required in order to run Gravity Forms Braintree Payments.']);
		$checker->setRequiredExtensions(['xmlwriter', 'openssl', 'dom', 'hash', 'curl']);
		$checker->setRequiredPlugins(['gravityforms/gravityforms.php'=>['min_version'=>'2.4', 'install_link'=>'https://rocketgenius.pxf.io/c/1331556/445235/7938', 'name'=>'Gravity Forms']]);
		//$checker->setDeactivatePlugins([self::$plugin_base_file]);
		if($checker->check()===true) {
			$this->init();
		}
    }

    public function init()
    {
        $path = trailingslashit( dirname( __FILE__ ) );

        // Ensure Gravity Forms (payment addon framework) is installed and good to go
        if( is_callable( array( 'GFForms', 'include_payment_addon_framework' ) ) ) {

            // Bootstrap payment addon framework
            GFForms::include_payment_addon_framework();
	        GFForms::include_addon_framework();

            // Require Braintree Payments core
	        if(!class_exists('Braintree')) {
		        require_once $path . 'lib/Braintree.php';
	        }

            // Require plugin entry point
	        require_once $path . 'includes/angelleye-gravity-braintree-helper.php';
            require_once $path . 'lib/class.plugify-gform-braintree.php';
	        require_once $path . 'includes/class-angelleye-gravity-braintree-ach-field.php';
	        require_once $path . 'includes/class-angelleye-gravity-braintree-ach-toggle-field.php';
	        require_once $path . 'lib/angelleye-gravity-forms-payment-logger.php';
            require_once $path . 'includes/angelleye-gravity-braintree-field-mapping.php';
            require_once $path . 'includes/class-angelleye-gravity-braintree-creditcard.php';

            /**
             * Required functions
             */
            if (!function_exists('angelleye_queue_update')) {
                require_once( 'includes/angelleye-functions.php' );
            }

            // Fire off entry point
            new Plugify_GForm_Braintree();
            new AngelleyeGravityBraintreeFieldMapping();

	        /**
	         * Register the ACH form field and Payment Method toggle field
	         */
	        GF_Fields::register( new Angelleye_Gravity_Braintree_ACH_Field() );
	        GF_Fields::register( new Angelleye_Gravity_Braintree_ACH_Toggle_Field() );
	        GF_Fields::register( new Angelleye_Gravity_Braintree_CreditCard_Field() );
            AngellEYE_GForm_Braintree_Payment_Logger::instance();
        }
    }

    public static function isBraintreeFeedActive()
    {
        global $wpdb;
        $addon_feed_table_name = $wpdb->prefix . 'gf_addon_feed';
        $is_active = $wpdb->get_var("select is_active from ".$addon_feed_table_name." where addon_slug='gravity-forms-braintree' and is_active=1");

        return $is_active=='1';
    }

    public function gform_payment_preview_html() {

        if( ! function_exists('angelleye_get_extra_fees') ) {
            require_once GRAVITY_FORMS_BRAINTREE_DIR_PATH.'includes/angelleye-functions.php';
        }

        $status = false;
        $preview_html = '';
        $extra_fees_enable = false;
        if( !empty( $_POST['nonce'] ) && wp_verify_nonce($_POST['nonce'],'preview-payment-nonce') ) {
            $card_type = !empty( $_POST['card_type'] ) ? $_POST['card_type'] : '';
            $form_id = !empty( $_POST['form_id'] ) ? $_POST['form_id'] : '';
            $form_data = !empty( $_POST['form_data'] ) ? $_POST['form_data']  : [];

            $extra_fees = angelleye_get_extra_fees( $form_id );
            $extra_fees_label = !empty( $extra_fees['title'] ) ? $extra_fees['title'] : '';

            if( empty( $extra_fees['is_fees_enable'] ) ) {

                if( $card_type === 'ACH' ) {

                    wp_send_json([
                        'status' => true,
                        'extra_fees_enable' => false,
                        'html' => ''
                    ]);
                } else {

                    wp_send_json([
                        'status' => false,
                        'html' => ''
                    ]);
                }
            }

            $status = true;
            $extra_fees_enable = true;

            $product_fields = get_product_fields_by_form_id( $form_id );
            $products = [];
            if( !empty( $product_fields['products'] ) && is_array( $product_fields['products'] ) ) {

                foreach ( $product_fields['products'] as $product_field ) {
                    $id = !empty( $product_field['id'] ) ? $product_field['id'] : '';
                    $label = !empty( $product_field['label'] ) ? $product_field['label'] : '';
                    $product_group = !empty( $product_field['group'] ) ? $product_field['group'] : '';
                    $price_id = !empty( $product_field['price_id'] ) ? $product_field['price_id'] : '';
                    $quantity_id = !empty( $product_field['quantity_id'] ) ? $product_field['quantity_id'] : '';

                    $product_price = 0;
                    $product_qty = 1;
                    if( !empty( $form_data ) && is_array( $form_data ) ) {
                        foreach ( $form_data as $data ) {
                            if( !empty( $data['name'] ) && $data['name'] == $price_id ) {
                                $product_price = !empty( $data['value'] ) ? get_price_without_fomatter( $data['value'] ) : '';
                                $label = !empty( $data['value'] ) ? get_selected_product_label( $data['value'], $label ) : '';
                            } elseif ( !empty( $data['name'] ) && $data['name'] == $quantity_id ) {
                                $product_qty = !empty( $data['value'] ) ? $data['value'] : 1;
                            }
                        }
                    }

                    $products[] = [
                        'id'    => $id,
                        'label' => $label,
                        'price' => $product_price,
                        'quantity' => $product_qty,
                    ];
                }
            }

            $cart_prices = get_gfb_prices([
                'form_id' => $form_id,
                'products' => $products,
                'card_type' => $card_type,
            ]);

            $extra_fee_amount = !empty( $cart_prices['extra_fee_amount'] ) ? $cart_prices['extra_fee_amount'] : '0';
            ob_start();
            ?>
            <div class="product-summary">
                <h2><?php esc_html_e('Product summary','angelleye-gravity-forms-braintree'); ?></h2>
                <table width="100%" border="1" cellpadding="0" cellspacing="0">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Items','angelleye-gravity-forms-braintree'); ?></th>
                        <th><?php esc_html_e('Price','angelleye-gravity-forms-braintree'); ?></th>
                        <th><?php esc_html_e('Qty','angelleye-gravity-forms-braintree'); ?></th>
                        <th><?php esc_html_e('Subtotal','angelleye-gravity-forms-braintree'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php
                        if( !empty( $products ) && is_array( $products ) ) {

                            foreach ( $products as $product) {

                                $product_title = !empty( $product['label'] ) ? $product ['label'] : esc_html__( 'Product', 'angelleye-gravity-forms-braintree' );
                                $product_price = !empty( $product['price'] ) ? $product ['price'] : '';
                                $product_quantity = !empty( $product['quantity'] ) ? $product ['quantity'] : '';

                                if( !empty( $product_price ) && !empty( $product_quantity ) ) {
                                    $subtotal = $product_price * $product_quantity;
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($product_title ); ?></td>
                                        <td><?php echo get_gfb_format_price( $product_price ); ?></td>
                                        <td><?php echo $product_quantity; ?></td>
                                        <td><?php echo get_gfb_format_price( $subtotal ); ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><?php esc_html_e('Subtotal','angelleye-gravity-forms-braintree'); ?></td>
                            <td><?php echo !empty( $cart_prices['subtotal'] ) ? $cart_prices['subtotal'] : '0.00'; ?></td>
                        </tr>
                        <tr>
                            <td colspan="3"><?php esc_html_e( "$extra_fees_label ({$extra_fee_amount}%)",'angelleye-gravity-forms-braintree'); ?></td>
                            <td><?php echo !empty( $cart_prices['convenience_fee'] ) ? $cart_prices['convenience_fee'] : '0.00'; ?></td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong><?php esc_html_e('Total','angelleye-gravity-forms-braintree'); ?></strong></td>
                            <td><?php echo !empty( $cart_prices['total'] ) ? $cart_prices['total'] : '0.00'; ?></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="summary-actions">
                    <button type="button" name="gform_payment_cancel_<?php echo $form_id; ?>" id="gform_payment_cancel_<?php echo $form_id; ?>"><?php esc_html_e('Cancel', 'angelleye-gravity-forms-braintree'); ?></button>
                    <button type="button" name="gform_payment_pay_<?php echo $form_id; ?>" id="gform_payment_pay_<?php echo $form_id; ?>"><?php esc_html_e('Pay Now', 'angelleye-gravity-forms-braintree'); ?></button>
                </div>
            </div>
            <?php
            $preview_html = ob_get_contents();
            ob_get_clean();
        }

        $response = [
            'status'=> $status,
            'html' => $preview_html,
            'extra_fees_enable' => $extra_fees_enable
        ];

        wp_send_json( $response);
    }

    public function gform_entry_field_value( $display_value, $field, $lead, $form ) {

        if( !empty( $display_value ) && is_array( $display_value ) && !empty( $field->type ) && $field->type === 'braintree_credit_card' ) {

            $field_inputs = !empty( $field['inputs'] ) ? $field['inputs'] : '';
            $value = '';
            if( !empty( $field_inputs ) && is_array( $field_inputs ) ) {
                foreach ( $field_inputs as $field_input ) {
                    $field_input_id = !empty( $field_input['id'] ) ? $field_input['id'] : '';
                    $field_input_label = !empty( $field_input['label'] ) ? $field_input['label'] : '';
                    $field_input_val = !empty( $display_value[$field_input_id] ) ? $display_value[$field_input_id] : '';
                    $value .= "<strong>{$field_input_label}</strong>: {$field_input_val}<br>";
                }
            }
            $display_value = $value;
        }
        return $display_value;
    }
}

AngelleyeGravityFormsBraintree::getInstance();