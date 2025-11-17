<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\Runtime\ComposeOperationOptions;
use Orryv\DockerComposeManager\Runtime\DockerRuntimeInterface;
use Orryv\DockerComposeManager\Runtime\RuntimeOperationResult;
use Orryv\DockerComposeManager\State\ContainerState;
use RuntimeException;

/**
 * Concrete runtime that shells out to docker compose, streams logs to disk,
 * and aggregates health/status information for each registered configuration.
 */
class CliDockerRuntime implements DockerRuntimeInterface
{
    /**
     * @param int $pollIntervalMs Sleep between log polling iterations.
     * @param int $operationTimeoutSeconds Max seconds before compose commands are cancelled.
     */
    public function __construct(
        private readonly ComposeCommandBuilderInterface $builder,
        private readonly DockerInspectorInterface $inspector,
        private readonly DockerOutputParser $parser,
        private int $pollIntervalMs = 250,
        private int $operationTimeoutSeconds = 600
    ) {
    }

    /**
     * Launch containers by executing docker compose up for each config.
     *
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function start(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->runOperation('start', $configs, $options);
    }

    /**
     * Stop running containers by executing docker compose stop.
     *
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function stop(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->runOperation('stop', $configs, $options, false);
    }

    /**
     * Remove containers/networks/volumes using docker compose down.
     *
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function remove(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->runOperation('remove', $configs, $options, false);
    }

    /**
     * Restart running containers.
     *
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return RuntimeOperationResult
     */
    public function restart(array $configs, ComposeOperationOptions $options): RuntimeOperationResult
    {
        return $this->runOperation('restart', $configs, $options);
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return array<string,mixed>
     */
    public function inspect(array $configs, ?string $serviceName = null): array
    {
        $states = $this->describe($configs);
        $result = [];
        foreach ($states as $id => $state) {
            $services = $state->getServices();
            if ($serviceName !== null && isset($services[$serviceName])) {
                $result[$id] = [$serviceName => $services[$serviceName]];
            } else {
                $result[$id] = $services;
            }
        }

        return $result;
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function containerExists(array $configs, ?string $serviceName = null): bool
    {
        foreach ($this->describe($configs) as $state) {
            if ($state->getStatus() !== ContainerState::STATUS_UNKNOWN) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function volumesExist(array $configs): bool
    {
        return !empty($this->listVolumes($configs));
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function imagesExist(array $configs): bool
    {
        return !empty($this->listImages($configs));
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return bool
     */
    public function isRunning(array $configs, ?string $serviceName = null): bool
    {
        foreach ($this->describe($configs) as $state) {
            if ($serviceName !== null) {
                $services = $state->getServices();
                $serviceState = strtolower((string) ($services[$serviceName] ?? ''));
                if (in_array($serviceState, ['running', 'healthy', 'up'], true)) {
                    return true;
                }
            } elseif ($state->isRunning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return array<int,string>
     */
    public function listVolumes(array $configs): array
    {
        return $this->runSimpleCommand('docker volume ls --format {{.Name}}');
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return array<int,string>
     */
    public function listImages(array $configs): array
    {
        return $this->runSimpleCommand('docker images --format {{.Repository}}:{{.Tag}}');
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return array<string,ContainerState>
     */
    public function describe(array $configs): array
    {
        return $this->inspector->describe($configs);
    }

    /**
     * Execute a docker compose command for multiple configs in parallel.
     *
     * @param array<string,DockerComposeConfig> $configs
     */
    private function runOperation(string $operation, array $configs, ComposeOperationOptions $options, bool $waitForHealth = true): RuntimeOperationResult
    {
        $handles = [];
        foreach ($configs as $id => $config) {
            $composeFile = $config->createTemporaryComposeFile();
            $logFile = $config->createTemporaryLogFile($operation);
            $definition = $this->builder->build($operation, $config, $options, $composeFile);
            $process = $this->startProcess($definition, $logFile);
            $handles[$id] = [
                'config' => $config,
                'composeFile' => $composeFile,
                'logFile' => $logFile,
                'definition' => $definition,
                'process' => $process,
                'last_progress' => 0.0,
            ];
            $this->triggerInitialProgress($operation, $id, $handles[$id]);
        }

        $status = [];
        $errors = [];
        $start = microtime(true);
        while (!empty($handles)) {
            foreach ($handles as $id => &$handle) {
                $this->parseLog($operation, $id, $handle, $errors);
                if (!$this->isProcessRunning($handle['process'])) {
                    // Parse one more time to catch trailing output written right before exit
                    $this->parseLog($operation, $id, $handle, $errors);
                    $exitCode = $this->closeProcess($handle['process']);
                    $status[$id] = $exitCode === 0;
                    if (!$status[$id]) {
                        $errors[$id][] = 'Process exited with code ' . $exitCode;
                    }
                    $handle['config']->persistDebugArtifacts($operation, $handle['composeFile'], $handle['logFile']);
                    $handle['config']->removeTmpFiles();
                    unset($handles[$id]);
                }
            }
            unset($handle);
            if (empty($handles)) {
                break;
            }
            if ((microtime(true) - $start) > $this->operationTimeoutSeconds) {
                foreach ($handles as $id => $handle) {
                    $errors[$id][] = 'Operation timed out';
                    $this->terminateProcess($handle['process']);
                    $handle['config']->persistDebugArtifacts($operation, $handle['composeFile'], $handle['logFile']);
                    $handle['config']->removeTmpFiles();
                }
                $handles = [];
                break;
            }
            usleep($this->pollIntervalMs * 1000);
        }

        $states = $waitForHealth && $options->requireHealthy
            ? $this->waitForHealth($configs, $options->healthTimeout)
            : $this->describe($configs);

        foreach ($states as $id => $state) {
            $status[$id] = ($status[$id] ?? true) && ($options->requireHealthy ? $state->isHealthy() : true);
            if ($options->requireHealthy && !$state->isHealthy()) {
                $errors[$id][] = 'Health check failed for ' . $id;
            }
        }

        return new RuntimeOperationResult($status, $errors, $states);
    }

    /**
     * Tail the log file for a process, update callbacks and collect error lines.
     *
     * @param array<string,mixed> $handle
     * @param array<string,array<int,string>> $errors
     */
    private function parseLog(string $operation, string $id, array &$handle, array &$errors): void
    {
        $logContent = @file_get_contents($handle['logFile']);
        if ($logContent === false) {
            return;
        }
        $parsed = $logContent === ''
            ? ['events' => [], 'errors' => [], 'lines' => []]
            : $this->parser->parse($logContent);
        if (!empty($parsed['errors'])) {
            $errors[$id] = array_values(array_unique(array_merge($errors[$id] ?? [], $parsed['errors'])));
        }
        $progress = $handle['config']->getProgressCallback();
        if ($progress) {
            [$callback, $interval] = $progress;
            $now = microtime(true);
            if ($now - $handle['last_progress'] >= ($interval / 1000)) {
                $callback($id, $parsed['events'], $operation);
                $handle['last_progress'] = $now;
            }
        }
    }

    /**
     * Immediately invoke progress callbacks once to indicate the command started.
     *
     * @param array<string,mixed> $handle
     */
    private function triggerInitialProgress(string $operation, string $id, array &$handle): void
    {
        $progress = $handle['config']->getProgressCallback();
        if (!$progress) {
            return;
        }

        [$callback, $interval] = $progress;
        $callback($id, [], $operation);
        $handle['last_progress'] = microtime(true);
    }

    /**
     * Spawn the docker compose process and direct stdout/stderr to a log file.
     *
     * @return resource
     */
    private function startProcess(CommandDefinition $definition, string $logFile)
    {
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $command = $isWin ? 'cmd.exe /C ' . $definition->command : $definition->command;
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];
        $env = array_merge($this->getProcessEnv(), $definition->environment);
        $proc = proc_open($command, $descriptors, $pipes, $definition->workingDirectory, $env);
        if (!is_resource($proc)) {
            throw new RuntimeException('Failed to start docker compose process.');
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        return $proc;
    }

    /**
     * @param resource $process
     *
     * @return bool
     */
    private function isProcessRunning($process): bool
    {
        $status = proc_get_status($process);

        return $status['running'] ?? false;
    }

    /**
     * @param resource $process
     *
     * @return int
     */
    private function closeProcess($process): int
    {
        $status = proc_get_status($process);
        $code = $status['exitcode'] ?? 0;
        proc_close($process);

        return $code;
    }

    /**
     * @param resource $process
     */
    private function terminateProcess($process): void
    {
        @proc_terminate($process);
        @proc_close($process);
    }

    /**
     * Poll docker until all containers report healthy or the timeout elapses.
     *
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return array<string,ContainerState>
     */
    private function waitForHealth(array $configs, int $timeout): array
    {
        $deadline = microtime(true) + $timeout;
        $states = [];
        do {
            $states = $this->describe($configs);
            $allHealthy = true;
            foreach ($states as $state) {
                if (!$state->isRunning() || !$state->isHealthy()) {
                    $allHealthy = false;
                    break;
                }
            }
            if ($allHealthy) {
                break;
            }
            usleep($this->pollIntervalMs * 1000);
        } while (microtime(true) < $deadline);

        return $states;
    }

    /**
     * @return array<string,string>
     */
    private function getProcessEnv(): array
    {
        $env = getenv();
        if (!is_array($env)) {
            return [];
        }

        return $env;
    }

    /**
     * Execute a read-only docker command and return trimmed output lines.
     *
     * @return array<int,string>
     */
    private function runSimpleCommand(string $command): array
    {
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $output)));
    }
}
