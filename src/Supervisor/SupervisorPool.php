<?php

declare(strict_types=1);

namespace JitRouter\Supervisor;

use JitRouter\Config\Service;
use JitRouter\Fingerprint\FingerprintCalculator;
use JitRouter\Process\ProcessRunner;
use Closure;

final class SupervisorPool
{
    /** @var array<string, ServiceSupervisor> */
    private array $supervisors = [];

    private readonly ?Closure $getEnv;

    /**
     * @param list<Service> $services
     */
    public function __construct(
        private readonly array $services,
        private readonly string $stateDir,
        ?ProcessRunner $runner = null,
        ?FingerprintCalculator $fingerprints = null,
        ?ReadinessChecker $readiness = null,
        ?callable $getEnv = null,
    ) {
        $runner ??= new ProcessRunner();
        $fingerprints ??= new FingerprintCalculator();
        $readiness ??= new ReadinessChecker();
        $this->getEnv = Closure::fromCallable(
            $getEnv ?? static fn (string $k): ?string => getenv($k) !== false ? (string) getenv($k) : null,
        );
        $getEnvCallable = $this->getEnv;
        foreach ($services as $service) {
            $this->supervisors[$service->name] = new ServiceSupervisor(
                service: $service,
                runner: $this->runner,
                fingerprints: $this->fingerprints,
                readiness: $this->readiness,
                stateDir: $stateDir,
                getEnv: $getEnvCallable,
            );
        }
    }

    public function get(string $name): ?ServiceSupervisor
    {
        return $this->supervisors[$name] ?? null;
    }

    /**
     * @return list<ServiceSupervisor>
     */
    public function all(): array
    {
        return array_values($this->supervisors);
    }
}
