(function () {
  'use strict';

  function uid() {
    if (window.crypto && crypto.randomUUID) {
      return crypto.randomUUID().slice(0, 8);
    }
    return String(Date.now()) + Math.random().toString(16).slice(2, 6);
  }

  function init(root) {
    if (root.dataset.hfReady === '1') return;
    root.dataset.hfReady = '1';

    const defaultStart = root.dataset.defaultStart || '';
    const defaultEnd = root.dataset.defaultEnd || '';
    const headRow = root.querySelector('[data-hf-head-row]');
    const body = root.querySelector('[data-hf-body]');
    const deleteBin = root.querySelector('[data-hf-delete-bin]');
    const addCol = root.querySelector('[data-hf-add-col]');
    const addOptionRow = root.querySelector('[data-hf-add-option-row]');

    if (!headRow || !body || !addCol || !addOptionRow) return;

    function bindActiveToggle(card) {
      const toggle = card.querySelector('[data-hf-active-toggle]');
      if (!toggle) return;
      const sync = () => card.classList.toggle('is-active', toggle.checked);
      toggle.addEventListener('change', sync);
      sync();
    }

    root.querySelectorAll('.hf-price__period-card').forEach(bindActiveToggle);

    function updateAddOptionColspan() {
      const periodCount = root.querySelectorAll('[data-hf-period-col]').length;
      const filler = addOptionRow.querySelector('td[colspan]');
      if (filler) {
        filler.colSpan = periodCount + 1;
      }
    }

    function buildPeriodHeader(key) {
      const th = document.createElement('th');
      th.className = 'hf-price__period';
      th.dataset.hfPeriodCol = '';
      th.dataset.key = key;
      th.innerHTML =
        '<div class="hf-price__period-card is-active">' +
        '<div class="hf-price__period-top">' +
        '<span class="hf-price__badge">новый</span>' +
        '<button type="button" class="hf-price__icon-btn" data-hf-remove-period title="Удалить период" aria-label="Удалить период">×</button>' +
        '</div>' +
        '<label class="hf-price__field"><span>Название</span>' +
        '<input type="text" name="periods[' + key + '][name]" value="" required placeholder="Например, До 1 июня"></label>' +
        '<label class="hf-price__field"><span>Начало</span>' +
        '<input type="datetime-local" name="periods[' + key + '][startAt]" value="' + defaultStart + '" required></label>' +
        '<label class="hf-price__field"><span>Окончание</span>' +
        '<input type="datetime-local" name="periods[' + key + '][endAt]" value="' + defaultEnd + '" required></label>' +
        '<label class="hf-price__switch">' +
        '<input type="checkbox" name="periods[' + key + '][isActive]" value="1" checked data-hf-active-toggle>' +
        '<span>Активен</span></label>' +
        '</div>';
      bindActiveToggle(th.querySelector('.hf-price__period-card'));
      return th;
    }

    function buildPriceCell(periodKey, optionKey) {
      const td = document.createElement('td');
      td.dataset.hfPriceCell = '';
      td.dataset.key = periodKey;
      td.innerHTML =
        '<div class="hf-price__money">' +
        '<input type="number" min="0" step="1" name="prices[' + periodKey + '][' + optionKey + ']" value="0" required inputmode="numeric">' +
        '<span>₽</span></div>';
      return td;
    }

    function buildOptionRow(optionKey) {
      const tr = document.createElement('tr');
      tr.dataset.hfOptionRow = '';
      tr.dataset.optionKey = optionKey;
      tr.innerHTML =
        '<td class="hf-price__sticky hf-price__option">' +
        '<div class="hf-price__option-line">' +
        '<input type="text" class="hf-price__option-input" name="options[' + optionKey + '][name]" value="" required placeholder="Название варианта">' +
        '<button type="button" class="hf-price__icon-btn hf-price__icon-btn--compact" data-hf-remove-option title="Удалить вариант" aria-label="Удалить вариант">×</button>' +
        '</div></td>';

      root.querySelectorAll('[data-hf-period-col]').forEach(function (col) {
        tr.appendChild(buildPriceCell(col.dataset.key, optionKey));
      });

      const spacer = document.createElement('td');
      spacer.className = 'hf-price__add-col';
      spacer.dataset.hfAddColSpacer = '';
      spacer.setAttribute('aria-hidden', 'true');
      tr.appendChild(spacer);

      return tr;
    }

    function addPeriod() {
      const key = 'new_' + uid();
      const th = buildPeriodHeader(key);
      headRow.insertBefore(th, addCol);

      body.querySelectorAll('[data-hf-option-row]').forEach(function (row) {
        const optionKey = row.dataset.optionKey;
        const spacer = row.querySelector('[data-hf-add-col-spacer]');
        if (optionKey && spacer) {
          row.insertBefore(buildPriceCell(key, optionKey), spacer);
        }
      });

      updateAddOptionColspan();

      const wrap = root.querySelector('.hf-price__sheet-wrap');
      if (wrap) wrap.scrollLeft = wrap.scrollWidth;
      th.querySelector('input[type="text"]')?.focus();
    }

    function addOption() {
      const key = 'new_' + uid();
      const row = buildOptionRow(key);
      body.insertBefore(row, addOptionRow);
      row.querySelector('input[type="text"]')?.focus();
    }

    function removePeriod(col) {
      const key = col.dataset.key;
      if (!key) return;

      const nameInput = col.querySelector('input[name*="[name]"]');
      const label = nameInput && nameInput.value ? '«' + nameInput.value + '»' : 'этот период';
      if (!window.confirm('Удалить ' + label + '? Нажмите «Сохранить», чтобы применить.')) {
        return;
      }

      if (!String(key).startsWith('new_')) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_periods[]';
        input.value = key;
        deleteBin.appendChild(input);
      }

      root.querySelectorAll('[data-hf-price-cell]').forEach(function (cell) {
        if (cell.dataset.key === key) cell.remove();
      });
      col.remove();
      updateAddOptionColspan();
    }

    function removeOption(row) {
      const key = row.dataset.optionKey;
      if (!key) return;

      const nameInput = row.querySelector('input[name*="[name]"]');
      const label = nameInput && nameInput.value ? '«' + nameInput.value + '»' : 'этот вариант';
      if (!window.confirm('Удалить ' + label + '? Нажмите «Сохранить», чтобы применить.')) {
        return;
      }

      if (!String(key).startsWith('new_')) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_options[]';
        input.value = key;
        deleteBin.appendChild(input);
      }

      row.remove();
    }

    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-hf-add-period]')) {
        e.preventDefault();
        e.stopPropagation();
        addPeriod();
        return;
      }
      if (e.target.closest('[data-hf-add-option]')) {
        e.preventDefault();
        e.stopPropagation();
        addOption();
        return;
      }
      const periodBtn = e.target.closest('[data-hf-remove-period]');
      if (periodBtn && root.contains(periodBtn)) {
        e.preventDefault();
        e.stopPropagation();
        const col = periodBtn.closest('[data-hf-period-col]');
        if (col) removePeriod(col);
        return;
      }
      const optionBtn = e.target.closest('[data-hf-remove-option]');
      if (optionBtn && root.contains(optionBtn)) {
        e.preventDefault();
        e.stopPropagation();
        const row = optionBtn.closest('[data-hf-option-row]');
        if (row) removeOption(row);
      }
    });

    updateAddOptionColspan();
  }

  function boot() {
    document.querySelectorAll('[data-hf-pricing-matrix]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
