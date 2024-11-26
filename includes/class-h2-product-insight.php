<?php

// file name: include/class-h2-product-insight.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the settings, renderer, and theme integration classes
require_once plugin_dir_path(__FILE__) . './constants.php';
require_once plugin_dir_path(__FILE__) . './product-insight-settings.php';
require_once plugin_dir_path(__FILE__) . './product-insight-renderer.php';
require_once plugin_dir_path(__FILE__) . './theme-integration.php';

class H2_Product_Insight {

    private $settings;
    private $api_key;
    private $product_id; // Add this line to store product ID

    public function __construct() {
        // Settings class initialization
        $this->settings = new H2_Product_Insight_Settings();
        add_action('init', array($this, 'init'));

        // AJAX action hooks
        add_action('wp_ajax_send_product_insight_message', array($this, 'send_product_insight_message'));
        add_action('wp_ajax_nopriv_send_product_insight_message', array($this, 'send_product_insight_message'));
        add_action('wp_ajax_h2_product_insight_initial_call', array($this, 'handle_initial_call'));
        add_action('wp_ajax_nopriv_h2_product_insight_initial_call', array($this, 'handle_initial_call'));

        // Hook to display the chatbox
        add_action('init', array($this, 'add_chatbox_display_hook'));

        // Theme integration
        new H2_Product_Insight_Theme_Integration();

        // Add shortcode registration
        add_shortcode('h2_product_insight', array($this, 'handle_shortcode'));

        // Remove the script enqueuing from the init method
        // add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    // Add this new method to handle shortcode
    public function handle_shortcode($atts) {
        $attributes = shortcode_atts(array(
            'product_id' => 0
        ), $atts);

        $this->product_id = intval($attributes['product_id']);

        // Enqueue scripts now that product_id is set
        $this->enqueue_scripts();

        return $this->render_chatbox();
    }

    public function init() {
        $options = get_option('h2_product_insight_options');
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';

        // Scripts will be enqueued when rendering the chatbox
    }

    public function enqueue_scripts() {
        // Enqueue the CSS and JS scripts
        wp_enqueue_style(
            'product-insight-style',
            plugin_dir_url(__FILE__) . '../css/product-insight-style.css',
            array(),
            '1.1'  // Changed from '1.0' to '1.1'
        );
        wp_enqueue_script(
            'h2-product-insight-script',
            plugin_dir_url(__FILE__) . '../js/h2-product-insight-script.js',
            array('jquery'),
            '1.1',  // Changed from '1.0' to '1.1'
            true
        );
        wp_localize_script('h2-product-insight-script', 'h2_product_insight_ajax', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('h2_product_insight_nonce'),
            'api_key'    => $this->api_key,
            'product_id' => $this->product_id ?: get_the_ID() // Modified to use stored product_id
        ));
    }

    public function add_chatbox_display_hook() {
        $options   = get_option('h2_product_insight_options');
        $placement = isset($options['chatbox_placement']) ? $options['chatbox_placement'] : 'after_add_to_cart';

        switch ($placement) {
            case 'before_add_to_cart':
                add_action('woocommerce_before_add_to_cart_form', array($this, 'display_chatbox'));
                break;
            case 'after_product_summary':
                add_action('woocommerce_after_single_product_summary', array($this, 'display_chatbox'));
                break;
            case 'after_product_meta':
                add_action('woocommerce_product_meta_end', array($this, 'display_chatbox'));
                break;
            case 'after_single_product':
                add_action('woocommerce_after_single_product', array($this, 'display_chatbox'));
                break;
            case 'in_product_tabs':
                add_filter('woocommerce_product_tabs', array($this, 'add_product_insight_tab'));
                break;
            case 'after_add_to_cart':
            default:
                add_action('woocommerce_after_add_to_cart_form', array($this, 'display_chatbox'));
                break;
        }
    }

    public function display_chatbox() {
        // Modified to work for both WooCommerce and shortcode
        if (is_product() || $this->product_id) {
            $this->product_id = get_the_ID();
            // Enqueue scripts now that product_id is set
            $this->enqueue_scripts();
            echo $this->render_chatbox();
        }
    }

