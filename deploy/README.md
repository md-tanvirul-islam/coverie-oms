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

## Fresh deploy checklist (things that bite if skipped)

- `chmod 664 /var/www/coverie-oms/.env` — PHP-FPM runs as `www-data` inside the container, not the host user that owns the file after `git clone`/`cp`. A `600` (owner-only) `.env` is unreadable to `www-data` and fails silently as a `MissingAppKeyException`, not a permissions error.
- `chown -R www-data:www-data storage bootstrap/cache` — same reasoning; Laravel needs to write logs/cache/sessions there, and a fresh `git clone` leaves them owned by the host user.
- Composer isn't a dependency in `vendor/` (gitignored) — run `docker exec <oms-php container> composer install --no-dev --optimize-autoloader --no-interaction` after first build.
- `php artisan key:generate --force` and `php artisan migrate --force` still need to run once, same as any fresh Laravel install.
- `docker exec <container> ...` defaults to **root**, not `www-data`. Any command run that way (`composer install`, `php artisan optimize:clear`, `migrate`, etc.) that touches `storage/`, `bootstrap/cache/`, or `vendor/` re-owns those paths to root, and the next real web request 500s with silent (unlogged, since the logger itself can't open its own file) permission-denied errors. After any maintenance `docker exec` run as root, re-run: `docker exec <container> chown -R www-data:www-data storage bootstrap/cache`. Prefer `docker exec --user www-data <container> ...` for artisan/composer commands to avoid this entirely.

## Custom error pages and header hardening

`resources/views/errors/{401,403,404,419,429,500,503,4xx,5xx}.blade.php` (extending a shared `errors/_layout.blade.php`) replace Laravel's stock illustrated error pages. Laravel resolves `errors::{status}`, falling back to `errors::{first-digit}xx`, so the `4xx`/`5xx` templates catch any HTTP status without a dedicated view.

The PHP-FPM image also disables `expose_php` (via `/usr/local/etc/php/conf.d/zz-security.ini`), which removes the `X-Powered-By: PHP/x.x.x` header; Caddy strips the `Via` header it would otherwise add. Together these stop the stack from fingerprinting itself to the outside world. Verify after any change:

```bash
curl -sI https://oms.baz360.com/ | grep -iE 'x-powered-by|^via'   # should print nothing
curl -s https://oms.baz360.com/does-not-exist                     # should show the custom 404, not Laravel's
```

## Rate limiting

`bootstrap/app.php` applies `throttle:120,1` (120 req/min per route, per authenticated user or IP) to the `web` group and `throttle:60,1` to `api`. This is Laravel's built-in `ThrottleRequests` middleware in its basic (non-named-limiter) form, so it needs no `RateLimiter::for(...)` registration. Each route gets its own bucket (Laravel keys by `sha1(method|uri|user_id_or_ip)`), so hammering one endpoint doesn't burn another's budget. The existing login-attempt throttle in `app/Http/Requests/Auth/LoginRequest.php` (5 attempts, keyed by email+IP) is separate and untouched.

A 429 renders through the custom `errors/429.blade.php` view automatically. Verify:

```bash
for i in $(seq 1 125); do curl -s -o /dev/null -w "%{http_code}\n" https://oms.baz360.com/; done | sort | uniq -c
# expect ~120 200s then 429s
```

## Verifying the queue worker actually processes jobs

`tinker`'s `dispatch(function () {...})` fails to serialize closures defined in its own eval context (`file_get_contents(eval()'d code): No such file`) — this is a `tinker` limitation, not a worker bug. To verify `oms-worker` end-to-end, dispatch a real job class instead:

```bash
# create a throwaway job class (never commit this)
mkdir -p /var/www/coverie-oms/app/Jobs   # may not exist yet
cat > /var/www/coverie-oms/app/Jobs/TestQueueJob.php <<'EOF'
<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function handle(): void { Log::info('QUEUE_TEST_OK: job processed at ' . now()); }
}
EOF

docker exec coverie-oms-oms-php-1 php artisan tinker --execute="App\Jobs\TestQueueJob::dispatch();"
# jobs table should go 1 -> 0 within a few seconds; confirm via:
docker exec coverie-oms-oms-php-1 php artisan tinker --execute="echo DB::table('jobs')->count();"
grep QUEUE_TEST_OK /var/www/coverie-oms/storage/logs/laravel.log

# clean up — this file (and the directory, if it didn't exist before) is a
# verification artifact only
rm /var/www/coverie-oms/app/Jobs/TestQueueJob.php
rmdir /var/www/coverie-oms/app/Jobs 2>/dev/null || true
```
