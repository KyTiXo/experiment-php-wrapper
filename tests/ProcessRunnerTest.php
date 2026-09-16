<?php

declare(strict_types=1);

use JitRouter\Process\ProcessRunner;

global $runner;

$runner->add('process runner executes build command', static function (): void {
    $runnerProc = new ProcessRunner();
    $result = $runnerProc->runBuild(['echo', 'hello'], sys_get_temp_dir(), [], 5);
    assertTrue($result->ok());
    assertTrue(str_contains($result->stdout, 'hello'));
});
