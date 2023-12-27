<?php

function getAngelleyeBraintreePaymentFields($form){
	$response = [
		'creditcard' => false,
		'braintree_ach' => false,
		'braintree_ach_cc_toggle' => false,
		'braintree_credit_card' => false
	];

	if(isset($form['fields'])) {
		foreach ($form['fields'] as $single_field) {
			if ($single_field->type == 'creditcard' || $single_field->type=='braintree_ach' || $single_field->type == 'braintree_ach_cc_toggle' || $single_field->type=='braintree_credit_card') {
				$response[$single_field->type] = $single_field;
			}
		}
	}

	return $response;
}

function getAngelleyeBraintreePaymentMethod($form){
	$selected_method = '';
    $response = getAngelleyeBraintreePaymentFields($form);

    //This means customer is using our toggle button
    if($response['braintree_ach_cc_toggle'] !== false){
	    $selected_method = rgpost( 'input_' . $response['braintree_ach_cc_toggle']->id . '_1' );
    } else {
        if($response['creditcard']!==false){
            if(isset($response['creditcard']['conditionalLogic']) && is_array($response['creditcard']['conditionalLogic']) && count($response['creditcard']['conditionalLogic'])){
                $conditionalLogic =  $response['creditcard']['conditionalLogic'];
                if($conditionalLogic['actionType'] == 'show'){
                    foreach ( $conditionalLogic['rules'] as $rule ) {
                        if($rule['operator'] == 'is') {
                            $fieldId = $rule['fieldId'];
                            $isValue = $rule['value'];
                            $selected_radio_value = rgpost( 'input_' . $fieldId );

                            if($selected_radio_value == $isValue){
                                $selected_method = 'creditcard';
                                break;
                            }
                        }
                    }
                }

            }
        }

        if($selected_method=='' && $response['braintree_ach'] !== false){
	        if(isset($response['braintree_ach']['conditionalLogic']) && is_array($response['braintree_ach']['conditionalLogic']) && count($response['braintree_ach']['conditionalLogic'])){
                $conditionalLogic =  $response['braintree_ach']['conditionalLogic'];
                if($conditionalLogic['actionType'] == 'show'){
                    foreach ( $conditionalLogic['rules'] as $rule ) {
                        if($rule['operator'] == 'is') {
                            $fieldId = $rule['fieldId'];
                            $isValue = $rule['value'];
                            $selected_radio_value = rgpost( 'input_' . $fieldId );
                            if($selected_radio_value == $isValue){
                                $selected_method = 'braintree_ach';
                                break;
                            }
                        }
                    }
                }
	        }
        }

        if($selected_method == '' && $response['creditcard']!==false){
            $selected_method = 'creditcard';
        } else if($selected_method == '' && $response['braintree_ach']!==false){
	        $selected_method = 'braintree_ach';
        } else if($selected_method == '' && $response['braintree_credit_card']!==false){
	        $selected_method = 'braintree_credit_card';
        }

    }

    return $selected_method;
}


/**
 * This is to setup default values for Custom Toggle and ACH form fields in admin panel
 */

