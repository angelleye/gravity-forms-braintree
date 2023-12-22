<?php

/**
 * Functions used by plugins
 */
/**
 * Queue updates for the Angell EYE Updater
 */
if (!function_exists('angelleye_queue_update')) {

    function angelleye_queue_update($file, $file_id, $product_id) {
        global $angelleye_queued_updates;

        if (!isset($angelleye_queued_updates))
            $angelleye_queued_updates = array();

        $plugin = new stdClass();
        $plugin->file = $file;
        $plugin->file_id = $file_id;
        $plugin->product_id = $product_id;

        $angelleye_queued_updates[] = $plugin;
    }

}


/**
 * Load installer for the AngellEYE Updater.
 * @return $api Object
 */
if (!class_exists('AngellEYE_Updater') && !function_exists('angell_updater_install')) {

    function angell_updater_install($api, $action, $args) {
        $download_url = AEU_ZIP_URL;

        if ('plugin_information' != $action ||
                false !== $api ||
                !isset($args->slug) ||
                'angelleye-updater' != $args->slug
        )
            return $api;

        $api = new stdClass();
        $api->name = 'AngellEYE Updater';
        $api->version = '';
        $api->download_link = esc_url($download_url);
        return $api;
    }

    add_filter('plugins_api', 'angell_updater_install', 10, 3);
}

/**
 * AngellEYE Installation Prompts
 */
if (!class_exists('AngellEYE_Updater') && !function_exists('angell_updater_notice')) {

    /**
     * Display a notice if the "AngellEYE Updater" plugin hasn't been installed.
     * @return void
     */
    function angell_updater_notice() {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
        if (in_array('angelleye-updater/angelleye-updater.php', $active_plugins))
            return;

        $slug = 'angelleye-updater';
        $install_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=' . $slug), 'install-plugin_' . $slug);
        $activate_url = 'plugins.php?action=activate&plugin=' . urlencode('angelleye-updater/angelleye-updater.php') . '&plugin_status=all&paged=1&s&_wpnonce=' . urlencode(wp_create_nonce('activate-plugin_angelleye-updater/angelleye-updater.php'));

        $message = '<a href="' . esc_url($install_url) . '">Install the Angell EYE Updater plugin</a> to get updates for your Angell EYE plugins.';
        $is_downloaded = false;
        $plugins = array_keys(get_plugins());
        foreach ($plugins as $plugin) {
            if (strpos($plugin, 'angelleye-updater.php') !== false) {
                $is_downloaded = true;
                $message = '<a href="' . esc_url(admin_url($activate_url)) . '"> Activate the Angell EYE Updater plugin</a> to get updates for your Angell EYE plugins.';
            }
        }
        echo '<div id="angelleye-updater-notice" class="updated notice updater-dismissible"><p>' . $message . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>' . "\n";
    }
    
    function angelleye_updater_dismissible_admin_notice() {
        set_transient( 'angelleye_updater_notice_hide', 'yes', MONTH_IN_SECONDS );
    }
    if ( false === ( $angelleye_updater_notice_hide = get_transient( 'angelleye_updater_notice_hide' ) ) ) {
        add_action('admin_notices', 'angell_updater_notice');
    }
    add_action( 'wp_ajax_angelleye_updater_dismissible_admin_notice', 'angelleye_updater_dismissible_admin_notice' );
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
            $extra_fees['is_fees_enable'] =  $settings['enable_extra_fees'];
            $extra_fees['credit_card_fees'] =  !empty( $settings['credit_card_fees'] ) ? $settings['credit_card_fees'] : '';
            $extra_fees['debit_card_fees'] =  !empty( $settings['debit_card_fees'] ) ? $settings['debit_card_fees'] : '';
            $extra_fees['ach_fees'] =  !empty( $settings['ach_fees'] ) ? $settings['ach_fees'] : '';
        }

        $form = GFAPI::get_form( $form_id );

        if( !empty( $form['override_extra_fees'] ) ) {

            $extra_fees['is_fees_enable'] = empty( $form['disable_extra_fees'] );

            if( empty( $form['disable_extra_fees'] ) ) {
                $extra_fees['credit_card_fees'] = !empty( $form['credit_card_fees'] ) ? $form['credit_card_fees'] : '';
                $extra_fees['debit_card_fees'] = !empty( $form['debit_card_fees'] ) ? $form['debit_card_fees'] : '';
                $extra_fees['ach_fees'] = !empty($form['ach_fees']) ? $form['ach_fees'] : '';
            } else {
                $extra_fees['credit_card_fees'] = 0;
                $extra_fees['debit_card_fees'] = 0;
                $extra_fees['ach_fees'] = 0;
            }

        }

    } catch (Exception $e) {
        $extra_fees = [];
    }

    return $extra_fees;
}

