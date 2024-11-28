<?php
/**
 * Settings Class for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 */

// File: includes/product-insight-settings.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . './constants.php';

class H2_Product_Insight_Settings {
    private $options;
    private $invalid_fields = array();

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));

        // Retrieve invalid fields from the previous submission
        $this->invalid_fields = get_option('h2_product_insight_invalid_fields', array());

        // Enqueue the custom CSS and scripts for the settings page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handler for activation
        add_action('wp_ajax_h2_activate_product_insight', array($this, 'handle_activate_product_insight'));
    }

    public function enqueue_admin_scripts($hook) {
        if ('settings_page_h2_product_insight' !== $hook) {
            return;
        }
        wp_enqueue_script('jquery');

        // Update version number to use constant
        wp_enqueue_script('h2_product_insight_admin_js', 
            plugins_url('../js/activation.js', __FILE__), 
            array('jquery'), 
            H2_PRODUCT_INSIGHT_VERSION, 
            true 
        );

        // Localize script to pass AJAX URL and nonce
        wp_localize_script('h2_product_insight_admin_js', 'h2_product_insight', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('h2_activate_product_insight_nonce')
        ));
    }

    /**
     * Enqueues custom admin styles.
     */
    public function enqueue_admin_styles($hook) {
        // Only enqueue on our settings page
        if ('settings_page_h2_product_insight' !== $hook) {
            return;
        }

        wp_enqueue_style('h2_product_insight_admin_css', plugins_url('../css/product-insight-style.css', __FILE__));
    }

    /**
     * Adds the settings page to the WordPress admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __('H2 Product Insight Settings', 'h2'),
            __('H2 AI', 'h2'),
            'manage_options',
            'h2_product_insight',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Initializes the plugin settings.
     */
    public function init_settings() {
        register_setting('h2_product_insight_settings', 'h2_product_insight_options', array($this, 'sanitize'));

        add_settings_section(
            'h2_product_insight_general_section',
            __('General Settings', 'h2'),
            array($this, 'render_general_section'),
            'h2_product_insight_settings'
        );

        // API Key field
        add_settings_field(
            'api_key',
            __('API Key', 'h2'),
            array($this, 'render_api_key_field'),
            'h2_product_insight_settings',
            'h2_product_insight_general_section'
        );

        // Chatbox Placement field
        add_settings_field(
            'chatbox_placement',
            __('Chatbox Placement', 'h2'),
            array($this, 'render_chatbox_placement_field'),
            'h2_product_insight_settings',
            'h2_product_insight_general_section'
        );

        // Custom CSS field
        add_settings_field(
            'custom_css',
            __('Custom CSS', 'h2'),
            array($this, 'render_custom_css_field'),
            'h2_product_insight_settings',
            'h2_product_insight_general_section'
        );
    }

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

        // Use direct concatenation for API endpoints
        $response = wp_remote_post(H2_PRODUCT_INSIGHT_API_URL . '/validate-api-key', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('api_key' => $sanitized_input['api_key'])),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            $this->invalid_fields = array_merge($this->invalid_fields, array('api_key'));
            add_settings_error(
                'h2_product_insight_settings',
                'api_request_failed',
                sprintf(
                    __('API validation request failed: %s. Previous values retained.', 'h2'),
                    $response->get_error_message()
                ),
                'error'
            );
            // Revert to existing values for API Key
            $sanitized_input['api_key'] = $existing_options['api_key'];
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body, true);

            if ($response_code !== 200 || empty($result['success'])) {
                $this->invalid_fields = array_merge($this->invalid_fields, array('api_key'));
                $message = !empty($result['message']) 
                    ? $result['message'] 
                    : __('API validation failed. Previous values retained.', 'h2');
                add_settings_error(
                    'h2_product_insight_settings',
                    'api_validation_failed',
                    $message,
                    'error'
                );
                // Revert to existing values for API URL and Key
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
    
    /**
     * Renders the settings page.
     */
    public function render_settings_page() {
        $this->options = get_option('h2_product_insight_options');
        ?>
        <div class="h2-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><a href="https://2human.ai/product-insight" target="_blank"><?php esc_html_e('PRODUCT INSIGHT AI HOME', 'h2'); ?></a></p>
            
            <?php if (empty($this->options['api_key'])) : ?>
                <form id="h2_activate_product_insight" method="post">
                    <?php wp_nonce_field('h2_activate_product_insight_nonce', 'h2_activate_product_insight_nonce_field'); ?>
                    <button type="button" class="button button-primary" id="h2_activate_button"><?php esc_html_e('Activate Product Insight AI', 'h2'); ?></button>
                </form>
                <div id="h2_activation_message"></div>
            <?php else : ?>
                <form action="options.php" method="post">
                <?php
                    settings_fields('h2_product_insight_settings');
                    do_settings_sections('h2_product_insight_settings');
                    submit_button();
                ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
        // Display settings errors
        settings_errors('h2_product_insight_settings');

        // Delete invalid fields option after rendering
        delete_option('h2_product_insight_invalid_fields');
    }

    /**
     * Renders the general settings section.
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure the settings for the H2 Product Insight plugin.', 'h2') . '</p>';
    }

    public function render_api_key_field() {
        $value = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $error_class = in_array('api_key', $this->invalid_fields) ? 'has-error' : '';
        echo '<div class="h2-input-wrapper ' . $error_class . '">';
        echo '<input type="text" id="api_key" name="h2_product_insight_options[api_key]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<span class="h2-error-indicator"></span>';
        echo '</div>';
    }
    
    /**
     * Renders the Chatbox Placement field.
     */
    public function render_chatbox_placement_field() {
        $value = isset($this->options['chatbox_placement']) ? $this->options['chatbox_placement'] : 'after_add_to_cart';
        
        $options = array(
            // Before product
            'before_single_product' => __('Before Single Product', 'h2'),
            
            // Title area
            'before_title' => __('Before Product Title', 'h2'),
            'after_title' => __('After Product Title', 'h2'),
            
            // Price area
            'before_price' => __('Before Price', 'h2'),
            'after_price' => __('After Price', 'h2'),
            
            // Short description area
            'before_excerpt' => __('Before Short Description', 'h2'),
            'after_excerpt' => __('After Short Description', 'h2'),
            
            // Add to cart area
            'before_add_to_cart' => __('Before Add to Cart Button', 'h2'),
            'after_add_to_cart' => __('After Add to Cart Button', 'h2'),
            
            // Product meta
            'before_product_meta' => __('Before Product Meta', 'h2'),
            'after_product_meta' => __('After Product Meta', 'h2'),
            
            // Product summary
            'before_product_summary' => __('Before Product Summary', 'h2'),
            'after_product_summary' => __('After Product Summary', 'h2'),
            
            // Tabs area
            'before_tabs' => __('Before Tabs', 'h2'),
            'in_product_tabs' => __('In Product Tabs', 'h2'),
            'after_tabs' => __('After Tabs', 'h2'),
            
            // Related products
            'before_related_products' => __('Before Related Products', 'h2'),
            'after_related_products' => __('After Related Products', 'h2'),
            
            // End of product
            'after_single_product' => __('After Single Product', 'h2'),
            
            // Sidebar options
            'product_sidebar' => __('In Product Sidebar', 'h2')
        );
        
        echo '<select id="chatbox_placement" name="h2_product_insight_options[chatbox_placement]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        
        echo '<p class="description">' . __('Select where to display the chatbox on product pages', 'h2') . '</p>';
    }

    /**
     * Renders the Custom CSS field.
     */
    public function render_custom_css_field() {
        $value = isset($this->options['custom_css']) ? $this->options['custom_css'] : '';
        echo '<textarea id="custom_css" name="h2_product_insight_options[custom_css]" rows="10" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter any custom CSS to style the chatbox.', 'h2') . '</p>';
    }




    /**
     * Handles the activation of Product Insight AI via AJAX.
     */
    public function handle_activate_product_insight() {
        check_ajax_referer('h2_activate_product_insight_nonce', 'nonce');
        error_log('H2 Product Insight: Starting activation process');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'h2')));
            return;
        }

        $response = wp_remote_post(H2_PRODUCT_INSIGHT_API_URL . '/new-registration', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('api_key' => '')),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        error_log('H2 Product Insight: API Response: ' . $response_body);

        if ($response_code !== 200 || empty($result['api_key'])) {
            $error_message = isset($result['message']) ? $result['message'] : __('API activation failed.', 'h2');
            wp_send_json_error(array('message' => $error_message));
            return;
        }

        // Create options array directly (bypass sanitize for initial activation)
        $options = array(
            'api_key' => sanitize_text_field($result['api_key']),
            'custom_template' => '',
            'custom_css' => '',
            'chatbox_placement' => 'after_add_to_cart'
        );

        error_log('H2 Product Insight: Options to save: ' . print_r($options, true));

        // Delete existing option first
        delete_option('h2_product_insight_options');
        
        // Add new option
        $update_success = add_option('h2_product_insight_options', $options);
        
        if (!$update_success) {
            // If add_option failed, try update_option
            $update_success = update_option('h2_product_insight_options', $options, false);
        }

        error_log('H2 Product Insight: Update success: ' . ($update_success ? 'true' : 'false'));

        // Verify the saved data
        $saved_options = get_option('h2_product_insight_options');
        error_log('H2 Product Insight: Saved options: ' . print_r($saved_options, true));

        if ($saved_options && isset($saved_options['api_key']) && !empty($saved_options['api_key'])) {
            wp_send_json_success(array(
                'message' => __('Product Insight AI activated successfully!', 'h2'),
                'api_key' => $saved_options['api_key']
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save API key.', 'h2')));
        }
    }    


}
