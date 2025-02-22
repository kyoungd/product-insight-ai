/**
 * H2 Product Insight - Product Insight AI for WooCommerce
 *
 * @package    H2_Product_Insight
 * @autor      Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 * @file       js/h2-product-insight-script.js
 */

(function($) {

    jQuery(document).ready(function($) {
        const TwoHumanAI_lastReplyContainer = $('#TwoHumanAI-product-insight-ailast-reply-container');
        const TwoHumanAI_inputContainer = $('#TwoHumanAI-product-insight-aiinput');
        let TwoHumanAI_userInput = $('#TwoHumanAI-product-insight-aiuser-input');
        let TwoHumanAI_initialResponse = null;
        let TwoHumanAI_initialCallMade = false;

        function TwoHumanAI_addMessage(message, isAI = false) {
            if (isAI) {
                TwoHumanAI_lastReplyContainer.empty().append(
                    $('<div>', {
                        'class': 'TwoHumanAI-ai-message',
                        'text': message
                    })
                ).show();
            }
        }
        
        function TwoHumanAI_showProgressBar() {
            console.log('TwoHumanAI_showProgressBar called');
            TwoHumanAI_inputContainer.html('<div class="TwoHumanAI-progress-bar"><div class="TwoHumanAI-progress"></div></div>');
        }

        function TwoHumanAI_hideProgressBar() {
            console.log('TwoHumanAI_hideProgressBar called');
            TwoHumanAI_inputContainer.html('<input type="text" id="TwoHumanAI-product-insight-aiuser-input" placeholder="Ask about the product...">');
            TwoHumanAI_userInput = $('#TwoHumanAI-product-insight-aiuser-input'); // Reassign the TwoHumanAI_userInput variable
            TwoHumanAI_attachInputListeners(); // Reattach event listeners
            TwoHumanAI_userInput.focus(); // Add this line to maintain focus
        }

        function TwoHumanAI_makeInitialCall() {
            console.log('TwoHumanAI_makeInitialCall called');
            TwoHumanAI_showProgressBar();
            $.ajax({
                url: TwoHumanAI_product_insight_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'TwoHumanAI_product_insight_initial_call',
                    nonce: TwoHumanAI_product_insight_ajax.nonce,
                    subscription_external_id: TwoHumanAI_product_insight_ajax.api_key,
                    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                    product_id: TwoHumanAI_product_insight_ajax.product_id,
                    caller_domain: window.location.hostname
                },
                success: function(response) {
                    TwoHumanAI_hideProgressBar();
                    if (response.success) {
                        TwoHumanAI_initialResponse = response.data.data;
                        console.log('Initial call successful:', TwoHumanAI_initialResponse);
                        TwoHumanAI_initialCallMade = true;
                    } else {
                        console.error('Initial call failed:', response.data);
                        TwoHumanAI_addMessage('Error initializing chat. Please try again later.', true);
                    }
                },
                error: function(xhr, status, error) {
                    TwoHumanAI_hideProgressBar();
                    console.error('Error making initial call:', error);
                    TwoHumanAI_addMessage('Error initializing chat. Please try again later.', true);
                }
            });
        }

        function TwoHumanAI_sendMessage() {
            const message = TwoHumanAI_userInput.val().trim();
            if (message === '') return;

            TwoHumanAI_userInput.val('');
            TwoHumanAI_showProgressBar();

            if (TwoHumanAI_initialResponse === null) {
                TwoHumanAI_addMessage('Please wait, initializing chat...', true);
                let checkInitialResponse = setInterval(function() {
                    if (TwoHumanAI_initialResponse !== null) {
                        clearInterval(checkInitialResponse);
                        TwoHumanAI_proceedWithMessage(message);
                    }
                }, 100);
            } else {
                TwoHumanAI_proceedWithMessage(message);
            }
        }

        function TwoHumanAI_proceedWithMessage(message) {
            // Enhanced client-side sanitization
            message = message.replace(/[<>]|javascript:|vbscript:|data:/gi, '').trim().substring(0, 1000);
            
            // Add data validation before sending
            if (!message || !TwoHumanAI_product_insight_ajax.nonce) {
                TwoHumanAI_addMessage('Invalid input data', true);
                return;
            }

            $.ajax({
                url: TwoHumanAI_product_insight_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'TwoHumanAI_send_product_insight_message',
                    nonce: TwoHumanAI_product_insight_ajax.nonce,
                    message: message,
                    data: TwoHumanAI_initialResponse
                },
                success: function(response) {
                    TwoHumanAI_hideProgressBar();
                    if (response.success) {
                        TwoHumanAI_initialResponse = response.data.data;
                        TwoHumanAI_addMessage(TwoHumanAI_initialResponse.message, true);
                    } else {
                        TwoHumanAI_addMessage('Error: ' + response.data, true);
                    }
                },
                error: function() {
                    TwoHumanAI_hideProgressBar();
                    TwoHumanAI_addMessage('Error communicating with the server', true);
                }
            });
        }

        function TwoHumanAI_attachInputListeners() {
            console.log('TwoHumanAI_attachInputListeners called');
            TwoHumanAI_userInput.on('keypress', function(e) {
                if (e.which === 13) {
                    TwoHumanAI_sendMessage();
                }
            });

            TwoHumanAI_userInput.one('focus', function() {
                if (!TwoHumanAI_initialCallMade) {
                    console.log('Input field focused. Making initial AI call.');
                    TwoHumanAI_makeInitialCall();
                }
            });
        }

        TwoHumanAI_hideProgressBar();
    });

})(jQuery);