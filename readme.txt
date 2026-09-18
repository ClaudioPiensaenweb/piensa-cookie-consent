=== Piensa Cookie Consent ===
Contributors: piensaenweb
Tags: cookies, gdpr, consent, privacy, cookie-banner
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent banner with Google Consent Mode v2, automatic script blocking, cookie scanning and a consent log. No external services.

== Description ==

Piensa Cookie Consent is a consent management platform for WordPress sites that need to comply with the GDPR and the ePrivacy Directive.

Everything runs on your own server. The plugin makes no calls to third-party services, loads no assets from a CDN and sends no visitor data anywhere.

= What it does =

* **Blocks before consent, including third parties it has never seen.** Scripts, iframes, images and external stylesheets are neutralised until the visitor opts in. An unrecognised third party is blocked rather than let through, which is what the law actually requires; a list of technical exceptions covers asset CDNs and font providers.
* **Google Consent Mode v2.** Sets the default denied state and updates it the moment the visitor chooses, so Analytics and Ads receive the signals they expect.
* **Finds your cookies.** Crawls your sitemap to discover external domains, reads `Set-Cookie` headers and can run an in-browser audit to catch the cookies scripts set at runtime. Detected services are categorised automatically, with manual override.
* **Consent log.** Every choice is recorded with a hashed IP, never the address itself, and can be exported to CSV as evidence of compliance.
* **Geo-targeting.** Show the banner across the EEA, the UK and Switzerland, in a country list of your own, or everywhere.
* **WP Consent API.** Registers as the site's consent manager so other plugins can ask whether they are allowed to set a cookie.
* **Appearance.** Layout, position, colours, radii and icons, with a live preview in the admin. One button reads your theme's own palette and fills the colours in for you, keeping a tinted background and a brand accent rather than flattening everything to black and white.
* **Four languages out of the box.** Spanish, English, German and French, all pre-translated and editable in one place.

= Shortcodes =

* `[piensa_cookie_consent_policy]` renders the cookie policy: one table per category listing each cookie, whether it is first- or third-party, its domain, its purpose and how long it is kept, followed by how to accept, refuse or withdraw consent.
* `[piensa_cookie_consent_review]` renders a button that reopens the preferences dialog.

= Privacy =

The plugin stores the visitor's choice in a first-party cookie and writes one row per consent to a table in your own database. That row holds a SHA-256 hash of the IP address, the user agent, the language, the categories accepted and the page URL. No data leaves your server.

= Third-party libraries =

Both are bundled with the plugin and served from your own site. Neither makes any external request.

* **CookieConsent 3.1.0** by Orest Bida, MIT licence. Renders the banner and the preferences dialog. Source: https://github.com/orestbida/cookieconsent
* **Lucide** icons, ISC licence. Four icons, embedded as inline SVG. Source: https://github.com/lucide-icons/lucide

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

= Does it block third parties it does not know about? =

Yes, and this is the setting that matters most for compliance. A third-party script the plugin has no entry for is blocked until the visitor consents, rather than being allowed through. Asset CDNs and font providers are exempt through the technical exceptions list, which you can edit. If something on your site stops working after the update, add its host to that list rather than turning the setting off.

= Can I translate it? =

Yes. The plugin is fully internationalised and ships with a Spanish translation. Other locales can be contributed through translate.wordpress.org.

== Changelog ==

= 1.8.0 =
* **One less request on every page view.** The consent log was being written on every page load rather than when a decision was made, so every visitor who had accepted sent an uncached request to the server on every page they opened. Decisions are recorded once now, and retried if the first attempt fails.
* **About 30 KB lighter.** The plugin's CSS and JavaScript are minified, and both scripts are deferred so they no longer block the page from rendering.
* **Less work per request.** Third-party discovery was scanning the whole page on every request; it now runs once an hour, like the record it updates.
* **The retention periods appeared in English on Spanish sites.** Fixed, along with the addition of German and French for everything a visitor reads.
* More tests: the geographic gate, the upgrade path from older versions, and the assets that get served.

