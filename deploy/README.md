# deploy/

Production infra for coverie-oms: MySQL + PHP-FPM (`oms-php`) + queue worker (`oms-worker`), containerized.

Deployed to `~/coverie-oms/` on the VPS — a directory separate from `/var/www/coverie-oms` (the app code checkout that this compose file bind-mounts). Keeping them separate avoids this compose file's `.env` (Docker/MySQL variables) colliding with Laravel's own `.env` (app config) if they ever shared a directory.

```bash
# on the VPS, in ~/coverie-oms/
# -p is required: without it, compose infers the project name from the
# deploy/ directory itself ("deploy"), not the repo.
docker compose -p coverie-oms -f deploy/docker-compose.yml --env-file deploy/.env up -d --build
```

Requires a `.env` (not committed) with `MYSQL_ROOT_PASSWORD`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — must match `/var/www/coverie-oms/.env`'s `DB_*` values.

Edge routing (`oms.baz360.com` → `oms-php:9000` via Caddy) lives in `coverie-platform`.
