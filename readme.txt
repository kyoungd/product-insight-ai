=== H2 Product Insight ===
Contributors: kyoungd
Donate link: 
Tags: artificial intelligence, product insight, product question answer
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.5
Requires PHP: 7.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered Product Insight chat for WooCommerce products.

== Description ==

H2 Product Insight adds an AI-powered chat interface to your WooCommerce product pages.

youtube https://www.youtube.com/watch?v=NhKPHvGIw2k&t=53s

**Features:**

* Customizable chat placement
* Custom CSS support
* Easy activation process
* WooCommerce integration
* Responsive design

**Quick Links:**

* [Documentation](https://2human.ai/docs)
* [Support](info@2human.ai)
* [Live Demo] https://2human.ai

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/h2-product-insight` or install via the WordPress Plugin Directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > H2 AI** to configure the plugin.

== Frequently Asked Questions ==

= Does this require an API key? =

Yes, you need an API key from [2human.ai](https://2human.ai) to use this plugin.

= Where will the chat appear? =

By default, it appears after the Add to Cart button, but you can customize its placement in the settings.

== Screenshots ==

1. Chat interface on product page.
2. Settings page.
3. Customization options.

== Changelog ==

= 1.5 =
* Updted the code to pass the submission to wordpress ==
* Properly escaping all text for input/output
* Remove debug messages
* Make sure all input/output variables before using

= 1.3 =
* Added custom CSS support.
* Improved error handling.
* Enhanced security measures.

= 1.2 =
* Added placement options.
* Bug fixes.

= 1.1 =
* Initial release.

== Upgrade Notice ==

== Privacy Policy ==

This plugin:

* Communicates with 2human.ai servers for AI processing.
* Collects product data and user messages to provide AI responses.
* Does not store personal information.
* Complies with GDPR requirements.

== External Services ==

This plugin connects to:

* [2human.ai API](https://2human.ai/wp-json) for AI responses.


== External Services ==

This plugin connects to an external API provided by 2Human.ai to validate API keys and retrieve product insights. This is necessary for the plugin to function correctly and provide the intended features.

=== What data is sent and when ===
- **API Key Validation**: When the plugin is configured, it sends the provided API key to `https://2human.ai/wp-json/my-first-plugin/v1/validate-api-key` to validate its authenticity.
- **Product Insights Query**: When the plugin retrieves product insights, it sends relevant data (e.g., product IDs, user preferences, etc.) to `https://2human.ai/wp-json/my-first-plugin/v1/query`.

=== Data Transmission ===
- The plugin sends the following data to the external service:
  - API key (for validation purposes).
  - Product-related data (for querying insights).
- Data is transmitted securely over HTTPS.

=== Service Provider ===
- The external service is provided by 2Human.ai.
- **Terms of Service**: [2Human.ai Terms of Service](https://2human.ai/terms-of-service)
- **Privacy Policy**: [2Human.ai Privacy Policy](https://2human.ai/privacy-policy)


== External Services ==

This plugin connects to an external API provided by 2Human.ai to validate API keys and retrieve product insights. This is necessary for the plugin to function correctly and provide the intended features.

=== What data is sent and when ===
- **API Key Validation**: When the plugin is configured, it sends the provided API key to `https://2human.ai/wp-json/my-first-plugin/v1/validate-api-key` to validate its authenticity.
- **Product Insights Query**: When the plugin receives a query, it sends relevant data (e.g., product information, user query, etc.) to `https://2human.ai/wp-json/my-first-plugin/v1/query`.

=== Data Transmission ===
- The plugin sends the following data to the external service:
  - API key (for validation purposes).
  - Product-related data (for querying insights).
  - User query (user questions)
- Data is transmitted securely over HTTPS.

=== Service Provider ===
- The external service is provided by 2Human.ai.
- **Terms of Service**: [2Human.ai Terms of Service](https://2human.ai/terms-of-service)
- **Privacy Policy**: [2Human.ai Privacy Policy](https://2human.ai/privacy)