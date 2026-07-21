# Financial Performance Platform — Backend

Laravel API backend for the restaurant and café financial performance platform.

- **Repository:** `financial-performance-backend`
- **Stack:** Laravel 13 · PHP 8.4 · MySQL 8.4 · Redis 7.4 · Nginx
- **Local runtime:** Docker Compose (`compose.yaml`)

Architecture and product specifications live in the separate `project-docs`
repository. Decisions referenced below (`DEC-0xx`) are recorded in
`project-docs/workflow/DECISIONS_LOG.md`.

---

## Requirements

- Docker Desktop with the WSL2 backend, running.
- The drive holding this repository shared with Docker Desktop
  (Settings → Resources → File sharing).

PHP, Composer and MySQL are **not** required on the host — everything runs in
containers.

---

## First run

```powershell
# 1. Create your local environment file.
copy .env.example .env

# 2. Fill in the two passwords in .env:
#      DB_PASSWORD=...
#      MYSQL_ROOT_PASSWORD=...
#    compose.yaml reads them from here; no credential is stored in the
#    compose file itself (DEC-032).

# 3. Build and start.
docker compose build
docker compose up -d

# 4. Install PHP dependencies inside the container (DEC-031).
docker compose exec app composer install

# 5. Generate an application key if .env has an empty APP_KEY.
docker compose exec app php artisan key:generate

# 6. Create the schema.
docker compose exec app php artisan migrate
```

Then open <http://localhost:8080/up>.

`.env` must exist before `docker compose up`. Without it Compose fails fast
with an explicit message naming the missing variable, rather than starting
MySQL with empty credentials and failing later for a misleading reason.

---

## Services and ports

| Service | Purpose | Host port | Container port |
|---|---|---|---|
| `nginx` | Single HTTP entry point | **8080** | 80 |
| `app` | PHP-FPM — HTTP requests only | — | 9000 (internal only) |
| `worker` | Horizon queue worker | — | — |
| `scheduler` | `schedule:work` loop | — | — |
| `mysql` | Database | **3307** | 3306 |
| `redis` | Queue, cache, sessions, locks | **6380** | 6379 |
| `mailpit` | Local SMTP sink | **8026** (UI) | 8025 |

Host ports avoid Laragon's defaults (DEC-029). Laragon also ships its own
Mailpit on 8025, so ours is published on **8026** instead.

**Inside the Docker network the standard ports always apply** — `DB_HOST=mysql`
with port 3306, `REDIS_HOST=redis` with port 6379. The alternate host ports are
only for Windows tools such as TablePlus or DBeaver:

- MySQL: `127.0.0.1:3307`
- Redis: `127.0.0.1:6380`
- Mailpit UI: <http://localhost:8026>

`app`, `worker` and `scheduler` all run the same image with different commands.

---

## Daily commands

All Composer and Artisan commands run **inside the container** (DEC-031). The
host's PHP lacks the `redis` and `pcntl` extensions, so running them on the
host will fail once Redis-backed drivers are in use.

```powershell
docker compose up -d
docker compose down                  # stop; keeps database data

docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose exec app composer install

docker compose exec app ./vendor/bin/pint          # format
docker compose exec app ./vendor/bin/pint --test   # check only
docker compose exec app php artisan test --compact

docker compose logs -f app worker
docker compose ps
```

> **Warning:** `docker compose down -v` deletes the `fpp-mysql-data` volume and
> with it the entire local database. It is intended only for a clean
> first-time bootstrap, before any real data exists (DEC / plan constraint N5).
> For everyday use run `docker compose down` without `-v`.

---

## Running tests

Tests run inside Docker against **MySQL 8.4**, not SQLite, using a dedicated
database `financial_performance_test`. The development database
`financial_performance` is never touched: `phpunit.xml` forces the connection to
the test database, and a safety guard in `tests/TestCase.php` aborts the suite
**before any migration** if the environment is not `testing`/`mysql`/
`financial_performance_test`.

