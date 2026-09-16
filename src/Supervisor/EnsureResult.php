<?php

declare(strict_types=1);

namespace JitRouter\Supervisor;

final readonly class EnsureResult
{
    public function __construct(
        public bool $ok,
        public bool $coldStart,
        public bool $rebuilt,
        public bool $degraded = false,
    ) {
    }
}
