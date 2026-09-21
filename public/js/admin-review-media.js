(() => {
  const boot = () => {
    const root = document.querySelector('[data-review-media]');
    if (!root) return;

    const uploadUrl = root.dataset.uploadUrl;
    const reorderUrl = root.dataset.reorderUrl;
    const updateUrlTpl = root.dataset.updateUrl;
    const deleteUrlTpl = root.dataset.deleteUrl;
    const csrf = root.dataset.csrf
      || document.querySelector('meta[name="hf-review-media-csrf"]')?.getAttribute('content')
      || '';

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

    if (!dropzone || !fileInput || !grid || !uploadUrl) return;

    /** @type {File[]} */
    let pendingFiles = [];

    function showError(message) {
      if (!alertError) return;
      alertSuccess?.classList.add('is-hidden');
      alertError.textContent = message;
      alertError.classList.remove('is-hidden');
    }

    function showSuccess(message) {
      if (!alertSuccess) return;
      alertError?.classList.add('is-hidden');
      alertSuccess.textContent = message;
      alertSuccess.classList.remove('is-hidden');
      window.setTimeout(() => alertSuccess.classList.add('is-hidden'), 3500);
    }

    function clearAlerts() {
      alertError?.classList.add('is-hidden');
      alertSuccess?.classList.add('is-hidden');
    }

    function updateEmpty() {
      if (!empty) return;
      const n = grid.querySelectorAll('.hf-gallery__card').length;
      empty.classList.toggle('is-hidden', n > 0);
    }

    function renderBatch() {
      if (!batchList || !batchCount || !batch) return;
      batchList.innerHTML = '';
      pendingFiles.forEach((file, index) => {
        const li = document.createElement('li');
        const isVideo = file.type.startsWith('video/');
        li.textContent = `${isVideo ? '▶ ' : ''}${file.name}`;
        li.dataset.index = String(index);
        batchList.appendChild(li);
      });
      batchCount.textContent = `${pendingFiles.length} файл(ов)`;
      batch.classList.toggle('is-hidden', pendingFiles.length === 0);
    }

    function addFiles(fileList) {
      clearAlerts();
      const accepted = [...(fileList || [])].filter(
        (f) => f.type.startsWith('image/') || f.type.startsWith('video/'),
      );
      if (!accepted.length) {
        showError('Нужны изображения или видео (JPG/PNG/WEBP/GIF, MP4/WebM/MOV).');
        return;
      }
      pendingFiles = pendingFiles.concat(accepted);
      renderBatch();
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
      addFiles(e.dataTransfer?.files);
    });
    fileInput.addEventListener('change', () => {
      addFiles(fileInput.files);
      fileInput.value = '';
    });

    batchReset?.addEventListener('click', () => {
      pendingFiles = [];
      renderBatch();
      clearAlerts();
    });

    batchSubmit?.addEventListener('click', async () => {
      if (!pendingFiles.length) return;
      clearAlerts();
      loading?.classList.remove('is-hidden');
      if (batchSubmit) batchSubmit.disabled = true;
      const form = new FormData();
      pendingFiles.forEach((file) => form.append('files[]', file));
      try {
        const res = await fetch(uploadUrl, {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-Review-Media-Token': csrf },
          body: form,
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Ошибка загрузки');
        (data.items || []).forEach((item) => appendCard(item));
        pendingFiles = [];
        renderBatch();
        updateEmpty();
        showSuccess(`Загружено: ${data.count}`);
      } catch (err) {
        showError(err.message || 'Не удалось загрузить');
      } finally {
        loading?.classList.add('is-hidden');
        if (batchSubmit) batchSubmit.disabled = false;
      }
    });

    function appendCard(item) {
      const li = document.createElement('li');
      li.className = 'hf-gallery__card';
      li.dataset.id = String(item.id);
      li.dataset.published = item.published ? '1' : '0';
      li.dataset.kind = item.kind;
      const mediaHtml = item.kind === 'video'
        ? `<video src="${item.url}" muted playsinline preload="metadata"></video><span class="hf-review-media__video-badge">Видео</span>`
        : `<img src="${item.thumbUrl || item.url}" alt="">`;
      li.innerHTML = `
      <div class="hf-gallery__card-drag" data-drag-handle title="Перетащить" aria-label="Перетащить">⋮⋮</div>
      <div class="hf-gallery__card-image">
        ${mediaHtml}
      </div>
      <div class="hf-gallery__card-body">
        <label class="hf-gallery__published">
          <input type="checkbox" data-published ${item.published ? 'checked' : ''}>
          Показывать на сайте
        </label>
        <button type="button" class="hf-gallery__delete" data-delete title="Удалить">Удалить</button>
      </div>
    `;
      grid.appendChild(li);
    }

    function urlFor(tpl, id) {
      return String(tpl || '').replace('__ID__', String(id));
    }

    grid.addEventListener('change', async (e) => {
      const input = e.target.closest('[data-published]');
      if (!input) return;
      const card = input.closest('.hf-gallery__card');
      const id = card?.dataset.id;
      if (!id) return;
      try {
        const res = await fetch(urlFor(updateUrlTpl, id), {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ published: input.checked, _token: csrf }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Ошибка');
        card.dataset.published = input.checked ? '1' : '0';
        const badge = card.querySelector('.hf-gallery__badge');
        if (!input.checked && !badge) {
          const wrap = card.querySelector('.hf-gallery__card-image');
          const span = document.createElement('span');
          span.className = 'hf-gallery__badge';
          span.textContent = 'Скрыто';
          wrap?.appendChild(span);
        } else if (input.checked && badge) {
          badge.remove();
        }
      } catch (err) {
        input.checked = !input.checked;
        showError(err.message || 'Не удалось обновить');
      }
    });

    grid.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-delete]');
      if (!btn) return;
      const card = btn.closest('.hf-gallery__card');
      const id = card?.dataset.id;
      if (!id || !window.confirm('Удалить этот файл?')) return;
      try {
        const res = await fetch(urlFor(deleteUrlTpl, id), {
          method: 'DELETE',
          headers: { Accept: 'application/json', 'X-Review-Media-Token': csrf },
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Ошибка');
        card.remove();
        updateEmpty();
        showSuccess('Удалено');
      } catch (err) {
        showError(err.message || 'Не удалось удалить');
      }
    });

    if (typeof Sortable !== 'undefined') {
      Sortable.create(grid, {
        handle: '[data-drag-handle]',
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: async () => {
          const ids = Array.from(grid.querySelectorAll('.hf-gallery__card')).map((el) => el.dataset.id);
          try {
            const res = await fetch(reorderUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
              body: JSON.stringify({ ids, _token: csrf }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.error || 'Ошибка сортировки');
          } catch (err) {
            showError(err.message || 'Не удалось сохранить порядок');
          }
        },
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
