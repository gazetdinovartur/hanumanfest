# Архив сайта 2026 (статика)

Замороженный снимок публичного WordPress «как было в 2026». Без PHP, MySQL и плагинов.

Целевой хост: `https://2026.хануманфест.рф`  
Выкладка — вместе с cutover Symfony на `https://хануманфест.рф` (не раньше: ссылка «сайт 2027» должна вести на новый сайт, а не на тот же WP).

## Что внутри

| Путь | В git? |
|------|--------|
| `site/*.html`, `site/assets/`, `site/wp-content/themes/` | да |
| `site/schedule/` (HTML + CSV программы) | да |
| `site/robots.txt`, `site/sitemap.xml` | да |
| `urls.txt` — список URL на момент снимка | да |
| `site/wp-content/uploads/`, видео `*.mp4` | нет (`.gitignore`) |
| `_inventory/` | нет |

Форма Forminator заменена на CTA на основной домен. Jivo, оплата, `wp-json` из HTML убраны. Расписание — снимок Google-таблицы (раньше короткая ссылка `clck.su/peQdI`).

В архиве только:

- `/` — главная
- `/schedule/` — расписание
- `/питание-на-хануман-фест/`
- `/политика-конфиденциальности/`, `/политика-возвратов/`
- `/публичная-оферта/`

## Локальный просмотр

Нужны медиа: либо уже лежит `site/wp-content/uploads/` после сборки, либо скопируйте из `public/uploads/wp/`:

```bash
mkdir -p legacy/archive-2026/site/wp-content/uploads
rsync -a public/uploads/wp/ legacy/archive-2026/site/wp-content/uploads/
```

Затем из каталога `site/`:

```bash
cd legacy/archive-2026/site
python3 -m http.server 8765
```

Открыть http://127.0.0.1:8765/

## Переснять с живого WP

Пока `хануманфест.рф` ещё WordPress:

```bash
python3 legacy/archive-2026/tools/build_archive.py
```

Только вычистить HTML уже снятого дерева:

```bash
python3 legacy/archive-2026/tools/build_archive.py --sanitize-only
```

Скрипт ходит на punycode `xn--80aap0aec3aidne.xn--p1ai` (это и есть `хануманфест.рф`). После выключения WP повторный снимок невозможен — остаётся этот каталог и бэкап `legacy/backup-wordpress_*.tar.zip`.

## Выкладка на Sweb (cutover)

1. Поддомен `2026.хануманфест.рф` → отдельный document root со статикой `site/`.
2. Залить HTML/CSS/JS из git и `wp-content/uploads/` rsync’ом (не в репозиторий).
3. PHP не нужен. `DirectoryIndex index.html`.
4. Основной домен → Symfony. WP выключить.
5. Не делать 301 `/` и живых страниц Symfony на архив.

Подробнее: `CUTOVER.md`, `DEPLOY_SWEB.md`.
