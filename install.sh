#!/usr/bin/env bash
# Creates a fresh Laravel app and layers this folder's files on top of it.
# Usage (from inside laravel-api/):   ./install.sh [target-folder]      default: ../api-server
# Requires: PHP 8.2+, Composer.   On Windows: use Git Bash/WSL, or follow the manual steps in README.md.
set -euo pipefail

SRC="$(cd "$(dirname "$0")" && pwd)"
TARGET="${1:-$SRC/../api-server}"

if [ ! -d "$TARGET" ]; then
  composer create-project laravel/laravel "$TARGET" --no-interaction
fi
cd "$TARGET"

php artisan install:api --no-interaction            # installs Laravel Sanctum + routes/api.php

for d in app bootstrap config database routes; do cp -R "$SRC/$d" ./; done

touch .env
add_env() { grep -q "^$1=" .env || echo "$1=$2" >> .env; }
add_env ADMIN_EMAIL "admin@example.com"
GENERATED=""
if ! grep -q "^ADMIN_PASSWORD=" .env; then
  GENERATED="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"
  echo "ADMIN_PASSWORD=$GENERATED" >> .env
fi
add_env CORS_ALLOWED_ORIGINS "http://localhost:5173"
GATE_SECRET=""
if ! grep -q "^ADMIN_LOGIN_SECRET=" .env; then
  GATE_SECRET="$(openssl rand -hex 24)"
  echo "ADMIN_LOGIN_SECRET=$GATE_SECRET" >> .env
fi
add_env REPORT_UPLOAD_MAX_MB "100"

php artisan migrate --seed --force

echo
echo "Done. Start the API:   cd $TARGET && php artisan serve      (http://localhost:8000)"
echo "Admin login:           $(grep '^ADMIN_EMAIL=' .env | cut -d= -f2)"
[ -n "$GENERATED" ] && echo "Admin password:        $GENERATED   (also stored in .env — change it!)"
[ -n "$GATE_SECRET" ] && echo "Secret login link:     http://localhost:5173/0xGlitch?secret=$GATE_SECRET"
echo "Frontend .env:         VITE_API_PROXY=http://localhost:8000   VITE_API_FALLBACK=false"
