<?php

namespace Tests\Integration\Stubs;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\Runtime\Cli\CommandDefinition;
use Orryv\DockerComposeManager\Runtime\Cli\ComposeCommandBuilderInterface;
use Orryv\DockerComposeManager\Runtime\ComposeOperationOptions;

class TestCommandBuilder implements ComposeCommandBuilderInterface
{
    public function build(string $operation, DockerComposeConfig $config, ComposeOperationOptions $options, string $composeFile): CommandDefinition
    {
        $script = escapeshellarg(__DIR__ . '/fake-docker.php');
        $command = 'php ' . $script . ' ' . escapeshellarg($operation);

        return new CommandDefinition($command, dirname($composeFile));
    }
}
