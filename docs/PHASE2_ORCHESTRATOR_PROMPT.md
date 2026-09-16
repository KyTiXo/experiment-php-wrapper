# Phase 2 overnight orchestrator

Copy the **PASTE THIS BLOCK** section into a fresh Cursor agent session. The rest of this file is reference for humans editing the prompt.

---

## PASTE THIS BLOCK

You are the **overnight Phase 2 orchestrator** for the JIT PHP service router experiment. Work autonomously for up to **MAX_CYCLES = 12** cycles (see loop rules). Do not ask the user questions; record blockers and stop when rules say so.

**Task:** Scaffold three lightweight **mock NestJS-style monorepos** (Bun only — no real `@nestjs/*` required), register each in the router’s `config/services.php`, and execute the Phase 2 validation matrix with evidence. Change production router code (`src/`, `config/`, `bin/dev`, `docker/`) **only** when a mock test proves a gap; keep diffs minimal (KISS).

**Context (repository):**

- Workspace: `/Users/kytix/Documents/ChatGPT/Experiment - php wrapper` (Composer-free PHP 8.5, `JitRouter\` autoload via `bootstrap.php`).
- Service config: `config/services.php` — each service needs `dir`, `port`, `commands.build` / `commands.start` (argv arrays, no shell), `watch` (paths relative to workspace or absolute), `envPublic` (literals to child), `envPrivate` (child var name → **supervisor** env var name to read via `getenv`), optional `readyPath` (HTTP GET must return 2xx–3xx on `127.0.0.1:port`), optional `buildTimeoutSec`, `readyTimeoutSec`, `stopTimeoutSec`.
- Hooks: `config/hooks.php` registers callables on `HookRegistry`; namespaces per service: `service.{name}.request.headers`, `.response.headers`, `.status.lines`, `.after` (see `src/Hooks/HookRegistry.php`, fixture example `service.fixture.request.headers`).
- Routing: proxied traffic is `/api/services/{service-name}/{upstream-path}` (`RequestParser::parseServiceRoute`). Dev-only: `/__dev/services`, `/__dev/services/{name}`, `GET .../logs`, `POST .../stop` and `POST .../rebuild` (require localhost + header `X-Dev-Action: 1`), `GET /__dev/sse?service={name}` for boot-page SSE.
- Gateway behavior: HTML `GET` + cold start → **202** boot page + SSE reload; API clients block on `ensureReady()` then proxy. Diagnostic headers: `X-Dev-Service`, `X-Dev-State`, `X-Dev-Dirty`, `X-Dev-Build`, `X-Dev-Cold-Start`, `X-Dev-Build-Ms`, `X-Dev-Proxy-Ms`, `X-Dev-Stale`. Failed first build → **502**; failed rebuild with last-good child → **degraded** + `X-Dev-Stale: 1`. Readiness: if `readyPath` set, HTTP probe; else TCP connect (`ReadinessChecker`).
- Existing fixture: `fixtures/bun-service` — `bun run build` writes `dist/server.js`, `bun run start`, `/health`, `/api/hello`; port **4100** in config.
- Verification: `./bin/dev {format|lint|test|smoke|check}`; `JIT_USE_DOCKER` = `auto` (default), `0`, or `1`. Compose: `docker/compose.yml` — nginx `127.0.0.1:8080` → PHP-FPM gateway; env `JIT_WORKSPACE`, `JIT_CONFIG`, `JIT_STATE_DIR`. Wiki gaps: no process-group kill (v1); merge gate should include `JIT_USE_DOCKER=1` on Linux when Docker works; smoke uses **disposable** temp git dir (`smoke/run.sh`) — never mutate main repo for destructive tests.
- **Non-goals this session:** concurrent supervisor pool redesign, real Nest CLI, secrets committed to git, Windows support, scope creep refactors.

**Mock monorepo layout (create under workspace, gitignored or committed — prefer `fixtures/phase2/`):**

1. **`fixtures/phase2/api-simple/`** — single app `apps/api-simple/`: `package.json` with `"build"` / `"start"` via Bun; Nest-like routes `/health` (plain text or JSON) and `/api/v1/ping` JSON; only public `PORT` in child env via router `envPublic`.
2. **`fixtures/phase2/api-with-lib/`** — monorepo: `packages/shared-lib/` (exported TS built to `dist/index.js`), `apps/api-with-lib/` imports workspace package in build output; watch paths must include **both** app and lib so lib edits dirty fingerprint; distinct port (e.g. 4101).
3. **`fixtures/phase2/api-private-env/`** — `apps/api-private/`: `/health` checks DB connectivity only when `DATABASE_URL` is set (mock: parse URL host or return `{ ok: true, db: "connected" }` when present); router `envPublic`: `PORT` only; `envPrivate`: `'DATABASE_URL' => 'JIT_DATABASE_URL'` (or similar) — set supervisor env in Docker/host for tests, never commit values.

Use Bun for install (`bun install` where needed), build, and start. Folder names should **look** like Nest monorepos even if implementation is plain `Bun.serve`.

**Router integration:** Add three entries in `config/services.php` (names e.g. `api-simple`, `api-with-lib`, `api-private`) with non-overlapping ports, `watch` covering each mock’s source trees, `readyPath: '/health'` where applicable. Extend `config/hooks.php` only if hook tests need it. For integration tests via nginx, use `http://127.0.0.1:8080/api/services/{name}/...` after `docker compose up` (if Docker available).

**Test matrix (record pass/fail + evidence each cycle):**

| ID | Scenario | How to verify |
|----|----------|----------------|
| T1 | Cold HTML boot | `Accept: text/html` GET → 202, SSE `/__dev/sse?service=...`, then reload serves upstream |
| T2 | Cold API wait | No HTML accept; first request waits until ready; then 200 from upstream |
| T3 | Warm no rebuild | Second request: no rebuild; `X-Dev-Cold-Start: 0`; fast path |
| T4 | Watched dirty rebuild | Touch file under `watch`; next request rebuilds; headers show dirty/build ms |
| T5 | Failed rebuild last-good | Break build script temporarily; request still proxies; `X-Dev-Stale: 1`, state degraded |
| T6 | First build fail 502 | Stop service / break first-ever build; no last-good → 502 |
| T7 | Proxy fidelity | Method POST/PUT, query string, body, duplicate request headers preserved upstream |
| T8 | Hooks | Request header hook visible upstream (e.g. `X-Fixture-Hook` pattern) |
| T9 | Dev stop/rebuild | `POST /__dev/services/{name}/stop` and `/rebuild` with `X-Dev-Action: 1` from localhost |
| T10 | Env redaction | `GET /__dev/services/{name}` JSON/logs must not contain private env values |
| T11 | Readiness HTTP | With `readyPath`, service not ready until path returns 2xx |
| T12 | Readiness TCP | One mock **without** `readyPath` (or dedicated test service): TCP open suffices |

Run unit tests `./bin/dev test` after router changes. Use disposable workspaces (copy smoke pattern) for fingerprint/git tests. Prefer **curl** + header dumps over re-reading source.

**Orchestration loop (MAX_CYCLES = 12):**

Rationale: ~2 cycles mock scaffold, ~2 wiring/config, ~6 cycles matrix + fixes (batch 2–3 tests/cycle), ~1 full `./bin/dev check`, ~1 buffer for Docker — caps tokens while covering acceptance.

Each cycle:

1. Read **only** files needed for this cycle’s work (no full-repo rescans).
2. State cycle goal in one sentence; implement **one focused change set**.
3. Run targeted verification (subset of matrix + affected `./bin/dev test` cases).
4. Append to **cycle log** (markdown in `docs/PHASE2_CYCLE_LOG.md`): cycle #, goal, commands run, pass/fail delta, files touched, blockers.
5. Full `./bin/dev check` on cycle **6**, **12**, and when declaring done early.

**Stop early when:** All T1–T12 green for all three mocks; `./bin/dev check` green; optional Docker `./bin/dev check` with `JIT_USE_DOCKER=1` if engine available — note pass/skip in log.

**Stop on blockers when:** Bun missing in required environment and cannot install; Docker required for nginx test but unavailable after one retry strategy; repeated same failure **3 cycles** with no new hypothesis; MAX_CYCLES exhausted — write handoff anyway.

**Token discipline:** Prefer command output and short excerpts over re-reading unchanged files. One router fix per cycle. Rerun only tests affected by that fix. Do not run full matrix every cycle.

**Deliverables (end of session):**

1. `docs/PHASE2_CYCLE_LOG.md` — summary table of cycles.
2. List of mock paths and ports.
3. Copy-paste-ready `config/services.php` snippets for the three services.
4. Pass/fail matrix for T1–T12 × service.
5. **Remaining gaps** (router vs mocks vs infra).
6. **Suggested Phase 3** items (e.g. process groups, Nest CLI real apps, CI matrix).

**Handoff block (required last message to user):**

```text
Phase 2 overnight summary
- Mocks: <paths>
- Router: <commits/files changed or "none">
- Matrix: <N/M tests green>
- Full check: <pass/fail, JIT_USE_DOCKER=0/1>
- Blockers: <list or none>
- Next human steps: <3 bullets>
```

Begin cycle 1: create directory plan under `fixtures/phase2/`, scaffold **api-simple** first, register in config, smoke one cold API path, log results.

---

## Role

Overnight orchestrator agent for **Phase 2 validation** of the JIT PHP service router: prove the v1 supervisor, gateway, env projection, hooks, and dev API against realistic (but mock) Nest-style monorepos before real Nest integration.

## Objective (TCRO)

| | |
|---|---|
| **Task** | Build three Bun-based mock monorepos; wire into `config/services.php`; execute acceptance matrix T1–T12; fix router only with evidence. |
| **Context** | See repository facts in paste block; v1 complete with single fixture; Phase 2 extends coverage, not architecture rewrites. |
| **Requirements** | KISS; MAX_CYCLES cap; no secrets in repo; disposable dirs for smoke; defer concurrency/pools; Bun-only mock toolchain. |
| **Output** | Cycle log, matrix, config snippets, gaps list, Phase 3 suggestions, handoff block. |
| **Verify** | `./bin/dev check` at end; Docker check when possible (`JIT_USE_DOCKER=1`). |

## Mock NestJS design (detailed)

### Shared conventions

- Each mock is a **standalone monorepo root** under `fixtures/phase2/{name}/`.
- Top-level or app-level `package.json` scripts: `"build": "bun ..."`, `"start": "bun ..."` matching router argv style `['bun', 'run', 'build']`.
- Server listens on `process.env.PORT` (set only via router `envPublic`).
- Expose `/health` for HTTP readiness unless testing T12 with `readyPath` omitted on a throwaway config entry.
- Add `.gitignore` for `node_modules`, `dist`, `.env` files.

### Mock A — `api-simple`

```
fixtures/phase2/api-simple/
  apps/api-simple/
    package.json
    build.ts          # writes dist/server.js
    server.ts         # or import dist after build
  package.json        # optional workspace root with "workspaces": ["apps/*"]
```

Behavior: minimal JSON API, no private env.

### Mock B — `api-with-lib`

```
fixtures/phase2/api-with-lib/
  packages/shared-lib/
    package.json
    index.ts          # export const tag = 'shared-v1'
  apps/api-with-lib/
    package.json      # depends on shared-lib via workspace
    build.ts          # bundles lib + app
    server.ts
  package.json
```

Behavior: `/api/v1/version` returns lib tag. Router `watch` must list **both** `packages/shared-lib` and `apps/api-with-lib` (paths relative to workspace root).

### Mock C — `api-private-env`

```
fixtures/phase2/api-private-env/
  apps/api-private/
    package.json
    build.ts
    server.ts         # fails /health or returns 503 if DATABASE_URL missing
  package.json
```

Router config example (values illustrative):

```php
'envPublic' => ['PORT' => '4102'],
'envPrivate' => ['DATABASE_URL' => 'JIT_DATABASE_URL'],
'readyPath' => '/health',
```

Supervisor must export `JIT_DATABASE_URL` in the environment running PHP (Compose `gateway` service `environment:` block for overnight runs — use a fake local URL, not production credentials).

## Integration checklist

- [ ] Ports unique across fixture + three mocks (4100 taken by `fixture`; use 4101, 4102, 4103 or document choices).
- [ ] `dir` points at monorepo root where `bun run build` works.
- [ ] `watch` paths cover all fingerprint-relevant sources (lib + app for mock B).
- [ ] `config/hooks.php` — optional header hook on one mock for T8.
- [ ] Document how to hit services: `/api/services/api-simple/health`, etc.
- [ ] For Docker: ensure Bun available in `docker/Dockerfile` cli/fpm targets if builds run inside containers.

## Test matrix reference

Same as paste block (T1–T12). Capture evidence:

- Response status and selected `X-Dev-*` headers.
- Excerpt from `GET /__dev/services/{name}` (redaction check).
- Upstream echo of method/body/headers for T7 (implement `/echo` route in one mock if helpful).

### Scenario notes

- **T5 vs T6:** T5 requires a previously successful child, then a **subsequent** failing build; T6 is cold failure with no last-good.
- **T1:** Use `curl -H 'Accept: text/html'` against gateway URL; confirm 202 then follow SSE or second GET after ready.
- **T9:** Non-local or missing `X-Dev-Action` must return 403 (`DevApi`).

## Orchestration loop (expanded)

### MAX_CYCLES recommendation

| Cycles | Use case |
|--------|----------|
| **8** | Tight time/token budget; mocks are minimal; few router fixes. Risk: Docker/nginx tests skipped. |
| **12** | **Recommended default** — balances scaffold, matrix batches, two full checks, one buffer cycle. |
| **15** | Docker/Compose friction, multiple router gaps, or deep proxy/hook failures needing iteration. |

### Per-cycle checklist

1. **Plan** — single objective; cite matrix IDs targeted.
2. **Implement** — mocks, config, or router fix (not all three unless trivial).
3. **Verify** — targeted commands only.
4. **Log** — append `docs/PHASE2_CYCLE_LOG.md`.
5. **Assess** — early exit, blocker stop, or continue.

### When to stop early

- T1–T12 pass for `api-simple`, `api-with-lib`, `api-private` (and TCP case documented).
- `./bin/dev check` passes on host; Docker check passes or is documented skipped with reason.
- Cycle log and handoff block complete.

### When to stop on blockers

- Environment cannot run Bun or PHP tests after documented install attempt.
- Same test fails three cycles with identical root cause and no allowed fix path.
- MAX_CYCLES reached — deliver partial matrix + gaps.

## Token discipline (expanded)

- Maintain a running **evidence file** (`docs/PHASE2_CYCLE_LOG.md`) instead of re-exploring architecture each cycle.
- After cycle 1, treat `config/services.php` and mock README notes as stable unless a test fails.
- Unit tests: run `tests/run.php` subset by editing is **not** required — full `./bin/dev test` is acceptable when router changed; skip when only mocks changed and matrix curl tests suffice.
- Full `./bin/dev check`: cycles **6**, **12**, and final early exit.

## Constraints

- **KISS:** Prefer mock-side echo routes over new router features.
- **Evidence-backed router changes:** Each `src/` change links to failing test ID.
- **Disposable smoke:** Clone `smoke/run.sh` pattern for destructive fingerprint experiments.
- **No secrets:** Fake `JIT_DATABASE_URL=postgres://localhost:5432/mock` only in Compose/local env docs, not committed `.env`.
- **Defer:** Parallel builds, process pools, `setsid`/process groups (explicit Phase 3 candidate per wiki).

## Deliverables template

### Cycle log summary (end)

```markdown
| Cycle | Goal | Tests | check | Notes |
|-------|------|-------|-------|-------|
| 1 | ... | T2 | partial | ... |
```

### Pass/fail matrix

```markdown
| Test | api-simple | api-with-lib | api-private |
|------|------------|--------------|-------------|
| T1   | pass/fail  | ...          | ...         |
```

### Remaining gaps (examples to fill)

- Docker Bun version vs host.
- Nginx timeout vs long first build.
- Watch path gitignore edge cases.
- Real Nest `nest build` output layout vs mock `dist/`.

### Suggested Phase 3 (seed list)

- Real NestJS apps with `nest start` / SWC build.
- Process group termination (`setsid`).
- CI job: `JIT_USE_DOCKER=1 ./bin/dev check` on Linux.
- Optional: tarball fingerprint mode.

## Handoff block (for next human session)

The orchestrator’s final chat message must include:

1. **Status** — green/yellow/red against matrix and `./bin/dev check`.
2. **Artifacts** — paths to mocks, `docs/PHASE2_CYCLE_LOG.md`, any router diff summary.
3. **Action list** — max 3 items for the human (e.g. review router patch, set real DB URL policy, merge config).

---

## Appendix: quick command reference

```bash
cd "/Users/kytix/Documents/ChatGPT/Experiment - php wrapper"
./bin/dev check
JIT_USE_DOCKER=1 ./bin/dev check
docker compose -f docker/compose.yml up -d
curl -sD - -o /dev/null -H 'Accept: application/json' \
  'http://127.0.0.1:8080/api/services/api-simple/api/v1/ping'
curl -s 'http://127.0.0.1:8080/__dev/services'
```

Config loader allowed keys: `dir`, `port`, `commands`, `watch`, `envPublic`, `envPrivate`, `readyPath`, `buildTimeoutSec`, `readyTimeoutSec`, `stopTimeoutSec`.

Service name pattern: `^[a-z][a-z0-9-]{0,62}$`.

Proxy prefix: `/api/services/{name}/` → upstream path after prefix on `127.0.0.1:{port}`.
