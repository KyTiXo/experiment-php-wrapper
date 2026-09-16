# Phase 2 cycle log

Orchestrator session for mock Nest-style monorepos + T1–T12 matrix.

**Reconcile:** 2026-09-16 overnight merge of `phase2/api-simple-matrix`, `phase2/api-with-lib-matrix`, `phase2/api-private-matrix` into `master` (init `2c54374`).

## Directory plan

| Mock | Path | Port | Notes |
|------|------|------|-------|
| fixture (baseline) | `fixtures/bun-service` | 4100 | existing |
| A api-simple | `fixtures/phase2/api-simple` | 4101 | `/health`, `/api/v1/ping`, `/echo` |
| B api-with-lib | `fixtures/phase2/api-with-lib` | 4102 | shared-lib + app watch |
| C api-private | `fixtures/phase2/api-private-env` | 4103 | `envPrivate` DATABASE_URL |
| T12 throwaway | `fixtures/phase2/api-tcp-probe` | 4104 | **no** `readyPath` (TCP-only) |

## Final summary

Phase 2 matrix runs completed in three isolated worktrees and merged to `master`. Router fixes required for host `php -S` gateways: SupervisorPool ctor wiring, `buildMs` cast, `curl_close` omit, `runtime.json` persist/rehydrate + adopted-PID stop, and `isLocalRequest()` any local port (`[::1]` included).

Evidence:
- `docs/PHASE2_EVIDENCE_api-simple.md` — 11 pass + T12 N/A
- `docs/PHASE2_EVIDENCE_api-with-lib.md` — T1–T7,T9–T11 pass; T8/T12 N/A; T4 needs content edit (not pure touch)
- `docs/PHASE2_EVIDENCE_api-private.md` + `docs/evidence-api-private/` — 12/12 pass (T12 via `api-tcp-probe`)

Docker Compose skipped throughout (workspace path contains spaces → bind-mount fails). Host gateway + `JIT_USE_DOCKER=0 ./bin/dev test` used instead.

## Cycle summary

| Cycle | Goal | Tests | check | Notes |
|-------|------|-------|-------|-------|
| 1 | Scaffold api-simple + config + T2 smoke | T2 fail | skipped | Local bun ok; host gateway fatal SupervisorPool; Docker mount fail (path spaces) |
| 2 | Fix SupervisorPool ctor deps (T2) | T2 fail | skipped | Pool fixed; unit tests 15/15; T2 now fails on buildMs float assign |
| 3 | Fix buildMs cast precedence (T2) | T2 pass (cold) | skipped | Cold API 200, X-Dev-Cold-Start:1, Build-Ms:148; curl_close deprecation pollutes body |
| 4 | Remove curl_close (PHP 8.5) | T2 clean; T3 fail | skipped | Interrupted by STOP; body clean; warm still X-Dev-Cold-Start:1 (rebuild) |
| 5 | Scaffold fixtures B+C + register | local smoke | skipped | api-with-lib 4102 + api-private 4103; api-simple hook for T8; no gateway matrix |
| 6 | WP-supervisor-persist (disk PID/fp) | T3 pass; unit 15/15 | lint ok (no smoke) | runtime.json adopt; warm Cold-Start:0; Docker skipped (path spaces) |
| 7 | Matrix api-simple (worktree) | T1–T11 pass; T12 N/A | skipped | T9 `isLocalRequest` hostname-only fix; evidence doc |
| 8 | Matrix api-with-lib (worktree) | T1–T7,T9–T11 pass; T8/T12 N/A | skipped | T4 content-hash note; evidence doc |
| 9 | Matrix api-private (worktree) | 12/12 pass | skipped | T12 via throwaway `api-tcp-probe` :4104; curl dumps |
| 10 | Overnight reconcile → master | merge + unit 15/15 | host test only | Merged simple→with-lib→private; keep all evidence; services 4100–4104; Docker skipped (path spaces) |

## Pass/fail matrix (final)

| Test | api-simple | api-with-lib | api-private |
|------|------------|--------------|-------------|
| T1   | pass       | pass         | pass        |
| T2   | pass       | pass         | pass        |
| T3   | pass       | pass         | pass        |
| T4   | pass       | pass†        | pass        |
| T5   | pass       | pass         | pass        |
| T6   | pass       | pass         | pass        |
| T7   | pass       | pass         | pass        |
| T8   | pass       | N/A          | pass        |
| T9   | pass       | pass         | pass        |
| T10  | pass       | pass         | pass        |
| T11  | pass       | pass         | pass        |
| T12  | N/A        | N/A          | pass‡       |

