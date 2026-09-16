<?php

declare(strict_types=1);

namespace JitRouter\Config;

final class ConfigLoader
{
    private const ALLOWED_SERVICE_KEYS = [
        'dir', 'port', 'commands', 'watch', 'envPublic', 'envPrivate',
        'readyPath', 'buildTimeoutSec', 'readyTimeoutSec', 'stopTimeoutSec',
    ];

    private const ALLOWED_COMMAND_KEYS = ['build', 'start'];

    /**
     * @return list<Service>
     */
    public static function load(string $path, string $workspaceRoot): array
    {
        if (!is_file($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }

        $raw = require $path;
        if (!is_array($raw)) {
            throw new ConfigException('Config must return an array');
        }

        if (!isset($raw['services']) || !is_array($raw['services'])) {
            throw new ConfigException('Config must contain a services array');
        }

        self::rejectUnknownKeys($raw, ['services'], 'root');

        $services = [];
        foreach ($raw['services'] as $name => $def) {
            if (!is_string($name) || $name === '' || !preg_match('/^[a-z][a-z0-9-]{0,62}$/', $name)) {
                throw new ConfigException("Invalid service name: {$name}");
            }
            if (!is_array($def)) {
                throw new ConfigException("Service {$name} must be an array");
            }
            $services[] = self::parseService($name, $def, $workspaceRoot);
        }

        return $services;
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function parseService(string $name, array $def, string $workspaceRoot): Service
    {
        self::rejectUnknownKeys($def, self::ALLOWED_SERVICE_KEYS, "service.{$name}");

        if (!isset($def['dir']) || !is_string($def['dir']) || $def['dir'] === '') {
            throw new ConfigException("service.{$name}.dir is required");
        }
        $dir = self::resolvePath($def['dir'], $workspaceRoot);
        if (!is_dir($dir)) {
            throw new ConfigException("service.{$name}.dir does not exist: {$dir}");
        }

        if (!isset($def['port']) || !is_int($def['port']) || $def['port'] < 1 || $def['port'] > 65535) {
            throw new ConfigException("service.{$name}.port must be 1-65535");
        }

        if (!isset($def['commands']) || !is_array($def['commands'])) {
            throw new ConfigException("service.{$name}.commands is required");
        }
        $commands = self::parseCommands($def['commands'], $name);

        $watch = self::parseStringList($def['watch'] ?? [], "service.{$name}.watch", $workspaceRoot, true);
        $envPublic = self::parseStringMap($def['envPublic'] ?? [], "service.{$name}.envPublic");
        $envPrivate = self::parseStringMap($def['envPrivate'] ?? [], "service.{$name}.envPrivate");

        $readyPath = null;
        if (array_key_exists('readyPath', $def)) {
            if (!is_string($def['readyPath']) || $def['readyPath'] === '') {
                throw new ConfigException("service.{$name}.readyPath must be a non-empty string");
            }
            $readyPath = $def['readyPath'];
        }

        return new Service(
            name: $name,
            dir: $dir,
            port: $def['port'],
            commands: $commands,
            watch: $watch,
            envPublic: $envPublic,
            envPrivate: $envPrivate,
            readyPath: $readyPath,
            buildTimeoutSec: self::optionalInt($def, 'buildTimeoutSec', 300, "service.{$name}"),
            readyTimeoutSec: self::optionalInt($def, 'readyTimeoutSec', 30, "service.{$name}"),
            stopTimeoutSec: self::optionalInt($def, 'stopTimeoutSec', 5, "service.{$name}"),
        );
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function parseCommands(array $def, string $serviceName): Commands
    {
        self::rejectUnknownKeys($def, self::ALLOWED_COMMAND_KEYS, "service.{$serviceName}.commands");

        if (!isset($def['build']) || !is_array($def['build'])) {
            throw new ConfigException("service.{$serviceName}.commands.build is required");
        }
        if (!isset($def['start']) || !is_array($def['start'])) {
            throw new ConfigException("service.{$serviceName}.commands.start is required");
        }

        return new Commands(
            build: self::parseCommandArray($def['build'], "service.{$serviceName}.commands.build"),
            start: self::parseCommandArray($def['start'], "service.{$serviceName}.commands.start"),
        );
    }

    /**
     * @param list<mixed> $cmd
     * @return list<non-empty-string>
     */
    private static function parseCommandArray(array $cmd, string $label): array
    {
        if ($cmd === []) {
            throw new ConfigException("{$label} must not be empty");
        }
        $out = [];
        foreach ($cmd as $i => $part) {
            if (!is_string($part) || $part === '') {
                throw new ConfigException("{$label}[{$i}] must be a non-empty string");
            }
            $out[] = $part;
        }

        return $out;
    }

    /**
     * @param array<mixed, mixed> $list
     * @return list<non-empty-string>
     */
    private static function parseStringList(array $list, string $label, string $root, bool $resolvePaths): array
    {
        $out = [];
        foreach ($list as $i => $item) {
            if (!is_string($item) || $item === '') {
                throw new ConfigException("{$label}[{$i}] must be a non-empty string");
            }
            $out[] = $resolvePaths ? self::resolvePath($item, $root) : $item;
        }

        return $out;
    }

    /**
     * @param array<mixed, mixed> $map
     * @return array<non-empty-string, non-empty-string>
     */
    private static function parseStringMap(array $map, string $label): array
    {
        $out = [];
        foreach ($map as $k => $v) {
            if (!is_string($k) || $k === '') {
                throw new ConfigException("{$label} keys must be non-empty strings");
            }
            if (!is_string($v) || $v === '') {
                throw new ConfigException("{$label}.{$k} must be a non-empty string");
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function optionalInt(array $def, string $key, int $default, string $ctx): int
    {
        if (!array_key_exists($key, $def)) {
            return $default;
        }
        if (!is_int($def[$key]) || $def[$key] < 1) {
            throw new ConfigException("{$ctx}.{$key} must be a positive integer");
        }

        return $def[$key];
    }

    /**
     * @param array<string, mixed> $arr
     * @param list<string> $allowed
     */
    private static function rejectUnknownKeys(array $arr, array $allowed, string $ctx): void
    {
        foreach (array_keys($arr) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new ConfigException("Unknown key {$ctx}.{$key}");
            }
        }
    }

    private static function resolvePath(string $path, string $root): string
    {
        if ($path[0] === '/') {
            return realpath($path) ?: $path;
        }

        $full = $root . '/' . ltrim($path, '/');
        $resolved = realpath($full);

        return $resolved !== false ? $resolved : $full;
    }
}
