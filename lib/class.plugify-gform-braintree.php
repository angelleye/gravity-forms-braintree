<?php

// Plugify_GForm_Braintree class

final class Plugify_GForm_Braintree extends GFPaymentAddOn {

    protected $_version = '5.0.1';
    protected $_min_gravityforms_version = '1.8.7.16';
    protected $_slug = 'gravity-forms-braintree';
    protected $_path = 'gravity-forms-braintree/lib/class.plugify-gform-braintree.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Braintree';
    protected $_short_title = 'Braintree';
    protected $_requires_credit_card = true;
    protected $_supports_callbacks = true;
    protected $_enable_rg_autoupgrade = true;
    protected $is_payment_gateway = true;
    protected $current_feed = true;
    protected $selected_payment_method = 'braintree_credit_card';

    /**
     * Class constructor. Send __construct call to parent
     * @since 1.0
     * @return void
     */
    public function __construct() {

        add_action('wp_ajax_angelleye_gform_braintree_adismiss_notice', array($this, 'angelleye_gform_braintree_adismiss_notice'), 10);
        add_action('admin_notices', array($this, 'angelleye_gform_braintree_display_push_notification'), 10);

        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles_css'), 10);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_js'), 10);
        add_filter('gform_noconflict_scripts', [$this, 'include_angelleye_braintree_script_noconflict']);
        add_filter('gform_noconflict_styles', [$this, 'include_angelleye_braintree_style_noconflict']);
        add_filter('angelleye_braintree_parameter', [$this,'manage_braintree_request_parameter'], 10, 4);

        // Build parent
        parent::__construct();
    }

    /**
     * Override credit card field check, so that we can return true when someone has ach form
     * If any user will have any form then same payment gateway class will be used
     * @param array $form
     *
     * @return bool
     */
    public function has_credit_card_field($form) {
        if (isset($form['fields'])) {
            foreach ($form['fields'] as $single_field) {
                if ($single_field->type == 'creditcard' || $single_field->type == 'braintree_ach' || $single_field->type == 'braintree_credit_card') {
                    return true;
                }
            }
        }
        return $this->get_credit_card_field($form) !== false;
    }

    /**
     * Override default message for Gravity Form Braintree Feeds
     * @return string
     */
    public function requires_credit_card_message() {
        $url = add_query_arg(array('view' => null, 'subview' => null));

        return sprintf(esc_html__("You must add a Credit Card/ACH Payment field to your form before creating a feed. Let's go %sadd one%s!", 'gravityforms'), "<a href='" . esc_url($url) . "'>", '</a>');
    }

    /**
     * Override init_frontend to assign front end based filters and actions required for operation
     *
     * @since 1.0
     * @return void
     */
    public function init_frontend() {

        // init_frontend on GFPaymentAddOn
        parent::init_frontend();
    }

    /**
     * Init the Braintree configuration and return gateway for transactions, etc.
     * @return bool|\Braintree\Gateway
     * @throws \Braintree\Exception\Configuration
     */
    public function getBraintreeGateway() {
        $settings = $this->get_plugin_settings();
        if (!$settings)
            return false;

        // Configure Braintree environment
        $braintree_config = new \Braintree\Configuration([
            'environment' => strtolower($settings['environment']),
            'merchantId' => $settings['merchant-id'],
            'publicKey' => $settings['public-key'],
            'privateKey' => $settings['private-key']
        ]);

        $braintree_config->timeout(60);

        $gateway = new Braintree\Gateway($braintree_config);
        return $gateway;
    }

    /**
     * ACH Payment authorization
     * @param $feed
     * @param $submission_data
     * @param $form
     * @param $entry
     *
     * @return array|bool
     * @throws \Braintree\Exception\Configuration
     */
    public function ach_authorize($feed, $submission_data, $form, $entry) {
        $this->log_debug("Braintree_ACH_Authorize::START");
        $gateway = $this->getBraintreeGateway();
        if ($gateway) {
            try {
                // Prepare authorization response payload
                $authorization = array(
                    'is_authorized' => false,
                    'error_message' => apply_filters('gform_braintree_credit_card_failure_message', __('We are unable to authorize the bank account, Please try again.', 'gravity-forms-braintree')),
                    'transaction_id' => '',
                    'captured_payment' => array(
                        'is_success' => false,
                        'error_message' => '',
                        'transaction_id' => '',
                        'amount' => $submission_data['payment_amount']
                    )
                );

                $ach_device_corelation = rgpost('ach_device_corelation');
                $ach_token = rgpost('ach_token');
                $payment_amount = number_format($submission_data['payment_amount'], 2, '.', '');

                $settings = $this->get_plugin_settings();
                $response = getAngelleyeBraintreePaymentFields($form);
                $braintree_ach_field = $response['braintree_ach'];
                /* $account_number = rgpost( 'input_' . $braintree_ach_field->id . '_1' );
                  $account_type = rgpost( 'input_' . $braintree_ach_field->id . '_2' );
                  $routing_number = rgpost( 'input_' . $braintree_ach_field->id . '_3' ); */
                $account_holder_name = rgpost('input_' . $braintree_ach_field->id . '_4');

                $account_holder_name = explode(' ', $account_holder_name);
                /**
                 * Create customer in Braintree
                 */
                $customer_request = [
                    'firstName' => @$account_holder_name[0],
                    'lastName' => end($account_holder_name),
                ];
                $this->log_debug("Braintree_ACH_Customer::create REQUEST => " . print_r($customer_request, 1));
                $customer_result = $gateway->customer()->create($customer_request);
                $this->log_debug("Braintree_ACH_Customer::create RESPONSE => " . print_r($customer_result, 1));

                if ($customer_result->success) {
                    $payment_method_request = [
                        'customerId' => $customer_result->customer->id,
                        'paymentMethodNonce' => $ach_token,
                        'options' => [
                            'usBankAccountVerificationMethod' => Braintree\Result\UsBankAccountVerification::NETWORK_CHECK
                        ]
                    ];

                    $this->log_debug("Braintree_ACH_PaymentRequest::create REQUEST => " . print_r($payment_method_request, 1));
                    $payment_method_response = $gateway->paymentMethod()->create($payment_method_request);
                    $this->log_debug("Braintree_ACH_PaymentRequest::create RESPONSE => " . print_r($payment_method_response, 1));

                    if (isset($payment_method_response->paymentMethod->token)) {

                        $sale_request = [
                            'amount' => $payment_amount,
                            'paymentMethodToken' => $payment_method_response->paymentMethod->token,
                            'deviceData' => $ach_device_corelation,
                            'options' => [
                                'submitForSettlement' => true
                            ]
                        ];

                        $sale_request = apply_filters('angelleye_braintree_parameter', $sale_request, $submission_data, $form, $entry);

                        $this->log_debug("Braintree_ACH_Transaction::sale REQUEST => " . print_r($sale_request, 1));
                        $sale_response = $gateway->transaction()->sale($sale_request);
                        $this->log_debug("Braintree_ACH_Transaction::sale RESPONSE => " . print_r($sale_response, 1));

                        do_action('angelleye_braintree_transaction_response', $sale_response, $submission_data, $form, $entry );

                        if ($sale_response->success) {
                            do_action('angelleye_gravity_forms_response_data', $sale_response, $submission_data, '16', ( strtolower($settings['environment']) == 'sandbox' ) ? true : false, false, 'braintree_ach');
                            $authorization['is_authorized'] = true;
                            $authorization['error_message'] = '';
                            $authorization['transaction_id'] = $sale_response->transaction->id;

                            $authorization['captured_payment'] = array(
                                'is_success' => true,
                                'transaction_id' => $sale_response->transaction->id,
                                'amount' => $sale_response->transaction->amount,
                                'error_message' => '',
                                'payment_method' => 'Braintree ACH'
                            );

                            $this->log_debug("Braintree_ACH::SUCCESS");
                        } else {

                            $processorResponseText = !empty( $sale_response->message ) ? $sale_response->message : '';
                            if( !empty( $sale_response->transaction->processorResponseText ) && strtolower( $sale_response->transaction->processorResponseText ) !== 'unavailable' ) {
                                $processorResponseText = $sale_response->transaction->processorResponseText;
                            }

                            if ( !empty( $processorResponseText ) ) {
                                $authorization['error_message'] = sprintf('Your bank did not authorized the transaction: %s.', $processorResponseText);
                            } else {
                                $authorization['error_message'] = sprintf('Your bank declined the transaction, please try again or contact bank.');
                            }
                            $this->log_debug("Braintree_ACH::FAILED_ERROR");
                        }
                    } else {
                        $authorization['error_message'] = __('We are unable to authorize bank account, This may have happened due to expired token, please try again.', 'gravity-forms-braintree');
                    }
                } else {
                    $authorization['error_message'] = __('Unable to proceed with the transaction due to invalid name.', 'gravity-forms-braintree');
                }
            } catch (Exception $exception) {
                $this->log_debug("Braintree_ACH::EXCEPTION: " . $exception->getTraceAsString());
                $exception['error_message'] = __('An internal error occurred, Please try later. ERROR: ' . $exception->getMessage());
            }
            return $authorization;
        }

        $this->log_debug("Braintree_ACH::FAILED");
        return false;
    }

    /**
     * Gets the payment validation result.
     *
     * @since  Unknown
     * @access public
     *
     * @used-by GFPaymentAddOn::validation()
     *
     * @param array $validation_result    Contains the form validation results.
     * @param array $authorization_result Contains the form authorization results.
     *
     * @return array The validation result for the credit card field.
     */
    public function get_validation_result($validation_result, $authorization_result) {

        $credit_card_page = 0;
        if ($this->selected_payment_method == 'braintree_ach') {
            foreach ($validation_result['form']['fields'] as &$field) {
                if ($field->type == 'braintree_ach') {
                    $field->failed_validation = true;
                    $field->validation_message = $authorization_result['error_message'];
                    $credit_card_page = $field->pageNumber;
                    break;
                }
            }
        } elseif ( $this->selected_payment_method === 'braintree_credit_card') {
            foreach ($validation_result['form']['fields'] as &$field) {
                if ($field->type == 'braintree_credit_card') {
                    $field->failed_validation = true;
                    $field->validation_message = $authorization_result['error_message'];
                    $credit_card_page = $field->pageNumber;
                    break;
                }
            }
        } else {
            foreach ($validation_result['form']['fields'] as &$field) {
                if ($field->type == 'creditcard') {
                    $field->failed_validation = true;
                    $field->validation_message = $authorization_result['error_message'];
                    $credit_card_page = $field->pageNumber;
                    break;
                }
            }
        }
        $validation_result['credit_card_page'] = $credit_card_page;
        $validation_result['is_valid'] = false;

        return $validation_result;
    }

    /**
     * Braintree credit card Payment authorization
     * @param $feed
     * @param $submission_data
     * @param $form
     * @param $entry
     *
     * @return array|bool
     * @throws \Braintree\Exception\Configuration
     */
    public function braintree_cc_authorize($feed, $submission_data, $form, $entry) {
        try {
            $settings = $this->get_plugin_settings();
            $gateway = $this->getBraintreeGateway();
            if ($gateway) {
                $authorization = array(
                    'is_authorized' => false,
                    'error_message' => apply_filters('gform_braintree_credit_card_failure_message', __('Your card could not be billed. Please ensure the details you entered are correct and try again.', 'gravity-forms-braintree')),
                    'transaction_id' => '',
                    'captured_payment' => array(
                        'is_success' => false,
                        'error_message' => '',
                        'transaction_id' => '',
                        'amount' => $submission_data['payment_amount']
                    )
                );
                if (empty($_POST['payment_method_nonce'])) {
                    return $authorization;
                }
                $args = array(
                    'amount' => $submission_data['payment_amount'],
                    'paymentMethodNonce' => $_POST['payment_method_nonce']
                );

                if ($settings['settlement'] == 'Yes') {
                    $args['options']['submitForSettlement'] = 'true';
                }

                $args = apply_filters('angelleye_braintree_parameter', $args, $submission_data, $form, $entry);

                $this->log_debug("Braintree_CC_Transaction::sale REQUEST => " . print_r($args, 1));

                $result = $gateway->transaction()->sale($args);

                $this->log_debug("Braintree_CC_Transaction::sale RESPONSE => " . print_r($result, 1));

                do_action('angelleye_braintree_transaction_response', $result, $submission_data, $form, $entry );

                if ($result->success) {
                    do_action('angelleye_gravity_forms_response_data', $result, $submission_data, '16', (strtolower($settings['environment']) == 'sandbox') ? true : false, false, 'braintree');
                    $authorization['is_authorized'] = true;
                    $authorization['error_message'] = '';
                    $authorization['transaction_id'] = $result->transaction->id;
                    $authorization['captured_payment'] = array(
                        'is_success' => true,
                        'transaction_id' => $result->transaction->id,
                        'amount' => $result->transaction->amount,
                        'error_message' => '',
                        'payment_method' => 'Credit Card'
                    );
                } else {

                    $processorResponseText = !empty( $result->message ) ? $result->message : '';
                    if( !empty( $result->transaction->processorResponseText ) && strtolower( $result->transaction->processorResponseText ) !== 'unavailable' ) {
                        $processorResponseText = $result->transaction->processorResponseText;
                    }

                    if ( !empty( $processorResponseText ) ) {
                        $authorization['error_message'] .= sprintf('. Your bank said: %s.', $processorResponseText);
                    }
                }
            }
        } catch (Exception $e) {
            // Do nothing with exception object, just fallback to generic failure
        }
        return $authorization;
    }

    /**
     * After form has been submitted, send CC details to Braintree and ensure the card is going to work
     * If not, void the validation result (processed elsewhere) and have the submit the form again
     *
     * @param $feed - Current configured payment feed
     * @param $submission_data - Contains form field data submitted by the user as well as payment information (i.e. payment amount, setup fee, line items, etc...)
     * @param $form - Current form array containing all form settings
     * @param $entry - Current entry array containing entry information (i.e data submitted by users). NOTE: the entry hasn't been saved to the database at this point, so this $entry object does not have the "ID" property and is only a memory representation of the entry.
     * @return array - Return an $authorization array in the following format:
     * [
     *  "is_authorized" => true|false,
     *  "error_message" => "Error message",
     *  "transaction_id" => "XXX",
     *
     *  //If the payment is captured in this method, return a "captured_payment" array with the following information about the payment
     *  "captured_payment" => ["is_success"=>true|false, "error_message" => "error message", "transaction_id" => "xxx", "amount" => 20]
     * ]
     * @since 1.0
     * @return void
     */
    public function authorize($feed, $submission_data, $form, $entry) {
        $this->selected_payment_method = getAngelleyeBraintreePaymentMethod($form);
        if ($this->selected_payment_method == 'braintree_ach') {
            return $this->ach_authorize($feed, $submission_data, $form, $entry);
        }
        if ($this->selected_payment_method == 'braintree_credit_card') {
            return $this->braintree_cc_authorize($feed, $submission_data, $form, $entry);
        }
        // Prepare authorization response payload
        $authorization = array(
            'is_authorized' => false,
            'error_message' => apply_filters('gform_braintree_credit_card_failure_message', __('Your card could not be billed. Please ensure the details you entered are correct and try again.', 'gravity-forms-braintree')),
            'transaction_id' => '',
            'captured_payment' => array(
                'is_success' => false,
                'error_message' => '',
                'transaction_id' => '',
                'amount' => $submission_data['payment_amount']
            )
        );
        // Perform capture in this function. For this version, we won't authorize and then capture later
        // at least, not in this version
        if ($settings = $this->get_plugin_settings()) {
            // Sanitize card number, removing dashes and spaces
            $card_number = str_replace(array('-', ' '), '', $submission_data['card_number']);
            // Prepare Braintree payload
            $args = array(
                'amount' => $submission_data['payment_amount'],
                'creditCard' => array(
                    'number' => $card_number,
                    'expirationDate' => sprintf('%s/%s', $submission_data['card_expiration_date'][0], $submission_data['card_expiration_date'][1]),
                    'cardholderName' => $submission_data['card_name'],
                    'cvv' => $submission_data['card_security_code']
                )
            );

            try {
                $gateway = $this->getBraintreeGateway();
                if ($gateway) {
                    // Set to auto settlemt if applicable
                    if ($settings['settlement'] == 'Yes') {
                        $args['options']['submitForSettlement'] = 'true';
                    }

                    $args = apply_filters('angelleye_braintree_parameter', $args, $submission_data, $form, $entry);

                    $this->log_debug("Braintree_Transaction::sale REQUEST => " . print_r($args, 1));

                    // Send transaction to Braintree
                    $result = $gateway->transaction()->sale($args);

                    $this->log_debug("Braintree_Transaction::sale RESPONSE => " . print_r($result, 1));

                    do_action('angelleye_braintree_transaction_response', $result, $submission_data, $form, $entry );

                    // Update response to reflect successful payment
                    if ($result->success) {
                        do_action('angelleye_gravity_forms_response_data', $result, $submission_data, '16', (strtolower($settings['environment']) == 'sandbox') ? true : false, false, 'braintree');
                        $authorization['is_authorized'] = true;
                        $authorization['error_message'] = '';
                        $authorization['transaction_id'] = $result->transaction->id;
                        $authorization['captured_payment'] = array(
                            'is_success' => true,
                            'transaction_id' => $result->transaction->id,
                            'amount' => $result->transaction->amount,
                            'error_message' => '',
                            'payment_method' => 'Credit Card'
                        );
                    } else {
                        // Append gateway response text to error message if it exists. If it doesn't exist, a more hardcore
                        // failure has occured and it won't do the user any good to see it other than a general error message

                        $processorResponseText = !empty( $result->message ) ? $result->message : '';
                        if( !empty( $result->transaction->processorResponseText ) && strtolower( $result->transaction->processorResponseText ) !== 'unavailable' ) {
                            $processorResponseText = $result->transaction->processorResponseText;
                        }

                        if ( !empty( $processorResponseText ) ) {
                            $authorization['error_message'] .= sprintf('. Your bank said: %s.', $processorResponseText);
                        }
                    }
                }
            } catch (Exception $e) {
                // Do nothing with exception object, just fallback to generic failure
            }
            return $authorization;
        }

        return false;
    }

    public function process_capture($authorization, $feed, $submission_data, $form, $entry) {

        do_action('gform_braintree_post_capture', rgar($authorization, 'is_authorized'), rgars($authorization, 'captured_payment/amount'), $entry, $form, $this->_args_for_deprecated_hooks['config'], $this->_args_for_deprecated_hooks['aim_response']);

        return parent::process_capture($authorization, $feed, $submission_data, $form, $entry);
    }

    /**
     * Braintree Override this method to add integration code to the payment processor in order to create a subscription.
     *
     * This method is executed during the form validation process and allows the form submission process to fail with a
     * validation error if there is anything wrong when creating the subscription.
     *
     * @param array $feed               Current configured payment feed.
     * @param array $submission_data    Contains form field data submitted by the user as well as payment information
     *                                  (i.e. payment amount, setup fee, line items, etc...).
     * @param array $form               Current form array containing all form settings.
     * @param array $entry              Current entry array containing entry information (i.e data submitted by users).
     *                                  NOTE: the entry hasn't been saved to the database at this point, so this $entry
     *                                  object does not have the 'ID' property and is only a memory representation of the entry.
     *
     * @return array {
     *      Return an $subscription array
     *     @type bool   $is_success      If the subscription is successful.
     *     @type string $error_message   The error message, if applicable.
     *     @type string $subscription_id The subscription ID.
     *     @type int    $amount          The subscription amount.
     *     @type array  $captured_payment {
     *         If payment is captured, an additional array is created.
     *         @type bool   $is_success     If the payment capture is successful.
     *         @type string $error_message  The error message, if any.
     *         @type string $transaction_id The transaction ID of the captured payment.
     *         @type int    $amount         The amount of the captured payment, if successful.
     *    }
     *
     * To implement an initial/setup fee for gateways that don't support setup fees as part of subscriptions, manually
     * capture the funds for the setup fee as a separate transaction and send that payment information in the
     * following 'captured_payment' array:
     *
     *      'captured_payment' => [
     *          'name'           => 'Setup Fee',
     *          'is_success'     => true|false,
     *          'error_message'  => 'error message',
     *          'transaction_id' => 'xxx',
     *          'amount'         => XX
     *      ]
     * }
     * @throws \Braintree\Exception\Configuration
     */
    public function subscribe($feed, $submission_data, $form, $entry) {
        $authorization = array(
            'is_authorized' => false,
            'is_success' => false,
            'error_message' => apply_filters('gform_braintree_credit_card_failure_message', __('Your card could not be billed. Please ensure the details you entered are correct and try again.', 'angelleye-gravity-forms-braintree')),
        );
        if (empty($_POST['payment_method_nonce'])) {
            return $authorization;
        }
        $settings = $this->get_plugin_settings();
        $gateway = $this->getBraintreeGateway();
        if ($gateway) {
            $args = array(
                'amount' => $submission_data['payment_amount'],
                'creditCard' => array(
                    'number' => !empty($submission_data['card_number']) ? str_replace(array('-', ' '), '', $submission_data['card_number']) : '',
                    'expirationDate' => sprintf('%s/%s', $submission_data['card_expiration_date'][0], $submission_data['card_expiration_date'][1]),
                    'cardholderName' => $submission_data['card_name'],
                    'cvv' => $submission_data['card_security_code']
                )
            );
            $args = apply_filters('angelleye_braintree_parameter', $args, $submission_data, $form, $entry);

            $this->log_debug("Braintree_subscribe_Transaction::sale REQUEST => " . print_r($args, 1));

            $customerArgs = !empty($args['customer']) ? $args['customer'] : array();
            $customer_id = $this->get_customer_id($customerArgs);
            $paymentMethod = $gateway->paymentMethod()->create([
                'customerId' => $customer_id,
                'paymentMethodNonce' => $_POST['payment_method_nonce']
            ]);
            $fee_amount = !empty($submission_data['setup_fee']) ? $submission_data['setup_fee'] : 0;
            $setup_fee_result = true;
            if (!empty($fee_amount) && $fee_amount > 0) {
                $feeArgs = array(
                    'amount' => $fee_amount,
                    'paymentMethodToken' => $paymentMethod->paymentMethod->token,
                );
                if ($settings['settlement'] == 'Yes') {
                    $feeArgs['options']['submitForSettlement'] = 'true';
                }
                $feeArgs = apply_filters('angelleye_braintree_parameter', $feeArgs, $submission_data, $form, $entry);

                $this->log_debug("Braintree_Feed_subscribe_Transaction::sale REQUEST => " . print_r($feeArgs, 1));

                $feeResult = $gateway->transaction()->sale($feeArgs);

                $this->log_debug("Braintree_Feed_subscribe_Transaction::sale RESPONSE => " . print_r($feeResult, 1));

                do_action('angelleye_braintree_transaction_response', $feeResult, $submission_data, $form, $entry );

                if ($feeResult->success) {
                    $authorization['captured_payment'] = array(
                        'is_success' => true,
                        'transaction_id' => $feeResult->transaction->id,
                        'amount' => $feeResult->transaction->amount,
                        'error_message' => '',
                        'payment_method' => 'Credit Card'
                    );
                } else {
                    $setup_fee_result = false;

                    $processorResponseText = !empty( $result->message ) ? $result->message : '';
                    if( !empty( $result->transaction->processorResponseText ) && strtolower( $result->transaction->processorResponseText ) !== 'unavailable' ) {
                        $processorResponseText = $result->transaction->processorResponseText;
                    }
                    if ( !empty( $processorResponseText ) ) {
                        $authorization['error_message'] .= sprintf('. Your bank said: %s.', $processorResponseText);
                    }
                }
            }
            if ($setup_fee_result) {
                try {
                    $subscriptionArgs = array(
                        'paymentMethodToken' => $paymentMethod->paymentMethod->token,
                        'planId' => !empty($feed['meta']['subscriptionPlan']) ? $feed['meta']['subscriptionPlan'] : '',
                        'price' => $submission_data['payment_amount'],
                    );
                    if ($feed['meta']['recurringTimes'] == 0) {
                        $subscriptionArgs['neverExpires'] = true;
                    } else {
                        $subscriptionArgs['numberOfBillingCycles'] = $feed['meta']['recurringTimes'];
                    }
                    if (!empty($feed['meta']['trial_enabled'])) {
                        $subscriptionArgs['trialDuration'] = '';
                        $subscriptionArgs['trialDurationUnit'] = '';
                        $subscriptionArgs['trialPeriod'] = true;
                    } else {
                        $subscriptionArgs['firstBillingDate'] = '';
                    }
                    $subscriptionArgs = apply_filters('angelleye_gravity_braintree_subscription_args', $subscriptionArgs);
                    $subscription = $gateway->subscription()->create($subscriptionArgs);
                    if ($subscription->success) {
                        $authorization['is_authorized'] = true;
                        $authorization['is_success'] = true;
                        $authorization['error_message'] = '';
                        $authorization['paymentMethodToken'] = $subscription->subscription->paymentMethodToken;
                        $authorization['subscription_id'] = $subscription->subscription->id;
                        $authorization['amount'] = $subscription->subscription->price;
                        $authorization['subscription_trial_amount'] = $subscription->subscription->price;
                        $authorization['subscription_start_date'] = $subscription->subscription->firstBillingDate->date;
                    }
                } catch (Exception $e) {
                    
                }
            }
        }
        return $authorization;
    }

    /**
     * Braintree override this method to add integration code to the payment processor in order to cancel a subscription.
     *
     * This method is executed when a subscription is canceled from the braintree Payment Gateway.
     *
     * @param array $entry  Current entry array containing entry information (i.e data submitted by users).
     * @param array $feed   Current configured payment feed.
     *
     * @return bool Returns true if the subscription was cancelled successfully and false otherwise.
     *
     * @throws \Braintree\Exception\Configuration
     */
    public function cancel($entry, $feed) {
        $gateway = $this->getBraintreeGateway();
        if ($gateway) {
            $result = $gateway->subscription()->cancel($entry['transaction_id']);
            if ($result->success) {
                return true;
            }
        }
        return false;
    }

    public function is_payment_gateway($entry_id) {

        if ($this->is_payment_gateway) {
            return true;
        }

        $gateway = gform_get_meta($entry_id, 'payment_gateway');

        return in_array($gateway, array('Braintree', $this->_slug));
    }

    /**
     * Create and display feed settings fields.
     *
     * @since 1.0
     * @return void
     */
    public function feed_settings_fields() {

        // Get defaults from GFPaymentAddOn
        $settings = parent::feed_settings_fields();

        // Remove billing information
        $settings = $this->remove_field('billingInformation', $settings);

        // Remove options
        $settings = $this->remove_field('options', $settings);

        // Remove the subscription option from transaction type dropdown
        $transaction_type = $this->get_field('transactionType', $settings);

        //foreach( $transaction_type['choices'] as $index => $choice ) {
        //if( $choice['value'] == 'subscription' ) {
        //unset( $transaction_type['choices'][$index] );
        //}
        //}

        $transactionType = '';
        foreach ($settings as $index => $setting) {
            if (!empty($setting['dependency']['field']) && $setting['dependency']['field'] == 'transactionType') {
                $transactionType = !empty($setting['dependency']['values'][0]) ? $setting['dependency']['values'][0] : '';
            }
        }

        if ((!empty($_POST['_gaddon_setting_transactionType']) && $_POST['_gaddon_setting_transactionType'] == 'subscription') || ( empty($_POST['_gaddon_setting_transactionType']) && !empty($transactionType) && $transactionType == 'subscription')) {
            $form_page_link = add_query_arg([
                'id' => !empty($_REQUEST['id']) ? $_REQUEST['id'] : '',
                    ], menu_page_url('gf_edit_forms', false));
            $transaction_type['description'] = sprintf(__('When building your subscription form, make sure to use the %sBraintree CC%s field instead of the basic Credit Card field.', 'angelleye-gravity-forms-braintree'), '<a href="' . $form_page_link . '">', '</a>');
        }

        $settings = $this->replace_field('transactionType', $transaction_type, $settings);

        $settings = parent::remove_field('trial', $settings);

        $createBraintreePlanUrl = $this->merchant_url('plans/new');
        $api_settings_field = array(
            array(
                'name' => 'braintree_trial',
                'label' => esc_html__('Trial', 'angelleye-gravity-forms-braintree'),
                'type' => 'braintree_trial',
                'hidden' => '',
                'tooltip' => ''
            ),
            array(
                'name' => 'subscriptionPlan',
                'label' => esc_html__('Plan', 'angelleye-gravity-forms-braintree'),
                'type' => 'select',
                'choices' => $this->get_plans(),
                'required' => true,
                'tooltip' => sprintf(__('Plugin will fetch and display the subscription plans. Create the %splan%s in your Braintree account.', 'angelleye-gravity-forms-braintree'), '<a href="' . $createBraintreePlanUrl . '" target="_blank">', '</a>'),
            ),
        );

        $settings = $this->add_field_after('setupFee', $api_settings_field, $settings);

        if( !empty( $settings ) && is_array( $settings ) ) {

            $temp_settings = [];

            $extra_fee_settings = [
                'title'      => esc_html__( 'Extra Fee Settings (%)', 'angelleye-gravity-forms-braintree' ),
                'fields' => [
                    [
                        'name'          => 'override_extra_fees',
                        'type'          => 'toggle',
                        'label'         => esc_html__( 'Override Global Settings', 'angelleye-gravity-forms-braintree' ),
                        'default_value' => false,
                        'tooltip'       => '<strong>' . __( 'Override Global Settings', 'angelleye-gravity-forms-braintree' ) . '</strong>' . __( 'If you would like this specific form/feed to use different values than what are configured in the global Braintree settings, you may override them here.', 'angelleye-gravity-forms-braintree' ),
                    ],
                    [
                        'name'          => 'extra_fee_label',
                        'type'          => 'text',
                        'label'         => esc_html__( 'Extra Fee Label', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => esc_html__( 'Convenience Fee', 'angelleye-gravity-forms-braintree'),
                        'placeholder'   => esc_html__( 'Convenience Fee', 'angelleye-gravity-forms-braintree'),
                        'class'         => 'extra-fees-input',
                        'dependency'    => [
                            'live'      => true,
                            'fields'    => [
                                [
                                    'field' => 'override_extra_fees',
                                ]
                            ]
                        ]
                    ],
                    [
                        'name'          => 'credit_card_fees',
                        'type'          => 'text',
                        'input_type'    => 'number',
                        'label'         => esc_html__( 'Credit Card', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => '0.00',
                        'placeholder'   => '0.00',
                        'min'           => '0',
                        'class'         => 'extra-fees-input',
                        'dependency'    => [
                            'live'      => true,
                            'fields'    => [
                                [
                                    'field' => 'override_extra_fees',
                                ]
                            ],
                        ],
                    ],
                    [
                        'name'          => 'debit_card_fees',
                        'type'          => 'text',
                        'input_type'    => 'number',
                        'label'         => esc_html__( 'Debit Card', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => '0.00',
                        'placeholder'   => '0.00',
                        'min'           => '0',
                        'class'         => 'extra-fees-input',
                        'dependency'    => [
                            'live'      => true,
                            'fields'    => [
                                [
                                    'field' => 'override_extra_fees',
                                ]
                            ],
                        ],
                    ],
                    [
                        'name'          => 'ach_fees',
                        'type'          => 'text',
                        'input_type'    => 'number',
                        'label'         => esc_html__( 'ACH', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => '0.00',
                        'placeholder'   => '0.00',
                        'min'           => '0',
                        'class'         => 'extra-fees-input',
                        'dependency'    => [
                            'live'      => true,
                            'fields'    => [
                                [
                                    'field' => 'override_extra_fees',
                                ]
                            ],
                        ],
                    ],
	                [
		                'name'          => 'paypal_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'PayPal', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => [
			                'live'      => true,
			                'fields'    => [
				                [
					                'field' => 'override_extra_fees',
				                ]
			                ],
		                ],
	                ],
	                [
		                'name'          => 'venmo_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'Venmo', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => [
			                'live'      => true,
			                'fields'    => [
				                [
					                'field' => 'override_extra_fees',
				                ]
			                ],
		                ],
	                ],
	                /*[
		                'name'          => 'google_pay_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'Google Pay', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => [
			                'live'      => true,
			                'fields'    => [
				                [
					                'field' => 'override_extra_fees',
				                ]
			                ],
		                ],
	                ],
	                [
		                'name'          => 'apple_pay_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'Apple Pay', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => [
			                'live'      => true,
			                'fields'    => [
				                [
					                'field' => 'override_extra_fees',
				                ]
			                ],
		                ],
	                ],*/
                    [
                        'name'          => 'disable_extra_fees',
                        'type'          => 'checkbox',
                        'label'         => '',
                        'tooltip'       => '',
                        'required'      => false,
                        'min'           => 0,
                        'choices'       => [
                            [
                                'name' => 'disable_extra_fees',
                                'label' => esc_html__( 'Disable Extra Fee', 'angelleye-gravity-forms-braintree' ),
                            ]
                        ],
                        'dependency'    => [
                            'live'      => true,
                            'fields'    => [
                                [
                                    'field' => 'override_extra_fees',
                                ]
                            ],
                        ],
                    ],
                ],
            ];

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
                        'tooltip'       => esc_html__('By default the payment will be processed by your primary Braintree merchant account.  If you have multiple merchant accounts configured, you can specify which one this form should pay to here.', 'angelleye-gravity-forms-braintree'),
                    ]
                ]
            ];

			$payment_methods_settings = [
				'title'      => esc_html__( 'Payment Method Settings', 'angelleye-gravity-forms-braintree' ),
				'fields' => [
					[
						'name'          => 'braintree_payment_methods',
						'label'         => esc_html__( 'Payment Methods', 'angelleye-gravity-forms-braintree' ),
						'type'          => 'checkbox',
						'choices'       => $this->get_payment_method_choices(),
						'required'      => false,
						'default_value' => '',
						'tooltip'       => esc_html__('By default the Braintree Credit Card payment method in your form. Enable multiple payment methods.', 'angelleye-gravity-forms-braintree'),
					],
					[
						'name'          => 'google_pay_merchant_id',
						'type'          => 'text',
						'label'         => esc_html__( 'Google Pay Merchant ID', 'angelleye-gravity-forms-braintree' ),
						'tooltip'       => '',
						'required'      => false,
						'dependency'    => [
							'live'      => true,
							'fields'    => [
								[
									'field' => 'braintree_payment_methods',
									'values' => ['google_pay'],
								]
							],
						],
					],
				]
			];

            foreach ( $settings as $setting ) {

                if( !empty( $setting ) && $setting['title'] === 'Other Settings' ) {
                    $temp_settings[] = $merchant_settings;
                    $temp_settings[] = $extra_fee_settings;
                    $temp_settings[] = $payment_methods_settings;
                }

                $temp_settings[] = $setting;
            }

            $settings = $temp_settings;
        }

        // Return sanitized settings
        return $settings;
    }

    public function merchant_url($tab = 'plans') {

        $settings = $this->get_plugin_settings();

        $braintreeUrl = '#';
        if (!empty($settings['merchant-id'])) {
            $environment = !empty($settings['environment']) ? strtolower($settings['environment']) : 'sandbox';
            $environmentUrl = ( $environment == 'sandbox' ) ? 'sandbox.' : '';

            $braintree_config = new \Braintree\Configuration([
                'environment' => strtolower($settings['environment']),
                'merchantId' => $settings['merchant-id'],
                'publicKey' => $settings['public-key'],
                'privateKey' => $settings['private-key']
            ]);

            $merchantPath = $braintree_config->merchantPath();
            $braintreeUrl = "https://{$environmentUrl}braintreegateway.com{$merchantPath}/{$tab}";
        }

        return $braintreeUrl;
    }

    /**
     * This function is callback for braintree_trial setting field.
     *
     * @param array $field Settings fields
     * @param bool $echo Display or return
     *
     * @return string $html
     *
     * @throws \Braintree\Exception\Configuration
     */
    public function settings_braintree_trial($field, $echo = true) {

        $braintreePlans = $this->merchant_url();
        $html = sprintf(__('Select your product trial form %sBraintree Plans%s', 'angelleye-gravity-forms-braintree'), '<a href="' . $braintreePlans . '" target="_blank">', '</a>');

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    /**
     * Create and display plugin settings fields. These are settings for Braintree in particular, not a feed
     *
     * @since 1.0
     * @return void
     */
    public function plugin_settings_fields() {

        return array(
            array(
                'title' => 'Account Settings',
                'fields' => array(
                    array(
                        'name' => 'merchant-id',
                        'tooltip' => 'Your Braintree Merchant ID',
                        'label' => 'Merchant ID',
                        'type' => 'text',
                        'class' => 'medium',
                    ),
                    array(
                        'name' => 'public-key',
                        'tooltip' => 'Your Braintree Account Public Key',
                        'label' => 'Public Key',
                        'type' => 'text',
                        'class' => 'medium'
                    ),
                    array(
                        'name' => 'private-key',
                        'tooltip' => 'Your Braintree Account Private Key',
                        'label' => 'Private Key',
                        'type' => 'text',
                        'class' => 'medium'
                    )
                )
            ),
            array(
                'title' => 'Braintree ACH Settings',
                'fields' => array(
                    array(
                        'name' => 'tokenization-key',
                        'tooltip' => 'Your Braintree Tokenization Key',
                        'label' => 'Tokenization Key',
                        'type' => 'text',
                        'class' => 'medium'
                    ),
                    array(
                        'name' => 'business-name',
                        'tooltip' => 'For all ACH transactions, you are required to collect a mandate or “proof of authorization” from the customer to prove that you have their explicit permission to debit their bank account. We will put your business name in authorization text',
                        'label' => 'Business name',
                        'type' => 'text',
                        'class' => 'medium'
                    )
                )
            ),
            array(
                'title' => 'Payment Settings',
                'fields' => array(
                    array(
                        'name' => 'settlement',
                        'tooltip' => 'Choosing \'Yes\' will tell Braintree to automatically submit your transactions for settlement upon receipt',
                        'label' => 'Automatic Settlement Submission',
                        'type' => 'radio',
                        'choices' => array(
                            array(
                                'label' => 'Yes',
                                'name' => 'yes'
                            ),
                            array(
                                'label' => 'No',
                                'name' => 'no'
                            )
                        )
                    )
                )
            ),
            array(
                'title' => 'Environment Settings',
                'fields' => array(
                    array(
                        'name' => 'environment',
                        'tooltip' => 'Do you want to process test payments or real payments?',
                        'label' => 'API Endpoint',
                        'type' => 'radio',
                        'choices' => array(
                            array(
                                'label' => 'Sandbox',
                                'name' => 'sandbox'
                            ),
                            array(
                                'label' => 'Production',
                                'name' => 'production'
                            )
                        )
                    )
                )
            ),
            array(
                'title'  => esc_html__( 'Extra Fee Settings', 'angelleye-gravity-forms-braintree' ),
                'fields' => array(
                    array(
                        'name'          => 'enable_extra_fees',
                        'type'          => 'toggle',
                        'label'         => esc_html__( 'Enable Extra Fee (%)', 'angelleye-gravity-forms-braintree' ),
                        'default_value' => false,
                        'tooltip'       => '<strong>' . __( 'Extra Fee', 'angelleye-gravity-forms-braintree' ) . '</strong>' . __( 'Enable this and set a percentage (%) to collect a convenience fee on credit card, debit card and/or ACH payments.', 'angelleye-gravity-forms-braintree' ),
                    ),
                    array(
                        'name'          => 'extra_fee_label',
                        'type'          => 'text',
                        'label'         => esc_html__( 'Extra Fee Label', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => esc_html__( 'Convenience Fee', 'angelleye-gravity-forms-braintree'),
                        'placeholder'   => esc_html__( 'Convenience Fee', 'angelleye-gravity-forms-braintree'),
                        'class'         => 'extra-fees-input',
                        'dependency'    => array(
                            'live'      => true,
                            'fields'    => array(
                                array(
                                    'field' => 'enable_extra_fees',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'name'          => 'credit_card_fees',
                        'type'          => 'text',
                        'input_type'    => 'number',
                        'label'         => esc_html__( 'Credit Card', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => '0.00',
                        'placeholder'   => '0.00',
                        'min'           => '0',
                        'class'         => 'extra-fees-input',
                        'dependency'    => array(
                            'live'      => true,
                            'fields'    => array(
                                array(
                                    'field' => 'enable_extra_fees',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'name'          => 'debit_card_fees',
                        'type'          => 'text',
                        'input_type'    => 'number',
                        'label'         => esc_html__( 'Debit Card', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => '0.00',
                        'placeholder'   => '0.00',
                        'min'           => '0',
                        'class'         => 'extra-fees-input',
                        'dependency'    => array(
                            'live'      => true,
                            'fields'    => array(
                                array(
                                    'field' => 'enable_extra_fees',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'name'          => 'ach_fees',
                        'type'          => 'text',
                        'input_type'    => 'number',
                        'label'         => esc_html__( 'ACH', 'angelleye-gravity-forms-braintree' ),
                        'tooltip'       => '',
                        'required'      => false,
                        'default_value' => '0.00',
                        'placeholder'   => '0.00',
                        'min'           => '0',
                        'class'         => 'extra-fees-input',
                        'dependency'    => array(
                            'live'      => true,
                            'fields'    => array(
                                array(
                                    'field' => 'enable_extra_fees',
                                ),
                            ),
                        ),
                    ),
	                array(
		                'name'          => 'paypal_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'PayPal', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => array(
			                'live'      => true,
			                'fields'    => array(
				                array(
					                'field' => 'enable_extra_fees',
				                ),
			                ),
		                ),
	                ),
	                array(
		                'name'          => 'venmo_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'Venmo', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => array(
			                'live'      => true,
			                'fields'    => array(
				                array(
					                'field' => 'enable_extra_fees',
				                ),
			                ),
		                ),
	                ),
	                /*array(
		                'name'          => 'google_pay_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'Google Pay', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => array(
			                'live'      => true,
			                'fields'    => array(
				                array(
					                'field' => 'enable_extra_fees',
				                ),
			                ),
		                ),
	                ),
	                array(
		                'name'          => 'apple_pay_fees',
		                'type'          => 'text',
		                'input_type'    => 'number',
		                'label'         => esc_html__( 'Apple Pay', 'angelleye-gravity-forms-braintree' ),
		                'tooltip'       => '',
		                'required'      => false,
		                'default_value' => '0.00',
		                'placeholder'   => '0.00',
		                'min'           => '0',
		                'class'         => 'extra-fees-input',
		                'dependency'    => array(
			                'live'      => true,
			                'fields'    => array(
				                array(
					                'field' => 'enable_extra_fees',
				                ),
			                ),
		                ),
	                ),*/
                )
            )
        );
    }

    /**
     * Helper function to determine if all Braintree settings have been set.
     * Does not check if they are correct, only that they have been set, IE not null
     * @param @settings Plugin settings to check if valid
     * @since 1.0
     * @return void
     */
    public function settings_are_valid($settings) {
        if (empty($settings)) {
            return false;
        }
        if (!empty($settings['merchant-id']) && !empty($settings['public-key']) && !empty($settings['public-key'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get plugin settings
     *
     * @since 1.0
     * @return void
     */
    public function get_plugin_settings() {

        $settings = parent::get_plugin_settings();

        if ($this->settings_are_valid($settings)) {
            return $settings;
        } else {
            return false;
        }
    }

    public function angelleye_gform_braintree_display_push_notification() {
        global $current_user;
        $user_id = $current_user->ID;
        if (false === ( $response = get_transient('angelleye_gravity_braintree_push_notification_result') )) {
            $response = $this->angelleye_get_push_notifications();
            if (is_object($response)) {
                set_transient('angelleye_gravity_braintree_push_notification_result', $response, 12 * HOUR_IN_SECONDS);
            }
        }
        if (is_object($response)) {
            foreach ($response->data as $key => $response_data) {
                if (!get_user_meta($user_id, $response_data->id)) {
                    $this->angelleye_display_push_notification($response_data);
                }
            }
        }
    }

    public function angelleye_get_push_notifications() {
        $args = array(
            'plugin_name' => 'angelleye-gravity-forms-braintree',
        );
        $api_url = PAYPAL_FOR_WOOCOMMERCE_PUSH_NOTIFICATION_WEB_URL . '?Wordpress_Plugin_Notification_Sender';
        $api_url .= '&action=angelleye_get_plugin_notification';
        $request = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array('user-agent' => 'AngellEYE'),
            'body' => $args,
            'cookies' => array(),
            'sslverify' => false
        ));
        if (is_wp_error($request) or wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }
        if ($request != '') {
            $response = json_decode(wp_remote_retrieve_body($request));
        } else {
            $response = false;
        }
        return $response;
    }

    public function angelleye_display_push_notification($response_data) {
        echo '<div class="notice notice-success angelleye-notice" style="display:none;" id="' . $response_data->id . '">'
        . '<div class="angelleye-notice-logo-push"><span> <img src="' . $response_data->ans_company_logo . '"> </span></div>'
        . '<div class="angelleye-notice-message">'
        . '<h3>' . $response_data->ans_message_title . '</h3>'
        . '<div class="angelleye-notice-message-inner">'
        . '<p>' . $response_data->ans_message_description . '</p>'
        . '<div class="angelleye-notice-action"><a target="_blank" href="' . $response_data->ans_button_url . '" class="button button-primary">' . $response_data->ans_button_label . '</a></div>'
        . '</div>'
        . '</div>'
        . '<div class="angelleye-notice-cta">'
        . '<button class="angelleye-notice-dismiss angelleye-dismiss-welcome" data-msg="' . $response_data->id . '">Dismiss</button>'
        . '</div>'
        . '</div>';
    }

    public function angelleye_gform_braintree_adismiss_notice() {
        global $current_user;
        $user_id = $current_user->ID;
        if (!empty($_POST['action']) && $_POST['action'] == 'angelleye_gform_braintree_adismiss_notice') {
            add_user_meta($user_id, wc_clean($_POST['data']), 'true', true);
            wp_send_json_success();
        }
    }

    public function include_angelleye_braintree_style_noconflict($styles) {
        $styles[] = 'gravity-forms-braintree-admin-css';
        return $styles;
    }

    public function include_angelleye_braintree_script_noconflict($scripts) {
        $scripts[] = 'gravity-forms-braintree-admin';
        return $scripts;
    }

    public function enqueue_scripts_js() {

        $is_report = !empty( $_GET['page'] ) && $_GET['page'] === 'gf_transaction_reports';

        if (GFForms::is_gravity_page() || $is_report) {
            wp_enqueue_script('datetimepicker-admin', GRAVITY_FORMS_BRAINTREE_ASSET_URL . 'assets/js/datetimepicker.min.js', array('jquery'), $this->_version, false);
            wp_enqueue_script('gravity-forms-braintree-admin', GRAVITY_FORMS_BRAINTREE_ASSET_URL . 'assets/js/gravity-forms-braintree-admin.js', array('jquery'), $this->_version, false);
            wp_localize_script('gravity-forms-braintree-admin',  'GFBraintreeObj',[
                'report_confirm' => __('Are you sure you want to delete this report file.','angelleye-gravity-forms-braintree'),
                'start_date_required' => __('<strong>Start date</strong> is required field','angelleye-gravity-forms-braintree'),
                'end_date_required' => __('<strong>End date</strong> is required field','angelleye-gravity-forms-braintree'),
            ]);
        }
    }

    public function enqueue_styles_css() {
        $is_report = !empty( $_GET['page'] ) && $_GET['page'] === 'gf_transaction_reports';
        if (GFForms::is_gravity_page() || $is_report) {
            wp_enqueue_style('datetimepicker-admin-css', GRAVITY_FORMS_BRAINTREE_ASSET_URL . 'assets/css/datetimepicker.min.css', array(), $this->_version, 'all');
            wp_enqueue_style('gravity-forms-braintree-admin-css', GRAVITY_FORMS_BRAINTREE_ASSET_URL . 'assets/css/gravity-forms-braintree-admin.css', array(), $this->_version, 'all');
        }
    }

    /**
     * Load the Braintree JS and Custom JS in frontend
     * @return array
     */
    public function scripts() {
        $translation_array = [];
        $settings = $this->get_plugin_settings();
        if ($settings !== false) {
            $translation_array['ajax_url'] = admin_url( 'admin-ajax.php' );
            $translation_array['ach_bt_nonce'] = wp_create_nonce('preview-payment-nonce');
            $translation_array['ach_bt_token'] = @$settings['tokenization-key'];
            $translation_array['ach_business_name'] = @$settings['business-name'];
            $translation_array['is_admin'] = is_admin();
        }

        $scripts = array(
            array(
                'handle' => 'angelleye-gravity-form-braintree-client',
                'src' => 'https://js.braintreegateway.com/web/3.61.0/js/client.min.js',
                'version' => $this->_version,
                'deps' => array('jquery'),
                'in_footer' => false,
                'callback' => array($this, 'localize_scripts'),
                'enqueue' => array(
                    array('field_types' => array('braintree_ach','braintree_credit_card'))
                )
            ),
            array(
                'handle' => 'angelleye-gravity-form-braintree-data-collector',
                'src' => 'https://js.braintreegateway.com/web/3.61.0/js/data-collector.min.js',
                'version' => $this->_version,
                'deps' => array(),
                'in_footer' => false,
                'callback' => array($this, 'localize_scripts'),
                'enqueue' => array(
                    array('field_types' => array('braintree_ach','braintree_credit_card'))
                )
            ),
            array(
                'handle' => 'angelleye-gravity-form-braintree-usbankaccount',
                'src' => 'https://js.braintreegateway.com/web/3.61.0/js/us-bank-account.min.js',
                'version' => $this->_version,
                'deps' => array(),
                'in_footer' => false,
                'callback' => array($this, 'localize_scripts'),
                'enqueue' => array(
                    array('field_types' => array('braintree_ach','braintree_credit_card'))
                )
            ),
            array(
                'handle' => 'angelleye_gravity_form_braintree_ach_handler',
                'src' => GRAVITY_FORMS_BRAINTREE_ASSET_URL . 'assets/js/angelleye-braintree-ach-cc.js',
                'version' => $this->_version,
                'deps' => array('jquery', 'angelleye-gravity-form-braintree-client', 'angelleye-gravity-form-braintree-data-collector',
                    'angelleye-gravity-form-braintree-usbankaccount'),
                'in_footer' => false,
                'callback' => array($this, 'localize_scripts'),
                'strings' => $translation_array,
                'enqueue' => array(
//					array(
//						'admin_page' => array( 'form_settings' ),
//						'tab'        => 'simpleaddon'
//					)
                    array('field_types' => array('braintree_ach','braintree_credit_card'))
                )
            ),
        );

        return array_merge(parent::scripts(), $scripts);
    }

    /**
     * Override default billing cycles intervals for subscription plan.
     *
     * @return array $billing_cycles
     */
    public function supported_billing_intervals() {
        $billing_cycles = array(
            'month' => array('label' => esc_html__('month(s)', 'angelleye-gravity-forms-braintree'), 'min' => 1, 'max' => 24)
        );

        return $billing_cycles;
    }

    /**
     * Get all Braintree plans using Braintree payment Gateway settings.
     *
     * @return array $plans
     *
     * @throws \Braintree\Exception\Configuration
     */
    public function get_plans() {
        try {
            $gateway = $this->getBraintreeGateway();
            if ($gateway) {
                $plan_lists = $gateway->plan()->all();
                $plans = array(array(
                        'label' => __('Select a plan', 'angelleye-gravity-forms-braintree'),
                        'value' => '',
                ));
                if (!empty($plan_lists)) {
                    foreach ($plan_lists as $plan) {
                        $plans[] = array(
                            'label' => $plan->name,
                            'value' => $plan->id,
                        );
                    }
                }
                return $plans;
            }
        } catch (Exception $ex) {
            
        }
    }

    /**
     * Get customer id using customer email address.
     *
     * If customer email address already exists in braintree customer lists then provide customer id,
     * Otherwise create a new customer using customer details and then provide a customer id.
     *
     * @param array $args
     *
     * @return int|string $customer_id Customer id.
     *
     * @throws \Braintree\Exception\Configuration
     */
    public function get_customer_id($args) {
        //check if customer detail is empty or not array then return.
        if (empty($args) || !is_array($args)) {
            return '';
        }
        $email = !empty($args['email']) ? $args['email'] : '';
        $gateway = $this->getBraintreeGateway();
        if ($gateway) {
            //search customer using email address
            $collections = $gateway->customer()->search([
                Braintree\CustomerSearch::email()->is($email)
            ]);
            $customer_id = 0;
            foreach ($collections as $key => $collection) {
                if (!empty($collection->id)) {
                    $customer_id = $collection->id;
                }
            }
            //check $customer_id is empty then create a new customer.
            if (empty($customer_id)) {
                $customer = $gateway->customer()->create($args);
                if (!empty($customer->customer->id)) {
                    $customer_id = $customer->customer->id;
                }
            }
            return $customer_id;
        }
    }

    /**
     * Manage Braintree request parameters.
     *
     * @param array $request_args Get request arguments.
     * @param array $data Get data.
     * @param array $form Get form.
     * @param array $entry Get form entry
     * @return array $request_args
     */
    public function manage_braintree_request_parameter( $request_args, $data, $form, $entry ) {

        $payment_feed = $this->get_payment_feed([], $form);
        $feed_meta = !empty( $payment_feed['meta'] ) ? $payment_feed['meta'] : '';

        if( !empty( $feed_meta['sub_merchant_account_id'] ) ) {
            $request_args['merchantAccountId'] = $feed_meta['sub_merchant_account_id'];
        }

        return $request_args;
    }

    /**
     * Get all sub merchant accounts using primary account.
     *
     * @return array $merchant_accounts
     */
    public function merchant_account_choices() {

        $merchant_accounts = [
            [
                'label' => esc_html__( 'Select Merchant Account ID', 'angelleye-gravity-forms-braintree' ),
                'value' => ''
            ]
        ];

        try {

            $gateway = $this->getBraintreeGateway();

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

	public function get_payment_method_choices() {

		$payment_methods = [];

		$get_payment_methods = get_braintree_payment_methods();

		if( !empty( $get_payment_methods ) && is_array( $get_payment_methods ) ) {

			foreach (  $get_payment_methods as $key =>  $get_payment_method )  {
				$payment_methods[] = [
					'label' => $get_payment_method,
					'name' => $key
				];
			}
		}

		return $payment_methods;
	}
}
