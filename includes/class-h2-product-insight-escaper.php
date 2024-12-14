<?php
/**
 * Escaping Utility Class for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @subpackage Classes
 */

if (!defined('ABSPATH')) {
    exit;
}

class H2_Product_Insight_Escaper {

    /**
     * Escapes translatable string
     */
    public static function escape_translation($string) {
        return esc_html__($string, 'h2-product-insight');
    }

    public static function escape_translation_attribute($string) {
        return esc_attr__($string, 'h2-product-insight');
    }
}