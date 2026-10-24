jQuery( function( $ ) {
    
    function renderPlainMessage($container, message, success = true) {
        $container.empty().css('color', success ? 'green' : 'red');

        if (message && message.jquery) {
            $container.append(message);
            return;
        }

        $container.text(message || '');
    }

    function renderPlainMessageLines($container, messages, className) {
        $container.empty();

        (messages || []).forEach(function(message, index) {
            const $line = $('<span>').addClass(className || '').text(message);
            $container.append($line);
            if (index < messages.length - 1) {
                $container.append('<br>');
            }
        });
    }
    
    function showResult(btn, message, success = true) {
        const resultSpan = $(btn).closest('.ep-license-card-box').find('.ep-extension-action-result');
        renderPlainMessage(resultSpan, message, success);
    }
    
    function showLicenseStatus(btn, message, success = true) {
        const resultSpan = $(btn).closest('.ep-license-status').find('.ep-extension-action-result');
        renderPlainMessage(resultSpan, message, success);
    }
    
    function showbundleLicenseStatus(btn, message, success = true) {
        const resultSpan = $(btn).closest('.ep-ext-box-description').find('.ep-extension-action-result');
        renderPlainMessage(resultSpan, message, success);
    }

    // Message from a licence AJAX reply (wp_send_json_* puts it in data or data.message), else the generic one.
    function licenseResponseMessage(response) {
        const data = response && response.data;
        if (typeof data === 'string' && data) {
            return data;
        }
        if (data && data.message) {
            return data.message;
        }
        return ep_admin_license_settings.request_failed;
    }

    // Re-enable the clicked button so the request can be retried.
    function restoreLicenseButton($btn) {
        $btn.children('.spinner').remove();
        $btn.removeAttr('disabled').removeClass('ep-pe-none');
    }

    // message is escaped by PHP: the toast shows it as HTML, the inline result as text (decoded first).
    function showLicenseFailure($btn, message) {
        restoreLicenseButton($btn);
        show_toast('error', message);
        showResult($btn, $('<textarea>').html(message).text(), false);
    }
    
    $('#ep_setting_form').on('keydown', 'input', function(event) {
  if (event.key === 'Enter') {
    event.preventDefault();
  }
});
    
    $('#ep_license-manager').on('click',function(e)
    {
        $('#ep_license_key').val('');
        $('.ep-license-error-message').html('').hide();
        $("body").addClass("ep-license-manager-modal");
    });
    $('.ep-deactivate-license-bundle').on('click', function (e) {
        
        e.preventDefault();
        var $bundleBtn = $(this);
        var license_key = $(this).attr('data-license');
        var loader = $( '<span class="spinner is-active" style="float: none;"></span>' );
        $bundleBtn.append(loader).attr('disabled', 'disabled');

        let data = { 
            'action': 'ep_deactivate_bundle_license', 
            'nonce': ep_admin_license_settings.ep_license_nonce,
            'ep_license' : license_key,
        };
        //console.log(data);
        $.ajax({
            type: 'POST', 
            url :  get_ajax_url(),
            data: data,
            success: function( response ) {
                if ( response && response.success ) {
                    show_toast( 'success', response.data.html );
                    setTimeout(function () {
                        location.reload();
                    }, 3000);
                } else {
                    restoreLicenseButton( $bundleBtn );
                    show_toast( 'error', licenseResponseMessage( response ) );
                }
            },
            error: function() {
                restoreLicenseButton( $bundleBtn );
                show_toast( 'error', ep_admin_license_settings.request_failed );
            }
        });
        
        
    });
    
    $('.ep-deactivate-license').on('click', function (e) {
        
        e.preventDefault();
        var $epDeactivateBtn = $(this);
        var prefix = $(this).attr('data-key');
        let key = $(this).attr('data-itemid');
        var license_key = $(this).attr('data-license');
        var ep_license_deactivate = 'deactivate';
        
        var loader = $( '<span class="spinner is-active ep-ml-1" style="float: none;"></span>' );
        //showResult(this, loader);
        showResult(this, '');
        $epDeactivateBtn.append(loader);
          $(this).attr('disabled','disabled');
          $(this).addClass('ep-pe-none');
        var data = { 
            action: 'ep_eventprime_deactivate_license', 
            nonce: ep_admin_license_settings.ep_license_nonce,
            ep_license_deactivate : ep_license_deactivate,
            'ep_license' : license_key,
            'ep_item_id' : key, 
            'ep_item_key': prefix
        };
        $.ajax({
            type: 'POST', 
            url :  get_ajax_url(),
            data: data,
            success: function( response ) {
                if ( response && response.success ) {
                    // The licence server answered; local records were removed, so reload either way.
                    show_toast( response.data.status ? 'success' : 'warning', response.data.message );
                    setTimeout(function () {
                        location.reload();
                    }, 3000);
                } else {
                    showLicenseFailure( $epDeactivateBtn, licenseResponseMessage( response ) );
                }
            },
            error: function() {
                showLicenseFailure( $epDeactivateBtn, ep_admin_license_settings.request_failed );
            }
        });
        
    });
    
    $('.ep-activate-license').on('click', function (e) {
        e.preventDefault();
        var $epActivateBtn = $(this);
        var prefix = $(this).attr('data-key');
        let key = $(this).attr('data-itemid');
        var license_key = $(this).attr('data-license');
        var ep_license_activate = 'activate';
        var loader = $( '<span class="spinner is-active ep-ml-1" style="float: none;"></span>' );
        //showResult(this, loader);
        showResult(this, '');
        $epActivateBtn.append(loader);
        $(this).attr('disabled','disabled');
        $(this).addClass('ep-pe-none');
        
        let data = { 
            'action': 'ep_eventprime_activate_license', 
            'nonce': ep_admin_license_settings.ep_license_nonce,
            'ep_license_activate' : ep_license_activate,
            'ep_license' : license_key,
            'ep_item_id' : key, 
            'ep_item_key': prefix
        };
        //console.log(data);
        $.ajax({
            type: 'POST', 
            url :  get_ajax_url(),
            data: data,
            success: function( response ) {
                var licenseData = response && response.data ? response.data.license_data : null;
                if ( response && response.success && licenseData && licenseData.success === true ) {
                    show_toast( 'success', response.data.message );
                    setTimeout(function () {
                        location.reload();
                    }, 3000);
                } else {
                    // Keep the page (and the message) so the admin can read it and retry.
                    showLicenseFailure( $epActivateBtn, licenseResponseMessage( response ) );
                }
            },
            error: function() {
                showLicenseFailure( $epActivateBtn, ep_admin_license_settings.request_failed );
            }
        });
    });
    

    $('.ep-activate-plugin').on('click', function () {
        const plugin = $(this).data('plugin');
        const button = this;
        
        $.post(ajaxurl, {
            action: 'ep_activate_plugin',
            plugin: plugin,
            nonce: ep_admin_license_settings.ep_license_nonce
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                showResult(button, response.data,false);
            }
        });
    });

    $('.ep-deactivate-plugin').on('click', function () {
        const plugin = $(this).data('plugin');
        const button = this;

        $.post(ajaxurl, {
            action: 'ep_deactivate_plugin',
            plugin: plugin,
            nonce: ep_admin_license_settings.ep_license_nonce
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                showResult(button, response.data,false);
            }
        });
    });
    
   

