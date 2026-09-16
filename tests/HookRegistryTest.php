<?php

declare(strict_types=1);

use JitRouter\Hooks\HookRegistry;

global $runner;

$runner->add('hooks run in priority order', static function (): void {
    $hooks = new HookRegistry();
    $order = [];
    $hooks->register('test', static function () use (&$order): string {
        $order[] = 'b';

        return 'b';
    }, 20);
    $hooks->register('test', static function () use (&$order): string {
        $order[] = 'a';

        return 'a';
    }, 5);
    $hooks->run('test');
    assertSame(['a', 'b'], $order);
});

$runner->add('status lines capped at two escaped', static function (): void {
    $hooks = new HookRegistry();
    $hooks->register('service.x.status.lines', static fn (): array => ['<bad>', 'a', 'b']);
    $lines = $hooks->statusLines('x');
    assertSame(2, count($lines));
    assertSame('&lt;bad&gt;', $lines[0]);
});

$runner->add('request header hook merges', static function (): void {
    $hooks = new HookRegistry();
    $hooks->register('service.api.request.headers', static function (array $headers): array {
        $headers['X-Test'] = ['1'];

        return $headers;
    });
    $out = $hooks->applyRequestHeaders('api', ['Host' => ['localhost']]);
    assertSame(['1'], $out['X-Test']);
});
