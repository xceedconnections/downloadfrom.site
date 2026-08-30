(function () {
    'use strict';

    var cfg = window.__VISITOR_ANALYTICS__;
    if (!cfg || !cfg.visitId || !cfg.leaveUrl) {
        return;
    }

    var visitId = cfg.visitId;
    var leaveUrl = cfg.leaveUrl;
    var startedAt = Date.now();
    var sent = false;

    function secondsOnPage() {
        return Math.max(0, Math.round((Date.now() - startedAt) / 1000));
    }

    function sendDuration() {
        if (sent) {
            return;
        }
        sent = true;

        var duration = secondsOnPage();
        var body = 'id=' + encodeURIComponent(String(visitId)) +
            '&duration=' + encodeURIComponent(String(duration));

        if (navigator.sendBeacon) {
            var blob = new Blob([body], { type: 'application/x-www-form-urlencoded' });
            navigator.sendBeacon(leaveUrl, blob);
            return;
        }

        try {
            fetch(leaveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                keepalive: true,
                credentials: 'same-origin'
            });
        } catch (e) {
            /* ignore */
        }
    }

    window.addEventListener('pagehide', sendDuration);
    window.addEventListener('beforeunload', sendDuration);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            sendDuration();
        }
    });
})();
