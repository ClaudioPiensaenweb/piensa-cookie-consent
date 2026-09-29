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

## What is never blocked

Payment gateways and fraud checks are exempt from consent under ePrivacy: a
payment is the service the buyer asked for, and a fraud check protects them.
Blocking either protects nobody — a neutralised payment script takes the card
fields off the checkout page, so the shop stops taking money from every visitor
who has not accepted marketing.

That list lives in `Scanner::get_essential_hosts()` rather than in the settings,
because the allowed-domains list is editable and an editable list is one
somebody can empty by accident. reCAPTCHA is matched by path instead of host,
since it is served from the same hosts as Google Maps.

A gateway that is not on the list can be added without touching the plugin:

```php
add_filter(
	'piensa_cookie_consent_essential_hosts',
	static function ( $hosts ) {
		$hosts[] = 'gateway.example.com';

		return $hosts;
	}
);
```

Hosts that merely serve fonts, icons and libraries — and set no cookies — are a
separate, editable list in `includes/data/technical-hosts.json`, which seeds the
allowed domains of a new install.

## Deciding about a domain once, for every site

The allowed and blocked domain lists are a per-site setting, which is fine for
one site and tedious across thirty. Both lists run through a filter, so the
decision can live in a single must-use plugin installed everywhere:

```php
<?php
/**
 * Plugin Name: Piensa Cookie Consent — agency defaults
 */

add_filter(
	'piensa_cookie_consent_allowed_domains',
	static function ( $domains ) {
		// A host that serves assets and sets no cookies. Allowed rather than
		// left unrecognised, which would block it as marketing and leave the
		// image missing until the visitor accepted.
		$domains[] = 'example.com';

		return $domains;
	}
);
```

`piensa_cookie_consent_blocked_domains` takes the same shape. Entries are
lower-cased and de-duplicated, and they are added to whatever the site's own
settings already list rather than replacing them.

A third filter, `piensa_cookie_consent_should_block`, switches the whole
rewriting pass off for one request.

## Keeping cookie retention periods current

`_ga`, `_gid`, `_fbp` and `fr` are declared with a retention period this plugin
wrote down at release time. A daily cron job checks those four names against
[Cookiedatabase.org](https://cookiedatabase.org/), the shared database Complianz
and other consent tools already read from, and shows its answer instead if one
comes back.

This does not identify new hosts. Cookiedatabase.org's public API resolves by
cookie *name* — "how long does `_ga` live" — with no way to go from a
discovered *host* to a service, so it could not have caught something like the
Stripe blocking incident this release line grew out of. It only keeps four
declared retention figures from silently going stale. Disable it with:

```php
add_filter( 'piensa_cookie_consent_sync_retention', '__return_false' );
```

A failed lookup — no network, a bad response, a cookie the database has
nothing on — leaves the plugin's own built-in figure standing; nothing here
can leave a cookie undeclared.

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

## Updates for the agency build

The agency package carries a self-hosted updater. It polls a static JSON
manifest, so there is no update server to run: the release workflow builds the
manifest and publishes it to GitHub Pages.

Point the client site at it under **Tools → Secure updates**:

```
https://claudiopiensaenweb.github.io/piensa-cookie-consent/update.json
```

The manifest names the **agency** ZIP, not the wp.org one. A site updating to
the wp.org package would lose the updater and stop receiving updates
altogether, which is why both are published to every release under distinct
names.

### Signing

The updater verifies an RSA signature over `version|package|checksum` when the
site requires one. Add the private key as the `UPDATE_SIGNING_KEY` repository
secret and the workflow signs each manifest:

```bash
openssl genrsa -out update-signing.key 4096
openssl rsa -in update-signing.key -pubout -out update-signing.pub
```

Paste the private key into the secret and the public key into the plugin's
**Public key** field on each client site.

Without the secret the manifest ships unsigned, and those sites have to turn
off *Require a valid signature*. The checksum is verified either way, so a
corrupted or swapped download is still rejected; the signature is what protects
against the manifest itself being tampered with.

## Directory assets

`.wordpress-org/` holds the artwork the plugin directory shows. It is generated
rather than drawn by hand, so the sizes stay in step:

```bash
python scripts/build-directory-assets.py
```

The mark is a plain geometric biscuit, deliberately generic. The artwork this
replaced was the Sesame Street character — the trademark that forced the
rename — and something that merely resembled it would put the submission back
where it started.

Screenshots are not generated: they have to come from a real install, named
`screenshot-1.png` upward to match the order in `readme.txt`.

## Releasing

1. Update the version in `piensa-cookie-consent.php` (header and constant),
   `readme.txt` (`Stable tag`) and `package.json`.
2. Add the changelog entries to `readme.txt` and `CHANGELOG.md`.
3. Verify they agree: `bash scripts/check-version.sh`.
4. Tag and publish a GitHub release. The `deploy` workflow builds both
   packages, attaches them to the release, publishes the update manifest, and
   pushes to the plugin directory over SVN.

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
