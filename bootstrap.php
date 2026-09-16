<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'JitRouter\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = __DIR__ . '/src/' . $relative . '.php';
    if (is_file($path)) {
        require $path;
    }
});
