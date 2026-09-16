<?php

declare(strict_types=1);

namespace JitRouter\Supervisor;

use JitRouter\Config\Service;
use JitRouter\Env\EnvProjection;
use JitRouter\Fingerprint\FingerprintCalculator;
use JitRouter\Process\PersistentProcess;
use JitRouter\Process\ProcessRunner;
use JitRouter\Runtime\RuntimeState;
use JitRouter\Runtime\ServiceSnapshot;
use Closure;
use Throwable;

final class ServiceSupervisor
{
    private RuntimeState $state = RuntimeState::Stopped;
    private ?PersistentProcess $process = null;
    private ?string $runningFingerprint = null;
    private ?string $lastGoodFingerprint = null;
    private bool $stale = false;
    private bool $dirty = false;
    private ?int $pid = null;
    private ?int $buildMs = null;
    private ?string $buildId = null;
    private bool $coldStart = false;
    private ?string $lastError = null;
    private int $startedAt = 0;

    private readonly Closure $getEnv;
    private readonly Closure $now;

    public function __construct(
        private readonly Service $service,
        private readonly ProcessRunner $runner,
        private readonly FingerprintCalculator $fingerprints,
        private readonly ReadinessChecker $readiness,
        private readonly string $stateDir,
        callable $getEnv,
        ?callable $now = null,
    ) {
        $this->getEnv = Closure::fromCallable($getEnv);
        $this->now = Closure::fromCallable($now ?? hrtime(...));
    }

    public function configuredService(): Service
    {
        return $this->service;
    }

    public function snapshot(): ServiceSnapshot
    {
        $projection = EnvProjection::project($this->service, $this->getEnv);

        return new ServiceSnapshot(
            name: $this->service->name,
            state: $this->state,
            dirty: $this->dirty,
            buildId: $this->buildId,
            coldStart: $this->coldStart,
            buildMs: $this->buildMs,
            stale: $this->stale,
            pid: $this->pid,
            fingerprint: $this->runningFingerprint,
            envPublic: $projection['public'],
            lastError: $this->lastError,
        );
    }