add_action( 'gform_editor_js_set_default_values', 'gravityFormSetDefaultValueOnDropin' );
function gravityFormSetDefaultValueOnDropin() {
    ?>
    case "braintree_ach" :
        if (!field.label)
        field.label = <?php echo json_encode( esc_html__( 'Pay through your Bank Account', 'gravity-forms-braintree' ) ); ?>;
        var accNumber, accType, routingNumber, accName;

        accNumber = new Input(field.id + ".1", <?php echo json_encode( gf_apply_filters( array( 'gform_account_number', rgget( 'id' ) ), esc_html__( 'Account Number', 'gravity-forms-braintree' ), rgget( 'id' ) ) ); ?>);
        accType = new Input(field.id + ".2", <?php echo json_encode( gf_apply_filters( array( 'gform_account_type', rgget( 'id' ) ), esc_html__( 'Account Type', 'gravity-forms-braintree' ), rgget( 'id' ) ) ); ?>);
        routingNumber = new Input(field.id + ".3", <?php echo json_encode( gf_apply_filters( array( 'gform_routing_number', rgget( 'id' ) ), esc_html__( 'Routing Number', 'gravity-forms-braintree' ), rgget( 'id' ) ) ); ?>);
        accName = new Input(field.id + ".4", <?php echo json_encode( gf_apply_filters( array( 'gform_account_name', rgget( 'id' ) ), esc_html__( 'Account Holder Name', 'gravity-forms-braintree' ), rgget( 'id' ) ) ); ?>);
        field.inputs = [accNumber, accType, routingNumber, accName];
        break;
    case "braintree_ach_cc_toggle":
        if (!field.label)
        field.label = <?php echo json_encode( esc_html__( 'Select a Payment Method', 'gravity-forms-braintree' ) ); ?>;
        var paymentMethodToggle;
        paymentMethodToggle = new Input(field.id + ".1", <?php echo json_encode( gf_apply_filters( array( 'gform_payment_method_selected', rgget( 'id' ) ), esc_html__( 'Payment Method Toggle', 'gravity-forms-braintree' ), rgget( 'id' ) ) ); ?>);
        field.inputs = [paymentMethodToggle];
        break;
    case "braintree_credit_card":
        if (!field.label)
        field.label = <?php echo json_encode( esc_html__( 'Credit Card', 'angelleye-gravity-forms-braintree' ) ); ?>;
        var braintreeCC = new Input(field.id + ".1", <?php echo json_encode( gf_apply_filters( array( 'gform_payment_method_selected', rgget( 'id' ) ), esc_html__( 'Credit Card', 'angelleye-gravity-forms-braintree' ), rgget( 'id' ) ) ); ?>);
        field.inputs = [braintreeCC];
        break;
    <?php
}


/**
 * Get extra fees values using gravity form id.
 * In this function we will manage Credit Card, Debit Card and ACH fees values.
 *
 * @param int $form_id Get form id.
 * @return array $extra_fees.
 */
function angelleye_get_extra_fees( $form_id ) {

    $extra_fees = [
        'is_fees_enable' => false,
        'title' => esc_html__( 'Convenience Fee', 'angelleye-gravity-forms-braintree' ),
        'credit_card_fees' => '0',
        'debit_card_fees' => '0',
        'ach_fees' => '0',
    ];

    if( empty( $form_id ) ) {
        return $extra_fees;
    }

    try {

        $gform_braintree = new Plugify_GForm_Braintree();
        $settings = $gform_braintree->get_plugin_settings();

        if( !empty( $settings['enable_extra_fees'] ) ) {
            if(  !empty( $settings['extra_fee_label'] ) ) {
                $extra_fees['title'] = $settings['extra_fee_label'];
            }
            $extra_fees['is_fees_enable'] =  $settings['enable_extra_fees'];
            $extra_fees['credit_card_fees'] =  !empty( $settings['credit_card_fees'] ) ? get_gfb_format_price( $settings['credit_card_fees'], false) : '';
            $extra_fees['debit_card_fees'] =  !empty( $settings['debit_card_fees'] ) ? get_gfb_format_price( $settings['debit_card_fees'],  false) : '';
            $extra_fees['ach_fees'] =  !empty( $settings['ach_fees'] ) ? get_gfb_format_price( $settings['ach_fees'], false ) : '';
        }

        $form = GFAPI::get_form( $form_id );
        $payment_feed = $gform_braintree->get_payment_feed([], $form);
        $feed_meta = !empty( $payment_feed['meta'] ) ? $payment_feed['meta'] : '';

        if( !empty( $feed_meta['override_extra_fees'] ) ) {

            $extra_fees['is_fees_enable'] = empty( $feed_meta['disable_extra_fees'] );

            if( !empty( $feed_meta['extra_fee_label'] ) ) {
                $extra_fees['title'] = $feed_meta['extra_fee_label'];
            }

            if( empty( $feed_meta['disable_extra_fees'] ) ) {
                $extra_fees['credit_card_fees'] = !empty( $feed_meta['credit_card_fees'] ) ? get_gfb_format_price( $feed_meta['credit_card_fees'], false) : 0.00;
                $extra_fees['debit_card_fees'] = !empty( $feed_meta['debit_card_fees'] ) ? get_gfb_format_price( $feed_meta['debit_card_fees'], false) : 0.00;
                $extra_fees['ach_fees'] = !empty( $feed_meta['ach_fees'] ) ? $feed_meta['ach_fees'] : 0.00;
            } else {
                $extra_fees['credit_card_fees'] = 0.00;
                $extra_fees['debit_card_fees'] = 0.00;
                $extra_fees['ach_fees'] = 0.00;
            }

        }

    } catch (Exception $e) {
        $extra_fees = [];
    }

    return $extra_fees;
}

