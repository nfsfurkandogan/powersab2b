#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_DIR="$ROOT_DIR/apps/web"

REMOTE_USER="${REMOTE_USER:-root}"
REMOTE_HOST="${REMOTE_HOST:-62.72.20.30}"
REMOTE_PORT="${REMOTE_PORT:-22}"
REMOTE_WEB_ROOT="${REMOTE_WEB_ROOT:-/var/www/powersab2b.com/web}"

STAMP="${DEPLOY_STAMP:-web-$(date +%Y%m%d-%H%M%S)}"
SSH=(ssh -o BatchMode=yes -p "$REMOTE_PORT")
RSYNC_RSH="ssh -o BatchMode=yes -p $REMOTE_PORT"

cd "$WEB_DIR"
npm run build

"${SSH[@]}" "$REMOTE_USER@$REMOTE_HOST" "set -e; cd '$REMOTE_WEB_ROOT'; mkdir -p tmp .codex-backups; cp -p server.cjs '.codex-backups/server.cjs-before-$STAMP' 2>/dev/null || true; if [ -d .next ]; then cp -al .next '.next-prev-$STAMP'; fi"

rsync -az --delete --exclude='dev/' -e "$RSYNC_RSH" "$WEB_DIR/.next/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_WEB_ROOT/.next/"
rsync -az --delete -e "$RSYNC_RSH" "$WEB_DIR/public/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_WEB_ROOT/public/"
rsync -az -e "$RSYNC_RSH" \
  "$WEB_DIR/server.cjs" \
  "$WEB_DIR/package.json" \
  "$WEB_DIR/package-lock.json" \
  "$WEB_DIR/next.config.ts" \
  "$REMOTE_USER@$REMOTE_HOST:$REMOTE_WEB_ROOT/"

"${SSH[@]}" "$REMOTE_USER@$REMOTE_HOST" "set -e; cd '$REMOTE_WEB_ROOT'; if [ -d '.next-prev-$STAMP/static' ]; then rsync -a --ignore-existing '.next-prev-$STAMP/static/' '.next/static/'; fi; mkdir -p .next/cache/images; chown -R www-data:www-data .next/cache; chmod -R u+rwX,g+rwX .next/cache; rm -rf src src_upload_tmp .codex-staging ._.next .env.example .env.local.example README.md eslint.config.mjs next-env.d.ts postcss.config.mjs tsconfig.json components.json .gitignore; echo '$STAMP' > tmp/last-ui-deploy-stamp.txt; touch tmp/restart.txt; systemctl restart powersab2b-web.service; echo \"deployed_build=\$(cat .next/BUILD_ID)\"; echo \"fixed_icons=\$(find public/dashboard-icons/fixed -maxdepth 1 -type f -name '*.webp' 2>/dev/null | wc -l)\""
