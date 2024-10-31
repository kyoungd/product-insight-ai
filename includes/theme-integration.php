<?php
class H2_Product_Insight_Theme_Integration {
    public function __construct() {
        add_action('wp_head', array($this, 'output_custom_styles'));
        add_action('switch_theme', array($this, 'clear_theme_color_cache'));
    }

    public function output_custom_styles() {
        $primary_color = $this->get_theme_primary_color();
        $secondary_color = $this->adjust_brightness($primary_color, 30);
        $text_color = $this->get_contrasting_color($primary_color);
        
        echo '<style type="text/css">';
        echo ':root {';
        echo '--ck-primary-color: ' . esc_attr($primary_color) . ';';
        echo '--ck-secondary-color: ' . esc_attr($secondary_color) . ';';
        echo '--ck-text-color: ' . esc_attr($text_color) . ';';
        echo '}';
        echo '</style>';
    }

    public function clear_theme_color_cache() {
        delete_transient('ck_theme_primary_color');
    }

    private function get_theme_primary_color() {
        $cached_color = get_transient('ck_theme_primary_color');
        if ($cached_color !== false) {
            return $cached_color;
        }

        // Default fallback color
        $primary_color = '#333333';

        // Common theme mod keys for primary color
        $color_mod_keys = array(
            'primary_color',
            'accent_color',
            'link_color',
            'header_textcolor',
            'background_color',
        );

        foreach ($color_mod_keys as $mod_key) {
            $theme_mod_color = get_theme_mod($mod_key);
            if ($theme_mod_color && $this->is_valid_color($theme_mod_color)) {
                $primary_color = $this->sanitize_hex_color($theme_mod_color);
                break;
            }
        }

        // Try to get color from theme.json for block themes
        if (function_exists('wp_get_global_settings')) {
            $global_settings = wp_get_global_settings(array('color', 'palette'));
            if (!empty($global_settings['color']['palette']['theme'])) {
                $theme_palette = $global_settings['color']['palette']['theme'];
                if (!empty($theme_palette[0]['color'])) {
                    $primary_color = $this->sanitize_hex_color($theme_palette[0]['color']);
                }
            }
        }

        // Cache the color for future use
        set_transient('ck_theme_primary_color', $primary_color, DAY_IN_SECONDS);
        return $primary_color;
    }

    private function is_valid_color($color) {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color);
    }

    private function sanitize_hex_color($color) {
        if ($this->is_valid_color($color)) {
            return $color;
        }
        return '#333333';
    }

    private function adjust_brightness($hex, $steps) {
        $steps = max(-255, min(255, $steps));
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $color_parts = str_split($hex, 2);
        $return = '#';

        foreach ($color_parts as $color) {
            $color = hexdec($color);
            $color = max(0, min(255, $color + $steps));
            $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
        }

        return $return;
    }

    private function get_contrasting_color($hex) {
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $brightness > 128 ? '#000000' : '#FFFFFF';
    }
}

new H2_Product_Insight_Theme_Integration();
