<?php

namespace Tests\Unit\Fakes;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\Runtime\ComposeOperationOptions;
use Orryv\DockerComposeManager\Runtime\DockerRuntimeInterface;
use Orryv\DockerComposeManager\Runtime\RuntimeOperationResult;
use Orryv\DockerComposeManager\State\ContainerState;

class FakeDockerRuntime implements DockerRuntimeInterface
{
    /** @var array<string,array<int,RuntimeOperationResult>> */
    private array $queuedResults = [];

    /** @var array<string,ContainerState> */
    private array $states = [];

    /** @var array<int,array{operation:string,ids:array}> */
    public array $calls = [];

    public function queueResult(string $operation, RuntimeOperationResult $result): void
    {
        $this->queuedResults[$operation][] = $result;
    }

    public function setState(string $id, ContainerState $state): void
    {
        $this->states[$id] = $state;
    }

    public function start(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->consume('start', $configs);
    }

    public function stop(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->consume('stop', $configs);
    }

    public function remove(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->consume('remove', $configs);
    }

    public function restart(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->consume('restart', $configs);
    }

    public function inspect(array $configs, ?string $serviceName = null): array
    {
        $this->calls[] = ['operation' => 'inspect', 'ids' => array_keys($configs)];
        return array_map(fn ($id) => ['id' => $id, 'service' => $serviceName], array_keys($configs));
    }

    public function containerExists(array $configs, ?string $serviceName = null): bool
    {
        $this->calls[] = ['operation' => 'containerExists', 'ids' => array_keys($configs)];
        return true;
    }

    public function volumesExist(array $configs): bool
    {
        $this->calls[] = ['operation' => 'volumesExist', 'ids' => array_keys($configs)];
        return true;
    }

    public function imagesExist(array $configs): bool
    {
        $this->calls[] = ['operation' => 'imagesExist', 'ids' => array_keys($configs)];
        return true;
    }

    public function isRunning(array $configs, ?string $serviceName = null): bool
    {
        $this->calls[] = ['operation' => 'isRunning', 'ids' => array_keys($configs)];
        return true;
    }

    public function listVolumes(array $configs): array
    {
        $this->calls[] = ['operation' => 'listVolumes', 'ids' => array_keys($configs)];
        return [];
    }

    public function listImages(array $configs): array
    {
        $this->calls[] = ['operation' => 'listImages', 'ids' => array_keys($configs)];
        return [];
    }

    public function describe(array $configs): array
    {
        $this->calls[] = ['operation' => 'describe', 'ids' => array_keys($configs)];
        $states = [];
        foreach ($configs as $id => $config) {
            $states[$id] = $this->states[$id] ?? ContainerState::unknown($id);
        }
        return $states;
    }

    private function consume(string $operation, array $configs): RuntimeOperationResult
    {
        $this->calls[] = ['operation' => $operation, 'ids' => array_keys($configs)];
        if (!empty($this->queuedResults[$operation])) {
            return array_shift($this->queuedResults[$operation]);
        }

        return RuntimeOperationResult::success(array_keys($configs));
    }
}
