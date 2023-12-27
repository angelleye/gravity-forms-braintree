jQuery(document).ready(function ($) {
    if ($('.gform_payment_method_options').length) {
        var payment_methods = {};
        $('.gform_payment_method_options input[type=radio]').each(function () {
            var targetdiv = $(this).attr('targetdiv');
            var value = $(this).val();
            payment_methods[value] = targetdiv;
        });

        $('.gform_payment_method_options').on('click', 'input[type=radio]', function () {
            var selectedradio = $(this).val();
            for (var i in payment_methods)
                if (i !== selectedradio)
                    $(this).closest('form').find('#' + payment_methods[i]).slideUp()

            var targetdiv = $(this).attr('targetdiv');
            $(this).closest('form').find('#' + targetdiv).slideDown();
        });

        var selectedradio = $('.gform_payment_method_options input[type=radio]:checked').val();

        switch (selectedradio) {
            case 'braintree_ach':
                $('.gform_payment_method_options input[value=braintree_ach]').trigger('click');
                break;
            default:
            case 'creditcard':
                $('.gform_payment_method_options input[value=creditcard]').trigger('click');
                break;
        }
    }

    $('.custom_ach_form_submit_btn').click(function (e) {
        window[ 'gf_submitting_' + $("input[name='gform_submit']").val() ] = true;
        $('#gform_ajax_spinner_' + $("input[name='gform_submit']").val()).remove();
        e.preventDefault();

        var curlabel = $(this).html();
        var form = $(this).closest('form');

        let form_id = form.find('input[name="gform_submit"]').val();
        var selectedradio = form.find('.gform_payment_method_options input[type=radio]:checked').val();

        if( $('#gform_preview_'+form_id).length === 0 ) {

            let gFormFields = document.getElementById('gform_fields_'+form_id);
            let previewHtmlField = document.createElement("div");
            previewHtmlField.id = 'gform_preview_'+form_id;
            previewHtmlField.classList.add('gform-preview');
            gFormFields.appendChild(previewHtmlField);
        }

        var check_if_ach_form = form.find('.ginput_ach_form_container');
        if (check_if_ach_form.length && (selectedradio === 'braintree_ach' || check_if_ach_form.closest('.gfield').css('display') !== 'none')) {
            if (form.find('.ginput_container_address').length == 0) {
                alert('ACH payment requires billing address fields, so please include Billing Address field in your Gravity form.');
                return;
            }

            var account_number = form.find('.ginput_account_number').val();
            var account_number_verification = form.find('.ginput_account_number_verification').val();
            var account_type = form.find('.ginput_account_type').val();
            var routing_number = form.find('.ginput_routing_number').val();
            var account_holdername = form.find('.ginput_account_holdername').val();

            var streetAddress = form.find('.ginput_container_address .address_line_1 input[type=text]').val();
            var extendedAddress = form.find('.ginput_container_address .address_line_2 input[type=text]').val();
            var locality = form.find('.ginput_container_address .address_city input[type=text]').val();
            var region = form.find('.ginput_container_address .address_state input[type=text], .ginput_container_address .address_state select').val();
            var postalCode = form.find('.ginput_container_address .address_zip input[type=text]').val();

            if (region.length > 2) {
                region = stateNameToAbbreviation(region);
            }

            var address_validation_errors = [];
            if (streetAddress == '') {
                address_validation_errors.push('Please enter a street address.');
            }

            if (locality == '') {
                address_validation_errors.push('Please enter your city.');
            }

            if (region == '') {
                address_validation_errors.push('Please enter your state.');
            }

            if (postalCode == '') {
                address_validation_errors.push('Please enter your postal code.');
            }

            if (address_validation_errors.length) {
                alert(address_validation_errors.join('\n'));
                return;
            }

            var achform_validation_errors = [];
            if (routing_number == '' || isNaN(routing_number) || account_number == '' || isNaN(account_number)) {
                achform_validation_errors.push('Please enter a valid routing and account number.')
            }

            if (account_type == '') {
                achform_validation_errors.push('Please select your account type.')
            }

            if (account_holdername == '') {
                achform_validation_errors.push('Please enter the account holder name.');
            } else {
                var account_holder_namebreak = account_holdername.split(' ');
                if (account_type == 'S' && account_holder_namebreak.length < 2) {
                    achform_validation_errors.push('Please enter the account holder first and last name.');
                }
            }

            if (account_number !== account_number_verification) {
                achform_validation_errors.push('Account Number and Account Number Verification field should be same.');
            }

            if (achform_validation_errors.length) {
                alert(achform_validation_errors.join('\n'));
                return;
            }

            var submitbtn = $(this);
            //   submitbtn.attr('disabled', true).html('<span>Please wait...</span>').css('opacity', '0.4');

            braintree.client.create({
                authorization: angelleye_gravity_form_braintree_ach_handler_strings.ach_bt_token
            }, function (clientErr, clientInstance) {
                if (clientErr) {
                    alert('There was an error creating the Client, Please check your Braintree Settings.');
                    console.error('clientErr', clientErr);
                    return;
                }

                braintree.dataCollector.create({
                    client: clientInstance,
                    paypal: true
                }, function (err, dataCollectorInstance) {
                    if (err) {
                        alert('We are unable to validate your system, please try again.');
                        resetButtonLoading(submitbtn, curlabel);
                        console.error('dataCollectorError', err);
                        return;
                    }

                    var deviceData = dataCollectorInstance.deviceData;

                    braintree.usBankAccount.create({
                        client: clientInstance
                    }, function (usBankAccountErr, usBankAccountInstance) {
                        if (usBankAccountErr) {
                            alert('There was an error initiating the bank request. Please try again.');
                            resetButtonLoading(submitbtn, curlabel);
                            console.error('usBankAccountErr', usBankAccountErr);
                            return;
                        }

                        var bankDetails = {
                            accountNumber: account_number, //'1000000000',
                            routingNumber: routing_number, //'011000015',
                            accountType: account_type == 'S' ? 'savings' : 'checking',
                            ownershipType: account_type == 'S' ? 'personal' : 'business',
                            billingAddress: {
                                streetAddress: streetAddress, //'1111 Thistle Ave',
                                extendedAddress: extendedAddress,
                                locality: locality, //'Fountain Valley',
                                region: region, //'CA',
                                postalCode: postalCode //'92708'
                            }
                        };

                        if (bankDetails.ownershipType === 'personal') {
                            bankDetails.firstName = account_holder_namebreak[0];
                            bankDetails.lastName = account_holder_namebreak[1];
                        } else {
                            bankDetails.businessName = account_holdername;
                        }

                        usBankAccountInstance.tokenize({
                            bankDetails: bankDetails,
                            mandateText: 'By clicking ["Submit"], I authorize Braintree, a service of PayPal, on behalf of ' + angelleye_gravity_form_braintree_ach_handler_strings.ach_business_name + ' (i) to verify my bank account information using bank information and consumer reports and (ii) to debit my bank account.'
                        }, function (tokenizeErr, tokenizedPayload) {
                            if (tokenizeErr) {
                                var errormsg = tokenizeErr['details']['originalError']['details']['originalError'][0]['message'];
                                if (errormsg.indexOf("Variable 'zipCode' has an invalid value") != -1)
                                    alert('Please enter valid postal code.');
                                else if (errormsg.indexOf("Variable 'state' has an invalid value") != -1)
                                    alert('Please enter valid state code. (e.g.: CA)');
                                else
                                    alert(errormsg);

                                resetButtonLoading(submitbtn, curlabel);
                                console.error('tokenizeErr', tokenizeErr);
                                return;
                            }

                            let card_type = 'ACH';
                            form.append("<input type='hidden' name='ach_device_corelation' value='" + deviceData + "' />");
                            form.append('<input type="hidden" name="ach_token" value="' + tokenizedPayload.nonce + '" />');
                            form.append('<input type="hidden" name="ach_card_type" value="'+card_type+'" />');

                            jQuery.ajax({
                                type: 'POST',
                                dataType: 'json',
                                url: angelleye_gravity_form_braintree_ach_handler_strings.ajax_url,
                                data: {
                                    action: 'gform_payment_preview_html',
                                    nonce: angelleye_gravity_form_braintree_ach_handler_strings.ach_bt_nonce,
                                    card_type: card_type,
                                    form_id: form_id,
                                    form_data: jQuery('#gform_'+form_id).serializeArray()
                                },
                                success: function ( result ) {
                                    if(result.status) {
                                        if( result.extra_fees_enable ) {
                                            let gFormPreview = document.getElementById('gform_preview_'+form_id);
                                            gFormPreview.innerHTML = result.html;
                                            manageACHGfromFields(form_id, true);
                                            manageACHPaymentActions(form_id);
                                        } else {
                                            form.submit();
                                        }
                                    } else {
                                        location.reload();
                                    }
                                }
                            });
                        });
                    });
                });
            });

        } else {
            form.submit();
        }
    });
});

