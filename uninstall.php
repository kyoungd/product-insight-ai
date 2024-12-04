
<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('h2_product_insight_options');
delete_option('h2_product_insight_invalid_fields');

// Clean up any additional options and custom tables if necessary