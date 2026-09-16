<?php

declare(strict_types=1);

namespace JitRouter\Fingerprint;

final class GitignoreMatcher
{
    /**
     * @var list<string>|null
     */
    private static ?array $patterns = null;

    public static function isIgnored(string $path, string $gitignorePath): bool
    {
        $patterns = self::loadPatterns($gitignorePath);
        $relative = self::relativeToRepo($path, dirname($gitignorePath));

        foreach ($patterns as $pattern) {
            if (self::matches($relative, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function loadPatterns(string $gitignorePath): array
    {
        if (self::$patterns !== null && self::$patterns['path'] === $gitignorePath) {
            return self::$patterns['list'];
        }

        $lines = file($gitignorePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $patterns = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $patterns[] = $line;
        }

        self::$patterns = ['path' => $gitignorePath, 'list' => $patterns];

        return $patterns;
    }

    private static function relativeToRepo(string $path, string $repoRoot): string
    {
        $realPath = realpath($path) ?: $path;
        $realRoot = realpath($repoRoot) ?: $repoRoot;
        if (str_starts_with($realPath, $realRoot . '/')) {
            return substr($realPath, strlen($realRoot) + 1);
        }

        return basename($realPath);
    }

    private static function matches(string $relative, string $pattern): bool
    {
        $negated = str_starts_with($pattern, '!');
        if ($negated) {
            $pattern = substr($pattern, 1);
        }
        $pattern = str_replace(['.', '*'], ['\\.', '.*'], $pattern);
        $pattern = '#^' . $pattern . '$#';

        return (bool) preg_match($pattern, $relative);
    }
}
