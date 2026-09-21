# Production cutover — Hanuman Fest

Чеклист переключения боевой регистрации с WordPress на Symfony.

## Перед cutover

- [ ] Symfony задеплоен на `https://хануманфест.рф`
- [ ] `composer test` зелёный локально
- [ ] Миграции применены: `php bin/console doctrine:migrations:migrate --no-interaction --env=prod`
- [ ] Seeds: `bin/deploy-prod.sh --with-seeds` или вручную (`app:seed:site-content --if-empty`) — см. `DEPLOY_SWEB.md`
- [ ] Kitchen mp4: `app:seed:site-pages` копирует в `public/uploads/pages/kitchen/` из `public/uploads/wp/2026/03/` или `data/site-pages/videos/` (4 файла: IMG_8652, IMG_8223, IMG_8224, IMG_8222)
- [ ] Prod `.env`: `YOOKASSA_*`, `MAILER_DSN`, сильный `ADMIN_PASSWORD`, `DEFAULT_URI`
- [ ] `SCHEDULE_SHEET_URL` для cron импорта программы
- [ ] Smoke: `/`, `/registration`, `/api/health`, `/api/product`, `/admin/login`
- [ ] JivoSite чат на сайте (виджет как на WP: `code.jivo.ru/widget/qNhdVN9jC1`)

## YooKassa

1. В ЛК YooKassa сменить webhook на:
   ```
   POST https://хануманфест.рф/api/webhooks/yookassa
   ```
2. Тестовый платёж → статус `SUCCEEDED` в `/admin` → Платежи
3. Legacy-платежи WP игнорируются webhook (ответ `{"ok":true}`)

Параллельный режим до cutover: см. `PARALLEL_TESTING.md` (forward с WP webhook).

## Регистрация

1. Отключить Forminator / старый поток на WP (или перевести страницу регистрации в draft)
2. Главная и `/registration` ведут на Symfony-форму
3. E2E: calculate → application → payment → return → status paid
4. Частичная оплата 50% → email с `/pay/{token}` (нужен `MAILER_DSN`)

## Google Sheets (опционально)

- `REGISTRATION_SHEET_URL` — зеркало заявок/оплат (таблица или Apps Script)
- Пустой URL = экспорт пропускается, MySQL — источник правды

## Legacy import

Если есть история в Sheet до cutover:

```bash
php bin/console app:import:legacy-orders --env=prod
php bin/console app:applications:recalculate-statuses --product-slug=hanuman-fest --env=prod
```

## Cron (Sweb)

Расписание cron — один раз в `DEPLOY_SWEB.md` (секция Cron).

## После cutover

- [ ] Метрики админки совпадают с ожиданиями
- [ ] Футер, юр. страницы, питание — из CMS
- [ ] Фаза 3 (`2026.хануманфест.рф`) — отдельно, позже
