=== Gravity Forms Braintree Payments ===
Contributors: angelleye, Plugify, hello@lukerollans.me, gravityplus
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9CQZZGGMF78VY&source=url
Tags: gravity form, gravity forms, credit card, credit cards, payment, payments, braintree
Requires at least: 5.0
Tested up to: 6.4.2
Stable tag: 5.0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Allow your customers to purchase goods and services through Gravity Forms via Braintree Payments

== Description ==

Braintree Payments is a payment gateway provider owned by PayPal which allows you to process credit card payments without the need for a bank merchant account and full PCI-compliance. No sensitive data such as credit card numbers are stored on your server, Braintree takes care of everything.

 > Requires at least WordPress 3.8 and Gravity Forms 1.8

There are just a few simple steps to begin leveraging your Braintree Payments account:

1. Install Gravity Forms Braintree Payments.
2. Go to the Form Settings page for the form you wish to create a Braintree feed on.
3. You will be prompted to configure your Braintree settings. Click the link provided to do so.
4. Once you have configured your Braintree settings, return to the Form Settings page and follow the prompts.

= Features =

* Seamlessly integrates your Gravity Forms credit card forms with Braintree Payments.
* Supports both production and sandbox environments, enabling you to test payments before going live.
* Form entries will only be created when payment is successful.
* Quick and easy setup.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Gravity Forms Braintree Payments, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type Gravity Forms Braintree Payments and click Search Plugins. Once you've found our plugin (make sure it says "by Angell EYE") you can view details about it such as the the rating and description. Most importantly, of course, you can install it by simply clicking Install Now.

= Manual Installation =

1. Unzip the files and upload the folder into your plugins folder (/wp-content/plugins/) overwriting older versions if they exist
2. Activate the plugin in your WordPress admin area.

= Usage =

1. Navigate to the Form you wish to setup with a Braintree feed.
2. Under Form Settings, choose the Braintree option.

== Frequently asked questions ==

= What type of Braintree payments can be accepted? =
* For this early version, only one off payments can be accepted.

= Does this plugin support Braintree subscriptions? =
* Not yet.  This will be added based on future demand.

== Screenshots ==
1. Drop a credit card field collection directly into any Gravity Form.
2. Easily configure your Braintree settings, allowing for quick and efficient setup.
3. Quickly and easily configure payment feeds under Form Settings of any Gravity Form.
4. List of active feeds on the current form.

== Changelog ==

= 5.0.1 - 01.24.2024 =
* Fix - Minor bug fixes

