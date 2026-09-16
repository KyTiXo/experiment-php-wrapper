<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use JitRouter\Application;

$workspace = getenv('JIT_WORKSPACE') ?: dirname(__DIR__);
$config = getenv('JIT_CONFIG') ?: $workspace . '/config/services.php';
$stateDir = getenv('JIT_STATE_DIR') ?: '/var/jit-state';

pcntl_async_signals(true);

$shutdown = static function () use ($workspace, $config, $stateDir): void {
    $app = new Application($config, $workspace, $stateDir);
    foreach ($app->pool()->all() as $supervisor) {
        $supervisor->stop();
    }
    exit(0);
};

pcntl_signal(SIGTERM, $shutdown);
pcntl_signal(SIGINT, $shutdown);

fwrite(STDOUT, "jit supervisor idle (SIGTERM stops children)\n");

while (true) {
    pcntl_signal_dispatch();
    sleep(5);
}
