<?php

declare(strict_types=1);

namespace JitRouter\Runtime;

final readonly class ServiceSnapshot
{
    /**
     * @param array<string, string> $envPublic
     */
    public function __construct(
        public string $name,
        public RuntimeState $state,
        public bool $dirty,
        public ?string $buildId,
        public bool $coldStart,
        public ?int $buildMs,
        public bool $stale,
        public ?int $pid,
        public ?string $fingerprint,
        public array $envPublic,
        public ?string $lastError,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state->value,
            'dirty' => $this->dirty,
            'buildId' => $this->buildId,
            'coldStart' => $this->coldStart,
            'buildMs' => $this->buildMs,
            'stale' => $this->stale,
            'pid' => $this->pid,
            'fingerprint' => $this->fingerprint,
            'envPublic' => $this->envPublic,
            'lastError' => $this->lastError,
        ];
    }
}
