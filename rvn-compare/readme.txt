=== RVN Compare products for woocommerce ===
Contributors: revolen
Tags: woocommerce, compare, comparison, products
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compare WooCommerce products in a clean, DNS / Citilink-style comparison table.

== Description ==

RVN Compare products for woocommerce lets customers add products to a compare
list and compare their attributes on a dedicated page with a convenient table.

= Features (MVP) =

* Compare list with live counter (localStorage for guests, user meta for logged-in users).
* Dedicated compare page with shortcodes.
* Compare button on product cards and product pages (auto-insert with configurable position).
* Comparison table with category/group tabs.
* Five shortcodes: table, button, counter button, counter, clear.
* Toast notifications for add / remove / clear / limit.
* Fully translatable (English source + Russian included).

== Installation ==

1. Upload the `rvn-compare` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. The plugin creates the compare page (`rvn-compare`) on activation.
4. Configure under `RVN > Compare`.

== Frequently Asked Questions ==

= Where is the compare page? =

The plugin creates a page with the slug `rvn-compare` on activation.

= How do I add a compare button to a custom template or page builder? =

Use the `[rvn-compare-button]` shortcode (optionally with `id`).

== Changelog ==

= 0.1.0 =
* MVP core: storage, REST, shortcodes, admin settings, compare page, toasts.