= 1.7.1 =
* **Automatic updates never ran.** The address of the update manifest was shown only as a placeholder in the settings field, so the setting itself was empty on every install and the plugin never asked whether a newer version existed — it simply looked up to date. It now uses the official channel unless you enter a different one.
* The update channel, and the version last seen there, are shown in Diagnostics.
* Added a `piensa_cookie_consent_update_url` filter for installs that manage updates themselves.
* Note: this one update has to be installed by hand, since the fix is in the version being installed.

= 1.7.0 =
* **The blocker could leave a page completely blank.** On a long page — an ordinary size for one built with a visual builder — the pattern matching gave up, and the failure was served to the visitor as an empty page. Any failure now serves the page unchanged.
* **Visual builders no longer break.** Bricks, Elementor, Divi, Oxygen, Beaver Builder, Brizy, Breakdance, WPBakery, Visual Composer, Thrive, Zion and SiteOrigin render their editor on the front end, where the blocker was rewriting their own scripts and stopping the editor from loading. They are recognised and left alone, along with feeds, REST responses, AJAX and the customizer preview.
* **Scripts written with single quotes were never blocked**, only those with double quotes. They are blocked now.
* **The Google Analytics 4 session cookie is declared and cleared.** GA4 names it after the measurement id, so no fixed name could match: it was missing from the cookie list the visitor reads, and withdrawing consent left it behind.
* Retention periods now show the real values Google documents rather than "not declared by the provider".
* Added a `piensa_cookie_consent_should_block` filter to switch blocking off for a single request.

= 1.6.3 =
* **Google Analytics still measured as denied after accepting — 1.6.2 did not fix it.** The consent library passes its callbacks a wrapper object containing the cookie, and the plugin treated that wrapper as the cookie. Nothing inside those callbacks ran: Consent Mode was never told about the acceptance, blocked embeds such as maps or booking widgets were never released from behind their placeholder, and the consent log stored rows with no id, categories or timestamp.
* The Consent Mode update was also being queued in a shape the Google tag ignores. It now appears as a real gtag command, which is what makes the `_ga` cookies appear when someone accepts.
* Added a JavaScript test suite that checks the consent signal directly, because both faults were silent in the browser.

= 1.6.2 =
* **Google Analytics kept measuring as denied after the visitor accepted.** The Consent Mode update was sent through `window.gtag`, but the tag that defines it is blocked until consent is given, so at the moment someone accepted it did not exist yet and the update was skipped. Analytics received consent-denied hits for the rest of the page. It is now pushed onto the dataLayer, which works whichever loads first.
* The plugin's own Consent Mode script was being blocked by the plugin, for the same reason as the configuration script in 1.5.0: it contains a `gtag()` call, which is what the analytics rule matches on.

= 1.6.1 =
* **The Spanish tab was showing English.** Releases 1.0 to 1.5 used English as their source language, so an untouched install had English sitting in the Spanish settings. The migration read that as wording the site had chosen and carried it into the Spanish block. It now recognises those values and clears them, on sites that already migrated too, restoring the shipped Spanish.
* The Languages tab still carried its old subtitle saying Spanish was managed elsewhere.

= 1.6.0 =
* **Spanish, English, German and French, all pre-translated.** The banner text now ships in four languages and is edited in one place, with Spanish first. It was previously Spanish on one screen and English on another, and adding a third language would have meant a third set of fields.
* Leaving a field empty restores the shipped translation, so you only override the wording you actually want to change.
* **The theme colours are read less rigidly.** A theme with a tinted light tone — a warm off-white, a pale brand wash — now gets it instead of flat white, and a brand colour reaches the button instead of being flattened to black. Contrast is still checked throughout: the tint only wins if body text clears WCAG AAA on it.
* Your existing text is carried over. Anything left at its old default is dropped, so the site picks up the improved translations.

