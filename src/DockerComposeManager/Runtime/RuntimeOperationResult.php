<?php

namespace Orryv\DockerComposeManager\Runtime;

use Orryv\DockerComposeManager\State\ContainerState;

/**
 * Aggregates the result of a multi-config runtime operation including success
 * flags, collected error messages and optional ContainerState snapshots.
 */
final class RuntimeOperationResult
{
    /** @var array<string,bool> */
    public array $statusById;
    /** @var array<string,array<int,string>> */
    public array $errorsById;
    /** @var array<string,ContainerState> */
    public array $states;

    /**
     * @param array<string,bool> $statusById
     * @param array<string,array<int,string>> $errorsById
     * @param array<string,ContainerState> $states
     */
    public function __construct(array $statusById = [], array $errorsById = [], array $states = [])
    {
        $this->statusById = $statusById;
        $this->errorsById = $errorsById;
        $this->states = $states;
    }

    /**
     * @param string[] $ids
     */
    /**
     * Build a success result for the provided identifiers.
     */
    public static function success(array $ids): self
    {
        $status = [];
        foreach ($ids as $id) {
            $status[$id] = true;
        }

        return new self($status, [], []);
    }

    /**
     * @param array<string,array<int,string>> $errors
     */
    /**
     * Build a failure result with the provided error bag.
     */
    public static function failure(array $errors): self
    {
        $status = [];
        foreach ($errors as $id => $messages) {
            $status[$id] = false;
        }

        return new self($status, $errors, []);
    }

    /**
     * Determine whether every tracked id completed successfully.
     */
    public function allSuccessful(): bool
    {
        foreach ($this->statusById as $value) {
            if ($value !== true) {
                return false;
            }
        }

        return !empty($this->statusById);
    }
}
