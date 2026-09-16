<?php

declare(strict_types=1);

namespace JitRouter\Config;

final readonly class Service
{
    /**
     * @param list<non-empty-string> $watch
     * @param array<non-empty-string, non-empty-string> $envPublic
     * @param array<non-empty-string, non-empty-string> $envPrivate child var => supervisor env var name
     */
    public function __construct(
        public string $name,
        public string $dir,
        public int $port,
        public Commands $commands,
        public array $watch,
        public array $envPublic,
        public array $envPrivate,
        public ?string $readyPath = null,
        public int $buildTimeoutSec = 300,
        public int $readyTimeoutSec = 30,
        public int $stopTimeoutSec = 5,
    ) {
    }
}
