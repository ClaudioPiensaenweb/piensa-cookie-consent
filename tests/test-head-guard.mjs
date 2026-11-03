/**
 * Tests for the script that runs first in <head>.
 *
 * It holds back trackers that other scripts inject after the page has loaded,
 * which the server-side blocker never sees, and it sends returning visitors'
 * consent to Google before any tag runs. Both fail silently in a browser, so
 * the script runs here against a minimal DOM and its effects are inspected.
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
const source = readFileSync(join(here, '..', 'assets', 'js', 'piensa-cookie-consent-head.js'), 'utf8');

const rules = {
    enabled: true,
    site: ['example.es'],
    essential: ['js.stripe.com'],
    essentialPaths: [],
    allowed: ['cdn.jsdelivr.net'],
    blocked: [],
    analytics: ['googletagmanager.com', 'google-analytics.com'],
    marketing: ['connect.facebook.net'],
    blockUnknown: true,
};

class FakeElement {
    constructor(tag) {
        this.tagName = tag.toUpperCase();
        this.nodeType = 1;
        this.attributes = {};
    }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; }
    hasAttribute(name) { return name in this.attributes; }
    removeAttribute(name) { delete this.attributes[name]; }
}

function run(cookie = '', revision = 1) {
    const listeners = {};
    function Node() {}
    Node.prototype.appendChild = function (child) { return child; };
    Node.prototype.insertBefore = function (child) { return child; };
    function Element() {}

    const sandbox = {
        window: {
            location: { href: 'https://www.example.es/', hostname: 'www.example.es' },
            piensaCookieConsentHead: { revision, rules },
            addEventListener(name, fn) { listeners[name] = fn; },
        },
        document: { cookie },
        Node,
        Element,
        URL,
    };
    sandbox.window.window = sandbox.window;
    vm.createContext(sandbox);
    vm.runInContext(source.replace(/window\./g, 'window.'), sandbox);

    const parent = new Node();
    const add = (tag, src) => {
        const el = new FakeElement(tag);
        el.setAttribute('src', src);
        parent.appendChild(el);
        return el;
    };

    return { sandbox, add, listeners };
}

function consentCookie(categories, revision = 1) {
    return 'cc_cookie=' + encodeURIComponent(JSON.stringify({ categories, revision }));
}

test('defaults are denied and wait for the banner', () => {
    const { sandbox } = run();
    const call = Array.from(sandbox.window.dataLayer[0]);
    assert.equal(call[0], 'consent');
    assert.equal(call[1], 'default');
    assert.equal(call[2].analytics_storage, 'denied');
    assert.equal(call[2].ad_user_data, 'denied');
    assert.equal(call[2].wait_for_update, 500);
});

test('a returning visitor\'s consent is sent before any tag runs', () => {
    const { sandbox } = run(consentCookie(['necessary', 'analytics']));
    const update = sandbox.window.dataLayer.map(a => Array.from(a)).find(a => a[1] === 'update');
    assert.ok(update, 'an update is queued straight away');
    assert.equal(update[2].analytics_storage, 'granted');
    assert.equal(update[2].ad_storage, 'denied');
});

test('consent given under an older revision does not count', () => {
    const { sandbox, add } = run(consentCookie(['necessary', 'analytics'], 1), 2);
    assert.ok(!sandbox.window.dataLayer.some(a => a[1] === 'update'));
    const script = add('script', 'https://www.googletagmanager.com/gtm.js?id=GTM-X');
    assert.equal(script.getAttribute('type'), 'text/plain');
});

test('an injected analytics script is held until consent', () => {
    const { add } = run();
    const script = add('script', 'https://www.googletagmanager.com/gtm.js?id=GTM-X');
    assert.equal(script.getAttribute('type'), 'text/plain');
    assert.equal(script.getAttribute('data-category'), 'analytics');
});

test('an injected pixel is held and released on consent', () => {
    const { sandbox, add, listeners } = run();
    const pixel = add('img', 'https://connect.facebook.net/tr?id=1');
    assert.equal(pixel.getAttribute('src'), null);
    assert.equal(pixel.getAttribute('data-src'), 'https://connect.facebook.net/tr?id=1');

    sandbox.document.cookie = consentCookie(['necessary', 'marketing']);
    listeners['cc:onConsent']();
    assert.equal(pixel.getAttribute('src'), 'https://connect.facebook.net/tr?id=1');
});

test('an unknown third party is held as marketing', () => {
    const { add } = run();
    assert.equal(add('script', 'https://tracker.example.net/t.js').getAttribute('data-category'), 'marketing');
});

test('the site, essential and allowed hosts load normally', () => {
    const { add } = run();
    for (const src of ['/wp-content/x.js', 'https://example.es/a.js', 'https://js.stripe.com/v3/', 'https://cdn.jsdelivr.net/x.js']) {
        assert.equal(add('script', src).getAttribute('type'), null, src);
    }
});

test('an accepted category loads normally', () => {
    const { add } = run(consentCookie(['necessary', 'analytics']));
    assert.equal(add('script', 'https://www.googletagmanager.com/gtag/js').getAttribute('type'), null);
});
