<?php

declare(strict_types=1);

namespace JitRouter\Process;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public function ok(): bool
    {
        return $this->exitCode === 0;
    }
}
