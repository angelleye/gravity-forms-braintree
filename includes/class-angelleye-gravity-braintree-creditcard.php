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

            if( ! function_exists('angelleye_get_extra_fees') ) {
                require_once GRAVITY_FORMS_BRAINTREE_DIR_PATH.'includes/angelleye-functions.php';
            }

            $extra_fees = angelleye_get_extra_fees( $form_id );

            $gfb_obj = [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'form_id' => $form_id,
                'is_fees_enable' => !empty( $extra_fees['is_fees_enable'] ) ? $extra_fees['is_fees_enable'] : false,
                'card_type' => $this->type,
                'is_admin' => is_admin(),
            ];

            $dropin_container_id = uniqid("{$form_id}_");
			?>
            <div class='ginput_container gform_payment_method_options ginput_container_<?php echo $this->type; ?>'
                 id='<?php echo $field_id; ?>'>
                <div id="dropin-container_<?php echo $dropin_container_id; ?>"></div>
                <input type="hidden" id="nonce" name="payment_method_nonce"/>
                <input type="hidden" id="payment_card_type" name="payment_card_type"/>
                <input type="hidden" id="payment_card_details" name="input_<?php echo $input_field_id; ?>"/>
            </div>
            <script type="text/javascript">
                var gfbObj = <?php echo json_encode($gfb_obj); ?>;

                function manageGfromFields( is_preview = false ) {

                    const form = document.getElementById('gform_<?php echo $form_id; ?>');
                    let cardTypeEl = form.querySelectorAll('.gfield--type-'+gfbObj.card_type);
                    if( undefined !== cardTypeEl  &&  null !== cardTypeEl ) {
                        cardTypeEl.forEach(function(element) {
                            if( is_preview ) {
                                element.style.display = 'none';
                            } else {
                                element.style.display = 'block';
                            }
                        });
                    }

                    let gformFields = document.getElementById('gform_fields_<?php echo $form_id; ?>');
                    if( undefined !== gformFields && null !== gformFields ) {
                        gformFields.classList.add('fields-preview');
                        var inputElements = gformFields.querySelectorAll('input, input[type="text"], input[type="number"], input[type="radio"], input[type="checkbox"], select, textarea');
                        inputElements.forEach(function(element) {
                            if( is_preview ) {
                                element.readOnly = true;
                                element.disabled = true;
                            } else {
                                element.readOnly = false;
                                element.disabled = false;
                            }
                        });
                    }

                    let gformFooter = form.querySelectorAll('.gform_footer');
                    if( undefined !== gformFooter  &&  null !== gformFooter ) {
                        gformFooter.forEach(function(footer) {
                            footer.style.display = 'none';
                        });
                    }

                    let gformPreview = document.getElementById('gform_preview_<?php echo $form_id; ?>');
                    if( undefined !== gformPreview && null !== gformPreview ) {
                        gformPreview.style.display = 'block';
                    }

                    let gformSpinner = document.getElementById('gform_ajax_spinner_<?php echo $form_id; ?>');
                    if (undefined !== gformSpinner && null !== gformSpinner) {
                        gformSpinner.remove();
                    }

                    manageScrollIntoView('gform_preview_<?php echo $form_id; ?>');
                }

                function managePreviewBeforePayment( payload ) {

                    displayPaymentPreview(payload);
                    manageGfromFields(true);
                }

                function displayPaymentPreview( payload ) {

                    if( undefined !== payload.type && null !== payload.type  ) {

                        jQuery.ajax({
                            type: 'POST',
                            dataType: 'json',
                            url: gfbObj.ajax_url,
                            data: {
                                action: 'gform_payment_preview_html',
                                nonce: '<?php echo wp_create_nonce('preview-payment-nonce'); ?>',
                                card_type: payload.type,
                                form_id: <?php echo $form_id; ?>,
                                form_data: jQuery('#gform_<?php echo $form_id; ?>').serializeArray()
                            },
                            success: function ( result ) {
                                if(result.status) {
                                    let gFormPreview = document.getElementById('gform_preview_<?php echo $form_id; ?>');
                                    gFormPreview.innerHTML = result.html;
                                    managePaymentActions();
                                } else {
                                    location.reload();
                                }
                            }
                        });
                    }
                }

                function manageScrollIntoView( sectionID ) {
                    var scrollSection = document.getElementById(sectionID);
                    if (scrollSection) {
                        scrollSection.scrollIntoView({ behavior: 'smooth' });
                    }
                }

                function managePaymentActions() {

                    manageScrollIntoView('gform_preview_<?php echo $form_id; ?>');

                    let paymentCancel = document.getElementById('gform_payment_cancel_<?php echo $form_id; ?>');
                    if( undefined !== paymentCancel && null !== paymentCancel ) {
                        paymentCancel.addEventListener('click', function () {
                            location.reload();
                        });
                    }

                    let paymentProcess = document.getElementById('gform_payment_pay_<?php echo $form_id; ?>');
                    if( undefined !== paymentProcess && null !== paymentProcess ) {
                        paymentProcess.addEventListener('click', function () {
                            manageGfromFields();
                            document.getElementById('gform_<?php echo $form_id; ?>').submit();
                        });
                    }
                }

                if( gfbObj.is_fees_enable ) {

                    let gform_fields_id = 'gform_fields_<?php echo $form_id; ?>'
                    if(gfbObj.is_admin) {
                        gform_fields_id = 'gform_fields';
                    }
                    let gFormFields = document.getElementById(gform_fields_id);
                    let previewHtmlField = document.createElement("div");
                    previewHtmlField.id = 'gform_preview_<?php echo $form_id; ?>';
                    previewHtmlField.classList.add('gform-preview');
                    gFormFields.appendChild(previewHtmlField);
                }

                if(typeof braintree === 'undefined') {
			        // console.log("Braintree is not loaded yet. Loading...");
			        var script = document.createElement('script');
			        script.onload = function () {
			            // console.log("Braintree is now loaded.");
			            braintree.dropin.create({
			                authorization: '<?php echo $clientToken;?>',
			                container: '#dropin-container_<?php echo $dropin_container_id; ?>'
			            }, (error, dropinInstance) => {
			                if (error) console.error(error);

                            document.getElementById('gform_<?php echo $form_id; ?>').addEventListener('submit', event => {
                                event.preventDefault();

                                dropinInstance.requestPaymentMethod((error, payload) => {
                                    if (error) console.error(error);
                                    document.getElementById('nonce').value = payload.nonce;
                                    document.getElementById('payment_card_type').value = payload.type;

                                    let cardType = payload.details.cardType;
                                    let cardLastFour = payload.details.lastFour;
                                    document.getElementById('payment_card_details').value = cardLastFour+" ("+cardType+")";
                                    if( gfbObj.is_fees_enable ) {
                                        managePreviewBeforePayment(payload);
                                    } else {
                                        document.getElementById('gform_<?php echo $form_id; ?>').submit();
                                    }
			                    });
			                });
			            });
			        };
			        script.src = 'https://js.braintreegateway.com/web/dropin/1.26.0/js/dropin.min.js';
			        document.head.appendChild(script);
			    } else {
			    	braintree.dropin.create({
	                    authorization: '<?php echo $clientToken;?>',
	                    container: '#dropin-container_<?php echo $dropin_container_id; ?>'
	                }, (error, dropinInstance) => {
	                    if (error) console.error(error);

                        document.getElementById('gform_<?php echo $form_id; ?>').addEventListener('submit', event => {
                            event.preventDefault();

                            dropinInstance.requestPaymentMethod((error, payload) => {
                                if (error) console.error(error);
                                document.getElementById('nonce').value = payload.nonce;
                                document.getElementById('payment_card_type').value = payload.type;

                                console.log(payload);
                                let cardType = payload.details.cardType;
                                let cardLastFour = payload.details.lastFour;
                                document.getElementById('payment_card_details').value = cardLastFour+" ("+cardType+")";
                                if( gfbObj.is_fees_enable ) {
                                    managePreviewBeforePayment(payload);
                                } else {
                                    document.getElementById('gform_<?php echo $form_id; ?>').submit();
                                }
                            });
                        });
                    });
                }
            </script>
            <?php
            $html = ob_get_contents();
            ob_get_clean();

            return $html;
        }
    }
}