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
          throw new Error(data.error || 'Request failed');
        }

        return data;
      });
  }

  function request(path, options) {
    return fetch(apiBase + path, {
      headers: { 'Content-Type': 'application/json' },
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

  function initRegistration(root) {
    const form = root.querySelector('[data-uae-form]');
        let latestPricing = null;

    const fields = {
      name: form.querySelector('[name="name"]'),
      email: form.querySelector('[name="email"]'),
      phone: form.querySelector('[name="phone"]'),
      participationOptionCode: form.querySelector('[name="participationOptionCode"]'),
      adultsCount: form.querySelector('[name="adultsCount"]'),
      childrenCount: form.querySelector('[name="childrenCount"]'),
      transferIncluded: form.querySelector('[name="transferIncluded"]'),
      paymentFactor: form.querySelector('[name="paymentFactor"]'),
    };

    const ui = {
      activePeriod: root.querySelector('[data-uae-active-period]'),
      total: root.querySelector('[data-uae-total]'),
      now: root.querySelector('[data-uae-now]'),
      meta: root.querySelector('[data-uae-meta]'),
      submit: root.querySelector('[data-uae-submit]'),
    };

    function getPayloadBase() {
      return {
        participationOptionCode: fields.participationOptionCode.value,
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
      if (!fields.participationOptionCode.value) return Promise.resolve();
      setError(root, '');
      setStatus(root, 'Пересчёт...');
      return request('/calculate', {
        method: 'POST',
        body: JSON.stringify(getPayloadBase()),
      })
        .then((pricing) => {
          updatePricingView(pricing);
          setStatus(root, '');
        })
        .catch((e) => {
          setStatus(root, '');
          setError(root, e.message);
        });
    }

    request('/product')
      .then((product) => {
        if (ui.activePeriod && product.activePricingPeriod) {
          ui.activePeriod.textContent = `Период: ${product.activePricingPeriod.name}`;
        }

        fields.participationOptionCode.innerHTML = '';
        product.participationOptions.forEach((item) => {
          const option = document.createElement('option');
          option.value = item.code;
          option.textContent = item.price ? `${item.name} — ${item.price} ₽` : item.name;
          fields.participationOptionCode.appendChild(option);
        });

        return recalculate();
      })
      .catch((e) => setError(root, e.message));

    ['change', 'input'].forEach((eventName) => {
      fields.participationOptionCode.addEventListener(eventName, recalculate);
      fields.adultsCount.addEventListener(eventName, recalculate);
      fields.childrenCount.addEventListener(eventName, recalculate);
      fields.transferIncluded.addEventListener(eventName, recalculate);
      fields.paymentFactor.addEventListener(eventName, recalculate);
    });

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

      const payload = {
        ...getPayloadBase(),
        name: fields.name.value.trim(),
        email: fields.email.value.trim(),
        phone,
      };

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
          window.location.href = payment.gateway_url;
        })
        .catch((err) => {
          ui.submit.disabled = false;
          setStatus(root, '');
          setError(root, err.message);
        });
    });
  }

  function initPayment(root) {
    const info = root.querySelector('[data-uae-payment-info]');
    const button = root.querySelector('[data-uae-pay-btn]');
    const token = root.dataset.token || detectTokenFromPath() || getQueryParam('token');

    if (!token) {
      setError(root, 'Не найден токен ссылки оплаты.');
      button.disabled = true;
      return;
    }

    setStatus(root, 'Загрузка заявки...');
    request(`/payment-links/${encodeURIComponent(token)}`)
      .then((data) => {
        info.innerHTML =
          `<div>Заявка: <strong>${data.application.uuid}</strong></div>` +
          `<div>Оплачено: <strong>${formatMoney(data.application.paidAmount)}</strong></div>` +
          `<div>Осталось: <strong>${formatMoney(data.application.remainingAmount)}</strong></div>`;
        setStatus(root, '');
      })
      .catch((e) => {
        setStatus(root, '');
        setError(root, e.message);
        button.disabled = true;
      });

    button.addEventListener('click', () => {
      setError(root, '');
      setStatus(root, 'Перенаправление на оплату...');
      button.disabled = true;

      request(`/payment-links/${encodeURIComponent(token)}/pay`, {
        method: 'POST',
      })
        .then((payment) => {
          window.location.href = payment.gateway_url;
        })
        .catch((e) => {
          button.disabled = false;
          setStatus(root, '');
          setError(root, e.message);
        });
    });
  }

  function initReturn(root) {
    const info = root.querySelector('[data-uae-return-info]');
    const paymentId = getQueryParam('payment_id');

    if (!paymentId) {
      setError(root, 'Не найден payment_id в адресе возврата.');
      info.textContent = 'Статус оплаты не определён.';
      return;
    }

    request(`/payments/${encodeURIComponent(paymentId)}/status`)
      .then((status) => {
        if (status.paid) {
          info.innerHTML =
            `<div>Оплата прошла успешно.</div>` +
            `<div>Сумма: <strong>${formatMoney(status.amount)}</strong></div>`;
          return;
        }

        info.innerHTML = `<div>Статус платежа: <strong>${status.status || 'unknown'}</strong></div>`;
      })
      .catch((e) => {
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

