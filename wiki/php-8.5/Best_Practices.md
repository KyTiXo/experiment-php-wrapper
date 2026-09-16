# PHP 8.5 Practices for the JIT Service Router

This page explains how we write PHP for the JIT Service Router experiment: Composer-free sources, Linux containers, strict typing, and [PSR-12](https://www.php-fig.org/psr/psr-12/) layout. It is written for engineers who already know PHP and want the *why* behind our conventions—not a tutorial on installing PHP.

Following [Diátaxis](https://diataxis.fr/), this document is an **explanation**: it connects architectural choices (JIT build, last-good fallback, redacted diagnostics) to concrete PHP 8.5 mechanisms. For step-by-step commands, use `./bin/dev` and the repository README; for API shapes, read the readonly classes in `src/Config` and `src/Runtime`.

## Scope and runtime assumptions

The router runs under PHP-FPM and CLI in Docker on Linux. We target [PHP 8.5](https://www.php.net/releases/8.5/en.php) features where they remove code, but we avoid novelty for its own sake. There is no Composer autoloading: a small `spl_autoload_register` bootstrap loads `JitRouter\` classes from `src/`. Dev-only tooling includes a pinned PHP-CS-Fixer PHAR (3.95.25), not a production dependency.

Windows parity is explicitly out of scope: process control, signal escalation, and `proc_open` semantics differ on Windows; do not assume this supervisor code runs unchanged outside Linux.

## Architecture: functional core, imperative shell

Domain logic—config parsing, fingerprint composition, env projection, lifecycle transitions—lives in pure or mostly pure units with explicit inputs and outputs. The imperative shell (`Application`, `ServiceSupervisor`, `ProcessRunner`) performs I/O: `proc_open`, `flock`, HTTP proxying, and log files. We introduce interfaces only when multiple implementations exist (for example, injectable hash or env closures in tests); otherwise concrete classes keep the experiment readable.

Native I/O stays behind narrow seams so tests can substitute closures without mocking entire extensions.

## Parse once at boundaries

Raw configuration arrays and environment lookups are converted exactly once into readonly value objects. Unknown keys fail fast; invalid ports, empty command arrays, and missing directories never reach the supervisor.

```php
final readonly class Service
{
    public function __construct(
        public string $name,
        public string $dir,
        public int $port,
        public Commands $commands,
        public array $watch,
        public array $envPublic,
        public array $envPrivate,
        public ?string $readyPath = null,
    ) {}
}
```

The loader throws path-aware `ConfigException` messages (`service.api.port must be 1-65535`) so operators fix config without reading stack traces. See [type declarations](https://www.php.net/manual/en/language.types.declarations.php) for why we prefer declared properties over dynamic arrays after the boundary.

## Type inference without noise

Public methods, config shapes, and serialization boundaries carry explicit parameter and return types. Private locals and obvious closures infer their types. PHPDoc appears only for non-JSON shapes such as `list<non-empty-string>` or `array<non-empty-string, non-empty-string>`.

Avoid `mixed` in domain code, ornamental generics, and redundant `@return` tags that duplicate native return types. [Readonly classes](https://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.class.readonly) (`readonly class Commands`) document immutability after construction.

## Lifecycle as data

Service runtime states are a backed enum with an exhaustive `match` for transitions and UI mapping:

```php
enum RuntimeState: string
{
    case Stopped = 'stopped';
    case Building = 'building';
    case Ready = 'ready';
    case Degraded = 'degraded';
    // ...

    public function isServing(): bool
    {
        return match ($this) {
            self::Ready, self::Degraded => true,
            default => false,
        };
    }
}
```

Diagnostics exposed to HTTP headers come from immutable snapshots (`ServiceSnapshot`), not live mutable structs—so concurrent requests see consistent state labels. See [backed enums](https://www.php.net/manual/en/language.enumerations.backed.php) and [`match`](https://www.php.net/manual/en/control-structures.match.php).

## Process ownership (build and long-lived children)

**Never** pass shell strings to `proc_open`. Always use argument arrays with absolute `cwd` and an explicit environment array merged from a controlled baseline—not the entire inherited FPM environment.

```php
$process = proc_open(
    ['bun', 'run', 'build'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $service->dir,
    $childEnv,
);
```

Build steps drain stdout and stderr concurrently with [`stream_select`](https://www.php.net/manual/en/function.stream-select.php) and monotonic [`hrtime`](https://www.php.net/manual/en/function.hrtime.php) deadlines—not wall-clock `time()` alone. Long-lived servers redirect stdout/stderr to bounded log files instead of unread pipes (which stall children).

Shutdown sequence for direct children: [`proc_terminate`](https://www.php.net/manual/en/function.proc-terminate.php) with SIGTERM, poll [`proc_get_status`](https://www.php.net/manual/en/function.proc-get-status.php) until a grace deadline, then SIGKILL. [`proc_terminate`](https://www.php.net/manual/en/function.proc-terminate.php) does **not** kill descendant processes; v1 owns only the direct child from `proc_open`. Do not claim process-group cleanup without `setsid`—that is Phase 2.

Incorrect patterns to reject in review:

- FPM “accidentally” owning build children without the CLI supervisor lifecycle
- Shell interpolation (`"bun run {$script}"`)
- Inherited full environments leaking secrets into children
- PID-only identity without fingerprint/start metadata
- Unlocked “ensure ready” sections (use [`flock`](https://www.php.net/manual/en/function.flock.php) on per-service lock files)
- Complex signal handlers; keep shutdown linear

## State, security, and env projection

Public env literals live in config; private bindings map child variable names to supervisor env vars that must exist before start. Missing private values fail startup and never appear in JSON diagnostics.

```php
function loadSecret(#[SensitiveParameter] string $supervisorVar): ?string
{
    $v = getenv($supervisorVar);
    return $v === false ? null : $v;
}
```

Use [`SensitiveParameter`](https://www.php.net/manual/en/class.sensitiveparameter.php) on boundaries that handle secrets. Redact private keys from dev API payloads even if an operator mistakenly logged env arrays.

Atomic state writes should use temporary files plus rename in future hardening; v1 serializes lifecycle with per-service locks.

## HTTP gateway and hooks

The gateway buffers upstream responses (v1), preserves method/query/body/status, strips hop-by-hop headers, and applies four hook namespaces in priority order (`request.headers`, `response.headers`, `status.lines`, `after`). Status hooks return at most two HTML-escaped lines for the cold 202 boot page—no CDN scripts; CSP nonces protect inline CSS/JS.

## Testing and review checklist

- Inject clock, env, and hash closures—never rely on disableable `assert()` for control flow.
- Unit-test config rejection, env redaction, hook ordering, and diagnostic headers without Docker when possible.
- Run `./bin/dev test` in CI; smoke uses a **disposable Git directory** so mutations never touch the main repo.
- Schedule a Docker signal smoke test when changing shutdown code ([PCNTL signals](https://www.php.net/manual/en/function.pcntl-signal.php) in the CLI supervisor entrypoint).

Tiny dependency-free test pattern:

```php
$runner->add('env redaction', static function (): void {
    $red = EnvProjection::redactDiagnostics(['SECRET' => 'x'], ['SECRET']);
    assertTrue(!isset($red['SECRET']));
});
```

## PHP 8.5 features used deliberately

Prefer `#[\NoDiscard]` on results where ignoring failures is dangerous (future hardening). Readonly value objects and backed enums already shrink bug surface. Do not adopt new syntax unless it deletes branching or documents intent more clearly than PHP 8.4 code.

## Summary

Treat config/env as untrusted input, lifecycle as enum-driven data, and processes as owned resources with explicit arrays, deadlines, and logs. Keep diagnostics public-by-design, secrets out of headers and JSON, and tests focused on boundaries and redaction—then extend with NestJS services in Phase 2 once this experiment’s `./bin/dev check` gate stays green on real hardware.

## Reference examples used in this repository

**Commands value object** — separates build and start argv lists so fingerprints hash stable JSON instead of imploding strings:

```php
final readonly class Commands
{
    public function __construct(
        public array $build,
        public array $start,
    ) {}
}
```

**Pure transition helper** — exhaustive `match` documents allowed transitions without nested if-chains:

```php
function nextState(RuntimeState $current): RuntimeState
{
    return match ($current) {
        RuntimeState::Fingerprinting => RuntimeState::Building,
        RuntimeState::Building => RuntimeState::Starting,
        RuntimeState::Starting => RuntimeState::Ready,
        default => $current,
    };
}
```

**Graceful stop with deadline** — monotonic clock, TERM, poll, KILL ([`proc_open`](https://www.php.net/manual/en/function.proc-open.php) resource required):

```php
proc_terminate($proc->resource, 15);
$deadline = hrtime(true) + $graceSec * 1_000_000_000;
while (hrtime(true) < $deadline && proc_get_status($proc->resource)['running']) {
    usleep(100_000);
}
proc_terminate($proc->resource, 9);
proc_close($proc->resource);
```

These patterns mirror production code in `src/Process/ProcessRunner.php` and `src/Supervisor/ServiceSupervisor.php`; keep them synchronized when you change behavior.

## Operational diagnostics (HTTP)

The gateway emits bounded diagnostic headers (`X-Dev-Service`, `X-Dev-State`, `X-Dev-Dirty`, `X-Dev-Build`, `X-Dev-Cold-Start`, `X-Dev-Build-Ms`, `X-Dev-Proxy-Ms`, `X-Dev-Stale`) built from snapshots—not live mutable structs. This prevents torn reads when one request triggers a rebuild while another proxies traffic. Never attach private env values to these headers; only public config literals and mechanical timestamps belong there.

When a rebuild fails but a last-good child remains, state moves to `degraded` and `X-Dev-Stale: 1` signals that upstream content may lag behind the latest fingerprint. Clients that require strict freshness should retry after `POST /__dev/services/{name}/rebuild` with JSON and `X-Dev-Action: 1` from localhost only.

## Cold start UX

Browser `GET` requests with `Accept: text/html` receive a dark, CSP-protected `202` boot page that subscribes to `/__dev/sse?service={name}` and reloads on `ready`. Inline script and style use per-request nonces—no third-party CDN. API clients without HTML accept headers block on `ensureReady()` and receive the proxied upstream response with the same diagnostic headers as warm traffic.

## When to refactor toward interfaces

Stay with concrete classes while the experiment has one supervisor and one proxy implementation. Extract interfaces when you add a second process runner (for example, a fake runner in CI) or an alternative fingerprint source (tarball hash instead of workspace walk). Premature interfaces obscured the v1 experiment goals; Phase 2 Nest integration should justify new seams with measured duplication.

Before merging Phase 2 changes, re-run `./bin/dev lint` and `./bin/dev check` on Linux with Docker enabled (`JIT_USE_DOCKER=1`) so PHP-FPM, Bun inside containers, and Compose networking match production. Host-only runs (`JIT_USE_DOCKER=0`) are supported for fast feedback but do not validate the FPM image or Nginx socket wiring.
