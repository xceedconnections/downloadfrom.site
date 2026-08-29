(function () {
    'use strict';

    var cfg = window.__AD_CONFIG__ || {};
    if (!cfg.enabled) {
        return;
    }

    var popups = cfg.popup || [];
    var sessionKey = 'df_popup_shown_';

    function activateScripts(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('script').forEach(function (oldScript) {
            var script = document.createElement('script');
            Array.prototype.forEach.call(oldScript.attributes, function (attr) {
                script.setAttribute(attr.name, attr.value);
            });
            script.text = oldScript.textContent;
            oldScript.parentNode.replaceChild(script, oldScript);
        });
    }

    popups.forEach(function (item) {
        if (!item.html) {
            return;
        }

        if (item.once && item.id) {
            try {
                if (sessionStorage.getItem(sessionKey + item.id) === '1') {
                    return;
                }
            } catch (e) {
                /* ignore */
            }
        }

        setTimeout(function () {
            var overlay = document.createElement('div');
            overlay.className = 'ad-popup-overlay';
            if (item.iframe) {
                overlay.classList.add('ad-popup-overlay-iframe');
            }

            var popupClass = 'ad-popup' + (item.iframe ? ' ad-popup-iframe-dialog' : '');
            overlay.innerHTML =
                '<div class="' + popupClass + '" role="dialog" aria-modal="true" aria-label="Advertisement">' +
                (item.closable !== false ? '<button type="button" class="ad-popup-close" aria-label="Close">&times;</button>' : '') +
                '<span class="ad-label">Advertisement</span>' +
                '<div class="ad-popup-body">' + item.html + '</div>' +
                '</div>';

            document.body.appendChild(overlay);
            activateScripts(overlay.querySelector('.ad-popup-body'));

            requestAnimationFrame(function () {
                overlay.classList.add('open');
            });

            if (item.once && item.id) {
                try {
                    sessionStorage.setItem(sessionKey + item.id, '1');
                } catch (e) {
                    /* ignore */
                }
            }

            function closePopup() {
                overlay.classList.remove('open');
                setTimeout(function () {
                    overlay.remove();
                }, 300);
            }

            var closeBtn = overlay.querySelector('.ad-popup-close');
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
