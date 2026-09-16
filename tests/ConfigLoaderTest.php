<?php

declare(strict_types=1);

use JitRouter\Config\ConfigException;
use JitRouter\Config\ConfigLoader;

global $runner;

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/jit-config-test-' . bin2hex(random_bytes(4));
mkdir($tmp . '/svc', 0777, true);
file_put_contents($tmp . '/services.php', <<<'PHP'
<?php
return ['services' => ['demo' => [
    'dir' => 'svc',
    'port' => 3000,
    'commands' => ['build' => ['echo', 'ok'], 'start' => ['sleep', '999']],
    'watch' => [],
]]];
PHP);

$runner->add('config loads valid service', static function () use ($root, $tmp): void {
    $saved = getcwd();
    chdir($tmp);
    $services = ConfigLoader::load($tmp . '/services.php', $tmp);
    chdir($saved);
    assertSame(1, count($services));
    assertSame('demo', $services[0]->name);
    assertSame(3000, $services[0]->port);
});

$runner->add('config rejects unknown keys', static function () use ($tmp): void {
    file_put_contents($tmp . '/bad.php', <<<'PHP'
<?php
return ['services' => ['x' => ['dir' => 'svc', 'port' => 1, 'commands' => ['build' => ['true'], 'start' => ['true']], 'nope' => 1]]];
PHP);
    try {
        ConfigLoader::load($tmp . '/bad.php', $tmp);
        assertTrue(false, 'expected exception');
    } catch (ConfigException $e) {
        assertTrue(str_contains($e->getMessage(), 'nope'));
    }
});

$runner->add('config rejects invalid port', static function () use ($tmp): void {
    file_put_contents($tmp . '/port.php', <<<'PHP'
<?php
return ['services' => ['x' => ['dir' => 'svc', 'port' => 0, 'commands' => ['build' => ['true'], 'start' => ['true']]]]];
PHP);
    try {
        ConfigLoader::load($tmp . '/port.php', $tmp);
        assertTrue(false, 'expected exception');
    } catch (ConfigException) {
        assertTrue(true);
    }
});
