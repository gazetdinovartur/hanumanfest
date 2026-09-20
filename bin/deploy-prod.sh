#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$PWD"
COMPOSER="${ROOT}/bin/composer"

composer_version_ok() {
    "${COMPOSER}" --version 2>/dev/null | grep -qE 'Composer version 2\.([1-9]|[1-9][0-9])'
}

# Timeweb: entire repo lives in public_html; web root = project root (./bundles, ./index.php).
# Local/Docker: web root = public/ subdirectory.
if [[ -f "${ROOT}/index.php" ]] && grep -q "__DIR__.*'/vendor/autoload_runtime.php'" "${ROOT}/index.php" 2>/dev/null; then
    WEB_ROOT="."
else
    WEB_ROOT="public"
fi

if [[ ! -f "${ROOT}/composer2.phar" ]]; then
    echo "composer2.phar missing in ${ROOT}. Download Composer 2.1+:"
    echo "  cd ${ROOT}"
    echo "  php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\""
    echo "  php composer-setup.php --filename=composer2.phar --2"
    echo "  rm composer-setup.php"
    exit 1
fi

if ! composer_version_ok; then
    echo "Composer 2.1+ required (Symfony 8). Current:"
    "${COMPOSER}" --version 2>/dev/null || true
    echo "Update phar: php composer2.phar self-update --2"
    exit 1
fi

echo "Project root: ${ROOT}"
echo "Web root: ${WEB_ROOT}"
echo "Using: $("${COMPOSER}" --version 2>/dev/null | head -1)"
echo "Installing production dependencies..."
"${COMPOSER}" install --no-dev --optimize-autoloader --no-interaction

echo "Preparing writable directories..."
mkdir -p var/cache var/log var/sessions/prod "${WEB_ROOT}/bundles"
chmod -R 775 var || true

if [[ -f .env.local.php ]]; then
    echo "Removing stale .env.local.php (use .env.local instead)..."
    rm -f .env.local.php
fi

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

if [[ "${1:-}" == "--with-seeds" ]]; then
    echo "Running content seeds..."
    php bin/console app:seed:hanuman-fest --no-interaction --env=prod
    php bin/console app:seed:site-content --if-empty --no-interaction --env=prod
    php bin/console app:seed:site-pages --no-interaction --env=prod
fi

echo "Rebuilding prod cache..."
rm -rf var/cache/prod
php bin/console cache:clear --no-warmup --env=prod
php bin/console cache:warmup --env=prod

echo "Installing bundle assets..."
php bin/console assets:install "${WEB_ROOT}" --no-interaction --env=prod

echo "Done. Smoke test:"
php bin/console about --env=prod | grep -E "Environment|Log directory" || true
echo "Optional: bin/deploy-prod.sh --with-seeds  # pricing + CMS pages"
echo "Cron: see DEPLOY_TIMEWEB.md and CUTOVER.md"
