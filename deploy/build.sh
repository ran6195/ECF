#!/usr/bin/env bash
#
# Costruisce il pacchetto di deploy pronto da caricare via FTP/SFTP nella
# sottocartella di collaudo (edysma.net/testECF).
#
# Output: deploy/build/testECF/   → il contenuto va caricato dentro /testECF/
#         deploy/build/database.sql → da importare via phpMyAdmin (una tantum)
#
# Uso:  bash deploy/build.sh
#
set -euo pipefail

# --- Configurazione (modifica qui se cambia la sottocartella) ---
BASE_PATH="testECF"                              # sottocartella su edysma.net
# ----------------------------------------------------------------

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"
ADMIN="$ROOT/admin"
DEPLOY="$ROOT/deploy"
OUT="$DEPLOY/build/$BASE_PATH"

echo "▶ ECF · build pacchetto di deploy per /$BASE_PATH"

# --- 0. Prerequisiti ---
if [ ! -f "$BACKEND/.env.production" ]; then
  echo "✗ Manca backend/.env.production."
  echo "  Copia backend/.env.production.example in backend/.env.production e compila i dati del DB di collaudo."
  exit 1
fi

# --- 1. Backend: dipendenze di produzione (senza dev) ---
echo "▶ composer install (--no-dev)"
( cd "$BACKEND" && composer install --no-dev --optimize-autoloader --no-interaction )

# --- 2. Admin: build di produzione (base path = /$BASE_PATH/) ---
echo "▶ npm build admin (base=/$BASE_PATH/)"
( cd "$ADMIN" && npm install --silent && npm run build -- --base="/$BASE_PATH/" )

# --- 3. Assemblaggio pacchetto ---
echo "▶ assemblaggio in $OUT"
rm -rf "$OUT"
mkdir -p "$OUT/app"

# Admin (index.html + assets/) nella root della sottocartella
cp -R "$ADMIN/dist/." "$OUT/"

# Loader embed servito dalla stessa origine
cp "$BACKEND/public/embed.js" "$OUT/embed.js"

# Front controller di produzione + .htaccess di root (con base path sostituito)
cp "$DEPLOY/templates/index.php" "$OUT/index.php"
sed "s|__BASE_PATH__|/$BASE_PATH|g" "$DEPLOY/templates/htaccess-root" > "$OUT/.htaccess"

# Codice backend (non servito direttamente) in app/
cp -R "$BACKEND/src" "$OUT/app/src"
cp -R "$BACKEND/vendor" "$OUT/app/vendor"
cp -R "$BACKEND/database" "$OUT/app/database"
cp "$BACKEND/.env.production" "$OUT/app/.env"
cp "$DEPLOY/templates/htaccess-app" "$OUT/app/.htaccess"

# --- 4. Script SQL per il DB di collaudo ---
echo "▶ generazione database.sql"
php "$DEPLOY/export-sql.php" > "$DEPLOY/build/database.sql"

echo ""
echo "✓ Pacchetto pronto:"
echo "  • $OUT/            → carica TUTTO il contenuto dentro la sottocartella /$BASE_PATH/ sul server"
echo "  • $DEPLOY/build/database.sql → importa una volta via phpMyAdmin nel DB di collaudo"
echo ""
echo "  Nota: ricorda di reinstallare le dipendenze di sviluppo in locale con:"
echo "        ( cd backend && composer install )"