    public function add_product_insight_tab($tabs) {
        $tabs['product_insight'] = array(
            'title'    => __('Product Insight', 'h2-product-insight'),
            'priority' => 50,
            'callback' => array($this, 'display_chatbox')
        );
        return $tabs;
    }

    public function render_chatbox() {
        // Ensure product_id is set
        if (!$this->product_id && is_product()) {
            $this->product_id = get_the_ID();
        }
        // Scripts are already enqueued in the previous methods
        return H2_Product_Insight_Renderer::render();
    }

    /**
     * Sanitizes and validates settings input
     * 
     * @param array $input The raw input from the settings form
     * @return array Sanitized input, reverting to previous values if validation fails
     */
    public function sanitize($input) {
        $sanitized_input = array();
        $this->invalid_fields = array(); 
        $existing_options = get_option('h2_product_insight_options', array());

        // Sanitize API Key
        if (isset($input['api_key']) && !empty($input['api_key'])) {
            $sanitized_input['api_key'] = sanitize_text_field($input['api_key']);
        } else {
            $this->invalid_fields[] = 'api_key';
            add_settings_error(
                'h2_product_insight_settings',
                'invalid_api_key',
                __('API Key is required. Previous key retained.', 'h2'),
                'error'
            );
            $sanitized_input['api_key'] = isset($existing_options['api_key']) ? $existing_options['api_key'] : '';
        }

        // If any required fields are missing, retain existing values and stop validation
        if (!empty($this->invalid_fields)) {
            update_option('h2_product_insight_invalid_fields', $this->invalid_fields);
            return array_merge($existing_options, $sanitized_input);
        }

        // Validate API Key
        $validate_api_url = preg_replace('#/query$#', '/validate-api-key', H2_PRODUCT_INSIGHT_API_URL);
        if ($validate_api_url === H2_PRODUCT_INSIGHT_API_URL) {
            $validate_api_url = rtrim(H2_PRODUCT_INSIGHT_API_URL, '/') . '/validate-api-key';
        }

        $response = wp_remote_post($validate_api_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('api_key' => $sanitized_input['api_key'])),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            $this->invalid_fields[] = 'api_key';
            add_settings_error(
                'h2_product_insight_settings',
                'api_request_failed',
                sprintf(
                    __('API validation request failed: %s. Previous values retained.', 'h2'),
                    $response->get_error_message()
                ),
                'error'
            );
            // Revert to existing value for API Key
            $sanitized_input['api_key'] = $existing_options['api_key'];
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body, true);

