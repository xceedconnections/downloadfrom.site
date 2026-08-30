(function () {
    'use strict';

    // Mobile navigation toggle
    var navToggle = document.querySelector('.bh-nav-toggle');
    var navWrap = document.getElementById('main-nav');
    if (navToggle && navWrap) {
        navToggle.addEventListener('click', function () {
            var expanded = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', String(!expanded));
            navWrap.classList.toggle('open');
        });
    }

    // Header search panel
    var searchToggle = document.getElementById('bh-search-toggle');
    var searchPanel = document.getElementById('bh-search-panel');
    var searchInput = document.getElementById('header-search-url');
    if (searchToggle && searchPanel) {
        searchToggle.addEventListener('click', function () {
            var expanded = searchToggle.getAttribute('aria-expanded') === 'true';
            searchToggle.setAttribute('aria-expanded', String(!expanded));
            searchPanel.hidden = expanded;
            if (!expanded && searchInput) {
                searchInput.focus();
            }
        });
    }

    // Mega menu toggles
    document.querySelectorAll('.bh-has-mega').forEach(function (item) {
        var btn = item.querySelector('.bh-nav-trigger');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = item.classList.contains('open');
            document.querySelectorAll('.bh-has-mega.open').forEach(function (other) {
                if (other !== item) {
                    other.classList.remove('open');
                    var otherBtn = other.querySelector('.bh-nav-trigger');
                    if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
                }
            });
            item.classList.toggle('open');
            btn.setAttribute('aria-expanded', String(!isOpen));
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.bh-has-mega.open').forEach(function (item) {
            item.classList.remove('open');
            var btn = item.querySelector('.bh-nav-trigger');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    });

    // Paste URL button
    var pasteBtn = document.getElementById('paste-btn');
    var urlInput = document.getElementById('video-url');
    if (pasteBtn && urlInput) {
        pasteBtn.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.readText) {
                navigator.clipboard.readText().then(function (text) {
                    urlInput.value = text.trim();
                    urlInput.focus();
                }).catch(function () {
                    urlInput.focus();
                });
            } else {
                urlInput.focus();
            }
        });
    }

    // Service & platform dropdowns (legacy pages)
    document.querySelectorAll('.nav-dropdown').forEach(function (dropdown) {
        var btn = dropdown.querySelector('.nav-dropdown-btn');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = dropdown.classList.contains('open');
            document.querySelectorAll('.nav-dropdown.open').forEach(function (other) {
                if (other !== dropdown) {
                    other.classList.remove('open');
                    var otherBtn = other.querySelector('.nav-dropdown-btn');
                    if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
                }
            });
            dropdown.classList.toggle('open');
            btn.setAttribute('aria-expanded', String(!isOpen));
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.nav-dropdown.open').forEach(function (dropdown) {
            dropdown.classList.remove('open');
            var btn = dropdown.querySelector('.nav-dropdown-btn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    });

    // Form validation feedback
    var form = document.getElementById('url-form');
    var serviceSelect = document.getElementById('service-select');
    if (serviceSelect && urlInput) {
        var placeholders = {
            all: 'Paste your video or audio URL here...',
            'download-video': 'Paste your video URL here...',
            'download-audio': 'Paste your audio or music URL here...'
        };
        serviceSelect.addEventListener('change', function () {
            urlInput.placeholder = placeholders[serviceSelect.value] || placeholders.all;
        });
    }
    if (form) {
        var submitBtn = document.getElementById('url-form-submit');
        form.addEventListener('submit', function (e) {
            if (window.__DF_GATE_BLOCKED__ || document.documentElement.classList.contains('df-gated')) {
                e.preventDefault();
                return;
            }
            var val = urlInput ? urlInput.value.trim() : '';
            if (!val) {
                e.preventDefault();
                if (urlInput) urlInput.focus();
                return;
            }
            form.classList.add('is-loading');
            form.setAttribute('aria-busy', 'true');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        });
    }
})();
