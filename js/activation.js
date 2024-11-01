jQuery(document).ready(function($){
    $('#h2_activate_button').on('click', function(e){
        e.preventDefault();
        var button = $(this);
        var messageDiv = $('#h2_activation_message');
        button.prop('disabled', true).text('Activating...');
        messageDiv.html('');

        $.ajax({
            url: h2_product_insight.ajax_url,
            method: 'POST',
            data: {
                action: 'h2_activate_product_insight',
                nonce: h2_product_insight.nonce
            },
            success: function(response) {
                if(response.success){
                    messageDiv.html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    // Reload the page to display the settings form
                    setTimeout(function(){
                        location.reload();
                    }, 1500);
                } else {
                    messageDiv.html('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                    button.prop('disabled', false).text('Activate Product Insight AI');
                }
            },
            error: function(){
                messageDiv.html('<div class="notice notice-error is-dismissible"><p>Unexpected error. Please try again.</p></div>');
                button.prop('disabled', false).text('Activate Product Insight AI');
            }
        });
    });
});
