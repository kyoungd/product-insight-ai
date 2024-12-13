<?php
/**
 * Renderer Class for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 */

// File: includes/product-insight-renderer.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . './class-h2-product-insight-sanitizer.php';

class H2_Product_Insight_Renderer {

    /**
     * Renders the chatbox based on the custom or default template.
     *
     * @return string The rendered chatbox HTML.
     */
    public static function render() {
        $options = H2_Product_Insight_Sanitizer::sanitize_array(
            get_option('h2_product_insight_options', array())
        );
        $custom_css = isset($options['custom_css']) ? $options['custom_css'] : '';
        
        // Enhanced CSS sanitization
        $custom_css = H2_Product_Insight_Sanitizer::sanitize_html($custom_css);

        $output = '';

        // Include custom CSS if provided
        if (!empty($custom_css)) {
            $output .= '<style>' . $custom_css . '</style>';
        }

        $output .= self::render_default_template();

        return $output;
    }

    // Remove the render_custom_template method
    /*
    private static function render_custom_template($template) {
        // ...method code...
    }
    */

    /**
     * Renders the default chatbox template.
     *
     * @return string The default template HTML.
     */
    private static function render_default_template() {
        ob_start();
        ?>
        <div id="product-insight-aichatbox">
            <div id="product-insight-aiinput">
                <input type="text" 
                       id="product-insight-aiuser-input" 
                       placeholder="<?php echo H2_Product_Insight_Sanitizer::sanitize_field(__('Ask about the product...','h2-product-insight')); ?>" 
                       aria-label="<?php echo H2_Product_Insight_Sanitizer::sanitize_field(__('Chat Input','h2-product-insight')); ?>"
                       maxlength="1000"
                       pattern="[^<>]*"
                >
                <div id="product-insight-ailoading" style="display: none;"><?php echo H2_Product_Insight_Sanitizer::sanitize_field(__('Initializing...','h2-product-insight')); ?></div>
            </div>
            <div id="product-insight-ailast-reply-container" style="display: none;"></div>
            <div id="product-insight-aimessages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
