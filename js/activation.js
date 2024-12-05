/**
 * H2 Product Insight - Admin Activation Script
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 */

jQuery(document).ready(function($) {
    $('#h2_activate_button').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $message = $('#h2_activation_message');
        var $spinner = $button.find('.spinner');
        
        $button.prop('disabled', true);
        $spinner.show();
        $message.html('Activating...').show().removeClass('updated error');
        
        $.ajax({
            url: h2_product_insight.ajax_url,
            type: 'POST',
            data: {
                action: 'h2_activate_product_insight',
                nonce: h2_product_insight.nonce  // This should match what we verify in PHP
            },
            success: function(response) {
                if (response.success) {
                    $message.html(response.data.message).addClass('updated');
                    // Reload the page after successful activation
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $message.html('Error: ' + response.data.message).addClass('error');
                    $button.prop('disabled', false);
                    $spinner.hide();
                }
            },
            error: function(xhr, status, error) {
                $message.html('Connection error: ' + error).addClass('error');
                $button.prop('disabled', false);
                $spinner.hide();
            }
        });
    });
});