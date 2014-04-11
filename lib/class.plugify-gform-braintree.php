<?php

// Plugify_GForm_Braintree class

final class Plugify_GForm_Braintree extends GFFeedAddOn {

	protected $_version = '0.1';
	protected $_min_gravityforms_version = '1.7.9999';
	protected $_slug = 'gravity-forms-braintree';
	protected $_path = 'gravity-forms-braintree/init.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://plugify.io/plugins/gravity-forms-braintree';
	protected $_title = 'Braintree Payments';
	protected $_short_title = 'Braintree';

	public function __construct () {

		parent::__construct();
		add_action( 'admin_init', array( &$this, 'get_form_id' ) );

	}

	public function plugin_page () {

		if( isset( $_GET['fid'] ) ) {

			$feed = $this->get_feed( $_GET['fid'] );
			$form = GFAPI::get_form( $feed['form_id'] );

			$this->feed_edit_page( $form, $feed['id'] );

		}
		else
			$this->feed_list_page();

	}

	public function insert_feed ( $form_id, $is_active, $meta ) {

		global $wpdb;

		if( $feed_id = parent::insert_feed( $form_id, $is_active, $meta ) ) {

			$wpdb->update( "{$wpdb->prefix}gf_addon_feed", array( 'form_id' => $_POST['form_id'] ), array( 'id' => $feed_id ) );
			return $feed_id;

		}

		return false;

	}

	public function save_feed_settings ( $feed_id, $form_id, $settings ) {

		global $wpdb;

		if( $result = parent::save_feed_settings( $feed_id, $form_id, $settings ) )
			return $wpdb->update( "{$wpdb->prefix}gf_addon_feed", array( 'form_id' => $settings['form_id'] ), array( 'id' => $feed_id ) );
		else
			return $result;

	}

	public function get_form_id () {

		if( isset( $_GET['fid'] ) && !isset( $_GET['id'] ) ) {

			$feed = $this->get_feed( $_GET['fid'] );
			wp_redirect( add_query_arg( array( 'id' => $feed['form_id'] ) ) );

		}

	}

	public function feed_settings_fields() {

		global $wpdb;

		if( $forms = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}rg_form WHERE `is_active` = 1", OBJECT ) ) {

			$form_choices = array();

			$form_choices[] = array(
				'label' => 'Select a form',
				'value' => ''
			);

			foreach( $forms as $form )
			$form_choices[] = array(
				'label' => $form->title,
				'value' => $form->id
			);

			$fields = array();

		}