The test database is created by a single init script — the one source of truth,
never duplicated as SQL here:

```text
docker/mysql/init/01-create-test-database.sh
```

**Fresh clone (empty volume):** MySQL runs the script automatically from
`/docker-entrypoint-initdb.d` on first `docker compose up`. Nothing to do.

**Existing volume:** `docker-entrypoint-initdb.d` does not run retroactively, so
after pulling this change run the **same** script once by hand (no `down -v`, no
data loss):

```powershell
docker compose exec mysql sh /docker-entrypoint-initdb.d/01-create-test-database.sh
```

Then run the suite:

```powershell
docker compose exec app php artisan test --compact
```

> **Warning:** never use `docker compose down -v` to "reset" for tests — it
> deletes the `fpp-mysql-data` volume and the entire local development database
> with it. The test database lives in the same volume; recreate it with the init
> script above, not by wiping the volume.

---

## Health endpoints

Two separate endpoints with two different jobs (DEC-039):

| Endpoint | Kind | Checks | Used by |
|---|---|---|---|
| `GET /up` | Liveness | Is the process alive and able to boot? No dependencies. | Docker `healthcheck` in `compose.yaml` |
| `GET /api/v1/health` | Readiness | Also reaches MySQL and Redis. | Load balancers, deploy gates, humans |

`/api/v1/health` returns **200** when both services answer and **503** when
either one does not — the process stays up either way, so a dependency outage
never triggers a container restart. The body is always JSON, with
`Cache-Control: no-store`:

```json
{ "status": "healthy", "services": { "database": "healthy", "redis": "healthy" } }
```

The probe uses the dedicated `health_mysql` / `health_redis` connections with
short connect timeouts, so it never changes the timeouts of the application's
own connections (DEC-040).

The endpoint is **unauthenticated at this stage** and deliberately reveals
nothing beyond which of the two services is up: no exception text, host, port,
database name, credential, SQLSTATE, stack trace, timing or version. Restricting
it at the network layer is left to a later slice.

```powershell
curl -i http://localhost:8080/up
curl -i http://localhost:8080/api/v1/health
```

---

## Queue worker — Horizon

The `worker` service runs Laravel Horizon, which supervises the queue
processes itself:

```text
php artisan horizon
```

**Never start `queue:work` alongside it** on the same connection — both would
consume the same Redis queue and every job would run twice. Horizon replaces
the temporary `queue:work redis` command (DEC-028).

Worker settings live in `config/horizon.php`, not in command-line flags, so the
dashboard and the running processes can never disagree:

| Setting | Value | Why |
|---|---|---|
| Supervisors | 1 | One workload, nothing to prioritise yet |
| Connection / queue | `redis` / `default` | The only queue in use |
| `balance` | `false` | No auto scaling — a fixed process count |
| `maxProcesses` | 1 | Sized for a local stack and a small VPS |
| `tries` | 3 | Conservative retry for transient failures |
| `timeout` | 60s | Must stay **below** `retry_after` (90s), otherwise a slow job is released while still running and processed twice |

```powershell
docker compose logs -f worker              # what the worker is processing
docker compose restart worker              # graceful: SIGTERM, current job finishes
docker compose exec worker php artisan horizon:terminate   # after deploying new code
```

### Dashboard

<http://localhost:8080/horizon>

Access is Horizon's built-in check — `Gate::check('viewHorizon') || environment('local')`:

- **local** → open, which is what this stack is.
- **any other environment** → **403**, because the `viewHorizon` gate in
  `app/Providers/HorizonServiceProvider.php` denies everyone.

There is no user the gate could legitimately allow yet: authentication is
deferred to its own slice (DEC-035). The gate is reopened there and bound to
`SYSTEM_ADMIN`. No Basic Auth, no custom middleware and no shared secret is
involved.

`horizon:snapshot` scheduling, queue metrics and failure notifications are
deliberately **not** configured yet.

### Verifying the queue end to end