function get_product_fields_by_form_id(  $form_id ) {

    $product_fields = [];

    $form = GFAPI::get_form($form_id);

    foreach ( $form['fields'] as $field ) {

        if ( $field->type === 'product' ) {
            $field_id = !empty( $field->id ) ? $field->id : '';
            $input_type = !empty( $field['inputType'] ) ? $field['inputType'] :  '';
            $product_fields['type'] = $input_type;
            switch ( $input_type ) {
                case 'singleproduct' :
                case 'hiddenproduct' :
                case 'calculation' :
                    $product_fields['group'] = 'multiple';
                    $product_fields['base_price'] = !empty( $field['basePrice'] ) ? get_price_without_fomatter( $field['basePrice'] ) :  '';
                    $inputs = !empty( $field['inputs'] ) ? $field['inputs'] : '';
                    if( !empty( $inputs ) && is_array( $inputs  )  ) {
                        foreach ( $inputs as $input ) {
                            if( !empty( $input['label'] ) && 'Price' === $input['label']) {
                                $product_fields['price_id'] = !empty( $input['id'] )  ? 'input_'.$input['id'] : '';
                            } elseif ( !empty( $input['label'] ) && 'Quantity' === $input['label'] ) {
                                $product_fields['quantity_id'] = !empty( $input['id'] )  ? 'input_'.$input['id'] : '';
                            }
                        }
                    }
                break;
                case 'select' :
                case 'price' :
                case 'radio' :
                    $product_fields['group'] = 'single';
                    $product_fields['price_id'] = "input_{$field_id}";
                break;
            }
        }
    }

    return $product_fields;
}

function get_gfb_format_price( $price = 0 ) {

    $currency_code = GFCommon::get_currency();
    $currency = RGCurrency::get_currency($currency_code);
    $symbol_padding = !empty( $currency['symbol_padding'] ) ? $currency['symbol_padding'] : '';

    $symbol_left  = ! empty( $currency['symbol_left'] ) ? $currency['symbol_left'] . $symbol_padding : '';
    $symbol_right = ! empty( $currency['symbol_right'] ) ? $symbol_padding. $currency['symbol_right'] : '';

    if( !empty( $price ) && $price > 0 ) {
        $price = number_format( $price, $currency['decimals'], $currency['decimal_separator'], $currency['thousand_separator'] );
    }

    return $symbol_left . $price . $symbol_right;
}

function get_gfb_prices( $args = [] ) {

    $form_id = !empty( $args['form_id'] ) ? $args['form_id'] : '';
    $product_price = !empty( $args['product_price'] ) ? $args['product_price'] : 0;
    $product_qty = !empty( $args['product_qty'] ) ? $args['product_qty'] : '';
    $card_type = !empty( $args['card_type'] ) ? $args['card_type'] : '';

    $extra_fees = angelleye_get_extra_fees( $form_id );

    $extra_fee_amount = 0;
    if( !empty( $card_type ) && $card_type === 'CreditCard' ) {
        $extra_fee_amount = !empty( $extra_fees['credit_card_fees'] ) ? $extra_fees['credit_card_fees'] : '';
    } elseif ( !empty( $card_type ) && $card_type === 'DebitCard' ) {
        $extra_fee_amount = !empty( $extra_fees['debit_card_fees'] ) ? $extra_fees['debit_card_fees'] : '';
    } elseif ( !empty( $card_type ) && $card_type === 'ACH' ) {
        $extra_fee_amount = !empty( $extra_fees['ach_fees'] ) ? $extra_fees['ach_fees'] : '';
    }

    $subtotal = $product_price * $product_qty;
    $convenience_fee = 0;
    if( !empty( $subtotal ) && !empty( $extra_fee_amount ) ) {
        $convenience_fee = ( $subtotal * $extra_fee_amount ) / 100;
    }

    $final_total = $subtotal + $convenience_fee;

    return [
        'card_type' => $card_type,
        'product_price' => get_gfb_format_price($product_price),
        'product_qty' => $product_qty,
        'subtotal' => get_gfb_format_price($subtotal),
        'convenience_fee' => get_gfb_format_price($convenience_fee),
        'total' => get_gfb_format_price($final_total),
        'extra_fee_amount' => $extra_fee_amount,
    ];
}

function get_price_without_fomatter( $price ) {

    if( empty( $price ) ) {
        return 0;
    }

    return preg_replace('/[^0-9.,]/', '', $price );
}