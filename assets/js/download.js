(function () {
    'use strict';

    var cfg = window.__DOWNLOAD_CONFIG__ || {};
    var countdownSec = Math.max(0, parseInt(cfg.countdown, 10) || 0);
    var modalHtmlByService = cfg.modalHtmlByService || {};
    var openerLinksByService = cfg.openerLinksByService || {};
    var defaultModalHtml = cfg.modalHtml || modalHtmlByService.default || '';
    var defaultOpenerLinks = cfg.openerLinks || openerLinksByService.default || [];
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

    function resolveOpenerLinks(serviceType) {
        var key = (serviceType || '').toLowerCase();
        if (key && Array.isArray(openerLinksByService[key]) && openerLinksByService[key].length > 0) {
            return openerLinksByService[key];
        }
        if (Array.isArray(defaultOpenerLinks) && defaultOpenerLinks.length > 0) {
            return defaultOpenerLinks;
        }
        return Array.isArray(openerLinksByService.default) ? openerLinksByService.default : [];
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
        if (Array.isArray(defaultOpenerLinks) && defaultOpenerLinks.length > 0) {
            return true;
        }
        return Object.keys(openerLinksByService).some(function (key) {
            return Array.isArray(openerLinksByService[key]) && openerLinksByService[key].length > 0;
        });
    }

    var useGate = cfg.useGate !== false && (hasAnyModalAds() || hasAnyOpenerLinks() || countdownSec > 0);

    function startDownload(url, target) {
        if (target === '_blank') {
            window.open(url, '_blank', 'noopener,noreferrer');
        } else {
            window.location.href = url;
        }
    }

    function openOpenerLinks(serviceType) {
        resolveOpenerLinks(serviceType).forEach(function (link) {
            if (link && String(link).indexOf('http') === 0) {
                window.open(link, '_blank', 'noopener,noreferrer');
            }
        });
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

        if (!hasModalAds && countdownSec <= 0 && !hasAnyOpenerLinks()) {
            openOpenerLinks(serviceType);
            startDownload(url, target);
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

        function closeModal() {
            overlay.classList.remove('open');
            setTimeout(function () {
                overlay.remove();
            }, 250);
        }

        function triggerDownload() {
            openOpenerLinks(serviceType);
            closeModal();
            startDownload(url, target);
        }

        if (countdownSec <= 0) {
            if (continueBtn) {
                continueBtn.addEventListener('click', triggerDownload);
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeModal);
            }
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
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
            });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                clearInterval(timer);
                closeModal();
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                clearInterval(timer);
                closeModal();
            });
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-download');
        if (!btn) {
            return;
        }

        var url = btn.getAttribute('data-download-url');
        if (!url) {
            return;
        }

        e.preventDefault();

        if (window.__DF_GATE_BLOCKED__ || document.documentElement.classList.contains('df-gated')) {
            return;
        }

        var target = btn.getAttribute('data-download-target') || '';
        var serviceType = btn.getAttribute('data-download-service') || 'default';

        if (useGate) {
            openDownloadModal(url, target, serviceType);
        } else {
            openOpenerLinks(serviceType);
            startDownload(url, target);
        }
    });
})();
