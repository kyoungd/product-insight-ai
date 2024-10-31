<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the settings, renderer, and theme integration classes
require_once plugin_dir_path(__FILE__) . './product-insight-settings.php';
require_once plugin_dir_path(__FILE__) . './product-insight-renderer.php';
require_once plugin_dir_path(__FILE__) . './theme-integration.php';

class H2_Product_Insight {

    private $settings;
    private $api_url;
    private $api_key;

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
    }

    public function init() {
        $options = get_option('h2_product_insight_options');
        $this->api_url = isset($options['api_url']) ? $options['api_url'] : '';
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts() {
        // Enqueue the CSS and JS scripts
        wp_enqueue_style(
            'product-insight-style',
            plugin_dir_url(__FILE__) . '../css/product-insight-style.css',
            array(),
            '1.0'
        );
        wp_enqueue_script(
            'h2-product-insight-script',
            plugin_dir_url(__FILE__) . '../js/h2-product-insight-script.js',
            array('jquery'),
            '1.0',
            true
        );
        wp_localize_script('h2-product-insight-script', 'h2_product_insight_ajax', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('h2_product_insight_nonce'),
            'api_key'    => $this->api_key,
            'product_id' => get_the_ID()
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
        if (is_product()) {
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
        // Render the chatbox using the renderer class
        return H2_Product_Insight_Renderer::render();
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
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('api_error', 'API URL or Key is not set. Please configure the plugin settings.');
        }

        $body = json_encode($initial_data);

        return wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body'    => $body,
            'timeout' => 15
        ));
    }

    private function call_ai_api($message, $initial_data) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('api_error', 'API URL or Key is not set. Please configure the plugin settings.');
        }

        $body = json_encode(array(
            'data'    => $initial_data,
            'message' => $message
        ));

        return wp_remote_post($this->api_url, array(
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