            if ($response_code !== 200 || empty($result['success'])) {
                $this->invalid_fields[] = 'api_key';
                $message = !empty($result['message']) 
                    ? $result['message'] 
                    : __('API validation failed. Previous values retained.', 'h2');
                add_settings_error(
                    'h2_product_insight_settings',
                    'api_validation_failed',
                    $message,
                    'error'
                );
                // Revert to existing value for API Key
                $sanitized_input['api_key'] = $existing_options['api_key'];
            } else {
                // Validation succeeded - add success message
                add_settings_error(
                    'h2_product_insight_settings',
                    'api_validation_success',
                    __('API connection validated successfully.', 'h2'),
                    'success'
                );
            }
        }

        // Sanitize optional fields
        
        // Sanitize custom template
        if (isset($input['custom_template'])) {
            $sanitized_input['custom_template'] = wp_kses_post($input['custom_template']);
        } else {
            $sanitized_input['custom_template'] = isset($existing_options['custom_template']) 
                ? $existing_options['custom_template'] 
                : '';
        }

        // Sanitize custom CSS
        if (isset($input['custom_css'])) {
            $sanitized_input['custom_css'] = wp_strip_all_tags($input['custom_css']);
        } else {
            $sanitized_input['custom_css'] = isset($existing_options['custom_css']) 
                ? $existing_options['custom_css'] 
                : '';
        }

        // Sanitize Chatbox Placement
        if (isset($input['chatbox_placement'])) {
            $sanitized_input['chatbox_placement'] = sanitize_text_field($input['chatbox_placement']);
        } else {
            $sanitized_input['chatbox_placement'] = isset($existing_options['chatbox_placement']) 
                ? $existing_options['chatbox_placement'] 
                : 'after_add_to_cart';
        }

        // Update invalid fields option for styling
        update_option('h2_product_insight_invalid_fields', $this->invalid_fields);

        // Return merged array to preserve any existing options not included in current update
        return array_merge($existing_options, $sanitized_input);
    }

    public function handle_initial_call() {
        check_ajax_referer('h2_product_insight_nonce', 'nonce');

        // Use the domain passed from JavaScript
        $caller_domain = isset($_POST['caller_domain']) ? sanitize_text_field($_POST['caller_domain']) : '';

        $initial_data = array(
            'subscription_external_id' => sanitize_text_field($_POST['subscription_external_id']),
            'timeZone'                 => sanitize_text_field($_POST['timeZone']),
            'caller'                   => new stdClass(),
            'caller_domain'            => $caller_domain
        );

        // Get the product ID from the AJAX request
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        $product_description = '';
        $product_title       = '';
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_title       = $this->get_product_title($product);
                $product_description = $this->get_product_full_description($product);
            }
        }

        $response = $this->call_ai_api_initial($initial_data);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
    
        $ai_response = json_decode($response['body'], false);
    
        if (!$ai_response || !isset($ai_response->success) || $ai_response->success !== true) {
            $error_message = isset($ai_response->message) ? $ai_response->message : 'Unknown error occurred';
            wp_send_json_error($error_message);
            return;
        }
    
        $ai_response->data->product_title = $product_title;
        $ai_response->data->product_description = $product_description;
        wp_send_json_success($ai_response);
    }

    public function send_product_insight_message() {
        check_ajax_referer('h2_product_insight_nonce', 'nonce');

        $user_message = sanitize_text_field($_POST['message']);
        $initial_data = isset($_POST['data']) ? $_POST['data'] : array();

        $response = $this->call_ai_api($user_message, $initial_data);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
    
        $ai_response = json_decode($response['body'], false);
    
        if (!$ai_response || !isset($ai_response->success) || $ai_response->success !== true) {
            $error_message = isset($ai_response->message) ? $ai_response->message : 'Unknown error occurred';
            wp_send_json_error($error_message);
            return;
        }
    
        wp_send_json_success($ai_response);
    }

    private function call_ai_api_initial($initial_data) {
        if (empty($this->api_key)) {
            return new WP_Error('api_error', 'API Key is not set. Please configure the plugin settings.');
        }

        $body = json_encode($initial_data);

        return wp_remote_post(H2_PRODUCT_INSIGHT_API_URL, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body'    => $body,
            'timeout' => 15
        ));
    }

    private function call_ai_api($message, $initial_data) {
        if (empty($this->api_key)) {
            return new WP_Error('api_error', 'API Key is not set. Please configure the plugin settings.');
        }

        $body = json_encode(array(
            'data'    => $initial_data,
            'message' => $message
        ));

        return wp_remote_post(H2_PRODUCT_INSIGHT_API_URL, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body'    => $body,
            'timeout' => 15
        ));
    }

    private function get_product_title($product) {
        return $product->get_name();
    }

    private function get_product_full_description($product) {
        $short_description = $product->get_short_description();
        $description       = $product->get_description();
        $reviews           = $this->get_product_reviews($product->get_id());

        return implode("\n", array_filter([$short_description, $description, $reviews]));
    }

    private function get_product_reviews($product_id) {
        $args    = array(
            'post_id' => $product_id,
            'status'  => 'approve',
        );
        $reviews = get_comments($args);
        $texts   = array();

        foreach ($reviews as $review) {
            $texts[] = $review->comment_content;
        }

        return implode("\n", $texts);
    }
}