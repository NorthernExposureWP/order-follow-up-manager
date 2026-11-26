=== Order Follow-up Manager ===
Tags: woocommerce, orders
Requires at least: 6.9
Requires PHP: 8.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Add a follow-up flag and notes to WooCommerce orders, then find flagged orders from the Orders list.

== Description ==

Order Follow-up Manager adds a "Follow-up required" checkbox and a free-text details field to the WooCommerce order editor. It adds a Follow-up column to the Orders list and lets administrators filter that list to orders marked as requiring follow-up.

Follow-up status and details are stored as WooCommerce order metadata. Both High-Performance Order Storage (HPOS) and legacy order storage are supported.

== Requirements ==

* WordPress 6.9 or later.
* PHP 8.4 or later.
* WooCommerce 10.8 or later.

== Installation ==

1. Upload the `order-follow-up-manager` folder to the `/wp-content/plugins/` directory, or install the plugin using your site's plugin installer.
2. Activate Order Follow-up Manager and WooCommerce from the Plugins screen. WooCommerce must be active for the plugin to initialize.

== Usage ==

1. Open a WooCommerce order in the administration area.
2. Set "Follow-up required" and optionally enter follow-up details, then save the order.
3. In the Orders list, use the Follow-up column to see marked orders. Choose "Follow-up required" in the filter to show only those orders.

== Changelog ==

= 0.1.0 =
* Add a follow-up required flag and details field to the WooCommerce order editor.
* Add a Follow-up column and a filter for flagged orders in the Orders list.
* Support HPOS and legacy order storage.
