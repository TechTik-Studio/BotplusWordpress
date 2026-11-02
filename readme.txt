=== BotPlus AI Agents ===
Contributors: botplus
Tags: ai agent, ai agents, woocommerce, customer support, live chat
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI sales and support agent for WordPress and WooCommerce. Conversational product discovery, cart assistance, order tracking, and lead capture.

== Description ==

BotPlus is an enterprise-grade AI agent that replaces generic chatbots with a dedicated sales and support agent for your WordPress site.

**Key Features:**

* **Conversational Product Discovery** - Customers ask naturally: "Find me a budget desk lamp under $30" and get curated recommendations with product cards
* **Cart Assistance** - Add, remove, update quantities directly in chat (WooCommerce)
* **Order Tracking** - Self-serve order status with verified identity
* **Lead Capture** - Turn browsers into subscribers with contextual forms (Klaviyo, Mailchimp sync)
* **Human Handoff** - Seamless escalation to your team with full conversation context
* **Managed AI** - No API keys needed. We handle model selection, fallbacks, and upgrades
* **Revenue Attribution** - Track exact sales influenced by AI conversations

**WooCommerce Integration:**
* Real-time product/catalog sync via webhooks
* Cart mutations via WC AJAX (secure, no API keys on frontend)
* Order verification via customer identity
* HPOS compatible

**For Developers:**
* REST API endpoints for custom integrations
* MCP (Model Context Protocol) server for AI agent access
* Extensible hooks and filters

== Third-Party Services ==

This plugin connects to and relies on the BotPlus cloud service (https://botplus.app) to deliver AI sales and support, conversational catalog search, and customer inbox management.

No store catalog data, conversation records, or customer data is transmitted to BotPlus prior to the site administrator explicitly connecting the plugin to their BotPlus account.

By installing and connecting this plugin, your site communicates with the BotPlus cloud platform. Please review our terms and privacy documentation:
* BotPlus Terms of Service: https://botplus.app/terms
* BotPlus Privacy Policy: https://botplus.app/privacy

What data is transmitted to BotPlus (https://botplus.app):
* Store catalog data (product titles, descriptions, pricing, inventory statuses, categories) to power your dedicated AI sales agent.
* Customer chat messages submitted to the storefront chat widget for AI response generation and support inbox management.
* Cart item IDs, quantities, and restore tokens when an online customer requests cart modifications or checkout assistance.
* Order status inquiries (order numbers and customer email) to query tracking and fulfillment status from WooCommerce.

== Installation ==

1. Upload the `botplus-ai-agents` directory to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Go to BotPlus AI in your WordPress admin menu
4. Connect your BotPlus account or enter your Laravel backend URL
5. Configure widget appearance and features
6. Save - the chat widget appears automatically

== Frequently Asked Questions ==

= Do I need a BotPlus account? =
Yes, the plugin connects to a BotPlus Laravel backend for AI processing. You can use our hosted service or self-host.

= Does it work without WooCommerce? =
Yes! It works on any WordPress site for content search, lead capture, and support. WooCommerce features activate automatically when detected.

= Is my data sent to external AI providers? =
Only conversation text is sent to our managed AI infrastructure. No customer PII, credentials, or private data leaves your server.

= Can I customize the widget appearance? =
Yes - colors, position, theme (Glass, Timeless, Messages), welcome message, and offline behavior.

== Screenshots ==

1. Storefront chat widget answering product questions with product cards
2. Merchant dashboard with conversation inbox
3. Agent settings panel
4. WooCommerce product sync status

== Changelog ==

= 1.0.0 =
* Initial release
* WooCommerce product sync, cart actions, order tracking
* Lead capture with Klaviyo/Mailchimp
* Human handoff with helpdesk integration
* Managed AI (no API keys required)
* Revenue attribution tracking

== Upgrade Notice ==

= 1.0.0 =
Initial release. Requires PHP 8.1+, WordPress 6.0+.