<?php
/**
 * Renderer Class for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 * @file       includes/product-insight-renderer.php
 */

// File: includes/product-insight-renderer.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . './class-h2-product-insight-sanitizer.php';

class H2PIAI_Product_Insight_Renderer {

    /**
     * Renders the chatbox based on the custom or default template.
     *
     * @return string The rendered chatbox HTML.
     */
    public static function render() {
        $output = self::render_default_template();
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
        <div id="h2piai-product-insight-aichatbox">
            <div id="h2piai-product-insight-aiinput">
                <input type="text" 
                       id="h2piai-product-insight-aiuser-input" 
                       placeholder="<?php echo esc_attr__('Ask about the product...', 'h2-product-insight'); ?>" 
                       aria-label="<?php echo esc_attr__('Chat Input', 'h2-product-insight'); ?>"
                       maxlength="1000"
                       pattern="[^<>]*"
                >
                <div id="h2piai-product-insight-ailoading" style="display: none;">
                    <?php echo esc_html__('Initializing...', 'h2-product-insight'); ?>
                </div>
            </div>
            <div id="h2piai-product-insight-ailast-reply-container" style="display: none;"></div>
            <div id="product-insight-aimessages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
}
