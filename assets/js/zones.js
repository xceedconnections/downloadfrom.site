(function () {
    'use strict';

    var cfg = window.__DFZ__ || {};
    if (!cfg.enabled) {
        return;
    }

    var popups = cfg.popup || [];
    var sessionKey = 'df_z_shown_';
    var relayPrefix = cfg.relay || '/x/r?u=';

    function relaySrc(url) {
        if (!url || url.indexOf('https://') !== 0) {
            return url;
        }
        if (url.indexOf(relayPrefix) !== -1) {
            return url;
        }
        try {
            return relayPrefix + btoa(url).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        } catch (e) {
            return url;
        }
    }

    function activateScripts(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('script').forEach(function (oldScript) {
            var script = document.createElement('script');
            Array.prototype.forEach.call(oldScript.attributes, function (attr) {
                if (attr.name === 'src' && attr.value.indexOf('https://') === 0) {
                    script.setAttribute('src', relaySrc(attr.value));
                    return;
                }
                script.setAttribute(attr.name, attr.value);
            });
            if (!script.src && oldScript.src) {
                script.src = relaySrc(oldScript.src);
            }
            script.text = oldScript.textContent;
            oldScript.parentNode.replaceChild(script, oldScript);
        });
    }

    function markShown(item) {
        if (!item.once || !item.id) {
            return;
        }
        try {
            sessionStorage.setItem(sessionKey + item.id, '1');
        } catch (e) {
            /* ignore */
        }
    }

    function wasShown(item) {
        if (!item.once || !item.id) {
            return false;
        }
        try {
            return sessionStorage.getItem(sessionKey + item.id) === '1';
        } catch (e) {
            return false;
        }
    }

    function showWindowPopup(item, done) {
        if (!item.url) {
            done();
            return;
        }

        setTimeout(function () {
            window.open(item.url, '_blank', 'noopener,noreferrer');
            markShown(item);
            done();
        }, (item.delay || 3) * 1000);
    }

    function showModalPopup(item, done) {
        if (!item.html) {
            done();
            return;
        }

        setTimeout(function () {
            var overlay = document.createElement('div');
            overlay.className = 'cz-layer-overlay';
            if (item.iframe) {
                overlay.classList.add('cz-layer-overlay-iframe');
            }

            var popupClass = 'cz-layer' + (item.iframe ? ' cz-layer-iframe-dialog' : '');
            overlay.innerHTML =
                '<div class="' + popupClass + '" role="dialog" aria-modal="true" aria-label="Notice">' +
                (item.closable !== false ? '<button type="button" class="cz-layer-close" aria-label="Close">&times;</button>' : '') +
                '<div class="cz-layer-body">' + item.html + '</div>' +
                '</div>';

            document.body.appendChild(overlay);
            activateScripts(overlay.querySelector('.cz-layer-body'));

            requestAnimationFrame(function () {
                overlay.classList.add('open');
            });

            markShown(item);

            function closePopup() {
                overlay.classList.remove('open');
                setTimeout(function () {
                    overlay.remove();
                    done();
                }, 300);
            }

            var closeBtn = overlay.querySelector('.cz-layer-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', closePopup);
            } else {
                setTimeout(closePopup, Math.max((item.delay || 3) * 1000, 5000));
            }

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closePopup();
                }
            });
        }, (item.delay || 3) * 1000);
    }

    function showPopup(item, done) {
        if (item.style === 'window') {
            showWindowPopup(item, done);
            return;
        }
        showModalPopup(item, done);
    }

    var queue = popups.filter(function (item) {
        return !wasShown(item);
    });

    function showNext(index) {
        if (index >= queue.length) {
            return;
        }
        var item = queue[index];
        showPopup(item, function () {
            showNext(index + 1);
        });
    }

    if (queue.length > 0) {
        showNext(0);
    }
})();
