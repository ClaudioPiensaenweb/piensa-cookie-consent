// assets/js/agency-shield-audit.js
(function() {
    if (!window.PWCookieAuditCfg) {
        return;
    }

    const cfg = window.PWCookieAuditCfg;
    const toastId = 'pw-cookie-audit-toast';

    function ensureToast(message) {
        let el = document.getElementById(toastId);
        if (!el) {
            el = document.createElement('div');
            el.id = toastId;
            el.style.position = 'fixed';
            el.style.bottom = '16px';
            el.style.right = '16px';
            el.style.background = '#111827';
            el.style.color = '#ffffff';
            el.style.padding = '10px 14px';
            el.style.borderRadius = '10px';
            el.style.fontSize = '12px';
            el.style.zIndex = '99999';
            el.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
            document.body.appendChild(el);
        }
        el.textContent = message;
        return el;
    }

    function namesFromCookie() {
        const raw = document.cookie || '';
        if (!raw) {
            return [];
        }
        return raw
            .split(';')
            .map(item => item.split('=')[0].trim())
            .filter(Boolean);
    }

    function send(names) {
        const body = new URLSearchParams();
        body.append('action', 'agency_shield_cmp_collect_cookies');
        body.append('nonce', cfg.nonce);
        body.append('domain', cfg.domain || '');
        body.append('cookies', JSON.stringify(names));

        const onSuccess = (count) => {
            const msg = count > 0
                ? 'Auditoria completada. Cookies detectadas: ' + count + '.'
                : 'Auditoria completada. No se detectaron cookies JS.';
            const el = ensureToast(msg);
            setTimeout(() => el.remove(), 3000);
        };

        const onFail = () => {
            const el = ensureToast('Auditoria fallida. Revisa consola/CSP.');
            setTimeout(() => el.remove(), 4000);
        };

        if (window.fetch) {
            fetch(cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        onSuccess(data.data && typeof data.data.count === 'number' ? data.data.count : 0);
                    } else {
                        onFail();
                    }
                })
                .catch(onFail);
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) {
                return;
            }
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText || '{}');
                    if (data && data.success) {
                        onSuccess(data.data && typeof data.data.count === 'number' ? data.data.count : 0);
                        return;
                    }
                } catch (e) {}
            }
            onFail();
        };
        xhr.send(body.toString());
    }

    ensureToast('Auditoria de cookies en curso...');
    setTimeout(() => send(namesFromCookie()), 800);
})();