function manageACHPaymentActions(form_id) {
    let paymentCancel = document.getElementById('gform_payment_cancel_'+form_id);
    if( undefined !== paymentCancel && null !== paymentCancel ) {
        paymentCancel.addEventListener('click', function () {
            location.reload();
        });
    }

    let paymentProcess = document.getElementById('gform_payment_pay_'+form_id);
    if( undefined !== paymentProcess && null !== paymentProcess ) {
        paymentProcess.addEventListener('click', function () {
            paymentProcess.classList.add('loader');
            manageACHGfromFields(form_id);
            manageACHScrollIntoView('gform_preview_'+form_id);
            document.getElementById('gform_'+form_id).submit();
        });
    }
}

function manageACHScrollIntoView(sectionID) {
    var scrollSection = document.getElementById(sectionID);
    if (scrollSection) {
        scrollSection.scrollIntoView({ behavior: 'smooth' });
    }
}

function manageACHGfromFields( form_id, is_preview = false ) {

    const form = document.getElementById('gform_'+form_id);

    let gformFields = document.getElementById('gform_fields_'+form_id);
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

    let gfieldBraintreeCC = form.querySelectorAll('.gfield--type-braintree_credit_card');
    if( undefined !== gfieldBraintreeCC  &&  null !== gfieldBraintreeCC ) {
        gfieldBraintreeCC.forEach(function(BraintreeCC) {
            if( is_preview ) {
                BraintreeCC.style.display = 'none';
            } else {
                BraintreeCC.style.display = 'block';
            }
        });
    }

    let gformFooter = form.querySelectorAll('.gform_footer');
    if( undefined !== gformFooter  &&  null !== gformFooter ) {
        gformFooter.forEach(function(footer) {
            footer.style.display = 'none';
        });
    }

    let gformPreview = document.getElementById('gform_preview_'+form_id);
    if( undefined !== gformPreview && null !== gformPreview ) {
        gformPreview.style.display = 'block';
    }

    let gformSpinner = document.getElementById('gform_ajax_spinner_'+form_id);
    if (undefined !== gformSpinner && null !== gformSpinner) {
        gformSpinner.remove();
    }

    manageACHScrollIntoView('gform_preview_'+form_id);
}

