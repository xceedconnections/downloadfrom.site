#!/bin/bash
# Post-deploy hook for aaPanel Git webhook (downloadfrom.site)
# aaPanel → Website → downloadfrom.site → Git → Post-deploy script:
#   bash /www/wwwroot/downloadfrom.site/deploy.sh

set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "[deploy] $(date -Iseconds) starting in $ROOT"

# Server-only config — never overwritten by git pull
if [ ! -f "$ROOT/config/config.local.php" ]; then
    echo "[deploy] WARNING: config/config.local.php missing."
    echo "[deploy] Copy config/config.local.php.example and set MySQL password."
fi

# Writable dirs (cache, logs, uploads)
for dir in storage storage/cache storage/logs storage/data assets/uploads; do
    if [ -d "$ROOT/$dir" ]; then
        chown -R www:www "$ROOT/$dir" 2>/dev/null || true
        chmod -R 775 "$ROOT/$dir" 2>/dev/null || true
    fi
done

# yt-dlp binary (Linux)
if [ -f "$ROOT/bin/yt-dlp" ]; then
    chmod +x "$ROOT/bin/yt-dlp" 2>/dev/null || true
fi

# Seed MySQL if empty (safe to run every deploy — skips existing stores)
if [ -f "$ROOT/config/config.local.php" ]; then
    php "$ROOT/tools/setup-mysql.php" 2>/dev/null || echo "[deploy] setup-mysql skipped (check DB credentials)"
fi

echo "[deploy] done — code updated; admin settings remain in MySQL"
