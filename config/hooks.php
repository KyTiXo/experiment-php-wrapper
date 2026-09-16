<?php

declare(strict_types=1);

use JitRouter\Hooks\HookRegistry;

return static function (HookRegistry $hooks): void {
    $hooks->register('service.fixture.request.headers', static function (array $headers): array {
        $headers['X-Fixture-Hook'] = ['1'];

        return $headers;
    }, priority: 10);
};
