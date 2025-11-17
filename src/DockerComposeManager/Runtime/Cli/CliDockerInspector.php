<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\State\ContainerState;

/**
 * Uses the docker compose CLI to inspect running services and translate them
 * into ContainerState value objects.
 */
class CliDockerInspector implements DockerInspectorInterface
{
    private string $binary;

    /**
     * @param string|null $binary Optional override for testing.
     */
    public function __construct(?string $binary = null)
    {
        $this->binary = $binary ?? $this->detectBinary();
    }

    /**
     * @param array<string,DockerComposeConfig> $configs
     *
     * @return array<string,ContainerState>
     */
    public function describe(array $configs): array
    {
        $states = [];
        foreach ($configs as $id => $config) {
            $states[$id] = $this->inspectConfig($config);
        }

        return $states;
    }

    /**
     * Perform `docker compose ps` for a single config and convert it to state data.
     *
     * @return ContainerState
     */
    private function inspectConfig(DockerComposeConfig $config): ContainerState
    {
        $sourceFile = $config->getSourceFile();
        if ($sourceFile && is_file($sourceFile)) {
            $cmd = sprintf('%s -f %s ps --format json', $this->binary, escapeshellarg($sourceFile));
            $output = [];
            $exitCode = 1;
            @exec($cmd, $output, $exitCode);
            if ($exitCode === 0) {
                return $this->parsePsOutput($config->getId(), implode("\n", $output));
            }
        }

        return ContainerState::unknown($config->getId());
    }

    /**
     * Convert docker compose JSON lines into a ContainerState instance.
     *
     * @return ContainerState
     */
    private function parsePsOutput(string $id, string $output): ContainerState
    {
        $lines = preg_split('/\r?\n/', trim($output)) ?: [];
        $services = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $service = $decoded['Service'] ?? $decoded['Name'] ?? null;
            $state = strtolower($decoded['State'] ?? 'unknown');
            if ($service) {
                $services[$service] = $state;
            }
        }
        if (empty($services)) {
            return ContainerState::unknown($id);
        }
        $allHealthy = !in_array('unhealthy', $services, true) && !in_array('exited', $services, true);

        return $allHealthy ? ContainerState::running($id, $services) : ContainerState::unhealthy($id, $services);
    }

    /**
     * Detect docker compose binary similarly to the command builder.
     *
     * @return string
     */
    private function detectBinary(): string
    {
        foreach (['docker compose', 'docker-compose'] as $candidate) {
            $exit = 1;
            @exec($candidate . ' version', $out, $exit);
            if ($exit === 0) {
                return $candidate;
            }
        }

        return 'docker compose';
    }
}
