<?php
/**
 * Plugin Name: H2 Product Insight
 * Plugin URI: https://example.com/h2-product-insight
 * Description: AI-powered Product Insight for WooCommerce
 * Version: 1.2
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: h2-product-insight
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 6.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('H2_PRODUCT_INSIGHT_VERSION', '1.2.1');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Include the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-h2-product-insight.php';

// Initialize the plugin
function h2_product_insight_init() {
    new H2_Product_Insight();
}
add_action('plugins_loaded', 'h2_product_insight_init');

// Enqueue scripts and styles
function h2_product_insight_enqueue_scripts() {
    wp_enqueue_style('product-insight-style', plugin_dir_url(__FILE__) . 'css/product-insight-style.css', array(), H2_PRODUCT_INSIGHT_VERSION);
    wp_enqueue_script('h2-product-insight-script', plugin_dir_url(__FILE__) . 'js/h2-product-insight-script.js', array('jquery'), H2_PRODUCT_INSIGHT_VERSION, true);
}
add_action('wp_enqueue_scripts', 'h2_product_insight_enqueue_scripts');

// Add a "Settings" link to the plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'h2_product_insight_plugin_action_links');

function h2_product_insight_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=h2_product_insight') . '">' . __('Settings', 'h2-product-insight') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}