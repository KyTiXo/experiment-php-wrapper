<?php

declare(strict_types=1);

namespace JitRouter\Env;

use JitRouter\Config\Service;

final class EnvProjection
{
    /**
     * @return array{public: array<string, string>, child: array<string, string>, missing: list<string>}
     */
    public static function project(Service $service, callable $getEnv): array
    {
        $public = [];
        foreach ($service->envPublic as $childKey => $literal) {
            $public[$childKey] = $literal;
        }

        $child = $public;
        $missing = [];

        foreach ($service->envPrivate as $childKey => $supervisorVar) {
            $value = $getEnv($supervisorVar);
            if ($value === null || $value === '') {
                $missing[] = $supervisorVar;
                continue;
            }
            $child[$childKey] = $value;
        }

        return ['public' => $public, 'child' => $child, 'missing' => $missing];
    }

    /**
     * @param array<string, mixed> $diagnostics
     * @param list<string> $privateKeys
     * @return array<string, mixed>
     */
    public static function redactDiagnostics(array $diagnostics, array $privateKeys): array
    {
        $redacted = $diagnostics;
        foreach ($privateKeys as $key) {
            if (array_key_exists($key, $redacted)) {
                unset($redacted[$key]);
            }
            if (isset($redacted['env']) && is_array($redacted['env']) && array_key_exists($key, $redacted['env'])) {
                unset($redacted['env'][$key]);
            }
        }

        return $redacted;
    }
}
