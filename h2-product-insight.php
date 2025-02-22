<?php
/**
 * Plugin Name: H2 Product Insight
 * Plugin URI: https://2human.ai/product-insight/
 * Description: AI-powered Product Insight for WooCommerce products. Adds an intelligent chatbot that helps customers understand your products better.
 * Version: 1.6
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Young Kwon
 * Author URI: https://2human.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: h2-product-insight
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 6.0
 *
 * @package H2_Product_Insight
 * @file h2-product-insight.php
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constant for the plugin's base path
if (!defined('H2_PRODUCT_INSIGHT_PATH')) {
    define('H2_PRODUCT_INSIGHT_PATH', plugin_dir_path(__FILE__));
}

require_once H2_PRODUCT_INSIGHT_PATH . 'includes/constants.php';

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Include the main plugin class
require_once H2_PRODUCT_INSIGHT_PATH . 'includes/class-h2-product-insight.php';
require_once H2_PRODUCT_INSIGHT_PATH . 'includes/class-h2-product-insight-sanitizer.php';

// Initialize the plugin
function TwoHumanAI_product_insight_initialization() {
    load_plugin_textdomain('h2-product-insight', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    new TwoHumanAI_Product_Insight_Main();
}
add_action('plugins_loaded', 'TwoHumanAI_product_insight_initialization');

// Add a "Settings" link to the plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'TwoHumanAI_product_insight_plugin_action_links');

function TwoHumanAI_product_insight_plugin_action_links($links) {
    $settings_url = admin_url('options-general.php?page=TwoHumanAI_product_insight');
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url($settings_url),
        esc_html__('Settings', 'h2-product-insight')
    );
    array_unshift($links, $settings_link);
    return $links;
}