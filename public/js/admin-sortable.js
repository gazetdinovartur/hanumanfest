document.addEventListener('DOMContentLoaded', () => {
  whenSortableReady(initIndexSortable);
  window.setTimeout(() => {
    if (typeof Sortable === 'undefined') {
      initIndexNativeSortable();
    }
  }, 700);
});

function whenSortableReady(callback) {
  if (typeof Sortable !== 'undefined') {
    callback();
    return;
  }
  let tries = 0;
  const timer = window.setInterval(() => {
    tries += 1;
    if (typeof Sortable !== 'undefined') {
      window.clearInterval(timer);
      callback();
    } else if (tries > 40) {
      window.clearInterval(timer);
    }
  }, 50);
}

function reorderMeta() {
  return {
    token: document.querySelector('meta[name="hf-reorder-csrf"]')?.getAttribute('content') || '',
    url: document.querySelector('meta[name="hf-reorder-url"]')?.getAttribute('content') || '',
  };
}

async function postOrder(ids) {
  const { token, url } = reorderMeta();
  if (!url || ids.length === 0) {
    return;
  }
  await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ ids, _token: token }),
  });
}

function collectIds(tbody) {
  return [...tbody.querySelectorAll('[data-sortable-id]')].map((el) => el.getAttribute('data-sortable-id'));
}

function initIndexSortable() {
  const tbody = document.querySelector('.ea-index table.datagrid tbody, .ea-index table.table tbody');
  if (!tbody || typeof Sortable === 'undefined' || !tbody.querySelector('[data-sortable-id]')) {
    return;
  }

  Sortable.create(tbody, {
    handle: '.hf-admin-drag',
    animation: 160,
    ghostClass: 'hf-admin-row-ghost',
    chosenClass: 'hf-admin-row-chosen',
    onEnd: async () => {
      try {
        await postOrder(collectIds(tbody));
      } catch {
        // UI order kept
      }
    },
  });
}

function initIndexNativeSortable() {
  const tbody = document.querySelector('.ea-index table.datagrid tbody, .ea-index table.table tbody');
  if (!tbody || !tbody.querySelector('[data-sortable-id]')) {
    return;
  }

  let dragRow = null;

  tbody.querySelectorAll('.hf-admin-drag').forEach((handle) => {
    handle.addEventListener('pointerdown', () => {
      const row = handle.closest('tr');
      if (row) row.draggable = true;
    });
    handle.addEventListener('pointerup', () => {
      const row = handle.closest('tr');
      if (row) row.draggable = false;
    });
  });

  tbody.addEventListener('dragstart', (event) => {
    const row = event.target instanceof Element ? event.target.closest('tr') : null;
    if (!row || !tbody.contains(row)) return;
    dragRow = row;
    row.classList.add('hf-admin-row-chosen');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', 'row');
  });

  tbody.addEventListener('dragend', () => {
    if (dragRow) {
      dragRow.classList.remove('hf-admin-row-chosen');
      dragRow.draggable = false;
    }
    dragRow = null;
    postOrder(collectIds(tbody)).catch(() => {});
  });

  tbody.addEventListener('dragover', (event) => {
    event.preventDefault();
    const over = event.target instanceof Element ? event.target.closest('tr') : null;
    if (!dragRow || !over || over === dragRow || !tbody.contains(over)) return;
    const rect = over.getBoundingClientRect();
    tbody.insertBefore(dragRow, event.clientY < rect.top + rect.height / 2 ? over : over.nextSibling);
  });
}
