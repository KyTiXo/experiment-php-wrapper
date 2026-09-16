<?php

declare(strict_types=1);

namespace JitRouter\Http;

use JitRouter\Env\EnvProjection;
use JitRouter\Supervisor\ServiceSupervisor;
use JitRouter\Supervisor\SupervisorPool;

final class DevApi
{
    public function __construct(
        private readonly SupervisorPool $pool,
    ) {
    }

    /**
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function handle(string $method, string $path, string $body, bool $localOk, ?string $actionHeader): array
    {
        if (str_starts_with($path, '/__dev/services/') && preg_match('#^/__dev/services/([a-z][a-z0-9-]{0,62})(/.*)?$#', $path, $m)) {
            $name = $m[1];
            $rest = $m[2] ?? '';
            $supervisor = $this->pool->get($name);
            if ($supervisor === null) {
                return $this->json(404, ['error' => 'unknown service']);
            }

            if ($rest === '/logs' && $method === 'GET') {
                $lines = (int) ($_GET['lines'] ?? 200);

                return $this->json(200, ['logs' => $supervisor->tailLogs($lines)]);
            }

            if (($rest === '/stop' || $rest === '/rebuild') && $method === 'POST') {
                if (!$localOk || $actionHeader !== '1') {
                    return $this->json(403, ['error' => 'forbidden']);
                }
                if ($rest === '/stop') {
                    $supervisor->stop();

                    return $this->json(200, ['ok' => true, 'action' => 'stop']);
                }
                $result = $supervisor->rebuild();

                return $this->json(200, ['ok' => $result->ok, 'action' => 'rebuild']);
            }

            if ($rest === '' && $method === 'GET') {
                return $this->json(200, $this->publicSnapshot($supervisor));
            }
        }

        if ($path === '/__dev/services' && $method === 'GET') {
            $list = [];
            foreach ($this->pool->all() as $sup) {
                $list[] = $this->publicSnapshot($sup);
            }

            return $this->json(200, ['services' => $list]);
        }

        if ($path === '/__dev/sse' && $method === 'GET') {
            return ['status' => 0, 'body' => '', 'headers' => [], 'sse' => true];
        }

        return $this->json(404, ['error' => 'not found']);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSnapshot(ServiceSupervisor $supervisor): array
    {
        $snap = $supervisor->snapshot();
        $data = $snap->toPublicArray();
        $privateKeys = array_keys($supervisor->configuredService()->envPrivate);

        return EnvProjection::redactDiagnostics($data, $privateKeys);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private function json(int $status, array $data): array
    {
        return [
            'status' => $status,
            'body' => json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
        ];
    }
}
