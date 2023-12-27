<?php

use Gravity_Forms\Gravity_Forms\Orders\Exporters\GF_Entry_Details_Order_Exporter;
use Gravity_Forms\Gravity_Forms\Orders\Factories\GF_Order_Factory;
use \Gravity_Forms\Gravity_Forms\Orders\Summaries\GF_Order_Summary;

defined('ABSPATH') or die('Direct Access Not allowed');

class AngelleyeGravityBraintreeFieldMapping
{
    public function __construct()
    {
        //if(isset($_GET['gf_edit_forms']))
        add_filter( 'gform_form_settings_menu', array($this, 'addBraintreeMappingMenu' ), 100, 2);
        add_action( 'gform_form_settings_page_braintree_mapping_settings_page', array($this, 'braintreeFieldMapping') );

        add_action('wp_ajax_save_gravity_form_mapping', array($this,'saveMapping'));

        add_filter('angelleye_braintree_parameter', array($this, 'mapGravityBraintreeFields'),10, 4);
        add_filter('angelleye_braintree_parameter', array($this, 'manage_convenience_fees'),15, 4);
        add_filter('gform_entry_created', array($this, 'manage_transaction_response'),10, 2);
        add_filter('gform_order_summary', array($this, 'gform_order_summary'),10, 5);

        add_filter( 'gform_field_content', array($this,'addNoticeToCreditCardForm'), 10, 5 );
    }

    public function addBraintreeMappingMenu($menu_items, $form_id)
    {
        if(isset($_GET['page']) && $_GET['page']=='gf_edit_forms') {
            if($this->isCreditCardFieldExist($form_id)) {
                $menu_items[] = array(
                    'name' => 'braintree_mapping_settings_page',
                    'label' => __('Braintree Field Mapping')
                );
            }
        }
        return $menu_items;
    }

    public function braintreeFieldMapping()
    {
        GFFormSettings::page_header();
        require dirname(__FILE__).'/pages/angelleye-braintree-field-map-form.php';
        GFFormSettings::page_footer();
    }

