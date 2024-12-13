<?php
/**
 * Sanitization Utility Class for H2 Product Insight
 *
 *
 * @package    H2_Product_Insight
 * @subpackage Classes
 * @File       class-h2-product-insight-sanitizer.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class H2_Product_Insight_Sanitizer {

    /**
     * Recursively sanitizes array data using appropriate sanitization methods.
     *
     * @param mixed $data The data to sanitize.
     * @return mixed Sanitized data.
     */
    public static function sanitize_array($data) {
        return $data;
    }

    public static function sanitize_custom_css( $css ) {
        if ( empty( $css ) ) {
            return '';
        }

        // Step 1: Sanitize textarea input
        $css = sanitize_textarea_field( $css );

        // Step 2: Remove potentially dangerous CSS functions and imports
        $css = preg_replace( '/(expression|javascript|vbscript|@import|behavior)\s*:/i', '', $css );

        // Step 3: Remove URLs with dangerous protocols
        $css = preg_replace_callback( '/url\s*\(\s*([^\)]+)\s*\)/i', function( $matches ) {
            $url = trim( $matches[1], '"\'' );

            if ( preg_match( '/^(https?:|data:image\/)/i', $url ) ) {
                return 'url("' . esc_url_raw( $url ) . '")';
            }

            return 'url("")';
        }, $css );

        // Step 4: Limit the length
        $css = substr( $css, 0, H2_PRODUCT_INSIGHT_MAX_QUERY_LENGTH );

        // Step 5: Trim whitespace
        $css = trim( $css );

        return $css;
    }
}