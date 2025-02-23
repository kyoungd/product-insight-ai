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

/**
 * Sanitizes the settings input.
 *
 * @param array $input The input settings array.
 * @return array Sanitized settings.
 */
function TwoHumanAI_Product_Insight_Settings_sanitize($input) {
    if (!is_array($input)) {
        return array();
    }

    $sanitized_input = array();
    $invalid_fields = array(); 
    $existing_options = get_option('TwoHumanAI_product_insight_options', array());

    // Sanitize API Key
    if (isset($input['api_key']) && !empty($input['api_key'])) {
        $sanitized_input['api_key'] = sanitize_text_field($input['api_key']);
    } else {
        $invalid_fields[] = 'api_key';
        add_settings_error(
            'TwoHumanAI_product_insight_options_group',
            'invalid_api_key',
            esc_html__('API Key is required. Previous key retained.', 'h2-product-insight'),
            'error'
        );
        $sanitized_input['api_key'] = $existing_options['api_key'] ?? '';
    }

    // If any required fields are missing, retain existing values and stop validation
    if (!empty($invalid_fields)) {
        update_option('TwoHumanAI_product_insight_invalid_fields', $invalid_fields);
        return array_merge($existing_options, $sanitized_input);
    }

    // Validate API key with the external API
    $response = wp_remote_post(TwoHumanAI_PRODUCT_INSIGHT_API_URL . '/validate-api-key', array(
        'headers' => array('Content-Type' => 'application/json'),
        'body'    => wp_json_encode(array('api_key' => $sanitized_input['api_key'])),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        $invalid_fields = array_merge($invalid_fields, array('api_key'));
        add_settings_error(
            'TwoHumanAI_product_insight_options_group',
            'api_request_failed',
            sprintf(
                /* translators: %s: error message from HTTP REST API request */
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
            $invalid_fields = array_merge($invalid_fields, array('api_key'));
            $message = !empty($result['message']) 
                ? sanitize_text_field($result['message'])
                : esc_html__('API validation failed. Previous values retained.', 'h2-product-insight');
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'api_validation_failed',
                $message,
                'error'
            );
            $sanitized_input['api_key'] = $existing_options['api_key'];
        } else {
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'api_validation_success',
                esc_html__('API connection validated successfully.', 'h2-product-insight'),
                'success'
            );
        }
    }

    // Sanitize optional fields
    if (isset($input['custom_css'])) {
        $sanitized_input['custom_css'] = TwoHumanAI_Product_Insight_Sanitizer::sanitize_custom_css($input['custom_css']);
    } else {
        $sanitized_input['custom_css'] = $existing_options['custom_css'] ?? '';
    }

    if (isset($input['chatbox_placement'])) {
        $sanitized_input['chatbox_placement'] = sanitize_text_field($input['chatbox_placement']);
    } else {
        $sanitized_input['chatbox_placement'] = $existing_options['chatbox_placement'] ?? 'after_add_to_cart';
    }

    // Update invalid fields option for styling
    update_option('TwoHumanAI_product_insight_invalid_fields', $invalid_fields);

    return array_merge($existing_options, $sanitized_input);
}


class TwoHumanAI_Product_Insight_Settings {
    private $options;
    private $invalid_fields = array();

