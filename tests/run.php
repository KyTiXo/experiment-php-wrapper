<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

final class TestRunner
{
    /** @var list<callable> */
    private array $tests = [];
    private int $passed = 0;
    private int $failed = 0;

    public function add(string $name, callable $test): void
    {
        $this->tests[] = static function () use ($name, $test): void {
            try {
                $test();
                echo "PASS {$name}\n";
            } catch (Throwable $e) {
                echo "FAIL {$name}: {$e->getMessage()}\n";
                throw $e;
            }
        };
    }

    public function run(): int
    {
        foreach ($this->tests as $test) {
            try {
                $test();
                ++$this->passed;
            } catch (Throwable) {
                ++$this->failed;
            }
        }
        echo "\n{$this->passed} passed, {$this->failed} failed\n";

        return $this->failed === 0 ? 0 : 1;
    }
}

function assertTrue(bool $cond, string $msg = ''): void
{
    if (!$cond) {
        throw new RuntimeException($msg ?: 'assertion failed');
    }
}

function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($msg ?: 'expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}

$runner = new TestRunner();
require __DIR__ . '/ConfigLoaderTest.php';
require __DIR__ . '/EnvProjectionTest.php';
require __DIR__ . '/HookRegistryTest.php';
require __DIR__ . '/RuntimeStateTest.php';
require __DIR__ . '/DiagnosticHeadersTest.php';
require __DIR__ . '/RequestParserTest.php';
require __DIR__ . '/ProcessRunnerTest.php';

exit($runner->run());
