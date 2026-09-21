# Deploy — Sweb (Hanuman Fest)

## Target

- Domain: `хануманфест.рф` (сайт + `/api` + `/admin`)
- PHP **8.5+** с расширениями **GD** и **WebP Support** (панель Sweb → PHP → `phpinfo`: `gd`, `WebP Support`)
- Composer 2, MySQL 8
- Document root → `public/` (если весь репозиторий в `public_html` без `public/` — см. автодетект в `bin/deploy-prod.sh`)

Без GD/WebP загрузка фото в админке не сможет строить оптимизированные варианты — проверьте расширения до первого деплоя контента.

## Uploads: что заливать

**Не загружайте на хостинг** каталог `public/uploads/wp/` (сырые WP-оригиналы).

На прод только рабочие пути:

- `uploads/gallery/`
- `uploads/reviews/` (+ `media/`, `video/`)
- `uploads/people/`
- `uploads/hero/`, `uploads/info/`, `uploads/site/`, `uploads/pages/`
- рядом с файлами — WebP-варианты (`*-thumb.webp`, `*-card.webp`, `*.webp`)

Локально после импорта WP:

```bash
php bin/console app:images:optimize --relocate-wp
```

Затем на Sweb синхронизируйте только новые пути (не `uploads/wp/`).

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
php bin/console app:seed:hanuman-fest --env=prod
php bin/console app:seed:site-content --env=prod
php bin/console app:seed:site-pages --env=prod
php bin/console assets:install public --env=prod
```

## Cron (Sweb panel)

```cron
0 */2 * * * cd /path/to/app && php bin/console app:import:schedule --env=prod --no-interaction
15 3 * * * cd /path/to/app && php bin/console app:applications:recalculate-statuses --product-slug=hanuman-fest --env=prod --no-interaction
0 9 * * * cd /path/to/app && php bin/console app:payment-links:generate --env=prod --no-interaction
```

## Smoke

```bash
curl -I https://хануманфест.рф/
curl -I https://хануманфест.рф/отзывы
curl -I https://хануманфест.рф/галерея
curl -I https://хануманфест.рф/admin
curl -s https://хануманфест.рф/api/health
curl -s https://хануманфест.рф/api/product
```

## Notes

- Поддомен `апи.хануманфест.рф` больше не используется.
- Kitchen-видео: файлы в `data/site-pages/videos/`, затем `app:seed:site-pages` копирует в `public/uploads/pages/kitchen/`.
- Архив 2026 на `2026.хануманфест.рф` — отдельная фаза.
- Очистка WP-разметки в CMS: `php bin/console app:cms:clean-wp-markup`