    public function isCreditCardFieldExist($id)
    {
        $get_form = GFAPI::get_form($id);
        if(isset($get_form['fields'])) {
            foreach ($get_form['fields'] as $single_field) {
                if ($single_field->type == 'creditcard' || $single_field->type=='braintree_ach' || $single_field->type=='braintree_credit_card' ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function saveMapping()
    {
        $form_id = $_POST['gform_id'];
        //sanitize input values
        $final_mapping = [];
        foreach ($_POST['gravity_form_field'] as $key=>$field_id){
            if(empty($field_id)) continue;
            $final_mapping[$key]  = $field_id;
        }

        $custom_fields=[];
        if(isset($_POST['gravity_form_custom_field_name'])){
            $mapped_field_ids = $_POST['gravity_form_custom_field'];
            foreach ($_POST['gravity_form_custom_field_name'] as $key => $single_custom_field_name){
                if(!isset($mapped_field_ids[$key]) || empty($mapped_field_ids[$key]))
                    continue;

                $custom_fields[$single_custom_field_name] = $mapped_field_ids[$key];
            }
        }
        if(count($custom_fields))
            $final_mapping['custom_fields'] = $custom_fields;

        $get_form = GFAPI::get_form($form_id);
        $get_form['braintree_fields_mapping'] = $final_mapping;
        GFAPI::update_form($get_form, $form_id);

        die(json_encode(['status'=>true,'message'=>'Mapping has been updated successfully.']));
    }

    function assignArrayByPath(&$arr, $path, $value, $separator='.') {
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            $arr = &$arr[$key];
        }

        $arr = $value;
    }

    public function mapGravityBraintreeFields($args, $submission_data, $form, $entry)
    {

        $braintree_mapping = isset($form['braintree_fields_mapping'])?$form['braintree_fields_mapping']:[];
        $final_array = [];

        if(count($braintree_mapping)){
            foreach ($braintree_mapping as $key_name => $single_mapid)
            {
                if(is_array($single_mapid)){
                    if($key_name=='custom_fields') {
                        foreach ($single_mapid as $subkey_name => $sub_mapid) {
                            if (isset($entry[$sub_mapid])) {
                                $this->assignArrayByPath($final_array, 'customFields.' . $subkey_name,  $entry[$sub_mapid]);
                            }
                        }
                    }
                }else {
                    if (isset($entry[$single_mapid])) {
                        $this->assignArrayByPath($final_array, $key_name, $entry[$single_mapid]);
                    }
                }
            }
        }
        if(count($final_array)){
            $args = array_merge($args, $final_array);
        }

        //var_dump($args); die;
        return $args;
    }

    function addNoticeToCreditCardForm( $field_content, $field,  $value, $lead_id, $form_id ) {
        if(is_admin()) {
            if ($field->type == 'creditcard' || $field->type == 'braintree_credit_card') {
                //echo ($field_content); die;
                $first_label_position = strpos($field_content, '<label');
                if ($first_label_position !== false) {

                    $mapping_page_link = add_query_arg([
                        'view' => 'settings',
                        'subview' => 'braintree_mapping_settings_page',
                        'id' => $form_id
                    ], menu_page_url('gf_edit_forms', false));

                    if(!AngelleyeGravityFormsBraintree::isBraintreeFeedActive()) {
                        $feed_page_link = add_query_arg([
                            'view' => 'settings',
                            'subview' => 'gravity-forms-braintree',
                            'id' => $form_id
                        ], menu_page_url('gf_edit_forms', false));
                        $add_text[] = "To process payments, please configure a <a target='_blank' href='$feed_page_link'>Braintree feed</a>.";
                    }

                    $add_text[] = "You can use <a target='_blank' href='$mapping_page_link'>Braintree Field Mapping</a> to pass specific data values into the Braintree transaction details.";
                    $final_text = '';
                    foreach ($add_text as $key=>$single_text){
                        $final_text.=($final_text!=''?'<br>':'').($key+1).") ".$single_text;
                    }
                    $replacement_text = "<div style='background-color: #f5e5cd;padding: 10px;color: #000;opacity: 0.83;transition: opacity 0.6s;margin:10px 0;'><p style='margin: 0'>$final_text</p></div>";

                    $field_content = substr_replace($field_content,$replacement_text, $first_label_position, 0);
                }
            }
        }
        return $field_content;
    }

    public function manage_convenience_fees( $args, $submission_data, $form, $entry ) {

        if( ! function_exists('angelleye_get_extra_fees') ) {
            require_once GRAVITY_FORMS_BRAINTREE_DIR_PATH.'includes/angelleye-functions.php';
        }

        $form_id = absint( rgar( $form, 'id' ) );
        $extra_fees = angelleye_get_extra_fees( $form_id );
        $extra_fees_label = !empty( $extra_fees['title'] ) ? $extra_fees['title'] : '';

        $card_type = !empty( $_POST['payment_card_type'] ) ? $_POST['payment_card_type'] : '';
        if( !empty( $_POST['ach_token'] ) ) {
            $card_type = !empty( $_POST['ach_card_type'] ) ? $_POST['ach_card_type'] : '';
        }

        $product_fields = get_product_fields_by_form_id( $form_id );
        $products = [];
        if( !empty( $product_fields['products'] ) && is_array( $product_fields['products'] ) ) {

            foreach ( $product_fields['products'] as $product_field ) {
                $id = !empty( $product_field['id'] ) ? $product_field['id'] : '';
                $label = !empty($product_field['label']) ? $product_field['label'] : '';
                $product_group = !empty($product_field['group']) ? $product_field['group'] : '';
                $price_id = !empty($product_field['price_id']) ? $product_field['price_id'] : '';
                $quantity_id = !empty($product_field['quantity_id']) ? $product_field['quantity_id'] : '';

                if( !empty( $product_group ) && $product_group === 'multiple' ) {
                    $price_id = !empty( $product_field['price_id'] ) ? get_product_field_filter( $product_field['price_id'] ) : '';
                    $quantity_id = !empty( $product_field['quantity_id'] ) ? get_product_field_filter( $product_field['quantity_id'] ) : '';
                }

                $product_price = !empty( $_POST[$price_id] ) ? get_price_without_fomatter( $_POST[$price_id] ) : 0;
                $product_qty = !empty( $_POST[$quantity_id] ) ? $_POST[$quantity_id] : 1;

                $label = !empty( $product_price ) ? get_selected_product_label( $product_price, $label ) : '';

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

        $line_items = [];
        if( !empty( $cart_prices['products'] ) && is_array( $cart_prices['products'] ) ) {

            foreach ( $cart_prices['products'] as $cart_product ) {

                $label = !empty( $cart_product['label'] ) ? $cart_product['label'] : esc_html__( 'Product', 'angelleye-gravity-forms-braintree' );
                $product_price = !empty( $cart_product['price'] ) ? $cart_product['price'] : '';
                $product_quantity = !empty( $cart_product['quantity'] ) ? $cart_product['quantity'] : '';
                $item_unit_amount = get_price_without_fomatter($product_price);
                $total_item_amount = $item_unit_amount * $product_quantity;

                $line_items[] = [
                    'name' => $label,
                    'kind' => Braintree\TransactionLineItem::DEBIT,
                    'quantity' => $product_quantity,
                    'unitAmount' => $item_unit_amount,
                    'totalAmount' => $total_item_amount,
                ];
            }
        }

        if( !empty( $extra_fees['is_fees_enable'] ) ) {

            if( !empty( $cart_prices['convenience_fee'] ) ) {

                $convenience_fee_amount = get_price_without_fomatter($cart_prices['convenience_fee']);
                $line_items[] = [
                    'name' => !empty( $extra_fees_label ) ? $extra_fees_label : esc_html__('Convenience Fee', 'angelleye-gravity-forms-braintree'),
                    'kind' => Braintree\TransactionLineItem::DEBIT,
                    'quantity' => 1,
                    'unitAmount' => $convenience_fee_amount,
                    'totalAmount' => $convenience_fee_amount,
                ];
            }
        }

        if( !empty( $line_items ) && is_array( $line_items ) ) {

            $args['lineItems'] = $line_items;

            $total = !empty( $cart_prices['total'] ) ? get_price_without_fomatter( $cart_prices['total'] ) : '';
            if( !empty( $total ) ) {
                $args['amount'] = $total;
            }
        }

        return $args;
    }

    public function manage_transaction_response(  $entry, $form ) {

        if( ! function_exists('angelleye_get_extra_fees') ) {
            require_once GRAVITY_FORMS_BRAINTREE_DIR_PATH.'includes/angelleye-functions.php';
        }

        $form_id = absint( rgar( $form, 'id' ) );
        $entry_id   = absint( rgar( $entry, 'id' ) );
        $extra_fees = angelleye_get_extra_fees( $form_id );

        $transaction_response = [];
        if( !empty( $extra_fees['is_fees_enable'] ) ) {

            $card_type = !empty( $_POST['payment_card_type'] ) ? $_POST['payment_card_type'] : '';
            if( !empty( $_POST['ach_token'] ) ) {
                $card_type = !empty( $_POST['ach_card_type'] ) ? $_POST['ach_card_type'] : '';
            }

            $product_fields = get_product_fields_by_form_id( $form_id );
            $products = [];

            if( !empty( $product_fields['products'] ) && is_array( $product_fields['products'] ) ) {

                foreach ($product_fields['products'] as $product_field) {
                    $id = !empty( $product_field['id'] ) ? $product_field['id'] : '';
                    $label = !empty( $product_field['label'] ) ? $product_field['label'] : '';
                    $product_group = !empty( $product_field['group'] ) ? $product_field['group'] : '';
                    $price_id = !empty( $product_field['price_id'] ) ? $product_field['price_id'] : '';
                    $quantity_id = !empty( $product_field['quantity_id'] ) ? $product_field['quantity_id'] : '';

                    if( !empty( $product_group ) && $product_group === 'multiple' ) {
                        $price_id = !empty( $product_field['price_id'] ) ? get_product_field_filter( $product_field['price_id'] ) : '';
                        $quantity_id = !empty( $product_field['quantity_id'] ) ? get_product_field_filter( $product_field['quantity_id'] ) : '';
                    }

                    $product_price = !empty( $_POST[$price_id] ) ? get_price_without_fomatter( $_POST[$price_id] ) : 0;
                    $product_qty = !empty( $_POST[$quantity_id] ) ? $_POST[$quantity_id] : 1;

                    $label = !empty( $product_price ) ? get_selected_product_label( $product_price, $label ) : '';

                    $products[] = [
                        'id'    => $id,
                        'label' => $label,
                        'price' => $product_price,
                        'quantity' => $product_qty,
                    ];
                }
            }

            $transaction_response = get_gfb_prices([
                'form_id' => $form_id,
                'products' => $products,
                'card_type' => $card_type,
            ]);

            $transaction_response['is_fees_enable'] = $extra_fees['is_fees_enable'];
            $transaction_response['fees_label'] = !empty( $extra_fees['title'] ) ? $extra_fees['title'] : '';
        }

        gform_update_meta( $entry_id, 'gform_transaction_response', $transaction_response );
    }

    public function gform_order_summary( $order_summary_markup, $form, $lead, $products, $type ) {

        $form_id   = absint( rgar( $form, 'id' ) );
        $entry_id   = absint( rgar( $lead, 'id' ) );

        $response = gform_get_meta($entry_id, 'gform_transaction_response');

        if( !empty( $response ) ) {

            if( !empty( $response['is_fees_enable'] ) ) {

                $fees_label = !empty( $response['fees_label'] ) ? $response['fees_label'] : esc_html__('Convenience Fee','angelleye-gravity-forms-braintree');

                GF_Order_Factory::load_dependencies();
                $order         = GF_Order_Factory::create_from_entry( $form, $lead, false, true, true);
                $order_summary = ( new GF_Entry_Details_Order_Exporter( $order ) )->export();
                if ( empty( $order_summary['rows'] ) ) {
                    return '';
                }

                $order_summary['labels'] = GF_Order_Summary::get_labels( $form );

                $sub_total = !empty( $response['subtotal'] ) ? $response['subtotal'] : 0;
                $total = !empty( $response['total'] ) ? $response['total'] : 0;
                $extra_fee_amount = !empty( $response['extra_fee_amount'] ) ? $response['extra_fee_amount'] : 0;
                $convenience_fee = !empty( $response['convenience_fee'] ) ? $response['convenience_fee'] : 0;

                $order_summary['totals']['sub_total_money'] = $sub_total;
                $order_summary['totals']['sub_total'] = get_price_without_fomatter($sub_total);
                $order_summary['totals']['total_money'] = $total;
                $order_summary['totals']['total'] = get_price_without_fomatter($total);

                $order_summary['rows']['footer'] = [
                    [
                        'name' => esc_html__("{$fees_label} ({$extra_fee_amount}%)",'angelleye-gravity-forms-braintree'),
                        'price_money' => $convenience_fee,
                        'sub_total_money' => $convenience_fee,
                    ]
                ];

                $template = 'view-pricing-fields-html.php';
                if( !empty( $_GET['page'] ) && $_GET['page'] === 'gf_entries' && !empty( $_GET['view'] ) && $_GET['view'] === 'entry' ) {
                    $template = 'view-order-summary.php';
                }

                ob_start();
                include_once GRAVITY_FORMS_BRAINTREE_DIR_PATH.'templates/'.$template;
                $order_summary_markup = ob_get_contents();
                ob_get_clean();

            }
        }

        return $order_summary_markup;
    }
}
