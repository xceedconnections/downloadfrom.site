(function () {
    'use strict';

    var cfg = window.__DFG__ || {};
    if (!cfg.enabled) {
        window.__DF_GATE_BLOCKED__ = false;
        return;
    }

    var siteName = cfg.site || 'this site';
    var gateEl = null;
    var statusEl = null;
    var pollTimer = null;

    function detectAdblock() {
        return new Promise(function (resolve) {
            var blocked = false;

            var bait = document.createElement('div');
            bait.innerHTML = '&nbsp;';
            bait.setAttribute('aria-hidden', 'true');
            bait.className = 'pub_300x250 pub_300x250m pub_728x90 text-ad textAd text_ad text_ads text-ads text-ad-links';
            bait.style.cssText = 'width:1px!important;height:1px!important;position:absolute!important;left:-9999px!important;top:0!important;';
            document.body.appendChild(bait);

            requestAnimationFrame(function () {
                var style = window.getComputedStyle(bait);
                if (
                    bait.offsetHeight === 0
                    || bait.offsetWidth === 0
                    || style.display === 'none'
                    || style.visibility === 'hidden'
                ) {
                    blocked = true;
                }
                bait.remove();

                if (!blocked) {
                    var script = document.createElement('script');
                    script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';
                    script.async = true;
                    var timer = window.setTimeout(function () {
                        blocked = true;
                        script.remove();
                        resolve(true);
                    }, 1200);
                    script.onload = function () {
                        window.clearTimeout(timer);
                        script.remove();
                        resolve(false);
                    };
                    script.onerror = function () {
                        window.clearTimeout(timer);
                        script.remove();
                        resolve(true);
                    };
                    document.head.appendChild(script);
                    return;
                }

                resolve(blocked);
            });
        });
    }

    function setBlocked(blocked) {
        window.__DF_GATE_BLOCKED__ = blocked;
        document.documentElement.classList.toggle('df-gated', blocked);
        if (gateEl) {
            gateEl.hidden = !blocked;
        }
        document.dispatchEvent(new CustomEvent('df:gate-ready', { detail: { blocked: blocked } }));
        if (!blocked) {
            document.dispatchEvent(new CustomEvent('df:gate-cleared'));
        }
    }

    function buildGate() {
        gateEl = document.createElement('div');
        gateEl.id = 'df-gate';
        gateEl.className = 'df-gate';
        gateEl.setAttribute('role', 'alertdialog');
        gateEl.setAttribute('aria-modal', 'true');
        gateEl.setAttribute('aria-labelledby', 'df-gate-title');
        gateEl.innerHTML =
            '<div class="df-gate-panel">' +
            '<h2 id="df-gate-title">Ad Blocker Detected</h2>' +
            '<p>To use <strong>' + siteName + '</strong>, please disable your ad blocker. Ads keep this service free for everyone.</p>' +
            '<ol class="df-gate-steps">' +
            '<li>Click the ad blocker icon in your browser toolbar.</li>' +
            '<li>Turn off blocking for this website (or pause the extension).</li>' +
            '<li>Click the button below to continue.</li>' +
            '</ol>' +
            '<button type="button" class="df-gate-recheck" id="df-gate-recheck">I disabled my ad blocker</button>' +
            '<p class="df-gate-status" id="df-gate-status" aria-live="polite"></p>' +
            '<div class="df-gate-promo" id="df-gate-promo"></div>' +
            '</div>';

        document.body.appendChild(gateEl);
        statusEl = gateEl.querySelector('#df-gate-status');

        gateEl.querySelector('#df-gate-recheck').addEventListener('click', function () {
            if (statusEl) {
                statusEl.textContent = 'Checking…';
            }
            runCheck(true);
        });
    }

    function blockInteractions() {
        document.addEventListener('click', function (e) {
            if (!document.documentElement.classList.contains('df-gated')) {
                return;
            }
            if (gateEl && gateEl.contains(e.target)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
        }, true);

        document.addEventListener('submit', function (e) {
            if (document.documentElement.classList.contains('df-gated')) {
                e.preventDefault();
                e.stopPropagation();
                if (statusEl) {
                    statusEl.textContent = 'Please disable your ad blocker to submit the form.';
                }
            }
        }, true);
    }

    function runCheck(manual) {
        detectAdblock().then(function (blocked) {
            setBlocked(blocked);
            if (statusEl) {
                if (blocked) {
                    statusEl.textContent = manual
                        ? 'Ad blocker still detected. Please disable it for this site and try again.'
                        : '';
                } else {
                    statusEl.textContent = '';
                }
            }
        });
    }

    buildGate();
    blockInteractions();
    runCheck(false);

    pollTimer = window.setInterval(function () {
        if (document.documentElement.classList.contains('df-gated')) {
            runCheck(false);
        }
    }, 2500);

    window.addEventListener('beforeunload', function () {
        if (pollTimer) {
            window.clearInterval(pollTimer);
        }
    });
})();
