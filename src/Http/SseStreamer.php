<?php

declare(strict_types=1);

namespace JitRouter\Http;

use JitRouter\Runtime\RuntimeState;
use JitRouter\Supervisor\ServiceSupervisor;

final class SseStreamer
{
    public static function stream(ServiceSupervisor $supervisor, int $maxSeconds = 600): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $deadline = time() + $maxSeconds;
        $lastState = null;

        while (time() < $deadline) {
            $snap = $supervisor->snapshot();
            if ($snap->state !== $lastState) {
                $event = match ($snap->state) {
                    RuntimeState::Building => 'building',
                    RuntimeState::Starting => 'starting',
                    RuntimeState::Ready => 'ready',
                    RuntimeState::Degraded => 'degraded',
                    RuntimeState::Failed => 'failed',
                    default => 'update',
                };
                echo "event: {$event}\n";
                echo 'data: ' . json_encode(['state' => $snap->state->value], JSON_THROW_ON_ERROR) . "\n\n";
                flush();
                $lastState = $snap->state;
                if ($snap->state === RuntimeState::Ready || $snap->state === RuntimeState::Degraded) {
                    break;
                }
            }
            if (connection_aborted()) {
                break;
            }
            usleep(300_000);
        }
    }
}
