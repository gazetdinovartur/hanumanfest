(function () {
  'use strict';

  const config = window.uaeBridgeConfig || {};
  const DEFAULT_API_BASE = '/api';
  const apiBase = (config.apiBase || DEFAULT_API_BASE).replace(/\/$/, '');

  if (!apiBase) {
    return;
  }

  function toJsonResponse(response) {
    return response
      .json()
      .catch(() => ({}))
      .then((data) => {
        if (!response.ok) {
          const error = new Error(data.error || data.detail || data.title || 'Request failed');
          error.payUrl = data.payUrl || '';
          error.paidAmount = data.paidAmount;
          error.remainingAmount = data.remainingAmount;
          error.amountDueNow = data.amountDueNow;
          error.totalAmount = data.totalAmount;
          error.token = data.token || '';
          error.cancellable = !!data.cancellable;
          throw error;
        }

        return data;
      });
  }

  function request(path, options) {
    return fetch(apiBase + path, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      ...options,
    }).then(toJsonResponse);
  }

  function formatMoney(value) {
    const num = Number(value || 0);
    return `${num.toLocaleString('ru-RU')} ₽`;
  }

  function setError(root, message) {
    const errorBox = root.querySelector('[data-uae-error]');
    if (!errorBox) return;
    if (!message) {
      errorBox.classList.add('d-none');
      errorBox.textContent = '';
      return;
    }
    errorBox.classList.remove('d-none');
    errorBox.textContent = message;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function setStatus(root, message) {
    const status = root.querySelector('[data-uae-status]');
    if (!status) {
      return;
    }
    status.textContent = message || '';
    status.classList.toggle('is-busy', Boolean(message));
  }

  function getQueryParam(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function rememberPaymentId(paymentId) {
    if (!paymentId) return;
    try {
      sessionStorage.setItem('hf_last_payment_id', paymentId);
    } catch (e) {}
  }

  function lastPaymentId() {
    try {
      return sessionStorage.getItem('hf_last_payment_id');
    } catch (e) {
      return null;
    }
  }

  function tokenFromPayUrl(url) {
    const match = String(url || '').match(/\/pay\/([^/?#]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  }

  function detectTokenFromPath() {
    const match = window.location.pathname.match(/\/pay\/([^/?#]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  }

  function normalizePhone(phone) {
    if (!phone) return '';
    let digits = String(phone).replace(/\D/g, '');
    if (digits.startsWith('8')) digits = '7' + digits.slice(1);
    if (digits.length === 10 && digits.startsWith('9')) digits = '7' + digits;
    if (digits.length !== 11) return '';
    return '+' + digits;
  }

  function bindCopyButton(root) {
    const input = root.querySelector('[data-uae-copy-input]');
    const button = root.querySelector('[data-uae-copy-btn]');
    if (!input || !button || button.dataset.copyBound) return;
    button.dataset.copyBound = '1';
    button.addEventListener('click', () => {
      const value = input.value;
      if (!value) return;
      const done = () => {
        const prev = button.textContent;
        button.textContent = 'Скопировано';
        setTimeout(() => {
          button.textContent = prev;
        }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(() => {
          input.select();
          document.execCommand('copy');
          done();
        });
        return;
      }
      input.select();
      document.execCommand('copy');
      done();
    });
  }

  function fillCopyField(root, url) {
    const input = root.querySelector('[data-uae-copy-input]');
    if (input) input.value = url || '';
    bindCopyButton(root);
  }

  function fillReturnDetail(panel, key, value) {
    const row = panel.querySelector('[data-uae-return-detail="' + key + '"]');
    if (!row) {
      return;
    }
    const text = value == null || value === '' ? '' : String(value);
    row.classList.toggle('d-none', text === '');
    const el = row.querySelector('[data-uae-return-detail-value]');
    if (el && text !== '') {
      el.textContent = text;
    }
  }

  function fillReturnSummary(panel, status) {
    if (!panel) {
      return;
    }
    const paid = panel.querySelector('[data-uae-return-paid]');
    const remaining = panel.querySelector('[data-uae-return-remaining]');
    const total = panel.querySelector('[data-uae-return-total]');
    if (paid) paid.textContent = formatMoney(status.paidAmount);
    if (remaining) remaining.textContent = formatMoney(status.remainingAmount);
    if (total) total.textContent = formatMoney(status.totalAmount);

    fillReturnDetail(panel, 'name', status.name);
    fillReturnDetail(panel, 'email', status.email);
    fillReturnDetail(panel, 'phone', status.phone);
    fillReturnDetail(panel, 'option', status.participationOptionName);
    fillReturnDetail(panel, 'period', status.pricingPeriodName);
    fillReturnDetail(panel, 'adults', status.adultsCount != null ? String(status.adultsCount) : '');
    fillReturnDetail(panel, 'children', status.childrenCount != null ? String(status.childrenCount) : '');
    fillReturnDetail(panel, 'transfer', status.transferIncluded ? 'Да' : 'Нет');
    fillReturnDetail(panel, 'roommate', status.tentRoommate);

    let paymentLabel = '';
    if (status.paymentFactor != null && status.paymentFactor !== '') {
      paymentLabel = Number(status.paymentFactor) < 1 ? 'Предоплата 50%' : 'Полная оплата';
    } else if (Number(status.remainingAmount || 0) > 0) {
      paymentLabel = 'Предоплата 50%';
    } else if (status.paid) {
      paymentLabel = 'Полная оплата';
    }
    fillReturnDetail(panel, 'payment', paymentLabel);
  }

  function initRegistration(root) {
    const form = root.querySelector('[data-uae-form]');
        let latestPricing = null;
    /** @type {Record<string, {id:number, code:string, name:string}>} */
    let optionsById = {};
    let emailTimer = null;

    const fields = {
      name: form.querySelector('[name="name"]'),
      email: form.querySelector('[name="email"]'),
      phone: form.querySelector('[name="phone"]'),
      participationOptionId: form.querySelector('[name="participationOptionId"]'),
      adultsCount: form.querySelector('[name="adultsCount"]'),
      childrenCount: form.querySelector('[name="childrenCount"]'),
      transferIncluded: form.querySelector('[name="transferIncluded"]'),
      paymentFactor: form.querySelector('[name="paymentFactor"]'),
      tentRoommate: form.querySelector('[name="tentRoommate"]'),
    };

    const conditional = {
      tentRoommate: root.querySelector('[data-uae-conditional="tent-roommate"]'),
      houseBooking: root.querySelector('[data-uae-conditional="house-booking"]'),
    };

    const ui = {
      total: root.querySelector('[data-uae-total]'),
      now: root.querySelector('[data-uae-now]'),
      meta: root.querySelector('[data-uae-meta]'),
      submit: root.querySelector('[data-uae-submit]'),
      factorHints: root.querySelectorAll('[data-uae-factor-hint]'),
      existing: root.querySelector('[data-uae-existing-payment]'),
      existingText: root.querySelector('[data-uae-existing-text]'),
      existingPay: root.querySelector('[data-uae-existing-pay]'),
      existingCancel: root.querySelector('[data-uae-existing-cancel]'),
      copyWrap: root.querySelector('[data-uae-copy-wrap]'),
      copyLabel: root.querySelector('[data-uae-copy-label]'),
    };

    function optionKind(option) {
      if (!option) return null;
      const code = String(option.code || '').toUpperCase();
      const name = String(option.name || '').toLowerCase();
      if (code.startsWith('OUR_TENT') || name.includes('нашей палатке')) return 'tent';
      if (code.startsWith('OWN_HOUSE') || name.includes('своем жилье')) return 'house';
      return null;
    }

    function updateConditionalFields() {
      const selected = optionsById[String(fields.participationOptionId.value)] || null;
      const kind = optionKind(selected);
      const showTent = kind === 'tent';
      const showHouse = kind === 'house';

      if (conditional.tentRoommate) {
        conditional.tentRoommate.classList.toggle('hf-reg-extra--off', !showTent);
        conditional.tentRoommate.toggleAttribute('inert', !showTent);
        if (!showTent && fields.tentRoommate) {
          fields.tentRoommate.value = '';
        }
      }
      if (conditional.houseBooking) {
        conditional.houseBooking.classList.toggle('hf-reg-extra--off', !showHouse);
        conditional.houseBooking.toggleAttribute('inert', !showHouse);
      }
    }

    function updateFactorHint() {
      if (!ui.factorHints.length) return;
      const factor = String(fields.paymentFactor.value || '1');
      ui.factorHints.forEach((hint) => {
        const match = hint.getAttribute('data-uae-factor-hint') === factor;
        hint.classList.toggle('hf-reg-hint--off', !match);
        hint.setAttribute('aria-hidden', match ? 'false' : 'true');
      });
    }

    function hideExistingPayment() {
      if (!ui.existing) return;
      ui.existing.classList.add('d-none');
      if (ui.existingCancel) {
        ui.existingCancel.disabled = false;
        ui.existingCancel.dataset.token = '';
      }
      if (ui.submit) {
        ui.submit.classList.remove('d-none');
        ui.submit.disabled = false;
      }
    }

    function showExistingPayment(data) {
      if (!ui.existing) return;
      const paid = Number(data.paidAmount || 0);
      const remaining = Number(data.remainingAmount || 0);
      const dueNow = Number(data.amountDueNow || remaining);
      const payUrl = data.payUrl || '';
      const token = data.token || tokenFromPayUrl(payUrl);
      const cancellable = paid === 0 && !!token && data.cancellable !== false;

      if (ui.existingText) {
        if (payUrl && paid > 0) {
          ui.existingText.textContent =
            'По этому email уже внесена предоплата. Осталось оплатить ' +
            formatMoney(remaining) +
            '. Ссылка также в письме — если его нет, проверьте папку «Спам».';
        } else if (payUrl) {
          ui.existingText.textContent =
            'По этому email уже есть заявка. К оплате сейчас ' +
            formatMoney(dueNow) +
            '. Анкету заполнять заново не нужно.' +
            (cancellable ? ' Если данные другие — отмените заявку и заполните форму снова.' : '');
        } else {
          ui.existingText.textContent =
            data.error || 'По этому email регистрация уже полностью оплачена.';
        }
      }

      if (ui.existingPay) {
        ui.existingPay.classList.toggle('d-none', !payUrl);
        if (payUrl) {
          ui.existingPay.href = payUrl;
          ui.existingPay.textContent = paid > 0
            ? 'Перейти к оплате остатка'
            : 'Оплатить ' + formatMoney(dueNow);
        }
      }

      if (ui.copyWrap) {
        ui.copyWrap.classList.toggle('d-none', !payUrl);
      }
      if (ui.copyLabel) {
        ui.copyLabel.textContent = paid > 0 ? 'Ссылка на оплату остатка' : 'Ссылка на оплату';
      }
      if (payUrl) {
        fillCopyField(ui.existing, payUrl);
      }

      if (ui.existingCancel) {
        ui.existingCancel.classList.toggle('d-none', !cancellable);
        ui.existingCancel.dataset.token = cancellable ? token : '';
        ui.existingCancel.disabled = false;
      }

      if (ui.submit) {
        ui.submit.classList.add('d-none');
        ui.submit.disabled = true;
      }

      setError(root, '');
      setStatus(root, '');
      ui.existing.classList.remove('d-none');
      ui.existing.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function cancelExistingApplication() {
      const token = ui.existingCancel ? ui.existingCancel.dataset.token || '' : '';
      if (!token || !ui.existingCancel) {
        return;
      }

      ui.existingCancel.disabled = true;
      setError(root, '');
      request('/applications/cancel', {
        method: 'POST',
        body: JSON.stringify({ token }),
      })
        .then(() => {
          hideExistingPayment();
          setStatus(root, 'Заявка отменена. Можно зарегистрироваться заново.');
        })
        .catch((err) => {
          ui.existingCancel.disabled = false;
          setError(root, err.message);
        });
    }

    function lookupExistingPayment() {
      const email = fields.email.value.trim();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        hideExistingPayment();
        return;
      }

      request('/payment-links/lookup', {
        method: 'POST',
        body: JSON.stringify({ email }),
      })
        .then((data) => {
          if (fields.email.value.trim().toLowerCase() !== email.toLowerCase()) {
            return;
          }
          if (!data.found || !data.payUrl) {
            hideExistingPayment();
            return;
          }
          showExistingPayment(data);
        })
        .catch(() => {
          hideExistingPayment();
        });
    }

    function scheduleEmailLookup() {
      clearTimeout(emailTimer);
      emailTimer = setTimeout(lookupExistingPayment, 400);
    }

    function getPayloadBase() {
      return {
        participationOptionId: Number(fields.participationOptionId.value),
        adultsCount: Math.max(1, Number(fields.adultsCount.value || 1)),
        childrenCount: Math.max(0, Number(fields.childrenCount.value || 0)),
        transferIncluded: !!fields.transferIncluded.checked,
        paymentFactor: Number(fields.paymentFactor.value || 1),
      };
    }

    function updatePricingView(pricing) {
      latestPricing = pricing;
      ui.total.textContent = formatMoney(pricing.totalAmount);
      ui.now.textContent = formatMoney(pricing.payNowAmount);
      ui.meta.textContent = `${pricing.pricingPeriodName} — ${pricing.participationOptionName}`;
    }

    function recalculate() {
      if (!fields.participationOptionId.value) return Promise.resolve();
      setError(root, '');
      root.classList.add('is-calculating');
      return request('/calculate', {
        method: 'POST',
        body: JSON.stringify(getPayloadBase()),
      })
        .then((pricing) => {
          updatePricingView(pricing);
        })
        .catch((e) => {
          setError(root, e.message);
        })
        .finally(() => {
          root.classList.remove('is-calculating');
        });
    }

    request('/product')
      .then((product) => {
        fields.participationOptionId.innerHTML = '';
        optionsById = {};
        product.participationOptions.forEach((item) => {
          optionsById[String(item.id)] = item;
          const option = document.createElement('option');
          option.value = String(item.id);
          option.textContent = item.price ? `${item.name} — ${item.price} ₽` : item.name;
          fields.participationOptionId.appendChild(option);
        });

        updateConditionalFields();
        updateFactorHint();
        return recalculate();
      })
      .catch((e) => setError(root, e.message));

    // One event per control — select fires both input+change in modern browsers.
    fields.participationOptionId.addEventListener('change', () => {
      updateConditionalFields();
      recalculate();
    });
    fields.paymentFactor.addEventListener('change', () => {
      updateFactorHint();
      recalculate();
    });
    fields.paymentFactor.addEventListener('input', updateFactorHint);
    fields.transferIncluded.addEventListener('change', recalculate);
    fields.adultsCount.addEventListener('input', recalculate);
    fields.childrenCount.addEventListener('input', recalculate);
    fields.email.addEventListener('input', () => {
      hideExistingPayment();
      scheduleEmailLookup();
    });
    fields.email.addEventListener('blur', lookupExistingPayment);
    if (ui.existingCancel) {
      ui.existingCancel.addEventListener('click', cancelExistingApplication);
    }
    updateFactorHint();

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      setError(root, '');
      setStatus(root, 'Создаём заявку');
      ui.submit.disabled = true;

      const phone = normalizePhone(fields.phone.value);
      if (!phone) {
        setError(root, 'Проверьте номер телефона');
        setStatus(root, '');
        ui.submit.disabled = false;
        return;
      }

      const selected = optionsById[String(fields.participationOptionId.value)] || null;
      const payload = {
        ...getPayloadBase(),
        name: fields.name.value.trim(),
        email: fields.email.value.trim(),
        phone,
      };

      if (optionKind(selected) === 'tent' && fields.tentRoommate) {
        const roommate = fields.tentRoommate.value.trim();
        if (roommate) {
          payload.tentRoommate = roommate;
        }
      }

      request('/applications', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
        .then((application) => {
          setStatus(root, 'Готовим страницу оплаты');
          return request('/payments', {
            method: 'POST',
            body: JSON.stringify({ applicationUuid: application.uuid }),
          });
        })
        .then((payment) => {
          rememberPaymentId(payment.payment_id);
          window.location.href = payment.gateway_url;
        })
        .catch((err) => {
          ui.submit.disabled = false;
          setStatus(root, '');
          if (err.payUrl || typeof err.remainingAmount === 'number') {
            showExistingPayment(err);
            return;
          }
          setError(root, err.message);
        });
    });
  }

  function initPayment(root) {
    const button = root.querySelector('[data-uae-pay-btn]');
    const token = root.dataset.token || detectTokenFromPath() || getQueryParam('token');

    if (!button) {
      return;
    }

    if (!token) {
      setError(root, 'Не найден токен ссылки оплаты.');
      button.disabled = true;
      return;
    }

    button.addEventListener('click', () => {
      setError(root, '');
      setStatus(root, 'Открываем оплату');
      button.disabled = true;

      request(`/payment-links/${encodeURIComponent(token)}/pay`, {
        method: 'POST',
      })
        .then((payment) => {
          rememberPaymentId(payment.payment_id);
          window.location.href = payment.gateway_url;
        })
        .catch((e) => {
          button.disabled = false;
          setStatus(root, '');
          setError(root, e.message);
        });
    });
  }

  function showReturnPanel(root, name) {
    root.querySelectorAll('[data-uae-return-panel]').forEach((panel) => {
      panel.classList.toggle('d-none', panel.getAttribute('data-uae-return-panel') !== name);
    });
  }

  function applyReturnStatus(root, status) {
    if (status.paid && Number(status.remainingAmount || 0) > 0) {
      showReturnPanel(root, 'partial');
      const panel = root.querySelector('[data-uae-return-panel="partial"]');
      fillReturnSummary(panel, status);
      const pay = panel.querySelector('[data-uae-return-pay]');
      if (pay) {
        if (status.payUrl) {
          pay.setAttribute('href', status.payUrl);
          pay.classList.remove('d-none');
        } else {
          pay.removeAttribute('href');
          pay.classList.add('d-none');
        }
      }
      if (status.payUrl) {
        fillCopyField(panel, status.payUrl);
      }
      return;
    }

    if (status.paid) {
      showReturnPanel(root, 'full');
      fillReturnSummary(root.querySelector('[data-uae-return-panel="full"]'), status);
      return;
    }

    showReturnPanel(root, 'pending');
  }

  function initReturn(root) {
    const paymentId = getQueryParam('payment_id') || lastPaymentId();

    if (!paymentId) {
      setError(root, 'Не найден payment_id в адресе возврата.');
      showReturnPanel(root, 'pending');
      const pending = root.querySelector('[data-uae-return-panel="pending"]');
      if (pending) {
        pending.querySelector('p').textContent = 'Статус оплаты не определён.';
      }
      return;
    }

    const maxAttempts = 8;

    function loadStatus(attempt) {
      return request(`/payments/${encodeURIComponent(paymentId)}/status`).then((status) => {
        if (!status.paid && (status.status === 'pending' || !status.status) && attempt < maxAttempts) {
          return new Promise((resolve) => setTimeout(resolve, 2000)).then(() => loadStatus(attempt + 1));
        }
        return status;
      });
    }

    loadStatus(1)
      .then((status) => {
        applyReturnStatus(root, status);
      })
      .catch((e) => {
        showReturnPanel(root, 'pending');
        setError(root, e.message);
      });
  }

  function initWidgets() {
    document.querySelectorAll('[data-uae-widget]').forEach((root) => {
      const mode = root.dataset.uaeWidget;
      if (mode === 'registration') initRegistration(root);
      if (mode === 'payment') initPayment(root);
      if (mode === 'return') initReturn(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWidgets);
  } else {
    initWidgets();
  }
})();
