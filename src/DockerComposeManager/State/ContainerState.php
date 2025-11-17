<?php

namespace Orryv\DockerComposeManager\State;

/**
 * Immutable snapshot describing the overall status/health of a compose
 * configuration plus per-service information.
 */
final class ContainerState
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_UNKNOWN = 'unknown';

    public const HEALTH_HEALTHY = 'healthy';
    public const HEALTH_UNHEALTHY = 'unhealthy';
    public const HEALTH_STARTING = 'starting';
    public const HEALTH_UNKNOWN = 'unknown';

    private string $status;
    private string $health;

    /** @var array<string,string> */
    private array $services;

    private string $id;

    /**
     * @param array<string,string> $services
     */
    private function __construct(string $id, string $status, string $health, array $services)
    {
        $this->id = $id;
        $this->status = $status;
        $this->health = $health;
        $this->services = $services;
    }

    /**
     * @param array<string,string> $services
     */
    public static function running(string $id, array $services = []): self
    {
        return new self($id, self::STATUS_RUNNING, self::HEALTH_HEALTHY, $services);
    }

    /**
     * @param array<string,string> $services
     */
    public static function stopped(string $id, array $services = []): self
    {
        return new self($id, self::STATUS_STOPPED, self::HEALTH_UNKNOWN, $services);
    }

    /**
     * @param array<string,string> $services
     */
    public static function unhealthy(string $id, array $services = []): self
    {
        return new self($id, self::STATUS_RUNNING, self::HEALTH_UNHEALTHY, $services);
    }

    /**
     * @param array<string,string> $services
     */
    public static function unknown(string $id, array $services = []): self
    {
        return new self($id, self::STATUS_UNKNOWN, self::HEALTH_UNKNOWN, $services);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getHealth(): string
    {
        return $this->health;
    }

    /**
     * @return array<string,string>
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * @return bool
     */
    public function isHealthy(): bool
    {
        if ($this->health === self::HEALTH_HEALTHY) {
            return true;
        }

        if (empty($this->services)) {
            return false;
        }

        foreach ($this->services as $health) {
            if ($health !== self::HEALTH_HEALTHY) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{id:string,status:string,health:string,services:array<string,string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'health' => $this->health,
            'services' => $this->services,
        ];
    }
}
