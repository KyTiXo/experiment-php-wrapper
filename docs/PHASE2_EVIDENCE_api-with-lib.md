# Phase 2 evidence — `api-with-lib`

| Field | Value |
|-------|-------|
| Service | `api-with-lib` |
| Port | **4102** |
| Gateway | `http://127.0.0.1:8091` (host `php -S`, Docker skipped — path spaces) |
| State dir | `.jit-state-lib` |
| Branch | `phase2/api-with-lib-matrix` |
| Fixture | `fixtures/phase2/api-with-lib` (app + `packages/shared-lib`) |
| Isolation | Only :8091 / :4102 touched; :4101 / :4103 clear |

## Matrix

| ID | Result | Notes |
|----|--------|-------|
| T1 | **pass** | Cold `Accept: text/html` → **202** boot page (`X-Dev-Cold-Start: 1`); reload → **200** upstream health JSON; boot HTML references `/__dev/sse?service=api-with-lib` |
| T2 | **pass** | Cold JSON → **200** `{"service":"api-with-lib","lib":"shared-v1"}`; `X-Dev-Cold-Start: 1`, `X-Dev-Build-Ms` set |
| T3 | **pass** | Warm → **200**; `X-Dev-Cold-Start: 0`; empty `X-Dev-Build` (no rebuild) |
| T4 | **pass** | Content edit under `packages/shared-lib` → rebuild (`X-Dev-Build-Ms: 41`, body `lib: shared-v1-dirty`). Pure `touch` alone does **not** dirty (content-hash fingerprint by design) |
| T5 | **pass** | Broken `build` script + dirty → still **200** last-good; `X-Dev-State: degraded`, `X-Dev-Stale: 1`, `X-Dev-Dirty: 1` |
| T6 | **pass** | Stop + wipe state + broken first build → **502**; `X-Dev-State: failed` |
| T7 | **pass** | POST `/echo?foo=bar&foo=baz` preserves method, query, body, duplicate `X-Custom-A: one, two` |
| T8 | **N/A** | No `service.api-with-lib.*` hook registered in `config/hooks.php` |
| T9 | **pass** | `POST .../stop` + `X-Dev-Action: 1` → 200; missing header → **403**; `POST .../rebuild` → 200. Required fix: `isLocalRequest()` allow any port on loopback (was hardcoded `:8080` only) |
| T10 | **pass** | `GET /__dev/services/api-with-lib` JSON has `envPublic.PORT=4102` only; no private/secret strings |
| T11 | **pass** | `readyPath=/health`; proxied `/health` → 200 `{ok:true}` |
| T12 | **N/A** | `readyPath` set — TCP-only readiness not applicable |

## Curl excerpts

### T1 cold HTML (202)

```text
HTTP/1.1 202 Accepted
Content-Type: text/html; charset=UTF-8
X-Dev-Service: api-with-lib
X-Dev-State: ready
X-Dev-Cold-Start: 1
X-Dev-Build-Ms: 43
```

Reload: `HTTP/1.1 200` + `{"ok":true,"service":"api-with-lib"}` + `X-Dev-Cold-Start: 0`.

### T2 / T3

```text
T2: X-Dev-Cold-Start: 1  X-Dev-Build-Ms: 44  body={"service":"api-with-lib","lib":"shared-v1"}
T3: X-Dev-Cold-Start: 0  X-Dev-Build: (empty)  X-Dev-Proxy-Ms: 0
```

### T4 shared-lib dirty (content)

```text
# after editing packages/shared-lib/index.ts tag → shared-v1-dirty
X-Dev-Build: 1c36d19b7e3d
X-Dev-Build-Ms: 41
{"service":"api-with-lib","lib":"shared-v1-dirty"}
```

`touch` only → no rebuild (`X-Dev-Dirty: 0`, empty `X-Dev-Build`) — expected for `hash_file` fingerprint.

### T5 / T6

```text
T5: HTTP/1.1 200  X-Dev-State: degraded  X-Dev-Stale: 1  X-Dev-Dirty: 1
T6: HTTP/1.1 502  X-Dev-State: failed  X-Dev-Cold-Start: 1  body=Service unavailable
```

### T7 echo

```json
{
  "method": "POST",
  "path": "/echo",
  "query": "?foo=bar&foo=baz",
  "headers": { "x-custom-a": "one, two", "...": "..." },
  "body": "{\"hello\":\"world\"}"
}
```

### T9 / T10

```text
POST /__dev/services/api-with-lib/stop + X-Dev-Action:1 → 200 {"ok":true,"action":"stop"}
POST /__dev/services/api-with-lib/rebuild (no header) → 403
POST /__dev/services/api-with-lib/rebuild + X-Dev-Action:1 → 200
GET  /__dev/services/api-with-lib → envPublic only; SECRET_HITS none
```

## Router fixes (evidence-linked)

| Change | Test ID |
|--------|---------|
| `Application::isLocalRequest()` — treat `127.0.0.1:<any-port>` / `localhost:<any-port>` as local | **T9** (stop/rebuild were 403 on `:8091`) |
| Supervisor persist / process runner / pool adopt (from main WIP) | cold restart stability under `php -S` |

## Verification

```text
JIT_USE_DOCKER=0 ./bin/dev test  → 15 passed, 0 failed
```

Docker / nginx **skipped** (workspace path contains spaces).

## Cleanup notes

- Gateway process: `php -S 127.0.0.1:8091` (may still be running in agent terminal)
- Child: bun on **4102**
- Merge-back: `/apply-worktree` · cleanup: `/delete-worktree`