/**
 * Get product fields using form id.
 *
 * @param int $form_id Get form id
 * @return array
 */
function get_product_fields_by_form_id(  $form_id ) {

    $form = GFAPI::get_form($form_id);
    $fields  = GFAPI::get_fields_by_type( $form, array( 'product' ) );

    $product_fields = [];
    if( !empty( $fields ) && is_array( $fields ) ) {

        foreach ( $fields as $field ) {

            $temp_field = [];
            $field_id = !empty( $field->id ) ? $field->id : '';
            $input_type = !empty( $field->inputType ) ? $field->inputType :  '';
            $temp_field['id'] = $field_id;
            $temp_field['type'] = $input_type;
            $temp_field['label'] = !empty( $field->label ) ? $field->label : esc_html__('Product', 'angelleye-gravity-forms-braintree');
            switch ( $input_type ) {
                case 'singleproduct' :
                case 'hiddenproduct' :
                case 'calculation' :
                    $temp_field['group'] = 'multiple';
                    $temp_field['base_price'] = !empty( $field->basePrice ) ? get_price_without_formatter( $field->basePrice ) :  '';
                    $inputs = !empty( $field->inputs ) ? $field->inputs : '';
                    if( !empty( $inputs ) && is_array( $inputs  )  ) {

                        foreach ( $inputs as $input ) {

                            if( !empty( $input['label'] ) && 'Price' === $input['label']) {
                                $temp_field['price_id'] =  !empty( $input['id'] )  ? 'input_'.$input['id'] : '';
                            } elseif ( !empty( $input['label'] ) && 'Quantity' === $input['label'] ) {
                                $temp_field['quantity_id'] = !empty( $input['id'] )  ? 'input_'.$input['id'] : '';
                            }
                        }
                    }
                    break;
                case 'select' :
                case 'price' :
                case 'radio' :
                    $temp_field['group'] = 'single';
                    $temp_field['price_id'] = "input_{$field_id}";
                    break;
            }

            $product_fields[] = $temp_field;
        }
    }

    $total_fields  = GFAPI::get_fields_by_type( $form, array( 'total' ) );
    $total = [];
    if( !empty( $total_fields ) && is_array( $total_fields )  ) {
        $total_field = !empty( $total_fields[0] ) ? $total_fields[0] : [];
        $total['type'] = !empty( $total_field->type ) ? $total_field->type : '';
        $total['label'] = !empty( $total_field->label ) ? $total_field->label : '';
        $total['id'] = !empty( $total_field->id ) ? $total_field->id : '';
        $total['input_id'] = !empty( $total_field->id ) ? "input_{$total_field->id}" : '';
    }

    $gform_braintree = new Plugify_GForm_Braintree();
    $payment_feed = $gform_braintree->get_payment_feed([], $form);
    $feed_meta = !empty( $payment_feed['meta'] ) ? $payment_feed['meta'] : '';
    $transaction_type = !empty( $feed_meta['transactionType'] ) ? $feed_meta['transactionType'] : '';
    $payment_amount = !empty( $feed_meta['paymentAmount'] ) ? $feed_meta['paymentAmount'] : '';

    if( !empty( $transaction_type ) && $transaction_type === 'subscription' ) {
        $payment_amount = !empty( $feed_meta['recurringAmount'] ) ? $feed_meta['recurringAmount'] : '';
    }

    if( !empty( $payment_amount ) && $payment_amount !== 'form_total' &&  !empty( $product_fields ) && is_array( $product_fields ) ) {

        $feed_products = [];

        foreach ( $product_fields as $product_field ) {

            if( !empty( $product_field['id'] ) && $product_field['id'] == $payment_amount ) {
                $feed_products[] = $product_field;
                break;
            }
        }

        $product_fields = $feed_products;
    }

    return [
        'products' => $product_fields,
        'total' => $total,
    ];
}

/**
 * Get formatted price based on gravity form currency settings.
 *
 * @param float $price Get price
 * @param bool $symbol Get is enable symbol
 * @return int|string
 */
