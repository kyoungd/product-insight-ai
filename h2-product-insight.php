<?php
/**
 * Plugin Name: H2 Product Insight
 * Plugin URI: https://2human.ai/wp-content/uploads/2024/11/h2-product-insight.zip
 * Description: AI-powered Product Insight for WooCommerce
 * Version: 1.2
 * Author: Young Kwon
 * Author URI: https://2human.ai
 * License: GPL-2.0-or-later                  // Added license information
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: h2-product-insight
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 6.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'includes/constants.php';

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

// Add a "Settings" link to the plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'h2_product_insight_plugin_action_links');

function h2_product_insight_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=h2_product_insight') . '">' . __('Settings', 'h2-product-insight') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}