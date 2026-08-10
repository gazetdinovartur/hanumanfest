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
APP_PRODUCT_SLUG=hanuman-fest
```

Не создавать `.env.local`, если нет особой причины — всё в `.env`.

`.env.test` подхватывается PHPUnit автоматически — не трогать секреты prod.

## Запуск

```bash
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed:hanuman-fest
```

Сайт: http://localhost:8080  
Админка: http://localhost:8080/admin (локально: `admin` / см. README)

## Расписание

```bash
docker compose exec php php bin/console app:import:schedule
```

Нужен `SCHEDULE_SHEET_URL` в `.env`.

## Тесты

```bash
composer test
```
