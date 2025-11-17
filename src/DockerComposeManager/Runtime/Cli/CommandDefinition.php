<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

/**
 * Immutable description of a single CLI invocation (command string, working
 * directory and environment variables) that the runtime should execute.
 */
final class CommandDefinition
{
    public string $command;
    public string $workingDirectory;
    /** @var array<string,string> */
    public array $environment;

    /**
     * @param array<string,string> $environment
     */
    public function __construct(string $command, string $workingDirectory, array $environment = [])
    {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environment = $environment;
    }
}
