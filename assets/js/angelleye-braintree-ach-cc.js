jQuery(document).ready(function ($) {

    const isAdmin = angelleye_gravity_form_braintree_ach_handler_strings.is_admin;

    var paymentMethodOptions = document.querySelectorAll('.gform_payment_method_toggle_options');
    if( typeof paymentMethodOptions !== 'undefined' && paymentMethodOptions.length > 0 && ! isAdmin ) {
        paymentMethodOptions.forEach(function(paymentMethod) {
            let form_id = paymentMethod?.getAttribute('data-form_id');
            let toggleOptions = paymentMethod.querySelectorAll('input[type=radio]');
            if( typeof toggleOptions !== 'undefined' && toggleOptions.length > 0 ) {
                toggleOptions.forEach(function(toggleEl) {
                    let currentForm = toggleEl?.form;
                    let targetEleID = toggleEl?.getAttribute('targetdiv');
                    let currentValue = toggleEl?.value;
                    let targetEle = currentForm.querySelector('#'+targetEleID);
                    let isChecked = toggleEl.checked;

                    cloneFormSubmitButton(toggleEl);

                    if( !isChecked ) {
                        if( currentValue === 'braintree_credit_card' ) {
                            toggleEl.checked = true;
                            if( undefined !== targetEle && null !== targetEle ) {
                                targetEle.style.display = 'block';
                            }
                        } else {
                            toggleEl.checked = false;
                            if( undefined !== targetEle && null !== targetEle ) {
                                targetEle.style.display = 'none';
                            }
                        }
                    }

                    manageBraintreeCCSubmitBtn(toggleEl);

                    toggleEl.addEventListener('click', function(event) {
                        let currentForm = this?.form;
                        let selectedValue = this?.value;
                        let paymentMethods = currentForm.querySelectorAll('.gform_payment_method_toggle_options input[type=radio]');

                        manageBraintreeCCSubmitBtn(toggleEl);

                        if( undefined !== paymentMethods && paymentMethods.length > 0 ) {
                            paymentMethods.forEach( function ( paymentMethod ) {
                                let currentEle = paymentMethod?.getAttribute('targetdiv');
                                let currentValue = paymentMethod?.value;
                                let targetEle = currentForm.querySelector('#'+currentEle);
                                if( selectedValue === currentValue ) {
                                    targetEle.style.display = 'block';
                                } else {
                                    targetEle.style.display = 'none';
                                }
                            });
                        }
                    });
                });
            }
        });
    }

    let achFormSubmit = document.querySelectorAll('.custom_ach_form_submit_btn');

    if( undefined !== achFormSubmit && achFormSubmit.length > 0 ) {
        achFormSubmit.forEach(function(submit) {
            submit.addEventListener('click', function (e) {

                var curlabel = $(this).html();
                var form = $(this).closest('form');

                window[ 'gf_submitting_' + $("input[name='gform_submit']").val() ] = true;
                $('#gform_ajax_spinner_' + $("input[name='gform_submit']").val()).remove();
                e.preventDefault();

                enableGformLoader(submit);

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
                    if (form.find('.ginput_container_address').length === 0) {
                        alert('ACH payment requires billing address fields, so please include Billing Address field in your Gravity form.');
                        removeGformLoader(form_id);
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
                    if (streetAddress === '') {
                        address_validation_errors.push('Please enter a street address.');
                    }

                    if (locality === '') {
                        address_validation_errors.push('Please enter your city.');
                    }

                    if (region === '') {
                        address_validation_errors.push('Please enter your state.');
                    }

                    if (postalCode === '') {
                        address_validation_errors.push('Please enter your postal code.');
                    }

                    if (address_validation_errors.length) {
                        alert(address_validation_errors.join('\n'));
                        removeGformLoader(form_id);
                        return;
                    }

                    var achform_validation_errors = [];
                    if (routing_number === '' || isNaN(routing_number) || account_number === '' || isNaN(account_number)) {
                        achform_validation_errors.push('Please enter a valid routing and account number.')
                    }

                    if (account_type === '') {
                        achform_validation_errors.push('Please select your account type.')
                    }

                    if (account_holdername === '') {
                        achform_validation_errors.push('Please enter the account holder name.');
                    } else {
                        var account_holder_namebreak = account_holdername.split(' ');
                        if (account_type === 'S' && account_holder_namebreak.length < 2) {
                            achform_validation_errors.push('Please enter the account holder first and last name.');
                        }
                    }

                    if (account_number !== account_number_verification) {
                        achform_validation_errors.push('Account Number and Account Number Verification field should be same.');
                    }

                    if (achform_validation_errors.length) {
                        alert(achform_validation_errors.join('\n'));
                        removeGformLoader(form_id);
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
                            removeGformLoader(form_id);
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
                                removeGformLoader(form_id);
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
                                    removeGformLoader(form_id);
                                    return;
                                }

                                var bankDetails = {
                                    accountNumber: account_number, //'1000000000',
                                    routingNumber: routing_number, //'011000015',
                                    accountType: account_type === 'S' ? 'savings' : 'checking',
                                    ownershipType: account_type === 'S' ? 'personal' : 'business',
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
                                        if (errormsg.indexOf("Variable 'zipCode' has an invalid value") !== -1)
                                            alert('Please enter valid postal code.');
                                        else if (errormsg.indexOf("Variable 'state' has an invalid value") !== -1)
                                            alert('Please enter valid state code. (e.g.: CA)');
                                        else
                                            alert(errormsg);

                                        resetButtonLoading(submitbtn, curlabel);
                                        console.error('tokenizeErr', tokenizeErr);
                                        removeGformLoader(form_id);
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

                                                    let fieldArgs = {
                                                        'form_id': form_id,
                                                        'is_fees_enable': result.extra_fees_enable,
                                                        'card_type': card_type
                                                    };

                                                    manageGfromFields(form_id, true, fieldArgs);
                                                    managePaymentActions(form_id, fieldArgs)
                                                    removeGformLoader(form_id);
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
    }
});

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

function cloneFormSubmitButton( toggleEl ) {
    let currentForm = toggleEl?.form;
    let currentValue = toggleEl?.value;

    if( currentValue === 'braintree_credit_card' ) {
        let formFooterEle = currentForm.querySelector('.gform_footer');
        let formFooterBtn = formFooterEle.querySelector('.gform_button');
        let formSubmitEle = formFooterEle.querySelector('input[name="gform_submit"]');
        let form_id = formSubmitEle?.value;
        var clonedButton = formFooterBtn.cloneNode(true);
        clonedButton.id = 'gform_submit_button_bcc_'+form_id;
        clonedButton.removeAttribute('onclick');
        clonedButton.removeAttribute('onkeypress');
        clonedButton.classList.remove('custom_ach_form_submit_btn');
        clonedButton.classList.add('braintree_cc_submit_btn');
        clonedButton.style.display = 'none';
        formFooterEle.appendChild(clonedButton);
    }
}

function manageBraintreeCCSubmitBtn( toggleEl ) {
    let currentForm = toggleEl?.form;
    let currentValue = toggleEl?.value;
    let isChecked = toggleEl?.checked;

    let formFooterEle = currentForm.querySelector('.gform_footer');
    let achButton = formFooterEle.querySelector('input.custom_ach_form_submit_btn');
    let braintreeCCButton = formFooterEle.querySelector('input.braintree_cc_submit_btn');
    if( isChecked && currentValue === 'braintree_credit_card') {
        braintreeCCButton.style.display = 'block';
        achButton.style.display = 'none';
    } else if( isChecked && currentValue === 'braintree_ach' ) {
        achButton.style.display = 'block';
        braintreeCCButton.style.display = 'none';
    }
}


function manageScrollIntoView( sectionID ) {
    var scrollSection = document.getElementById(sectionID);
    if (scrollSection) {
        //scrollSection.scrollIntoView({ behavior: 'smooth' });
    }
}

function manageGfromFields( form_id, is_preview = false, args = [] ) {

    const form = document.getElementById('gform_'+form_id);
    let cardTypeEl = form.querySelectorAll('.gfield--type-braintree_credit_card');
    if( undefined !== cardTypeEl  &&  null !== cardTypeEl ) {
        cardTypeEl.forEach(function(element) {
            element.style.display = 'none';
        });
    }

    let gformFields = document.getElementById('gform_fields_'+form_id);
    if( undefined !== gformFields && null !== gformFields ) {
        gformFields.classList.add('fields-preview');
        let inputElements = gformFields.querySelectorAll('input, input[type="text"], input[type="number"], input[type="radio"], input[type="checkbox"], select, textarea');
        if( undefined !== inputElements && null !== inputElements ) {
            inputElements.forEach(function (element) {
                if (is_preview) {
                    element.readOnly = true;
                    element.disabled = true;
                } else {
                    element.readOnly = false;
                    element.disabled = false;
                }
            });
        }

        let captchaEle = gformFields.querySelectorAll('.gfield.gfield--type-captcha');
        if( undefined !== captchaEle && null !== captchaEle ){
            captchaEle.forEach(function (element) {
                element.style.display = 'none';
            });
        }
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

    manageScrollIntoView('gform_preview_'+form_id);
}

function managePaymentActions(form_id, args = [] ) {

    manageScrollIntoView('gform_preview_'+form_id);

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
            manageGfromFields(form_id, false, args);
            setTimeout(function () {
                document.getElementById('gform_'+form_id).submit();
            }, 500);
        });
    }
}

function displayPaymentPreview( payload, form_id, args = [] ) {

    if( undefined !== payload.type && null !== payload.type  ) {

        let binDataDebit = payload?.binData?.debit;
        let paymentCardType = payload.type;
        if( binDataDebit.toLowerCase() === 'yes' || binDataDebit === true) {
            paymentCardType = 'DebitCard';
        }

        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: args.ajax_url,
            data: {
                action: 'gform_payment_preview_html',
                nonce: args.nonce,
                card_type: paymentCardType,
                form_id: form_id,
                form_data: jQuery('#gform_'+form_id).serializeArray()
            },
            success: function ( result ) {
                if(result.status) {
                    let gFormPreview = document.getElementById('gform_preview_'+form_id);
                    gFormPreview.innerHTML = result.html;
                    managePaymentActions(form_id, args);
                    removeGformLoader(form_id);
                } else {
                    location.reload();
                }
            }
        });
    }
}

function managePreviewBeforePayment( payload, form_id, args = [] ) {

    displayPaymentPreview(payload, form_id, args);
    manageGfromFields(form_id, true, args);
}

function loadBraintreeDropIn( form_id, args = [] ) {

    let dropInContainer = document.querySelectorAll('#dropin-container_'+args.container_id);
    let currentForm = '';
    if( undefined !== dropInContainer ) {
        currentForm = dropInContainer[0]?.form;
        if( typeof  currentForm == 'undefined' ) {
            currentForm = dropInContainer[0]?.offsetParent;
        }
    }

    var paymentMethodOptions = ( undefined !== currentForm && '' !== currentForm ) ?  currentForm.querySelectorAll('.gform_payment_method_toggle_options input[type=radio]') : [];

    braintree.dropin.create({
        authorization: args.client_token,
        container: '#dropin-container_'+args.container_id
    }, (error, dropinInstance) => {
        if (error) console.error(error);

        let gformBCCEle = 'gform_'+form_id;
        let gformBCCEvent = 'submit';
        if( undefined !== paymentMethodOptions && paymentMethodOptions.length > 0 ) {
            gformBCCEle = 'gform_submit_button_bcc_' + form_id;
            gformBCCEvent = 'click';
        }

        let bccFormSubmit = document.querySelectorAll('#'+gformBCCEle);

        if( undefined !== bccFormSubmit && bccFormSubmit.length > 0 ) {

            bccFormSubmit.forEach(function (element) {

                element.addEventListener(gformBCCEvent, event => {
                    event.preventDefault();

                    let gFormAjaxLoader = document.getElementById('gform_ajax_spinner_'+form_id);
                    if(undefined !== gFormAjaxLoader ) {
                        gFormAjaxLoader.remove();
                    }

                    let loaderEl = element;
                    if( gformBCCEvent === 'submit' ) {
                        let currentGform = document.getElementById(gformBCCEle);
                        loaderEl = currentGform?.querySelector('.gform_footer #gform_submit_button_'+form_id);
                    }
                    enableGformLoader(loaderEl);
                    dropinInstance.requestPaymentMethod((error, payload) => {
                        if (error) {
                            console.error(error);
                        } else {
                            document.getElementById('nonce_'+form_id).value = payload.nonce;
                            let binDataDebit = payload?.binData?.debit;
                            let paymentCardType = payload.type;
                            if( binDataDebit.toLowerCase() === 'yes' || binDataDebit === true) {
                                paymentCardType = 'DebitCard';
                            }
                            document.getElementById('payment_card_type_'+form_id).value = paymentCardType;
                            let cardType = payload.details.cardType;
                            let cardLastFour = payload.details.lastFour;
                            document.getElementById('payment_card_details_'+form_id).value = cardLastFour+" ("+cardType+")";
                            if( args.is_fees_enable ) {
                                managePreviewBeforePayment(payload, form_id, args);
                            } else {
                                document.getElementById('gform_'+form_id).submit();
                            }
                        }
                    });
                });
            });
        }
    });
}

function initBraintreeDropIn( form_id, args = [] ) {

    if( args.is_fees_enable ) {

        let gform_fields_id = 'gform_fields_'+form_id
        if(args.is_admin) {
            gform_fields_id = 'gform_fields';
        }
        let gFormFields = document.getElementById(gform_fields_id);
        let previewHtmlField = document.getElementById('gform_preview_'+form_id);
        if( undefined === previewHtmlField || null === previewHtmlField ) {
            gFormFields.insertAdjacentHTML('afterend', '<div id="gform_preview_'+form_id+'" class="gform-preview"></div>');
        }
    }

    if(typeof braintree === 'undefined' || typeof braintree.dropin === 'undefined' ) {

        var script = document.createElement('script');
        script.onload = function () {
            // console.log("Braintree is now loaded.");
            loadBraintreeDropIn( form_id, args );
        };
        script.src = 'https://js.braintreegateway.com/web/dropin/1.26.0/js/dropin.min.js';
        document.head.appendChild(script);
    } else {
        loadBraintreeDropIn( form_id, args );
    }
}

function enableGformLoader( element ) {
    element.insertAdjacentHTML('afterend', '<div class="loader"></div>');
}

function removeGformLoader(form_id) {

    let loaders = document.querySelectorAll("#gform_"+form_id+" .gform_footer .loader");
    if( undefined !== loaders && loaders.length > 0 ) {
        loaders.forEach( function (loader) {
            loader.remove();
        });
    }
}