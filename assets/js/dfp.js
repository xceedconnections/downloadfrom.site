(function () {
    'use strict';

    var cfg = window.__DFZ__ || {};
    if (!cfg.enabled) {
        return;
    }

    var sessionKey = 'df_z_shown_';
    var relayPrefix = cfg.relay || '/assets/c/w?u=';
    var popups = cfg.popup || [];

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

    function slotQuery(key) {
        return '[data-dfp="' + key + '"]';
    }

    function fetchSlotHtml(key) {
        var base = cfg.slotBase || '/assets/c/d';
        var params = new URLSearchParams();
        params.set('k', key);
        params.set('pt', cfg.pageType || 'all');
        if (cfg.serviceId) {
            params.set('sid', cfg.serviceId);
        }
        if (cfg.providerId) {
            params.set('pid', cfg.providerId);
        }

        return fetch(base + '?' + params.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
        }).then(function (response) {
            if (!response.ok) {
                return '';
            }
            return response.text();
        }).catch(function () {
            return '';
        });
    }

    function resolveSlotHtml(key) {
        var owned = cfg.owned || {};
        var inline = (owned.slots || {})[key] || '';

        if (inline.trim() !== '') {
            return Promise.resolve(inline);
        }

        return fetchSlotHtml(key);
    }

    function badgeMarkup() {
        var src = cfg.badge || '';
        if (!src) {
            return '';
        }
        return '<div class="dfz-mark"><img src="' + src + '" alt="" class="dfz-mark-img" decoding="async"></div>';
    }

    function createWrap(key) {
        var wrap = document.createElement('div');
        wrap.className = 'dfz-wrap';
        wrap.setAttribute('data-dfp-wrap', key);
        wrap.innerHTML = badgeMarkup()
            + '<div class="dfz" data-dfp="' + key + '"><div class="dfz-body"></div></div>';
        return wrap;
    }

    function ensureBadgeOutside(el, key) {
        var wrap = el.closest('.dfz-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'dfz-wrap';
            wrap.setAttribute('data-dfp-wrap', key);
            el.parentNode.insertBefore(wrap, el);
            wrap.appendChild(el);
        }
        var markInside = el.querySelector('.dfz-mark');
        if (markInside) {
            wrap.insertBefore(markInside, el);
        }
        if (!wrap.querySelector('.dfz-mark')) {
            wrap.insertAdjacentHTML('afterbegin', badgeMarkup());
        }
        return wrap;
    }

    function mountHtml(key, html, target) {
        if (!html || html.trim() === '') {
            return null;
        }

        var el = null;
        if (target) {
            el = target.querySelector ? target.querySelector('.dfz[data-dfp="' + key + '"]') : null;
            if (!el && target.classList && target.classList.contains('dfz')) {
                el = target;
            }
        }
        if (!el) {
            el = document.querySelector(slotQuery(key));
        }

        if (!el) {
            var owned = cfg.owned || {};
            var mountSelector = (owned.mounts || {})[key];
            if (!mountSelector) {
                return null;
            }
            var mount = document.querySelector(mountSelector);
            if (!mount) {
                return null;
            }
            var wrap = createWrap(key);
            mount.appendChild(wrap);
            el = wrap.querySelector('.dfz');
        }

        ensureBadgeOutside(el, key);

        var body = el.querySelector('.dfz-body');
        if (body) {
            body.innerHTML = html;
        } else {
            el.innerHTML = '<div class="dfz-body">' + html + '</div>';
        }

        el.style.setProperty('display', 'block', 'important');
        el.style.setProperty('visibility', 'visible', 'important');
        el.style.setProperty('opacity', '1', 'important');
        el.style.setProperty('filter', 'none', 'important');
        activateScripts(body || el);
        return el;
    }

    function mountOwnedSlots() {
        var owned = cfg.owned || {};
        var slots = owned.slots || {};
        var keys = Object.keys(slots);

        if (keys.length === 0) {
            document.querySelectorAll('.dfz[data-dfp]').forEach(function (el) {
                activateScripts(el);
            });
            return Promise.resolve();
        }

        return Promise.all(keys.map(function (key) {
            return resolveSlotHtml(key).then(function (html) {
                mountHtml(key, html);
            });
        }));
    }

    function mountGatePromo() {
        var owned = cfg.owned || {};
        var slots = owned.slots || {};
        var keys = Object.keys(slots);
        if (keys.length === 0) {
            return Promise.resolve();
        }

        var promo = document.getElementById('df-gate-promo');
        if (!promo) {
            return Promise.resolve();
        }

        var key = keys.indexOf('hhs') >= 0 ? 'hhs' : (keys.indexOf('hdr') >= 0 ? 'hdr' : keys[0]);
        return resolveSlotHtml(key).then(function (html) {
            promo.innerHTML = badgeMarkup()
                + '<div class="dfz"><div class="dfz-body">' + html + '</div></div>';
            activateScripts(promo);
        });
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

    function startPopupQueue() {
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
    }

    function bootAll() {
        mountOwnedSlots().then(function () {
            mountGatePromo();
            if (!window.__DF_GATE_BLOCKED__) {
                startPopupQueue();
            }
        });
    }

    bootAll();

    document.addEventListener('df:gate-ready', function () {
        mountOwnedSlots().then(function () {
            mountGatePromo();
        });
    });

    document.addEventListener('df:gate-cleared', function () {
        mountOwnedSlots().then(function () {
            startPopupQueue();
        });
    });
})();
