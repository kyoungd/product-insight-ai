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
require_once H2_PRODUCT_INSIGHT_PATH . 'includes/constants.php';
require_once H2_PRODUCT_INSIGHT_PATH . 'includes/product-insight-settings.php';
require_once H2_PRODUCT_INSIGHT_PATH . 'includes/product-insight-renderer.php';
require_once H2_PRODUCT_INSIGHT_PATH . 'includes/class-h2-product-insight-sanitizer.php';

/**
 * Main plugin class
 *
 * @package    TwoHumanAI_Product_Insight_Main
 * @subpackage Classes
 * @since      1.0.0
 */
class TwoHumanAI_Product_Insight_Main {

    private $settings;
    private $api_key;
    private $product_id; // Add this line to store product ID

    public function __construct() {
        // Settings class initialization
        $this->settings = new TwoHumanAI_Product_Insight_Settings();
        add_action('init', array($this, 'init'));

        // AJAX action hooks
        add_action('wp_ajax_TwoHumanAI_send_product_insight_message', array($this, 'TwoHumanAI_send_product_insight_message'));
        add_action('wp_ajax_nopriv_TwoHumanAI_send_product_insight_message', array($this, 'TwoHumanAI_send_product_insight_message'));
        add_action('wp_ajax_TwoHumanAI_product_insight_initial_call', array($this, 'TwoHumanAI_handle_initial_call'));
        add_action('wp_ajax_nopriv_TwoHumanAI_product_insight_initial_call', array($this, 'TwoHumanAI_handle_initial_call'));

        // Hook to display the chatbox
        add_action('init', array($this, 'add_chatbox_display_hook'));

        // Add shortcode registration
        add_shortcode('TwoHumanAI_product_insight', array($this, 'handle_shortcode'));

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
        $options = get_option('TwoHumanAI_product_insight_options', array()); // Added default array value
        $this->api_key = isset($options['api_key']) ? sanitize_text_field($options['api_key']) : '';
    }

    public function enqueue_scripts() {
        // Register the style
        wp_register_style(
            'h2-product-insight-style',
            plugin_dir_url(__FILE__) . '../css/product-insight-style.css',
            array(), // No dependencies
            TwoHumanAI_PRODUCT_INSIGHT_VERSION
        );

        // Register the script with defer attribute (WordPress 6.3+)
        wp_register_script(
            'h2-product-insight-script',
            plugin_dir_url(__FILE__) . '../js/h2-product-insight-script.js',
            array('jquery'), // Dependency on jQuery
            TwoHumanAI_PRODUCT_INSIGHT_VERSION,
            array(
                'in_footer' => true, // Load in footer
                'strategy'  => 'defer' // Add defer attribute (WP 6.3+)
            )
        );

        // Localize script data
        wp_localize_script('h2-product-insight-script', 'TwoHumanAI_product_insight_ajax', array(
            'ajax_url'   => esc_url(admin_url('admin-ajax.php')),
            'api_key'    => esc_attr($this->api_key),
            'product_id' => absint($this->product_id ?: get_the_ID()),
            'nonce'      => wp_create_nonce('TwoHumanAI_product_insight_nonce')
        ));

        // Enqueue only on product pages or when shortcode is used
        if (is_product() || $this->product_id) {
            wp_enqueue_style('h2-product-insight-style');
            wp_enqueue_script('h2-product-insight-script');

            // Add inline CSS if available
            $options = get_option('TwoHumanAI_product_insight_options', array());
            if (!empty($options['custom_css'])) {
                $custom_css = TwoHumanAI_Product_Insight_Sanitizer::sanitize_custom_css($options['custom_css']);
                $custom_css = wp_strip_all_tags($custom_css); // Additional safety
                // Note: Sanitization ensures CSS safety; no native esc_css() exists in WordPress
                wp_add_inline_style('h2-product-insight-style', $custom_css);            }
        }
    }

    public function add_chatbox_display_hook() {
        $options   = get_option('TwoHumanAI_product_insight_options');
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
        if (!$this->product_id && is_product()) {
            $this->product_id = get_the_ID();
        }
        return TwoHumanAI_Product_Insight_Renderer::render();
    }

