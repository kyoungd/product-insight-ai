<?php
// File: includes/product-insight-renderer.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class H2_Product_Insight_Renderer {

    /**
     * Renders the chatbox based on the custom or default template.
     *
     * @return string The rendered chatbox HTML.
     */
    public static function render() {
        $options = get_option('h2_product_insight_options');
        $custom_template = isset($options['custom_template']) ? $options['custom_template'] : '';
        $custom_css = isset($options['custom_css']) ? $options['custom_css'] : '';

        $output = '';

        // Include custom CSS if provided
        if (!empty($custom_css)) {
            $output .= '<style>' . wp_strip_all_tags($custom_css) . '</style>';
        }

        if (!empty($custom_template)) {
            $output .= self::render_custom_template($custom_template);
        } else {
            $output .= self::render_default_template();
        }

        return $output;
    }

    /**
     * Renders the custom template with placeholders replaced.
     *
     * @param string $template The custom template HTML.
     * @return string The rendered template.
     */
    private static function render_custom_template($template) {
        $required_placeholders = array('{input_field}', '{loading_indicator}', '{last_reply}', '{messages}');
        $missing_placeholders = array();

        foreach ($required_placeholders as $placeholder) {
            if (strpos($template, $placeholder) === false) {
                $missing_placeholders[] = $placeholder;
            }
        }

        if (!empty($missing_placeholders)) {
            return '<div class="h2-error">' . sprintf(
                esc_html__('The following placeholders are missing in your custom template: %s', 'h2'),
                implode(', ', $missing_placeholders)
            ) . '</div>';
        }

        $placeholders = array(
            '{input_field}' => '<input type="text" id="product-insight-aiuser-input" placeholder="' . esc_attr__('I am Edward, your AI. Ask me anything...', 'h2') . '" aria-label="' . esc_attr__('Chat Input', 'h2') . '">',
            '{loading_indicator}' => '<div id="product-insight-ailoading" style="display: none;">' . esc_html__('Initializing...', 'h2') . '</div>',
            '{last_reply}' => '<div id="product-insight-ailast-reply-container" style="display: none;"></div>',
            '{messages}' => '<div id="product-insight-aimessages"></div>',
        );

        $template = wp_kses_post($template);
        $rendered_template = str_replace(array_keys($placeholders), array_values($placeholders), $template);

        return $rendered_template;
    }

    /**
     * Renders the default chatbox template.
     *
     * @return string The default template HTML.
     */
    private static function render_default_template() {
        ob_start();
        ?>
        <div id="product-insight-aichatbox">
            <div id="product-insight-aiinput">
                <input type="text" id="product-insight-aiuser-input" placeholder="<?php echo esc_attr__('I am Edward, your AI. Ask me anything...', 'h2'); ?>" aria-label="<?php echo esc_attr__('Chat Input', 'h2'); ?>">
                <div id="product-insight-ailoading" style="display: none;"><?php echo esc_html__('Initializing...', 'h2'); ?></div>
            </div>
            <div id="product-insight-ailast-reply-container" style="display: none;"></div>
            <div id="product-insight-aimessages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
