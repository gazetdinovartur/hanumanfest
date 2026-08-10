# Legacy — не runtime приложения

Полный бэкап WP (архив + SQL): `backup-wordpress_*.tar.zip` — **не коммитить**.

| Путь | Содержание |
|------|------------|
| `wordpress-theme/hanumanfest/` | Тема сайта 2026 (эталон дизайна для Symfony) |
| `wordpress/` | UAE bridge-плагин (исторический) |
| `wordpress-scripts/` | Старые check/create-payment, google-proxy |
| `google-apps-script/` | Экспорт заявок/оплат в Sheets |
| `*.csv`, forminator exports | Импорт legacy-заявок |
| `google-proxy.php` | Старый proxy (дубль в wordpress-scripts) |

Развёртывание архива на `2026.хануманфест.рф` — фаза 3 (отложена).
