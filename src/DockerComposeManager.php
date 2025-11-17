<?php

namespace Orryv;

use InvalidArgumentException;
use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\Runtime\Cli\CliDockerInspector;
use Orryv\DockerComposeManager\Runtime\Cli\CliDockerRuntime;
use Orryv\DockerComposeManager\Runtime\Cli\DockerComposeCommandBuilder;
use Orryv\DockerComposeManager\Runtime\Cli\DockerOutputParser;
use Orryv\DockerComposeManager\Runtime\ComposeOperationOptions;
use Orryv\DockerComposeManager\Runtime\DockerRuntimeInterface;
use Orryv\DockerComposeManager\Runtime\RuntimeOperationResult;
use Orryv\DockerComposeManager\State\ContainerStateRepository;
use Orryv\DockerComposeManager\State\ContainerState;
use Orryv\DockerComposeManager\Yaml\YamlAdapter;

/**
 * Primary orchestration service that registers compose configurations, keeps
 * their state snapshot, and proxies lifecycle operations to the configured
 * runtime implementation.
 */
class DockerComposeManager
{
    private DockerRuntimeInterface $runtime;

    /** @var array<string,DockerComposeConfig> */
    private array $configs = [];

    private ContainerStateRepository $states;

    /** @var array<string,array<int,string>> */
    private array $errors = [];

    private int $defaultHealthTimeout = 120;

    /**
     * Bootstrap the manager with a runtime implementation.
     *
     * @param DockerRuntimeInterface|null $runtime Optional runtime (defaults to CLI runtime).
     */
    public function __construct(?DockerRuntimeInterface $runtime = null)
    {
        $this->runtime = $runtime ?? new CliDockerRuntime(
            new DockerComposeCommandBuilder(),
            new CliDockerInspector(),
            new DockerOutputParser()
        );
        $this->states = new ContainerStateRepository();
    }

    /**
     * Define the fallback timeout while waiting for container healthchecks.
     *
     * @param int $seconds Number of seconds before failing operations.
     */
    public function setDefaultHealthTimeout(int $seconds): void
    {
        $this->defaultHealthTimeout = max(1, $seconds);
    }