    public function TwoHumanAI_handle_initial_call() {
        if (!wp_verify_nonce($_POST['nonce'], 'TwoHumanAI_product_insight_nonce')) {
            wp_send_json_error(__('Security check failed.', 'h2-product-insight'));
            wp_die(); // Use wp_die() after wp_send_json_* for AJAX
        }

        if (!isset($_POST['subscription_external_id'], $_POST['timeZone'])) {
            wp_send_json_error(__('Required fields are missing.', 'h2-product-insight'));
            return;
        }

        $subscription_id = sanitize_text_field(wp_unslash($_POST['subscription_external_id']));
        if (empty($subscription_id)) {
            wp_send_json_error(__('Invalid subscription ID.', 'h2-product-insight'));
            return;
        }

        $timezone = sanitize_text_field(wp_unslash($_POST['timeZone']));
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            wp_send_json_error(__('Invalid timezone.', 'h2-product-insight'));
            return;
        }

        $caller_domain = '';
        if (isset($_POST['caller_domain'])) {
            $caller_domain = sanitize_text_field(wp_unslash($_POST['caller_domain']));
            if (empty($caller_domain)) {
                wp_send_json_error(__('Invalid domain.', 'h2-product-insight'));
                return;
            }

            if (strpos($caller_domain, 'http://') !== 0 && strpos($caller_domain, 'https://') !== 0) {
                $caller_domain = 'https://' . $caller_domain;
            }            

            if (!wp_http_validate_url($caller_domain)) {
                wp_send_json_error(__('Invalid domain.', 'h2-product-insight'));
                return;
            }
        }

        $product_id = 0;
        if (isset($_POST['product_id'])) {
            $product_id = absint(wp_unslash($_POST['product_id']));
            if ($product_id > 0 && !wc_get_product($product_id)) {
                wp_send_json_error(__('Invalid product ID.', 'h2-product-insight'));
                return;
            }
        }

        $initial_data = array(
            'subscription_external_id' => $subscription_id,
            'timeZone'                => $timezone,
            'caller'                  => new stdClass(),
            'caller_domain'           => $caller_domain
        );
    
        $product_description = '';
        $product_title = '';
        if ($product_id > 0) {
            $product = wc_get_product($product_id);
            if ($product && $product instanceof WC_Product) {
                $product_title = sanitize_text_field($this->get_product_title($product));
                $product_description = wp_kses_post($this->get_product_full_description($product));
            }
        }
    
        $response = $this->call_ai_api_initial($initial_data);
    
        if (is_wp_error($response)) {
            wp_send_json_error(__($response->get_error_message(), 'h2-product-insight'));
            return;
        }
    
        $raw_data = TwoHumanAI_Product_Insight_Sanitizer::sanitize_wp_unslash($response['body']);
        $ai_response = TwoHumanAI_Product_Insight_Sanitizer::sanitize_and_validate_ai_response($raw_data);
        if (!$ai_response || !isset($ai_response->success) || $ai_response->success !== true) {
            $error_message = isset($ai_response->message) ? 
                esc_html__($ai_response->message, 'h2-product-insight') : 
                esc_html__('Unknown error occurred.', 'h2-product-insight');
            wp_send_json_error($error_message);
            return;
        }
    
        if (!isset($ai_response->data)) {
            $ai_response->data = new stdClass();
        }
    
        $ai_response->data = (object) array_merge(
            (array) $ai_response->data,
            array(
                'product_title' => sanitize_text_field($product_title),
                'product_description' => wp_kses_post($product_description)
            )
        );
    
