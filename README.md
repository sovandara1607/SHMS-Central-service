# Central Service

The "Central Service" from the Smart Hospital Management System's
architecture diagram, split out as its own deployable Laravel app. The
Application Server lives in a separate repo (`Database-final`) and does not
share PHP code with this one — the two are wired together only through a
Redis list (async) and a small REST API (sync).

Responsibilities:
- **Data Synchronization Engine** — mirrors medical record versions into
  MongoDB (`app/Jobs/SyncMedicalRecordVersionJob.php`), and writes the
  tamper-evident audit trail (`app/Jobs/LogAuditEventJob.php`).
- **File & Document Processor** — renders lab report PDFs, uploads them to
  the shared documents disk (Cloudflare R2 or local), and mirrors a snapshot
  into MongoDB (`app/Jobs/GenerateLabReportDocumentJob.php`).

## Shared infrastructure

This app connects to the **same** Postgres database, MongoDB database, and
Redis instance as the Application Server — it does not own a separate data
store. Match `.env` here to the Application Server's `.env` for
`DB_*`, `MONGODB_*`, and `REDIS_*`.

**This app never runs `php artisan migrate`.** Postgres schema ownership
stays with the Application Server (`Database-final/database/migrations`);
Central Service only reads/writes existing tables (`hospital_settings`,
`lab_report`, `lab_test_order`, `lab_test_result`, etc.) through thin,
non-migrating Eloquent models in `app/Models/`.

## Integration contract

### Async: the shared Redis bus

The Application Server pushes plain JSON messages onto a Redis list —
`central-service:jobs` — over a dedicated, **unprefixed** connection named
`bus` (`config/database.php`), on its own logical DB (`REDIS_BUS_DB`,
default `2`) so it can't collide with either app's own cache/session/queue
keyspace.

Message shape:
```json
{ "type": "log_audit_event", "payload": { "action": "...", "entity": "...", "...": "..." } }
```

`app/Console/Commands/RelayBusJobs.php` (`php artisan bus:relay`) blocks on
that list and turns each message into one of this app's own queued jobs via
**named-argument unpacking** — `payload` keys must match the target job's
constructor parameter names exactly (see `DISPATCH_MAP` in that command).
This is a plain data contract, not a shared PHP class, so either side can be
rewritten in a different language later without touching the other.

Run both processes together:
```bash
php artisan bus:relay          # drains the shared bus, dispatches internal jobs
php artisan queue:work --tries=3  # processes those jobs (retries, failed_jobs table)
```

### Sync: REST API

For cases where the caller is actively waiting on a result (e.g. a staff
member clicking "Regenerate PDF" because the async path didn't run yet):

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/api/health` | none | liveness check |
| GET | `/api/lab-reports/{id}/status` | `X-Central-Service-Key` | is the PDF ready, and where |
| POST | `/api/lab-reports/{id}/regenerate` | `X-Central-Service-Key` | render + upload the PDF synchronously |

Auth is a single shared secret header, not a user session — there's no login
here. `CENTRAL_SERVICE_API_KEY` must match the same value in the Application
Server's `.env`.

## Local development

```bash
composer install
cp .env.example .env   # fill in DB_*, MONGODB_*, REDIS_*, CENTRAL_SERVICE_API_KEY
                        # to match Database-final's .env
php artisan serve --port=8100   # REST API
php artisan bus:relay            # separate process
php artisan queue:work --tries=3 # separate process
```

Requires the same Postgres/MongoDB/Redis containers as `Database-final`
(`docker compose up -d` in that repo) to already be running.