$('.ep_check_license_update').on('click', function(e){
    e.preventDefault();
    // Clear previous messages
    $('.ep-license-error-message, .ep-license-success-message').hide().text('');
    $('.ep-license-upload-fallback').hide();

    var $checkBtn = $(this);
    let licenseKey = $(this).data('licensekey');
    if(!licenseKey) return;

    // Show loader
    var loader = $( '<span class="spinner is-active" style="float: left;"></span>' );
    //showLicenseStatus(this, loader);
    //$('.ep_check_license_update').before(loader);

      $(this).append(loader);
     $(this).attr('disabled','disabled');

    $.ajax({
        url: get_ajax_url(),
        method: 'POST',
        data: {
            action: 'ep_check_license_status',
            ep_license_key: licenseKey,
            nonce: ep_admin_license_settings.ep_license_nonce
        },
        success: function(response){
            // Remove loader and enable button
            

            if(response && response.success){
                if(response.data.html) {
                    
              
                    
                        $('.ep-license-success-message')
                        .text(response.data.html)
                        .fadeIn();
                        // The success box is inside the closed Add License modal; show the result where the admin is looking.
                        show_toast('success', response.data.html);
                        
                        setTimeout(function () {

                            location.reload();

                        }, 7000);
 
                } 
                else if(response.data.message) {
                    $('.ep-license-success-message')
                        .text(response.data.message)
                        .fadeIn();
                    $('.ep-license-btn-wrap .spinner').remove();
                    $('.ep_check_license_update').removeAttr('disabled');
                }
               

            } else {
                restoreLicenseButton($checkBtn);
                // The stored licence details are unchanged; say so where the admin is looking (the modal is closed).
                show_toast('error', licenseResponseMessage(response));
                if(response && response.data && response.data.error === 'connection') {
                    // Show fallback file upload field
                    $('.ep-license-upload-fallback').slideDown();
                    $('#ep-license-fields').slideUp();
                          
                    

                    $('.ep-license-error-message')
                        .text('Unable to connect to the license server. Please upload a .json license file.')
                        .fadeIn();
                         
                         
                         
                         
                } else {
                    $('.ep-license-error-message')
                        .text((response && response.data && response.data.message) || 'Verification failed. Please try again.')
                        .fadeIn();
                
                
                  $('.ep-license-upload-fallback').slideDown();
                    $('#ep-license-fields').slideUp();
                        
                        //$('.ep-license-upload-fallback').slideDown();
                }
            }
        },
        error: function() {
            restoreLicenseButton($checkBtn);
            show_toast('error', ep_admin_license_settings.request_failed);
        }
    });

});


