// assets/js/piensa-cookie-consent.js

function activate_blocked_content(cookie) {
    if (!cookie || !cookie.categories) {
        return;
    }

    const blockedIframes = document.querySelectorAll('.ag-blocked-content[data-cookie-category]');

    blockedIframes.forEach(node => {
        const category = node.getAttribute('data-cookie-category');
        if (category && !cookie.categories.includes(category)) {
            return;
        }

        const service = node.getAttribute('data-cookie-service');
        if (service && cookie.services && cookie.services[category] && !cookie.services[category].includes(service)) {
            return;
        }

        if (node.tagName === 'LINK') {
            const realHref = node.getAttribute('data-href');
            if (realHref) {
                node.setAttribute('href', realHref);
                node.removeAttribute('data-href');
            }
            const realRel = node.getAttribute('data-rel');
            if (realRel) {
                node.setAttribute('rel', realRel);
                node.removeAttribute('data-rel');
            }
            node.classList.remove('ag-blocked-content');
            return;
        }

        const realSrc = node.getAttribute('data-src');
        if (realSrc) {
            node.setAttribute('src', realSrc);
            node.removeAttribute('data-src');
            node.classList.remove('ag-blocked-content');
        }

        const wrapper = node.closest('.ag-placeholder-wrapper');
        const overlay = wrapper ? wrapper.querySelector('.ag-placeholder-overlay') : null;
        if (overlay) {
            overlay.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    maybeAddFloatingConsentButton();

    document.querySelectorAll('.ag-btn-accept-marketing').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.CookieConsent && CookieConsent.acceptCategory) {
                const service = btn.getAttribute('data-ag-service');
                const category = btn.getAttribute('data-ag-category') || 'marketing';
                if (service && CookieConsent.acceptService) {
                    CookieConsent.acceptService(service, category);
                } else {
                    CookieConsent.acceptCategory(category);
                }
            }
        });
    });

    if (window.CookieConsent && CookieConsent.run) {
        const config = window.PiensaCookieConsentConfig || {};
        const categories = config.categories || {};
        const enabledFlags = {
            analytics: !!categories.analytics,
            marketing: !!categories.marketing,
        };
        const definitions = config.cookieDefinitions || {};
        const policy = config.policy || {};
        const ui = config.ui || {};
        const theme = config.theme || {};
        const languageConfig = config.language || {};
        const activeLang = resolveLanguage(languageConfig);
        const texts = languageConfig.texts || {};
        const sectionsEs = buildPreferenceSections(definitions, enabledFlags, texts.es || {});
        const sectionsEn = buildPreferenceSections(definitions, enabledFlags, texts.en || {});
        const autoClear = buildAutoClear(definitions, enabledFlags);
        const servicesConfig = buildServicesConfig(config.services || {}, enabledFlags);
        const allowNecessaryToggle = !!ui.allowNecessaryToggle;

        applyThemeVars(theme);

        CookieConsent.run({
            mode: 'opt-in',
            revision: Number(policy.revision || 0),
            autoClearCookies: true,
            manageScriptTags: true,
            guiOptions: {
                consentModal: {
                    layout: ui.consentLayout || 'box',
                    position: ui.consentPosition || 'bottom right',
                    equalWeightButtons: true,
                },
                preferencesModal: {
                    layout: ui.preferencesLayout || 'box',
                    position: ui.preferencesPosition || 'right',
                },
            },
            language: {
                default: activeLang,
                autoDetect: resolveAutoDetect(languageConfig),
                translations: buildTranslations(languageConfig, sectionsEs, sectionsEn, policy, config.brand),
            },
            categories: {
                necessary: { enabled: true, readOnly: !allowNecessaryToggle },
                ...(enabledFlags.analytics
                    ? {
                          analytics: {
                              enabled: true,
                              autoClear: autoClear.analytics,
                              ...(Object.keys(servicesConfig.analytics || {}).length ? { services: servicesConfig.analytics } : {}),
                          },
                      }
                    : {}),
                ...(enabledFlags.marketing
                    ? {
                          marketing: {
                              enabled: true,
                              autoClear: autoClear.marketing,
                              ...(Object.keys(servicesConfig.marketing || {}).length ? { services: servicesConfig.marketing } : {}),
                          },
                      }
                    : {}),
            },
            onFirstConsent: function(cookie) {
                update_consent_mode(cookie);
                activate_blocked_content(cookie);
                updateConsentStatus(cookie);
                logConsent(cookie, 'first');
            },
            onConsent: function(cookie) {
                update_consent_mode(cookie);
                activate_blocked_content(cookie);
                updateConsentStatus(cookie);
                logConsent(cookie, 'consent');
            },
            onChange: function(cookie) {
                update_consent_mode(cookie);
                activate_blocked_content(cookie);
                updateConsentStatus(cookie);
                logConsent(cookie, 'change');
                logNecessaryDisabled(cookie);
            },
            preferencesModal: {},
        });

        applyConsentPositionOverride(ui.consentPosition, ui.consentLayout);
        updateConsentStatus(CookieConsent.getCookie());
        injectBannerIcon();
    }

    document.querySelectorAll('[data-ag-consent-review]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (window.CookieConsent && CookieConsent.showPreferences) {
                CookieConsent.showPreferences();
            }
        });
    });
});