    /**
     * Register a docker-compose.yml file under a readable identifier.
     *
     * @param string $id Friendly handle used when invoking operations later.
     * @param string $filePath Absolute or relative path to the compose file.
     *
     * @return DockerComposeConfig Mutable config object for chaining adjustments.
     */
    public function fromDockerComposeFile(string $id, string $filePath): DockerComposeConfig
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException("Docker compose file '{$filePath}' not found.");
        }
        $data = YamlAdapter::parseFile($filePath);
        $config = new DockerComposeConfig(
            $id,
            dirname($filePath),
            is_array($data) ? $data : [],
            DockerComposeConfig::TYPE_FILE,
            $filePath
        );

        return $this->register($config);
    }

    /**
     * Register an in-memory YAML array as a docker compose configuration.
     *
     * @param string $id Identifier used to refer to the configuration later.
     * @param array<string,mixed> $yamlArray Raw compose structure.
     *
     * @return DockerComposeConfig Config object for further mutation.
     */
    public function fromYamlArray(string $id, array $yamlArray): DockerComposeConfig
    {
        $config = new DockerComposeConfig($id, getcwd() ?: sys_get_temp_dir(), $yamlArray, DockerComposeConfig::TYPE_ARRAY);

        return $this->register($config);
    }

    /**
     * Register an existing container by name for inspection-only operations.
     *
     * @param string $id Identifier used to refer to the configuration later.
     * @param string $containerName Name of the already-running container.
     *
     * @return DockerComposeConfig Config object for further chaining.
     */
    public function fromContainerName(string $id, string $containerName): DockerComposeConfig
    {
        $config = new DockerComposeConfig($id, getcwd() ?: sys_get_temp_dir(), ['container_name' => $containerName], DockerComposeConfig::TYPE_CONTAINER);

        return $this->register($config);
    }

    /**
     * Register a compose project using only its project name for read-only operations.
     *
     * @param string $id Identifier used to refer to the configuration later.
     * @param string $projectName Compose project name.
     *
     * @return DockerComposeConfig Config object for further chaining.
     */
    public function fromProjectName(string $id, string $projectName): DockerComposeConfig
    {
        $config = new DockerComposeConfig($id, getcwd() ?: sys_get_temp_dir(), ['name' => $projectName], DockerComposeConfig::TYPE_PROJECT);

        return $this->register($config);
    }

    /**
     * Start containers for the selected configurations and wait for health checks.
     *
     * @param string|array|null $id Single id, list of ids or null to target all startable configs.
     * @param string|null $serviceName Optional service to limit the compose command to.
     * @param bool $rebuild Whether to rebuild images prior to starting.
     * @param array<string,mixed> $options Additional runtime flags (e.g. custom timeouts).
     *
     * @return bool True when every targeted container became healthy.
     */
    public function start($id = null, ?string $serviceName = null, bool $rebuild = false, array $options = []): bool
    {
        $configs = $this->resolveConfigs($id, true);
        if (empty($configs)) {
            return false;
        }
        if ($this->states->allRunning(array_keys($configs))) {
            return true;
        }
        $operationOptions = ComposeOperationOptions::forStart($serviceName, $rebuild, $options['health_timeout'] ?? $this->defaultHealthTimeout, $options);
        $result = $this->runtime->start($configs, $operationOptions);
        $this->afterOperation('start', $configs, $result);

        return $result->allSuccessful();
    }

    /**
     * Stop running containers for the selected configurations.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     * @param string|null $serviceName Optional service name to limit stopping.
     * @param bool $removeVolumes Whether to remove volumes as part of the stop.
     * @param string|null $removeImages Optional image pruning directive accepted by docker compose.
     * @param array<string,mixed> $options Additional runtime flags.
     *
     * @return bool True when the runtime reports success for every id.
     */
    public function stop($id = null, ?string $serviceName = null, bool $removeVolumes = false, ?string $removeImages = null, array $options = []): bool
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return false;
        }
        $operationOptions = ComposeOperationOptions::forStop($serviceName, $removeVolumes, $removeImages, $options);
        $result = $this->runtime->stop($configs, $operationOptions);
        $this->afterOperation('stop', $configs, $result);

        return $result->allSuccessful();
    }

    /**
     * Remove stopped containers and optionally volumes/images.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     * @param string|null $serviceName Optional service selector.
     * @param bool $removeVolumes Whether to delete volumes.
     * @param string|null $removeImages Optional image pruning directive.
     * @param array<string,mixed> $options Additional runtime flags.
     *
     * @return bool True when the runtime reports success for every id.
     */
    public function remove($id = null, ?string $serviceName = null, bool $removeVolumes = false, ?string $removeImages = null, array $options = []): bool
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return false;
        }
        $operationOptions = ComposeOperationOptions::forStop($serviceName, $removeVolumes, $removeImages, $options);
        $result = $this->runtime->remove($configs, $operationOptions);
        $this->afterOperation('remove', $configs, $result);

        return $result->allSuccessful();
    }

    /**
     * Restart containers for the selected configurations.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     * @param string|null $serviceName Optional service selector.
     * @param bool $rebuild Whether to rebuild images.
     * @param bool $removeVolumes Whether to prune volumes while restarting.
     * @param string|null $removeImages Optional image pruning directive.
     * @param array<string,mixed> $options Additional runtime flags.
     *
     * @return bool True when every targeted container restarted successfully.
     */
    public function restart($id = null, ?string $serviceName = null, bool $rebuild = false, bool $removeVolumes = false, ?string $removeImages = null, array $options = []): bool
    {
        $configs = $this->resolveConfigs($id, true);
        if (empty($configs)) {
            return false;
        }
        $ids = array_keys($configs);
        if (!$this->states->allRunning($ids)) {
            return $this->start($id, $serviceName, $rebuild, $options);
        }
        $operationOptions = new ComposeOperationOptions($serviceName, $rebuild, $removeVolumes, $removeImages, $options['flags'] ?? [], true, $options['health_timeout'] ?? $this->defaultHealthTimeout, $options);
        $result = $this->runtime->restart($configs, $operationOptions);
        $this->afterOperation('restart', $configs, $result);

        return $result->allSuccessful();
    }

    /**
     * Retrieve docker inspect data for the selected configurations.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     * @param string|null $serviceName Optional service selector.
     *
     * @return array<string,mixed> Raw inspect payload keyed by config id.
     */
    public function inspect($id = null, ?string $serviceName = null): array
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return [];
        }

        return $this->runtime->inspect($configs, $serviceName);
    }

    /**
     * Determine whether matching containers already exist.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     * @param string|null $serviceName Optional service selector.
     *
     * @return bool
     */
    public function containerExists($id = null, ?string $serviceName = null): bool
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return false;
        }

        return $this->runtime->containerExists($configs, $serviceName);
    }

    /**
     * Determine whether volumes exist for the matching configurations.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     *
     * @return bool
     */
    public function volumesExist($id = null): bool
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return false;
        }

        return $this->runtime->volumesExist($configs);
    }

    /**
     * Determine whether images exist for the matching configurations.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     *
     * @return bool
     */
    public function imagesExist($id = null): bool
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return false;
        }

        return $this->runtime->imagesExist($configs);
    }

    /**
     * Check whether the targeted containers are currently running.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     * @param string|null $serviceName Optional service selector.
     *
     * @return bool
     */
    public function isRunning($id = null, ?string $serviceName = null): bool
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return false;
        }

        return $this->runtime->isRunning($configs, $serviceName);
    }

    /**
     * List docker volumes for the targeted configurations.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     *
     * @return array<int,mixed> Raw runtime response items.
     */
    public function listVolumes($id = null): array
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return [];
        }

        return $this->runtime->listVolumes($configs);
    }

    /**
     * List docker images for the targeted configurations.
     *
     * @param string|array|null $id Single id, list of ids or null to target all configs.
     *
     * @return array<int,mixed> Raw runtime response items.
     */
    public function listImages($id = null): array
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return [];
        }

        return $this->runtime->listImages($configs);
    }

    /**
     * Retrieve accumulated runtime errors for a subset of configurations.
     *
     * @param string|array|null $id Single id, list of ids or null for all errors.
     *
     * @return array<string,array<int,string>> Errors keyed by configuration id.
     */
    public function getErrors($id = null): array
    {
        if ($id === null) {
            return $this->errors;
        }
        if (is_array($id)) {
            return array_intersect_key($this->errors, array_flip($id));
        }

        return isset($this->errors[$id]) ? [$id => $this->errors[$id]] : [];
    }

    /**
     * Fetch one or many registered configurations.
     *
     * @param string|array $id Single id or an array of ids.
     *
     * @return DockerComposeConfig|array<string,DockerComposeConfig>
     */
    public function getDockerComposeConfig($id)
    {
        if (is_array($id)) {
            return $this->resolveConfigs($id, false);
        }
        if (!isset($this->configs[$id])) {
            throw new InvalidArgumentException("Configuration '{$id}' is not registered.");
        }

        return $this->configs[$id];
    }

    /**
     * Return the cached runtime state for a configuration, if available.
     *
     * @return ContainerState|null
     */
    public function getState(string $id): ?ContainerState
    {
        return $this->states->get($id);
    }

    /**
     * Force-refresh the cached runtime states via the runtime describe command.
     *
     * @param string|array|null $id Single id, list of ids or null to refresh all.
     *
     * @return void
     */
    public function refreshStates($id = null): void
    {
        $configs = $this->resolveConfigs($id);
        if (empty($configs)) {
            return;
        }
        $states = $this->runtime->describe($configs);
        $this->states->merge($states);
        foreach ($states as $state) {
            $this->configs[$state->getId()]->setState($state);
        }
    }

    /**
     * Save the configuration locally and synchronize its initial state.
     *
     * @param DockerComposeConfig $config Configuration being stored.
     *
     * @return DockerComposeConfig
     */
    private function register(DockerComposeConfig $config): DockerComposeConfig
    {
        $this->configs[$config->getId()] = $config;
        $this->refreshStates($config->getId());

        return $config;
    }

    /**
     * Resolve the provided ids (or all) into configuration instances.
     *
     * @param string|array|null $id Single id, list of ids or null for all configs.
     * @param bool $startableOnly When true, return only startable configurations.
     *
     * @return array<string,DockerComposeConfig>
     */
    private function resolveConfigs($id, bool $startableOnly = false): array
    {
        if ($id === null) {
            $selected = $this->configs;
        } elseif (is_array($id)) {
            $selected = [];
            foreach ($id as $singleId) {
                if (isset($this->configs[$singleId])) {
                    $selected[$singleId] = $this->configs[$singleId];
                }
            }
        } else {
            $selected = isset($this->configs[$id]) ? [$id => $this->configs[$id]] : [];
        }

        if ($startableOnly) {
            $selected = array_filter($selected, fn (DockerComposeConfig $config) => $config->canManageContainers());
        }

        return $selected;
    }

    /**
     * Update cached states, callbacks and error collections after an operation.
     *
     * @param string $operation Operation name (start/stop/etc.).
     * @param array<string,DockerComposeConfig> $configs Configurations involved.
     * @param RuntimeOperationResult $result Detailed runtime response.
     */
    private function afterOperation(string $operation, array $configs, RuntimeOperationResult $result): void
    {
        foreach ($configs as $id => $config) {
            if (!isset($result->statusById[$id])) {
                continue;
            }
            $success = $result->statusById[$id];
            if (!$success && isset($result->errorsById[$id])) {
                $this->errors[$id] = $result->errorsById[$id];
                $callback = $config->getErrorCallback();
                if ($callback) {
                    $callback($id, $result->errorsById[$id]);
                }
            } elseif ($success) {
                unset($this->errors[$id]);
                $callback = $config->getSuccessCallback();
                if ($callback) {
                    $callback($id, $operation);
                }
            }
        }
        if (!empty($result->states)) {
            $this->states->merge($result->states);
            foreach ($result->states as $state) {
                $config = $this->configs[$state->getId()] ?? null;
                if ($config) {
                    $config->setState($state);
                }
            }
        }
    }
}
