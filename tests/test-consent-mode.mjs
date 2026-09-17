/**
 * Tests for the Consent Mode signal the front-end script sends.
 *
 * Two bugs shipped here went unnoticed because both fail silently: the browser
 * raises nothing, the banner behaves normally, and only a Google Analytics
 * report weeks later shows the traffic was measured as consent-denied. So the
 * script is loaded into a sandbox with just enough DOM to run, and the command
 * it queues is inspected directly.
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

/**
 * A DOM node that accepts anything the script does to it and reports nothing
 * back. The script only ever writes to the elements it creates, so recording
 * the writes is unnecessary; not throwing is the whole job.
 */
function fakeElement() {
    const node = {
        style: { setProperty() {} },
        classList: { add() {}, remove() {} },
        dataset: {},
        children: [],
        tagName: 'DIV',
        appendChild(child) { node.children.push(child); return child; },
        setAttribute() {},
        removeAttribute() {},
        getAttribute() { return null; },
        addEventListener() {},
        querySelector() { return null; },
        querySelectorAll() { return []; },
        closest() { return null; },
        insertAdjacentHTML() {},
        remove() {},
    };

    return node;
}

/**
 * Load the script with a stubbed browser and hand back the configuration it
 * passes to CookieConsent.run(), which is where the callbacks live.
 */
function loadScript() {
    const sandbox = {
        console,
        navigator: { language: 'es-ES' },
        location: { href: 'https://example.test/' },
        sessionStorage: { getItem() { return null; }, setItem() {}, removeItem() {} },
        fetch() { return Promise.resolve(); },
        MutationObserver: class { observe() {} disconnect() {} },
        setTimeout,
        clearTimeout,
        document: {
            documentElement: fakeElement(),
            body: fakeElement(),
            addEventListener(event, handler) { sandbox.__ready = handler; },
            createElement() { return fakeElement(); },
            querySelector() { return null; },
            querySelectorAll() { return []; },
            getElementById() { return null; },
        },
    };

    sandbox.window = sandbox;

    let runConfig = null;

    sandbox.CookieConsent = {
        run(config) { runConfig = config; },
        getCookie() { return {}; },
        showPreferences() {},
        acceptCategory() {},
    };

    sandbox.PiensaCookieConsentConfig = {
        categories: { analytics: true, marketing: true },
        cookieDefinitions: {},
        policy: {},
        ui: {},
        theme: {},
        language: { active: 'es', texts: { es: {} } },
        services: {},
    };

    vm.createContext(sandbox);
    vm.runInContext(source, sandbox);

    assert.ok(typeof sandbox.__ready === 'function', 'the script registers a DOMContentLoaded handler');
    sandbox.__ready();

    assert.ok(runConfig, 'the script calls CookieConsent.run()');

    return { sandbox, runConfig };
}

/**
 * The last thing queued on the dataLayer.
 */
function lastCommand(sandbox) {
    const layer = sandbox.dataLayer || [];

    return layer[layer.length - 1];
}

test('a consent command reaches the dataLayer as an arguments object', () => {
    const { sandbox, runConfig } = loadScript();

    // The shape the library actually passes: one detail object wrapping the
    // cookie. Taking the argument for the cookie itself left `categories`
    // undefined and the whole callback returned before doing anything.
    runConfig.onConsent({ cookie: { categories: ['necessary', 'analytics'] } });

    const command = lastCommand(sandbox);

    assert.ok(command, 'the callback queued something');

    // The Google tag recognises consent commands by this exact type. A plain
    // array is accepted by dataLayer.push and then read as an ordinary event,
    // so the update is discarded and measurement stays denied.
    assert.equal(
        Object.prototype.toString.call(command),
        '[object Arguments]',
        'the command is an arguments object, not an array'
    );
    assert.ok(!Array.isArray(command), 'the command is not a plain array');

    assert.equal(command[0], 'consent');
    assert.equal(command[1], 'update');
    assert.equal(command[2].analytics_storage, 'granted');
    assert.equal(command[2].ad_storage, 'denied');
});

test('accepting everything grants all four signals', () => {
    const { sandbox, runConfig } = loadScript();

    runConfig.onFirstConsent({ cookie: { categories: ['necessary', 'analytics', 'marketing'] } });

    const state = { ...lastCommand(sandbox)[2] };

    assert.deepEqual(state, {
        analytics_storage: 'granted',
        ad_storage: 'granted',
        ad_user_data: 'granted',
        ad_personalization: 'granted',
    });
});

test('rejecting leaves every signal denied', () => {
    const { sandbox, runConfig } = loadScript();

    runConfig.onChange({ cookie: { categories: ['necessary'] }, changedCategories: ['analytics'] });

    const state = { ...lastCommand(sandbox)[2] };

    assert.deepEqual(state, {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
});

test('withdrawing consent sends a fresh denial rather than staying silent', () => {
    const { sandbox, runConfig } = loadScript();

    runConfig.onConsent({ cookie: { categories: ['necessary', 'analytics', 'marketing'] } });
    runConfig.onChange({ cookie: { categories: ['necessary'] }, changedCategories: ['analytics', 'marketing'] });

    assert.equal(sandbox.dataLayer.length, 2, 'both decisions were signalled');
    assert.equal(lastCommand(sandbox)[2].analytics_storage, 'denied');
});

test('a callback with no cookie is ignored instead of throwing', () => {
    const { sandbox, runConfig } = loadScript();

    runConfig.onConsent({});
    runConfig.onConsent(undefined);

    assert.equal((sandbox.dataLayer || []).length, 0, 'nothing was queued');
});

test('a wildcard cookie name is cleared by pattern, not by its literal name', () => {
    const { sandbox } = loadScript();

    // GA4 names its session cookie after the measurement id, so the plugin
    // declares the family as `_ga_*`. Handed to the library as a literal
    // string it matched nothing: withdrawing consent cleared `_ga` and left
    // `_ga_G1AB2CD3EF` behind, still identifying the visitor.
    const matcher = sandbox.cookieMatcher({ name: '_ga_*', is_pattern: true });

    // Built inside the sandbox, so it belongs to that realm and `instanceof`
    // against this one would say no to a perfectly good regular expression.
    assert.equal(Object.prototype.toString.call(matcher), '[object RegExp]', 'a pattern becomes a regular expression');
    assert.ok(matcher.test('_ga_G1AB2CD3EF'), 'it matches a real GA4 session cookie');
    assert.ok(matcher.test('_ga_8TD9SVBD6Z'), 'it matches another property');
    assert.ok(!matcher.test('_gali'), 'it does not match an unrelated cookie that merely starts alike');
    assert.ok(!matcher.test('x_ga_1'), 'it is anchored at the start');
});

test('a plain cookie name is left as an exact name', () => {
    const { sandbox } = loadScript();

    assert.equal(sandbox.cookieMatcher({ name: '_ga' }), '_ga');
    assert.equal(sandbox.cookieMatcher({ name: 'cc_cookie', is_pattern: false }), 'cc_cookie');
});

test('the throttling cookie family covers both tag versions', () => {
    const { sandbox } = loadScript();

    const matcher = sandbox.cookieMatcher({ name: '_gat*', is_pattern: true });

    assert.ok(matcher.test('_gat'), 'the classic tag name');
    assert.ok(matcher.test('_gat_gtag_UA_1_1'), 'the gtag.js name');
});
