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
    ],
];