// License tab hint "Retry check": re-run the status check for the stored key without touching the Add License modal.
$(document).on('click', '.ep-license-hint-retry', function(e) {
    e.preventDefault();
    const $btn = $(this);
    const licenseKey = $btn.data('licensekey');
    if (!licenseKey || $btn.prop('disabled')) {
        return;
    }
    $btn.prop('disabled', true).attr('aria-busy', 'true').append('<span class="spinner is-active"></span>');

    const fail = function(message) {
        restoreLicenseButton($btn);
        $btn.removeAttr('aria-busy');
        show_toast('error', message);
    };

    $.ajax({
        url: get_ajax_url(),
        method: 'POST',
        data: {
            action: 'ep_check_license_status',
            ep_license_key: licenseKey,
            nonce: ep_admin_license_settings.ep_license_nonce
        },
        success: function(response) {
            if (response && response.success) {
                show_toast('success', response.data.html);
                $btn.closest('.ep-license-inline-hint').slideUp();
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                fail(licenseResponseMessage(response));
            }
        },
        error: function() {
            fail(ep_admin_license_settings.request_failed);
        }
    });
});

$('#ep-license-verify-submit').on('click', function(e) {
    e.preventDefault();

    // Reset messages and state
    $('.ep-license-error-message, .ep-license-success-message').hide().text('');
    $('.ep-license-upload-fallback').hide();
    

    // Show loader
    const $btn = $('#ep-license-verify-submit');
    const loader = $('<span class="spinner is-active" style="float: none;"></span>');
    $btn.after(loader).attr('disabled', 'disabled').addClass("ep-btn-disabled");
    

    const licenseKey = $('#ep_license_key').val();
    const fileInput = document.getElementById('ep-license-json-file');
    const file = fileInput?.files?.[0];

    // Case 1: License Key provided
    if (licenseKey) {
        $.ajax({
            url: get_ajax_url(),
            method: 'POST',
            data: {
                action: 'ep_save_license_settings',
                ep_license_key: licenseKey,
                nonce: ep_admin_license_settings.ep_license_nonce
            },
            success: function(response) {
                handleLicenseResponse(response);
            },
            error: handleAjaxError
        });
        //return;
    }

    // Case 2: License file selected
    else if (file) {
        // Use File API + TextDecoder instead of FileReader
        file.arrayBuffer().then(buffer => {
            const text = new TextDecoder('utf-8').decode(buffer);

            try {
                // Do NOT use object destructuring, keep structure as-is
                const parsed = JSON.parse(text); // preserves order from file exactly

                $.ajax({
                    url: get_ajax_url(),
                    method: 'POST',
                    data: {
                        action: 'ep_upload_license_file',
                        license_data: text, // Send raw JSON string
                        nonce: ep_admin_license_settings.ep_license_nonce
                    },
                    success: handleLicenseResponse,
                    error: handleAjaxError
                });
            } catch (err) {
                resetVerifyButton();
                showError('Invalid file format. Please upload a valid license file.');
            }
        }).catch(() => {
            resetVerifyButton();
            showError('Failed to read file.');
        });
        //return;
    }
    else 
    {
            
         $('#ep-license-verify-submit .spinner').remove();
         $('#ep-license-verify-submit').removeAttr('disabled');
         let method = $('#ep-license-verify-submit').data('method');
         console.log(method);
         if(method=='key')
         {
             showError('Please enter a license key.');
                $("#ep-license-verify-submit + .spinner").hide();
         }
         else
         {
             showError('Please upload a valid license file.');
         }
        
    }

    // If nothing was provided
   
});

