=== Yandex.Metrica ===
Contributors: yandexmetrika
Tags: яндекс.метрика, яндекс, метрика, metrica, yandex, ecommerce, commerce, e-commerce, wordpress ecommerce, tracking, yandex metrics, metrik, stats, statistics, tools, analytics, analytics tool
Requires at least: 5.2.9
Tested up to: 6.8.2
Requires PHP: 5.6.20
Stable tag: 1.2.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

The free official Yandex.Metrica plugin for WordPress.

== Description ==

= Yandex.Metrica =

The free official Yandex.Metrica plugin for WordPress. This plugin helps you install a Yandex.Metrica tag on your site and configure the transfer of E-commerce data without manually editing the site's code. It also transmits data about product views, additions to the basket, and sales.

= Features =

- Official Yandex.Metrica plugin
- E-commerce event tracking without manually editing the site's code
- Quick installation
- Support for WordPress versions 5.2.9 and higher
- Scheduled updates
- Prompt support service

== List of functions ==
- Automatically search for and configure installed Yandex.Metrica tags.
- Quickly add new Yandex.Metrica tags. The following parameters are set by default:
	- E-commerce: Enabled
	- Session Replay: Enabled (can be disabled if necessary)
	- Click map: enabled
- Transfer of e-commerce events according to the [documentation](https://yandex.ru/support/metrica/data/e-commerce.html):
	- Adding an item to the basket
	- Pageview of a product profile
	- Removing an item from the basket
	- Placing an order
- Detalization of transferred product data according to the [documentation](https://yandex.ru/support/metrica/ecommerce/data.html)
- Event logs with the following error codes:
	- The WordPress version is deprecated
	- The site lacks the brand taxonomy indicated by the user
	- The theme doesn't have the hook required for the plugin to work
	- The tag number contains characters that aren't numbers

= Translations =

* Russian
* English

== Installation ==

= Installation from the WordPress admin panel =

1. Register at Yandex.Metrica and create a tracking tag. If you already have a tag, skip this step.

2. In the WordPress admin panel, go to Plugins → Add New.

3. Search for the Yandex.Metrica plugin and install it.

4. Go to Plugins → Installed Plugins. Activate the installed plugin.

5. Add your Yandex.Metrica tag number in the plugin settings and save the changes.


= Installation from Wordpress.org =

1. Register at Yandex.Metrica and create a tracking tag. If you already have a tag, skip this step.

2. Download the file with the Yandex.Metrica plugin from [wordpress.org](https://wordpress.org/plugins/wp-yandex-metrika) or from [Yandex.Metrica Help](https://yandex.ru/support/metrica/ecommerce/wordpress.html).

3. In the WordPress admin panel, go to Plugins → Add New.

4. Click Upload Plugin. Select the downloaded file with the plugin archive → Install.

5. Go to Plugins in the admin panel  → Installed Plugins. Activate the installed plugin.

6. Add your Yandex.Metrica tag number in the plugin settings and save the changes.


== Frequently Asked Questions ==

= How do I submit a support request? =

Look at the topics discussed on the [support](https://wordpress.org/support/plugin/wp-yandex-metrika) tab. You might find the solution there. If the issue with the plugin has not been previously discussed, contact [Support](https://yandex.ru/support/metrica/troubleshooting.html).

= Why is the plugin free? =

This is the official Yandex.Metrica plugin for WordPress. We want to make it as easy as possible for our users to set up Yandex.Metrica and e-commerce, so we made the plugin completely free.

= Is it possible to use two plugins from different developers? =

We recommend using the official Yandex.Metrica plugin separately from other developers' solutions. If you use the official plugin together with third-party modules, it may interfere with the Yandex.Metrica tag.

= What is E-commerce? =

Yandex.Metrica E-commerce is a tool for online store analytics. It provides detailed statistics on sales and interactions with products on a site. [Learn more](https://yandex.ru/promo/metrica/retail)

= How can I check if events are sent correctly without waiting for the data to be displayed in the Yandex.Metrica dashboard? =

Add the ym_debug=1 parameter to the page URL in the browser's address bar and reload the page. Example: http://example.com/?_ym_debug=1
For more information, see [Help](https://yandex.ru/support/metrica/ecommerce/check.html).

= Why does the plugin have separate fields for "Brand type" and "Taxonomy or custom brand field"? =

These fields are needed to correctly send the brand value of the product.
- If you have selected "Term" in the "Brand type" field, then enter the taxonomy name in "Taxonomy or custom brand field".
- If you have selected "Custom field" in the "Brand type" field, then in "Taxonomy or custom brand field", specify the name of the custom field.

= What is Session Replay in the plugin? =

Session Replay technology offers an entirely new level of detail for analyzing the behavior of site users. You can replay their actions exactly as they occurred to see what happens on each page and how users navigate, right down to every mouse movement, keystroke, and click. Enable Session Replay using the checkbox next to the tag field.

== Changelog ==

= 1.2.2 =
Some minor bugfixes

= 1.2.1 =
Some minor bugfixes

= 1.2.0 =
Resolved statistics collection issue with code minimization plugins

= 1.1.9 =
Some minor bugfixes

= 1.1.8 =
Some minor bugfixes

= 1.1.7 =
Added the functionality of sending recommended goals - do it without changing the code on the site

= 1.1.6 =
Fixed error in Woodmart theme and some bugfixes

= 1.1.5 =
Fixed some minor errors in admin area

= 1.1.4 =
Fixed error with sending the "purchase" in some cases

= 1.1.3 =
Fixed an error with sending the "add" ecom-event and some other issues

= 1.1.2 =
Fixed a minor error with quantity on updated cart

= 1.1.1 =
Some minor bugfixes

= 1.0.0 =
Release date: October 5, 2021


== Upgrade Notice ==
1.1.5: Fixed some minor errors in admin area
