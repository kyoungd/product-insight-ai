
<?php
/**
 * Uninstall script for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 * @file       uninstall.php
 */

 // If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// // Delete plugin options
// delete_option('TwoHumanAI_product_insight_options');
// delete_option('TwoHumanAI_product_insight_invalid_fields');
// Delete transients or other plugin-specific data if used
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'TwoHumanAI_%'");

// Clean up any additional options and custom tables if necessary