= 5.0.0 - 01.22.2024 =
* Feature - Braintree Merchant Account ID Compatibility for each Braintree payment forms. ([GFB-54](https://github.com/angelleye/gravity-forms-braintree/pull/46))
* Feature - Add global and form level settings for Extra fees. ([GFB-50](https://github.com/angelleye/gravity-forms-braintree/pull/47))
* Feature - Collect Extra fees based on Card Type: Debit, Credit or ACH.
* Feature - Generate and export Braintree transaction reports. ([GFB-56](https://github.com/angelleye/gravity-forms-braintree/pull/48))
* Check the compatibility with latest WordPress version 6.4.2

= 4.0.7 - 04.18.2023 =
* Fix - Resolved Braintree payment error while using credit card field. ([GFB-46](https://github.com/angelleye/gravity-forms-braintree/pull/45))

= 4.0.6 - 04.18.2023 =
* Fix - Adjustments to resolve theme conflict. ([GFB-40](https://github.com/angelleye/gravity-forms-braintree/pull/44))

= 4.0.5 - 01.26.2022 =
* Feature - Added Gravity Forms version 2.5.16 capability. ([GFB-42](https://github.com/angelleye/gravity-forms-braintree/pull/39))

= 4.0.4 - 01.17.2022 =
* Fix - Resolved PHP Fatal error. ([GFB-41](https://github.com/angelleye/gravity-forms-braintree/pull/38))

= 4.0.3 - 01.10.2022 =
* Fix - Resolved PHP Fatal error. ([GFB-37](https://github.com/angelleye/gravity-forms-braintree/pull/37))

= 4.0.2 - 07.12.2021 =
* Tweak - Updates Update Braintree SDK. ([GFB-37](https://github.com/angelleye/gravity-forms-braintree/pull/33))

= 4.0.1 - 03.16.2021 =
* Tweak - Adding label for Braintree CC while setting up Subscription method ([GFB-36](https://github.com/angelleye/gravity-forms-braintree/pull/31))

= 4.0 - 03.01.2021 =
* Feature - Added Braintree Subscription ([GFB-31](https://github.com/angelleye/gravity-forms-braintree/pull/30))

= 3.1.2 - 05.14.2020 =
* Fix - Resolved Braintree ACH/CC form validation issuw with multiple Payment Methods  ([GFB-27](https://github.com/angelleye/gravity-forms-braintree/pull/28))

= 3.1.1 - 05.14.2020 =
* Feature - Added Braintree ACH Direct Debit + CC compatibility with custom radio fields and conditions ([GFB-25](https://github.com/angelleye/gravity-forms-braintree/pull/26))
* Feature - Pass custom field mapping data with ACH Direct Debit payments ([GFB-24](https://github.com/angelleye/gravity-forms-braintree/pull/27))

= 3.1.0 - 05.13.2020 =
* Feature - Added Braintree ACH Direct Debit Payment Gateway ([GFB-17](https://github.com/angelleye/gravity-forms-braintree/pull/25))
* Feature - Added custom plugin requirement checker to validate server configuration ([GFB-22](https://github.com/angelleye/gravity-forms-braintree/pull/24))

= 3.0.2 - 05.04.2020 =
* Fix - Resolved the issue with PHP Version comparison ([GFB-21](https://github.com/angelleye/gravity-forms-braintree/pull/23))

= 3.0.1 - 04.28.2020 =
* Fix - Compatibility issue with PayPal for WooCommerce in loading Braintree library ([GFB-18](https://github.com/angelleye/gravity-forms-braintree/pull/22))

= 3.0.0 - 04.28.2020 =
* Upgrade - Braintree Library Upgraded from 3.36.0 to 5.0.0 ([GFB-18](https://github.com/angelleye/gravity-forms-braintree/pull/21))
* Tweak - Support Gravity Form No Conflict Mode issue with Braintree script loading in backend. ([GFB-19](https://github.com/angelleye/gravity-forms-braintree/pull/20))

= 2.2.2 - 12.30.2019 =
* Tweak - Adjustment to Updater plugin notice dismissible. ([GFB-16](https://github.com/angelleye/gravity-forms-braintree/pull/17))

= 2.2.0 = 11.20.2019 =
* Verification - WordPress 5.3 compatibility.

= 2.2.0 = 10.16.2019 =
* Feature - Adds Braintree field mapping capability. ([GFB-12](https://github.com/angelleye/gravity-forms-braintree/pull/14)) ([GFB-15](https://github.com/angelleye/gravity-forms-braintree/pull/16))
* Tweak - Adds a notice if you try to activate the Braintree Payments extension without Gravity Forms active.

= 2.1.3 - 07.23.2019 =
* Tweak - Update push notification system sync interval time. ([GFB-9](https://github.com/angelleye/gravity-forms-braintree/pull/11)) 

= 2.1.2 - 07.09.2019 =
* Tweak - Minor adjustments to API request.

= 2.1.1 - 05.31.2019 =
* Feature - Adds AE notification system. ([GFB-8](https://github.com/angelleye/gravity-forms-braintree/pull/10))
* Tweak - Adds text domain. ([GFB-7](https://github.com/angelleye/gravity-forms-braintree/pull/9))

= 2.1.0 =
* Feature - Adds AE Updater compatibility for future notices and automated updates. [GFB-4] ([GFB-5](https://github.com/angelleye/gravity-forms-braintree/pull/8))

= 2.0.0 =
* Fix - Updates Braintree Payments SDK and resolves failures with latest version of Gravity Forms. ([#1](https://github.com/angelleye/gravity-forms-braintree/issues/1))

= 1.1.2 =
* Internal maintenance release. Version 1.2 is coming soon and it's going to be big!

= 1.1.1 =
* Dashes and spaces are now removed from credit card number before sending to Braintree

= 1.1 =
* Fixed bug causing automatic settlement submission to fail

= 1.0 =
* Updated to latest Gravity Forms payment framework
* Added authorization validation. Form entries will no longer validate unless the payment has succeeded
* Payment information now displays on the entry page

= 0.8.1 =
* Stricter settings validation
* Fixed bug causing inactive feeds to process

= 0.8 =
* Most of plugin functionality

= 0.1 =
* Initial version of the plugin