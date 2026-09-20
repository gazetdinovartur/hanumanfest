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
          error.totalAmount = data.totalAmount;
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
  }

  function setStatus(root, message) {
    const status = root.querySelector('[data-uae-status]');
    if (status) status.textContent = message || '';
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
      factorHint: root.querySelector('[data-uae-factor-hint]'),
      existing: root.querySelector('[data-uae-existing-payment]'),
      existingText: root.querySelector('[data-uae-existing-text]'),
      existingPay: root.querySelector('[data-uae-existing-pay]'),
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
        conditional.tentRoommate.classList.toggle('d-none', !showTent);
        if (!showTent && fields.tentRoommate) {
          fields.tentRoommate.value = '';
        }
      }
      if (conditional.houseBooking) {
        conditional.houseBooking.classList.toggle('d-none', !showHouse);
      }
    }

    function updateFactorHint() {
      if (!ui.factorHint) return;
      ui.factorHint.classList.toggle('d-none', Number(fields.paymentFactor.value || 1) !== 0.5);
    }

    function hideExistingPayment() {
      if (!ui.existing) return;
      ui.existing.classList.add('d-none');
    }

    function showExistingPayment(data) {
      if (!ui.existing || !data.payUrl) return;
      const remaining = formatMoney(data.remainingAmount);
      if (ui.existingText) {
        ui.existingText.textContent =
          'По этому email уже внесена предоплата. Осталось оплатить ' +
          remaining +
          '. Ссылка также в письме — если его нет, проверьте папку «Спам».';
      }
      if (ui.existingPay) {
        ui.existingPay.href = data.payUrl;
      }
      fillCopyField(ui.existing, data.payUrl);
      ui.existing.classList.remove('d-none');
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
    fields.transferIncluded.addEventListener('change', recalculate);
    fields.adultsCount.addEventListener('input', recalculate);
    fields.childrenCount.addEventListener('input', recalculate);
    fields.email.addEventListener('input', () => {
      hideExistingPayment();
      scheduleEmailLookup();
    });
    fields.email.addEventListener('blur', lookupExistingPayment);

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      setError(root, '');
      setStatus(root, 'Создаём заявку...');
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
          setStatus(root, 'Создаём оплату...');
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
          if (err.payUrl) {
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
      setStatus(root, 'Перенаправление на оплату...');
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
      const paid = root.querySelector('[data-uae-return-paid]');
      const remaining = root.querySelector('[data-uae-return-remaining]');
      const total = root.querySelector('[data-uae-return-total]');
      const email = root.querySelector('[data-uae-return-email]');
      if (paid) paid.textContent = formatMoney(status.paidAmount);
      if (remaining) remaining.textContent = formatMoney(status.remainingAmount);
      if (total) total.textContent = formatMoney(status.totalAmount);
      if (email) email.textContent = status.email || 'ваш email';
      if (status.payUrl) {
        fillCopyField(root, status.payUrl);
      }
      return;
    }

    if (status.paid) {
      showReturnPanel(root, 'full');
      const amount = root.querySelector('[data-uae-return-amount]');
      if (amount) amount.textContent = formatMoney(status.amount);
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
