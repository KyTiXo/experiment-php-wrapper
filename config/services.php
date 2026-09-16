<?php

declare(strict_types=1);

return [
    'services' => [
        'fixture' => [
            'dir' => 'fixtures/bun-service',
            'port' => 4100,
            'commands' => [
                'build' => ['bun', 'run', 'build'],
                'start' => ['bun', 'run', 'start'],
            ],
            'watch' => ['fixtures/bun-service'],
            'envPublic' => [
                'PORT' => '4100',
            ],
            'envPrivate' => [],
            'readyPath' => '/health',
        ],
        'api-simple' => [
            'dir' => 'fixtures/phase2/api-simple',
            'port' => 4101,
            'commands' => [
                'build' => ['bun', 'run', 'build'],
                'start' => ['bun', 'run', 'start'],
            ],
            'watch' => [
                'fixtures/phase2/api-simple/apps/api-simple',
            ],
            'envPublic' => [
                'PORT' => '4101',
            ],
            'envPrivate' => [],
            'readyPath' => '/health',
        ],
        'api-private' => [
            'dir' => 'fixtures/phase2/api-private-env',
            'port' => 4103,
            'commands' => [
                'build' => ['bun', 'run', 'build'],
                'start' => ['bun', 'run', 'start'],
            ],
            'watch' => [
                'fixtures/phase2/api-private-env/apps/api-private',
            ],
            'envPublic' => [
                'PORT' => '4103',
            ],
            'envPrivate' => [
                'DATABASE_URL' => 'JIT_DATABASE_URL',
            ],
            'readyPath' => '/health',
        ],
        // THROWAWAY Phase2 T12 — TCP readiness only (no readyPath). Port 4104.
        'api-tcp-probe' => [
            'dir' => 'fixtures/phase2/api-tcp-probe',
            'port' => 4104,
            'commands' => [
                'build' => ['bun', 'run', 'build'],
                'start' => ['bun', 'run', 'start'],
            ],
            'watch' => [
                'fixtures/phase2/api-tcp-probe/apps/api-tcp-probe',
            ],
            'envPublic' => [
                'PORT' => '4104',
            ],
            'envPrivate' => [],
            // intentionally no readyPath — TCP connect suffices (T12)
        ],
    ],
];
