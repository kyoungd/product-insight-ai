
<?php
/**
 * Sanitization Utility Class for H2 Product Insight
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

    public static function sanitize_custom_css($css) {
        if (!$css) {
            return '';
        }
    
        // Remove null bytes
        $css = str_replace(['\\0', '\\a', '\\f', '\\v'], '', $css);
    
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    
        // Remove potentially dangerous protocols and expressions from URLs
        $css = preg_replace([
            '/expression\s*\(.*\)/i',           // Remove expressions
            '/behavior\s*:.*?(;|$)/i',          // Remove behavior
            '/javascript\s*:/i',                // Remove javascript
            '/vbscript\s*:/i',                  // Remove vbscript
            '/@import\s+[^;]+;/i',              // Remove @import
            '/position\s*:\s*fixed/i',          // Remove position:fixed
            '/-moz-binding\s*:/i',              // Remove -moz-binding
            '/binding\s*:/i',                   // Remove binding
            '/filter\s*:/i'                     // Remove filter
        ], '', $css);
    
        // Allow data URLs for background images after sanitizing
        $css = preg_replace_callback('/url\s*\(\s*([^)]*)\s*\)/i', function($matches) {
            $url = trim($matches[1], '"\'');
            
            // Allow data URLs and regular URLs
            if (preg_match('/^data:image\/(?:png|jpg|jpeg|gif|webp|svg\+xml);base64,/i', $url) ||
                preg_match('/^(?:https?:)?\/\//i', $url) ||
                preg_match('/^[\/\.](?:[a-zA-Z0-9\-._\/]+)$/i', $url)) {
                return 'url(' . $url . ')';
            }
            
            return '';
        }, $css);
    
        // Split into rules while preserving media queries and keyframes
        $css = preg_replace('/\s+/', ' ', $css); // Normalize whitespace
        $parts = preg_split('/((?:^|})(?:\s*)(?:[^{]+)(?:\s*){(?:[^}]*})+)/', $css, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        $sanitized = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
    
            // Handle media queries and keyframes
            if (preg_match('/^@(?:media|keyframes|supports|container)/i', $part)) {
                // Basic sanitization of media query / keyframe content
                $part = preg_replace('/[^\w\s\-@:;,.(){}\'"#%]/', '', $part);
                $sanitized .= $part . "\n";
                continue;
            }
    
            // Handle regular CSS rules
            if (strpos($part, '{') !== false) {
                // Split selector from properties
                list($selector, $properties) = array_pad(explode('{', $part, 2), 2, '');
                $selector = trim($selector);
                $properties = trim(rtrim($properties, '}'));
    
                // Basic selector sanitization while allowing more complex selectors
                $selector = preg_replace('/[^\w\s\-_.,#*+>~:[\]()="|\']/i', '', $selector);
    
                // Split properties into name:value pairs
                $pairs = explode(';', $properties);
                $sanitized_properties = [];
    
                foreach ($pairs as $pair) {
                    $pair = trim($pair);
                    if (empty($pair)) {
                        continue;
                    }
    
                    $property_parts = explode(':', $pair, 2);
                    if (count($property_parts) !== 2) {
                        continue;
                    }
    
                    $property_name = trim($property_parts[0]);
                    $property_value = trim($property_parts[1]);
    
                    // Allow custom properties (CSS variables)
                    if (strpos($property_name, '--') === 0) {
                        $sanitized_properties[] = $property_name . ': ' . $property_value;
                        continue;
                    }
    
                    // Allow any valid CSS property name
                    if (preg_match('/^-?[a-zA-Z0-9\-]+$/', $property_name)) {
                        // Sanitize property value while allowing most valid CSS values
                        $property_value = preg_replace(
                            '/[^\w\s\-_.,#%+~:;()\/\'"]+/', 
                            '', 
                            $property_value
                        );
                        
                        // Special handling for gradient and transform values
                        if (strpos($property_value, 'gradient') !== false || 
                            strpos($property_value, 'transform') !== false ||
                            strpos($property_value, 'calc') !== false) {
                            // Allow more characters for these properties
                            $property_value = preg_replace(
                                '/[^\w\s\-_.,#%+~:;()\/\'"deg]+/', 
                                '', 
                                $property_value
                            );
                        }
                        
                        $sanitized_properties[] = $property_name . ': ' . $property_value;
                    }
                }
    
                if (!empty($sanitized_properties)) {
                    $sanitized .= $selector . ' { ' . implode('; ', $sanitized_properties) . '; }' . "\n";
                }
            }
        }
    
        return trim($sanitized);
    }

}