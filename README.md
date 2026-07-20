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
| `worker` | Queue worker | — | — |
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

## Queue worker

The `worker` service currently runs:

```text
php artisan queue:work redis --tries=3 --timeout=90
```

When Horizon is installed in a later slice, this command is replaced by
`php artisan horizon`. **`queue:work` and Horizon must never run at the same
time** on the same connection — that causes double processing (DEC-028).

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
