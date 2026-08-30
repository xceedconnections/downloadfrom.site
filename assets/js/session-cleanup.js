(function () {
    'use strict';

    var STORAGE_KEY = 'pending_result_cleanup';
    var cleaned = {};

    function clearPending() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* ignore */ }
    }

    function setPending(token, cleanupUrl) {
        if (!token || !cleanupUrl) {
            return;
        }
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ token: token, url: cleanupUrl }));
        } catch (e) { /* ignore */ }
    }

    function readPendingConfig() {
        var token = window.__RESULT_TOKEN__;
        var cleanupUrl = window.__CLEANUP_URL__;
        if (token && cleanupUrl) {
            setPending(token, cleanupUrl);
            return { token: token, url: cleanupUrl };
        }

        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            if (parsed && parsed.token && parsed.url) {
                return parsed;
            }
        } catch (e2) { /* ignore */ }

        return null;
    }

    function fireCleanup(cfg, sync) {
        if (!cfg || !cfg.url || cleaned[cfg.token]) {
            return false;
        }

        cleaned[cfg.token] = true;

        if (sync) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', cfg.url, false);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('token=' + encodeURIComponent(cfg.token));
                if (xhr.status >= 200 && xhr.status < 300) {
                    clearPending();
                    return true;
                }
            } catch (e) { /* fall through */ }
        }

        try {
            if (navigator.sendBeacon) {
                var body = new URLSearchParams();
                body.set('token', cfg.token);
                if (navigator.sendBeacon(cfg.url, body)) {
                    clearPending();
                    return true;
                }
            }
        } catch (e2) { /* fall through */ }

        try {
            fetch(cfg.url, {
                method: 'POST',
                body: new URLSearchParams({ token: cfg.token }),
                keepalive: true,
                credentials: 'same-origin',
            }).then(function () { clearPending(); }).catch(function () {});
            return true;
        } catch (e3) { /* fall through */ }

        try {
            var img = new Image();
            img.src = cfg.url + (cfg.url.indexOf('?') >= 0 ? '&' : '?') + 'token=' + encodeURIComponent(cfg.token) + '&_=' + Date.now();
            clearPending();
            return true;
        } catch (e4) { /* ignore */ }

        return false;
    }

    function runCleanup(sync) {
        var cfg = readPendingConfig();
        if (!cfg) {
            return false;
        }
        return fireCleanup(cfg, sync);
    }

    var currentToken = window.__RESULT_TOKEN__ || '';
    var cfg = readPendingConfig();

    if (cfg && !currentToken) {
        fireCleanup(cfg, false);
        return;
    }

    if (!cfg) {
        return;
    }

    window.addEventListener('pagehide', function () {
        runCleanup(false);
    });

    window.addEventListener('beforeunload', function () {
        runCleanup(true);
    });

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        var href = link.getAttribute('href') || '';
        if (href === '' || href.charAt(0) === '#') {
            return;
        }
        runCleanup(false);
    }, true);
})();