    return array(

      array(
        'fields' => array(
          array(
            'label' => 'Gravity Form',
            'type' => 'select',
            'name' => 'form_id',
            'class' => 'small',
						'choices' => $form_choices
          ),
					array(
						'label' => '',
						'type' => 'hidden',
						'name' => 'transaction_type',
						'value' => 'Single Payment',
						'class' => 'small'
					),
          array(
            'name' => 'gf_braintree_mapped_fields',
            'label' => 'Map Fields',
            'type' => 'field_map',
            'field_map' => array(
							array(
								'name' => 'first_name',
								'label' => 'First Name',
								'required' => 1,
								'choices' => array( 'label' => 'OMG YAY', 'value' => 'derp' ),
								'values' => array( 'label' => 'OMG YAY', 'value' => 'derp' )
							),
							array(
								'name' => 'last_name',
								'label' => 'Last Name',
								'required' => 1
							),
							array(
								'name' => 'company',
								'label' => 'Company (optional)',
								'required' => 0
							),
							array(
								'name' => 'email',
								'label' => 'Email',
								'required' => 1
							),
							array(
								'name' => 'phone',
								'label' => 'Phone (optional)',
								'required' => 0
							),
							array(
								'name' => 'cc_number',
								'label' => 'Credit Card Number',
								'required' => 1
							),
							array(
								'name' => 'cc_expiry',
								'label' => 'Credit Card Expiry',
								'required' => 1
							),
							array(
								'name' => 'cc_security_code',
								'label' => 'Security Code (eg CVV)',
								'required' => 1
							),
							array(
								'name' => 'cc_cardholder',
								'label' => 'Cardholder Name',
								'required' => 1
							),
							array(
								'name' => 'amount',
								'label' => 'Payment Amount',
								'required' => 1
							)
          	)
          )
        )
      )

    );

  }

	public function get_column_value_form( $item ) {

		$form = GFAPI::get_form( $item['form_id'] );
		return __( $form['title'], 'gravity-forms-braintree' );

	}

	public function get_column_value_txntype( $item ) {
		return __( 'Single payment', 'gravity-forms-braintree' );
	}

	public function plugin_settings_fields () {

		return array(

      array(
        'title' => 'Account Settings',
        'fields' => array(
          array(
            'name' => 'merchant-id',
            'tooltip' => 'Your Braintree Merchant ID',
            'label' => 'Merchant ID',
            'type' => 'text',
            'class' => 'small'
          ),
					array(
						'name' => 'public-key',
						'tooltip' => 'Your Braintree Account Public Key',
						'label' => 'Public Key',
						'type' => 'text',
						'class' => 'small'
					),
					array(
						'name' => 'private-key',
						'tooltip' => 'Your Braintree Account Private Key',
						'label' => 'Private Key',
						'type' => 'text',
						'class' => 'small'
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
					),
					array(
						'name' => 'settlement',
						'tooltip' => 'Should authorized payments be automatically submitted for settlement?',
						'label' => 'Settlement',
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
			)

    );

	}

	protected function feed_list_columns () {

		return array(
			'id' => __( 'ID', 'gravity-forms-braintree' ),
			'form' => __( 'Form', 'gravity-forms-braintree' ),
			'txntype' => __( 'Transaction Type', 'gravity-forms-braintree' )
		);

	}

	public function feed_list_no_item_message () {
		return sprintf(__("<p style=\"padding: 10px 5px 5px;\">You don't have any Braintree feeds configured. Let's go %screate one%s!</p>", "gravityforms"), "<a href='" . add_query_arg( array( 'fid' => 0, 'id' => 0 ) ) . "'>", "</a>");
	}

	public function process_feed( $feed, $entry, $form ) {

		if( $settings = $this->get_plugin_settings() ) {

			Braintree_Configuration::environment( strtolower($settings['environment']) );
			Braintree_Configuration::merchantId( $settings['merchant-id']);
			Braintree_Configuration::publicKey( $settings['public-key'] );
			Braintree_Configuration::privateKey( $settings['private-key'] );

			$args = array(

				'amount' => trim( $entry[ $feed['meta']['gf_braintree_mapped_fields_amount'] ], "$ \t\n\r\0\x0B" ),
				'orderId' => $entry['id'],
				'creditCard' => array(
					'number' => $_POST[ 'input_' . str_replace( '.', '_', $feed['meta']['gf_braintree_mapped_fields_cc_number'] ) ],
					'expirationDate' => implode( '/', $_POST[ 'input_' . str_replace( '.', '_', $feed['meta']['gf_braintree_mapped_fields_cc_expiry'] ) ] ),
					'cardholderName' => $_POST[ 'input_' . str_replace( '.', '_', $feed['meta']['gf_braintree_mapped_fields_cc_cardholder'] ) ],
					'cvv' => $_POST[ 'input_' . str_replace( '.', '_', $feed['meta']['gf_braintree_mapped_fields_cc_security_code'] ) ]
				),
				'customer' => array(
					'firstName' => $entry[ $feed['meta']['gf_braintree_mapped_fields_first_name'] ],
					'lastName' => $entry[ $feed['meta']['gf_braintree_mapped_fields_last_name'] ],
					'email' => $entry[ $feed['meta']['gf_braintree_mapped_fields_email'] ]
				)

			);

			if( !empty( $feed['meta']['gf_braintree_mapped_fields_phone'] ) )
			$args['customer']['phone'] = $entry[ $feed['meta']['gf_braintree_mapped_fields_phone'] ];

			if( !empty( $feed['meta']['gf_braintree_mapped_fields_company'] ) )
			$args['customer']['company'] = $entry[ $feed['meta']['gf_braintree_mapped_fields_company'] ];

			$result = Braintree_Transaction::sale( $args );

		}

	}

}

?>