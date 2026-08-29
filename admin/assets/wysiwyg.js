(function () {
    'use strict';

    var baseConfig = {
        license_key: 'gpl',
        menubar: false,
        statusbar: false,
        plugins: 'lists link code autoresize',
        toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat | code',
        min_height: 160,
        max_height: 420,
        branding: false,
        promotion: false,
        relative_urls: false,
        convert_urls: false,
        entity_encoding: 'raw'
    };

    function uniqueId(prefix) {
        return prefix + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    }

    function ensureId(el, prefix) {
        if (!el.id) {
            el.id = uniqueId(prefix || 'wysiwyg');
        }
        return el.id;
    }

    window.AdminWysiwyg = {
        init: function (selector) {
            if (typeof tinymce === 'undefined') {
                return Promise.resolve([]);
            }
            return tinymce.init(Object.assign({}, baseConfig, { selector: selector }));
        },

        initElement: function (el) {
            var id = ensureId(el, 'wysiwyg');
            if (typeof tinymce !== 'undefined' && tinymce.get(id)) {
                return Promise.resolve(tinymce.get(id));
            }
            return this.init('#' + id);
        },

        initIn: function (container) {
            var root = container || document;
            var areas = root.querySelectorAll('textarea.wysiwyg');
            areas.forEach(function (el) {
                ensureId(el, 'wysiwyg');
            });
            if (!areas.length) {
                return Promise.resolve([]);
            }
            return this.init('textarea.wysiwyg');
        },

        removeIn: function (container) {
            if (typeof tinymce === 'undefined') {
                return;
            }
            (container || document).querySelectorAll('textarea.wysiwyg').forEach(function (el) {
                if (el.id && tinymce.get(el.id)) {
                    tinymce.remove('#' + el.id);
                }
            });
        },

        saveAll: function () {
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelector('textarea.wysiwyg')) {
            AdminWysiwyg.initIn(document);
        }

        var faqItems = document.getElementById('faq-items');
        var addBtn = document.getElementById('faq-add');
        var tpl = document.getElementById('faq-row-template');

        if (faqItems && addBtn && tpl) {
            addBtn.addEventListener('click', function () {
                var clone = tpl.content.cloneNode(true);
                var row = clone.querySelector('[data-faq-row]');
                var ta = row.querySelector('textarea.wysiwyg');
                if (ta) {
                    ta.id = uniqueId('faq-a');
                }
                faqItems.appendChild(clone);
                if (ta) {
                    AdminWysiwyg.initElement(ta);
                }
                renumberFaqRows();
            });

            faqItems.addEventListener('click', function (e) {
                var btn = e.target.closest('.faq-remove');
                if (!btn) {
                    return;
                }
                var row = btn.closest('[data-faq-row]');
                if (!row) {
                    return;
                }
                AdminWysiwyg.removeIn(row);
                row.remove();
                renumberFaqRows();
            });

            initFaqDragDrop(faqItems);
        }

        function initFaqDragDrop(container) {
            var dragRow = null;

            container.addEventListener('dragstart', function (e) {
                var handle = e.target.closest('.faq-drag-handle');
                if (!handle) {
                    return;
                }
                dragRow = handle.closest('[data-faq-row]');
                if (!dragRow) {
                    return;
                }
                dragRow.classList.add('faq-dragging');
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', 'faq');
                }
            });

            container.addEventListener('dragend', function () {
                if (dragRow) {
                    dragRow.classList.remove('faq-dragging');
                }
                container.querySelectorAll('.faq-drag-over').forEach(function (el) {
                    el.classList.remove('faq-drag-over');
                });
                dragRow = null;
                renumberFaqRows();
            });

            container.addEventListener('dragover', function (e) {
                if (!dragRow) {
                    return;
                }
                e.preventDefault();
                var over = e.target.closest('[data-faq-row]');
                if (!over || over === dragRow) {
                    return;
                }
                container.querySelectorAll('.faq-drag-over').forEach(function (el) {
                    el.classList.remove('faq-drag-over');
                });
                over.classList.add('faq-drag-over');
                var rect = over.getBoundingClientRect();
                var after = e.clientY > rect.top + rect.height / 2;
                container.insertBefore(dragRow, after ? over.nextSibling : over);
            });

            container.addEventListener('drop', function (e) {
                e.preventDefault();
            });
        }

        function renumberFaqRows() {
            if (!faqItems) {
                return;
            }
            faqItems.querySelectorAll('[data-faq-row]').forEach(function (row, index) {
                var label = row.querySelector('[data-faq-num]');
                if (label) {
                    label.textContent = String(index + 1);
                }
            });
        }

        renumberFaqRows();

        document.querySelectorAll('form[data-wysiwyg-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                AdminWysiwyg.saveAll();
            });
        });
    });
})();
