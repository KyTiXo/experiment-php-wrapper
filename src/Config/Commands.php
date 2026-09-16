<?php

declare(strict_types=1);

namespace JitRouter\Config;

final readonly class Commands
{
    /**
     * @param list<non-empty-string> $build
     * @param list<non-empty-string> $start
     */
    public function __construct(
        public array $build,
        public array $start,
    ) {
    }
}
