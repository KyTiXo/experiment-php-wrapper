<?php

declare(strict_types=1);

namespace JitRouter\Http;

use JitRouter\Hooks\HookRegistry;
use JitRouter\Runtime\ServiceSnapshot;

final class BootPageRenderer
{
    public static function render(string $serviceName, string $nonce, HookRegistry $hooks = new HookRegistry()): string
    {
        $lines = $hooks->statusLines($serviceName);
        $statusHtml = $lines === []
            ? '<p>Building service…</p>'
            : '<p>' . implode('</p><p>', $lines) . '</p>';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Starting {$serviceName}</title>
<style nonce="{$nonce}">
:root { color-scheme: dark; }
body { background: #0d1117; color: #c9d1d9; font-family: ui-monospace, monospace; padding: 2rem; }
h1 { font-size: 1.1rem; font-weight: 600; }
.degraded { color: #d29922; }
</style>
</head>
<body>
<h1>JIT router · {$serviceName}</h1>
{$statusHtml}
<p id="state">Connecting…</p>
<script nonce="{$nonce}">
(function () {
  const el = document.getElementById('state');
  const es = new EventSource('/__dev/sse?service={$serviceName}');
  es.addEventListener('ready', function () { window.location.reload(); });
  es.addEventListener('building', function () { el.textContent = 'Building…'; });
  es.addEventListener('starting', function () { el.textContent = 'Starting…'; });
  es.addEventListener('degraded', function () { el.textContent = 'Degraded (serving last good)'; el.className = 'degraded'; });
  es.onerror = function () { el.textContent = 'Reconnecting…'; };
})();
</script>
</body>
</html>
HTML;
    }
}