= 1.5.0 =
* **The plugin was blocking its own configuration.** Its settings script declares the cookie names and domains of the services it knows about — Hotjar's `_hj*`, `googletagmanager.com` — which is exactly what the inline-script rules match on. The script that tells the banner which categories to offer was therefore neutralised on every page, and the preferences dialog fell back to its defaults: one category, none of the site's own text, colours or policy links. Fixed; the plugin now recognises its own scripts.
* Domains and cookies can be forgotten one at a time. Forgetting a domain also drops the cookies recorded against it and any manual category override, so nothing reappears later.

= 1.4.2 =
* The release package is now reproducible: identical code produces identical bytes. It previously embedded file timestamps, so any rebuild changed the checksum and invalidated the update manifest, leaving the updater refusing a download that was perfectly good.

= 1.4.1 =
* The agency build can now update itself from GitHub. Each release publishes a static update manifest, so there is no update server to run.

= 1.4.0 =
* **The scanner now records which page each domain was found on**, shown as a new column. A domain nobody recognises was previously a dead end: there was no way to tell a real third party from a leftover of an earlier scan.
* **A button to clear the discovered list.** It only ever grew, so a domain that appeared once stayed for good, even after the page that introduced it was gone.
* A site addressed without `www` whose markup writes `www` was recording itself as a third party. Host comparison now normalises the prefix everywhere it is done.

= 1.3.2 =
* **Scan now found nothing on sites with an SEO plugin.** A sitemap index lists other sitemaps, not pages, and the scanner crawled those XML documents looking for script tags. Because the list was not empty it never fell back to the home page either, so the scan reported no third parties on a site full of them. It now follows a sitemap index to the sitemaps underneath, knows about WordPress's own `wp-sitemap.xml`, and always includes the home page.
* Hosts are compared with `www` normalised. A sitemap listing the www form on a site configured without it (or the reverse) made every URL look external, and the scan found nothing.
* Two copies of the plugin installed at once now produce an admin notice naming both folders, instead of a fatal error naming a class.
* The diagnostics report said no domain activated a category while a category was being shown. It now reports both routes — plugins detected and domains scanned — because either one can turn a category on.

= 1.3.1 =
* Added a Diagnostics section under Health: which categories the banner is offering and what decided that, whether the consent table exists and how many records it holds, what the scanner has found, and the database engine in use. When the banner offers the wrong categories or the log looks empty, this says which input produced that result instead of leaving you to guess.
* The scanner table headings and the in-browser audit messages were still fixed in Spanish.

= 1.3.0 =
* **Use the theme colors.** A button under Appearance reads the palette your theme declares and fills in the banner colours. It picks the background, the text and an accent, and derives the rest, checking WCAG contrast as it goes: the button label stays legible and the button stands out from the panel it sits on, whatever palette the theme happens to have. Themes that declare no palette say so instead of guessing.
* Added a test suite. It covers the colour logic against real theme palettes and found a bug before release.
* Two more admin strings that were still fixed in Spanish are now translatable.

= 1.2.1 =
* The consent log could read as permanently empty on installs using the SQLite integration, including WordPress Playground. The table was detected with `SHOW TABLES LIKE`, which is MySQL syntax and simply returns nothing there, so every read reported no table and every write was skipped. Detection now uses a recorded option, which also removes a database query from each read and write.
* The consent log status in the dashboard was shown in Spanish regardless of language, and unescaped.

= 1.2.0 =
* **Works with page caching.** The blocker varied the HTML by the visitor's consent cookie, so any page cache would store one visitor's version and serve it to everyone — releasing scripts to people who never accepted. The server now blocks unconditionally and the front-end script releases what the visitor accepted, so every visitor gets the same cacheable markup. Consent Mode likewise always declares the denied default, which is what Google documents.
* **No longer writes to the database on every page view.** Third-party discovery refreshed a timestamp in wp_options on each request. It now writes only for a host it has not seen, or once an hour, and the stored list is capped.
* The sitemap crawler only follows URLs belonging to the site. A sitemap entry naming another address would previously have been fetched.
* The public consent-logging endpoint is rate limited and caps the size of its JSON fields. It has to be public, and its nonce is shared by every anonymous visitor, so without a ceiling it was a way to fill the database.
* Consent records are purged daily past a retention period, two years by default and configurable. Keeping them indefinitely is its own compliance problem.
* The CSV export covers every record. It silently stopped at the first thousand, which made it poor evidence of anything.
* Settings are read once per request rather than on every call.
* The health check warns when geo-targeting and a page cache are both active, which is the one remaining case where the page still varies by visitor.

