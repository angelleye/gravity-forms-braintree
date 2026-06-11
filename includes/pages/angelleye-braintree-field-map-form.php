<?php
$braintree_fields = [
    'billing' => [
        'firstName'             => 'First Name',
        'lastName'              => 'Last Name',
        'company'               => 'Company Name',
        /*'countryCodeAlpha2'     => 'Country Code (e.g US)',
        'countryCodeAlpha3'     => 'Country Code (e.g. USA)',
        'countryCodeNumeric'    => 'Country Code (e.g. +1)',*/
        'streetAddress'         => 'Street Address',
        'extendedAddress'       => 'Street Address 2 (Apartment or Suite Number)',
        'locality'              => 'Locality/City',
        'region'                => 'State/Province',
        'postalCode'            => 'Postal Code',
        'countryName'           => 'Country Name',
    ],
    'shipping' => [
        'firstName'             => 'First Name',
        'lastName'              => 'Last Name',
        'company'               => 'Company Name',
        /*'countryCodeAlpha2'     => 'Country Code (e.g US)',
        'countryCodeAlpha3'     => 'Country Code (e.g. USA)',
        'countryCodeNumeric'    => 'Country Code (e.g. +1)',*/
        'streetAddress'         => 'Street Address',
        'extendedAddress'       => 'Street Address 2 (Apartment or Suite Number)',
        'locality'              => 'Locality/City',
        'region'                => 'State/Province',
        'postalCode'            => 'Postal Code',
        'countryName'           => 'Country Name',
    ],
    //'customFields'              => 'Custom Fields (Defined in your account)',
    'customer' => [
        'firstName'             => 'First Name',
        'lastName'              => 'Last Name',
        'company'               => 'Company Name',
        'email'                 => 'Email',
        'phone'                 => 'Phone',
        'website'               => 'Website URL'
    ]
];

$form_id = $_GET['id'];
$get_form = GFAPI::get_form($form_id);
//echo '<pre>';print_r($get_form); die;

$braintree_mapping = isset($get_form['braintree_fields_mapping'])?$get_form['braintree_fields_mapping']:[];

function agreegateAllFields($main_arr, $parent_key = ''){
    $final_fields = [];
    foreach ($main_arr as $main_key => $single_field){
        if(is_array($single_field))
            $final_fields[$main_key] = agreegateAllFields($single_field, $main_key);
        else {
            $final_fields[($parent_key != '' ? $parent_key . '.' : '') . $main_key] = $single_field;
        }
    }

    return $final_fields;
}

$total_braintree_fields = agreegateAllFields($braintree_fields);
//print_r($total_braintree_fields);

$gravity_fields = $get_form['fields'];
$ignore_type_fields = ['creditcard'];

$final_gravity_fields = [];
foreach ($gravity_fields as $gravity_field) {
    if(in_array($gravity_field->type, $ignore_type_fields))
        continue;
    if(is_array($gravity_field['inputs']) && count($gravity_field['inputs'])){
        foreach ($gravity_field['inputs'] as $single_input)
            $final_gravity_fields[$single_input['id']] = $single_input['label'].' ('.$gravity_field['label'].')';
    }else
        $final_gravity_fields[$gravity_field['id']] = $gravity_field['label'];
}
//print_r($gravity_fields);
?>
<h3><span><i class="fa fa-cogs"></i> Braintree Field Mapping</span></h3>

<?php
if(!AngelleyeGravityFormsBraintree::isBraintreeFeedActive()) {
    $feed_page_link = add_query_arg([
        'page' => 'gf_edit_forms',
        'view' => 'settings',
        'subview' => 'gravity-forms-braintree',
        'id' => $form_id
    ], admin_url('admin.php'));

    echo "<div style='background-color: #f5e5cd;padding: 10px;color: #000;opacity: 0.83;transition: opacity 0.6s;margin:10px 0;'><p style='margin: 0'> Please make sure to configure the <a href='$feed_page_link'>Braintree feed</a> to process the payments.</p></div>";
}?>

<p>
    Here you can map individual Gravity form fields to Braintree fields so that they will show up in the Braintree transaction details.