function get_gfb_format_price( $price = 0, $symbol = true ) {

    $currency_code = GFCommon::get_currency();
    $currency = RGCurrency::get_currency($currency_code);
    $symbol_padding = !empty( $currency['symbol_padding'] ) ? $currency['symbol_padding'] : '';

    $symbol_left  = ! empty( $currency['symbol_left'] ) ? $currency['symbol_left'] . $symbol_padding : '';
    $symbol_right = ! empty( $currency['symbol_right'] ) ? $symbol_padding. $currency['symbol_right'] : '';

    if( !empty( $price ) && $price >= 0 ) {
        $price = number_format( $price, $currency['decimals'], $currency['decimal_separator'], $currency['thousand_separator'] );
    }

    if( $symbol ) {
        return $symbol_left . $price . $symbol_right;
    }

    return $price;
}

/**
 * Get price for product summary.
 *
 * @param array $args Get products price arguments.
 * @return array
 */
function get_gfb_prices( $args = [] ) {

    $form_id = !empty( $args['form_id'] ) ? $args['form_id'] : '';
    $products = !empty( $args['products'] ) ? $args['products'] : 0;
    $card_type = !empty( $args['card_type'] ) ? $args['card_type'] : '';

    $extra_fees = angelleye_get_extra_fees( $form_id );
    $is_fees_enable  = !empty( $extra_fees['is_fees_enable'] ) ? $extra_fees['is_fees_enable']  : '';

    $extra_fee_amount = 0;
    if( !empty( $is_fees_enable ) && !empty( $card_type ) && $card_type === 'CreditCard' ) {
        $extra_fee_amount = !empty( $extra_fees['credit_card_fees'] ) ? $extra_fees['credit_card_fees'] : '';
    } elseif ( !empty( $is_fees_enable ) && !empty( $card_type ) && $card_type === 'DebitCard' ) {
        $extra_fee_amount = !empty( $extra_fees['debit_card_fees'] ) ? $extra_fees['debit_card_fees'] : '';
    } elseif ( !empty( $is_fees_enable ) && !empty( $card_type ) && $card_type === 'ACH' ) {
        $extra_fee_amount = !empty( $extra_fees['ach_fees'] ) ? $extra_fees['ach_fees'] : '';
    }

    $subtotal = 0;
    if( !empty( $products ) && is_array( $products ) ) {
        foreach ( $products as $product) {
            $product_price = !empty( $product['price'] ) ? $product ['price'] : '';
            $product_quantity = !empty( $product['quantity'] ) ? $product ['quantity'] : '';

            if( !empty( $product_price ) && !empty( $product_quantity ) ) {
                $subtotal += $product_price * $product_quantity;
            }
        }
    }

    $convenience_fee = 0;
    if( !empty( $is_fees_enable ) && !empty( $subtotal ) && !empty( $extra_fee_amount ) ) {
        $convenience_fee = ( $subtotal * $extra_fee_amount ) / 100;
    }

    $final_total = $subtotal + $convenience_fee;

    return [
        'card_type' => $card_type,
        'products' => $products,
        'subtotal' => get_gfb_format_price($subtotal),
        'convenience_fee' => get_gfb_format_price($convenience_fee),
        'total' => get_gfb_format_price($final_total),
        'extra_fee_amount' => $extra_fee_amount,
    ];
}

/**
 * Get price without formatter.
 *
 * @param float $price Get price
 * @return int|string
 */
function get_price_without_formatter( $price ) {

    if( empty( $price ) ) {
        return 0;
    }

    if( str_contains($price, '|' ) ) {
        $temp_price = explode('|', $price);
        $price = !empty( $temp_price[1] ) ? $temp_price[1] : 0;
    }

    return preg_replace('/[^0-9.,]/', '', $price );
}

/**
 * Get product label.
 *
 * @param string $value Get product value
 * @param string $default_value Get product default value
 * @return mixed|string
 */
function get_selected_product_label( $value, $default_value = '' ){

    $product_label = '';
    if( str_contains( $value, '|' ) ) {
        $temp_price = explode('|', $value);
        $product_label = !empty( $temp_price[0] ) ? $temp_price[0] : '';
    }

    return !empty( $product_label  ) ? $product_label : $default_value;
}

/**
 * Get product field id filter.
 *
 * @param string $product_field Get product field id.
 * @return array|string|string[]
 */
function get_product_field_filter( $product_field ) {

    return !empty( $product_field ) ? str_replace('.', '_', $product_field ) : '';
}