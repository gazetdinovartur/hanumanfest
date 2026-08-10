(function () {
  'use strict';

  const CREATE_PAYMENT_URL = ykData.restUrl + 'create';
  const FORM_SELECTOR = 'form.forminator-custom-form';
  const REGISTRATION_SUCCESS_PARAM = 'registration_success';

  const form = document.querySelector(FORM_SELECTOR);
  if (!form) return;

  window.addEventListener('DOMContentLoaded', handleReturnFromRegistration);

  function getParam(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  /* =========================
     LOADER
  ========================= */

  const style = document.createElement('style');
  style.textContent = `
    .modulbank-loader { display:none; position:fixed; inset:0; align-items:center; justify-content:center; z-index:9999; }
    .modulbank-loader .loader-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.4); }
    .modulbank-loader .loader-content { position:relative; background:#fff; padding:20px 28px; border-radius:10px; text-align:center; box-shadow:0 6px 30px rgba(0,0,0,.2); z-index:10000; }
    .modulbank-loader .spinner { width:36px; height:36px; border:4px solid #ddd; border-top-color:#1976d2; border-radius:50%; margin:0 auto 8px; animation:spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  `;
  document.head.appendChild(style);

  function showLoader(message = 'Проверяем оплату...') {
    let loader = document.querySelector('.modulbank-loader');
    if (!loader) {
      loader = document.createElement('div');
      loader.className = 'modulbank-loader';
      loader.innerHTML = `
        <div class="loader-backdrop"></div>
        <div class="loader-content">
          <div class="spinner"></div>
          <p class="loader-message">${message}</p>
        </div>`;
      document.body.appendChild(loader);
    }
    loader.querySelector('.loader-message').textContent = message;
    loader.style.display = 'flex';
  }

  function hideLoader() {
    const loader = document.querySelector('.modulbank-loader');
    if (loader) loader.style.display = 'none';
  }

// ---------- 3. Ловим успешную регистрацию ----------
async function handleReturnFromRegistration() {
  const success = getParam(REGISTRATION_SUCCESS_PARAM);
  const email = getParam('email');
  const phone = normalizePhone(getParam('phone'));

  if (success !== '1' || !email || !phone) return;

  const amount = parseFloat(getParam('amount').replace(/[^\d.]/g, '')).toFixed(2);
  const manual = parseFloat(getParam('manual').replace(/[^\d.]/g, '')).toFixed(2);
  
  saveRegistrationData(email, phone, amount,manual);
  showLoader('Создаем страницу оплаты...');
  await createAndSubmitPayment(email, phone, amount, manual);
}

  /* =========================
     CREATE PAYMENT
  ========================= */

  async function createAndSubmitPayment(email, phone, amount, manual) {
    const payload = { amount, manual, email, phone };

    try {
      const resp = await fetch(CREATE_PAYMENT_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': ykData.nonce
        },
        body: JSON.stringify(payload)
      });

      if (!resp.ok) throw new Error('Create payment failed');

      const json = await resp.json();

      if (!json.gateway_url) {
        throw new Error('Invalid payment response');
      }

      window.location.href = json.gateway_url;

    } catch (err) {
      hideLoader();
      alert('Не удалось инициировать оплату. Попробуйте позже.');
    }
  }

  /* =========================
     HELPERS
  ========================= */

  function normalizePhone(phone) {
    if (!phone) return '';
    let digits = phone.replace(/\D/g, '');
    if (digits.startsWith('8')) digits = '7' + digits.slice(1);
    if (digits.length === 10 && digits.startsWith('9')) digits = '7' + digits;
    if (digits.length !== 11) return '';
    return '+' + digits;
  }

  function generateRegistrationId(email, phone) {
    return `${email}-${phone}-${Date.now()}`;
  }

  function saveRegistrationData(email, phone, amount) {
    const registrationData = {
      id: generateRegistrationId(email, phone),
      email,
      phone,
      amount
    };

    try {
      localStorage.setItem('registration', JSON.stringify(registrationData));
    } catch {}

    return registrationData.id;
  }

})();