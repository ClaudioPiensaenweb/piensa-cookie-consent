/**
 * Runs first in <head>, inline, before any other script on the page.
 *
 * Three jobs that cannot wait for the deferred banner script:
 *
 * 1. Google Consent Mode defaults, with `wait_for_update` so tags loaded by
 *    GTM hold their hits until the banner answers.
 * 2. For a returning visitor, the `update` call from their stored choice,
 *    straight away. Issued from the deferred script it came after
 *    DOMContentLoaded, and every tag that ran before it saw "denied".
 * 3. A guard on scripts, iframes and pixels added after the page loaded.
 *    The server-side blocker only sees the HTML it sends; a GTM container or
 *    a Meta pixel injected by another script used to go straight through.
 *
 * Reads only the visitor's own cookie, in the browser, so the HTML stays the
 * same for everyone and pages remain safe to cache.
 *
 * Configuration arrives as window.piensaCookieConsentHead (see
 * class-consent-mode.php).
 */
(function () {
    'use strict';

    var config = window.piensaCookieConsentHead || {};
    var rules = config.rules || {};

    window.piensaCookieConsentMode = 1;
    window.dataLayer = window.dataLayer || [];
    function gtag() {
        window.dataLayer.push(arguments);
    }

    gtag('consent', 'default', {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        wait_for_update: 500,
    });
    gtag('set', 'ads_data_redaction', true);
    if (config.urlPassthrough) {
        gtag('set', 'url_passthrough', true);
    }

    function storedCategories() {
        var match = document.cookie.match(/(?:^|;\s*)cc_cookie=([^;]*)/);
        if (!match) {
            return null;
        }
        try {
            var value = JSON.parse(decodeURIComponent(match[1]));
            // A choice made under an earlier revision of the policy no longer
            // counts: the banner asks again, and nothing loads meanwhile.
            if (Number(value.revision || 0) !== Number(config.revision || 0)) {
                return null;
            }
            return Array.isArray(value.categories) ? value.categories : null;
        } catch {
            return null;
        }
    }

    function granted(category) {
        var categories = storedCategories();
        return !!categories && categories.indexOf(category) !== -1;
    }

    var stored = storedCategories();
    if (stored) {
        var analytics = stored.indexOf('analytics') !== -1 ? 'granted' : 'denied';
        var marketing = stored.indexOf('marketing') !== -1 ? 'granted' : 'denied';
        gtag('consent', 'update', {
            analytics_storage: analytics,
            ad_storage: marketing,
            ad_user_data: marketing,
            ad_personalization: marketing,
        });
    }

    function hostOf(url) {
        try {
            return new URL(url, window.location.href).hostname.toLowerCase();
        } catch {
            return '';
        }
    }

    function matches(host, list) {
        for (var i = 0; i < (list || []).length; i++) {
            var domain = String(list[i]).toLowerCase();
            if (host === domain || host.slice(-domain.length - 1) === '.' + domain) {
                return true;
            }
        }
        return false;
    }

    /**
     * The category a URL needs, or null when it may load now. Mirrors the
     * server-side blocker's decision.
     */
    function categoryFor(url) {
        if (!rules.enabled || !url || /^(data|blob|javascript):/i.test(url)) {
            return null;
        }
        var lower = String(url).toLowerCase();
        for (var p = 0; p < (rules.essentialPaths || []).length; p++) {
            if (lower.indexOf(String(rules.essentialPaths[p]).toLowerCase()) !== -1) {
                return null;
            }
        }
        var host = hostOf(url);
        if (!host || host === window.location.hostname || matches(host, rules.site)) {
            return null;
        }
        if (matches(host, rules.essential) || matches(host, rules.allowed)) {
            return null;
        }
        var category = null;
        if (matches(host, rules.blocked)) {
            category = 'marketing';
        } else if (matches(host, rules.analytics)) {
            category = 'analytics';
        } else if (matches(host, rules.marketing)) {
            category = 'marketing';
        } else if (rules.blockUnknown) {
            category = 'marketing';
        }
        return category && !granted(category) ? category : null;
    }

    var held = [];

    function neutralise(node) {
        if (!node || node.nodeType !== 1 || node.hasAttribute('data-pcc-held')) {
            return;
        }
        var tag = node.tagName;
        if (tag !== 'SCRIPT' && tag !== 'IFRAME' && tag !== 'IMG') {
            return;
        }
        var src = node.getAttribute('src');
        if (!src || (tag === 'SCRIPT' && node.getAttribute('type') === 'text/plain')) {
            return;
        }
        var category = categoryFor(src);
        if (!category) {
            return;
        }
        node.setAttribute('data-pcc-held', category);
        if (tag === 'SCRIPT') {
            // The banner script runs every `script[data-category]` it finds
            // when that category is accepted, as it does for the ones the
            // server neutralised.
            node.setAttribute('type', 'text/plain');
            node.setAttribute('data-category', category);
        } else {
            node.setAttribute('data-src', src);
            node.removeAttribute('src');
            held.push(node);
        }
    }

    function guard(original) {
        return function (node) {
            neutralise(node);
            if (node && node.nodeType === 11) {
                Array.prototype.forEach.call(node.querySelectorAll('script,iframe,img'), neutralise);
            }
            return original.apply(this, arguments);
        };
    }

    Node.prototype.appendChild = guard(Node.prototype.appendChild);
    Node.prototype.insertBefore = guard(Node.prototype.insertBefore);
    if (Element.prototype.append) {
        var append = Element.prototype.append;
        Element.prototype.append = function () {
            Array.prototype.forEach.call(arguments, neutralise);
            return append.apply(this, arguments);
        };
    }

    // Iframes and pixels come back once their category is accepted.
    function release() {
        held = held.filter(function (node) {
            var category = node.getAttribute('data-pcc-held');
            if (!granted(category)) {
                return true;
            }
            node.setAttribute('src', node.getAttribute('data-src'));
            node.removeAttribute('data-src');
            node.removeAttribute('data-pcc-held');
            return false;
        });
    }
    window.addEventListener('cc:onConsent', release);
    window.addEventListener('cc:onChange', release);
})();
