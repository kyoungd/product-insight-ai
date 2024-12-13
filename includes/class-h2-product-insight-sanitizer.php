
<?php
/**
 * Sanitization Utility Class for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @subpackage Classes
 */

if (!defined('ABSPATH')) {
    exit;
}

class H2_Product_Insight_Sanitizer {
    /**
     * Sanitizes HTML content
     */
    public static function sanitize_html($content, $strict = false) {
        if (empty($content)) {
            return '';
        }

        $allowed_html = $strict ? H2_PRODUCT_INSIGHT_ALLOWED_TAGS_STRICT : H2_PRODUCT_INSIGHT_ALLOWED_HTML_TAGS;
        $content = wp_kses($content, $allowed_html);
        return substr($content, 0, H2_PRODUCT_INSIGHT_MAX_MESSAGE_LENGTH);
    }

    /**
     * Sanitizes URL with additional checks
     */
    public static function sanitize_url($url) {
        if (empty($url)) {
            return '';
        }

        $clean_url = esc_url_raw($url);
        
        // Additional validation
        $parts = wp_parse_url($clean_url);
        if (!$parts || !isset($parts['host']) || !isset($parts['scheme'])) {
            return '';
        }

        if (!in_array($parts['scheme'], array('http', 'https'))) {
            return '';
        }

        if (strlen($clean_url) > H2_PRODUCT_INSIGHT_MAX_QUERY_LENGTH) {
            return '';
        }

        return $clean_url;
    }

    /**
     * Sanitizes array data recursively
     */
    public static function sanitize_array($data) {
        if (!is_array($data)) {
            return self::sanitize_field($data);
        }

        $sanitized = array();
        foreach ($data as $key => $value) {
            $clean_key = sanitize_key($key);
            $sanitized[$clean_key] = self::sanitize_array($value);
        }

        return $sanitized;
    }

    /**
     * Sanitizes object data by converting to array and back
     */
    public static function sanitize_object($object) {
        if (!is_object($object)) {
            return $object;
        }

        // Convert to array and remove potentially unsafe properties
        $array = (array) $object;
        $unsafe_props = array('__proto__', 'constructor', 'prototype', 'eval');
        
        foreach ($unsafe_props as $prop) {
            unset($array[$prop]);
        }

        // Sanitize remaining properties
        $clean_array = self::sanitize_array($array);

        // Convert back to object if needed
        return (object) $clean_array;
    }

    /**
     * Sanitizes individual field based on type
     */
    public static function sanitize_field($value) {
        if (is_string($value)) {
            return self::sanitize_string($value);
        }

        if (is_numeric($value)) {
            return is_float($value) ? (float) $value : absint($value);
        }

        if (is_bool($value)) {
            return (bool) $value;
        }

        if (is_array($value)) {
            return self::sanitize_array($value);
        }

        if (is_object($value)) {
            return self::sanitize_object($value);
        }

        return '';
    }

    /**
     * Sanitizes string with additional checks
     */
    private static function sanitize_string($string) {
        $string = sanitize_text_field($string);
        
        // Remove potentially harmful patterns
        foreach (H2_PRODUCT_INSIGHT_SECURITY_PATTERNS as $pattern) {
            $string = preg_replace($pattern, '', $string);
        }

        // Check for invalid inputs
        foreach (H2_PRODUCT_INSIGHT_INVALID_INPUTS as $pattern) {
            if (preg_match($pattern, $string)) {
                return '';
            }
        }

        return substr($string, 0, H2_PRODUCT_INSIGHT_MAX_MESSAGE_LENGTH);
    }

    // For translatable strings
    public static function sanitize_translation($string) {
        return self::sanitize_field(__($string, 'h2-product-insight'));
    }

    // For HTML attributes
    public static function sanitize_attribute($value) {
        return esc_attr(self::sanitize_field($value));
    }

}