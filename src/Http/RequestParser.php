<?php

declare(strict_types=1);

namespace JitRouter\Http;

final class RequestParser
{
    /**
     * @return array{method: string, path: string, query: string, body: string, headers: array<string, list<string>>, acceptsHtml: bool}
     */
    public static function fromGlobals(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? (string) $parts['query'] : '';

        $body = file_get_contents('php://input') ?: '';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_') || !is_string($value)) {
                continue;
            }
            $name = str_replace('_', '-', substr($key, 5));
            $headers[$name][] = $value;
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'][] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $acceptsHtml = str_contains($accept, 'text/html');

        return compact('method', 'path', 'query', 'body', 'headers', 'acceptsHtml');
    }

    /**
     * @return array{service: string, subpath: string}|null
     */
    public static function parseServiceRoute(string $path): ?array
    {
        if (!preg_match('#^/api/services/([a-z][a-z0-9-]{0,62})(/.*)?$#', $path, $m)) {
            return null;
        }
        $sub = $m[2] ?? '/';
        if ($sub === '') {
            $sub = '/';
        }

        return ['service' => $m[1], 'subpath' => ltrim($sub, '/')];
    }
}
