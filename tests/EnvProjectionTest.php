<?php

declare(strict_types=1);

use JitRouter\Config\Commands;
use JitRouter\Config\Service;
use JitRouter\Env\EnvProjection;

global $runner;

$service = new Service(
    name: 's',
    dir: '/tmp',
    port: 1,
    commands: new Commands(['build'], ['start']),
    watch: [],
    envPublic: ['PUBLIC' => 'yes'],
    envPrivate: ['SECRET' => 'JIT_SECRET'],
);

$runner->add('env projection maps private from supervisor env', static function () use ($service): void {
    $proj = EnvProjection::project($service, static fn (string $k): ?string => $k === 'JIT_SECRET' ? 'hidden' : null);
    assertSame('hidden', $proj['child']['SECRET']);
    assertSame([], $proj['missing']);
    assertTrue(!array_key_exists('SECRET', $proj['public']));
});

$runner->add('env projection reports missing private', static function () use ($service): void {
    $proj = EnvProjection::project($service, static fn (string $k): ?string => null);
    assertSame(['JIT_SECRET'], $proj['missing']);
});

$runner->add('env redaction removes private keys from diagnostics', static function (): void {
    $diag = ['env' => ['PUBLIC' => 'yes', 'SECRET' => 'hidden'], 'SECRET' => 'hidden'];
    $red = EnvProjection::redactDiagnostics($diag, ['SECRET']);
    assertTrue(!isset($red['SECRET']));
    assertTrue(!isset($red['env']['SECRET']));
    assertSame('yes', $red['env']['PUBLIC']);
});
