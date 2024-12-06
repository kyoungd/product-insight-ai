/**
 * H2 Product Insight - Product Insight AI for WooCommerce
 *
 * @package    H2_Product_Insight
 * @autor      Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 */

jQuery(document).ready(function($) {
    // // Check if we're on a product page
    // if (!$('body').hasClass('single-product')) {
    //     console.log('skip - no single product.')
    //     return;
    // }

    const lastReplyContainer = $('#product-insight-ailast-reply-container');
    const inputContainer = $('#product-insight-aiinput');
    let userInput = $('#product-insight-aiuser-input');
    let initialResponse = null;
    let initialCallMade = false;

    function addMessage(message, isAI = false) {
        if (isAI) {
            lastReplyContainer.html('<div class="ai-message">' + message + '</div>');
            lastReplyContainer.show();
        }
    }

    function showProgressBar() {
        console.log('showProgressBar called');
        inputContainer.html('<div class="progress-bar"><div class="progress"></div></div>');
    }

    function hideProgressBar() {
        console.log('hideProgressBar called');
        inputContainer.html('<input type="text" id="product-insight-aiuser-input" placeholder="Ask about the product...">');
        userInput = $('#product-insight-aiuser-input'); // Reassign the userInput variable
        attachInputListeners(); // Reattach event listeners
        userInput.focus(); // Add this line to maintain focus
    }

    function makeInitialCall() {
        console.log('makeInitialCall called');
        showProgressBar();
        $.ajax({
            url: h2_product_insight_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h2_product_insight_initial_call',
                nonce: h2_product_insight_ajax.nonce,
                subscription_external_id: h2_product_insight_ajax.api_key,
                timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                product_id: h2_product_insight_ajax.product_id,
                caller_domain: window.location.hostname // Add this line
            },
            success: function(response) {
                hideProgressBar();
                if (response.success) {
                    initialResponse = response.data.data;
                    console.log('Initial call successful:', initialResponse);
                    initialCallMade = true;
                } else {
                    console.error('Initial call failed:', response.data);
                    addMessage('Error initializing chat. Please try again later.', true);
                }
            },
            error: function(xhr, status, error) {
                hideProgressBar();
                console.error('Error making initial call:', error);
                addMessage('Error initializing chat. Please try again later.', true);
            }
        });
    }

    function sendMessage() {
        const message = userInput.val().trim();
        if (message === '') return;

        userInput.val('');
        showProgressBar();

        if (initialResponse === null) {
            addMessage('Please wait, initializing chat...', true);
            let checkInitialResponse = setInterval(function() {
                if (initialResponse !== null) {
                    clearInterval(checkInitialResponse);
                    proceedWithMessage(message);
                }
            }, 100);
        } else {
            proceedWithMessage(message);
        }
    }

    function proceedWithMessage(message) {
        $.ajax({
            url: h2_product_insight_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'send_product_insight_message',
                nonce: h2_product_insight_ajax.nonce,
                message: message,
                data: initialResponse
            },
            success: function(response) {
                hideProgressBar();
                if (response.success) {
                    initialResponse = response.data.data;
                    addMessage(initialResponse.message, true);
                } else {
                    addMessage('Error: ' + response.data, true);
                }
            },
            error: function() {
                hideProgressBar();
                addMessage('Error communicating with the server', true);
            }
        });
    }

    function attachInputListeners() {
        console.log('attachInputListeners called');
        userInput.on('keypress', function(e) {
            if (e.which === 13) {
                sendMessage();
            }
        });

        userInput.one('focus', function() {
            if (!initialCallMade) {
                console.log('Input field focused. Making initial AI call.');
                makeInitialCall();
            }
        });
    }

    hideProgressBar();
});