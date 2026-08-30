(function () {
    'use strict';

    var cfg = window.__DOWNLOAD_CONFIG__ || {};
    var countdownSec = Math.max(0, parseInt(cfg.countdown, 10) || 0);
    var modalHtml = cfg.modalHtml || '';
    var useGate = cfg.useGate !== false && countdownSec > 0;

    function startDownload(url, target) {
        if (target === '_blank') {
            window.open(url, '_blank', 'noopener,noreferrer');
        } else {
            window.location.href = url;
        }
    }

    function openDownloadModal(url, target) {
        var overlay = document.createElement('div');
        overlay.className = 'cz-modal-overlay';
        var hasSide = modalHtml.trim() !== '';
        overlay.innerHTML =
            '<div class="cz-modal-wrap">' +
            '<div class="cz-modal' + (hasSide ? ' has-side-slot' : '') + '">' +
            '<div class="cz-modal-main">' +
            '<h3>Your download is ready</h3>' +
            '<p>Please wait a moment while we prepare your download.</p>' +
            '<div class="cz-modal-actions">' +
            '<button type="button" class="btn btn-primary cz-modal-continue" disabled>Continue Download</button>' +
            '<button type="button" class="btn btn-secondary cz-modal-cancel">Cancel</button>' +
            '</div>' +
            '<p class="cz-modal-countdown">Download available in <span class="cz-countdown-num">' + countdownSec + '</span>s</p>' +
            '</div>' +
            (hasSide ? '<div class="cz-modal-side">' + modalHtml + '</div>' : '') +
            '</div>' +
            '<button type="button" class="cz-modal-close" aria-label="Close">&times;</button>' +
            '</div>';

        document.body.appendChild(overlay);
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
            closeModal();
            startDownload(url, target);
        }

        if (countdownSec <= 0) {
            triggerDownload();
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
                    continueBtn.textContent = 'Continue Download';
                }
                var countdownEl = overlay.querySelector('.cz-modal-countdown');
                if (countdownEl) {
                    countdownEl.textContent = 'Click Continue to start your download.';
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

        if (useGate) {
            openDownloadModal(url, target);
        } else {
            startDownload(url, target);
        }
    });
})();
