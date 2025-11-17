<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\State\ContainerState;

/**
 * Abstraction responsible for asking Docker about container states outside of
 * docker-compose (e.g. docker inspect, ps). Allows the runtime to be tested by
 * swapping in fake inspectors.
 */
interface DockerInspectorInterface
{
    /**
     * Describe the runtime state for each configuration.
     *
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return array<string,ContainerState>
     */
    public function describe(array $configs): array;
}
