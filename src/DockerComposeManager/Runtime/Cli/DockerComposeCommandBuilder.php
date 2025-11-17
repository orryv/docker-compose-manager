<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\Runtime\ComposeOperationOptions;

/**
 * Default implementation that constructs docker compose CLI commands and
 * automatically detects whether "docker compose" or "docker-compose" is available.
 */
class DockerComposeCommandBuilder implements ComposeCommandBuilderInterface
{
    private string $binary;

    /**
     * @param string|null $binary Explicit binary override for testing/non-standard paths.
     */
    public function __construct(?string $binary = null)
    {
        $this->binary = $binary ?? $this->detectBinary();
    }

    /**
     * Translate a high-level operation into a runnable CLI command.
     *
     * @return CommandDefinition
     */
    public function build(string $operation, DockerComposeConfig $config, ComposeOperationOptions $options, string $composeFile): CommandDefinition
    {
        $file = escapeshellarg($composeFile);
        $base = sprintf('%s -f %s', $this->binary, $file);
        $command = $base . ' ' . $this->mapOperation($operation, $options);

        return new CommandDefinition($command, $config->getWorkingDirectory(), $config->getEnvVariables());
    }

    /**
     * Map friendly operation names onto docker compose commands/flags.
     *
     * @return string
     */
    private function mapOperation(string $operation, ComposeOperationOptions $options): string
    {
        $service = $options->serviceName ? escapeshellarg($options->serviceName) : '';
        $flags = $options->flags ? implode(' ', $options->flags) . ' ' : '';
        return match ($operation) {
            'start' => $flags . 'up -d' . ($options->rebuild ? ' --build' : '') . ($service ? ' ' . $service : ''),
            'stop' => $flags . 'stop' . ($service ? ' ' . $service : ''),
            'remove' => $flags . 'down' . $this->appendRemovalFlags($options) . ($service ? ' ' . $service : ''),
            'restart' => $flags . 'restart' . ($service ? ' ' . $service : ''),
            default => $flags . $operation,
        };
    }

    /**
     * Build the optional remove flags when running `down` operations.
     *
     * @return string
     */
    private function appendRemovalFlags(ComposeOperationOptions $options): string
    {
        $parts = [];
        if ($options->removeVolumes) {
            $parts[] = '--volumes';
        }
        if ($options->removeImages) {
            $parts[] = '--rmi ' . $options->removeImages;
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    /**
     * Best-effort detection for whether docker compose v2 or legacy docker-compose exists.
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
