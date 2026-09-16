<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$workspace = getenv('JIT_WORKSPACE') ?: dirname(__DIR__);
$config = getenv('JIT_CONFIG') ?: $workspace . '/config/services.php';
$stateDir = getenv('JIT_STATE_DIR') ?: '/var/jit-state';

$hooksFile = $workspace . '/config/hooks.php';
$hooks = new JitRouter\Hooks\HookRegistry();
if (is_file($hooksFile)) {
    $register = require $hooksFile;
    if (is_callable($register)) {
        $register($hooks);
    }
}

$app = new JitRouter\Application($config, $workspace, $stateDir, $hooks);
$app->run();
