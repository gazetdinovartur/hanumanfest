(() => {
  const root = document.querySelector('.hf-gallery');
  if (!root) return;

  const uploadUrl = root.dataset.uploadUrl;
  const reorderUrl = root.dataset.reorderUrl;
  const updateUrlTpl = root.dataset.updateUrl;
  const deleteUrlTpl = root.dataset.deleteUrl;
  const csrf = root.dataset.csrf;

  const dropzone = root.querySelector('[data-dropzone]');
  const fileInput = root.querySelector('[data-file-input]');
  const loading = root.querySelector('[data-loading]');
  const batch = root.querySelector('[data-batch]');
  const batchList = root.querySelector('[data-batch-list]');
  const batchCount = root.querySelector('[data-batch-count]');
  const batchReset = root.querySelector('[data-batch-reset]');
  const batchSubmit = root.querySelector('[data-batch-submit]');
  const alertError = root.querySelector('[data-alert-error]');
  const alertSuccess = root.querySelector('[data-alert-success]');
  const grid = root.querySelector('[data-grid]');
  const empty = root.querySelector('[data-empty]');
  const emptyFilter = root.querySelector('[data-empty-filter]');
  const itemCount = root.querySelector('[data-item-count]');
  const statPublished = root.querySelector('[data-stat-published]');
  const statHidden = root.querySelector('[data-stat-hidden]');
  const filterButtons = root.querySelectorAll('[data-filter]');

  /** @type {'all'|'published'|'hidden'} */
  let activeFilter = 'all';

  /** @type {File[]} */
  let pendingFiles = [];

  function showError(message) {
    alertSuccess.classList.add('is-hidden');
    alertError.textContent = message;
    alertError.classList.remove('is-hidden');
  }

  function showSuccess(message) {
    alertError.classList.add('is-hidden');
    alertSuccess.textContent = message;
    alertSuccess.classList.remove('is-hidden');
    window.setTimeout(() => alertSuccess.classList.add('is-hidden'), 3500);
  }

  function clearAlerts() {
    alertError.classList.add('is-hidden');
    alertSuccess.classList.add('is-hidden');
  }

  function updateCount() {
    const cards = grid.querySelectorAll('.hf-gallery__card');
    const n = cards.length;
    let published = 0;
    cards.forEach((card) => {
      if (card.dataset.published === '1') {
        published += 1;
      }
    });

    itemCount.textContent = String(n);
    if (statPublished) statPublished.textContent = String(published);
    if (statHidden) statHidden.textContent = String(n - published);
    empty.classList.toggle('is-hidden', n > 0);
    applyFilter();
  }

  function applyFilter() {
    const cards = grid.querySelectorAll('.hf-gallery__card');
    let visible = 0;

    cards.forEach((card) => {
      const isPublished = card.dataset.published === '1';
      const show =
        activeFilter === 'all'
        || (activeFilter === 'published' && isPublished)
        || (activeFilter === 'hidden' && !isPublished);

      card.classList.toggle('is-filter-hidden', !show);
      if (show) visible += 1;
    });

    if (emptyFilter) {
      emptyFilter.classList.toggle('is-hidden', visible > 0 || cards.length === 0);
    }
  }

  filterButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      activeFilter = btn.dataset.filter || 'all';
      filterButtons.forEach((b) => {
        const active = b === btn;
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      applyFilter();
    });
  });

  function updateUrl(id) {
    return updateUrlTpl.replace('__ID__', String(id));
  }

  function deleteUrl(id) {
    return deleteUrlTpl.replace('__ID__', String(id));
  }

  function renderBatch() {
    batchList.innerHTML = '';
    pendingFiles.forEach((file, index) => {
      const li = document.createElement('li');
      const url = URL.createObjectURL(file);
      li.innerHTML = `<img src="${url}" alt=""><button type="button" data-remove="${index}" aria-label="Убрать">×</button>`;
      batchList.appendChild(li);
    });
    batchCount.textContent = `${pendingFiles.length} файл(ов)`;
    batch.classList.toggle('is-hidden', pendingFiles.length === 0);
  }

  function addFiles(list) {
    clearAlerts();
    const accepted = [...list].filter((f) => f.type.startsWith('image/'));
    if (!accepted.length) {
      showError('Нужны изображения (JPG/PNG/WEBP/GIF).');
      return;
    }
    pendingFiles = pendingFiles.concat(accepted);
    renderBatch();
  }

  function createCard(item) {
    const li = document.createElement('li');
    li.className = 'hf-gallery__card';
    li.dataset.id = String(item.id);
    li.dataset.published = item.published ? '1' : '0';

    const badge = item.published ? '' : '<span class="hf-gallery__badge">Скрыто</span>';
    const checked = item.published ? 'checked' : '';
    const caption = item.caption ? String(item.caption).replace(/"/g, '&quot;') : '';

    li.innerHTML = `
      <div class="hf-gallery__card-drag" data-drag-handle title="Перетащить" aria-label="Перетащить">⋮⋮</div>
      <div class="hf-gallery__card-image">
        <img src="${item.imageUrl}" alt="${caption}">
        ${badge}
      </div>
      <div class="hf-gallery__card-body">
        <input type="text" class="hf-gallery__caption" value="${caption}" placeholder="Подпись (необязательно)" maxlength="255" data-caption>
        <label class="hf-gallery__published">
          <input type="checkbox" data-published ${checked}>
          Показывать на сайте
        </label>
        <button type="button" class="hf-gallery__delete" data-delete title="Удалить">Удалить</button>
      </div>`;

    return li;
  }

  async function saveOrder() {
    const ids = [...grid.querySelectorAll('.hf-gallery__card')].map((el) => Number(el.dataset.id));
    const res = await fetch(reorderUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ ids, _token: csrf }),
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error(data.error || 'Не удалось сохранить порядок');
    }
  }

  async function patchItem(id, payload) {
    const res = await fetch(updateUrl(id), {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ ...payload, _token: csrf }),
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error(data.error || 'Ошибка сохранения');
    }
    return data.item;
  }

  async function removeItem(id) {
    const res = await fetch(deleteUrl(id), {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-Gallery-Token': csrf },
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error(data.error || 'Ошибка удаления');
    }
  }

  function syncCardPublished(card, published) {
    card.dataset.published = published ? '1' : '0';
    const image = card.querySelector('.hf-gallery__card-image');
    let badge = card.querySelector('.hf-gallery__badge');
    if (published && badge) {
      badge.remove();
    }
    if (!published && !badge && image) {
      badge = document.createElement('span');
      badge.className = 'hf-gallery__badge';
      badge.textContent = 'Скрыто';
      image.appendChild(badge);
    }
  }

  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      fileInput.click();
    }
  });
  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('is-dragover');
  });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragover'));
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('is-dragover');
    addFiles(e.dataTransfer.files);
  });
  fileInput.addEventListener('change', () => addFiles(fileInput.files || []));

  batchList.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove]');
    if (!btn) return;
    pendingFiles.splice(Number(btn.dataset.remove), 1);
    renderBatch();
  });

  batchReset.addEventListener('click', () => {
    pendingFiles = [];
    renderBatch();
    clearAlerts();
  });

  batchSubmit.addEventListener('click', async () => {
    if (!pendingFiles.length) return;
    clearAlerts();
    loading.classList.remove('is-hidden');
    batchSubmit.disabled = true;

    const form = new FormData();
    pendingFiles.forEach((f) => form.append('files[]', f));

    try {
      const res = await fetch(uploadUrl, {
        method: 'POST',
        body: form,
        headers: { Accept: 'application/json', 'X-Gallery-Token': csrf },
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Ошибка загрузки');

      data.items.forEach((item) => grid.appendChild(createCard(item)));
      updateCount();
      showSuccess(`Загружено: ${data.count}`);
      pendingFiles = [];
      renderBatch();
      fileInput.value = '';
    } catch (err) {
      showError(err.message || 'Ошибка загрузки');
    } finally {
      loading.classList.add('is-hidden');
      batchSubmit.disabled = false;
    }
  });

  grid.addEventListener('click', async (e) => {
    const deleteBtn = e.target.closest('[data-delete]');
    if (!deleteBtn) return;

    const card = deleteBtn.closest('.hf-gallery__card');
    if (!card || !window.confirm('Удалить фото с сайта и с диска?')) return;
    try {
      await removeItem(Number(card.dataset.id));
      card.remove();
      updateCount();
      showSuccess('Фото удалено');
    } catch (err) {
      showError(err.message || 'Ошибка удаления');
    }
  });

  grid.addEventListener('change', async (e) => {
    const publishedInput = e.target.closest('[data-published]');
    if (!publishedInput) return;

    const card = publishedInput.closest('.hf-gallery__card');
    if (!card) return;
    try {
      const item = await patchItem(Number(card.dataset.id), { published: publishedInput.checked });
      syncCardPublished(card, item.published);
      updateCount();
    } catch (err) {
      publishedInput.checked = !publishedInput.checked;
      showError(err.message || 'Ошибка сохранения');
    }
  });

  grid.addEventListener('focusout', async (e) => {
    const captionInput = e.target.closest('[data-caption]');
    if (!captionInput) return;
    const card = captionInput.closest('.hf-gallery__card');
    if (!card) return;
    try {
      await patchItem(Number(card.dataset.id), { caption: captionInput.value });
    } catch (err) {
      showError(err.message || 'Не удалось сохранить подпись');
    }
  });

  if (typeof Sortable !== 'undefined') {
    Sortable.create(grid, {
      animation: 150,
      handle: '[data-drag-handle]',
      ghostClass: 'sortable-ghost',
      dragClass: 'sortable-drag',
      onEnd: async () => {
        try {
          await saveOrder();
        } catch (err) {
          showError(err.message || 'Не удалось сохранить порядок');
        }
      },
    });
  }
})();
