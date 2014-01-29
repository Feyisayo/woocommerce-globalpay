=== Woocommerce GlobalPay ===
Contributors: Feyisayo
Donate Link: http://profiles.wordpress.org/feyisayo/
Tags: globalpay, payment gateway, zenith bank, ecommerce, e-commerce, commerce, wordpress ecommerce
Requires at least: 3.5
Tested up to: 3.6
Stable tag: 1.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Woocommerce GlobalPay allows payments to be made to a Woocommerce shop via GlobalPay.

== Description ==
GlobalPay is a web-based payment gateway that enables merchants with functional websites to accept card payments from customers worldwide.

GlobalPay accepts MasterCard, VISA and bank transfers. Get started at https://www.globalpay.com.ng/

This plugin was tested up till WooCommerce 2.0.20

This plugin development was sponsored by the [The Social Bees](http://thesocialbees.com/)

== Installation ==
1. Ensure that you have met the following requirements:
 * WordPress 3.5 or greater
 * PHP version 5.2.4 or greater
 * MySQL version 5.0 or greater
2. Uncompress the plugin on your computer
3. Using an FTP program, or your hosting control panel, upload the unzipped
plugin folder to your WordPress installation’s wp-content/plugins/ directory.
4. Activate the plugin from the Plugins menu within the WordPress admin.
5. __Configure your permalink settings:__ Make sure you are NOT using Wordpress' default permalink setting otherwise user re-direction from GlobalPay's website will NOT work. You can configure your permalink settings on the admin end by going to Settings -> Permalinks and ensure that the option selected is NOT the default.
6. __Configure your merchant credentials:__ WooCommerce -> Settings -> Payment Gateways -> GlobalPay to configure the merchant credentials (either live or demo) for GlobalPay
7. __Return URL:__ GlobalPay will require that you specify a URL on your site that your users will be redirected to after attempting to pay on GlobalPay. Use the following URL based on your website: __YOURSITE.COM/globalpay-transaction-response__

== Frequently Asked Questions ==
= How do I get a GlobalPay Merchant ID? =
Go to https://www.globalpay.com.ng/ to register. Alternatively, if you have a Zenith Bank account contact your branch to get started.

= How do I add the Naira currency to WooCommerce? =
When WooCommerce GlobalPay is installed it will automatically add the Naira currency to WooCommerce.

= Something went wrong when a user was redirected back from GlobalPay to my site. How do I know the status of the payment =
WooCommerce GlobalPay always confirms a payment transaction's status from GlobalPay's servers after a user returns to the merchant's site. However, if the user for some reason is not properly redirected you can manually confirm the payment anytime. On the admin end, go to WooCommerce -> Orders. Find the order in question and in the "Actions" section click on the "R" icon it. Give it a few seconds and the order's status icon will change to reflect the payment information received from GlobalPay's servers.

NOTE: In WooCommerce, when an order is paid it is marked "Processing" 

== Screenshots ==
1. GlobalPay listed as a payment method
2. Updating an order status on the admin

== Changelog ==

= 1.0 =
Initial release

== Upgrade Notice ==
= 1.0 =
Initial release