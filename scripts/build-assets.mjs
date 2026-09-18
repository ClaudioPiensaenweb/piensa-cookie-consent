/**
 * Minify the plugin's CSS and JavaScript.
 *
 * The readable source stays where it is and is what the repository and the
 * wordpress.org package are reviewed on; the `.min` files sit next to it and
 * are what the plugin enqueues. The outputs are committed so that a plain
 * checkout works without a build step, and CI regenerates them and fails on a
 * difference, which is what stops a stale minified file being served.
 *
 * Usage: npm run build:assets
 */

import { build } from 'esbuild';
import { statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

/**
 * Sources to minify. The vendored consent library is left out on purpose: it
 * ships both its script and its stylesheet already minified, so running them
 * through again buys nothing and would put a second near-identical copy of
 * each into the package.
 */
const sources = [
    'assets/js/piensa-cookie-consent.js',
    'assets/js/piensa-cookie-consent-admin.js',
    'assets/js/piensa-cookie-consent-audit.js',
    'assets/css/piensa-cookie-consent.css',
    'assets/css/piensa-cookie-consent-admin.css',
];

const kb = bytes => (bytes / 1024).toFixed(1).padStart(6) + ' KB';

let before = 0;
let after = 0;

for (const source of sources) {
    const target = source.replace(/\.(js|css)$/, '.min.$1');

    await build({
        entryPoints: [join(root, source)],
        outfile: join(root, target),
        minify: true,
        // No bundling: these are plain browser scripts and stylesheets loaded
        // by WordPress, not modules with dependencies to pull in.
        bundle: false,
        legalComments: 'inline',
        target: ['es2019'],
        logLevel: 'warning',
    });

    const sourceSize = statSync(join(root, source)).size;
    const targetSize = statSync(join(root, target)).size;

    before += sourceSize;
    after += targetSize;

    const saved = Math.round((1 - targetSize / sourceSize) * 100);

    process.stdout.write(`${source.padEnd(46)} ${kb(sourceSize)} -> ${kb(targetSize)}  (-${saved}%)\n`);
}

process.stdout.write(`\n${'total'.padEnd(46)} ${kb(before)} -> ${kb(after)}  (-${Math.round((1 - after / before) * 100)}%)\n`);
