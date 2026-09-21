# JitPit Router — agent brief

Paste as system/context when discussing, extending, or reviewing this repo.

---

## Task

Explain, implement, or review **JitPit Router** (branded **PHit Router**): a Composer-free PHP 8 gateway that lazily builds, starts, and reverse-proxies local child services behind `/api/services/{name}/…`.

---

## Context

- **Problem:** Local dev with many small microservices (often Bun/Node argv children) burns RAM and attention when every service stays in heavy build mode or dev watch mode “just in case.” Mode-switching across repos does not scale on a laptop.
- **What exists today:** `public/index.php` → [`Gateway`](../src/Http/Gateway.php) → [`ServiceSupervisor`](../src/Supervisor/ServiceSupervisor.php). On request: [`FingerprintCalculator`](../src/Fingerprint/FingerprintCalculator.php) hashes watched files (`.gitignore`-aware), compares to last run, then **build → start → readiness → proxy** if dirty. Failed rebuild keeps last-good process (`degraded`, `X-Dev-Stale: 1`). Config: [`config/services.php`](../config/services.php). Phase 2 Bun fixtures under `fixtures/phase2/`.
- **Direction (not fully implemented):** Per-service **HEAD / dirty-tree–aware dev runtime** when sources change—swap one repo to lighter dev mode while others stay cold or on build/start until touched. Fingerprinting today is **file-content based**, not git-HEAD based; do not claim HEAD detection ships unless code adds it.
- **Namespace:** `JitRouter\` (unchanged). Marketing name: JitPit / PHit Router.

---

## Requirements

When writing docs or code for this project:

1. **Honesty:** Distinguish shipped behavior (fingerprint rebuild, `ensureReady`, last-good degraded) from roadmap (HEAD-triggered dev runtime).
2. **Scope:** Local/dev experimental tool. **Not** production, **not** Windows, **not** a replacement for k8s/systemd/pm2. No Composer dependency.
3. **Platform:** Linux-first; Docker compose in `docker/compose.yml`; host fallback `php -S` with `JIT_STATE_DIR`.
4. **Verification:** `./bin/dev check` (lint + test + smoke). Wiki: [wiki/php-8.5/Best_Practices.md](../wiki/php-8.5/Best_Practices.md).
5. **Tone:** Humble, resume-safe, dry humor about microservice sprawl—no mean-spirited language about teams or languages.
6. **Config shape:** Service registry keys: `dir`, `port`, `commands.build`, `commands.start`, `watch`, `envPublic`, `envPrivate`, optional `readyPath`.

---

## Output

When asked to summarize the project for a portfolio or README:

- **One-liner:** PHP gateway that wakes services on demand, fingerprints sources, proxies to localhost ports, and degrades gracefully on failed rebuilds.
- **Pun/tagline:** PHit Router — low-memory overhead concept for hauling many local containers without running all of them in build mode at once.
- **Differentiator:** Request-driven supervision + fingerprinting; optional future: git-aware dev runtime per service to save RAM across a fleet.

When implementing features, prefer minimal diffs in `src/`, extend `config/services.php` only for real fixtures, and add tests via existing `./bin/dev test` patterns.
