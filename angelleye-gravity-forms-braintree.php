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
	    add_action( 'gform_form_settings_fields', [$this, 'gform_form_settings_fields']);
        add_action( 'gform_form_settings_fields', [$this, 'extra_fees_form_fields']);
        add_action('wp_ajax_gform_payment_preview_html', [ $this, 'gform_payment_preview_html']);
        add_action('wp_ajax_nopriv_gform_payment_preview_html', [ $this, 'gform_payment_preview_html']);
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

    /**
     * Added Merchant Account setting fields in Gravity form.
     *
     * @param array $fields get fields.
     * @return array $fields
     */
    public function gform_form_settings_fields( $fields ) {

        $merchant_settings = [
            'title'      => esc_html__( 'Merchant Account Settings', 'angelleye-gravity-forms-braintree' ),
            'fields' => [
                [
                    'name'          => 'sub_merchant_account_id',
                    'label'         => esc_html__( 'Merchant Account ID', 'angelleye-gravity-forms-braintree' ),
                    'type'          => 'select',
                    'choices'       => $this->merchant_account_choices(),
                    'required'      => false,
                    'default_value' => '',
                    'tooltip'       => ''
                ]
            ],
        ];

        $setting_key = 'merchant_account';

        if (! empty( $fields['form_button'] ) ) {

            $new_fields = [];
            foreach ( $fields as $key => $field ) {

                if( $key === 'form_button' ) {

                    $new_fields[$setting_key] = $merchant_settings;

                }

                $new_fields[$key] = $field;
            }

            $fields = $new_fields;
        } else {
            $fields[$setting_key] = $merchant_settings;
        }

        return $fields;
    }

    /**
     * Get all sub merchant accounts using primary account.
     *
     * @return array $merchant_accounts
     */
    public function merchant_account_choices() {

        $merchant_accounts = [
            [
                'label' => esc_html__( 'Select Merchant ID', 'angelleye-gravity-forms-braintree' ),
                'value' => ''
            ]
        ];

        try {

            $gform_braintree = new Plugify_GForm_Braintree();
            $gateway = $gform_braintree->getBraintreeGateway();

            if ( ! empty( $gateway ) ) {

                $subMerchantAccounts = $gateway->merchantAccount()->all();

                foreach ( $subMerchantAccounts as $account ) {

                    $account_id = !empty( $account->id ) ? $account->id : '';
                    $account_currency = !empty( $account->currencyIsoCode ) ? $account->currencyIsoCode : '';
                    $merchant_accounts[] = [
                        'label' => sprintf('%s - [%s]', $account_id, $account_currency),
                        'value' => $account_id
                    ];
                }
            }
        } catch (Exception $exception) {

        }

        return $merchant_accounts;
    }

    public function extra_fees_form_fields( $fields ) {

        $extra_fee_settings = [
            'title' => esc_html__('Extra Fees Settings', 'angelleye-gravity-forms-braintree'),
            'fields' => [
                [
                    'name' => 'override_extra_fees',
                    'type' => 'toggle',
                    'label' => esc_html__('Override Global Settings', 'angelleye-gravity-forms-braintree'),
                    'default_value' => false,
                    'tooltip' => '<strong>' . __('Override Global Settings', 'angelleye-gravity-forms-braintree') . '</strong>' . __('Override the global extra fees settings.', 'angelleye-gravity-forms-braintree'),
                ],
                [
                    'name' => 'credit_card_fees',
                    'type' => 'text',
                    'input_type' => 'number',
                    'label' => esc_html__('Credit Card', 'angelleye-gravity-forms-braintree'),
                    'tooltip' => '',
                    'required' => false,
                    'min' => 0,
                    'class' => 'extra-fees-input',
                    'dependency' => [
                        'live' => true,
                        'fields' => [
                            [
                                'field' => 'override_extra_fees',
                            ]
                        ],
                    ],
                ],
                [
                    'name' => 'debit_card_fees',
                    'type' => 'text',
                    'input_type' => 'number',
                    'label' => esc_html__('Debit Card', 'angelleye-gravity-forms-braintree'),
                    'tooltip' => '',
                    'required' => false,
                    'min' => 0,
                    'class' => 'extra-fees-input',
                    'dependency' => [
                        'live' => true,
                        'fields' => [
                            [
                                'field' => 'override_extra_fees',
                            ]
                        ],
                    ],
                ],
                [
                    'name' => 'ach_fees',
                    'type' => 'text',
                    'input_type' => 'number',
                    'label' => esc_html__('ACH', 'angelleye-gravity-forms-braintree'),
                    'tooltip' => '',
                    'required' => false,
                    'min' => 0,
                    'class' => 'extra-fees-input',
                    'dependency' => [
                        'live' => true,
                        'fields' => [
                            [
                                'field' => 'override_extra_fees',
                            ]
                        ],
                    ],
                ],
                [
                    'name' => 'disable_extra_fees',
                    'type' => 'checkbox',
                    'label' => '',
                    'tooltip' => '',
                    'required' => false,
                    'min' => 0,
                    'choices' => [
                        [
                            'name' => 'disable_extra_fees',
                            'label' => esc_html__('Disable Extra Fees', 'angelleye-gravity-forms-braintree'),
                        ]
                    ],
                    'dependency' => [
                        'live' => true,
                        'fields' => [
                            [
                                'field' => 'override_extra_fees',
                            ]
                        ],
                    ],
                ],
            ],
        ];

        $setting_key = 'extra_fees';

        if (! empty( $fields['form_button'] ) ) {

            $new_fields = [];
            foreach ( $fields as $key => $field ) {

                if( $key === 'form_button' ) {

                    $new_fields[$setting_key] = $extra_fee_settings;

                }

                $new_fields[$key] = $field;
            }

            $fields = $new_fields;
        } else {
            $fields[$setting_key] = $extra_fee_settings;
        }

        return $fields;
    }

    public function gform_payment_preview_html() {

        $status = false;
        $preview_html = '';
        $extra_fees_enable = false;
        if( !empty( $_POST['nonce'] ) && wp_verify_nonce($_POST['nonce'],'preview-payment-nonce') ) {
            $card_type = !empty( $_POST['card_type'] ) ? $_POST['card_type'] : '';
            $form_id = !empty( $_POST['form_id'] ) ? $_POST['form_id'] : '';
            $form_data = !empty( $_POST['form_data'] ) ? $_POST['form_data']  : [];

            $extra_fees = angelleye_get_extra_fees( $form_id );

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
            $product_group = !empty( $product_fields['group'] ) ? $product_fields['group'] : '';
            $price_id = !empty( $product_fields['price_id'] ) ? $product_fields['price_id'] : '';
            $quantity_id = !empty( $product_fields['quantity_id'] ) ? $product_fields['quantity_id'] : '';

            $product_price = 0;
            $product_qty = 1;
            if( !empty( $form_data ) && is_array( $form_data ) ) {
                foreach ( $form_data as $key => $data ) {
                    if( !empty( $data['name'] ) && $data['name'] == $price_id ) {
                        $product_price = !empty( $data['value'] ) ? get_price_without_fomatter( $data['value'] ) : '';
                    } elseif ( !empty( $data['name'] ) && $data['name'] == $quantity_id ) {
                        $product_qty = !empty( $data['value'] ) ? $data['value'] : 1;
                    }
                }
            }

            $cart_prices = get_gfb_prices([
                'form_id' => $form_id,
                'product_price' => $product_price,
                'product_qty' => $product_qty,
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
                    <tr>
                        <td><?php esc_html_e('Product','angelleye-gravity-forms-braintree'); ?></td>
                        <td><?php echo !empty( $cart_prices['product_price'] ) ? $cart_prices['product_price'] : '0.00'; ?></td>
                        <td><?php echo !empty( $cart_prices['product_qty'] ) ? $cart_prices['product_qty'] : '0'; ?></td>
                        <td><?php echo !empty( $cart_prices['subtotal'] ) ? $cart_prices['subtotal'] : '0.00'; ?></td>
                    </tr>
                    <tr>
                        <td colspan="3"><?php esc_html_e('Convenience Fee ('.$extra_fee_amount.'%)','angelleye-gravity-forms-braintree'); ?></td>
                        <td><?php echo !empty( $cart_prices['convenience_fee'] ) ? $cart_prices['convenience_fee'] : '0.00'; ?></td>
                    </tr>
                    </tbody>
                    <tfoot>
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
}

AngelleyeGravityFormsBraintree::getInstance();