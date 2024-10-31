<?php
// File: includes/product-insight-settings.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class H2_Product_Insight_Settings {
    private $options;
    private $invalid_fields = array();

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));

        // Retrieve invalid fields from the previous submission
        $this->invalid_fields = get_option('h2_product_insight_invalid_fields', array());

        // Enqueue the custom CSS for the settings page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        // Enqueue jquery for the settings page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }


    public function enqueue_admin_scripts($hook) {
        if ('settings_page_h2_product_insight' !== $hook) {
            return;
        }
        wp_enqueue_script('jquery');
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

        // API URL field
        add_settings_field(
            'api_url',
            __('API URL', 'h2'),
            array($this, 'render_api_url_field'),
            'h2_product_insight_settings',
            'h2_product_insight_general_section'
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

        // Custom Template field
        add_settings_field(
            'custom_template',
            __('Custom Template', 'h2'),
            array($this, 'render_custom_template_field'),
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

    /**
     * Sanitizes the input options.
     *
     * @param array $input The input options.
     * @return array The sanitized options.
     */
    public function sanitize($input) {
        $sanitized_input = array();
        $this->invalid_fields = array(); // Reset invalid fields

        // Sanitize API URL
        if (isset($input['api_url']) && strlen($input['api_url']) > 2) {
            $sanitized_input['api_url'] = esc_url_raw($input['api_url']);
            if (empty($sanitized_input['api_url']) || !filter_var($sanitized_input['api_url'], FILTER_VALIDATE_URL)) {
                $this->invalid_fields[] = 'api_url';
                add_settings_error('h2_product_insight_settings', 'invalid_api_url', __('API URL is invalid.', 'h2'));
            }
        } else {
            $this->invalid_fields[] = 'api_url';
            add_settings_error('h2_product_insight_settings', 'invalid_api_url', __('API URL is required. The default url is set,', 'h2'));
        }

        // Sanitize API Key
        if (isset($input['api_key'])) {
            $sanitized_input['api_key'] = sanitize_text_field($input['api_key']);
            if (empty($sanitized_input['api_key'])) {
                $this->invalid_fields[] = 'api_key';
                add_settings_error('h2_product_insight_settings', 'invalid_api_key', __('API Key is required.', 'h2'));
            }
        } else {
            $this->invalid_fields[] = 'api_key';
            add_settings_error('h2_product_insight_settings', 'invalid_api_key', __('API Key is required.', 'h2'));
        }

        // If any of the top 2 fields are invalid, stop further processing
        if (!empty($this->invalid_fields)) {
            update_option('h2_product_insight_invalid_fields', $this->invalid_fields);
            return $sanitized_input;
        }

        // Validate API URL and API Key
        $validate_api_url = preg_replace('#/query$#', '/validate-api-key', $sanitized_input['api_url']);
        if ($validate_api_url === $sanitized_input['api_url']) {
            // If '/query' was not found at the end, append '/validate-api-key'
            $validate_api_url = rtrim($sanitized_input['api_url'], '/') . '/validate-api-key';
        }

        // Prepare the body
        $body = array(
            'api_key' => $sanitized_input['api_key'],
        );

        // Make the HTTP POST request
        $response = wp_remote_post($validate_api_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            $this->invalid_fields = array_merge($this->invalid_fields, array('api_url', 'api_key'));
            add_settings_error('h2_product_insight_settings', 'api_request_failed', __('API validation request failed.', 'h2'));
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body, true);

            if ($response_code !== 200 || empty($result['success'])) {
                $this->invalid_fields = array_merge($this->invalid_fields, array('api_url', 'api_key'));
                $message = !empty($result['message']) ? $result['message'] : __('API validation failed.', 'h2');
                add_settings_error('h2_product_insight_settings', 'api_validation_failed', $message);
            }
        }

        // Sanitize custom template
        if (isset($input['custom_template'])) {
            $sanitized_input['custom_template'] = wp_kses_post($input['custom_template']);
        }

        // Sanitize custom CSS
        if (isset($input['custom_css'])) {
            $sanitized_input['custom_css'] = wp_strip_all_tags($input['custom_css']);
        }

        // Sanitize Chatbox Placement
        if (isset($input['chatbox_placement'])) {
            $sanitized_input['chatbox_placement'] = sanitize_text_field($input['chatbox_placement']);
        }

        // Update invalid fields option for styling
        update_option('h2_product_insight_invalid_fields', $this->invalid_fields);

        return $sanitized_input;
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
            <form action="options.php" method="post">
            <?php
                settings_fields('h2_product_insight_settings');
                do_settings_sections('h2_product_insight_settings');
                submit_button();
            ?>
            </form>
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

    public function render_api_url_field() {
        $default_api_url = 'https://2human.ai/wp-json/my-first-plugin/v1/query';
        $value = isset($this->options['api_url']) ? $this->options['api_url'] : $default_api_url;
        $error_class = in_array('api_url', $this->invalid_fields) ? 'has-error' : '';
        echo '<div class="h2-input-wrapper ' . $error_class . '">';
        echo '<input type="text" id="api_url" name="h2_product_insight_options[api_url]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<span class="h2-error-indicator"></span>';
        echo '</div>';
    }
    
    public function render_api_key_field() {
        $value = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $error_class = in_array('api_key', $this->invalid_fields) ? 'has-error' : '';
        echo '<div class="h2-input-wrapper ' . $error_class . '">';
        echo '<input type="password" id="api_key" name="h2_product_insight_options[api_key]" value="' . esc_attr($value) . '" class="regular-text">';
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
     * Renders the Custom Template field with placeholder documentation and live preview.
     */
    public function render_custom_template_field() {
        $value = isset($this->options['custom_template']) ? $this->options['custom_template'] : '';
        echo '<textarea id="custom_template" name="h2_product_insight_options[custom_template]" rows="10" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter your custom HTML template here. Use the placeholders listed below.', 'h2') . '</p>';
        echo '<p class="description">' . esc_html__('Available placeholders:', 'h2') . '</p>';
        echo '<ul>';
        echo '<li><code>{input_field}</code> - ' . esc_html__('The input field for user queries.', 'h2') . '</li>';
        echo '<li><code>{loading_indicator}</code> - ' . esc_html__('The loading indicator element.', 'h2') . '</li>';
        echo '<li><code>{last_reply}</code> - ' . esc_html__('Container for the last AI reply.', 'h2') . '</li>';
        echo '</ul>';
        echo '<h3>' . esc_html__('Live Preview:', 'h2') . '</h3>';
        echo '<div id="custom_template_preview" style="border: 1px solid #ccc; padding: 10px; margin-top: 10px;"></div>';
        echo '
        <script>
        (function($){
            function updatePreview() {
                var template = $("#custom_template").val();
                var placeholders = {
                    "{input_field}": "<input type=\'text\' placeholder=\'I am Edward, your AI. Ask me anything...\' />",
                    "{loading_indicator}": "<div>Initializing...</div>",
                    "{last_reply}": "<div>Last reply will appear here.</div>",
                };
                for (var key in placeholders) {
                    template = template.replace(new RegExp(key, "g"), placeholders[key]);
                }
                $("#custom_template_preview").html(template);
            }
            $(document).ready(function(){
                $("#custom_template").on("input", updatePreview);
                updatePreview(); // Initial call
            });
        })(jQuery);
        </script>
        ';
    }

    /**
     * Renders the Custom CSS field.
     */
    public function render_custom_css_field() {
        $value = isset($this->options['custom_css']) ? $this->options['custom_css'] : '';
        echo '<textarea id="custom_css" name="h2_product_insight_options[custom_css]" rows="10" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter any custom CSS to style the chatbox.', 'h2') . '</p>';
    }
}