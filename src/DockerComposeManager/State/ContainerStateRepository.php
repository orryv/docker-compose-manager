<?php

namespace Orryv\DockerComposeManager\State;

/**
 * Simple in-memory cache keyed by compose id storing ContainerState objects.
 */
final class ContainerStateRepository
{
    /** @var array<string,ContainerState> */
    private array $states = [];

    /**
     * Persist/update the state for a given id.
     */
    public function set(ContainerState $state): void
    {
        $this->states[$state->getId()] = $state;
    }

    /**
     * Merge multiple state objects into the repository.
     *
     * @param array<string,ContainerState> $states
     */
    public function merge(array $states): void
    {
        foreach ($states as $state) {
            $this->set($state);
        }
    }

    /**
     * @return ContainerState|null
     */
    public function get(string $id): ?ContainerState
    {
        return $this->states[$id] ?? null;
    }

    /**
     * @param string[] $ids
     *
     * @return bool
     */
    public function allRunning(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }

        foreach ($ids as $id) {
            $state = $this->get($id);
            if (!$state || !$state->isRunning() || !$state->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string,ContainerState>
     */
    public function all(): array
    {
        return $this->states;
    }
}