† T4 on api-with-lib: content edit under `packages/shared-lib` dirties fingerprint; pure `touch` does not (content-hash by design).  
‡ T12 exercised via throwaway `api-tcp-probe` (port 4104, no `readyPath`), not api-private itself. api-simple / api-with-lib keep `readyPath` → T12 N/A.

## Remaining gaps

- Docker Desktop bind-mount fails when workspace path contains spaces; no `JIT_USE_DOCKER=1` evidence on this machine.
- Nginx gateway / Compose path not re-verified after matrix (host `php -S` only).
- SSE under single-threaded `php -S` cannot build+stream concurrently (T1 boot page still embeds EventSource; curl SSE alone may return 0 bytes).
- `api-with-lib` has no T8 hook registered (intentional N/A).
- T12 coverage is throwaway service; production services still use HTTP `readyPath`.
- Watch/fingerprint: mtime-only touch insufficient — operators must change file content for dirty rebuild demos.
- Real Nest `nest build` layout vs mock `dist/` still unproven.
- Process group / `setsid` termination still deferred (adopted PID stop is SIGTERM/SIGKILL on single PID).

## Suggested Phase 3

- Real NestJS apps with `nest start` / SWC build against the same supervisor.
- Process group termination (`setsid`) so child trees die with the service.
- CI job: `JIT_USE_DOCKER=1 ./bin/dev check` on Linux (path without spaces).
- Optional: tarball fingerprint mode; richer dirty/stale UX.
- Decide fate of throwaway `api-tcp-probe` (keep for TCP readiness regression or remove).
- Symlink / relocate workspace to a spaceless path if Docker Compose evidence is required.

## Handoff block (for next human session)

1. **Status — yellow/green:** Matrix evidence green across three services (T8/T12 N/A where applicable; private 12/12 including TCP probe). Host `JIT_USE_DOCKER=0 ./bin/dev test` → **15 passed, 0 failed**. Docker Compose / full `./bin/dev check` with Docker **not** green on this Mac path (spaces). Review agent (Bugbot) not run by reconcile — parent launches separately.
2. **Artifacts**
   - Mocks: `fixtures/phase2/{api-simple,api-with-lib,api-private-env,api-tcp-probe}`
   - Evidence: `docs/PHASE2_EVIDENCE_api-{simple,with-lib,private}.md`, dumps `docs/evidence-api-private/`
   - Log: `docs/PHASE2_CYCLE_LOG.md` (this file)
   - Config: `config/services.php` ports 4100–4104; hooks for fixture, api-simple, api-private
   - Router: `Application::isLocalRequest`, `ServiceSupervisor` runtime.json, `ProcessRunner` adopted PID stop, `HttpProxy` curl_close omit, `SupervisorPool` ctor, `buildMs` casts
3. **Action list (max 3)**
   1. Human review of merged router patch + evidence docs (or let parent Bugbot run).
   2. Confirm `JIT_DATABASE_URL` policy for local/Compose (fake URL only; never commit `.env`).
   3. Cleanup worktrees when ready: `/delete-worktree` for `phase2-simple-bfdfb8aa`, `phase2-lib-f5112be3`, `api-private-c5e8bbf2` (reconcile left them intact).

---

## Cycle details

### Cycle 1 — scaffold api-simple + cold API smoke

**Goal:** Create `fixtures/phase2/api-simple`, register port 4101, smoke T2 via host gateway.

**Commands:**
- `bun install && bun run build` in mock — OK
- Direct `PORT=4101 bun run start` → `/health` 200, `/api/v1/ping` 200
- `php -S 127.0.0.1:8090` + curl T2 → **fatal** `SupervisorPool` undefined `$this->runner`
- `docker compose up -d --build` → fail: mount path with spaces (`operation not permitted`)

**Files touched:** mock under `fixtures/phase2/api-simple/`, `config/services.php` (+api-simple), this log.

**Pass/fail delta:** T2 api-simple = fail (router). Local mock lifecycle OK.

**Blockers:** Docker Desktop cannot bind-mount workspace path containing spaces (one attempt). Will use host PHP gateway for matrix; retry Docker via symlink without spaces later if needed.

### Cycle 2 — fix SupervisorPool (T2 evidence)

**Goal:** Fix undefined `$this->runner` / fingerprints / readiness in pool constructor.

**Change:** `src/Supervisor/SupervisorPool.php` — pass local `$runner`, `$fingerprints`, `$readiness` into `ServiceSupervisor`.

