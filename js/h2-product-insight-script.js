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
        // // Check if we're on a product page
        // if (!$('body').hasClass('single-product')) {
        //     console.log('skip - no single product.')
        //     return;
        // }

        const h2piai_lastReplyContainer = $('#h2piai-product-insight-ailast-reply-container');
        const h2piai_inputContainer = $('#h2piai-product-insight-aiinput');
        let h2piai_userInput = $('#h2piai-product-insight-aiuser-input');
        let h2piai_initialResponse = null;
        let h2piai_initialCallMade = false;

        function h2piai_addMessage(message, isAI = false) {
            if (isAI) {
                h2piai_lastReplyContainer.empty().append(
                    $('<div>', {
                        'class': 'h2piai-ai-message',
                        'text': message
                    })
                ).show();
            }
        }
        
        function h2piai_showProgressBar() {
            console.log('h2piai_showProgressBar called');
            h2piai_inputContainer.html('<div class="h2piai-progress-bar"><div class="h2piai-progress"></div></div>');
        }

        function h2piai_hideProgressBar() {
            console.log('h2piai_hideProgressBar called');
            h2piai_inputContainer.html('<input type="text" id="h2piai-product-insight-aiuser-input" placeholder="Ask about the product...">');
            h2piai_userInput = $('#h2piai-product-insight-aiuser-input'); // Reassign the h2piai_userInput variable
            h2piai_attachInputListeners(); // Reattach event listeners
            h2piai_userInput.focus(); // Add this line to maintain focus
        }

        function h2piai_makeInitialCall() {
            console.log('h2piai_makeInitialCall called');
            h2piai_showProgressBar();
            $.ajax({
                url: h2_product_insight_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'h2piai_product_insight_initial_call',
                    nonce: h2_product_insight_ajax.nonce,  // Add this line
                    subscription_external_id: h2_product_insight_ajax.api_key,
                    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                    product_id: h2_product_insight_ajax.product_id,
                    caller_domain: window.location.hostname // Add this line
                },
                success: function(response) {
                    h2piai_hideProgressBar();
                    if (response.success) {
                        h2piai_initialResponse = response.data.data;
                        console.log('Initial call successful:', h2piai_initialResponse);
                        h2piai_initialCallMade = true;
                    } else {
                        console.error('Initial call failed:', response.data);
                        h2piai_addMessage('Error initializing chat. Please try again later.', true);
                    }
                },
                error: function(xhr, status, error) {
                    h2piai_hideProgressBar();
                    console.error('Error making initial call:', error);
                    h2piai_addMessage('Error initializing chat. Please try again later.', true);
                }
            });
        }

        function h2piai_sendMessage() {
            const message = h2piai_userInput.val().trim();
            if (message === '') return;

            h2piai_userInput.val('');
            h2piai_showProgressBar();

            if (h2piai_initialResponse === null) {
                h2piai_addMessage('Please wait, initializing chat...', true);
                let checkInitialResponse = setInterval(function() {
                    if (h2piai_initialResponse !== null) {
                        clearInterval(checkInitialResponse);
                        h2piai_proceedWithMessage(message);
                    }
                }, 100);
            } else {
                h2piai_proceedWithMessage(message);
            }
        }

        function h2piai_proceedWithMessage(message) {
            // Basic client-side sanitization
            message = message.replace(/[<>]/g, '').trim().substring(0, 1000);
            
            // Add data validation before sending
            if (!message || !h2_product_insight_ajax.nonce) {
                h2piai_addMessage('Invalid input data', true);
                return;
            }

            $.ajax({
                url: h2_product_insight_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'h2piai_send_product_insight_message',
                    nonce: h2_product_insight_ajax.nonce,
                    message: message,
                    data: h2piai_initialResponse
                },
                success: function(response) {
                    h2piai_hideProgressBar();
                    if (response.success) {
                        h2piai_initialResponse = response.data.data;
                        h2piai_addMessage(h2piai_initialResponse.message, true);
                    } else {
                        h2piai_addMessage('Error: ' + response.data, true);
                    }
                },
                error: function() {
                    h2piai_hideProgressBar();
                    h2piai_addMessage('Error communicating with the server', true);
                }
            });
        }

        function h2piai_attachInputListeners() {
            console.log('h2piai_attachInputListeners called');
            h2piai_userInput.on('keypress', function(e) {
                if (e.which === 13) {
                    h2piai_sendMessage();
                }
            });

            h2piai_userInput.one('focus', function() {
                if (!h2piai_initialCallMade) {
                    console.log('Input field focused. Making initial AI call.');
                    h2piai_makeInitialCall();
                }
            });
        }

        h2piai_hideProgressBar();
    });

})(jQuery);