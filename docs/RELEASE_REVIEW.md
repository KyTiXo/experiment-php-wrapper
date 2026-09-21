# JitPit Router release review

**Scope:** public-GitHub readiness review and release-validation record. License and repository visibility remain owner decisions.

## Snapshot

- Branch: `master`, aligned with `origin/master` at `0896d5f` (`Fix Bugbot findings: stop adopted child before respawn; allow IPv6 Origin.`).
- Release artifacts: `README.md`, `docs/JITPIT_PROMPT.md`, this review, and `.github/workflows/check.yml`.
- Remote: private `KyTiXo/experiment-php-wrapper`; description is “JIT PHP service router experiment”; no homepage, topics, license, or GitHub Actions workflow.
- Local tools: Docker Engine 27.4.0 / Compose 2.31.0, PHP 8.5.8, and Bun 1.3.12.
- Release validation passed from a space-free detached worktree on 2026-09-21 after fixing container networking: Compose build/up, nginx → PHP-FPM → `api-simple`, diagnostics, and `JIT_USE_DOCKER=1 ./bin/dev check`.

## What it does

JitPit Router (branded **PHit Router**) is a Composer-free PHP gateway for local development. A request to `/api/services/{name}/…` selects a configured local child service, fingerprints its watched files while honouring `.gitignore`, then builds, starts, waits for readiness, and reverse-proxies to its localhost port. It persists child PID/fingerprint state across PHP requests and exposes diagnostic state. If a changed service fails to rebuild, the last-good child remains available in `degraded` state with `X-Dev-Stale: 1`.

Shipped behavior is request-driven build/start/readiness/proxying, content-based fingerprinting, private-host-env projection, persistent PID reattachment, HTTP or TCP readiness, and Phase 2 Bun fixture coverage. The future per-service git-HEAD/dirty-tree choice of a lighter dev runtime is not a shipped toggle. Neither are real Nest applications, process-group termination, or a production runtime.

## README and portfolio assessment

The README is unusually good for an experimental tool: it states the user problem, accurately separates the current router from the north star, gives runnable-looking commands, exposes configuration shape, and repeatedly limits the claim to local/dev experimentation. Its jokes are part of the project voice and should stay.

Only material risks need attention:

- **“Docker (recommended)” is supported by release validation.** Compose and the Docker-backed full check passed from a space-free worktree. The validation exposed and fixed nginx container binding and PHP-FPM socket-permission defects.
- **The fixtures are not real Nest services.** This is already disclosed twice and should remain prominent. Do not rephrase it into a benchmark or a “Nest-compatible” claim.
- **The PHP 8.5/JIT line is safely framed as an alternate timeline.** It is a joke plus an explicit non-benchmark disclaimer, not a release blocker. Do not remove it.
- **The repository name and metadata do not yet match the public-facing name.** “experiment-php-wrapper” and the current generic description make the public portfolio signal weaker than the README. A rename is optional and outside a minimal release; a precise description and topics are sufficient.
- **Historical docs contain an absolute local path and detailed agent/session notes.** They are useful evidence but not polished public documentation. They reveal a local username/path, not a credential. Retain evidence unless intentionally pruning it, but add a short public-facing verification/status pointer rather than presenting the cycle log as primary documentation.

## Exact release gaps

| Area | Current state | Release gap |
| --- | --- | --- |
| Docker | Compose build/up, nginx → PHP-FPM → `api-simple`, diagnostics, teardown, and the Docker-backed full check pass. | Keep the Docker path covered by CI. |
| CI | `.github/workflows/check.yml` provisions Bun 1.3.12 and runs the validated Docker-backed command. | Confirm the first GitHub-hosted run after push. |
| License | MIT license approved and added. | Confirm GitHub detects it after publication. |
| GitHub metadata | Remote is private, generic description, empty homepage/topics; issues and projects enabled. | Before changing visibility, set a concise accurate description, e.g. “Experimental PHP gateway that lazily builds and proxies local Bun services,” plus relevant topics (`php`, `bun`, `reverse-proxy`, `local-development`, `experimental`). Decide whether a homepage is warranted; leave blank if not. Public visibility itself is a manual approval gate. |
| README/docs | New README and agent brief are untracked but intentional. README links the agent brief; Phase 2 evidence exists. No concise public release/status note or contribution/security policy. | Commit the two intentional artifacts with the release review. Keep README humour. Add only a brief validation/status note if Docker results require it. `CONTRIBUTING`/`SECURITY` are nice-to-have, not minimal-release blockers for this personal experiment. |
| Runtime boundaries | Linux-only, local/dev, no real Nest, and experimental status are stated. Single-PID stop and content-only fingerprint limitations remain. | Preserve these limits in the README. Do not add Phase 3 features merely to make the release look bigger. |

## Proposed minimal changes after approval

| File or setting | Proposed action | Why |
| --- | --- | --- |
| `README.md` | Add as-is, preserving voice. Change only the Docker wording if the spaceless-path validation fails, and optionally add a one-line validation/status link. | It is the public landing page; its framing is already honest. |
| `docs/JITPIT_PROMPT.md` | Add as-is. | Gives portfolio/agent context while clearly distinguishing roadmap from shipped behavior. |
| `docs/RELEASE_REVIEW.md` | Add this review. | Records release claims, gaps, approval gates, and the validation basis. |
| `.github/workflows/check.yml` | Add the proven check with Bun 1.3.12; use no Node package manager. | Reproduces the release gate on GitHub-hosted Linux. |
| `docker/Dockerfile` | Consider removing the unused Node.js installation only if the validated build confirms Bun alone is sufficient. | Aligns the image with the Bun-only policy and reduces image supply surface; defer if it risks the minimal release. |
| GitHub repository settings | After a separate explicit go-ahead: update description/topics, choose visibility, and add a license only after the license is selected. | These are external, potentially consequential release mutations. |

No rename, Phase 3 runtime work, real Nest app, process-group work, HEAD-aware mode, config redesign, or broad documentation rewrite is proposed.

## Verification performed

1. Created a disposable detached Git worktree at a path without spaces.
2. Ran Compose config/build/up; confirmed `api-simple` health and diagnostics through nginx; tore down containers and volumes.
3. Ran `JIT_USE_DOCKER=1 ./bin/dev check`: PHP syntax/style, shell syntax, Compose config, 15 tests, and Bun smoke lifecycle passed.
4. Added the same command to GitHub Actions with Bun 1.3.12.
5. Rechecked whitespace, links, and repository status before release.

## Known limitations to retain publicly

- Local/dev only; not production, Windows, systemd, Kubernetes, or pm2.
- Phase 2 services are Bun mock monorepos, not real `@nestjs/cli` applications.
- Fingerprints are content-based; a timestamp-only touch does not trigger a rebuild.
- The desired HEAD-aware dev runtime is roadmap only.
- Single-PID termination does not yet guarantee child-process-tree cleanup.
- SSE boot-page behavior is limited under single-threaded `php -S`; Compose/nginx is the intended path to validate.

## Ready to commit?

- [x] README and agent brief reviewed; humour and experimental framing retained.
- [x] Docker validation passes from a no-spaces temporary/worktree path.
- [x] `JIT_USE_DOCKER=1 ./bin/dev check` passes; repository lint included.
- [x] CI added only after the Linux command was demonstrated reliable.
- [x] MIT license explicitly approved and added.
- [x] Public release requested; description/topics/visibility ready to update.
- [x] Exact diff reviewed after validation.
- [x] Explicit authorization received to finish and publish the release.
