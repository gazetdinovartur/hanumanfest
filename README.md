# Hanuman Fest

Сайт фестиваля, регистрация и оплата на **Symfony**. Один домен: сайт + `/api` + `/admin`.

Подробный контекст для агентов: [`AGENTS.md`](AGENTS.md) · [`docs/`](docs/).

---

## Архитектура

```
хануманфест.рф (Symfony Twig + API + EasyAdmin + MySQL)
        │
        ├── YooKassa
        ├── Google Sheets API
        └── SMTP
```

Архив WP 2026 → `2026.хануманфест.рф` (фаза 3, позже). Бэкапы и тема — в `legacy/`.

---

## Быстрый старт (локально)

```bash
cp .env.example .env   # секреты только в .env, не в git
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed:hanuman-fest
docker compose exec php php bin/console app:seed:site-content
```

- Сайт: http://localhost:8080  
- Админка: http://localhost:8080/admin  
- Цены: http://localhost:8080/admin/pricing  
- API: http://localhost:8080/api/product  

Локальные URL в `.env`:

```dotenv
APP_URL=http://localhost:8080
FRONTEND_URL=http://localhost:8080
CORS_ALLOW_ORIGIN=http://localhost:8080
DEFAULT_URI=http://localhost:8080
REGISTRATION_SHEET_URL=      # лист регистраций
SCHEDULE_SHEET_URL=          # программа (отдельный Sheet)
```

---

## Публичные маршруты

| URL | Назначение |
|-----|------------|
| `/` | Главная |
| `/program` | Полное расписание (из БД / spreadsheet import) |
| `/registration` | Регистрация и оплата |
| `/return` | Возврат после YooKassa |
| `/pay/{token}` | Доплата по ссылке |
| `/api/*` | JSON API |
| `/admin` | EasyAdmin |

---

## Расписание

```bash
php bin/console app:import:schedule
```

`SCHEDULE_SHEET_URL` в `.env`.

---

## Данные и импорт

| Команда | Назначение |
|---------|------------|
| `app:seed:hanuman-fest` | продукт, периоды, цены |
| `app:seed:site-content` | контент главной из WP dump |
| `app:seed:site-pages` | CMS-страницы (юр. + питание) |
| `app:import:schedule` | программа из `SCHEDULE_SHEET_URL` |
| `app:import:legacy-orders` | заявки/платежи из Sheet (default URL из webhook) |

Метрики в `/admin` считаются из **MySQL**, не из Google Sheet. Подробности: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Тесты

```bash
composer test
```

---

## Деплой

[`DEPLOY_SWEB.md`](DEPLOY_SWEB.md) — обновить webhook YooKassa на `https://хануманфест.рф/api/webhooks/yookassa`.

---

## Стек

PHP 8.5+, Symfony 8, Doctrine ORM 3, EasyAdmin 5, Twig, MySQL 8, YooKassa.
