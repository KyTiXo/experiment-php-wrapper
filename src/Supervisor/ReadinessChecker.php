<?php

declare(strict_types=1);

namespace JitRouter\Supervisor;

final class ReadinessChecker
{
    public function isReady(
        string $host,
        int $port,
        ?string $httpPath,
        int $timeoutSec,
    ): bool {
        $deadline = time() + $timeoutSec;
        while (time() <= $deadline) {
            if ($httpPath !== null) {
                $url = sprintf('http://%s:%d%s', $host, $port, $httpPath);
                $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
                $body = @file_get_contents($url, false, $ctx);
                if ($body !== false) {
                    $code = $this->lastHttpCode($http_response_header ?? []);
                    if ($code >= 200 && $code < 400) {
                        return true;
                    }
                }
            } else {
                $fp = @fsockopen($host, $port, $errno, $errstr, 1.0);
                if ($fp !== false) {
                    fclose($fp);

                    return true;
                }
            }
            usleep(200_000);
        }

        return false;
    }

    /**
     * @param list<string> $headers
     */
    private function lastHttpCode(array $headers): int
    {
        foreach ($headers as $h) {
            if (preg_match('#HTTP/\d\.\d\s+(\d+)#', $h, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }
}
