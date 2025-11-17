<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\Runtime\ComposeOperationOptions;

/**
 * Responsible for turning high-level operations into executable CLI commands.
 */
interface ComposeCommandBuilderInterface
{
    /**
     * Build a command definition for docker-compose.
     *
     * @param string $operation Operation name (start/stop/etc.).
     * @param DockerComposeConfig $config
     * @param ComposeOperationOptions $options
     * @param string $composeFile Path to the temp compose file.
     */
    public function build(string $operation, DockerComposeConfig $config, ComposeOperationOptions $options, string $composeFile): CommandDefinition;
}
