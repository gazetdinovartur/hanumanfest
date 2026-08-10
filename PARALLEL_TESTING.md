# Параллельное тестирование (не трогая боевую регистрацию)

Цель: протестировать Symfony + bridge на **живом сайте**, не мешая текущему потоку доплат. На следующий год — переключить основные страницы.

---

## Принцип

| | Боевая система (сейчас) | Тестовый контур (новый) |
|---|------------------------|-------------------------|
| Форма | Forminator / старый поток | Secret-страницы WP + bridge |
| БД | WordPress + Google Sheet | Symfony MySQL + **отдельный** Google Sheet |
| YooKassa | Тот же магазин | Тот же магазин |
| Webhook в ЛК YooKassa | `.../wp-json/yk/v1/webhook` | **Не менять** — дублируем через forward |

---

## 1. Secret-страницы на WordPress

Создайте страницы **без ссылок в меню** (только вы знаете URL):

| URL (пример) | Shortcode |
|--------------|-----------|
| `/uae-test-reg/` | `[uae_registration]` |
| `/uae-test-pay/` | `[uae_payment]` |
| `/uae-test-return/` | `[uae_return]` |

В `wp-config.php` (уже должно быть для bridge):

```php
define('UAE_API_BASE', 'https://хануманфест.рф/api');
define('UAE_PRODUCT_SLUG', 'hanuman-fest');
```

Permalinks: сохранить ещё раз после создания страниц.

Rewrite `/pay/{token}` работает на slug `pay` — для теста можно:
- использовать страницу с slug `uae-test-pay` и править `returnUrl` в bridge (если нужно), **или**
- создать тестовую страницу `pay-test` и временно поменять rewrite в плагине.

**Проще для теста:** оставить slug `pay` только на secret-странице `uae-test-pay` — payment links из Symfony ведут на `{FRONTEND_URL}/pay/{token}`. Если боевой `/pay/` занят старым потоком, задайте на время теста:

```dotenv
FRONTEND_URL=https://хануманфест.рф/uae-test-pay
```

и поправьте rewrite в bridge под этот slug **или** создайте на WP страницу `pay` как draft/secret только для UAE (осторожно с конфликтом).

**Рекомендация:** secret-страница со slug **`pay-uae-test`**, в `.env` API:

```dotenv
FRONTEND_URL=https://хануманфест.рф
```

Ссылки второй оплаты: `https://хануманфест.рф/pay-uae-test/{token}` — потребует одной строки в rewrite bridge (можно добавить второе правило). Пока минимальный путь: secret `/pay/` без публикации в меню, если старый pay другой.

---

## 2. Отдельный Google Sheet

1. Создайте копию таблицы «Регистрации» → **«UAE Test 2026»**.
2. Скопируйте `legacy/google-apps-script/Code.by-columns.gs` в новый Apps Script проект.
3. Укажите `SHEET_ID` тестовой таблицы.
4. Deploy → Web app → скопируйте URL.
5. На **API-хостинге** в `.env.local`:

```dotenv
GOOGLE_SHEETS_WEBHOOK_URL=https://script.google.com/macros/s/XXXX/exec
```

Боевая таблица не затронется.

---

## 3. YooKassa: один webhook, два получателя

В личном кабинете YooKassa **один URL** — оставьте текущий WordPress:

```
https://хануманфест.рф/wp-json/yk/v1/webhook
```

**Не переключайте** на Symfony до следующего года.

### Дублирование на Symfony

В `wp-config.php` добавьте:

```php
define('UAE_SYMFONY_WEBHOOK_URL', 'https://хануманфест.рф/api/webhooks/yookassa');
```

Плагин `yookassa-plugin.php` при каждом webhook **асинхронно** шлёт копию на Symfony.

| Платёж | WordPress | Symfony |
|--------|-----------|---------|
| Старый (Forminator) | обрабатывает | игнорирует (нет в БД) |
| Новый (UAE test) | 404 «not found» — **нормально** | обрабатывает |

Symfony после оплаты тестовой заявки обновит статус, Sheets (тестовый), payment link.

---

## 4. Проверка end-to-end (чеклист)

1. [ ] API: `curl https://хануманфест.рф/api/products/hanuman-fest`
2. [ ] Secret-страница регистрации открывается, цена считается
3. [ ] Тестовая оплата минимальной суммы (или 50%)
4. [ ] Webhook: в Symfony `/admin` — платёж `SUCCEEDED`, заявка обновилась
5. [ ] Тестовый Google Sheet — новая/обновлённая строка
6. [ ] Ссылка второй оплаты (если 50%) — письмо или `/admin`
7. [ ] Боевая таблица и старые доплаты **не** сломались

---

## 5. Админка Symfony

- URL: `https://хануманфест.рф/admin/login`
- Логин: `admin`
- Пароль: из `ADMIN_PASSWORD` в `.env` (локально по умолчанию `TempAdmin!2026`)

---