`App\Jobs\Infrastructure\HorizonSmokeTestJob` exists only to prove the path
`dispatch → Redis → Horizon worker` works. It has no business logic:

```powershell
docker compose exec app php artisan tinker
>>> App\Jobs\Infrastructure\HorizonSmokeTestJob::dispatch();
```

Then check all three:

```powershell
docker compose logs --tail=20 worker    # ... HorizonSmokeTestJob ... DONE
docker compose exec app sh -c 'tail -n 5 storage/logs/laravel-$(date +%F).log'
```

and the dashboard's **Completed Jobs** list — the job must appear there, not
under **Failed Jobs**.

---

## Monitoring — Pulse

<http://localhost:8080/pulse>

Access follows Pulse's own rule, `Gate::check('viewPulse')`, which the package
defines as "the environment is `local`":

- **local** → open, which is what this stack is.
- **any other environment** → **403**.

No gate, provider, Basic Auth, custom middleware or shared secret is added by
this project. The gate is redefined and bound to `SYSTEM_ADMIN` in the
authentication slice (DEC-035); until then `/pulse` is a local-only tool.

**Pulse writes straight into the application's MySQL database.** The `storage`
ingest driver stores entries at the end of the request, so there is **no
`pulse:work` and no `pulse:check` process, and no Pulse service in
`compose.yaml`**. Data lives in `pulse_values`, `pulse_entries` and
`pulse_aggregates` inside `financial_performance`, and is trimmed
automatically during ingest — no scheduled task is involved.

| Recorder | State | Why |
|---|---|---|
| Exceptions | **on** | Highest value for the lowest write cost |
| Queues | **on** | Complements Horizon with history |
| Slow jobs | **on** | Threshold 1000ms |
| Slow queries | **on** | Threshold 1000ms, with the calling location |
| Slow requests | **on** | Threshold 1000ms |
| Cache interactions | off | Highest cardinality, heaviest write load |
| Slow outgoing requests | off | No external integrations in the MVP |
| User requests / user jobs | off | No authentication yet — everything would be a guest |
| Servers | off | Only records while a `pulse:check` daemon runs; none exists |

Retention is **7 days** and the sample rate is **1** (nothing is sampled away)
— sized for a small VPS with low traffic.

```powershell
docker compose exec app php artisan pulse:clear   # drop all collected data
```

Server monitoring and production authorization are deliberately deferred:
server metrics need their own long-running process, and the dashboard stays
local-only until authentication exists.

---

## Connecting clients

### Next.js admin panel

`admin-web` runs on the host (not in this stack), and points at:

```env
NEXT_PUBLIC_API_URL=http://localhost:8080
```

Sanctum, CORS and `SESSION_DOMAIN` are deliberately **not** configured yet;
they belong to the authentication slice (DEC-035).

### Expo mobile app

| Target | Base URL |
|---|---|
| Android emulator | `http://10.0.2.2:8080` |
| iOS simulator | `http://localhost:8080` |
| Physical device | `http://<windows-lan-ip>:8080` |

For a physical device: the phone and PC must share a Wi-Fi network, the
Windows network profile must be **Private**, and an inbound Windows Firewall
rule for TCP 8080 is required. Without that rule the connection fails silently
— it is the most common cause of "the app can't reach the API" locally.

Get the LAN address with `ipconfig` (IPv4 of the Wi-Fi adapter).

---

## Notes

- **Arabic content:** MySQL runs `utf8mb4` / `utf8mb4_0900_ai_ci` throughout.
  `DB_COLLATION` in `.env` pins Laravel to the same collation so the schema and
  the server agree.
- **Configuration files** live in `docker/`. The PHP ones are baked into the
  image, so changing them requires `docker compose build app`.
- **`.env` is never committed.** It is excluded by `.gitignore`.
- **Bind-mount performance:** the project is mounted across the Windows↔WSL2
  boundary, which is noticeably slower than a native Linux filesystem. This is
  acceptable at the current size; moving the repository inside WSL2 is the
  remedy if it ever becomes a problem.
