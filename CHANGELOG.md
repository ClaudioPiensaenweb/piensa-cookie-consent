# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.2] - 2026-09-15

Reported from a live site: "Scan now" found nothing, while the in-browser
audit did.

### Fixed
- **The crawler was reading sitemaps, not pages.** A sitemap index lists other
  sitemaps; the scanner put those URLs straight into the crawl queue and looked
  for script tags in XML. Nothing was found, and because the queue was not
  empty the fall back to the home page never ran either — so a site with Yoast
  or RankMath scanned clean however many third parties it loaded. The index is
  now followed one level down, `wp-sitemap.xml` is recognised, and the home
  page is always in the queue.
- Host comparison normalises `www`. A sitemap listing `www.example.com` on a
  site whose home_url() is `example.com` made every URL look like someone
  else's, and the SSRF guard added in 1.2.0 discarded them all.
- A second copy of the plugin now produces an admin notice naming both
  folders. Two copies declare the same classes, and the fatal error that
  results names a class rather than the problem.
- The diagnostics report contradicted itself, saying no domain activated a
  category while the category was shown. A category has two independent
  routes — a detected plugin or a scanned domain — and only one was reported.

## [1.3.1] - 2026-09-15

Installing the plugin on a real site turned up behaviour that reading the code
did not explain. This release makes that state visible rather than guessing at
it again.

### Added
- A **Diagnostics** section under Health: which categories the banner is
  offering and what decided that, whether the consent table exists and how many
  records it holds, what the scanner has found, the database engine in use and
  which build is installed. Plus the same report as plain text, to paste into a
  support thread.

### Fixed
- The scanner table headings and the in-browser audit messages were still
  hardcoded in Spanish.

## [1.3.0] - 2026-09-15

### Added
- **Colours from the theme's own palette.** A button under Appearance reads
  `wp_get_global_settings()` and fills the banner colours in. Parsing the
  theme's CSS was the alternative and is a poor one: colours live in custom
  properties, preprocessor output and media queries, and picking "the brand
  colour" out of CSS text is guesswork that shows up directly in the banner.
  The palette is the theme's own declaration of the same thing.

  Slugs vary between themes, so a named slug wins when present and relative
  luminance decides otherwise, which keeps the result sensible on a palette
  using names the plugin has never seen. Contrast is checked throughout: the
  button label is black or white by WCAG luminance, and an accent that does not
  reach 3:1 against the background is passed over even when its slug says it is
  the accent — a theme can call a pale tint "accent-1", and on a button over a
  near-white panel that reads as no button at all.
- **A test suite**, run on both supported PHP versions in CI. It covers the
  colour logic against real theme palettes, and caught a normalisation bug in
  three-digit hex colours before release.

### Changed
- Two more admin strings that were still hardcoded in Spanish.

## [1.2.1] - 2026-09-15

Found by running the plugin in WordPress Playground rather than reading it.

### Fixed
- The consent log read as permanently empty under the SQLite integration.
  `SHOW TABLES LIKE` is MySQL syntax; SQLite answers nothing, so every read
  concluded the table was missing and every write was skipped. The table is now
  recorded in an option at creation, which also takes a query out of each read
  and each write.
- The consent log status in the dashboard was hardcoded in Spanish and printed
  without escaping.

## [1.2.0] - 2026-09-15

Findings from a security and scalability review.

### Fixed
- **Page caching could release blocked scripts to visitors who never
  consented.** `get_allowed_categories()` read the consent cookie, so the
  generated HTML differed per visitor. Any page cache — and most production
  sites run one — stores what the first visitor generated and serves it to
  everyone. The server now blocks unconditionally and the front-end script
  releases what the visitor accepted, which is how the bundled library is meant
  to work. Consent Mode always declares the denied default for the same reason,
  and because that is what Google documents.
- **An UPDATE against `wp_options` on every front-end request.** Third-party
  discovery rewrote `last_seen` each time. It now writes only for an unseen
  host or once an hour, and caps the stored list at 500 entries.
- The sitemap crawler followed any URL a `<loc>` element named, including
  addresses that are not the site's. An entry pointing at an internal address
  would have been fetched and its cookies stored.
- The public consent endpoint had no rate limit and no size cap on its JSON
  fields. It is public by necessity and its nonce is shared by every anonymous
  visitor, so it was a way to fill the database.
- The CSV export stopped at 1000 records without saying so.
- `get_settings()` re-read the option and a file from disk on every call, of
  which there are many per request.

### Added
- A retention period for consent records, two years by default, purged daily
  and cleared on deactivation.
- A health check warning for the one case the fix above does not cover:
  geo-targeting decides per visitor whether the banner runs at all, so with a
  page cache in front the first version generated is served to everyone.

## [1.1.0] - 2026-09-15

Closes the gaps between what the plugin did and what an ePrivacy consent
requirement actually asks for.

### Changed
- **Unrecognised third parties are blocked by default.** `should_block_category()`
  let anything it could not classify through, so a third-party script the plugin
  had no entry for ran before the visitor chose. Unclassified third parties are
  now treated as marketing and held back until that category is accepted.
  First-party resources are unaffected, and a technical exceptions list —
  pre-filled with asset CDNs and font providers — keeps layout intact.
- The cookie policy table now states first- or third-party origin, and gives
  real retention periods. `'variable'` is not a retention period.
- Policy table headings are translatable; they were fixed in Spanish.

### Added
- The banner links to the cookie policy and privacy policy. Both URLs were
  already configurable and passed to the front end, but the footer that should
  have rendered them was empty.
- The policy explains how to accept, refuse and withdraw consent, and embeds
  the review button. Being able to withdraw is not the same as saying how.
- The plugin declares its own consent cookie, which the policy omitted.

## [1.0.0] - 2026-09-15

First release in the WordPress.org plugin directory. The plugin was previously
distributed privately as *PW Cookie Monster*.

### Added
- WP Consent API integration, so other plugins can query the consent state
  instead of shipping a banner of their own.
- `uninstall.php`, which removes options and drops the consent log table,
  multisite included.
- Automatic migration of settings, detected cookies and the consent log from
  the `agency_shield_cmp_` prefix used up to 0.5.8.
- Continuous integration: coding standards, static analysis, Plugin Check
  against the real release package, and a deploy workflow to the plugin
  directory.

### Changed
- Renamed to **Piensa Cookie Consent**. The previous name used a third-party
  trademark, which the plugin directory does not accept.
- Consent reading is centralised in one class with proper sanitisation, rather
  than duplicated across the blocker and Consent Mode.
- Every user-facing string is now translatable, with English as the source
  language and a Spanish translation included.

### Removed
- The Font Awesome CDN dependency. The four icons in use are now inline SVG
  from Lucide, so the plugin loads nothing from a third-party server.
- The self-hosted updater is excluded from the WordPress.org package, which
  guideline #8 requires. The agency build still carries it.

## [0.5.8] - 2026-01-30

See `readme.txt` for the history of releases before the rename.
