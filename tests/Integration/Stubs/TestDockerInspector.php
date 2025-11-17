<?php

namespace Tests\Integration\Stubs;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\Runtime\Cli\DockerInspectorInterface;
use Orryv\DockerComposeManager\State\ContainerState;

class TestDockerInspector implements DockerInspectorInterface
{
    private bool $initial = true;

    /**
     * @param array<string,DockerComposeConfig> $configs
     * @return array<string,ContainerState>
     */
    public function describe(array $configs): array
    {
        $states = [];
        foreach ($configs as $id => $config) {
            if ($this->initial) {
                $states[$id] = ContainerState::unknown($id);
            } else {
                $states[$id] = ContainerState::running($id, ['worker' => 'healthy']);
            }
        }
        $this->initial = false;

        return $states;
    }
}
