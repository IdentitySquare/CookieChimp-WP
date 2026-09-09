=== CookieChimp ===
Contributors: CookieChimp
Tags: cookies, consent, GDPR, cookie banner, consent banner
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds the CookieChimp consent banner at the start of the website head so it can intercept scripts before consent.

== Description ==

This plugin integrates the CookieChimp consent management platform with WordPress. Enter your CookieChimp Account ID on the settings page and the plugin adds your account-specific widget to public pages.

CookieChimp is printed synchronously at the earliest possible `wp_head` priority. This lets its optional auto-blocking feature install its interceptors before scripts added through the normal WordPress script queue or later `wp_head` callbacks.

An active [CookieChimp account](https://cookiechimp.com/) is required. The [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) plugin is recommended, but not required.

== External service ==

CookieChimp is an external consent management service provided by Identity Square.

The plugin does not contact CookieChimp until a WordPress administrator saves a non-empty CookieChimp Account ID. After configuration, every public page loads the account-specific consent widget from `https://cookiechimp.com/widget/ACCOUNT_ID.js`. The widget displays the consent banner, records consent, and, when enabled in CookieChimp, blocks configured third-party scripts and requests until the visitor permits them.

Requests to CookieChimp include the configured Account ID and standard web request information such as the visitor's IP address and browser user agent. Depending on the CookieChimp configuration and the visitor's interaction, the widget can also send the website origin, a pseudonymous visitor identifier, regional information, consent status and choices, language, Global Privacy Control preference, and a user ID supplied by the website through its data layer. This data is used to provide and record the consent service.

* Service: [CookieChimp](https://cookiechimp.com/)
* Terms of service: [CookieChimp Terms](https://cookiechimp.com/terms)
* Privacy policy: [CookieChimp Privacy Policy](https://cookiechimp.com/privacy)

== Installation ==

1. Upload the `cookiechimp` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to 'Settings' -> 'CookieChimp' and enter your CookieChimp Account ID.

== Frequently Asked Questions ==

= Is CookieChimp guaranteed to be the first script on the page? =

The plugin prints CookieChimp before all other callbacks on WordPress's `wp_head` hook and before the normal WordPress script queue. For this to make CookieChimp the first script in the document, the active theme must call `wp_head()` immediately before the closing `</head>` tag and must not hard-code another script before that call. If a theme hard-codes scripts before `wp_head()`, move those scripts after the hook or enqueue them through WordPress.

= Does activation immediately connect my site to CookieChimp? =

No. The widget is loaded only after an administrator saves a valid CookieChimp Account ID.

== Changelog ==

= 1.0.3 =
* Make the CookieChimp widget the earliest `wp_head` script so it can intercept later scripts.
* Validate the Account ID and register the setting at the correct WordPress lifecycle hook.
* Document the CookieChimp external service and the data it can process.
* Add direct-access protection, uniquely prefixed functions, and WordPress coding-standard cleanup.

= 1.0.2 =
* Initial public version.

== Upgrade Notice ==

= 1.0.3 =
* Improves early script interception, settings reliability, validation, and service documentation.
