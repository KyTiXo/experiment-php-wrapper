<?php

declare(strict_types=1);

namespace JitRouter\Http;

use JitRouter\Env\EnvProjection;
use JitRouter\Hooks\HookRegistry;
use JitRouter\Runtime\ServiceSnapshot;
use JitRouter\Supervisor\EnsureResult;
use JitRouter\Supervisor\ServiceSupervisor;

final class Gateway
{
    public function __construct(
        private readonly HttpProxy $proxy,
        private readonly HookRegistry $hooks,
        private readonly bool $devMode,
    ) {
    }

    /**
     * @param array<string, list<string>> $incomingHeaders
     */
    public function handleServiceRequest(
        ServiceSupervisor $supervisor,
        string $method,
        string $path,
        string $query,
        string $body,
        array $incomingHeaders,
        bool $acceptsHtml,
    ): GatewayResult {
        $ensure = $supervisor->ensureReady();
        $snap = $supervisor->snapshot();

        if (!$ensure->ok) {
            return GatewayResult::error(502, $snap, $ensure, 'Service unavailable');
        }

        if ($acceptsHtml && $ensure->coldStart && $method === 'GET') {
            return GatewayResult::bootPage($snap, $ensure);
        }

        $service = $supervisor->configuredService();
        $upstream = sprintf(
            'http://127.0.0.1:%d/%s%s',
            $service->port,
            ltrim($path, '/'),
            $query !== '' ? '?' . $query : '',
        );

        $response = $this->proxy->forward(
            $service->name,
            $method,
            $upstream,
            $body,
            $incomingHeaders,
            ['snapshot' => $snap],
        );

        return GatewayResult::proxied($response, $snap, $ensure);
    }
}
