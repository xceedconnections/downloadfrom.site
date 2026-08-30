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

# yt-dlp binary (Linux) — required for YouTube/TikTok/SoundCloud downloads
YTDLP="$ROOT/bin/yt-dlp"
echo "[deploy] updating yt-dlp..."
curl -fsSL "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp" -o "$YTDLP" || {
    echo "[deploy] WARNING: failed to download yt-dlp — video/audio downloads may not work"
}
if [ -f "$YTDLP" ]; then
    chmod +x "$YTDLP" 2>/dev/null || true
    chown www:www "$YTDLP" 2>/dev/null || true
fi

# Ensure config.local.php has yt-dlp + node paths for production
if [ -f "$ROOT/config/config.local.php" ]; then
    php "$ROOT/tools/ensure-ytdlp-config.php" || true
fi

# Clear stale extraction cache (old single-quality results)
if [ -d "$ROOT/storage/cache" ]; then
    find "$ROOT/storage/cache" -type f -name '*.json' -delete 2>/dev/null || true
fi

# Seed MySQL if empty (safe to run every deploy — skips existing stores)
if [ -f "$ROOT/config/config.local.php" ]; then
    php "$ROOT/tools/setup-mysql.php" || {
        echo "[deploy] ERROR: setup-mysql failed — check config/config.local.php DB password"
        exit 1
    }
else
    echo "[deploy] ERROR: config/config.local.php missing — create it with MySQL password"
    exit 1
fi

# Verify yt-dlp returns multiple qualities + MP3
if [ -x "$YTDLP" ]; then
    if ! php "$ROOT/tools/verify-ytdlp.php"; then
        echo "[deploy] ERROR: yt-dlp verification failed."
        echo "[deploy] Ensure Node.js is installed (node --version) and config.local.php has ytdlp.node_path."
        echo "[deploy] YouTube downloads will show 360p only until this is fixed."
    fi
fi

echo "[deploy] done — code updated; admin settings remain in MySQL"
