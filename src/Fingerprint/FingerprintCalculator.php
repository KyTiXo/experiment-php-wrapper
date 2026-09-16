<?php

declare(strict_types=1);

namespace JitRouter\Fingerprint;

use JitRouter\Config\Service;
use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FingerprintCalculator
{
    private readonly ?Closure $hashFile;

    public function __construct(?callable $hashFile = null)
    {
        $this->hashFile = $hashFile !== null ? Closure::fromCallable($hashFile) : null;
    }

    public function calculate(Service $service, string $commandFingerprint): string
    {
        $hashFile = $this->hashFile ?? static fn (string $path, string $algo): string|false => hash_file($algo, $path);
        $parts = [
            'commands' => $commandFingerprint,
            'envPublic' => json_encode($service->envPublic, JSON_THROW_ON_ERROR),
            'envPrivateKeys' => json_encode(array_keys($service->envPrivate), JSON_THROW_ON_ERROR),
            'port' => (string) $service->port,
            'readyPath' => $service->readyPath ?? '',
        ];

        $paths = array_merge([$service->dir], $service->watch);
        $fileHashes = [];
        foreach ($this->collectWatchedFiles($paths) as $file) {
            $fileHashes[$file] = $hashFile($file, 'xxh128');
        }
        ksort($fileHashes);
        $parts['files'] = json_encode($fileHashes, JSON_THROW_ON_ERROR);

        return hash('xxh128', implode("\0", $parts));
    }

    /**
     * @param list<string> $roots
     * @return list<string>
     */
    private function collectWatchedFiles(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            if (is_file($root)) {
                if (!$this->isIgnored($root)) {
                    $files[] = $root;
                }
                continue;
            }
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if ($this->isIgnored($path)) {
                    continue;
                }
                $files[] = $path;
            }
        }
        sort($files);

        return $files;
    }

    private function isIgnored(string $path): bool
    {
        $gitignore = $this->findGitignore($path);
        if ($gitignore === null) {
            return false;
        }

        return GitignoreMatcher::isIgnored($path, $gitignore);
    }

    private function findGitignore(string $path): ?string
    {
        $dir = is_dir($path) ? $path : dirname($path);
        while ($dir !== '/' && $dir !== '') {
            $candidate = $dir . '/.gitignore';
            if (is_file($candidate)) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return null;
    }
}
