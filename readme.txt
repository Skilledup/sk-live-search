=== SK Live Search ===
Contributors: Mohammad Anbarestany
Tags: search, live search, ajax search, real-time search
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A plugin to add live search functionality to your WordPress site.

== Description ==

SK Live Search adds an accessible, real-time AJAX-powered live search to your WordPress site. It supports multilingual sites via Polylang and WPML.

Features:
* Real-time search results as you type
* AJAX-powered with nonce security
* Polylang and WPML compatible
* Accessible (ARIA attributes)
* Lightweight and fast

== Installation ==

1. Upload the `sk-live-search` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. The live search will be automatically enabled on your site.

== Keyboard Shortcuts ==

* **Ctrl + /**: Focus the SK Live Search input
* **Arrow Up/Down**: Navigate through search results
* **Enter**: Select highlighted result
* **Escape**: Close search results
* **Tab**: Close search results and move focus

== Frequently Asked Questions ==

= Does this plugin support multilingual sites? =

Yes. It supports both Polylang and WPML.

== Changelog ==

= 1.0.8 =
* Fixed text domain to match plugin slug.
* Improved input sanitization.
* Removed deprecated load_plugin_textdomain() calls.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.8 =
Improved security and i18n compliance. Update recommended.
