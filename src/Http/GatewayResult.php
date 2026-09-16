<?php

declare(strict_types=1);

namespace JitRouter\Http;

use JitRouter\Runtime\ServiceSnapshot;
use JitRouter\Supervisor\EnsureResult;

final readonly class GatewayResult
{
    private function __construct(
        public string $kind,
        public int $status,
        public string $body,
        /** @var array<string, list<string>> */
        public array $headers,
        public ServiceSnapshot $snapshot,
        public EnsureResult $ensure,
        public ?ProxyResponse $proxy = null,
    ) {
    }

    public static function proxied(ProxyResponse $proxy, ServiceSnapshot $snap, EnsureResult $ensure): self
    {
        return new self(
            kind: 'proxy',
            status: $proxy->status,
            body: $proxy->body,
            headers: $proxy->headers,
            snapshot: $snap,
            ensure: $ensure,
            proxy: $proxy,
        );
    }

    public static function bootPage(ServiceSnapshot $snap, EnsureResult $ensure): self
    {
        $nonce = bin2hex(random_bytes(16));
        $html = BootPageRenderer::render($snap->name, $nonce);

        return new self(
            kind: 'boot',
            status: 202,
            body: $html,
            headers: [
                'Content-Type' => ['text/html; charset=UTF-8'],
                'Content-Security-Policy' => ["default-src 'none'; style-src 'nonce-{$nonce}'; script-src 'nonce-{$nonce}'; connect-src 'self'"],
            ],
            snapshot: $snap,
            ensure: $ensure,
        );
    }

    public static function error(int $status, ServiceSnapshot $snap, EnsureResult $ensure, string $message): self
    {
        return new self(
            kind: 'error',
            status: $status,
            body: $message,
            headers: ['Content-Type' => ['text/plain; charset=UTF-8']],
            snapshot: $snap,
            ensure: $ensure,
        );
    }
}
