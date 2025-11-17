<?php

namespace Orryv\DockerComposeManager\Runtime;

/**
 * Value object capturing all optional toggles that can be passed to runtime
 * compose operations (service filtering, rebuild flags, health timeouts, etc.).
 */
final class ComposeOperationOptions
{
    public ?string $serviceName;
    public bool $rebuild;
    public bool $removeVolumes;
    public ?string $removeImages;
    /** @var string[] */
    public array $flags;
    public bool $requireHealthy;
    public int $healthTimeout;
    /** @var array<string,mixed> */
    public array $extra;

    /**
     * Bundle every optional toggle in a single immutable structure.
     *
     * @param string|null $serviceName
     * @param bool $rebuild
     * @param bool $removeVolumes
     * @param string|null $removeImages
     * @param array<int,string> $flags
     * @param bool $requireHealthy
     * @param int $healthTimeout
     * @param array<string,mixed> $extra
     */
    public function __construct(
        ?string $serviceName = null,
        bool $rebuild = false,
        bool $removeVolumes = false,
        ?string $removeImages = null,
        array $flags = [],
        bool $requireHealthy = true,
        int $healthTimeout = 120,
        array $extra = []
    ) {
        $this->serviceName = $serviceName;
        $this->rebuild = $rebuild;
        $this->removeVolumes = $removeVolumes;
        $this->removeImages = $removeImages;
        $this->flags = $flags;
        $this->requireHealthy = $requireHealthy;
        $this->healthTimeout = $healthTimeout;
        $this->extra = $extra;
    }

    /**
     * Convenience factory for start/restart operations.
     *
     * @param array<string,mixed> $options
     *
     * @return self
     */
    public static function forStart(?string $serviceName, bool $rebuild, int $healthTimeout, array $options = []): self
    {
        return new self(
            $serviceName,
            $rebuild,
            (bool) ($options['remove_volumes'] ?? false),
            $options['remove_images'] ?? null,
            $options['flags'] ?? [],
            (bool) ($options['require_healthy'] ?? true),
            $healthTimeout,
            $options
        );
    }

    /**
     * Convenience factory for stop/remove operations.
     *
     * @param array<string,mixed> $options
     *
     * @return self
     */
    public static function forStop(?string $serviceName, bool $removeVolumes, ?string $removeImages, array $options = []): self
    {
        return new self(
            $serviceName,
            false,
            $removeVolumes,
            $removeImages,
            $options['flags'] ?? [],
            false,
            (int) ($options['health_timeout'] ?? 30),
            $options
        );
    }
}
