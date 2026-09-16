<?php

declare(strict_types=1);

namespace JitRouter\Runtime;

enum RuntimeState: string
{
    case Stopped = 'stopped';
    case Fingerprinting = 'fingerprinting';
    case Building = 'building';
    case Starting = 'starting';
    case Ready = 'ready';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Stopping = 'stopping';

    public function isServing(): bool
    {
        return match ($this) {
            self::Ready, self::Degraded => true,
            default => false,
        };
    }
}
