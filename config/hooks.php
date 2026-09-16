<?php

declare(strict_types=1);

use JitRouter\Hooks\HookRegistry;

return static function (HookRegistry $hooks): void {
    $hooks->register('service.fixture.request.headers', static function (array $headers): array {
        $headers['X-Fixture-Hook'] = ['1'];

        return $headers;
    }, priority: 10);

    $hooks->register('service.api-private.request.headers', static function (array $headers): array {
        $headers['X-Fixture-Hook'] = ['api-private'];

        return $headers;
    }, priority: 10);
};
