<?php

declare(strict_types=1);

namespace JitRouter;

use JitRouter\Config\ConfigLoader;
use JitRouter\Hooks\HookRegistry;
use JitRouter\Http\DevApi;
use JitRouter\Http\Gateway;
use JitRouter\Http\HttpProxy;
use JitRouter\Http\RequestParser;
use JitRouter\Http\ResponseEmitter;
use JitRouter\Http\SseStreamer;
use JitRouter\Supervisor\SupervisorPool;

final class Application
{
    private SupervisorPool $pool;
    private Gateway $gateway;
    private DevApi $devApi;
    private HookRegistry $hooks;

    public function __construct(
        string $configPath,
        string $workspaceRoot,
        string $stateDir,
        ?HookRegistry $hooks = null,
    ) {
        $services = ConfigLoader::load($configPath, $workspaceRoot);
        $this->hooks = $hooks ?? new HookRegistry();
        $this->pool = new SupervisorPool($services, $stateDir);
        $this->gateway = new Gateway(new HttpProxy($this->hooks), $this->hooks, devMode: true);
        $this->devApi = new DevApi($this->pool);
    }

    public function hooks(): HookRegistry
    {
        return $this->hooks;
    }

    public function pool(): SupervisorPool
    {
        return $this->pool;
    }

    public function run(): void
    {
        $req = RequestParser::fromGlobals();
        $localOk = $this->isLocalRequest();
        $actionHeader = $_SERVER['HTTP_X_DEV_ACTION'] ?? null;

        if (str_starts_with($req['path'], '/__dev')) {
            if ($req['path'] === '/__dev/sse' && $req['method'] === 'GET') {
                $name = $_GET['service'] ?? '';
                $supervisor = $this->pool->get((string) $name);
                if ($supervisor === null) {
                    ResponseEmitter::emit(404, 'unknown service');

                    return;
                }
                SseStreamer::stream($supervisor);

                return;
            }

            $dev = $this->devApi->handle($req['method'], $req['path'], $req['body'], $localOk, is_string($actionHeader) ? $actionHeader : null);
            if (isset($dev['sse'])) {
                return;
            }
            ResponseEmitter::emit($dev['status'], $dev['body'], array_map(static fn ($v) => [$v], $dev['headers']));

            return;
        }

        $route = RequestParser::parseServiceRoute($req['path']);
        if ($route === null) {
            ResponseEmitter::emit(404, 'Not found');

            return;
        }

        $supervisor = $this->pool->get($route['service']);
        if ($supervisor === null) {
            ResponseEmitter::emit(404, 'Unknown service');

            return;
        }

        $result = $this->gateway->handleServiceRequest(
            $supervisor,
            $req['method'],
            $route['subpath'],
            $req['query'],
            $req['body'],
            $req['headers'],
            $req['acceptsHtml'],
        );

        ResponseEmitter::emitGateway($result);
    }

    private function isLocalRequest(): bool
    {
        // T9: allow any local Host port (php -S :8090, nginx :8080, etc.)
        $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
        $hostOnly = explode(':', $host, 2)[0];
        if (!in_array($hostOnly, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return false;
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== ''
            && !str_contains($origin, '127.0.0.1')
            && !str_contains($origin, 'localhost')
            && !str_contains($origin, '[::1]')
            && !str_contains($origin, '::1')
        ) {
            return false;
        }

        return true;
    }
}
