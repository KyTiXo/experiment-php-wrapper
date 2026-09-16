<?php

declare(strict_types=1);

namespace JitRouter\Http;

use JitRouter\Runtime\ServiceSnapshot;
use JitRouter\Supervisor\EnsureResult;

final class DiagnosticHeaders
{
    /**
     * @return array<string, string>
     */
    public static function fromSnapshot(ServiceSnapshot $snap, EnsureResult $ensure, ?int $proxyMs = null): array
    {
        $headers = [
            'X-Dev-Service' => $snap->name,
            'X-Dev-State' => $snap->state->value,
            'X-Dev-Dirty' => $snap->dirty ? '1' : '0',
            'X-Dev-Build' => $snap->buildId ?? '',
            'X-Dev-Cold-Start' => $ensure->coldStart ? '1' : '0',
            'X-Dev-Stale' => $snap->stale ? '1' : '0',
        ];
        if ($snap->buildMs !== null) {
            $headers['X-Dev-Build-Ms'] = (string) $snap->buildMs;
        }
        if ($proxyMs !== null) {
            $headers['X-Dev-Proxy-Ms'] = (string) $proxyMs;
        }

        return $headers;
    }
}
