#!/bin/bash
# Run BEFORE git pull in aaPanel (Git → Pre-deploy script):
#   bash /www/wwwroot/downloadfrom.site/pre-deploy.sh
#
# Admin data lives in MySQL now. Legacy JSON files in storage/data/
# must not block webhook pulls.

set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "[pre-deploy] $(date -Iseconds) cleaning legacy storage/data JSON"

for f in "$ROOT"/storage/data/*.json; do
    if [ -f "$f" ]; then
        rm -f "$f"
        echo "[pre-deploy] removed $(basename "$f")"
    fi
done

# Drop local git changes under storage/data (old installs still tracked settings.json)
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git checkout -- storage/data/ 2>/dev/null || true
    git clean -fd -- storage/data/ 2>/dev/null || true
fi

echo "[pre-deploy] done"
