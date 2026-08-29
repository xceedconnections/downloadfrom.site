(function () {
    'use strict';

    var cfg = window._WCFG || {};
    if (!cfg.on) {
        return;
    }

    var base = (cfg.base || '').replace(/\/$/, '');

    function runScripts(root) {
        root.querySelectorAll('script').forEach(function (oldScript) {
            var script = document.createElement('script');
            Array.prototype.forEach.call(oldScript.attributes, function (attr) {
                script.setAttribute(attr.name, attr.value);
            });
            script.text = oldScript.textContent || '';
            oldScript.parentNode.replaceChild(script, oldScript);
        });
    }

    function inject(el, html) {
        if (!html) {
            return;
        }
        el.innerHTML = html;
        runScripts(el);
    }

    function isBlocked(el) {
        if (!el || !el.isConnected) {
            return true;
        }
        if (!el.innerHTML.trim()) {
            return true;
        }
        var rect = el.getBoundingClientRect();
        if (rect.height < 4 && rect.width < 4) {
            return true;
        }
        var style = window.getComputedStyle(el);
        return style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0;
    }

    function loadMount(el) {
        var placement = el.getAttribute('data-w');
        if (!placement) {
            return;
        }
        var page = el.getAttribute('data-p') || 'all';
        var svc = el.getAttribute('data-s') || '';
        var url = base + '/api/w/' + encodeURIComponent(placement) + '?p=' + encodeURIComponent(page);
        if (svc) {
            url += '&s=' + encodeURIComponent(svc);
        }

        fetch(url, { credentials: 'same-origin', cache: 'default' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                inject(el, data && data.html ? data.html : '');
            })
            .catch(function () { /* ignore */ });
    }

    function ensureMounts() {
        document.querySelectorAll('.cw-mount').forEach(function (el) {
            if (!el.innerHTML.trim() || isBlocked(el)) {
                loadMount(el);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureMounts);
    } else {
        ensureMounts();
    }

    setTimeout(ensureMounts, 500);
    setTimeout(ensureMounts, 2500);

    var popups = cfg.popup || [];
    popups.forEach(function (item) {
        if (!item.html) {
            return;
        }

        setTimeout(function () {
            var overlay = document.createElement('div');
            overlay.className = 'cw-layer';
            overlay.innerHTML =
                '<div class="cw-panel" role="dialog" aria-modal="true">' +
                (item.closable !== false ? '<button type="button" class="cw-panel-x" aria-label="Close">&times;</button>' : '') +
                '<div class="cw-panel-body">' + item.html + '</div>' +
                '</div>';

            document.body.appendChild(overlay);
            runScripts(overlay);
            requestAnimationFrame(function () {
                overlay.classList.add('open');
            });

            function closePopup() {
                overlay.classList.remove('open');
                setTimeout(function () {
                    overlay.remove();
                }, 300);
            }

            var closeBtn = overlay.querySelector('.cw-panel-x');
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
