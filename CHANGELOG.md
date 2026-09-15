# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
