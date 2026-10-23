#!/usr/bin/env bash
# Vylaris Links – Server-Setup in einem Schritt.
#   ./setup.sh                 interaktiv
#   ./setup.sh --check         nur prüfen
#   weitere Optionen (--url=, --db-name=, --admin-email= ...) gehen an "php artisan links:install"
set -euo pipefail
cd "$(dirname "$0")"

say() { printf '\033[1m==> %s\033[0m\n' "$*"; }
die() { printf '\033[31mFehler: %s\033[0m\n' "$*" >&2; exit 1; }

command -v php >/dev/null || die "PHP ist nicht installiert (PHP 8.3 oder neuer nötig)."
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || die "PHP $(php -r 'echo PHP_VERSION;') ist zu alt – PHP 8.3 oder neuer nötig."

# 1) PHP-Abhängigkeiten (fehlen nur bei einer Installation aus Git; das Release-Paket bringt vendor/ mit)
if [ ! -f vendor/autoload.php ]; then
  command -v composer >/dev/null || die "vendor/ fehlt und Composer ist nicht installiert. Nimm das source-code.zip aus dem neuesten Release (enthält alles) oder installiere Composer."
  say "Composer-Pakete installieren"
  composer install --no-dev --optimize-autoloader --no-interaction
fi

# 2) Assets (fehlen nur bei einer Installation aus Git)
if [ ! -f public/build/manifest.json ]; then
  command -v npm >/dev/null || die "public/build fehlt und Node/npm ist nicht installiert. Nimm das source-code.zip aus dem neuesten Release (enthält die fertigen Assets)."
  say "Assets bauen"
  npm ci --no-audit --no-fund
  npm run build
fi

# 3) Verzeichnisrechte
say "Verzeichnisse vorbereiten"
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || echo "  (Rechte konnten nicht gesetzt werden – ggf. mit dem Besitzer des Webservers nachholen)"

# 4) Rest: .env, Schlüssel, Datenbank, Migrationen, Admin, Caches
say "Einrichtung starten"
php artisan links:install "$@"