**Verify:** `JIT_USE_DOCKER=0 ./bin/dev test` → 15 passed. T2 curl still fails: `Cannot assign float to property ... $buildMs of type ?int` at ServiceSupervisor.php:164 (operator precedence on cast/div).

**Pass/fail delta:** T2 still fail (new root cause).


### Cycle 3 — fix buildMs int cast (T2 evidence)

**Goal:** Fix float assignment to `$buildMs` via cast/div precedence.

**Change:** `src/Supervisor/ServiceSupervisor.php` line 164 — `(int) ((now - start) / 1e6)`.

**Evidence:** cold curl → HTTP 200, `X-Dev-Cold-Start: 1`, `X-Dev-State: ready`, body `{"pong":true,...}` (plus PHP 8.5 `curl_close()` deprecation after body).

**Pass/fail delta:** T2 api-simple = pass. Unit tests still 15/15.

### Cycle 4 — remove curl_close (interrupted)

**Goal:** Stop PHP 8.5 `curl_close()` deprecation from appending to proxied bodies.

**Change:** `src/Http/HttpProxy.php` — drop both `curl_close($ch)` calls (handles are GC'd).

**Evidence before STOP:**
- T2 cold clean: 200, body exactly `{"pong":true,"service":"api-simple"}`, no deprecation in log.
- T3 warm: 200 but `X-Dev-Cold-Start: 1`, `X-Dev-Build-Ms: 41` (rebuild) — fails T3 “no rebuild / cold-start 0”. Likely per-request `Application` loses in-memory `PersistentProcess` (php -S / FPM).

**Status:** Cycle incomplete (STOP). Files intact (php -l OK).

### Cycle 5 — scaffold fixtures B+C + config (WP-mock-B/C)

**Goal:** Add `api-with-lib` (4102) and `api-private-env` (4103); register in `config/services.php`; optional T8 hook; local bun smoke only (no T1–T12 matrix).

**Commands:**
- `bun install && bun run build` in both mocks — OK
- `PORT=4102 bun run start` → `/health` 200, `/api/v1/version` `{"lib":"shared-v1"}`, `/echo` OK
- `PORT=4103 bun run start` → `/health` **503** without `DATABASE_URL`
- `PORT=4103 DATABASE_URL=postgres://localhost:5432/mock bun run start` → `/health` `{"ok":true,"db":"connected"}` 200

**Files touched:**
- `fixtures/phase2/api-with-lib/` (workspaces: `packages/shared-lib`, `apps/api-with-lib`)
- `fixtures/phase2/api-private-env/` (`apps/api-private`)
- `config/services.php` (+api-with-lib, +api-private)
- `config/hooks.php` (`service.api-simple.request.headers` → `X-Fixture-Hook`)
- this log

**Pass/fail delta:** matrix unchanged (no gateway runs). Local mock lifecycle OK for B+C.

**Blockers:** none for this WP. Still need supervisor persist before warm-path matrix; Compose still needs fake `JIT_DATABASE_URL` for api-private readiness via gateway.

---

## CHECKPOINT (STOP — parent restructure)

**Stopped:** immediately on parent instruction. No further cycles or matrix work.

### Last completed cycle
- **Last fully logged cycle:** 3
- **In-flight at STOP:** cycle 4 (HttpProxy fix landed + verified; log/checkpoint written on STOP)

### Files created / changed this session

**Created**
- `fixtures/phase2/api-simple/` (monorepo mock: package.json, apps/api-simple/{package.json,build.ts,server.ts}, .gitignore, bun.lock, dist after local build)
- `docs/PHASE2_CYCLE_LOG.md`

**Changed (router / config)**
- `config/services.php` — added `api-simple` on port **4101**
- `src/Supervisor/SupervisorPool.php` — pass local `$runner`/`$fingerprints`/`$readiness` (was `$this->*` undefined) — **T2**
- `src/Supervisor/ServiceSupervisor.php` — `buildMs` cast precedence fix — **T2**
- `src/Http/HttpProxy.php` — remove deprecated `curl_close` — response hygiene / **T2**

**Not created**
- `fixtures/phase2/api-with-lib/`
- `fixtures/phase2/api-private-env/`
- No hooks for phase2 services
- No Docker compose env for `JIT_DATABASE_URL`

### Mocks that exist
| Mock | Path | Port | Status |
|------|------|------|--------|
| api-simple | `fixtures/phase2/api-simple` | 4101 | exists; local bun build/start OK; registered |
| api-with-lib | — | 4102 (planned) | **missing** |
| api-private-env | — | 4103 (planned) | **missing** |
| fixture (pre-existing) | `fixtures/bun-service` | 4100 | unchanged |

### Services registered in `config/services.php`
- `fixture` (4100) — pre-existing
- `api-simple` (4101) — added this session
- **Not registered:** `api-with-lib`, `api-private` / `api-private-env`

### Matrix evidence (T1–T12)

| Test | api-simple | api-with-lib | api-private | Evidence notes |
|------|------------|--------------|-------------|----------------|
| T1 | pending | — | — | not run |
| T2 | **pass** | — | — | host `php -S :8090`; cold JSON 200 + `X-Dev-Cold-Start:1` |
| T3 | **pass** | — | — | host gateway warm: `X-Dev-Cold-Start: 0`, no `X-Dev-Build-Ms` (cycle 6) |
| T4–T12 | pending | — | — | not run |

Unit tests: `JIT_USE_DOCKER=0 ./bin/dev test` → **15 passed** after pool/buildMs fixes. Full `./bin/dev check` **not** run.

### Blockers
1. **Docker Desktop bind-mount** fails for workspace path with spaces (`Experiment - php wrapper` → `operation not permitted`). One attempt; nginx `:8080` matrix path blocked unless symlink/worktree without spaces.
2. ~~**Supervisor process affinity**~~ — **fixed cycle 6** via `JIT_STATE_DIR/.../runtime.json` PID+fingerprint rehydrate.
3. Orchestration **STOP** — remaining mocks/matrix deferred to focused workers.

### Exact next recommended work packages (for parent)
1. ~~**WP-supervisor-persist**~~ — **done** (cycle 6).
2. **WP-mock-B / WP-mock-C:** owned elsewhere if still incomplete.
3. **WP-matrix-api-simple:** Run T1,T4–T12 against api-simple via host gateway (or Docker via path-without-spaces mount).
4. **WP-docker-path:** Symlink or clone workspace to path without spaces; retry `docker compose -f docker/compose.yml up` for nginx `:8080` checks.

```text
Phase 2 overnight summary (STOPPED early; persist WP resumed)
- Mocks: api-simple + (cycle 5) B/C registered
- Router: SupervisorPool, ServiceSupervisor (persist), HttpProxy (curl_close), ProcessRunner stop-by-pid
- Matrix: T2+T3×api-simple green; rest pending
- Full check: not run (lint+test ok host)
- Blockers: Docker path spaces
- Next: matrix T1/T4–T12
```

---

## Cycle 6 — WP-supervisor-persist (router worker)

**Goal:** Warm second request reuses running child (`X-Dev-Cold-Start: 0`, no rebuild).

**Root cause:** Each gateway request constructs a new `Application`/`SupervisorPool`/`ServiceSupervisor`. Child PID + fingerprint lived only in memory (`$this->process`), so `needsRebuild` was always true when `$this->process === null`. `stateDir` had locks/logs only — no runtime metadata. (Child process itself survives PHP request GC on 8.5; adopt was the missing piece.)

**Change:**
- `src/Supervisor/ServiceSupervisor.php` — write/read `$JIT_STATE_DIR/{service}/runtime.json` `{pid,fingerprint}`; rehydrate + readiness short-circuit; rebuild only when fingerprint changes; clear on stop.
- `src/Process/ProcessRunner.php` — `stop()` via `posix_kill` when no `proc_open` resource (adopted PID).
- Prior router cleanup kept: `HttpProxy` omits deprecated `curl_close`; SupervisorPool ctor + buildMs cast.

**Verify (host, `JIT_USE_DOCKER=0`, Docker skipped — path spaces):**
- `JIT_STATE_DIR=$PWD/.jit-state php -S 127.0.0.1:8090 -t public`
- Cold: `X-Dev-Cold-Start: 1`, `X-Dev-Build-Ms: 102`, body `{"pong":true,"service":"api-simple"}`, runtime.json written
- Warm: `X-Dev-Cold-Start: 0`, no `X-Dev-Build-Ms`, `X-Dev-Proxy-Ms: 0`
- `./bin/dev test` → 15 passed; `./bin/dev lint` → ok; full `check`/smoke not run this cycle

**Pass/fail delta:** T3 api-simple = **pass**.

- api-simple T1–T12 evidence: see `docs/PHASE2_EVIDENCE_api-simple.md` (branch `phase2/api-simple-matrix`).