    /**
     * Constructor. Registers hooks for settings and activation.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));

        // Retrieve invalid fields from the previous submission
        $this->invalid_fields = get_option('TwoHumanAI_product_insight_invalid_fields', array());

        // Enqueue the custom CSS and scripts for the settings page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handler for activation
        add_action('wp_ajax_TwoHumanAI_activate_product_insight', array($this, 'handle_activate_product_insight'));
    }

    /**
     * Enqueues admin JavaScript for the settings page.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_TwoHumanAI_product_insight' !== $hook) {
            return;
        }

        // Register admin script with defer
        wp_register_script(
            'h2-product-insight-admin-js',
            plugins_url('../js/activation.js', __FILE__),
            array('jquery'), // Dependency on jQuery
            TwoHumanAI_PRODUCT_INSIGHT_VERSION,
            array(
                'in_footer' => true,
                'strategy'  => 'defer' // Defer loading (WP 6.3+)
            )
        );

        // Localize script data
        wp_localize_script('h2-product-insight-admin-js', 'TwoHumanAI_product_insight', array(
            'ajax_url' => esc_url(admin_url('admin-ajax.php')),
            'api_url'  => esc_url(TwoHumanAI_PRODUCT_INSIGHT_API_URL),
            'nonce'    => wp_create_nonce('TwoHumanAI_activate_product_insight_nonce') // Changed nonce string here
        ));

        // Enqueue the script
        wp_enqueue_script('h2-product-insight-admin-js');
    }

    /**
     * Enqueues custom admin styles for the settings page.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_admin_styles($hook) {
        if ('settings_page_TwoHumanAI_product_insight' !== $hook) {
            return;
        }

        // Register and enqueue admin style
        wp_register_style(
            'h2-product-insight-admin-css',
            plugins_url('../css/product-insight-style.css', __FILE__),
            array(), // No dependencies
            TwoHumanAI_PRODUCT_INSIGHT_VERSION
        );
        wp_enqueue_style('h2-product-insight-admin-css');
    }

    /**
     * Adds the settings page to the WordPress admin menu.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_options_page(
            esc_attr__('H2 Product Insight Settings', 'h2-product-insight'),
            esc_attr__('H2 Product Insight', 'h2-product-insight'),
            'manage_options',
            'TwoHumanAI_product_insight',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Registers and initializes plugin settings.
     *
     * @return void
     */
    public function init_settings() {
        register_setting(
            'TwoHumanAI_product_insight_options_group', 
            'TwoHumanAI_product_insight_options', 
            'TwoHumanAI_Product_Insight_Settings_sanitize'  // Reference the class method directly
        );

        add_settings_section(
            'TwoHumanAI_product_insight_general_section',
            esc_html__('General Settings', 'h2-product-insight'),
            array($this, 'render_general_section'),
            'TwoHumanAI_product_insight'
        );

        // API Key field
        add_settings_field(
            'api_key',
            esc_html__('API Key', 'h2-product-insight'),
            array($this, 'render_api_key_field'),
            'TwoHumanAI_product_insight',
            'TwoHumanAI_product_insight_general_section'
        );

        // Chatbox Placement field
        add_settings_field(
            'chatbox_placement',
            esc_html__('Chatbox Placement', 'h2-product-insight'),
            array($this, 'render_chatbox_placement_field'),
            'TwoHumanAI_product_insight',
            'TwoHumanAI_product_insight_general_section'
        );

        // Custom CSS field
        add_settings_field(
            'custom_css',
            esc_html__('Custom CSS', 'h2-product-insight'),
            array($this, 'render_custom_css_field'),
            'TwoHumanAI_product_insight',
            'TwoHumanAI_product_insight_general_section'
        );
    }

