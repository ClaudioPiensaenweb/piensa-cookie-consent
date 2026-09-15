=== Piensa Cookie Consent ===
Contributors: piensaenweb
Tags: cookies, gdpr, consent, privacy, cookie-banner
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent banner with Google Consent Mode v2, automatic script blocking, cookie scanning and a consent log. No external services.

== Description ==

Piensa Cookie Consent is a consent management platform for WordPress sites that need to comply with the GDPR and the ePrivacy Directive.

Everything runs on your own server. The plugin makes no calls to third-party services, loads no assets from a CDN and sends no visitor data anywhere.

= What it does =

* **Blocks before consent.** Third-party scripts and iframes are neutralised until the visitor opts in, which is what the law actually requires. Images and external stylesheets can be blocked too.
* **Google Consent Mode v2.** Sets the default denied state and updates it the moment the visitor chooses, so Analytics and Ads receive the signals they expect.
* **Finds your cookies.** Crawls your sitemap to discover external domains, reads `Set-Cookie` headers and can run an in-browser audit to catch the cookies scripts set at runtime. Detected services are categorised automatically, with manual override.
* **Consent log.** Every choice is recorded with a hashed IP, never the address itself, and can be exported to CSV as evidence of compliance.
* **Geo-targeting.** Show the banner across the EEA, the UK and Switzerland, in a country list of your own, or everywhere.
* **WP Consent API.** Registers as the site's consent manager so other plugins can ask whether they are allowed to set a cookie.
* **Appearance.** Layout, position, colours, radii and icons, with a live preview in the admin.

= Shortcodes =

* `[piensa_cookie_consent_policy]` renders a cookie policy table from the cookies the scanner found.
* `[piensa_cookie_consent_review]` renders a button that reopens the preferences dialog.

= Privacy =

The plugin stores the visitor's choice in a first-party cookie and writes one row per consent to a table in your own database. That row holds a SHA-256 hash of the IP address, the user agent, the language, the categories accepted and the page URL. No data leaves your server.

== Installation ==

1. Install the plugin through **Plugins → Add New**, or upload the ZIP.
2. Activate it.
3. Go to **Piensa Cookie Consent** in the admin menu.
4. Run **Scan now** to discover the external domains and cookies your site uses.
5. Review the categories, adjust the texts and appearance, and publish.

== Frequently Asked Questions ==

= Does it send anything to an external service? =

No. There is no cloud component, no licence check and no CDN. Everything runs on your server.

= Will it keep my settings if I upgrade from PW Cookie Monster? =

Yes. Settings, detected cookies and the consent log are migrated automatically on the first load after the update.

= Does it work with Google Analytics and Google Ads? =

Yes, through Google Consent Mode v2. The plugin sets the denied default before the tags load and updates the state when the visitor chooses. Install the tags with Site Kit or your tag manager as usual.

= Does it support the IAB TCF framework? =

No. TCF requires a registered CMP ID and is aimed at programmatic advertising. This plugin targets the GDPR and ePrivacy consent requirements directly.

= Can I translate it? =

Yes. The plugin is fully internationalised and ships with a Spanish translation. Other locales can be contributed through translate.wordpress.org.

== Screenshots ==

1. Dashboard with consent metrics.
2. Appearance editor with live preview.
3. Scanner results and detected cookies.
4. The banner on the front end.

== Changelog ==

= 1.0.0 =
* First release in the WordPress.org plugin directory, renamed from PW Cookie Monster.
* Added WP Consent API integration, so other plugins can query consent state.
* Replaced the Font Awesome CDN dependency with inline Lucide icons; the plugin now loads no external assets.
* Centralised consent reading with proper input sanitisation.
* Full internationalisation, with a Spanish translation included.
* Added an uninstall routine that removes options and the consent log table, multisite included.
* Settings and the consent log migrate automatically from 0.5.x.

== Upgrade Notice ==

= 1.0.0 =
Renamed release with WP Consent API support and no external asset loading. Settings and consent records migrate automatically.