</p>
<p>The field names on the left are currently available in Braintree.  Simply select the Gravity form field from the drop-down that you would like to pass to the matching field in Braintree transaction details.</p>
<p>If you do not see a Braintree field available for your Gravity form field, you may create custom fields within your Braintree account, and then add these custom fields at the bottom of this field mapping section.</p>
<p>For more information, <a target="_blank" href="https://www.angelleye.com/gravity-forms-braintree-payments-field-mapping?utm_source=gravity-forms-braintree&utm_medium=plugin&utm_campaign=gravity-forms-plugin">see our documentation</a>.</p>
<form action="<?php echo add_query_arg(['action'=>'save_gravity_form_mapping'], admin_url('admin-ajax.php')) ?>" method="post" id="gform_braintree_mapping">
    <input type="hidden" name="gform_id" value="<?php echo $form_id ?>">
    <table class="gforms_form_settings" cellspacing="0" cellpadding="0">
        <tbody>
        <?php  foreach ($total_braintree_fields as $keyname => $single_field_set){
                if(is_array($single_field_set)){
                    ?>
                    <tr>
                        <td colspan="2"><h4 class="gf_settings_subgroup_title"><?php echo ucfirst($keyname) ?></h4></td>
                    </tr>
                    <?php
                    foreach ($single_field_set as $key => $label){
                        $selected_option = isset($braintree_mapping[$key])?$braintree_mapping[$key]:'';
                        ?>
                        <tr>
                            <td width="300"><?php echo $label ?></td>
                            <td width="300">
                                <select name="gravity_form_field[<?php echo $key ?>]">
                                    <option value="">-- Map Form Field --</option>
                                    <?php foreach ($final_gravity_fields as $gid=>$single_field_label)
                                        echo '<option value="'.$gid.'" '.($selected_option==$gid?'selected':'').'>'.$single_field_label.'</option>'?>
                                </select>
                            </td>
                        </tr>

                        <?php
                    }
                }else{
                    $selected_option = isset($braintree_mapping[$keyname])?$braintree_mapping[$keyname]:'';
            ?>
                    <tr>
                        <td><?php echo $label ?></td>
                        <td>
                            <select name="gravity_form_field[<?php echo $keyname ?>]">
                                <option value="">-- Map Form Field --</option>
                                <?php foreach ($final_gravity_fields as $gid=>$single_field_label)
                                    echo '<option value="'.$gid.'" '.($selected_option==$gid?'selected':'').'>'.$single_field_label.'</option>'?>
                            </select>
                        </td>
                    </tr>
        <?php }
        } ?>
        <tr class="custom_field_row">
            <td colspan="2">
                <h4 class="gf_settings_subgroup_title">Custom Fields
                    <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_notification_send_to_email" title="<h6>Setup custom fields</h6>You can map your braintree custom fields with gravity form fields. For more information, <a target='_blank' href='https://www.angelleye.com/gravity-forms-braintree-payments-field-mapping?utm_source=gravity-forms-braintree&utm_medium=plugin&utm_campaign=gravity-forms-plugin'>see our documentation</a>.">
                        <i class="fa fa-question-circle"></i>
                    </a>
                    <span class="pull-right">
                        <a class=" addmorecustomfield">Add Custom Field</a>
                        <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_notification_send_to_email" title="Please make sure all of the defined custom field names matches with Braintree Custom Field API Name. Otherwise, the payment processor will throw an error.">
                            <i class="fa fa-question-circle"></i>
                        </a>
                    </span>
                </h4>
                <div class="alert-notification-custom-fields <?php if(!isset($braintree_mapping['custom_fields']) || count($braintree_mapping['custom_fields'])==0) echo 'hide'; ?>">
                    <p>Please make sure all of the defined custom field names match the Braintree Custom Field API names.  Otherwise, the payment processor will return an error.</p>
                </div>
            </td>
        </tr>

        <?php if(isset($braintree_mapping['custom_fields']))
            foreach($braintree_mapping['custom_fields'] as $key => $mapped_id){
            ?><tr class="custom_field_row">
                <td valign="top"><input type="text" name="gravity_form_custom_field_name[]" value="<?php echo $key ?>" placeholder="Please enter your field name from BrainTree" class="form-control" ></td>
                <td valign="top">
                    <select name="gravity_form_custom_field[]">
                        <option value="">-- Map Form Field --</option>
                        <?php foreach ($final_gravity_fields as $gid=>$single_field_label)
                            echo '<option value="'.$gid.'" '.($mapped_id==$gid?'selected':'').'>'.$single_field_label.'</option>'?>
                    </select>
                    <a class="remove_custom_field">Remove</a> </td>
              </tr>
            <?php
        } ?>
        <tr><td colspan="2">
                <button class="button button-primary updatemappingbtn">Update Mapping</button>
                <div class="successful_message"></div>
            </td></tr>
        </tbody>
    </table>
</form>
<div class="hide custom_fields_template">
    <select name="gravity_form_custom_field[]">
        <option value="">-- Map Form Field --</option>
        <?php foreach ($final_gravity_fields as $gid=>$single_field_label)
            echo '<option value="'.$gid.'">'.$single_field_label.'</option>'?>
    </select>
</div>
<style type="text/css">
    .remove_custom_field, .addmorecustomfield{cursor:pointer}
    .form-control{width:99% ;padding: 5px;font-size: 13px;line-height: 13px;}.pull-right{float: right;}.hide{display: none;}
</style>