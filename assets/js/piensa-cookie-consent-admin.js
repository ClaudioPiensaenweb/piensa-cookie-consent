// assets/js/piensa-cookie-consent-admin.js

(function() {
    function initTabs() {
        const nav = document.querySelector('[data-ag-tabs]');
        if (!nav) {
            return;
        }

        const buttons = Array.from(nav.querySelectorAll('[data-tab]'));
        const panels = Array.from(document.querySelectorAll('.ag-tab'));

        function activate(tab) {
            buttons.forEach(btn => {
                btn.classList.toggle('is-active', btn.getAttribute('data-tab') === tab);
            });
            panels.forEach(panel => {
                panel.classList.toggle('is-active', panel.getAttribute('data-tab') === tab);
            });
        }

        function getHashTab() {
            const hash = window.location.hash || '';
            if (hash.indexOf('ag-tab=') === -1) {
                return null;
            }
            return hash.split('ag-tab=')[1] || null;
        }

        const initial = getHashTab() || (buttons[0] ? buttons[0].getAttribute('data-tab') : null);
        if (initial) {
            activate(initial);
        }

        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = btn.getAttribute('data-tab');
                if (!tab) {
                    return;
                }
                activate(tab);
                if (history && history.replaceState) {
                    history.replaceState(null, '', '#ag-tab=' + tab);
                } else {
                    window.location.hash = 'ag-tab=' + tab;
                }
            });
        });
    }

    function initPreview() {
        const frame = document.querySelector('[data-ag-preview-frame]');
        if (!frame || !window.PiensaCookieConsentAdminConfig) {
            return;
        }

        const form = document.querySelector('form[action="options.php"]');
        if (!form) {
            return;
        }

        const assets = PiensaCookieConsentAdminConfig.previewAssets || {};
        let timer = null;

        const getValue = (data, key, fallback = '') => {
            const value = data.get('piensa_cookie_consent_settings[' + key + ']');
            return value !== null && value !== undefined ? value : fallback;
        };

        const getBool = (data, key) => data.has('piensa_cookie_consent_settings[' + key + ']');
        const getInt = (data, key, fallback = 0) => {
            const raw = getValue(data, key, fallback);
            const parsed = parseInt(raw, 10);
            return Number.isFinite(parsed) ? parsed : fallback;
        };

        const TEXT_FIELDS = [
            'banner_title',
            'banner_description',
            'banner_accept_all',
            'banner_reject_all',
            'banner_manage_prefs',
            'banner_save_prefs',
            'banner_preferences_title',
            'necessary_label',
            'necessary_description',
            'necessary_legal_note',
            'analytics_label',
            'analytics_description',
            'marketing_label',
            'marketing_description',
        ];

        function readBannerText(data) {
            const texts = {};
            const prefix = 'piensa_cookie_consent_settings[banner_text][';

            for (const key of data.keys()) {
                if (key.indexOf(prefix) !== 0) {
                    continue;
                }
                const code = key.slice(prefix.length).split(']')[0];
                if (!code || texts[code]) {
                    continue;
                }

                texts[code] = {};
                TEXT_FIELDS.forEach(function(field) {
                    const value = data.get(prefix + code + '][' + field + ']');
                    if (value) {
                        texts[code][field] = value;
                    }
                });
            }

            return texts;
        }

        function buildConfig(data) {
            const defaultLanguage = getValue(data, 'default_language', 'es') || 'es';

            return {
                categories: {
                    necessary: true,
                    analytics: getBool(data, 'analytics_enabled'),
                    marketing: getBool(data, 'marketing_enabled'),
                },
                // Labels come from the language blocks now; the preview only
                // needs the shape, since the text is applied from translations.
                cookieDefinitions: {
                    necessary: { label: '', description: '', cookies: [] },
                    analytics: { label: '', description: '', cookies: [] },
                    marketing: { label: '', description: '', cookies: [] },
                },
                ui: {
                    floatingButton: false,
                    floatingButtonText: getValue(data, 'floating_button_text', ''),
                    floatingButtonStyle: getValue(data, 'floating_button_style', 'icon'),
                    consentLayout: getValue(data, 'consent_layout', 'box'),
                    consentPosition: getValue(data, 'consent_position', 'bottom right'),
                    preferencesLayout: getValue(data, 'preferences_layout', 'box'),
                    preferencesPosition: getValue(data, 'preferences_position', 'right'),
                    bannerShowIcon: getBool(data, 'banner_show_icon'),
                    bannerIconStyle: getValue(data, 'banner_icon_style', 'cookie'),
                    allowNecessaryToggle: getBool(data, 'allow_necessary_toggle'),
                },
                theme: {
                    bg: getValue(data, 'theme_bg', '#ffffff'),
                    primaryColor: getValue(data, 'theme_primary_color', '#2c2f31'),
                    secondaryColor: getValue(data, 'theme_secondary_color', '#5e6266'),
                    primaryBtnBg: getValue(data, 'theme_btn_primary_bg', '#30363c'),
                    primaryBtnColor: getValue(data, 'theme_btn_primary_color', '#ffffff'),
                    secondaryBtnBg: getValue(data, 'theme_btn_secondary_bg', '#eaeff2'),
                    secondaryBtnColor: getValue(data, 'theme_btn_secondary_color', '#2c2f31'),
                    modalRadius: getInt(data, 'theme_modal_radius', 8),
                    buttonRadius: getInt(data, 'theme_button_radius', 6),
                },
                language: {
                    mode: 'custom',
                    default: defaultLanguage,
                    site: defaultLanguage,
                    // Read straight from the form, which now holds one block
                    // per language rather than a flat set of keys with an _en
                    // suffix on half of them.
                    texts: readBannerText(data),
                },
                brand: {
                    name: getValue(data, 'brand_name', ''),
                    logo: getValue(data, 'brand_logo_url', ''),
                    hide: getBool(data, 'hide_branding'),
                },
                policy: {
                    revision: getInt(data, 'policy_revision', 0),
                    cookiePolicyUrl: getValue(data, 'cookie_policy_url', ''),
                    privacyPolicyUrl: getValue(data, 'privacy_policy_url', ''),
                    logConsent: false,
                    ajaxUrl: '',
                    nonce: '',
                    banner: {
                        title: getValue(data, 'banner_title', ''),
                        description: getValue(data, 'banner_description', ''),
                        acceptAll: getValue(data, 'banner_accept_all', ''),
                        rejectAll: getValue(data, 'banner_reject_all', ''),
                        managePrefs: getValue(data, 'banner_manage_prefs', ''),
                        savePrefs: getValue(data, 'banner_save_prefs', ''),
                        preferencesTitle: getValue(data, 'banner_preferences_title', ''),
                    },
                },
            };
        }

        function buildPreviewHtml(config) {
            const json = JSON.stringify(config).replace(/<\/script>/gi, '<\\/script>');
            return `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="${assets.cssCc}">
  <link rel="stylesheet" href="${assets.cssMain}">
  <style>html,body{margin:0;padding:0;background:#f1f5f9;}#preview-root{min-height:360px;position:relative;padding:20px;}#cc-main{position:absolute !important;}</style>
</head>
<body>
  <div id="preview-root"></div>
  <script>window.PiensaCookieConsentConfig=${json};</script>
  <script src="${assets.jsCc}"></script>
  <script src="${assets.jsMain}"></script>
  <script>setTimeout(function(){if(window.CookieConsent&&CookieConsent.show){CookieConsent.show();}},60);</script>
</body>
</html>`;
        }

        function refreshPreview() {
            const data = new FormData(form);
            const config = buildConfig(data);
            frame.classList.add('is-loading');
            frame.srcdoc = buildPreviewHtml(config);
            frame.classList.remove('is-loading');
        }

        function schedulePreview() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(refreshPreview, 200);
        }

        form.addEventListener('input', schedulePreview);
        form.addEventListener('change', schedulePreview);

        schedulePreview();
    }

    function initColorEditor() {
        const colorInputs = Array.from(document.querySelectorAll('[data-ag-color]'));
        if (!colorInputs.length) {
            return;
        }

        const isHex = (value) => /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value);

        colorInputs.forEach(input => {
            const key = input.getAttribute('data-ag-color');
            const textInput = document.querySelector('[data-ag-color-text="' + key + '"]');
            if (!textInput) {
                return;
            }

            textInput.value = input.value || '';

            input.addEventListener('input', () => {
                textInput.value = input.value || '';
            });

            textInput.addEventListener('input', () => {
                const value = textInput.value.trim();
                if (!isHex(value)) {
                    return;
                }
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    // Shared by the preset buttons and the theme-colour button: writing the
    // value is not enough, the colour picker beside each field listens for
    // these events to stay in step.
    function setSettingsField(name, value) {
        const input = document.querySelector('[name="piensa_cookie_consent_settings[' + name + ']"]');
        if (!input || value === undefined) {
            return;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // The Banner screen points at Languages rather than repeating its fields.
    function initTabLinks() {
        document.querySelectorAll('[data-ag-goto-tab]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const target = btn.getAttribute('data-ag-goto-tab');
                const tab = document.querySelector('.ag-tab-btn[data-tab="' + target + '"]');
                if (tab) {
                    tab.click();
                    tab.scrollIntoView({ block: 'center' });
                }
            });
        });
    }

    function initThemeColors() {
        const button = document.querySelector('[data-ag-use-theme-colors]');
        if (!button || !window.PiensaCookieConsentAdminConfig) {
            return;
        }

        const colors = PiensaCookieConsentAdminConfig.themeColors || {};
        if (!Object.keys(colors).length) {
            return;
        }

        button.addEventListener('click', function() {
            Object.keys(colors).forEach(function(key) {
                setSettingsField(key, colors[key]);
            });
        });
    }

    function initPresets() {
        const applyBtn = document.querySelector('[data-ag-apply-preset]');
        const select = document.querySelector('.ag-preset-select');
        if (!applyBtn || !select || !window.PiensaCookieConsentAdminConfig) {
            return;
        }

        const presets = PiensaCookieConsentAdminConfig.presets || {};

        const setField = setSettingsField;

        applyBtn.addEventListener('click', function() {
            const key = select.value;
            const preset = presets[key];
            if (!preset) {
                return;
            }

            setField('theme_bg', preset.theme_bg);
            setField('theme_primary_color', preset.theme_primary_color);
            setField('theme_secondary_color', preset.theme_secondary_color);
            setField('theme_btn_primary_bg', preset.theme_btn_primary_bg);
            setField('theme_btn_primary_color', preset.theme_btn_primary_color);
            setField('theme_btn_secondary_bg', preset.theme_btn_secondary_bg);
            setField('theme_btn_secondary_color', preset.theme_btn_secondary_color);
            setField('theme_modal_radius', preset.theme_modal_radius);
            setField('theme_button_radius', preset.theme_button_radius);
            setField('consent_layout', preset.consent_layout);
            setField('consent_position', preset.consent_position);
            setField('preferences_layout', preset.preferences_layout);
            setField('preferences_position', preset.preferences_position);
        });
    }

    function init() {
        initTabs();
        initPreview();
        initColorEditor();
        initPresets();
        initThemeColors();
        initTabLinks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
