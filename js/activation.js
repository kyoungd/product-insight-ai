/**
 * H2 Product Insight - Admin Activation Script
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 * @file       js/activation.js
 */

jQuery(document).ready(function($) {
    $('#h2_activate_product_insight').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('#h2_activate_button');
        var $message = $('#h2piai-activation-message');
        var $spinner = $button.find('.spinner');
        
        $button.prop('disabled', true);
        $spinner.css('visibility', 'visible'); // WordPress spinners use visibility
        $message.attr('class', 'notice').hide().text('');

        // Serialize form data to include nonce and action
        var formData = $form.serialize();

        // Basic validation before sending
        var apiKey = $form.find('input[name="api_key"]').val();
        if (!apiKey || apiKey.length < 10) {
            $message.text('Invalid API key format').addClass('notice-error').show();
            return;
        }

        // Ensure nonce is present
        if (!$form.find('input[name="_wpnonce"]').val()) {
            $message.text('Security check failed').addClass('notice-error').show();
            return;
        }

        $.post(h2_product_insight.ajax_url, formData, function(response) {
            if (response.success) {
                $message.text(response.data.message).addClass('notice-success').show();
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                $message.text('Error: ' + response.data.message).addClass('notice-error').show();
                $button.prop('disabled', false);
                $spinner.css('visibility', 'hidden');
            }
        }).fail(function(xhr, status, error) {
            $message.text('Connection error: ' + error).addClass('notice-error').show();
            $button.prop('disabled', false);
            $spinner.css('visibility', 'hidden');
        });
    });
});