# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
