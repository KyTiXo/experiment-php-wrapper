<?php

declare(strict_types=1);

namespace JitRouter\Process;

final class ProcessRunner
{
    /**
     * @param list<non-empty-string> $command
     * @param array<string, string> $env
     * @param callable(array<int, resource>, array<int, resource>|null, array<int, resource>|null, int): int|false|null $selectFn
     */
    public function runBuild(
        array $command,
        string $cwd,
        array $env,
        int $timeoutSec,
        ?callable $selectFn = null,
    ): ProcessResult {
        $selectFn ??= null;
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $procEnv = array_merge(getenvLocal(), $env);
        $process = proc_open($command, $descriptors, $pipes, $cwd, $procEnv);
        if (!is_resource($process)) {
            return new ProcessResult(exitCode: 1, stdout: '', stderr: 'proc_open failed');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = hrtime(true) + $timeoutSec * 1_000_000_000;

        while (true) {
            if (hrtime(true) >= $deadline && !self::hasData([$pipes[1], $pipes[2]])) {
                proc_terminate($process, 15);
                proc_close($process);

                return new ProcessResult(exitCode: 124, stdout: $stdout, stderr: $stderr . "\n[timeout]");
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $timeoutUsec = min(200_000, max(1000, (int) (($deadline - hrtime(true)) / 1000)));
            if ($selectFn !== null) {
                $selectFn($read, $write, $except, $timeoutUsec);
            } else {
                stream_select($read, $write, $except, 0, $timeoutUsec);
            }

            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);
                if ($chunk === false || $chunk === '') {
                    continue;
                }
                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $code = $status['exitcode'];
                if ($code === -1) {
                    $code = proc_close($process);
                } else {
                    proc_close($process);
                }

                return new ProcessResult(exitCode: $code, stdout: $stdout, stderr: $stderr);
            }
        }
    }

    /**
     * @param list<non-empty-string> $command
     * @param array<string, string> $env
     */
    public function startPersistent(
        array $command,
        string $cwd,
        array $env,
        string $logPath,
    ): PersistentProcess {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a'],
        ];

        $procEnv = array_merge(getenvLocal(), $env);
        $process = proc_open($command, $descriptors, $pipes, $cwd, $procEnv);
        if (!is_resource($process)) {
            throw new ProcessException('proc_open failed for persistent process');
        }
        fclose($pipes[0]);

        $status = proc_get_status($process);

        return new PersistentProcess(
            resource: $process,
            pid: (int) $status['pid'],
        );
    }

    public function stop(PersistentProcess $proc, int $graceSec): int
    {
        if (!is_resource($proc->resource)) {
            return 0;
        }

        proc_terminate($proc->resource, 15);
        $deadline = hrtime(true) + $graceSec * 1_000_000_000;

        while (hrtime(true) < $deadline) {
            $status = proc_get_status($proc->resource);
            if (!$status['running']) {
                return proc_close($proc->resource);
            }
            usleep(100_000);
        }

        proc_terminate($proc->resource, 9);
        usleep(100_000);

        return proc_close($proc->resource);
    }

    /**
     * @param array<resource> $streams
     */
    private static function hasData(array $streams): bool
    {
        foreach ($streams as $s) {
            $meta = stream_get_meta_data($s);
            if ($meta['unread_bytes'] > 0) {
                return true;
            }
        }

        return false;
    }
}

/**
 * @return array<string, string>
 */
function getenvLocal(): array
{
    $out = [];
    foreach ($_ENV as $k => $v) {
        if (is_string($v)) {
            $out[$k] = $v;
        }
    }
    foreach (getenv() ?: [] as $k => $v) {
        if (is_string($v)) {
            $out[$k] = $v;
        }
    }

    return $out;
}
