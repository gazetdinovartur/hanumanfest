# Deploy — Timeweb (Hanuman Fest)

## Target

- Domain: `хануманфест.рф` (сайт + `/api` + `/admin`)
- PHP 8.5+, Composer 2, MySQL 8
- Document root → `public/`

## Env on server

Скопировать `.env.example` → `.env` на сервере (не коммитить). Минимум:

```dotenv
APP_ENV=prod
APP_SECRET=...
DATABASE_URL="mysql://..."
APP_URL="https://хануманфест.рф"
FRONTEND_URL="https://хануманфест.рф"
CORS_ALLOW_ORIGIN="https://хануманфест.рф"
DEFAULT_URI="https://хануманфест.рф"
APP_PRODUCT_SLUG=hanuman-fest
YOOKASSA_SHOP_ID=...
YOOKASSA_SECRET_KEY=...
GOOGLE_SHEETS_WEBHOOK_URL=...
MAILER_DSN=smtp://...
ADMIN_PASSWORD_HASH=...
SCHEDULE_SHEET_URL=...
```

YooKassa webhook: `POST https://хануманфест.рф/api/webhooks/yookassa`

## After git pull

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console app:seed:hanuman-fest   # при первом запуске / обновлении цен
```

## Smoke

```bash
curl -I https://хануманфест.рф/
curl -I https://хануманфест.рф/admin
curl -s https://хануманфест.рф/api/health
curl -s https://хануманфест.рф/api/products/hanuman-fest
```

## Notes

- Поддомен `апи.хануманфест.рф` больше не используется.
- Архив 2026 на `2026.хануманфест.рф` — отдельная фаза.
