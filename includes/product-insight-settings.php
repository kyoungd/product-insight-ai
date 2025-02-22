<?php
/**
 * Settings for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 * @file       includes/product-insight-settings.php
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . './constants.php';
require_once plugin_dir_path(__FILE__) . './class-h2-product-insight-sanitizer.php';

class h2piai_Product_Insight_Settings {
    private $options;
    private $invalid_fields = array();

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));

        // Retrieve invalid fields from the previous submission
        $this->invalid_fields = get_option('h2piai_product_insight_invalid_fields', array());

        // Enqueue the custom CSS and scripts for the settings page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handler for activation
        add_action('wp_ajax_h2piai_activate_product_insight', array($this, 'handle_activate_product_insight'));
    }

    /**
     * Enqueues admin scripts for the settings page.
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_h2piai_product_insight' !== $hook) {
            return;
        }
        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'h2_product_insight_admin_js', 
            plugins_url('../js/activation.js', __FILE__), 
            array('jquery'), 
            h2piai_PRODUCT_INSIGHT_VERSION, 
            true 
        );

        // Localize script to pass AJAX URL and nonce
        wp_localize_script('h2_product_insight_admin_js', 'h2piai_product_insight', array(
            'ajax_url' => esc_url(admin_url('admin-ajax.php')),
            'api_url'  => esc_url(h2piai_PRODUCT_INSIGHT_API_URL),
            'nonce'    => wp_create_nonce('h2piai_activate_product_insight_nonce')
        ));
    }

    /**
     * Enqueues custom admin styles for the settings page.
     */
    public function enqueue_admin_styles($hook) {
        if ('settings_page_h2piai_product_insight' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'h2_product_insight_admin_css', 
            plugins_url('../css/product-insight-style.css', __FILE__),
            array(),
            h2piai_PRODUCT_INSIGHT_VERSION
        );        
    }

    /**
     * Adds the settings page to the WordPress admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            esc_attr__('H2 Product Insight Settings', 'h2-product-insight'),
            esc_attr__('H2 Product Insight', 'h2-product-insight'),
            'manage_options',
            'h2piai_product_insight',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Initializes the plugin settings.
     */
    public function init_settings() {
        register_setting(
            'h2piai_product_insight_options_group', 
            'h2piai_product_insight_options', 
            'h2piai_product_insight_options_sanitize'
        );

        add_settings_section(
            'h2_product_insight_general_section',
            esc_html__('General Settings', 'h2-product-insight'),
            array($this, 'render_general_section'),
            'h2piai_product_insight'
        );

        // API Key field
        add_settings_field(
            'api_key',
            esc_html__('API Key', 'h2-product-insight'),
            array($this, 'render_api_key_field'),
            'h2piai_product_insight',
            'h2_product_insight_general_section'
        );

        // Chatbox Placement field
        add_settings_field(
            'chatbox_placement',
            esc_html__('Chatbox Placement', 'h2-product-insight'),
            array($this, 'render_chatbox_placement_field'),
            'h2piai_product_insight',
            'h2_product_insight_general_section'
        );

        // Custom CSS field
        add_settings_field(
            'custom_css',
            esc_html__('Custom CSS', 'h2-product-insight'),
            array($this, 'render_custom_css_field'),
            'h2piai_product_insight',
            'h2_product_insight_general_section'
        );
    }

    /**
     * Sanitizes the settings input.
     */
    public function sanitize($input) {
        if (!is_array($input)) {
            return array();
        }

        $sanitized_input = array();
        $this->invalid_fields = array(); 
        $existing_options = get_option('h2piai_product_insight_options', array());

        // Sanitize API Key
        if (isset($input['api_key']) && !empty($input['api_key'])) {
            $sanitized_input['api_key'] = sanitize_text_field($input['api_key']);
        } else {
            $this->invalid_fields[] = 'api_key';
            add_settings_error(
                'h2piai_product_insight_options_group',
                'invalid_api_key',
                esc_html__('API Key is required. Previous key retained.', 'h2-product-insight'),
                'error'
            );
            $sanitized_input['api_key'] = $existing_options['api_key'] ?? '';
        }

        // If any required fields are missing, retain existing values and stop validation
        if (!empty($this->invalid_fields)) {
            update_option('h2piai_product_insight_invalid_fields', $this->invalid_fields);
            return array_merge($existing_options, $sanitized_input);
        }

        // Validate API key with the external API
        $response = wp_remote_post(h2piai_PRODUCT_INSIGHT_API_URL . '/validate-api-key', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('api_key' => $sanitized_input['api_key'])),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            $this->invalid_fields = array_merge($this->invalid_fields, array('api_key'));
            add_settings_error(
                'h2piai_product_insight_options_group',
                'api_request_failed',
                sprintf(
                    esc_html__('API validation request failed: %s. Previous values retained.', 'h2-product-insight'),
                    esc_html($response->get_error_message())
                ),
                'error'
            );
            $sanitized_input['api_key'] = $existing_options['api_key'];
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body, true);

            if ($response_code !== 200 || empty($result['success'])) {
                $this->invalid_fields = array_merge($this->invalid_fields, array('api_key'));
                $message = !empty($result['message']) 
                    ? sanitize_text_field($result['message'])
                    : esc_html__('API validation failed. Previous values retained.', 'h2-product-insight');
                add_settings_error(
                    'h2piai_product_insight_options_group',
                    'api_validation_failed',
                    $message,
                    'error'
                );
                $sanitized_input['api_key'] = $existing_options['api_key'];
            } else {
                add_settings_error(
                    'h2piai_product_insight_options_group',
                    'api_validation_success',
                    esc_html__('API connection validated successfully.', 'h2-product-insight'),
                    'success'
                );
            }
        }

        // Sanitize optional fields
        if (isset($input['custom_css'])) {
            $sanitized_input['custom_css'] = h2piai_Product_Insight_Sanitizer::sanitize_custom_css($input['custom_css']);
        } else {
            $sanitized_input['custom_css'] = $existing_options['custom_css'] ?? '';
        }

        if (isset($input['chatbox_placement'])) {
            $sanitized_input['chatbox_placement'] = sanitize_text_field($input['chatbox_placement']);
        } else {
            $sanitized_input['chatbox_placement'] = $existing_options['chatbox_placement'] ?? 'after_add_to_cart';
        }

        // Update invalid fields option for styling
        update_option('h2piai_product_insight_invalid_fields', $this->invalid_fields);

        return array_merge($existing_options, $sanitized_input);
    }

    /**
     * Renders the settings page.
     */
    public function render_settings_page() {
        $this->options = get_option('h2piai_product_insight_options', array());
        ?>
        <div class="h2piai-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>
                <a href="<?php echo esc_url('https://2human.ai/product-insight'); ?>" target="_blank">
                    <?php echo esc_html__('PRODUCT INSIGHT AI HOME', 'h2-product-insight'); ?>
                </a>
            </p>
            
            <?php if (h2piai_ACTIVATION_TEST || empty($this->options['api_key'])) : ?>
                <form id="h2piai_activate_product_insight" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post">
                    <?php wp_nonce_field('h2piai_activate_product_insight_nonce', '_wpnonce'); ?>
                    <input type="hidden" name="action" value="h2piai_activate_product_insight" />
                    <button type="submit" class="button button-primary" id="h2_activate_button">
                        <?php echo esc_html__('Activate Product Insight AI', 'h2-product-insight'); ?>
                        <span class="spinner" style="display:none;"></span>
                    </button>
                </form>
                <div id="h2piai-activation-message" class="notice" style="display:none;"></div>
            <?php else : ?>
                <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php
                    settings_fields('h2piai_product_insight_options_group');
                    do_settings_sections('h2piai_product_insight');
                    submit_button();
                ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
        settings_errors('h2piai_product_insight_options_group');
        delete_option('h2piai_product_insight_invalid_fields');
    }

    // Placeholder for render methods (to be implemented as needed)
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure the general settings for H2 Product Insight.', 'h2-product-insight') . '</p>';
    }

    public function render_api_key_field() {
        $value = isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : '';
        echo '<input type="text" name="h2piai_product_insight_options[api_key]" value="' . $value . '" class="regular-text" />';
    }

    public function render_chatbox_placement_field() {
        $value = isset($this->options['chatbox_placement']) ? esc_attr($this->options['chatbox_placement']) : 'after_add_to_cart';
        // Define multiple placement options
        $options = array(
            'before_add_to_cart'    => esc_html__('Before Add to Cart', 'h2-product-insight'),
            'after_add_to_cart'     => esc_html__('After Add to Cart', 'h2-product-insight'),
            'after_product_summary' => esc_html__('After Product Summary', 'h2-product-insight'),
            'after_product_meta'    => esc_html__('After Product Meta', 'h2-product-insight'),
            'after_single_product'  => esc_html__('After Single Product', 'h2-product-insight'),
            'in_product_tabs'       => esc_html__('In Product Tabs', 'h2-product-insight')
        );
        
        echo '<select name="h2piai_product_insight_options[chatbox_placement]">';
        foreach ($options as $key => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($value, $key, false), esc_html($label));
        }
        echo '</select>';
    }

    public function render_custom_css_field() {
        $value = isset($this->options['custom_css']) ? esc_textarea($this->options['custom_css']) : '';
        echo '<textarea name="h2piai_product_insight_options[custom_css]" rows="5" cols="50" class="large-text">' . $value . '</textarea>';
    }

    // Placeholder for AJAX handler (to be implemented as needed)
    public function handle_activate_product_insight() {
        // Implement AJAX activation logic here
    }
}

// Global helper sanitization function
if (!function_exists('h2piai_product_insight_options_sanitize')) {
    function h2piai_product_insight_options_sanitize($input) {
        $instance = new h2piai_Product_Insight_Settings();
        return $instance->sanitize($input);
    }
}