= 1.1.0 =
* Third-party resources the plugin does not recognise are now blocked until consent is given, instead of being allowed through. This is what the ePrivacy consent requirement asks for. A technical exceptions list, pre-filled with asset CDNs and font providers, keeps layout and scripts working.
* The banner now links to your cookie policy and privacy policy. The setting existed but the link was never rendered.
* The cookie policy now states whether each cookie is first- or third-party, gives real retention periods instead of "variable", and explains how to accept, refuse and withdraw consent.
* The plugin now declares its own consent cookie in the policy, which it previously omitted.
* The policy table headings are translatable; they were fixed in Spanish.

= 1.0.0 =
* First release in the WordPress.org plugin directory, renamed from PW Cookie Monster.
* Added WP Consent API integration, so other plugins can query consent state.
* Replaced the Font Awesome CDN dependency with inline Lucide icons; the plugin now loads no external assets.
* Centralised consent reading with proper input sanitisation.
* Full internationalisation, with a Spanish translation included.
* Added an uninstall routine that removes options and the consent log table, multisite included.
* Settings and the consent log migrate automatically from 0.5.x.

== Upgrade Notice ==

= 1.8.0 =
Removes a server request on every page view, cuts about 30 KB from every page, and fixes the cookie retention periods showing in English on Spanish sites.

= 1.7.1 =
Fixes automatic updates never running. Install this one by hand; later versions will be offered automatically.

= 1.7.0 =
Important if your site uses a visual builder such as Bricks or Elementor, or Google Analytics 4. Fixes the blocker blanking long pages and breaking builder editors, and declares and clears the GA4 session cookie.

= 1.6.3 =
Fixes Google Analytics still recording consent as denied after visitors accept, which 1.6.2 did not. Also restores blocked embeds being released on acceptance. Recommended for every site.

= 1.6.2 =
Fixes Google Analytics recording consent as denied even after visitors accept. Recommended for any site using Analytics or Ads.

= 1.6.1 =
Fixes the Spanish tab showing English text. Recommended for anyone who installed 1.6.0.

= 1.6.0 =
Banner text now ships in four languages, edited in one place. Your customised text is carried over.

= 1.5.0 =
Fixes the banner showing only the necessary category with none of your own text or colours. Clear any page cache after updating.

= 1.4.2 =
Release infrastructure only.

= 1.4.1 =
Release infrastructure only; no change to how the plugin behaves on a site.

= 1.4.0 =
The scanner now records where each domain was found. Clear the list and scan again to see only what your site loads today.

= 1.3.2 =
Fixes Scan now finding nothing on sites with an SEO plugin sitemap. Re-run the scan after updating.

= 1.3.1 =
Adds a diagnostics report under Health, for working out why the banner or the log is behaving as it is.

= 1.3.0 =
Adds a button that fills the banner colours from your theme's palette.

= 1.2.1 =
Fixes the consent log reading as empty on sites using the SQLite integration.

= 1.2.0 =
Fixes a serious interaction with page caching that could serve blocked scripts to visitors who never consented, and stops the plugin writing to the database on every page view. Recommended for every site.

= 1.1.0 =
Unrecognised third-party scripts are now blocked until consent is given. Review the technical exceptions list under Settings if anything on your site depends on an external host.

= 1.0.0 =
Renamed release with WP Consent API support and no external asset loading. Settings and consent records migrate automatically.
