/**
 * Tests for bringing theme colours to WCAG AA in the banner.
 *
 * Found on emedemuro.es: the theme's secondary grey, #bababa, was the banner's
 * body text on white, a contrast of 1.9:1 against the 4.5:1 WCAG AA asks for.
 *
 * Run with `npm run test:js` or `node --test tests/`.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import vm from 'node:vm';

const here = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(join(here, '..', 'assets', 'js', 'piensa-cookie-consent.js'), 'utf8');
const helpers = source.slice(source.indexOf('function parseColor'), source.indexOf('function applyThemeVars'));
const sandbox = {};
vm.createContext(sandbox);
vm.runInContext(helpers + ';this.api = { ensureContrast, contrastRatio, parseColor };', sandbox);
const { ensureContrast, contrastRatio, parseColor } = sandbox.api;

const ratio = (a, b) => contrastRatio(parseColor(a), parseColor(b));

test('a light grey on white is darkened to AA', () => {
    const fixed = ensureContrast('#bababa', '#ffffff');
    assert.ok(ratio(fixed, '#ffffff') >= 4.5);
});

test('a brand button background is darkened under white text', () => {
    const fixed = ensureContrast('#887b6b', '#ffffff');
    assert.ok(ratio(fixed, '#ffffff') >= 4.5);
    assert.notEqual(fixed, '#000000', 'only as much as needed');
});

test('a light text on a dark banner is lightened', () => {
    assert.ok(ratio(ensureContrast('#555555', '#111111'), '#111111') >= 4.5);
});

test('colours that already pass are left alone', () => {
    assert.equal(ensureContrast('#1d2327', '#ffffff'), '#1d2327');
});

test('colours it cannot read are left alone', () => {
    assert.equal(ensureContrast('var(--brand)', '#ffffff'), 'var(--brand)');
    assert.equal(ensureContrast('rgb(186, 186, 186)', '#fff').startsWith('#'), true);
});
