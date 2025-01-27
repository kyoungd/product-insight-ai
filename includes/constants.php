<?php
/**
 * Constants for H2 Product Insight
 *
 * @package    H2_Product_Insight
 * @author     Young Kwon
 * @copyright  Copyright (C) 2024, Young Kwon
 * @license    GPL-2.0-or-later
 * @link       https://2human.ai
 * @file       includes/constants.php
 */

// file name: include/constants.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('h2piai_ACTIVATION_TEST', false);
define('h2piai_PRODUCT_INSIGHT_VERSION', '1.4');
define('h2piai_PRODUCT_INSIGHT_API_URL', 'https://2human.ai/wp-json/my-first-plugin/v1');
# define('h2piai_PRODUCT_INSIGHT_API_URL', 'https://talkee.ai/wp-json/my-first-plugin/v1');

define('h2piai_PRODUCT_INSIGHT_MAX_MESSAGE_LENGTH', 1000);
define('h2piai_PRODUCT_INSIGHT_MAX_QUERY_LENGTH', 2000);

define('h2piai_PRODUCT_INSIGHT_SECURITY_PATTERNS', array(
    '/\\<script\\b[^>]*\\>.*?\\<\\/script\\>/is',  // Remove <script> tags (escaped)
    '/\\<iframe\\b[^>]*\\>.*?\\<\\/iframe\\>/is',  // Remove <iframe> tags (escaped)
    '/on\\w+\\s*=\\s*".*?"/is',                    // Remove inline event handlers in double quotes
    "/on\\w+\\s*=\\s*'.*?'/is",                    // Remove inline event handlers in single quotes
    '/on\\w+\\s*=\\s*\\w+/is',                     // Remove inline event handlers without quotes
));

define('h2piai_PRODUCT_INSIGHT_INVALID_INPUTS', array(
    '/<\?php/i',                         // PHP tags
    '/<\?=/i',                           // Short open tags
    '/\<\%.+?\%\>/s',                    // ASP-style tags
    '/(javascript|vbscript|data):/i',    // Potential XSS vectors
    '/&(#[xX]?)?(?:[0-9a-fA-F]+|\w+);/', // HTML entities
));


define('h2piai_PRODUCT_INSIGHT_ALLOWED_HTML_TAGS', array(
    // Text formatting
    'p'      => array(
        'class' => array(),
        'id'    => array(),
        'style' => array(),
    ),
    'span'   => array(
        'class' => array(),
        'id'    => array(),
        'style' => array(),
    ),
    'div'    => array(
        'class' => array(),
        'id'    => array(),
        'style' => array(),
    ),
    'strong' => array(),
    'b'      => array(),
    'em'     => array(),
    'i'      => array(),
    'u'      => array(),
    'strike' => array(),
    'del'    => array(),
    'sup'    => array(),
    'sub'    => array(),
    'mark'   => array(),
    
    // Links
    'a'      => array(
        'href'   => array(),
        'title'  => array(),
        'rel'    => array(),
        'class'  => array(),
        'id'     => array(),
        'target' => array(),
    ),
    
    // Lists
    'ul'     => array(
        'class' => array(),
        'id'    => array(),
        'style' => array(),
    ),
    'ol'     => array(
        'class' => array(),
        'id'    => array(),
        'style' => array(),
    ),
    'li'     => array(
        'class' => array(),
        'id'    => array(),
    ),
    'dl'     => array(),
    'dt'     => array(),
    'dd'     => array(),
    
    // Tables
    'table'  => array(
        'class'  => array(),
        'id'     => array(),
        'style'  => array(),
        'width'  => array(),
        'border' => array(),
    ),
    'thead'  => array(),
    'tbody'  => array(),
    'tfoot'  => array(),
    'tr'     => array(),
    'th'     => array(
        'scope'   => array(),
        'colspan' => array(),
        'rowspan' => array(),
    ),
    'td'     => array(
        'colspan' => array(),
        'rowspan' => array(),
    ),
    
    // Media
    'img'    => array(
        'src'     => array(),
        'alt'     => array(),
        'title'   => array(),
        'width'   => array(),
        'height'  => array(),
        'class'   => array(),
        'id'      => array(),
        'loading' => array(),
    ),
    'figure' => array(
        'class' => array(),
        'id'    => array(),
    ),
    'figcaption' => array(),
    
    // Semantic elements
    'article' => array(
        'class' => array(),
        'id'    => array(),
    ),
    'section' => array(
        'class' => array(),
        'id'    => array(),
    ),
    'aside'   => array(
        'class' => array(),
        'id'    => array(),
    ),
    'header'  => array(
        'class' => array(),
        'id'    => array(),
    ),
    'footer'  => array(
        'class' => array(),
        'id'    => array(),
    ),
    
    // Text structure
    'h1'      => array('class' => array(), 'id' => array()),
    'h2'      => array('class' => array(), 'id' => array()),
    'h3'      => array('class' => array(), 'id' => array()),
    'h4'      => array('class' => array(), 'id' => array()),
    'h5'      => array('class' => array(), 'id' => array()),
    'h6'      => array('class' => array(), 'id' => array()),
    'br'      => array(),
    'hr'      => array(),
    
    // Formatting
    'pre'     => array(),
    'code'    => array(),
    'blockquote' => array(
        'cite'  => array(),
        'class' => array(),
    ),
    
    // Forms (read-only/display)
    'button'  => array(
        'class'    => array(),
        'id'       => array(),
        'disabled' => array(),
        'type'     => array(),
    ),
    'label'   => array(
        'for'   => array(),
        'class' => array(),
    ),
));


// Strict tags for user inputs and sensitive contexts
define('h2piai_PRODUCT_INSIGHT_ALLOWED_TAGS_STRICT', array(
    'p'      => array(),
    'br'     => array(),
    'strong' => array(),
    'em'     => array(),
    'b'      => array(),
    'i'      => array(),
    'a'      => array(
        'href'   => array(),
        'title'  => array(),
        'rel'    => array(),
    ),
    'ul'     => array(),
    'ol'     => array(),
    'li'     => array(),
));