// ✅ Response handler
function handleLicenseResponse(response) {
    resetVerifyButton();

    if (response && response.success) {
        const plugins = response.data.plugins || {};
        const validPlugins = {};
        const errorMessages = [];

        Object.keys(plugins).forEach(function(key) {
            const plugin = plugins[key];
            if (plugin.can_activate) {
                validPlugins[key] = plugin;
            } else if (plugin.message) {
                errorMessages.push(plugin.name + ': ' + plugin.message);
            }
        });

        if (Object.keys(validPlugins).length > 0) {
            $('.ep-license-success-message')
                .text(response.data.html || 'License successfully installed.')
                .fadeIn();
            $('#ep-license-verify-submit').remove();
            $('#ep_license_key').attr('disabled','disabled');
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            renderPlainMessageLines($('.ep-license-error-message'), errorMessages, 'ep-text-danger');
            $('.ep-license-error-message').fadeIn();
        }

    } else {
        if (response?.data?.error === 'connection') {
            $('.ep-license-upload-fallback').slideDown();
            $('#ep-license-fields').slideUp();
            $('#ep-license-verify-submit').attr('data-method','file');
            showError('Unable to connect to license server. You can upload a .json license file instead.');
            $('#ep_license_key').val('');
            
            $("body").addClass("ep-license-manager-modal");
        } else {
            showError(response?.data?.message || 'License verification failed.');
        }
    }
}

// ✅ Error fallback
function handleAjaxError() {
    resetVerifyButton();
    showError(ep_admin_license_settings.request_failed);
}

// Spinner sits after the modal Verify button; clear it so the admin can retry.
function resetVerifyButton() {
    $('#ep-license-verify-submit + .spinner').remove();
    $('#ep-license-verify-submit').removeAttr('disabled').removeClass('ep-btn-disabled');
}

