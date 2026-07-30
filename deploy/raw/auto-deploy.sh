#!/bin/bash
# Cron poller: checks origin/main every minute, and if it moved, pulls,
# rebuilds caches, fixes ownership, and restarts PHP-FPM plus the two
# long-running workers (bus-relay, queue-work — these hold old code in
# memory until restarted, unlike PHP-FPM which reloads per-request).
# Only touches composer when composer.lock actually changed.
#
# Install: crontab entry `* * * * * /var/www/central-service/deploy/raw/auto-deploy.sh`
set -euo pipefail

REPO=/var/www/central-service
LOG=/var/log/auto-deploy.log
FPM_SERVICE=php8.4-fpm

cd "$REPO"

LOCAL=$(git rev-parse HEAD)
git fetch origin main -q
REMOTE=$(git rev-parse origin/main)

if [ "$LOCAL" = "$REMOTE" ]; then
    exit 0
fi

echo "$(date -u +%FT%TZ) deploying $LOCAL -> $REMOTE" >> "$LOG"

COMPOSER_CHANGED=$(git diff --name-only "$LOCAL" "$REMOTE" -- composer.lock)

git pull origin main >> "$LOG" 2>&1

if [ -n "$COMPOSER_CHANGED" ]; then
    composer install --no-dev --optimize-autoloader >> "$LOG" 2>&1
fi

php artisan config:cache >> "$LOG" 2>&1
php artisan route:cache >> "$LOG" 2>&1

chown -R www-data:www-data storage bootstrap/cache

systemctl restart "$FPM_SERVICE"
systemctl restart central-service-bus-relay
systemctl restart central-service-queue-work

echo "$(date -u +%FT%TZ) deploy complete ($REMOTE)" >> "$LOG"
