#!/usr/bin/env bash
# Build the site and serve it like Wedos would: Apache + PHP + .htaccess.
# Server state (data/, uploads/, config.php) lives in .local-server/ and
# survives rebuilds, just like on the real server.
set -euo pipefail
cd "$(dirname "$0")/.."

IMAGE=zubni-lisov-php
STATE=.local-server
WWW=$STATE/www
PORT=${PORT:-8080}

docker build -q -t "$IMAGE" docker >/dev/null
npm run build

mkdir -p "$STATE"
[ -d "$STATE/data" ] || cp -R server-seed/data "$STATE/data"
[ -d "$STATE/uploads" ] || cp -R server-seed/uploads "$STATE/uploads"
if [ ! -f "$STATE/config.php" ]; then
  # local-only password: "lisov-local-test"
  HASH=$(docker run --rm "$IMAGE" php -r 'echo password_hash("lisov-local-test", PASSWORD_DEFAULT);')
  sed -e "s#'password_hash' => ''#'password_hash' => '$HASH'#" \
      -e "s#'cookie_secure' => true#'cookie_secure' => false#" \
      public/admin/config.example.php > "$STATE/config.php"
fi

rm -rf "$WWW" && cp -R dist "$WWW"
ln -s ../data "$WWW/data"
ln -s ../uploads "$WWW/uploads"
cp "$STATE/config.php" "$WWW/admin/config.php"
cp tools/server-check.php "$WWW/admin/check.php"

docker rm -f zubni-lisov >/dev/null 2>&1 || true
docker run -d --name zubni-lisov -p "$PORT:80" -v "$PWD/$STATE:/var/www/state" "$IMAGE" \
  bash -c 'rm -rf /var/www/html && ln -s /var/www/state/www /var/www/html && chown -R www-data /var/www/state/data /var/www/state/uploads && apache2-foreground' >/dev/null
echo "Site:  http://localhost:$PORT/"
echo "Admin: http://localhost:$PORT/admin/   (password: lisov-local-test)"
