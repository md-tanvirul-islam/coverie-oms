# deploy/

Production infra for coverie-oms: MySQL + PHP-FPM (`oms-php`) + queue worker (`oms-worker`), containerized.

Deployed to `~/coverie-oms/` on the VPS — a directory separate from `/var/www/coverie-oms` (the app code checkout that this compose file bind-mounts). Keeping them separate avoids this compose file's `.env` (Docker/MySQL variables) colliding with Laravel's own `.env` (app config) if they ever shared a directory.

```bash
# on the VPS, in ~/coverie-oms/
docker compose up -d --build
```

Requires a `.env` (not committed) with `MYSQL_ROOT_PASSWORD`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — must match `/var/www/coverie-oms/.env`'s `DB_*` values.

Edge routing (`oms.baz360.com` → `oms-php:9000` via Caddy) lives in `coverie-platform`.
