(function () {
    'use strict';

    var cfg = window.__DOWNLOAD_CONFIG__ || {};
    var countdownSec = Math.max(0, parseInt(cfg.countdown, 10) || 0);
    var modalHtmlByService = cfg.modalHtmlByService || {};
    var openerContainers = Array.isArray(cfg.openerContainers) ? cfg.openerContainers : [];
    var defaultModalHtml = cfg.modalHtml || modalHtmlByService.default || '';
    var downloadBtnLabel = 'Download Video Now';

    function resolveModalHtml(serviceType) {
        var key = (serviceType || '').toLowerCase();
        if (key && modalHtmlByService[key] && modalHtmlByService[key].trim() !== '') {
            return modalHtmlByService[key];
        }
        if (defaultModalHtml.trim() !== '') {
            return defaultModalHtml;
        }
        return modalHtmlByService.default || '';
    }

    function isValidUrl(url) {
        return /^https?:\/\//i.test(String(url || '').trim());
    }

    function pickOpenerLinks() {
        var picked = [];
        var seen = {};

        openerContainers.forEach(function (container) {
            var links = (container.links || []).filter(isValidUrl);
            if (links.length === 0) {
                return;
            }

            var mode = container.mode === 'fixed' ? 'fixed' : 'random';
            var batch = mode === 'fixed'
                ? links
                : [links[Math.floor(Math.random() * links.length)]];

            batch.forEach(function (url) {
                var trimmed = String(url).trim();
                if (!seen[trimmed]) {
                    seen[trimmed] = true;
                    picked.push(trimmed);
                }
            });
        });

        return picked;
    }

    function hasAnyModalAds() {
        if (defaultModalHtml.trim() !== '') {
            return true;
        }
        return Object.keys(modalHtmlByService).some(function (key) {
            return String(modalHtmlByService[key] || '').trim() !== '';
        });
    }

    function hasAnyOpenerLinks() {
        return openerContainers.some(function (c) {
            return Array.isArray(c.links) && c.links.some(isValidUrl);
        });
    }

    var useGate = cfg.useGate !== false && (hasAnyModalAds() || hasAnyOpenerLinks() || countdownSec > 0);

    function openExternalUrl(url) {
        var trimmed = String(url || '').trim();
        if (!isValidUrl(trimmed)) {
            return;
        }
        window.open(trimmed, '_blank', 'noopener,noreferrer');
    }

    function startDownload(url, target) {
        var trimmed = String(url || '').trim();
        if (trimmed === '') {
            return;
        }
        if (target === '_blank') {
            openExternalUrl(trimmed);
            return;
        }
        window.location.href = trimmed;
    }

    function runDownloadFlow(url, target) {
        pickOpenerLinks().forEach(openExternalUrl);
        startDownload(url, target);
    }

    function activateModalScripts(container) {
        if (!container) {
            return;
        }
        if (typeof window.__dfzActivateScripts === 'function') {
            window.__dfzActivateScripts(container);
            return;
        }

        container.querySelectorAll('script').forEach(function (oldScript) {
            if (oldScript.getAttribute('data-dfp-active') === '1') {
                return;
            }
            var script = document.createElement('script');
            Array.prototype.forEach.call(oldScript.attributes, function (attr) {
                script.setAttribute(attr.name, attr.value);
            });
            script.text = oldScript.textContent;
            script.setAttribute('data-dfp-active', '1');
            oldScript.parentNode.replaceChild(script, oldScript);
        });
    }

    function openDownloadModal(url, target, serviceType) {
        var modalHtml = resolveModalHtml(serviceType);
        var hasModalAds = modalHtml.trim() !== '';

        if (!hasModalAds && countdownSec <= 0) {
            runDownloadFlow(url, target);
            return;
        }

        var overlay = document.createElement('div');
        overlay.className = 'cz-modal-overlay';
        overlay.innerHTML =
            '<div class="cz-modal-wrap">' +
            '<div class="cz-modal' + (hasModalAds ? ' has-side-slot' : '') + '">' +
            '<div class="cz-modal-main">' +
            '<h3>Your download is ready</h3>' +
            '<p>Please wait a moment while we prepare your download.</p>' +
            '<div class="cz-modal-actions">' +
            '<button type="button" class="btn btn-primary cz-modal-continue"' + (countdownSec > 0 ? ' disabled' : '') + '>' + downloadBtnLabel + '</button>' +
            '<button type="button" class="btn btn-secondary cz-modal-cancel">Cancel</button>' +
            '</div>' +
            '<p class="cz-modal-countdown">' +
            (countdownSec > 0
                ? 'Download available in <span class="cz-countdown-num">' + countdownSec + '</span>s'
                : 'Click ' + downloadBtnLabel + ' to start your download.') +
            '</p>' +
            '</div>' +
            (hasModalAds ? '<div class="cz-modal-side">' + modalHtml + '</div>' : '') +
            '</div>' +
            '<button type="button" class="cz-modal-close" aria-label="Close">&times;</button>' +
            '</div>';

        document.body.appendChild(overlay);

        var side = overlay.querySelector('.cz-modal-side');
        if (side) {
            activateModalScripts(side);
        }

        requestAnimationFrame(function () {
            overlay.classList.add('open');
        });

        var continueBtn = overlay.querySelector('.cz-modal-continue');
        var cancelBtn = overlay.querySelector('.cz-modal-cancel');
        var closeBtn = overlay.querySelector('.cz-modal-close');
        var numEl = overlay.querySelector('.cz-countdown-num');
        var remaining = countdownSec;
        var downloadTriggered = false;

        function closeModal() {
            overlay.classList.remove('open');
            setTimeout(function () {
                overlay.remove();
            }, 250);
        }

        function triggerDownload() {
            if (downloadTriggered) {
                return;
            }
            downloadTriggered = true;
            runDownloadFlow(url, target);
            closeModal();
        }

        if (countdownSec <= 0) {
            if (continueBtn) {
                continueBtn.addEventListener('click', triggerDownload, { once: true });
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeModal, { once: true });
            }
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal, { once: true });
            }
            return;
        }

        var timer = setInterval(function () {
            remaining -= 1;
            if (numEl) {
                numEl.textContent = String(Math.max(remaining, 0));
            }
            if (remaining <= 0) {
                clearInterval(timer);
                if (continueBtn) {
                    continueBtn.disabled = false;
                    continueBtn.textContent = downloadBtnLabel;
                }
                var countdownEl = overlay.querySelector('.cz-modal-countdown');
                if (countdownEl) {
                    countdownEl.textContent = 'Click ' + downloadBtnLabel + ' to start your download.';
                }
            }
        }, 1000);

        if (continueBtn) {
            continueBtn.addEventListener('click', function () {
                if (!continueBtn.disabled) {
                    clearInterval(timer);
                    triggerDownload();
                }
            }, { once: true });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                clearInterval(timer);
                closeModal();
            }, { once: true });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                clearInterval(timer);
                closeModal();
            }, { once: true });
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-download');
        if (!btn || btn.getAttribute('data-download-busy') === '1') {
            return;
        }

        var url = btn.getAttribute('data-download-url');
        if (!url) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (window.__DF_GATE_BLOCKED__ || document.documentElement.classList.contains('df-gated')) {
            return;
        }

        btn.setAttribute('data-download-busy', '1');
        setTimeout(function () {
            btn.removeAttribute('data-download-busy');
        }, 1500);

        var target = btn.getAttribute('data-download-target') || '';
        var serviceType = btn.getAttribute('data-download-service') || 'default';

        if (useGate) {
            openDownloadModal(url, target, serviceType);
        } else {
            runDownloadFlow(url, target);
        }
    }, true);
})();
