<?php

declare(strict_types=1);

namespace JitRouter\Http;

final class ResponseEmitter
{
    /**
     * @param array<string, string> $extraHeaders
     */
    public static function emit(int $status, string $body, array $headers = [], array $extraHeaders = []): void
    {
        http_response_code($status);
        foreach ($headers as $name => $values) {
            if (is_array($values)) {
                foreach ($values as $value) {
                    header($name . ': ' . $value, false);
                }
            } else {
                header($name . ': ' . $values);
            }
        }
        foreach ($extraHeaders as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $body;
    }

    public static function emitGateway(GatewayResult $result): void
    {
        $diag = DiagnosticHeaders::fromSnapshot(
            $result->snapshot,
            $result->ensure,
            $result->proxy?->proxyMs,
        );
        self::emit($result->status, $result->body, $result->headers, $diag);
    }
}
