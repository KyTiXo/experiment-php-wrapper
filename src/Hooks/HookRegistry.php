<?php

declare(strict_types=1);

namespace JitRouter\Hooks;

final class HookRegistry
{
    /** @var array<string, list<callable>> */
    private array $hooks = [];

    /**
     * @param callable(mixed...): mixed $callback
     */
    public function register(string $name, callable $callback, int $priority = 10): void
    {
        if (!isset($this->hooks[$name])) {
            $this->hooks[$name] = [];
        }
        $this->hooks[$name][] = ['priority' => $priority, 'callback' => $callback];
        usort($this->hooks[$name], static fn ($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * @param array<string, mixed> $context
     * @return mixed
     */
    public function run(string $name, array $context = []): mixed
    {
        $result = null;
        foreach ($this->hooks[$name] ?? [] as $entry) {
            $result = ($entry['callback'])(...array_values($context));
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $headers
     * @return array<string, list<string>>
     */
    public function applyRequestHeaders(string $serviceName, array $headers): array
    {
        $hook = "service.{$serviceName}.request.headers";
        $result = $this->run($hook, ['headers' => $headers]);
        if (is_array($result)) {
            return self::normalizeHeaders($result);
        }

        return $headers;
    }

    /**
     * @param array<string, list<string>> $headers
     * @return array<string, list<string>>
     */
    public function applyResponseHeaders(string $serviceName, array $headers): array
    {
        $hook = "service.{$serviceName}.response.headers";
        $result = $this->run($hook, ['headers' => $headers]);
        if (is_array($result)) {
            return self::normalizeHeaders($result);
        }

        return $headers;
    }

    /**
     * @return list<string> at most two escaped lines
     */
    public function statusLines(string $serviceName): array
    {
        $hook = "service.{$serviceName}.status.lines";
        $result = $this->run($hook);
        if (!is_array($result)) {
            return [];
        }
        $lines = [];
        foreach (array_slice($result, 0, 2) as $line) {
            if (is_string($line)) {
                $lines[] = htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function after(string $serviceName, array $context): void
    {
        $hook = "service.{$serviceName}.after";
        $this->run($hook, $context);
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, list<string>>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            if (is_string($value)) {
                $out[$name] = [$value];
            } elseif (is_array($value)) {
                $out[$name] = array_map(strval(...), $value);
            }
        }

        return $out;
    }
}
