# Домены

| URL | Роль |
|-----|------|
| `https://хануманфест.рф` | Сайт, API (`/api`), админка (`/admin`) |
| `https://хануманфест.рф/program` | Полное расписание |
| `https://хануманфест.рф/registration` | Регистрация |
| `https://хануманфест.рф/return` | Возврат после YooKassa |
| `https://хануманфест.рф/pay/{token}` | Доплата по ссылке |
| `https://2026.хануманфест.рф` | Архив сайта 2026 (статика, не WP) |

## Env

```dotenv
APP_URL="https://хануманфест.рф"
FRONTEND_URL="https://хануманфест.рф"
CORS_ALLOW_ORIGIN="https://хануманфест.рф"
DEFAULT_URI="https://хануманфест.рф"
```

Локально: `http://localhost:8080` для всех трёх URL.

Webhook YooKassa: `POST https://хануманфест.рф/api/webhooks/yookassa`

**Не использовать** `апи.хануманфест.рф` в новых конфигах и доках.
