<?php

declare(strict_types=1);

namespace JitRouter\Http;

final readonly class ProxyResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public int $proxyMs,
    ) {
    }
}
