<?php

declare(strict_types=1);

namespace JitRouter\Http;

use JitRouter\Hooks\HookRegistry;

final class HttpProxy
{
    private const HOP_BY_HOP = [
        'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
        'te', 'trailers', 'transfer-encoding', 'upgrade',
    ];

    public function __construct(
        private readonly HookRegistry $hooks,
    ) {
    }

    /**
     * @param array<string, list<string>> $requestHeaders
     * @param array<string, mixed> $hookContext
     */
    public function forward(
        string $serviceName,
        string $method,
        string $upstreamUrl,
        string $body,
        array $requestHeaders,
        array $hookContext = [],
    ): ProxyResponse {
        $proxyStart = hrtime(true);
        $requestHeaders = $this->hooks->applyRequestHeaders($serviceName, $requestHeaders);

        $headerLines = [];
        foreach ($requestHeaders as $name => $values) {
            foreach ($values as $value) {
                if (in_array(strtolower($name), self::HOP_BY_HOP, true)) {
                    continue;
                }
                $headerLines[] = $name . ': ' . $value;
            }
        }

        $ch = curl_init($upstreamUrl);
        if ($ch === false) {
            return new ProxyResponse(502, [], 'curl init failed', 0);
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POSTFIELDS => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? $body : null,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);

            return new ProxyResponse(502, [], $err, (int) ((hrtime(true) - $proxyStart) / 1_000_000));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);
        $headers = self::parseHeaders($rawHeaders);
        $headers = $this->hooks->applyResponseHeaders($serviceName, $headers);

        $proxyMs = (int) ((hrtime(true) - $proxyStart) / 1_000_000);

        $this->hooks->after($serviceName, array_merge($hookContext, [
            'status' => $status,
            'proxyMs' => $proxyMs,
        ]));

        return new ProxyResponse($status, $headers, $responseBody, $proxyMs);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (explode("\r\n", $raw) as $line) {
            if ($line === '' || str_starts_with($line, 'HTTP/')) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $headers[$name][] = $value;
        }

        return $headers;
    }
}
