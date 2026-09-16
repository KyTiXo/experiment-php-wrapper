<?php

declare(strict_types=1);

use JitRouter\Http\DiagnosticHeaders;
use JitRouter\Runtime\RuntimeState;
use JitRouter\Runtime\ServiceSnapshot;
use JitRouter\Supervisor\EnsureResult;

global $runner;

$runner->add('diagnostic headers include required fields', static function (): void {
    $snap = new ServiceSnapshot(
        name: 'demo',
        state: RuntimeState::Ready,
        dirty: false,
        buildId: 'abc',
        coldStart: true,
        buildMs: 12,
        stale: false,
        pid: 1,
        fingerprint: 'fp',
        envPublic: ['PORT' => '1'],
        lastError: null,
    );
    $headers = DiagnosticHeaders::fromSnapshot($snap, new EnsureResult(true, true, false), 3);
    assertSame('demo', $headers['X-Dev-Service']);
    assertSame('ready', $headers['X-Dev-State']);
    assertSame('1', $headers['X-Dev-Cold-Start']);
    assertSame('12', $headers['X-Dev-Build-Ms']);
    assertSame('3', $headers['X-Dev-Proxy-Ms']);
});
