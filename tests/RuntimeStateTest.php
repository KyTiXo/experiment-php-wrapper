<?php

declare(strict_types=1);

use JitRouter\Runtime\RuntimeState;

global $runner;

$runner->add('runtime state serving match', static function (): void {
    assertTrue(RuntimeState::Ready->isServing());
    assertTrue(RuntimeState::Degraded->isServing());
    assertTrue(!RuntimeState::Building->isServing());
});

$runner->add('lifecycle transition exhaustive', static function (): void {
    $next = static function (RuntimeState $s): RuntimeState {
        return match ($s) {
            RuntimeState::Stopped => RuntimeState::Fingerprinting,
            RuntimeState::Fingerprinting => RuntimeState::Building,
            RuntimeState::Building => RuntimeState::Starting,
            RuntimeState::Starting => RuntimeState::Ready,
            RuntimeState::Ready, RuntimeState::Degraded => RuntimeState::Ready,
            RuntimeState::Failed => RuntimeState::Failed,
            RuntimeState::Stopping => RuntimeState::Stopped,
        };
    };
    assertSame(RuntimeState::Building, $next(RuntimeState::Fingerprinting));
});
