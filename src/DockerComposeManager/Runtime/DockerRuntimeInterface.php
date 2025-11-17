<?php

namespace Orryv\DockerComposeManager\Runtime;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;

/**
 * Contract implemented by any backend capable of managing docker-compose
 * projects (CLI runtime, test fakes, etc.).
 */
interface DockerRuntimeInterface
{
    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function start(array $configs, ComposeOperationOptions $options): RuntimeOperationResult;

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function stop(array $configs, ComposeOperationOptions $options): RuntimeOperationResult;

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function remove(array $configs, ComposeOperationOptions $options): RuntimeOperationResult;

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function restart(array $configs, ComposeOperationOptions $options): RuntimeOperationResult;

    /**
     * @param array<string,DockerComposeConfig> $configs
     * @return array<string,mixed>
     */
    public function inspect(array $configs, ?string $serviceName = null): array;

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function containerExists(array $configs, ?string $serviceName = null): bool;

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function volumesExist(array $configs): bool;

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function imagesExist(array $configs): bool;

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function isRunning(array $configs, ?string $serviceName = null): bool;

    /**
     * @param array<string,DockerComposeConfig> $configs
     * @return array<int,mixed>
     */
    public function listVolumes(array $configs): array;

    /**
     * @param array<string,DockerComposeConfig> $configs
     * @return array<int,mixed>
     */
    public function listImages(array $configs): array;

    /**
     * @param array<string,DockerComposeConfig> $configs
     * @return array<string,\Orryv\DockerComposeManager\State\ContainerState>
     */
    public function describe(array $configs): array;
}
