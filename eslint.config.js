// Flat config (ESLint 9+).
const globals = {
    window: 'readonly',
    document: 'readonly',
    navigator: 'readonly',
    console: 'readonly',
    XMLHttpRequest: 'readonly',
    URLSearchParams: 'readonly',
    fetch: 'readonly',
    setTimeout: 'readonly',
    clearTimeout: 'readonly',
    localStorage: 'readonly',
    CustomEvent: 'readonly',
    MutationObserver: 'readonly',
    wp: 'readonly',
    jQuery: 'readonly',
    ajaxurl: 'readonly',
    CookieConsent: 'readonly',
    PiensaCookieConsentConfig: 'readonly',
    PiensaCookieConsentAdminConfig: 'readonly',
    PiensaCookieConsentAudit: 'readonly',
};

module.exports = [
    {
        // Bundled third-party library; linting it would only report on
        // upstream's minified output.
        ignores: ['**/*.min.js', 'assets/js/cookieconsent.js', 'node_modules/**'],
    },
    {
        files: ['assets/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2021,
            sourceType: 'script',
            globals,
        },
        rules: {
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            'no-empty': 'error',
            eqeqeq: ['error', 'smart'],
        },
    },
];
