<?php

declare(strict_types=1);

namespace JitRouter\Process;

final class PersistentProcess
{
    public function __construct(
        public mixed $resource,
        public int $pid,
    ) {
    }
}
