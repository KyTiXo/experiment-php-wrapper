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
        'api-with-lib' => [
            'dir' => 'fixtures/phase2/api-with-lib',
            'port' => 4102,
            'commands' => [
                'build' => ['bun', 'run', 'build'],
                'start' => ['bun', 'run', 'start'],
            ],
            'watch' => [
                'fixtures/phase2/api-with-lib/packages/shared-lib',
                'fixtures/phase2/api-with-lib/apps/api-with-lib',
            ],
            'envPublic' => [
                'PORT' => '4102',
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
    ],
];
