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

Источник правды — MySQL. Sheets — зеркало для людей.

## Поток регистрации

1. Форма на `/registration` (или якорь на главной).
2. `POST /api/calculate` → цена.
3. `POST /api/applications` → заявка (+ экспорт в Sheets).
4. `POST /api/payments` → YooKassa, редирект.
5. Webhook → статус, доплата при 50%, email.
6. `/return` → `GET /api/payments/{id}/status`.

## Архив 2026 (фаза 3)

Отдельный хост WP на `2026.хануманфест.рф`, регистрация закрыта, ссылка на основной сайт. Код архива не является runtime этого репозитория.

## Legacy

| Путь | Назначение |
|------|------------|
| `legacy/backup-wordpress_*.tar.zip` | Полный бэкап WP + SQL dump |
| `legacy/wordpress-theme/` | Тема 2026 — эталон дизайна |
| `legacy/wordpress/` | Bridge UAE (исторический) |
| `legacy/wordpress-scripts/` | Старые PHP оплаты/proxy |
| `legacy/google-apps-script/` | Экспорт в Sheets |
| `legacy/*.csv` | Импорт Forminator / Sheets |
