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

class H2_Product_Insight_Renderer {

    /**
     * Renders the chatbox based on the custom or default template.
     *
     * @return string The rendered chatbox HTML.
     */
    public static function render() {
        $options = get_option('h2_product_insight_options');
        $custom_css = isset($options['custom_css']) ? $options['custom_css'] : '';

        $output = '';

        // Include custom CSS if provided
        if (!empty($custom_css)) {
            $output .= '<style>' . wp_strip_all_tags($custom_css) . '</style>';
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
                <input type="text" id="product-insight-aiuser-input" placeholder="<?php echo esc_attr__('I am Edward, your AI. Ask me anything...', 'h2'); ?>" aria-label="<?php echo esc_attr__('Chat Input', 'h2'); ?>">
                <div id="product-insight-ailoading" style="display: none;"><?php echo esc_html__('Initializing...', 'h2'); ?></div>
            </div>
            <div id="product-insight-ailast-reply-container" style="display: none;"></div>
            <div id="product-insight-aimessages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
