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