        wp_send_json_success($ai_response);
    }

    public function TwoHumanAI_send_product_insight_message() {
        // Verify nonce for security
        if (!check_ajax_referer('TwoHumanAI_product_insight_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed. Please try again.', 'h2-product-insight'));
            return;
        }
    
        // Check for required message field
        if (!isset($_POST['message'])) {
            wp_send_json_error(__('Message field is missing. Please provide a message.', 'h2-product-insight'));
            return;
        }
    
        // Sanitize and validate message early
        $user_message = sanitize_text_field(wp_unslash($_POST['message']));
        if (empty($user_message)) {
            wp_send_json_error(__('Message cannot be empty. Please enter a valid message.', 'h2-product-insight'));
            return;
        }
    
        if (strlen($user_message) > TwoHumanAI_PRODUCT_INSIGHT_MAX_MESSAGE_LENGTH) {
            wp_send_json_error(sprintf(
                esc_html__('Message exceeds maximum length of %d characters.', 'h2-product-insight'),
                TwoHumanAI_PRODUCT_INSIGHT_MAX_MESSAGE_LENGTH
            ));
            return;
        }
    
        $initial_data = array();
        if (isset($_POST['data'])) {
            $raw_data = wp_unslash($_POST['data']);
    
            // #1: Stronger Validation - Explicitly check data type and structure
            if (!is_string($raw_data) && !is_array($raw_data)) {
                wp_send_json_error(__('Invalid data type received. Expected string or array.', 'h2-product-insight'));
                return;
            }
    
            // #2: Sanitization Clarity - Rename and document the method for clarity
            // Note: Assuming TwoHumanAI_Product_Insight_Sanitizer::sanitize_and_validate_ai_response exists or is renamed
            $initial_data = TwoHumanAI_Product_Insight_Sanitizer::sanitize_and_validate_ai_response($raw_data);
            if (!$initial_data) {
                // #3: Error Specificity - Provide detailed error messages
                if (is_string($raw_data) && json_decode($raw_data, true) === null) {
                    wp_send_json_error(__('Invalid JSON format in data. Please check the input.', 'h2-product-insight'));
                } else {
                    wp_send_json_error(__('AI response data is invalid or incomplete.', 'h2-product-insight'));
                }
                return;
            }
        }
    
        // Proceed with API call using sanitized and validated data
        $response = $this->call_ai_api($user_message, $initial_data);
    
        if (is_wp_error($response)) {
            wp_send_json_error(__($response->get_error_message(), 'h2-product-insight'));
            return;
        }
    
        $raw_response = TwoHumanAI_Product_Insight_Sanitizer::sanitize_wp_unslash($response['body']);
        $ai_response = TwoHumanAI_Product_Insight_Sanitizer::sanitize_and_validate_ai_response($raw_response);
    
        if (!$ai_response || !isset($ai_response->success) || $ai_response->success !== true) {
            $error_message = isset($ai_response->message) ? 
                esc_html__($ai_response->message, 'h2-product-insight') : 
                esc_html__('Unknown error occurred while processing the AI response.', 'h2-product-insight');
            wp_send_json_error($error_message);
            return;
        }
    
        wp_send_json_success($ai_response);
    }
        
    private function call_ai_api_initial($initial_data) {
        if (empty($this->api_key)) {
            return new WP_Error('api_error', __('API Key is not set. Please configure the plugin settings.', 'h2-product-insight'));
        }
    
        $response = wp_remote_post(esc_url_raw(TwoHumanAI_PRODUCT_INSIGHT_API_URL . '/query'), array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body'    => wp_json_encode($initial_data),
            'timeout' => 15
        ));
    
        if (is_wp_error($response)) {
            return $response;
        }
    
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
    
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('API request failed with status %d.', 'h2-product-insight'), $response_code));
        }
    
        if (empty($response_body)) {
            return new WP_Error('api_error', __('Empty response from API.', 'h2-product-insight'));
        }
    
        return $response;
    }

    private function call_ai_api($message, $initial_data) {
        if (empty($this->api_key)) {
            return new WP_Error('api_error', 
                esc_html__('API Key is not set.', 'h2-product-insight')
            );
        }

        $url = esc_url(TwoHumanAI_PRODUCT_INSIGHT_API_URL . '/query');
        if (empty($url)) {
            return new WP_Error('invalid_url', 
                esc_html__('Invalid API URL.', 'h2-product-insight')
            );
        }

        $sanitized_data = array(
            'data'    => $initial_data,
            'message' => $message
        );

        $response = wp_remote_post(esc_url($url), array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
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
            'post_id' => absint($product_id),
            'status'  => 'approve',
            'number'  => min(50, absint(apply_filters('TwoHumanAI_product_insight_max_reviews', 50))),
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