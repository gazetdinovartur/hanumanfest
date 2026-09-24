(function () {
    'use strict';

    const root = document.querySelector('[data-hf-highlights]');
    if (!root) {
        return;
    }

    const reorderUrl = root.getAttribute('data-reorder-url') || '';
    const csrf = root.getAttribute('data-csrf') || '';
    const errorEl = root.querySelector('[data-alert-error]');
    const successEl = root.querySelector('[data-alert-success]');

    function show(el, message) {
        if (!el) {
            return;
        }
        el.textContent = message;
        el.classList.remove('is-hidden');
    }

    function hide(el) {
        if (!el) {
            return;
        }
        el.textContent = '';
        el.classList.add('is-hidden');
    }

    function columnIds(column) {
        const pane = root.querySelector('[data-column="' + column + '"]');
        if (!pane) {
            return [];
        }

        return [...pane.querySelectorAll('.hf-hl-tile')].map((tile) => Number(tile.dataset.id)).filter(Boolean);
    }

    async function saveOrder() {
        hide(errorEl);
        hide(successEl);
        const res = await fetch(reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                left: columnIds('left'),
                right: columnIds('right'),
                _token: csrf,
            }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
            throw new Error(data.error || 'Не удалось сохранить порядок');
        }
        show(successEl, 'Порядок сохранён');
    }

    const columns = root.querySelectorAll('[data-column]');
    if (typeof Sortable === 'undefined') {
        show(errorEl, 'Не загрузилось перетаскивание. Обновите страницу.');
        return;
    }

    columns.forEach((column) => {
        Sortable.create(column, {
            group: 'highlights',
            handle: '[data-drag-handle]',
            draggable: '.hf-hl-tile',
            animation: 160,
            ghostClass: 'is-ghost',
            dragClass: 'is-drag',
            onEnd: async () => {
                try {
                    await saveOrder();
                } catch (err) {
                    show(errorEl, err.message || 'Не удалось сохранить порядок');
                }
            },
        });
    });
})();
