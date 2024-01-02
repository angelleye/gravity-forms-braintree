<?php

class Angelleye_Gravity_Braintree_ACH_Toggle_Field extends GF_Field {
	/**
	 * @var string $type The field type.
	 */
	public $type = 'braintree_ach_cc_toggle';

	/**
	 * Return the field title, for use in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return __( 'Payment Method Toggle', 'gravity-forms-braintree' );
	}

	/**
	 * Assign the field button to the Pricing Fields group.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return [ 'group' => 'pricing_fields', 'text'  => 'Payment Types' ];
	}

	/**
	 * The settings which should be available on the field in the form editor.
	 *
	 * @return array
	 */
	function get_form_editor_field_settings() {
		return ['label_setting', 'admin_label_setting', 'description_setting',  'error_message_setting', 'css_class_setting', 'conditional_logic_field_setting',
			'rules_setting', 'input_placeholders_setting',
		];
	}

	/**
	 * Return the Payment method toggle radio buttons
	 * @param array $form
	 * @param string $value
	 * @param null $entry
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id  = ( $is_entry_detail || $is_form_editor ) && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		$cc_field_id = '';
		$ach_field_id = '';
		if(!$is_form_editor && !$is_entry_detail){
			foreach ($form['fields'] as $single_field) {

				if ($single_field->type == 'braintree_credit_card')
					$cc_field_id = $single_field->id;
				else if($single_field->type=='braintree_ach') {
					$ach_field_id = $single_field->id;
				}
			}
		}

		$payment_options = '';
		$payment_methods = [['key' => 'braintree_credit_card', 'label'=>'Credit Card', 'field_id' => $cc_field_id],
			['key' => 'braintree_ach', 'label' => 'ACH', 'field_id' => $ach_field_id]];
		foreach ( $payment_methods as $payment_method ) {
			$checked = rgpost( "input_{$id}_1" ) == $payment_method['key'] ? "checked='checked'" : '';
            if(empty( $checked ) && $payment_method['key'] == 'braintree_credit_card' ) {
                $checked = "checked='checked'";
            }
			$payment_options .= "<div class='gform_payment_method_option gform_payment_{$payment_method['key']}'><input type='radio' name='input_{$id}.1' value='{$payment_method['key']}' id='gform_payment_method_{$payment_method['key']}' targetdiv='field_{$form_id}_{$payment_method['field_id']}' {$checked}/> {$payment_method['label']}</div>";
		}

		return "<div class='ginput_container gform_payment_method_options gform_payment_method_toggle_options ginput_container_{$this->type}' id='{$field_id}' data-form_id='{$form_id}'>" .$payment_options
		       . ' </div>';
	}

	public function is_value_submission_empty( $form_id ) {
		return false;
	}

	public function get_entry_inputs() {
		$inputs = array();
		if(is_array($this->inputs)) {
			foreach ( $this->inputs as $input ) {
				if ( in_array( $input['id'], array( $this->id . '.1' ) ) ) {
					$inputs[] = $input;
				}
			}
		}

		return $inputs;
	}

	/**
	 * Validate the user input
	 * @param array|string $value
	 * @param array $form
	 */
	public function validate( $value, $form ) {
		$toggle_selected 	= rgpost( 'input_' . $this->id.'_1' );

		if (empty( $toggle_selected ) || !in_array($toggle_selected, ['creditcard','braintree_ach','braintree_credit_card']) ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage ) ? __( 'Please select a payment method.', 'gravity-forms-braintree' ) : $this->errorMessage;
		}
	}

	/**
	 * Shows the filled information by the users
	 * @param array $field_values
	 * @param bool $get_from_post_global_var
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		if ( $get_from_post_global_var ) {
			$value[ $this->id . '.1' ] = $this->get_input_value_submission( 'input_' . $this->id . '_1', rgar( @$this->inputs[0], 'name' ), $field_values, true );
		} else {
			$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		}
		return $value;
	}

	/**
	 * Format the value on entry detail page
	 * @param array|string $value
	 * @param string $currency
	 * @param bool $use_text
	 * @param string $format
	 * @param string $media
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( is_array( $value ) ) {
			$selected_method = trim( rgget( $this->id . '.1', $value ) );
			return $selected_method=='braintree_ach' ? 'Braintree ACH Direct Debit' : 'Credit Card';
		} else {
			return '';
		}
	}

	/**
	 * Format the entry value for display on the entries list page.
	 *
	 * Return a value that's safe to display on the page.
	 *
	 * @param string|array $value    The field value.
	 * @param array        $entry    The Entry Object currently being processed.
	 * @param string       $field_id The field or input ID currently being processed.
	 * @param array        $columns  The properties for the columns being displayed on the entry list page.
	 * @param array        $form     The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {

		$allowable_tags = $this->get_allowable_tags( $form['id'] );

		list( $input_id, $field_id ) = rgexplode( '.', $field_id, 2 );
		switch($field_id){
			case 1:
				if($value=='braintree_ach')
					$return = 'Braintree ACH Direct Debit';
				else if($value=='creditcard' || $value == 'braintree_credit_card')
						$return = 'Credit Card';
				break;
		}

		return $return;
	}
}