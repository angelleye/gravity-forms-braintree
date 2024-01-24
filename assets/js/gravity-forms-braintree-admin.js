jQuery(function () {
    jQuery('[id^=angelleye_notification]').each(function (i) {
        jQuery('[id="' + this.id + '"]').slice(1).remove();
    });
    var el_notice = jQuery(".angelleye-notice");
    el_notice.fadeIn(750);
    jQuery(".angelleye-notice-dismiss").click(function(e){
        e.preventDefault();
        jQuery( this ).parent().parent(".angelleye-notice").fadeOut(600, function () {
            jQuery( this ).parent().parent(".angelleye-notice").remove();
        });
        notify_wordpress(jQuery( this ).data("msg"));
    });
    function notify_wordpress(message) {
        var param = {
            action: 'angelleye_gform_braintree_adismiss_notice',
            data: message
        };
        jQuery.post(ajaxurl, param);
    }
    jQuery(document).off('click', '#angelleye-updater-notice .notice-dismiss').on('click', '#angelleye-updater-notice .notice-dismiss',function(event) {
        var r = confirm("If you do not install the Updater plugin you will not receive automated updates for Angell EYE products going forward!");
        if (r == true) {
            var data = {
                action : 'angelleye_updater_dismissible_admin_notice'
            };
            jQuery.post(ajaxurl, data, function (response) {
                var $el = jQuery( '#angelleye-updater-notice' );
                event.preventDefault();
                $el.fadeTo( 100, 0, function() {
                        $el.slideUp( 100, function() {
                                $el.remove();
                        });
                });
            });
        } 
    });
});

jQuery(document).ready(function ($) {
    $('.addmorecustomfield').click(function () {
        $('.custom_field_row:last').after('<tr class="custom_field_row"><td valign="top"><input type="text" name="gravity_form_custom_field_name[]" value="" placeholder="Please enter your field name from BrainTree" class="form-control" ></td><td>'+$('.custom_fields_template').html()+' <a class="remove_custom_field">Remove</a> </td></tr>');
        if($('.custom_field_row').length>1){
            $('.alert-notification-custom-fields').removeClass('hide');
        }
    });

    $('body').on('click','.remove_custom_field', function () {
        $(this).closest('tr.custom_field_row') .remove();
    });

    $('#gform_braintree_mapping').submit(function (e) {
        e.preventDefault();

        var data = $(this).serialize();
        var url = $(this).attr('action');
        $('.successful_message').html('');
        $('.updatemappingbtn').html('Saving...').attr('disabled','disabled');
        $.ajax({
            url:url,
            method: 'post',
            'data': data,
            'dataType': 'json'
        }).done(function (response) {
            if(response.status){
                $('.successful_message').html('<div class="updated fade"><p>'+response.message+'</p></div>')
            }else {
                console.log('error', response);
            }

        }).complete(function () {
            $('.updatemappingbtn').html('Update Mapping').removeAttr('disabled');
        });
    });

    $('.datepicker').datetimepicker({
        format: 'm/d/Y H:i',
    });

    $( document ).on( "click",'.delete-report', function() {
        let fileName = $(this).attr('data-file_name');
        let transactionDir = $(this).attr('data-transaction_dir');
        let ref_id = $(this).attr('data-ref_id');
        if ( confirm(GFBraintreeObj.report_confirm) === true) {

            remove_gfb_notification();

            jQuery.post(ajaxurl, {
                action: 'angelleye_gform_braintree_report_delete',
                file_name: fileName,
                transaction_dir: transactionDir,
            }, function ( response ) {

                if( response.status ) {
                    $('.transactions-report-lists  .'+ref_id).remove();
                } else {
                    alert(response.message);
                }
            }).fail(function(error){
                let status = error.status;
                let message =  ( status ===  504 ) ? status+' Gateway Time-out '+error.statusText : status+' '+error.statusText;
                add_gfb_notification( message, 'error' );
            });
        }
    });

    $( document ).on( "click",'#search_transactions', function( event ) {
        event.preventDefault();
        window.onbeforeunload = null;
        let currentObj = $(this);
        currentObj.addClass('loader');

        remove_gfb_notification();

        let action = $(this).attr('data-action');
        let nonce = $(this).attr('data-nonce');
        let start_date = $("#start_date").val();
        let end_date = $("#end_date").val();
        let merchant_account_id = $("#merchant_account_id").val();

        if( start_date !== '' &&  end_date !== '' ) {

            jQuery.post(ajaxurl, {
                action: 'angelleye_gform_braintree_generate_report',
                data_action: action,
                data_nonce: nonce,
                start_date: start_date,
                end_date: end_date,
                merchant_account_id: merchant_account_id,
            }, function (response) {
                let type = (response.status) ? 'success': 'error';
                add_gfb_notification(response.message, type);
                if( response.status ) {
                    setTimeout(function (){
                        window.location.href = response.redirect_url;
                    }, 1000);
                }

                currentObj.removeClass('loader');
            }).fail(function(error){
                let status = error.status;
                let message =  ( status ===  504 ) ? status+' Gateway Time-out '+error.statusText : status+' '+error.statusText;
                add_gfb_notification( message, 'error' );
                currentObj.removeClass('loader');
            });
        } else {

            let errMsg ='';
            if( start_date === '' )  {
                errMsg  += '<span>'+GFBraintreeObj.start_date_required+'</span><br/>';
            }

            if( end_date === '' )  {
                errMsg  += '<span>'+GFBraintreeObj.end_date_required+'</span><br/>';
            }

            add_gfb_notification(errMsg, 'error');
            currentObj.removeClass('loader');
        }
    });
});

function remove_gfb_notification() {
    jQuery('.notifications .notification').remove();
}

function add_gfb_notification( message, type= 'success' ) {
    remove_gfb_notification();
    jQuery('.notifications').append('<p class="notification '+type+'">'+message+'</p>');
}

setTimeout(function() {
    function manageExtraFeesFields( is_enable = false ) {
        let extraFeesFields = document.querySelectorAll('input.extra-fees-input');
        if( undefined !== extraFeesFields && extraFeesFields.length > 0 ) {
            extraFeesFields.forEach(function ( field ){
                if(is_enable) {
                    field.setAttribute('readonly', 'readonly');
                } else {
                    field.removeAttribute('readonly');
                }
            });
        }
    }
    let disableExtraFee = document.getElementById('disable_extra_fees');
    if( undefined !== disableExtraFee && null !== disableExtraFee ) {
        manageExtraFeesFields(disableExtraFee.checked);
        disableExtraFee.addEventListener('click', function (e) {
            manageExtraFeesFields(disableExtraFee.checked);
        });
    }
}, 500);
