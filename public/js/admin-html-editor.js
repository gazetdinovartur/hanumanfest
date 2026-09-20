(function () {
    'use strict';

    const CMD_MAP = {
        bold: () => document.execCommand('bold'),
        italic: () => document.execCommand('italic'),
        underline: () => document.execCommand('underline'),
        ul: () => document.execCommand('insertUnorderedList'),
        ol: () => document.execCommand('insertOrderedList'),
        link: () => {
            const url = window.prompt('URL ссылки', 'https://');
            if (url) {
                document.execCommand('createLink', false, url);
            }
        },
        h3: () => document.execCommand('formatBlock', false, 'h3'),
        h4: () => document.execCommand('formatBlock', false, 'h4'),
        p: () => document.execCommand('formatBlock', false, 'p'),
        removeFormat: () => document.execCommand('removeFormat'),
    };

    function syncToTextarea(wrap) {
        const surface = wrap.querySelector('.hf-html-editor__surface');
        const textarea = wrap.querySelector('textarea');
        if (!surface || !textarea) {
            return;
        }
        textarea.value = surface.innerHTML.trim();
    }

    function syncFromTextarea(wrap) {
        const surface = wrap.querySelector('.hf-html-editor__surface');
        const textarea = wrap.querySelector('textarea');
        if (!surface || !textarea) {
            return;
        }
        surface.innerHTML = textarea.value || '';
    }

    function toggleSource(wrap) {
        const surface = wrap.querySelector('.hf-html-editor__surface');
        const textarea = wrap.querySelector('textarea');
        const btn = wrap.querySelector('[data-cmd="toggleSource"]');
        if (!surface || !textarea || !btn) {
            return;
        }

        const sourceMode = wrap.classList.toggle('hf-html-editor--source');
        if (sourceMode) {
            syncToTextarea(wrap);
            surface.hidden = true;
            textarea.hidden = false;
            textarea.classList.add('form-control');
            btn.classList.add('is-active');
        } else {
            textarea.hidden = true;
            textarea.classList.remove('form-control');
            surface.hidden = false;
            surface.innerHTML = textarea.value || '';
            btn.classList.remove('is-active');
        }
    }

    function initEditor(wrap) {
        if (wrap.dataset.hfEditorReady === '1') {
            return;
        }
        wrap.dataset.hfEditorReady = '1';

        syncFromTextarea(wrap);

        wrap.querySelectorAll('[data-cmd]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const cmd = button.getAttribute('data-cmd');
                if (cmd === 'toggleSource') {
                    toggleSource(wrap);
                    return;
                }
                const surface = wrap.querySelector('.hf-html-editor__surface');
                if (surface && !wrap.classList.contains('hf-html-editor--source')) {
                    surface.focus();
                    const fn = CMD_MAP[cmd];
                    if (fn) {
                        fn();
                    }
                    syncToTextarea(wrap);
                }
            });
        });

        const surface = wrap.querySelector('.hf-html-editor__surface');
        if (surface) {
            surface.addEventListener('input', () => syncToTextarea(wrap));
            surface.addEventListener('blur', () => syncToTextarea(wrap));
        }
    }

    function initAll(root) {
        root.querySelectorAll('[data-hf-html-editor]').forEach(initEditor);
    }

    function bindForms(root) {
        root.querySelectorAll('form').forEach((form) => {
            if (form.dataset.hfEditorFormBound === '1') {
                return;
            }
            form.dataset.hfEditorFormBound = '1';
            form.addEventListener('submit', () => {
                form.querySelectorAll('[data-hf-html-editor]').forEach((wrap) => {
                    if (wrap.classList.contains('hf-html-editor--source')) {
                        const surface = wrap.querySelector('.hf-html-editor__surface');
                        const textarea = wrap.querySelector('textarea');
                        if (surface && textarea) {
                            surface.innerHTML = textarea.value || '';
                        }
                        wrap.classList.remove('hf-html-editor--source');
                    }
                    syncToTextarea(wrap);
                });
            });
        });
    }

    function boot() {
        initAll(document);
        bindForms(document);
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('ea:tab-content-loaded', boot);
})();
