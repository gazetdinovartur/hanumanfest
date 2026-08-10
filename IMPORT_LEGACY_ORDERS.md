# Import legacy orders and payment links

## 1) Seed current festival data

```bash
php bin/console app:seed:hanuman-fest
```

## 2) Import live applications/payments from CSV

По умолчанию `--sheet-source` берётся из `GOOGLE_SHEETS_WEBHOOK_URL`: если это Apps Script webhook, команда скачивает CSV листа «Регистрации» той же таблицы, что в `legacy/google-apps-script/Code.by-columns.gs`.

Dry run:

```bash
php bin/console app:import:legacy-orders \
  --forminator-source="/absolute/path/forminator-форма-регистрации-260610234408.csv" \
  --product-slug=hanuman-fest \
  --dry-run
```

С явным CSV (или без forminator, только sheet из env):

```bash
php bin/console app:import:legacy-orders \
  --sheet-source="/absolute/path/Лист регистраций - Регистрации.csv" \
  --product-slug=hanuman-fest \
  --dry-run
```

Real import:

```bash
php bin/console app:import:legacy-orders \
  --forminator-source="/absolute/path/forminator-форма-регистрации-260610234408.csv" \
  --product-slug=hanuman-fest
```

You can also import one source only:

```bash
php bin/console app:import:legacy-orders --source="/absolute/path/legacy-orders.csv"
```

Notes:
- The importer auto-merges duplicate rows from both sources by UUID (if exists) or by a stable fingerprint.
- It supports historical option aliases like `Только воскресение`, `Только суббота`, `С пятницы на субботу`, `С субботы на воскресение`.
- Rows with `Вариант участия = Полный` are intentionally skipped (ambiguous legacy meaning for 2026 model) and should be reviewed manually.

## 3) Recalculate paid amounts and statuses

If legacy data was imported in several passes, run status reconciliation:

```bash
php bin/console app:applications:recalculate-statuses --product-slug=hanuman-fest --dry-run
php bin/console app:applications:recalculate-statuses --product-slug=hanuman-fest
```

## 4) Generate links for second payment (50% cases)

Dry run:

```bash
php bin/console app:payment-links:generate --product-slug=hanuman-fest --dry-run
```

Create links:

```bash
php bin/console app:payment-links:generate --product-slug=hanuman-fest
```

Create links + send email to all generated:

```bash
php bin/console app:payment-links:generate --product-slug=hanuman-fest --send-email
```

