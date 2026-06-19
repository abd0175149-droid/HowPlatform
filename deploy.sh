#!/usr/bin/env bash
# ============================================================
# HowPlatform deploy/update script (run on the server).
#   ./deploy.sh           -> pull, build, up, post-deploy tasks
#   ./deploy.sh --no-pull  -> skip git pull (use current tree)
# Prereqs (one-time, NOT done here):
#   - ./.env present (MYSQL_ROOT_PASSWORD)
#   - each <app>/public_html/.env present (real secrets)
#   - ./secure-keys/ holds Firebase + Vertex JSON
#   - DB users created + dumps imported (see README/import step)
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

LARAVEL_APPS="auth www library tools"

if [ "${1:-}" != "--no-pull" ]; then
  echo ">> git pull"
  git pull --ff-only || echo "WARN: git pull skipped/failed; continuing with current tree"
fi

echo ">> docker compose build"
docker compose build

echo ">> docker compose up -d"
docker compose up -d

echo ">> waiting for db health"
until docker compose exec -T db mysqladmin ping -h 127.0.0.1 -uroot -p"${MYSQL_ROOT_PASSWORD:-}" --silent 2>/dev/null; do
  sleep 3; echo "   ...db not ready yet"
done

for svc in $LARAVEL_APPS; do
  echo ">> [$svc] artisan post-deploy"
  docker compose exec -T "$svc" sh -lc '
    php artisan migrate --force || true
    php artisan storage:link || true
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    php artisan optimize:clear || true
  ' || echo "WARN: $svc post-deploy had issues"
done

echo ">> [diploma] spark migrate"
docker compose exec -T diploma sh -lc 'php spark migrate || true' || echo "WARN: diploma migrate had issues"

echo ">> done. Containers:"
docker compose ps