function stateNameToAbbreviation(name) {
    let states = {
        "Alabama": "AL",
        "Alaska": "AK",
        "Arizona": "AZ",
        "Arkansas": "AR",
        "California": "CA",
        "Colorado": "CO",
        "Connecticut": "CT",
        "Delaware": "DE",
        "District of Columbia": "DC",
        "Florida": "FL",
        "Georgia": "GA",
        "Hawaii": "HI",
        "Idaho": "ID",
        "Illinois": "IL",
        "Indiana": "IN",
        "Iowa": "IA",
        "Kansas": "KS",
        "Kentucky": "KY",
        "Louisiana": "LA",
        "Maine": "ME",
        "Maryland": "MD",
        "Massachusetts": "MA",
        "Michigan": "MI",
        "Minnesota": "MN",
        "Mississippi": "MS",
        "Missouri": "MO",
        "Montana": "MT",
        "Nebraska": "NE",
        "Nevada": "NV",
        "New Hampshire": "NH",
        "New Jersey": "NJ",
        "New Mexico": "NM",
        "New York": "NY",
        "North Carolina": "NC",
        "North Dakota": "ND",
        "Ohio": "OH",
        "Oklahoma": "OK",
        "Oregon": "OR",
        "Pennsylvania": "PA",
        "Rhode Island": "RI",
        "South Carolina": "SC",
        "South Dakota": "SD",
        "Tennessee": "TN",
        "Texas": "TX",
        "Utah": "UT",
        "Vermont": "VT",
        "Virginia": "VA",
        "Washington": "WA",
        "West Virginia": "WV",
        "Wisconsin": "WI",
        "Wyoming": "WY",
        "Armed Forces Americas": "AA",
        "Armed Forces Europe": "AE",
        "Armed Forces Pacific": "AP"
    }
    if (states[name] !== null) {
        return states[name];
    }
    return name;
}

function resetButtonLoading(submitbtn, curlabel) {
    //  submitbtn.attr('disabled', false).html(curlabel).css('opacity', '1');
}
