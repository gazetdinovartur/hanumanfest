# AGENTS.md — Hanuman Fest

Контекст для любого агента в этом репозитории. Читать перед правками.

## Продукт

Сайт и бэкенд фестиваля **Хануман Фест** на Symfony.

| Домен | Назначение | Статус |
|-------|------------|--------|
| `https://хануманфест.рф` | Основной сайт + `/api` + `/admin` | Цель (Symfony) |
| `https://2026.хануманфест.рф` | Архив WP «как было в 2026» | Фаза 3 (позже) |


## Правила работы

1. **Plan → Act → Observe.** Сначала план и согласование, потом код, потом проверка. Результат отдаёшь только со 100% работающей механикой
2. **Правки только по согласованию.** Не добавлять фичи, файлы и рефакторинг «от себя».
3. **Минимально необходимое**, с запасом на рост. Без избыточных абстракций.
4. **Тесты** на существующие и новые механики. После изменений — `composer test`.
5. **Один `.env`** для локальных/секретных значений (в `.gitignore`). В репо — только `.env.example` с заглушками. `.env.test` — исключение для PHPUnit.
6. Язык UI и пользовательских текстов — **русский**.

## Стек

- PHP 8.5+, Symfony 8, Doctrine ORM 3, EasyAdmin 5, Twig
- MySQL 8 (prod/dev), SQLite в тестах
- YooKassa, Google Sheets (Apps Script webhook), Mailer
- Публичный сайт: Twig + Bootstrap 5 + vanilla JS (дизайн на базе WP 2026)

## Доменный язык

- **Product** — один активный продукт фестиваля (`slug`: `hanuman-fest`). Не плодить `hanuman-fest-2027`.
- **PricingPeriod** — ценовые окна (ранняя цена и т.п.) внутри одного Product. Это и есть «сезоны» цен.
- **ParticipationOption / ParticipationPrice** — варианты участия и цены в периоде.
- **Application / Payment / PaymentLink** — заявка, платёж, ссылка на доплату.
- **ScheduleEvent** — программа; импорт из Google Spreadsheet (`app:import:schedule`).

Несколько Product имели бы смысл только при параллельных независимых событиях. Сейчас — один Product.

## Где что лежит

```
src/                  # Symfony (Entity, Service, Api, Admin, Command)
templates/            # Twig: сайт, admin, email
public/               # Web root + статичные ассеты сайта
legacy/               # Архивы WP, bridge, CSV, тема 2026 (не runtime)
docs/                 # Документация проекта
tests/                # PHPUnit
```

- Bridge WP и старые скрипты оплаты — только в `legacy/` (справка / архив).
- Полный бэкап WP: `legacy/backup-wordpress_*.tar.zip` (не коммитить).
- Тема 2026 (эталон дизайна): `legacy/wordpress-theme/hanumanfest/`.

## Локальная разработка

См. `docs/LOCAL_DEV.md`. Кратко:

```bash
cp .env.example .env   # заполнить секреты локально
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed:hanuman-fest
docker compose exec php php bin/console app:seed:site-content
docker compose exec php php bin/console app:seed:site-pages
composer test
```

## Документация

| Файл | Содержание |
|------|------------|
| `docs/PROJECT.md` | Видение, roadmap, ADR |
| `docs/ARCHITECTURE.md` | Схема, источники данных, админ |
| `docs/DOMAINS.md` | Домены и URL |
| `docs/LOCAL_DEV.md` | Локальный запуск и env |
| `docs/PRESENTATION.md` | Исторический обзор ценности (часть 2 — tech, обновлять осторожно) |
| `README.md` | Быстрый старт для людей |

## Не делать без запроса

- Коммиты, push, деплой, force/destructive git
- Фаза 3 (перенос WP на `2026.…`) — отложена
- Новые зависимости и пакеты без согласования
- Упоминание `апи.хануманфест.рф` в новых примерах — только `хануманфест.рф`

