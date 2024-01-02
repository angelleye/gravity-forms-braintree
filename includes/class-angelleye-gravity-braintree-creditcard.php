<?php
if ( ! class_exists( 'GFForms' ) ) {
    die();
}

/**
 * Check Angelleye_Gravity_Braintree_CreditCard_Field class exists or not.
 */
if ( ! class_exists( 'Angelleye_Gravity_Braintree_CreditCard_Field' ) ) {

    /**
     * Class Angelleye_Gravity_Braintree_CreditCard_Field
     *
     * This class provides the Braintree CreditCard fields functionality for Gravity Forms.
     */
    class Angelleye_Gravity_Braintree_CreditCard_Field extends GF_Field {

        /**
         * @var string $type The field type.
         */
        public $type = 'braintree_credit_card';

        /**
         * Return the field title, for use in the form editor.
         *
         * @return string|void
         */
        public function get_form_editor_field_title() {
            return __( 'Braintree Credit Card', 'angelleye-gravity-forms-braintree' );
        }

        /**
         * Assign the field button to the Pricing Fields group.
         *
         * @return array
         */
        public function get_form_editor_button() {
            return [ 'group' => 'pricing_fields', 'text' => 'Braintree CC' ];
        }

        /**
         * The settings which should be available on the field in the form editor.
         *
         * @return array
         */
        function get_form_editor_field_settings() {
            return [
                'label_setting',
                'admin_label_setting',
                'description_setting',
                'error_message_setting',
                'css_class_setting',
                'conditional_logic_field_setting',
                'rules_setting',
                'input_placeholders_setting',
                'label_placement_setting'
            ];
        }

        /**
         * Returns the field inner markup.
         *
         * @param array  $form  The Form Object currently being processed.
         * @param string $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
         * @param null   $entry Null or the Entry Object currently being edited.
         *
         * @return false|string
         * @throws \Braintree\Exception\Configuration
         */
        public function get_field_input( $form, $value = '', $entry = null ) {

            $is_entry_detail = $this->is_entry_detail();
            $is_form_editor  = $this->is_form_editor();

            $form_id  = $form['id'];
            $id       = intval( $this->id );
            $field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
            $form_id  = ( $is_entry_detail || $is_form_editor ) && empty( $form_id ) ? rgget( 'id' ) : $form_id;

            $inputs = !empty( $this->inputs ) ? $this->inputs : '';
            $input_field_id = !empty( $inputs[0]['id'] ) ? $inputs[0]['id'] : $id;

            $Plugify_GForm_Braintree = new Plugify_GForm_Braintree();
            $gateway                 = $Plugify_GForm_Braintree->getBraintreeGateway();
            $clientToken             = $gateway->clientToken()->generate();

            ob_start();

            $extra_fees = angelleye_get_extra_fees( $form_id );

            $dropin_container_id = uniqid("{$form_id}_");
            $gfb_obj = [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'form_id' => (int)$form_id,
                'is_fees_enable' => !empty( $extra_fees['is_fees_enable'] ) ? $extra_fees['is_fees_enable'] : false,
                'card_type' => $this->type,
                'is_admin' => is_admin(),
                'client_token' => $clientToken,
                'container_id' => $dropin_container_id,
                'nonce' => wp_create_nonce('preview-payment-nonce'),
            ];

			?>
            <div class='ginput_container gform_payment_method_options ginput_container_<?php echo $this->type; ?>'
                 id='<?php echo $field_id; ?>'>
                <div id="dropin-container_<?php echo $dropin_container_id; ?>"></div>
                <input type="hidden" id="nonce_<?php echo $form_id; ?>" name="payment_method_nonce"/>
                <input type="hidden" id="payment_card_type_<?php echo $form_id; ?>" name="payment_card_type"/>
                <input type="hidden" id="payment_card_details_<?php echo $form_id; ?>" name="input_<?php echo $input_field_id; ?>"/>
            </div>
            <script type="text/javascript">
                initBraintreeDropIn('<?php echo $form_id; ?>', <?php echo json_encode($gfb_obj); ?>);
            </script>
            <?php
            $html = ob_get_contents();
            ob_get_clean();

            return $html;
        }
    }
}