(function () {
    'use strict';

    var cfg = window.__PROMO_CFG__ || {};
    if (!cfg.enabled) {
        return;
    }

    var popups = cfg.popup || [];
    popups.forEach(function (item) {
        if (!item.html) {
            return;
        }

        setTimeout(function () {
            var overlay = document.createElement('div');
            overlay.className = 'promo-popup-overlay';
            overlay.innerHTML =
                '<div class="promo-popup" role="dialog" aria-modal="true">' +
                (item.closable !== false ? '<button type="button" class="promo-popup-close" aria-label="Close">&times;</button>' : '') +
                '<span class="promo-tag">Sponsored</span>' +
                '<div class="promo-popup-body">' + item.html + '</div>' +
                '</div>';

            document.body.appendChild(overlay);
            requestAnimationFrame(function () {
                overlay.classList.add('open');
            });

            function closePopup() {
                overlay.classList.remove('open');
                setTimeout(function () {
                    overlay.remove();
                }, 300);
            }

            var closeBtn = overlay.querySelector('.promo-popup-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', closePopup);
            }
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closePopup();
                }
            });
        }, (item.delay || 3) * 1000);
    });
})();
