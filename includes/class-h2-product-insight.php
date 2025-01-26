<?php
/**
 * Main Class for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 * @file      includes/class-h2-product-insight.php
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include only the necessary files
require_once plugin_dir_path(__FILE__) . './constants.php';
require_once plugin_dir_path(__FILE__) . './product-insight-settings.php';
require_once plugin_dir_path(__FILE__) . './product-insight-renderer.php';
require_once plugin_dir_path(__FILE__) . './class-h2-product-insight-sanitizer.php';

/**
 * Main plugin class
 *
 * @package    H2PIAI_Product_Insight_Main
 * @subpackage Classes
 * @since      1.0.0
 */
class H2PIAI_Product_Insight_Main {

    private $settings;
    private $api_key;
    private $product_id; // Add this line to store product ID

    public function __construct() {
        // Settings class initialization
        $this->settings = new H2PIAI_Product_Insight_Settings();
        add_action('init', array($this, 'init'));

        // AJAX action hooks
        add_action('wp_ajax_send_product_insight_message', array($this, 'send_product_insight_message'));
        add_action('wp_ajax_nopriv_send_product_insight_message', array($this, 'send_product_insight_message'));
        add_action('wp_ajax_h2_product_insight_initial_call', array($this, 'handle_initial_call'));
        add_action('wp_ajax_nopriv_h2_product_insight_initial_call', array($this, 'handle_initial_call'));

        // Hook to display the chatbox
        add_action('init', array($this, 'add_chatbox_display_hook'));

        // Add shortcode registration
        add_shortcode('h2piai_product_insight', array($this, 'handle_shortcode'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    // Add this new method to handle shortcode
    public function handle_shortcode($atts) {
        $attributes = shortcode_atts(array(
            'product_id' => 0
        ), $atts);

        $this->product_id = absint($attributes['product_id']);

        // Enqueue scripts now that product_id is set
        $this->enqueue_scripts();

        return $this->render_chatbox();
    }

    public function init() {
        $options = get_option('h2piai_product_insight_options', array()); // Added default array value
        $this->api_key = isset($options['api_key']) ? sanitize_text_field($options['api_key']) : '';
    }

    public function enqueue_scripts() {
        // Enqueue the CSS and JS scripts
        wp_enqueue_style(
            'product-insight-style',
            plugin_dir_url(__FILE__) . '../css/product-insight-style.css',
            array(),
            H2PIAI_PRODUCT_INSIGHT_VERSION  // Updated to use constant
        );
        wp_enqueue_script(
            'h2-product-insight-script',
            plugin_dir_url(__FILE__) . '../js/h2-product-insight-script.js',
            array('jquery'),
            H2PIAI_PRODUCT_INSIGHT_VERSION,  // Updated to use constant
            true
        );
        wp_localize_script('h2-product-insight-script', 'h2_product_insight_ajax', array(
            'ajax_url'   => esc_url(admin_url('admin-ajax.php')),
            'api_key'    => esc_attr($this->api_key),
            'product_id' => absint($this->product_id ?: get_the_ID()),
            'nonce'      => wp_create_nonce('h2_product_insight_nonce') // Add this line
        ));

        // Add custom CSS if it exists
        $options = get_option('h2piai_product_insight_options', array());
        if (!empty($options['custom_css'])) {
            $custom_css = H2PIAI_Product_Insight_Sanitizer::sanitize_custom_css($options['custom_css']);
            wp_add_inline_style('product-insight-style', $custom_css);
        }
    }

    public function add_chatbox_display_hook() {
        $options   = get_option('h2piai_product_insight_options');
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
        // First ensure scripts are loaded
        if (is_product() || $this->product_id) {
            $this->product_id = $this->product_id ?: get_the_ID();
            $this->enqueue_scripts();
            echo wp_kses_post($this->render_chatbox());
        }
    }

    public function add_product_insight_tab($tabs) {
        $tabs['product_insight'] = array(
            'title'    => esc_html__('Product Insight', 'h2-product-insight'),
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
        
        // Ensure scripts are loaded before rendering
        if (!wp_script_is('h2-product-insight-script', 'enqueued')) {
            $this->enqueue_scripts();
        }
        
        return H2PIAI_Product_Insight_Renderer::render();
    }

    public function handle_initial_call() {
        // Verify nonce first
        if (!check_ajax_referer('h2_product_insight_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Security check failed.', 'h2-product-insight'));
            return;
        }

        // Validate required fields exist
        if (!isset($_POST['subscription_external_id'], $_POST['timeZone'])) {
            wp_send_json_error(esc_html__('Required fields are missing', 'h2-product-insight'));
            return;
        }

        // Sanitize and validate inputs
        $subscription_id = sanitize_text_field(wp_unslash($_POST['subscription_external_id']));
        if (empty($subscription_id)) {
            wp_send_json_error(esc_html__('Invalid subscription ID', 'h2-product-insight'));
            return;
        }

        $timezone = sanitize_text_field(wp_unslash($_POST['timeZone']));
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            wp_send_json_error(esc_html__('Invalid timezone', 'h2-product-insight'));
            return;
        }

        $caller_domain = '';
        if (isset($_POST['caller_domain'])) {
            $caller_domain = sanitize_text_field(wp_unslash($_POST['caller_domain']));
            if (empty($caller_domain)) {
                wp_send_json_error(esc_html__('Invalid domain', 'h2-product-insight'));
                return;
            }

            // Ensure the domain starts with http:// or https://
            if (strpos($caller_domain, 'http://') !== 0 && strpos($caller_domain, 'https://') !== 0) {
                $caller_domain = 'https://' . $caller_domain;
            }            

            if (!wp_http_validate_url($caller_domain)) {
                wp_send_json_error(esc_html__('Invalid domain', 'h2-product-insight'));
                return;
            }
        }

        $product_id = 0;
        if (isset($_POST['product_id'])) {
            $product_id = absint(wp_unslash($_POST['product_id']));
            if ($product_id > 0 && !wc_get_product($product_id)) {
                wp_send_json_error(esc_html__('Invalid product ID', 'h2-product-insight'));
                return;
            }
        }

        // 5. Create data array with sanitized values
        $initial_data = array(
            'subscription_external_id' => $subscription_id,
            'timeZone'                => $timezone,
            'caller'                  => new stdClass(),
            'caller_domain'           => $caller_domain
        );
    
        // Product data handling
        $product_description = '';
        $product_title = '';
        if ($product_id > 0) {  // Additional validation
            $product = wc_get_product($product_id);
            if ($product && $product instanceof WC_Product) {  // Type checking
                $product_title = sanitize_text_field($this->get_product_title($product));
                $product_description = wp_kses_post($this->get_product_full_description($product));
            }
        }
    
        $response = $this->call_ai_api_initial($initial_data);
    
        if (is_wp_error($response)) {
            wp_send_json_error(esc_html($response->get_error_message()));
            return;
        }
    
        $raw_data = H2PIAI_Product_Insight_Sanitizer::sanitize_wp_unslash($response['body']);  // Unslash before sanitization
        $ai_response = H2PIAI_Product_Insight_Sanitizer::sanitize_ai_response($raw_data);
        if (!$ai_response || !isset($ai_response->success) || $ai_response->success !== true) {
            $error_message = isset($ai_response->message) ? 
                esc_html($ai_response->message) : 
                esc_html__('Unknown error occurred', 'h2-product-insight');
            wp_send_json_error($error_message);
            return;
        }
    
        // Ensure data object exists and sanitize final output
        if (!isset($ai_response->data)) {
            $ai_response->data = new stdClass();
        }
    
        // Safely assign the properties with sanitized values
        $ai_response->data = (object) array_merge(
            (array) $ai_response->data,
            array(
                'product_title' => sanitize_text_field($product_title),
                'product_description' => wp_kses_post($product_description)
            )
        );
    
        wp_send_json_success($ai_response);
    }

    public function send_product_insight_message() {
        // Verify nonce first
        if (!check_ajax_referer('h2_product_insight_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Security check failed.', 'h2-product-insight'));
            return;
        }

        // Validate message exists
        if (!isset($_POST['message'])) {
            wp_send_json_error(esc_html__('Message is required', 'h2-product-insight'));
            return;
        }

        // Sanitize and validate message
        $user_message = sanitize_text_field(wp_unslash($_POST['message']));
        if (empty($user_message)) {
            wp_send_json_error(esc_html__('Message cannot be empty', 'h2-product-insight'));
            return;
        }

        // Validate message length
        if (strlen($user_message) > H2PIAI_PRODUCT_INSIGHT_MAX_MESSAGE_LENGTH) {
            wp_send_json_error(esc_html__('Message is too long', 'h2-product-insight'));
            return;
        }

        // Validate and sanitize data array if present
        $initial_data = array();
        if (isset($_POST['data'])) {
            $raw_data = wp_unslash($_POST['data']);
            if (!empty($raw_data)) {
                $initial_data = H2PIAI_Product_Insight_Sanitizer::sanitize_ai_response($raw_data);
                if (!$initial_data) {
                    wp_send_json_error(esc_html__('Invalid data format', 'h2-product-insight'));
                    return;
                }
            }
        }

        // 5. Make API call with sanitized data
        $response = $this->call_ai_api($user_message, $initial_data);
    
        if (is_wp_error($response)) {
            wp_send_json_error(esc_html($response->get_error_message()));
            return;
        }
    
        // 6. Sanitize API response
        $raw_response = H2PIAI_Product_Insight_Sanitizer::sanitize_wp_unslash($response['body']);  // Unslash before sanitization
        $ai_response = H2PIAI_Product_Insight_Sanitizer::sanitize_ai_response($raw_response);
    
        if (!$ai_response || !isset($ai_response->success) || $ai_response->success !== true) {
            $error_message = isset($ai_response->message) ? 
                esc_html($ai_response->message) : 
                esc_html__('Unknown error occurred', 'h2-product-insight');
            wp_send_json_error($error_message);
            return;
        }
    
        wp_send_json_success($ai_response);
    }
    
    private function call_ai_api_initial($initial_data) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'api_error', 
                esc_html__('API Key is not set. Please configure the plugin settings.', 'h2-product-insight')
            );
        }

        $body = wp_json_encode($initial_data);

        return wp_remote_post(esc_url_raw(H2PIAI_PRODUCT_INSIGHT_API_URL . '/query'), array(
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
            return new WP_Error('api_error', 
                esc_html__('API Key is not set.', 'h2-product-insight')
            );
        }

        $url = esc_url(H2PIAI_PRODUCT_INSIGHT_API_URL . '/query');
        if (empty($url)) {
            return new WP_Error('invalid_url', 
                esc_html__('Invalid API URL', 'h2-product-insight')
            );
        }

        $sanitized_data = array(
            'data'    => $initial_data,
            'message' => $message
        );

        $response = wp_remote_post(esc_url($url), array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key // Changed
            ),
            'body'    => wp_json_encode($sanitized_data),
            'timeout' => 15
        ));

        return $response;
    }

    private function get_product_title($product) {
        return $product->get_name();
    }

    private function get_product_full_description($product) {
        $short_description = $product->get_short_description();
        $description = $product->get_description();
        $reviews = $this->get_product_reviews($product->get_id());
    
        $data1 = implode("\n", array_filter([$short_description, $description, $reviews]));
        return $data1;
    }

    private function get_product_reviews($product_id) {
        $args    = array(
            'post_id' => absint($product_id), // Added absint
            'status'  => 'approve',
            'number'  => min(50, absint(apply_filters('h2_product_insight_max_reviews', 50))), // Added limit
            'orderby' => 'date',
            'order'   => 'DESC',
        );
        $reviews = get_comments($args);
        $texts   = array();

        foreach ($reviews as $review) {
            $texts[] = wp_kses_post($review->comment_content);
        }

        return implode("\n", $texts);
    }
}