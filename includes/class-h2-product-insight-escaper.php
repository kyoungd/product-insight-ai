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
     * Escapes URL
     */
    public static function escape_url($url) {
        return esc_url($url);
    }

    /**
     * Escapes HTML attribute
     */
    public static function escape_attribute($value) {
        return esc_attr($value);
    }

    /**
     * Escapes translatable string
     */
    public static function escape_translation($string) {
        return esc_html__($string, 'h2-product-insight');
    }

    /**
     * Escapes HTML
     */
    public static function escape_html($content) {
        return esc_html($content);
    }

    /**
     * Escapes and translates string
     */
    public static function escape_translated_string($string) {
        return esc_html(translate($string, 'h2-product-insight'));
    }

    /**
     * Escapes JavaScript
     */
    public static function escape_js($text) {
        return esc_js($text);
    }

    /**
     * Escapes text directional
     */
    public static function escape_textarea($text) {
        return esc_textarea($text);
    }

    /**
     * Escapes HTML with translation
     */
    public static function escape_html_e($text) {
        echo self::escape_html($text);
    }

    /**
     * Force escapes all HTML including translations
     */
    public static function escape_html_all($text, $translate = false) {
        if ($translate) {
            return self::escape_translation($text);
        }
        return wp_kses_post($text);
    }

    /**
     * Escapes JSON for HTML attribute
     */
    public static function escape_json_attr($data) {
        return esc_attr(wp_json_encode($data));
    }

    /**
     * Escapes JavaScript strings specifically
     *
     * @param string $string The string to escape
     * @return string The escaped string
     */
    public static function escape_js_string($string) {
        return addslashes(wp_check_invalid_utf8($string));
    }

    /**
     * Escapes CSS values
     *
     * @param string $css The CSS value to escape
     * @return string The escaped CSS value
     */
    public static function escape_css_value($css) {
        return sanitize_hex_color($css) ?: preg_replace('/[^a-zA-Z0-9_%\-\.#]/', '', $css);
    }

    /**
     * Escapes file paths
     *
     * @param string $path The file path to escape
     * @return string The escaped file path
     */
    public static function escape_path($path) {
        return sanitize_file_name(wp_normalize_path($path));
    }
}