// ✅ Reusable showError function
function showError(message) {
    $('.ep-license-error-message')
        .text(message)
        .fadeIn();
}


    
    $( document ).on( 'click', '.mg-verify-license', function(e) {
        
        e.preventDefault();
        let license_key = $('#ep_license_key').val();
        let license_email = $('#ep_license_email').val();
        let data = { 
            'action': 'ep_save_license_settings', 
            'nonce': ep_admin_license_settings.ep_license_nonce,
            'ep_license_key' : license_key,
            'ep_license_email' : license_email
        };
        //console.log(data);
        $.ajax({
            type: 'POST', 
            url :  get_ajax_url(),
            data: data,
            success: function( response ) {
            
            }
        });
        
        
        
    });
    
    $(document).on('click', '.ep-install-extension', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        const license = $(this).data('license');
        const itemid = $(this).data('itemid');
        const key = $(this).data('key');
        const $button = $(this);
        const $resultSpan = $button.closest('.ep-card-body').find('.ep-extension-action-result');
        
//        var loader = $( '<span class="spinner ep-p-0 ep-m-0 is-active" style="float: none;"></span>' );
//        showResult(this, loader);
        var loader = $('<span class="spinner ep-p-0 ep-m-0 is-active ep-ml-1" style="float: none;"></span>');
    $button.append(loader);
    $button.attr('disabled','disabled');
        
        // First get the plugin download URL
        fetch(`${url}`)
            .then(response => response.json())
            .then(data => {
                if(data.url){
                    // Send to WP to install
                    $.post(get_ajax_url(), {
                        action: 'ep_install_remote_plugin',
                        plugin_url: data.url,
                        license_key:license,
                        itemid:itemid,
                        key:key,
                        nonce: ep_admin_license_settings.ep_license_nonce
                    }, function(response) {
                        //console.log(response);
                       
                        if (response.success) {
                            show_toast( 'success', response.data.message );
                            setTimeout(function () {
                                location.reload();
                            }, 3000);
                             
                            // Auto-download and install using response.url, or update status only
                            //$resultSpan.html('<span class="ep-text-success">Installed & Activated</span>');
                        } else {
                            // wp_send_json_error() puts the reason in response.data(.message); it arrives HTML-escaped.
                            renderPlainMessageLines($resultSpan, [$('<textarea>').html(licenseResponseMessage(response)).text()], 'ep-text-danger');
                        }
                        $button.children('.spinner').remove();
                             $button.removeAttr('disabled');
                    }).fail(function() {
                        restoreLicenseButton($button);
                        renderPlainMessageLines($resultSpan, [ep_admin_license_settings.request_failed], 'ep-text-danger');
                    });
                }
                else
                {
                    $button.children('.spinner').remove();
                    $button.removeAttr('disabled');
                    renderPlainMessageLines($resultSpan, [data.error || 'Download unavailable.'], 'ep-text-danger');
                    //console.log(data);
                }
            })
            .catch(() => {
                restoreLicenseButton($button);
                renderPlainMessageLines($resultSpan, [ep_admin_license_settings.request_failed], 'ep-text-danger');
            });
    });
    
   
    $( ".ep-license-block" ).on( 'keyup', function( e ) {
        var prefix = $(this).data('key');
        
        var license_key_length = $('#' + prefix + '_license_key' ).val();
        // let child_length = $('.'+ prefix +  ' .' + prefix + '-license-status-block').children.length;
        if( license_key_length.length === 32 && prefix != 'undefined' && prefix != '' ){
            $('#' + prefix + '_license_activate' ).show();
        }
    });

    $( ".ep-license-block" ).on( 'keydown', function( e ) {
        var prefix = $(this).data('key');    
       
        if( prefix != 'undefined' && prefix != '' ){
            $('#' + prefix + '_license_activate' ).hide();
        }
    });

    $( document ).on( 'click', '.ep_license_activate', function(e) {
        e.preventDefault();
        var prefix = $(this).attr('data-key');
        let key = $(this).attr('data-prefix');
        
        var license_key = $('#'+prefix + '_license_key').val();
        var ep_license_activate = $('#' + prefix + '_license_activate').val();
        
        $( '.'+ prefix +  ' .license-expire-date' ).html( '' );
            $( '.'+ prefix +  ' .' + prefix + '-license-status-block .ep_license_activate' ).addClass( 'disabled' );
    
        let data = { 
            'action': 'ep_eventprime_activate_license', 
            'nonce': ep_admin_license_settings.ep_license_nonce,
            'ep_license_activate' : ep_license_activate,
            'ep_license' : license_key,
            'ep_item_id' : key, 
            'ep_item_key': prefix
        };
        //console.log(data);
        $.ajax({
            type: 'POST', 
            url :  get_ajax_url(),
            data: data,
            success: function( response ) {
                $( '.'+ prefix +  ' .' + prefix + '-license-status-block .ep_license_activate' ).removeClass( 'disabled' );
                if ( ! response || ! response.success || ! response.data ) {
                    show_toast( 'error', licenseResponseMessage( response ) );
                    return;
                }
                if( response.data.license_data && response.data.license_data.success === true )
                {
                    show_toast( 'success', response.data.message );
                    // update license activate/deactivate button
                    if( response.data.license_status_block != '' && response.data.license_status_block != 'undefined' ){
                        if( prefix == 'ep_free' || prefix == 'ep_professional' || prefix == 'ep_essential' || prefix == 'ep_metabundle' || prefix == 'ep_metabundle_plus' || prefix == 'ep_premium_plus' ){
                            $('.ep_premium .ep_premium-license-status-block').html(response.data.license_status_block);
                        }else{
                            $('.'+ prefix +  ' .' + prefix + '-license-status-block').html(response.data.license_status_block);
                        }
                    }
                    // update license expiry date
                    if( prefix == 'ep_free' || prefix == 'ep_professional' || prefix == 'ep_essential' || prefix == 'ep_metabundle' || prefix == 'ep_metabundle_plus' || prefix == 'ep_premium_plus' ){
                        $('.ep_premium .license-expire-date').html(response.data.expire_date);
                    }else{
                        $('.' + prefix +  ' .license-expire-date').html(response.data.expire_date);
                    }
                }else{
                    show_toast( 'error', response.data.message );
                }
            
            },
            error: function() {
                $( '.'+ prefix +  ' .' + prefix + '-license-status-block .ep_license_activate' ).removeClass( 'disabled' );
                show_toast( 'error', ep_admin_license_settings.request_failed );
            }
        });
    });
    

    $( document ).on( 'click', '.ep_license_deactivate', function(e) {
        e.preventDefault();
        var prefix = $(this).attr('data-key');
        let key = $(this).attr('data-prefix');
        var license_key = $('#'+prefix + '_license_key').val();
        var ep_license_deactivate = $('#'+ prefix + '_license_deactivate').val();
        
         $( '.'+ prefix +  ' .license-expire-date' ).html( '' );
         $( '.'+ prefix +  ' .' + prefix + '-license-status-block .ep_license_deactivate' ).addClass( 'disabled' );
        
       
        var data = { 
            action: 'ep_eventprime_deactivate_license', 
            nonce: ep_admin_license_settings.ep_license_nonce,
            ep_license_deactivate : ep_license_deactivate,
            'ep_license' : license_key,
            'ep_item_id' : key, 
            'ep_item_key': prefix
        };
        $.ajax({
            type: 'POST', 
            url :  get_ajax_url(),
            data: data,
            success: function( response ) {
                $( '.'+ prefix +  ' .' + prefix + '-license-status-block .ep_license_deactivate' ).removeClass( 'disabled' );
                if ( ! response || ! response.success || ! response.data ) {
                    show_toast( 'error', licenseResponseMessage( response ) );
                    return;
                }
                // This action answers with status (unified method); older replies carried license_data.
                if( response.data.status === true || ( response.data.license_data && response.data.license_data.success === true ) )
                {
                    show_toast( 'success', response.data.message );
                    // update license activate/deactivate button
                    if( response.data.license_status_block ){
                        if( prefix == 'ep_free' || prefix == 'ep_professional' || prefix == 'ep_essential' || prefix == 'ep_metabundle' || prefix == 'ep_metabundle_plus' || prefix == 'ep_premium_plus' ){
                            $('.ep_premium  .ep_premium-license-status-block').html(response.data.license_status_block);
                        }else{
                            $('.'+ prefix +  ' .' + prefix + '-license-status-block').html(response.data.license_status_block);
                        }
                    }
                    // update license expiry date
                    // $('.'+prefix+ ' .license-expire-date').html(response.data.expire_date);
                    if( prefix == 'ep_free' || prefix == 'ep_professional' || prefix == 'ep_essential' || prefix == 'ep_metabundle' || prefix == 'ep_metabundle_plus' || prefix == 'ep_premium_plus' ){
                        $('.ep_premium .license-expire-date').html('');
                    }else{
                        $('.'+prefix+ ' .license-expire-date').html('');
                    }
                }else{
                    show_toast( 'error', response.data.message );
                    if( response.data.license_status_block ){
                        if( prefix == 'ep_free' || prefix == 'ep_professional' || prefix == 'ep_essential' || prefix == 'ep_metabundle' || prefix == 'ep_metabundle_plus' || prefix == 'ep_premium_plus' ){
                            $('.ep_premium .ep_premium-license-status-block').html(response.data.license_status_block);
                        }else{
                            $('.'+ prefix +  ' .' + prefix + '-license-status-block').html(response.data.license_status_block);
                        }
                    }
                    // if( response.data.expire_date != '' && response.data.expire_date != 'undefined' ){
                    //     // update license expiry date
                    //     $('.'+prefix+ ' .license-expire-date').html(response.data.expire_date);
                    // }
                    if( prefix == 'ep_free' || prefix == 'ep_professional' || prefix == 'ep_essential' || prefix == 'ep_metabundle' || prefix == 'ep_metabundle_plus' || prefix == 'ep_premium_plus' ){
                        $('.ep_premium .license-expire-date').html(''); 
                    }else{
                        $('.'+prefix+ ' .license-expire-date').html(''); 
                    }
                }
            },
            error: function() {
                $( '.'+ prefix +  ' .' + prefix + '-license-status-block .ep_license_deactivate' ).removeClass( 'disabled' );
                show_toast( 'error', ep_admin_license_settings.request_failed );
            }
        });
    });


});

function ep_on_change_bundle(value)
{
    jQuery('#ep_premium_license_key').attr('data-prefix',value);
    jQuery('.ep_premium-license-status-block button').attr('data-prefix',value);
}


jQuery(document).ready(function () {
    jQuery('.ep-tooltips').append("<span></span>");
    jQuery('.ep-tooltips:not([tooltip-position])').attr('tooltip-position', 'bottom');
    jQuery( ".ep-tooltips" ).on( 'mouseenter', function() {
        jQuery(this).find('span').empty().append(jQuery(this).attr('tooltip'));
    });
});
