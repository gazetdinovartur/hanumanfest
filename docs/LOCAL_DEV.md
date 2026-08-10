# Локальная разработка

## Требования

- Docker + Docker Compose
- PHP 8.5+ (опционально без Docker)
- Composer 2

## Env

Один рабочий файл: **`.env`** (не в git).

```bash
cp .env.example .env
# заполнить секреты и локальные URL
```

Для Docker обычно:

```dotenv
APP_ENV=dev
APP_SECRET=change-me-locally
DATABASE_URL="mysql://app:!ChangeMe!@database:3306/app?serverVersion=8.0&charset=utf8mb4"
APP_URL=http://localhost:8080
FRONTEND_URL=http://localhost:8080
CORS_ALLOW_ORIGIN=http://localhost:8080
DEFAULT_URI=http://localhost:8080
```

Секреты / интеграции:

```dotenv
YOOKASSA_SHOP_ID=
YOOKASSA_SECRET_KEY=
GOOGLE_SHEETS_WEBHOOK_URL=   # Apps Script …/macros/s/…/exec
SCHEDULE_SHEET_URL=          # CSV/export URL программы (отдельный Sheet)
ADMIN_PASSWORD=TempAdmin!2026
```

Не создавать `.env.local`, если нет особой причины — всё в `.env`.

`.env.test` подхватывается PHPUnit автоматически — не трогать секреты prod.

## Запуск

```bash
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed:hanuman-fest
docker compose exec php php bin/console app:seed:site-content
```

Опционально расписание и legacy-заявки:

```bash
docker compose exec php php bin/console app:import:schedule
docker compose exec php php bin/console app:import:legacy-orders --dry-run
```

- Сайт: http://localhost:8080  
- Админка: http://localhost:8080/admin — логин `admin` / пароль из `ADMIN_PASSWORD`
- API: http://localhost:8080/api/product  

## Админка (частые экраны)

| Раздел | Путь |
|--------|------|
| Периоды и цены | `/admin/pricing` |
| Специальные гости / Музыканты / Мастера | меню «Сайт» |
| Галерея | `/admin/gallery` |
| Заявки | CRUD «Заявки» |

Метрики на дашборде — из MySQL (не из Sheet). Ссылка на таблицу регистраций появляется, если задан `GOOGLE_SHEETS_WEBHOOK_URL`.

## Тесты

```bash
composer test
```