    public function ensureReady(): EnsureResult
    {
        $lock = $this->acquireLock();
        try {
            return $this->ensureReadyLocked();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureReadyLocked(): EnsureResult
    {
        $commandFp = hash('xxh128', json_encode([
            'build' => $this->service->commands->build,
            'start' => $this->service->commands->start,
        ], JSON_THROW_ON_ERROR));

        $this->state = RuntimeState::Fingerprinting;
        $currentFp = $this->fingerprints->calculate($this->service, $commandFp);

        if ($this->process === null) {
            $this->rehydrateFromDisk($currentFp);
        }

        if ($this->process !== null && $this->runningFingerprint === $currentFp && $this->state !== RuntimeState::Failed) {
            if ($this->readiness->isReady('127.0.0.1', $this->service->port, $this->service->readyPath, 1)) {
                $this->state = $this->stale ? RuntimeState::Degraded : RuntimeState::Ready;
                $this->coldStart = false;

                return new EnsureResult(ok: true, coldStart: false, rebuilt: false);
            }
        }

        $disk = $this->readRuntimeState();
        $lastFp = $this->runningFingerprint ?? ($disk['fingerprint'] ?? null);
        $hadRunning = $this->process !== null;
        $needsRebuild = $lastFp !== $currentFp;

        if ($needsRebuild) {
            $this->dirty = $hadRunning && $lastFp !== $currentFp;
            $this->coldStart = !$hadRunning;
            $buildResult = $this->runBuild($currentFp);
            if (!$buildResult) {
                if ($hadRunning) {
                    $this->stale = true;
                    $this->state = RuntimeState::Degraded;

                    return new EnsureResult(ok: true, coldStart: false, rebuilt: false, degraded: true);
                }
                $this->state = RuntimeState::Failed;

                return new EnsureResult(ok: false, coldStart: $this->coldStart, rebuilt: true);
            }
        } else {
            $this->coldStart = !$hadRunning;
        }

        if (!$this->startIfNeeded($currentFp)) {
            if ($hadRunning) {
                $this->stale = true;
                $this->state = RuntimeState::Degraded;

                return new EnsureResult(ok: true, coldStart: false, rebuilt: false, degraded: true);
            }
            $this->state = RuntimeState::Failed;

            return new EnsureResult(ok: false, coldStart: $this->coldStart, rebuilt: true);
        }

        $this->state = RuntimeState::Ready;
        $this->stale = false;
        $this->dirty = false;
        $this->lastGoodFingerprint = $currentFp;

        return new EnsureResult(ok: true, coldStart: $this->coldStart, rebuilt: $needsRebuild);
    }

    private function runBuild(string $fingerprint): bool
    {
        $this->state = RuntimeState::Building;
        $this->buildId = substr($fingerprint, 0, 12);
        $start = ($this->now)(true);

        $projection = EnvProjection::project($this->service, $this->getEnv);
        if ($projection['missing'] !== []) {
            $this->lastError = 'Missing env: ' . implode(', ', $projection['missing']);

            return false;
        }

        $logPath = $this->logPath('build');
        file_put_contents($logPath, "=== build {$this->buildId} ===\n", FILE_APPEND);

        $result = $this->runner->runBuild(
            $this->service->commands->build,
            $this->service->dir,
            $projection['child'],
            $this->service->buildTimeoutSec,
        );

        $this->buildMs = (int) ((($this->now)(true) - $start) / 1_000_000);
        file_put_contents($logPath, $result->stdout . $result->stderr, FILE_APPEND);

        if (!$result->ok()) {
            $this->lastError = trim($result->stderr) ?: 'Build failed';

            return false;
        }

        return true;
    }

    private function startIfNeeded(string $fingerprint): bool
    {
        if ($this->process !== null && $this->runningFingerprint === $fingerprint) {
            if ($this->readiness->isReady('127.0.0.1', $this->service->port, $this->service->readyPath, $this->service->readyTimeoutSec)) {
                return true;
            }
        }

        if ($this->process !== null && $this->runningFingerprint !== $fingerprint) {
            $this->stopProcess();
        }

        $this->state = RuntimeState::Starting;
        $projection = EnvProjection::project($this->service, $this->getEnv);
        if ($projection['missing'] !== []) {
            $this->lastError = 'Missing env: ' . implode(', ', $projection['missing']);

            return false;
        }

        $logPath = $this->logPath('runtime');
        try {
            $proc = $this->runner->startPersistent(
                $this->service->commands->start,
                $this->service->dir,
                $projection['child'],
                $logPath,
            );
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        $this->process = $proc;
        $this->pid = $proc->pid;
        $this->runningFingerprint = $fingerprint;
        $this->startedAt = time();
        $this->writeRuntimeState($proc->pid, $fingerprint);

        if (!$this->readiness->isReady('127.0.0.1', $this->service->port, $this->service->readyPath, $this->service->readyTimeoutSec)) {
            $this->lastError = 'Readiness timeout';
            if ($this->lastGoodFingerprint === null) {
                $this->stopProcess();

                return false;
            }

            return false;
        }

        return true;
    }

    public function stop(): void
    {
        $lock = $this->acquireLock();
        try {
            $this->state = RuntimeState::Stopping;
            $this->stopProcess();
            $this->state = RuntimeState::Stopped;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function rebuild(): EnsureResult
    {
        $lock = $this->acquireLock();
        try {
            $this->stopProcess();
            $this->runningFingerprint = null;
            $this->dirty = true;

            return $this->ensureReadyLocked();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function tailLogs(int $lines): string
    {
        $path = $this->logPath('runtime');
        if (!is_file($path)) {
            return '';
        }
        $content = file($path, FILE_IGNORE_NEW_LINES) ?: [];

        return implode("\n", array_slice($content, -$lines));
    }

    private function stopProcess(): void
    {
        if ($this->process === null) {
            $disk = $this->readRuntimeState();
            if ($disk !== null && self::isPidAlive($disk['pid'])) {
                $this->process = new PersistentProcess(resource: null, pid: $disk['pid']);
                $this->pid = $disk['pid'];
            } else {
                $this->clearRuntimeState();

                return;
            }
        }
        $this->runner->stop($this->process, $this->service->stopTimeoutSec);
        $this->process = null;
        $this->pid = null;
        $this->runningFingerprint = null;
        $this->clearRuntimeState();
    }

    /**
     * Reattach to a child started by a previous PHP request (FPM / php -S).
     */
    private function rehydrateFromDisk(string $currentFp): void
    {
        $disk = $this->readRuntimeState();
        if ($disk === null) {
            return;
        }

        if (!self::isPidAlive($disk['pid'])) {
            $this->clearRuntimeState();

            return;
        }

        $this->process = new PersistentProcess(resource: null, pid: $disk['pid']);
        $this->pid = $disk['pid'];
        $this->runningFingerprint = $disk['fingerprint'];
        $this->lastGoodFingerprint = $disk['fingerprint'];
        if ($disk['fingerprint'] === $currentFp) {
            $this->coldStart = false;
        }
    }

    /**
     * @return array{pid: int, fingerprint: string}|null
     */
    private function readRuntimeState(): ?array
    {
        $path = $this->runtimeStatePath();
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        try {
            /** @var mixed $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($data)
            || !isset($data['pid'], $data['fingerprint'])
            || !is_string($data['fingerprint'])
            || (!is_int($data['pid']) && !(is_string($data['pid']) && ctype_digit($data['pid'])))
        ) {
            return null;
        }

        return [
            'pid' => (int) $data['pid'],
            'fingerprint' => $data['fingerprint'],
        ];
    }

    private function writeRuntimeState(int $pid, string $fingerprint): void
    {
        $dir = $this->serviceStateDir();
        file_put_contents(
            $dir . '/runtime.json',
            json_encode(['pid' => $pid, 'fingerprint' => $fingerprint], JSON_THROW_ON_ERROR),
        );
    }

    private function clearRuntimeState(): void
    {
        $path = $this->runtimeStatePath();
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function runtimeStatePath(): string
    {
        return $this->serviceStateDir() . '/runtime.json';
    }

    private function serviceStateDir(): string
    {
        $dir = $this->stateDir . '/' . $this->service->name;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (!function_exists('posix_kill')) {
            return false;
        }

        return @posix_kill($pid, 0);
    }

    private function logPath(string $kind): string
    {
        return $this->serviceStateDir() . '/' . $kind . '.log';
    }

    /**
     * @return resource
     */
    private function acquireLock()
    {
        $dir = $this->serviceStateDir();
        $lock = fopen($dir . '/lock', 'c+');
        if ($lock === false) {
            throw new SupervisorException('Cannot acquire lock');
        }
        flock($lock, LOCK_EX);

        return $lock;
    }
}
