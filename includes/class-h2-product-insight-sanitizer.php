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

    /**
     * Sanitizes AI response data.
     *
     * @param array $data The AI response data to sanitize.
     * @return array Sanitized data.
     */
    public static function sanitize_ai_response($response_json) {
        // If already an array/object, don't decode
        $response = is_string($response_json) ? json_decode($response_json, true) : $response_json;
        
        if (json_last_error() !== JSON_ERROR_NONE && is_string($response_json)) {
            return null;
        }
        
        if (!isset($response['success'])) {
            return $response; // Return as-is if not expected format
        }
        
        $sanitized = array(
            'success' => (bool) $response['success']
        );
        
        if (isset($response['data'])) {
            $data = $response['data'];
            
            $sanitized['data'] = array(
                'caller_domain' => sanitize_text_field($data['caller_domain'] ?? ''),
                'email' => sanitize_email($data['email'] ?? ''),
                'id' => sanitize_key($data['id'] ?? ''),
                'mark_index' => absint($data['mark_index'] ?? 0),
                'message' => wp_kses_post($data['message'] ?? ''),
                'state' => sanitize_text_field($data['state'] ?? ''),
                'subscription_external_id' => sanitize_key($data['subscription_external_id'] ?? ''),
                'timezone' => sanitize_text_field($data['timezone'] ?? ''),
                'caller' => is_array($data['caller'] ?? null) ? array_map('sanitize_text_field', $data['caller']) : array(),
                'transcription' => self::sanitize_transcription($data['transcription'] ?? array()),
                'pause_conversation' => (bool) ($data['pause_conversation'] ?? false),
                'product_description' => wp_kses_post($data['product_description'] ?? ''),
                'product_title' => sanitize_text_field($data['product_title'] ?? '')
            );
        }
        
        return (object) $sanitized; // Convert to object to match expected format
    }
    
    /**
     * Sanitizes the transcription array
     *
     * @param array $transcription Array of transcription entries
     * @return array Sanitized transcription data
     */
    private static function sanitize_transcription($transcription) {
        if (!is_array($transcription)) {
            return array();
        }
        
        return array_map(function($entry) {
            return array(
                'content' => wp_kses_post($entry['content'] ?? ''),
                'role' => sanitize_text_field($entry['role'] ?? '')
            );
        }, $transcription);
    }   
}