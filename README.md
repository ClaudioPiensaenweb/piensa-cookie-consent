# Piensa Cookie Consent

A GDPR and ePrivacy consent management platform for WordPress, built for
agencies that need the banner to actually block things before consent.

Everything runs on the site's own server: no cloud service, no licence check,
no third-party CDN and no visitor data leaving the host.

## What it does

- **Blocks before consent.** Third-party scripts and iframes are neutralised
  until the visitor opts in. Images and external stylesheets optionally too.
- **Google Consent Mode v2.** Sets the denied default and updates it the moment
  the visitor chooses.
- **Finds the cookies.** Crawls the sitemap for external domains, reads
  `Set-Cookie` headers, and runs an in-browser audit for the cookies scripts set
  at runtime. Known services are categorised automatically.
- **Consent log.** One row per choice, with a hashed IP rather than the address,
  exportable to CSV.
- **Geo-targeting.** EEA, UK and Switzerland, a custom country list, or global.
- **WP Consent API.** Registers as the site's consent manager so other plugins
  can ask before setting a cookie.

## Requirements

| | |
|---|---|
| WordPress | 6.0 or later |
| PHP | 7.4 or later |

## Repository layout

```
piensa-cookie-consent.php   Plugin bootstrap and headers
includes/                   Plugin classes, one per file
assets/                     Front-end and admin CSS/JS
languages/                  .pot template and shipped translations
templates/                  Front-end partials
scripts/                    Release build and version checks
.wordpress-org/             Plugin directory assets and Playground blueprint
```

## Development

```bash
composer install
npm ci
```

| Command | What it does |
|---|---|
| `composer lint` | WordPress Coding Standards (PHPCS) |
| `composer analyse` | Static analysis (PHPStan, level 5) |
| `npm run lint:js` | ESLint over the front-end scripts |
| `npm run build` | Build the WordPress.org release ZIP |
| `npm run build:agency` | Build the ZIP that keeps the self-hosted updater |

### Translations

English is the source language. Regenerate the template after changing any
string:

```bash
npm run i18n:pot
```

The Spanish translation lives in `languages/piensa-cookie-consent-es_ES.po`.
The `.mo` files are build artefacts, compiled during the release build rather
than committed.

## Two builds, one codebase

The plugin ships in two shapes:

- **The WordPress.org package**, built by `scripts/build-release.sh`. It strips
  the dev tooling and `includes/class-updater.php`, because [guideline
  #8](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
  forbids a plugin in the directory from serving its own updates.
- **The agency package**, built with `--keep-updater`, installed by hand on
  client sites and updated from our own signed update server.

The code detects which build it is in, so the update settings never appear in a
site that cannot use them. A site can also opt out explicitly:

```php
define( 'PIENSA_COOKIE_CONSENT_DISABLE_UPDATER', true );
```

## Releasing

1. Update the version in `piensa-cookie-consent.php` (header and constant),
   `readme.txt` (`Stable tag`) and `package.json`.
2. Add the changelog entries to `readme.txt` and `CHANGELOG.md`.
3. Verify they agree: `bash scripts/check-version.sh`.
4. Tag and publish a GitHub release. The `deploy` workflow builds the package
   and pushes it to the plugin directory over SVN.

The deploy workflow needs two repository secrets, `SVN_USERNAME` and
`SVN_PASSWORD`, holding the WordPress.org account that owns the plugin.

## Continuous integration

| Workflow | Gate |
|---|---|
| `quality.yml` | PHP 7.4 and 8.3 syntax, PHPCS, PHPStan, ESLint, and an assertion that the updater never reaches the wp.org package |
| `plugin-check.yml` | Plugin Check against the built package, not the working tree |
| `deploy.yml` | Version consistency, then SVN deploy on a published release |

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).

Icons are from [Lucide](https://lucide.dev) (ISC), embedded inline rather than
loaded from a CDN.
