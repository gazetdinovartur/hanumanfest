# Проект Hanuman Fest

## Видение

Единый Symfony-проект: публичный сайт фестиваля, регистрация/оплата, админка, API и расписание.

WordPress 2026 уходит в архив на `2026.хануманфест.рф` (фаза 3 — позже). Бэкапы лежат в `legacy/`.

## Roadmap

| Фаза | Содержание | Статус |
|------|------------|--------|
| 0 | AGENTS.md, docs, правила агентов | в работе |
| 1 | Аудит FS, локализация legacy | в работе |
| 2 | Один `.env` + `.env.example` | в работе |
| 3 | WP → `2026.…`, закрыть регистрацию | отложена |
| 4 | Symfony/composer hygiene | в работе |
| 5 | Публичный сайт на Symfony + регистрация + schedule | в работе |
| 6 | Переключение доменов в prod | позже |

## Решения (ADR)

### ADR-001: Один Product

Принят slug `hanuman-fest`. Отдельный `hanuman-fest-2027` не создаём.

Ценовые «сезоны» = `PricingPeriod` внутри одного Product.

### ADR-002: API на основном домене

`https://хануманфест.рф/api/...` — без поддомена `апи.`.

### ADR-003: Секреты только локально

В git: `.env.example`. Рабочий `.env` — локально / на сервере, не в репозитории. `.env.test` — для PHPUnit.

### ADR-004: Дизайн

База — тема WP 2026 (`legacy/wordpress-theme/hanumanfest`). Доработки UX — по согласованию.

### ADR-005: Расписание

Импорт из Google Spreadsheet (`SCHEDULE_SHEET_URL` + `app:import:schedule`), показ на сайте через Twig/JS.

### ADR-006: doctrine/orm 3.6.7

`doctrine/orm` 3.6.8 + DBAL 4.4 ломает `SchemaTool` в тестах (`setSchema` требует DBAL ^4.5, ещё не stable). Пока пиним `3.6.7`.