    /**
     * Renders the settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        $this->options = get_option('TwoHumanAI_product_insight_options', array());
        ?>
        <div class="TwoHumanAI-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>
                <a href="<?php echo esc_url('https://2human.ai/product-insight'); ?>" target="_blank">
                    <?php echo esc_html__('PRODUCT INSIGHT AI HOME', 'h2-product-insight'); ?>
                </a>
            </p>
            
            <?php if (TwoHumanAI_ACTIVATION_TEST || empty($this->options['api_key'])) : ?>
                <form id="TwoHumanAI_activate_product_insight" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post">
                    <?php wp_nonce_field('TwoHumanAI_activate_product_insight_nonce', '_wpnonce'); // Changed nonce string here ?>
                    <input type="hidden" name="action" value="TwoHumanAI_activate_product_insight" />
                    <button type="submit" class="button button-primary" id="h2_activate_button">
                        <?php echo esc_html__('Activate Product Insight AI', 'h2-product-insight'); ?>
                        <span class="spinner" style="display:none;"></span>
                    </button>
                </form>
                <div id="TwoHumanAI-activation-message" class="notice" style="display:none;"></div>
            <?php else : ?>
                <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post">
                <?php
                    settings_fields('TwoHumanAI_product_insight_options_group');
                    do_settings_sections('TwoHumanAI_product_insight');
                    submit_button();
                ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
        settings_errors('TwoHumanAI_product_insight_options_group');
        delete_option('TwoHumanAI_product_insight_invalid_fields');
    }

    /**
     * Renders the general settings section.
     *
     * @return void
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure the general settings for H2 Product Insight.', 'h2-product-insight') . '</p>';
    }

    /**
     * Renders the API Key input field.
     *
     * @return void
     */
    public function render_api_key_field() {
        $value = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        echo '<input type="text" name="TwoHumanAI_product_insight_options[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    /**
     * Renders the Chatbox Placement dropdown field.
     *
     * @return void
     */
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
        
        echo '<select name="TwoHumanAI_product_insight_options[chatbox_placement]">';
        foreach ($options as $key => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($value, $key, false), esc_html($label));
        }
        echo '</select>';
    }

    /**
     * Renders the Custom CSS textarea field.
     *
     * @return void
     */
    public function render_custom_css_field() {
        $value = isset($this->options['custom_css']) ? $this->options['custom_css'] : '';
        echo '<textarea name="' . esc_attr('TwoHumanAI_product_insight_options[custom_css]') . '" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    }

    /**
     * Handles the activation of Product Insight AI via AJAX.
     *
     * @return void JSON response on success or error.
     */
    public function handle_activate_product_insight() {
        ob_clean(); // Clear any output before sending JSON
        check_ajax_referer('TwoHumanAI_activate_product_insight_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'permission_denied',
                esc_html__('Permission denied.', 'h2-product-insight'),
                'error'
            );
            wp_send_json_error(array('message' => get_settings_errors('TwoHumanAI_product_insight_options_group')));
            return;
        }

        $api_url = esc_url_raw(TwoHumanAI_PRODUCT_INSIGHT_API_URL . '/new-registration');
        if (empty($api_url)) {
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'invalid_api_url',
                esc_html__('Invalid API URL.', 'h2-product-insight'),
                'error'
            );
            wp_send_json_error(array('message' => get_settings_errors('TwoHumanAI_product_insight_options_group')));
            return;
        }

        // Send API key as empty string to get a new key
        $response = wp_remote_post($api_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('api_key' => '')),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'activation_request_failed',
                sprintf(
                    /* translators: %s: error message from HTTP REST API request */
                    esc_html__('API activation request failed: %s.', 'h2-product-insight'),
                    esc_html($response->get_error_message())
                ),
                'error'
            );
            wp_send_json_error(array('message' => get_settings_errors('TwoHumanAI_product_insight_options_group')));
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if (!is_array($result)) {
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'invalid_json',
                __('Invalid JSON response received.', 'h2-product-insight')
            );
            wp_send_json_error(__('Invalid JSON response received.', 'h2-product-insight'));
            return;
        }

        if ($response_code !== 200 || empty($result['api_key'])) {
            $error_message = isset($result['message']) && !empty($result['message'])
                ? esc_html($result['message'])
                : esc_html__('API activation failed.', 'h2-product-insight');
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'api_activation_failed',
                $error_message,
                'error'
            );
            wp_send_json_error(array('message' => get_settings_errors('TwoHumanAI_product_insight_options_group')));
            return;
        }

        // Create options array directly (bypass sanitize for initial activation)
        $options = array(
            'api_key'           => sanitize_text_field($result['api_key']),
            'custom_template'   => '',
            'custom_css'        => '',
            'chatbox_placement' => 'after_add_to_cart'
        );

        // Delete existing option first
        delete_option('TwoHumanAI_product_insight_options');

        // Add new option
        $update_success = update_option('TwoHumanAI_product_insight_options', $options, false);
        error_log('------------------ Update success: ' . var_export($update_success, true)); // Debug]

        if ($update_success && !empty($options['api_key'])) {
            wp_send_json_success(array(
                'message' => esc_html__('Product Insight AI activated successfully!', 'h2-product-insight')
            ));
        } else {
            add_settings_error(
                'TwoHumanAI_product_insight_options_group',
                'failed_to_save_api_key',
                esc_html__('Failed to save API key.', 'h2-product-insight'),
                'error'
            );
            wp_send_json_error(array('message' => get_settings_errors('TwoHumanAI_product_insight_options_group')));
        }
    }
}

