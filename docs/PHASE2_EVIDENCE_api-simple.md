# Phase 2 evidence — api-simple only

**Branch:** `phase2/api-simple-matrix`  
**Gateway:** `http://127.0.0.1:8090` (`php -S`, `JIT_USE_DOCKER=0`)  
**State dir:** `.jit-state-simple`  
**Service port:** `4101`  
**Date:** 2026-09-16  

## Pass/fail matrix (api-simple)

| ID | Result | Notes |
|----|--------|-------|
| T1 | **pass** | Cold HTML → `202` boot page + `X-Dev-Cold-Start: 1`; later reload `200` upstream. SSE curl alone returned 0 bytes under single-threaded `php -S` (cannot build+stream concurrently); boot page still embeds EventSource. |
| T2 | **pass** | Cold API → `200` after wait; `X-Dev-Cold-Start: 1`, `X-Dev-Build-Ms: 38`. |
| T3 | **pass** | Warm → `200`, `X-Dev-Cold-Start: 0`, empty `X-Dev-Build`; `runtime.json` present under `.jit-state-simple/api-simple/`. |
| T4 | **pass** | Touch `apps/api-simple/build.ts` → rebuild with `X-Dev-Build-Ms: 41` + new build id. Final `X-Dev-Dirty: 0` expected (cleared after successful rebuild in `ensureReadyLocked`). |
| T5 | **pass** | Broken build after warm → still `200` upstream, `X-Dev-State: degraded`, `X-Dev-Stale: 1`, `X-Dev-Dirty: 1`. Build script restored after. |
| T6 | **pass** | Stop + clear state + broken first build → `502`, `X-Dev-State: failed`, `X-Dev-Cold-Start: 1`. Build script restored after. |
| T7 | **pass** | POST `/echo?foo=bar&foo=baz` body + headers echoed; method/query/body/`x-trace` preserved; duplicate `X-Custom-A` coalesced as `one, two`. |
| T8 | **pass** | Upstream sees `x-fixture-hook: 1` from `service.api-simple.request.headers`. |
| T9 | **pass** | After fix (below): missing `X-Dev-Action` → `403`; with header `stop` → `200` and port 4101 free; `rebuild` → `200` then ping `200`. |
| T10 | **pass** | `GET /__dev/services/api-simple` shows only `envPublic.PORT`; no private/secret leakage. Logs endpoint returns build/start tail only. |
| T11 | **pass** | `readyPath: /health` configured; cold T1/T2 only succeed once HTTP readiness passes. |
| T12 | **N/A** | api-simple has `readyPath`; TCP-only readiness left to a service without `readyPath` (e.g. api-private agent). |

**Score:** 11 pass + 1 N/A (T12) for api-simple.

## Router fix (T9)

**File:** `src/Application.php` — `isLocalRequest()`  
**Cause:** Host allowlist only included `:8080` / bare host, so `127.0.0.1:8090` was treated as non-local → always `403` on stop/rebuild.  
**Fix:** Compare hostname only (`localhost` / `127.0.0.1` / `[::1]`), any port.

Also brought forward from main workspace (uncommitted) for matrix to work under `php -S`:

- `src/Supervisor/ServiceSupervisor.php` — runtime.json persist/rehydrate (T3)
- `src/Process/ProcessRunner.php` — stop adopted PIDs
- `src/Supervisor/SupervisorPool.php` — constructor wiring
- `src/Http/HttpProxy.php` — curl_close cleanup
- `config/hooks.php` — `service.api-simple.request.headers`

## Curl evidence (excerpts)

### T1 cold HTML
```
HTTP/1.1 202 Accepted
X-Dev-Service: api-simple
X-Dev-Cold-Start: 1
Content-Type: text/html; charset=UTF-8
… boot page “Building service…” …
```
Reload after ready: `200`, `X-Dev-Cold-Start: 0`, upstream JSON.

### T2 / T3
```
T2: 200 X-Dev-Cold-Start:1 X-Dev-Build-Ms:38
T3: 200 X-Dev-Cold-Start:0 (no Build-Ms)
runtime.json: {"pid":…,"fingerprint":"b0812bd880623341714fc2550d09011a"}
```

### T4
```
200 X-Dev-Build:733305acd1cb X-Dev-Build-Ms:41 X-Dev-Cold-Start:0
```

### T5 / T6
```
T5: 200 X-Dev-State:degraded X-Dev-Stale:1 X-Dev-Dirty:1 body={"pong":true,…}
T6: 502 X-Dev-State:failed X-Dev-Cold-Start:1 body=Service unavailable
```

### T7 / T8
```
{"method":"POST","path":"/echo","query":"?foo=bar&foo=baz",
 "headers":{…,"x-custom-a":"one, two","x-fixture-hook":"1","x-trace":"abc"},
 "body":"{\"hello\":\"world\"}"}
```

### T9 (post-fix)
```
POST /stop without header → 403
POST /stop X-Dev-Action:1 → {"ok":true,"action":"stop"} ; 4101 free
POST /rebuild X-Dev-Action:1 → {"ok":true,"action":"rebuild"} ; ping 200
```

## Commands used

```bash
export JIT_USE_DOCKER=0
export JIT_WORKSPACE="$PWD"
export JIT_STATE_DIR="$PWD/.jit-state-simple"
export JIT_CONFIG="$PWD/config/services.php"
php -S 127.0.0.1:8090 -t public public/index.php
./bin/dev test   # after Application.php change — 15 passed
```

Raw capture: `docs/_evidence_raw_simple.txt` (local helper; optional).

## Blockers

- Docker/nginx path not exercised (path spaces / host `php -S` per orchestrator).
- SSE concurrent with cold build not verifiable on single-threaded `php -S`.
- T12 N/A for this service by design.