function applyConsentPositionOverride(position, layout) {
    if (!position || layout === 'bar') {
        return;
    }
    const root = document.getElementById('cc-main');
    if (!root) {
        return;
    }
    const tokens = String(position).toLowerCase().split(/\s+/).filter(Boolean);
    root.classList.remove('ag-pos-left', 'ag-pos-right', 'ag-pos-center', 'ag-pos-top', 'ag-pos-bottom');
    if (tokens.includes('left')) {
        root.classList.add('ag-pos-left');
    }
    if (tokens.includes('right')) {
        root.classList.add('ag-pos-right');
    }
    if (tokens.includes('center')) {
        root.classList.add('ag-pos-center');
    }
    if (tokens.includes('top')) {
        root.classList.add('ag-pos-top');
    }
    if (tokens.includes('bottom')) {
        root.classList.add('ag-pos-bottom');
    }

    const observer = new MutationObserver(() => {
        if (document.getElementById('cc-main')) {
            root.classList.remove('ag-pos-left', 'ag-pos-right', 'ag-pos-center', 'ag-pos-top', 'ag-pos-bottom');
            if (tokens.includes('left')) {
                root.classList.add('ag-pos-left');
            }
            if (tokens.includes('right')) {
                root.classList.add('ag-pos-right');
            }
            if (tokens.includes('center')) {
                root.classList.add('ag-pos-center');
            }
            if (tokens.includes('top')) {
                root.classList.add('ag-pos-top');
            }
            if (tokens.includes('bottom')) {
                root.classList.add('ag-pos-bottom');
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
}

function logNecessaryDisabled(cookie) {
    if (!cookie || !cookie.categories) {
        return;
    }
    if (cookie.categories.indexOf('necessary') !== -1) {
        sessionStorage.removeItem('ag_necessary_disabled');
        return;
    }
    if (sessionStorage.getItem('ag_necessary_disabled')) {
        return;
    }
    sessionStorage.setItem('ag_necessary_disabled', '1');
    logConsent(cookie, 'necessary_disabled');
}

function update_consent_mode(cookie) {
    if (!window.gtag || !cookie || !cookie.categories) {
        return;
    }

    const analytics = cookie.categories.includes('analytics') ? 'granted' : 'denied';
    const marketing = cookie.categories.includes('marketing') ? 'granted' : 'denied';

    window.gtag('consent', 'update', {
        analytics_storage: analytics,
        ad_storage: marketing,
        ad_user_data: marketing,
        ad_personalization: marketing,
    });
}

function buildPreferenceSections(definitions, enabledFlags, texts) {
    const legalNote = texts.necessary_legal_note || '';
    const sections = [
        {
            title: texts.necessary_label || (definitions.necessary ? definitions.necessary.label : 'Cookies necesarias'),
            description: buildLegalDescription(
                texts.necessary_description || (definitions.necessary ? definitions.necessary.description : 'Requeridas para el funcionamiento basico del sitio.'),
                legalNote
            ),
            linkedCategory: 'necessary',
            cookieTable: buildCookieTable(definitions.necessary ? definitions.necessary.cookies : []),
        },
    ];

    if (definitions.analytics && enabledFlags.analytics) {
        sections.push({
            title: texts.analytics_label || definitions.analytics.label,
            description: texts.analytics_description || definitions.analytics.description,
            linkedCategory: 'analytics',
            cookieTable: buildCookieTable(definitions.analytics.cookies || []),
        });
    }

    if (definitions.marketing && enabledFlags.marketing) {
        sections.push({
            title: texts.marketing_label || definitions.marketing.label,
            description: texts.marketing_description || definitions.marketing.description,
            linkedCategory: 'marketing',
            cookieTable: buildCookieTable(definitions.marketing.cookies || []),
        });
    }

    return sections;
}

function buildLegalDescription(description, legalNote) {
    const clean = String(description || '').trim();
    const note = String(legalNote || '').trim();
    if (!note) {
        return clean;
    }
    return clean ? clean + ' ' + note : note;
}

function buildCookieTable(cookies) {
    if (!cookies || !cookies.length) {
        return null;
    }

    return {
        caption: 'Listado de cookies',
        headers: {
            name: 'Cookie',
            domain: 'Dominio',
            description: 'Finalidad',
            duration: 'Duracion',
        },
        body: cookies.map(cookie => ({
            name: cookie.name || '',
            domain: cookie.domain || '',
            description: cookie.description || '',
            duration: cookie.duration || '',
        })),
    };
}

function buildAutoClear(definitions, enabledFlags) {
    const buildList = (cookies) => {
        if (!cookies || !cookies.length) {
            return undefined;
        }
        return {
            cookies: cookies.map(cookie => ({
                name: cookie.name,
                domain: cookie.domain || '',
                path: '/',
            })),
        };
    };

    return {
        analytics: enabledFlags.analytics ? buildList(definitions.analytics ? definitions.analytics.cookies : []) : undefined,
        marketing: enabledFlags.marketing ? buildList(definitions.marketing ? definitions.marketing.cookies : []) : undefined,
    };
}

function resolveLanguage(languageConfig) {
    if (!languageConfig) {
        return 'es';
    }

    const defaultLang = languageConfig.default || 'es';
    if (languageConfig.mode === 'custom') {
        return defaultLang;
    }

    if (languageConfig.mode === 'site' && languageConfig.site) {
        return sanitizeLang(languageConfig.site, defaultLang);
    }

    if (languageConfig.mode === 'browser' && navigator.language) {
        return sanitizeLang(navigator.language, defaultLang);
    }

    if (document.documentElement && document.documentElement.lang) {
        return sanitizeLang(document.documentElement.lang, defaultLang);
    }

    if (navigator.language) {
        return sanitizeLang(navigator.language, defaultLang);
    }

    return defaultLang;
}

function resolveAutoDetect(languageConfig) {
    if (!languageConfig) {
        return 'document';
    }
    if (languageConfig.mode === 'browser') {
        return 'browser';
    }
    if (languageConfig.mode === 'site' || languageConfig.mode === 'custom') {
        return false;
    }
    return 'document';
}

function sanitizeLang(value, fallback) {
    const lang = String(value || '').toLowerCase().slice(0, 2);
    if (lang === 'en' || lang === 'es') {
        return lang;
    }
    return fallback || 'es';
}

// The banner has to link to the cookie policy: informing the visitor before
// they choose is the whole point of asking. The URLs are configured in the
// admin, and the footer is left empty when neither is set.
function buildFooter(policy, brand, labels) {
    const links = [];

    if (policy && policy.cookiePolicyUrl) {
        links.push('<a href="' + escapeAttribute(policy.cookiePolicyUrl) + '">' + escapeText(labels.cookiePolicy) + '</a>');
    }

    if (policy && policy.privacyPolicyUrl) {
        links.push('<a href="' + escapeAttribute(policy.privacyPolicyUrl) + '">' + escapeText(labels.privacyPolicy) + '</a>');
    }

    if (brand && brand.name && !brand.hide) {
        links.push(escapeText(brand.name));
    }

    return links.join(' &middot; ');
}

function escapeText(value) {
    const node = document.createElement('span');
    node.textContent = String(value == null ? '' : value);
    return node.innerHTML;
}

function escapeAttribute(value) {
    return escapeText(value).replace(/"/g, '&quot;');
}

function buildTranslations(languageConfig, sectionsEs, sectionsEn, policy, brand) {
    const texts = (languageConfig && languageConfig.texts) || {};
    const es = texts.es || {};
    const en = texts.en || {};
    const i18n = (window.PiensaCookieConsentConfig || {}).i18n || {};

    const footerEs = buildFooter(policy, brand, {
        cookiePolicy: i18n.cookiePolicyEs || 'Politica de cookies',
        privacyPolicy: i18n.privacyPolicyEs || 'Politica de privacidad',
    });
    const footerEn = buildFooter(policy, brand, {
        cookiePolicy: i18n.cookiePolicyEn || 'Cookie policy',
        privacyPolicy: i18n.privacyPolicyEn || 'Privacy policy',
    });

    return {
        es: {
            consentModal: {
                title: es.banner_title || 'Preferencias de cookies',
                description: es.banner_description || 'Usamos cookies para mejorar la experiencia y medir el rendimiento.',
                acceptAllBtn: es.banner_accept_all || 'Aceptar todas',
                acceptNecessaryBtn: es.banner_reject_all || 'Rechazar no necesarias',
                showPreferencesBtn: es.banner_manage_prefs || 'Gestionar preferencias',
                footer: footerEs,
            },
            preferencesModal: {
                title: es.banner_preferences_title || 'Preferencias de cookies',
                acceptAllBtn: es.banner_accept_all || 'Aceptar todas',
                acceptNecessaryBtn: es.banner_reject_all || 'Rechazar no necesarias',
                savePreferencesBtn: es.banner_save_prefs || 'Guardar preferencias',
                sections: sectionsEs,
            },
        },
        en: {
            consentModal: {
                title: en.banner_title || 'Cookie preferences',
                description: en.banner_description || 'We use cookies to improve the experience and measure performance.',
                acceptAllBtn: en.banner_accept_all || 'Accept all',
                acceptNecessaryBtn: en.banner_reject_all || 'Reject non-essential',
                showPreferencesBtn: en.banner_manage_prefs || 'Manage preferences',
                footer: footerEn,
            },
            preferencesModal: {
                title: en.banner_preferences_title || 'Cookie preferences',
                acceptAllBtn: en.banner_accept_all || 'Accept all',
                acceptNecessaryBtn: en.banner_reject_all || 'Reject non-essential',
                savePreferencesBtn: en.banner_save_prefs || 'Save preferences',
                sections: sectionsEn,
            },
        },
    };
}

function buildServicesConfig(servicesData, enabledFlags) {
    const registry = servicesData.registry || {};
    const detected = servicesData.detected || [];
    const detectedSet = new Set(detected);
    const services = { analytics: {}, marketing: {} };

    Object.keys(registry).forEach(key => {
        const service = registry[key];
        if (!service || !detectedSet.has(key)) {
            return;
        }
        const category = service.category;
        if (category === 'analytics' && enabledFlags.analytics) {
            services.analytics[key] = { label: service.label };
        }
        if (category === 'marketing' && enabledFlags.marketing) {
            services.marketing[key] = { label: service.label };
        }
    });

    return services;
}

function maybeAddFloatingConsentButton() {
    if (!window.PiensaCookieConsentConfig || !PiensaCookieConsentConfig.ui || !PiensaCookieConsentConfig.ui.floatingButton) {
        return;
    }

    const ui = PiensaCookieConsentConfig.ui;
    const text = String(ui.floatingButtonText || 'Revisar consentimiento');
    const style = ui.floatingButtonStyle || 'icon';
    const position = ui.consentPosition || 'bottom right';

    const container = document.createElement('div');
    container.className = 'ag-consent-floating';

    // Aplicar posicion basada en la posicion del banner
    const positionClasses = getFloatingPositionClasses(position);
    container.classList.add(...positionClasses);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ag-btn-consent-review';
    button.setAttribute('data-ag-consent-review', '1');
    // It reopens the preferences dialog, which assistive technology should announce.
    button.setAttribute('aria-haspopup', 'dialog');

    const icon = createCookieIcon('cookie');

    if (style === 'icon') {
        button.classList.add('ag-btn-icon');
        button.setAttribute('aria-label', text);

        const label = document.createElement('span');
        label.className = 'ag-btn-label';
        label.textContent = text;

        button.append(icon, label);
    } else {
        icon.classList.add('ag-btn-icon-inline');
        button.append(icon);
        button.append(document.createTextNode(' ' + text));
    }

    button.title = text;

    container.appendChild(button);
    document.body.appendChild(container);
}

function getFloatingPositionClasses(position) {
    const classes = [];
    if (position.includes('bottom')) {
        classes.push('ag-floating--bottom');
    } else if (position.includes('top')) {
        classes.push('ag-floating--top');
    } else {
        classes.push('ag-floating--bottom');
    }

    if (position.includes('left')) {
        classes.push('ag-floating--left');
    } else if (position.includes('center')) {
        classes.push('ag-floating--center');
    } else {
        classes.push('ag-floating--right');
    }

    return classes;
}

function updateConsentStatus(cookie) {
    if (!cookie || !cookie.categories) {
        return;
    }

    const status = cookie.categories.join(', ');
    document.querySelectorAll('[data-ag-consent-status]').forEach(node => {
        const label = ((window.PiensaCookieConsentConfig || {}).i18n || {}).consentStatus || 'Consent:';
        node.textContent = status ? label + ' ' + status : '';
    });
}

// Icon names offered by releases before the Lucide switch.
const ICON_ALIASES = {
    'cookie-bite': 'cookie',
    'fingerprint': 'shield-check'
};

function resolveIconName(style) {
    const icons = (window.PiensaCookieConsentConfig || {}).icons || {};
    const name = ICON_ALIASES[style] || style;
    return icons[name] ? name : 'cookie';
}

function createCookieIcon(style) {
    const icons = (window.PiensaCookieConsentConfig || {}).icons || {};
    const name = resolveIconName(style);

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'pcc-icon cc-cookie-icon');
    svg.setAttribute('width', '24');
    svg.setAttribute('height', '24');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    // Decorative: the surrounding control carries the accessible name.
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    // The icon bodies come from a fixed server-side table, never from user input.
    svg.innerHTML = icons[name] || '';

    return svg;
}

function injectBannerIcon() {
    const config = window.PiensaCookieConsentConfig || {};
    const ui = config.ui || {};

    if (ui.bannerShowIcon === false) {
        return;
    }

    const iconStyle = ui.bannerIconStyle || 'cookie';
    const checkAndInject = () => {
        const titleEl = document.querySelector('#cc-main .cm__title');
        if (titleEl && !titleEl.querySelector('.cc-cookie-icon')) {
            const iconWrapper = document.createElement('span');
            iconWrapper.className = 'cc-cookie-icon-wrapper';
            iconWrapper.appendChild(createCookieIcon(iconStyle));
            titleEl.insertBefore(iconWrapper, titleEl.firstChild);
        }

        const prefsTitleEl = document.querySelector('#cc-main .pm__title');
        if (prefsTitleEl && !prefsTitleEl.querySelector('.cc-cookie-icon')) {
            const iconWrapper = document.createElement('span');
            iconWrapper.className = 'cc-cookie-icon-wrapper';
            iconWrapper.appendChild(createCookieIcon(iconStyle));
            prefsTitleEl.insertBefore(iconWrapper, prefsTitleEl.firstChild);
        }
    };

    checkAndInject();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach(() => {
            checkAndInject();
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    setTimeout(checkAndInject, 100);
    setTimeout(checkAndInject, 500);
}

function applyThemeVars(theme) {
    if (!theme) {
        return;
    }

    const root = document.documentElement;
    const setVar = (name, value) => {
        if (value) {
            root.style.setProperty(name, value);
        }
    };

    setVar('--cc-bg', theme.bg);
    setVar('--cc-primary-color', theme.primaryColor);
    setVar('--cc-secondary-color', theme.secondaryColor);
    setVar('--cc-btn-primary-bg', theme.primaryBtnBg);
    setVar('--cc-btn-primary-color', theme.primaryBtnColor);
    setVar('--cc-btn-secondary-bg', theme.secondaryBtnBg);
    setVar('--cc-btn-secondary-color', theme.secondaryBtnColor);
    if (theme.primaryBtnBg) {
        setVar('--cc-toggle-on-bg', theme.primaryBtnBg);
    }
    if (theme.modalRadius !== undefined && theme.modalRadius !== null) {
        setVar('--cc-modal-border-radius', theme.modalRadius + 'px');
    }
    if (theme.buttonRadius !== undefined && theme.buttonRadius !== null) {
        setVar('--cc-btn-border-radius', theme.buttonRadius + 'px');
        setVar('--cc-pm-toggle-border-radius', theme.buttonRadius + 'px');
    }
}

function logConsent(cookie, actionType) {
    const config = window.PiensaCookieConsentConfig || {};
    const policy = config.policy || {};
    if (!policy.logConsent || !policy.ajaxUrl || !policy.nonce || !cookie) {
        return;
    }

    const payload = new URLSearchParams();
    payload.append('action', 'piensa_cookie_consent_log_consent');
    payload.append('nonce', policy.nonce);
    payload.append('consent_action', actionType || 'consent');
    payload.append('consent_id', cookie.consentId || '');
    payload.append('categories', JSON.stringify(cookie.categories || []));
    payload.append('services', JSON.stringify(cookie.services || {}));
    payload.append('revision', cookie.revision || policy.revision || 0);
    payload.append('consent_timestamp', cookie.consentTimestamp || '');
    payload.append('language', cookie.languageCode || '');
    payload.append('gpc', navigator.globalPrivacyControl ? 1 : 0);
    payload.append('url', window.location.href);

    fetch(policy.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: payload.toString(),
    });
}












