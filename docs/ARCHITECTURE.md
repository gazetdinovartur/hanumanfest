# Архитектура

## Целевая схема

```
┌────────────────────────────────────────────────────────────┐
│  хануманфест.рф (Symfony)                                  │
│  Twig-сайт · /registration · /program · /return · /pay     │
│  /api/* · /admin · MySQL                                   │
└───────────────┬──────────────────┬─────────────┬───────────┘
                ▼                  ▼             ▼
          YooKassa           Google Sheets     SMTP
                             (Apps Script)
```

**После cutover:** источник правды — MySQL. Sheets — зеркало для людей.

**Сейчас (переходный период):** боевая регистрация может ещё идти через WordPress → Sheet;
Symfony-админка и метрики читают **только MySQL** (после `app:import:legacy-orders` или новых заявок через `/api`).

## Источники данных

| Область | Источник | Env / команда |
|---------|----------|---------------|
| Заявки / платежи | MySQL (`application`, `payment`) | импорт: `app:import:legacy-orders` |
| Зеркало регистраций | Google Sheet «Регистрации» | `GOOGLE_SHEETS_WEBHOOK_URL` (Apps Script) |
| Цены | MySQL periods × options | `/admin/pricing`, seed `app:seed:hanuman-fest` |
| Контент лендинга | MySQL CMS + `public/uploads` | `app:seed:site-content`, админка |
| Расписание | отдельный Spreadsheet → MySQL | `SCHEDULE_SHEET_URL`, `app:import:schedule` |

Два Google-документа: **регистрации** (`GOOGLE_SHEETS_WEBHOOK_URL`) и **программа** (`SCHEDULE_SHEET_URL`) — не путать.

## Поток регистрации (цель)

1. Форма на `/registration` (или якорь на главной).
2. `POST /api/calculate` → цена (`participationOptionId`).
3. `POST /api/applications` → заявка (+ экспорт в Sheets).
4. `POST /api/payments` → YooKassa, редирект.
5. Webhook → статус, доплата при 50%, email.
6. `/return` → `GET /api/payments/{id}/status`.

## Админка (ключевые экраны)

| Раздел | URL / CRUD |
|--------|------------|
| Дашборд + метрики MySQL | `/admin` |
| Периоды и цены (матрица) | `/admin/pricing` |
| Специальные гости / Музыканты / Мастера | отдельные Person CRUD |
| Галерея | `/admin/gallery` |
| Заявки / Платежи / Пользователи | EasyAdmin CRUD |

Метрики дашборда (MySQL): **без оплаты** (`NEW`), **оплачено** (`PAID`), **получено по заявкам** (`SUM(paid_amount)`), **регистраций** (все кроме `CANCELLED`). Ссылка на Sheet — подробности для команды, не источник метрик.

## Архив 2026 (фаза 3)

Отдельный хост WP на `2026.хануманфест.рф`, регистрация закрыта, ссылка на основной сайт. Код архива не является runtime этого репозитория.

## Legacy

| Путь | Назначение |
|------|------------|
| `legacy/backup-wordpress_*.tar.zip` | Полный бэкап WP + SQL dump |
| `legacy/wordpress-theme/` | Тема 2026 — эталон дизайна |
| `legacy/wordpress/` | Bridge UAE (исторический / параллельный тест) |
| `legacy/wordpress-scripts/` | Старые PHP оплаты/proxy |
| `legacy/google-apps-script/` | Экспорт в Sheets (`Code.by-columns.gs`) |
| `legacy/*.csv` | Импорт Forminator / Sheets |
