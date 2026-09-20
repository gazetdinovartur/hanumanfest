# Deploy — Timeweb (Hanuman Fest)

## Target

- Domain: `хануманфест.рф` (сайт + `/api` + `/admin`)
- PHP 8.5+, Composer 2, MySQL 8
- Document root → `public/` (или корень репо на Timeweb — см. `bin/deploy-prod.sh`)

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
YOOKASSA_SHOP_ID=...
YOOKASSA_SECRET_KEY=...
REGISTRATION_SHEET_URL=...
MAILER_DSN=smtp://...
MAILER_FROM="noreply@hanumanfest.ru"
MAILER_FROM_NAME="Хануман Фест"
ADMIN_PASSWORD=...
SCHEDULE_SHEET_URL=...
```

YooKassa webhook: `POST https://хануманфест.рф/api/webhooks/yookassa`

Полный чеклист cutover: `CUTOVER.md`.

## After git pull

```bash
bin/deploy-prod.sh
```

**Обязательно после обновления с CMS-плитками и kitchen-видео:** миграция `Version20260811060000` добавляет колонки `kitchen_video_*`, таблицу `home_highlight` и HTML-поля в `site_settings`. Без неё — ошибка `Unknown column kitchen_video1`.

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

С seeds при первом запуске или обновлении контента:

```bash
bin/deploy-prod.sh --with-seeds
```

Или вручную:

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console app:seed:hanuman-fest --env=prod      # цены
php bin/console app:seed:site-content --env=prod     # главная CMS
php bin/console app:seed:site-pages --env=prod       # юр. + питание
php bin/console assets:install public --env=prod
```

## Cron (Timeweb panel)

```cron
0 */2 * * * cd /path/to/app && php bin/console app:import:schedule --env=prod --no-interaction
15 3 * * * cd /path/to/app && php bin/console app:applications:recalculate-statuses --product-slug=hanuman-fest --env=prod --no-interaction
0 9 * * * cd /path/to/app && php bin/console app:payment-links:generate --env=prod --no-interaction
```

## Smoke

```bash
curl -I https://хануманфест.рф/
curl -I https://хануманфест.рф/admin
curl -s https://хануманфест.рф/api/health
curl -s https://хануманфест.рф/api/product
curl -I https://хануманфест.рф/registration
```

## Notes

- Поддомен `апи.хануманфест.рф` больше не используется.
- Kitchen mp4: `public/uploads/wp/2026/03/IMG_*.mp4` (не в git, залить вручную).
- Архив 2026 на `2026.хануманфест.рф` — отдельная фаза.
