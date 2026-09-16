# Phase 2 evidence — api-private

| Field | Value |
|-------|-------|
| Service | `api-private` |
| Fixture | `fixtures/phase2/api-private-env` |
| Port | **4103** |
| Gateway | `http://127.0.0.1:8092` |
| State dir | `.jit-state-private` |
| Private env | `envPrivate: DATABASE_URL => JIT_DATABASE_URL` |
| Test secret | `JIT_DATABASE_URL=postgres://localhost:5432/mock` (never committed) |
| Branch | `phase2/api-private-matrix` |
| Docker | skipped (host PHP built-in server) |

Raw curl dumps: `docs/evidence-api-private/` (`*.hdr`, `*.body`, `*.code`).

## Matrix result

| ID | Result | Evidence |
|----|--------|----------|
| T1 | **PASS** | HTML cold → **202** boot page (`Starting api-private`); reload API **200** |
| T2 | **PASS** | Cold API wait → **200**, `X-Dev-Cold-Start: 1`, `hasDb: true` |
| T3 | **PASS** | Warm → **200**, `X-Dev-Cold-Start: 0`, no rebuild (`X-Dev-Build` empty) |
| T4 | **PASS** | Touch `build.ts` → rebuild `X-Dev-Build-Ms: 42`, new build id `0e8bf0ff30d7` (dirty bit cleared after successful ready) |
| T5 | **PASS** | Broken build + last-good → **200**, `X-Dev-State: degraded`, `X-Dev-Stale: 1`, `X-Dev-Dirty: 1`; package.json restored |
| T6 | **PASS** | Cold fail, no last-good → **502** `Service unavailable`, `X-Dev-State: failed`; restored via rebuild |
| T7 | **PASS** | POST `/echo?q=1&r=2` body + `x-custom-a: one, two` preserved upstream |
| T8 | **PASS** | Hook `service.api-private.request.headers` → upstream `x-fixture-hook: api-private` |
| T9 | **PASS** | `POST .../stop` **200** with `X-Dev-Action: 1`; rebuild without header **403**; with header **200** (localhost any-port allowlist fix) |
| T10 | **PASS** | `GET /__dev/services/api-private` (+ logs) contain **no** `postgres://localhost:5432/mock`; only `envPublic.PORT` |
| T11 | **PASS** | Direct mock without `DATABASE_URL` → `/health` **503**; via gateway with projected URL + `readyPath` → **200** `{db:"connected"}` |
| T12 | **PASS** | Throwaway `api-tcp-probe` port **4104**, **no `readyPath`** → TCP readiness **200** `{mode:"tcp-ready"}` |

**Score: 12/12 PASS** (T12 is throwaway TCP probe, not api-private itself)

## T10 redaction excerpt

```json
{
  "name": "api-private",
  "envPublic": { "PORT": "4103" }
}
```

`grep` for `postgres://localhost:5432/mock` over snapshot + logs: **no matches**.

## T11 readiness notes

- Mock `/health` returns **503** when child lacks `DATABASE_URL`.
- Router `envPrivate` requires `JIT_DATABASE_URL` before start; with it set, HTTP readiness on `/health` returns 2xx and service becomes ready.
- Evidence: `T11nodb` (503) + `T11ready` (200 via gateway).

## T12 TCP throwaway

Clearly marked in `config/services.php`:

```php
// THROWAWAY Phase2 T12 — TCP readiness only (no readyPath). Port 4104.
'api-tcp-probe' => [ ... /* no readyPath */ ],
```

Fixture: `fixtures/phase2/api-tcp-probe` (clone of api-simple pattern).

## Router fixes (minimal, test-linked)

| Fix | Test | File |
|-----|------|------|
| SupervisorPool use constructor locals (`$runner` not `$this->runner`) | boot | `src/Supervisor/SupervisorPool.php` |
| `buildMs` cast after division + supervisor disk persist (from main) | T2+ | `src/Supervisor/ServiceSupervisor.php` (+ `ProcessRunner`, `HttpProxy`) |
| Localhost allowlist: any port on `127.0.0.1`/`localhost` | T9 @ :8092 | `src/Application.php` |

## How to re-run

```bash
export JIT_WORKSPACE="$PWD"
export JIT_CONFIG="$PWD/config/services.php"
export JIT_STATE_DIR="$PWD/.jit-state-private"
export JIT_DATABASE_URL='postgres://localhost:5432/mock'
php -S 127.0.0.1:8092 -t public public/index.php
# then curl matrix against :8092 / api-private (and api-tcp-probe for T12)
JIT_USE_DOCKER=0 ./bin/dev test
```

## Blockers

None for api-private matrix on host gateway. Docker path not exercised (per brief).
