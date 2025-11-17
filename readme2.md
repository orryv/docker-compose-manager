# docker-compose-manager

A PHP library to manage Docker Compose configurations and containers programmatically: create, modify, and control Docker Compose setups using PHP, including starting, stopping, and inspecting containers.

## Installation

Requirements:

- PHP 8.2 or higher // TODO: check if we can support 8.1, or only 8.4
- Composer
- [`ext-yaml`](https://www.php.net/manual/en/book.yaml.php) **or** [`symfony/yaml`](https://github.com/symfony/yaml) (only one is required – we auto-detect ext-yaml first, then fall back to Symfony's parser when available)
- Docker & Docker Compose CLI installed (e.g. included in Docker Desktop)
- [`psr/log`](https://github.com/php-fig/log) (for logging, optional)

Install the library via Composer as usual:

```
composer require orryv/docker-compose-manager
```

### Testing locally

The repository ships with a PHPUnit test suite:

```bash
composer install
composer test:unit      # runs fast unit tests with coverage
composer test:integration # runs the integration tests that execute the CLI runner
composer test           # runs both suites
```

## Notes

- Make sure to add a valid healthcheck to each service in your docker-compose.yml files, so the library can determine when a container is "ready" (and thus can return from start(), etc). Example:

```yaml
services:
  web:
    image: my-web-image
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"] # Depends on the container
      interval: 5s
      timeout: 5s
      retries: 10
```

## Usage

Run multiple docker compose simultaneously from different docker-compose.yml files, manage them independently:

```php
use Orryv\DockerComposeManager;

$dcm = new DockerComposeManager();


// Each configuration is registered under a custom $id. 
//  You can later start/stop/inspect by passing one or more IDs to the manager.
$config1 = $dcm->fromDockerComposeFile('id-1', 'path/to/docker-compose.yml');
$config2 = $dcm->fromDockerComposeFile('id-2', 'path/to/another-docker-compose.yml');

$config1->setEnvVariable('MY_ENV_VAR', 'value1');

$dcm->start(); // runs all registered compose projects in parallel and returns when they all finish or become healthy

```

## Methods

All container management methods accept an optional `$options` array as the last argument. Supported keys:

| Option | Applies to | Description |
| ------ | ---------- | ----------- |
| `flags` | start/stop/remove/restart | Extra CLI flags appended to the docker compose command. |
| `health_timeout` | start/restart | Overrides the default health check timeout (seconds). |
| `remove_volumes` / `remove_images` | stop/remove/restart | Identical to the dedicated boolean parameters but can also be set through the array for convenience. |
| `require_healthy` | start/restart | When `false`, the method returns as soon as Docker Compose finishes instead of waiting for health checks. |

### `DockerComposeManager`

```php
use Orryv\DockerComposeManager\DockerComposeConfig;

fromDockerComposeFile(string $id, string $file_path): DockerComposeConfig
fromYamlArray(string $id, array $yaml_array): DockerComposeConfig

// Next "from" methods can't be used to start or reset containers,
//  Only inspect, stop, remove, etc.
fromContainerName(string $id, string $container_name): DockerComposeConfig
fromProjectName(string $id, string $project_name): DockerComposeConfig

// Container management
start(string|array|null $id = null, ?string $service_name = null, bool $rebuild_containers = false, array $options = []): bool
stop(
    string|array|null $id = null,
    ?string $service_name = null,
    bool $remove_volumes = false,
    ?string $remove_images = null,
    array $options = []
): bool
remove(
    string|array|null $id = null,
    ?string $service_name = null,
    bool $remove_volumes = false,
    ?string $remove_images = null,
    array $options = []
): bool
restart(
    string|array|null $id = null,
    ?string $service_name = null,
    bool $rebuild_containers = false,
    bool $remove_volumes = false,
    ?string $remove_images = null,
    array $options = []
): bool
inspect(string|array|null $id = null, ?string $service_name = null): array
containerExists(string|array|null $id = null, ?string $service_name = null): bool
volumesExist(string|array|null $id = null): bool
imagesExist(string|array|null $id = null): bool
isRunning(string|array|null $id = null, ?string $service_name = null): bool
listVolumes(string|array|null $id = null): array
listImages(string|array|null $id = null): array
getErrors(string|array|null $id = null): array
getDockerComposeConfig(string|array $id)
getState(string $id): ?ContainerState
refreshStates(string|array|null $id = null): void
```

### `DockerComposeConfig`

```php
#### Configuration ####
debug(?string $path = null): self // disabled when null, will put tmp files (docker-compose, logs, etc) in $path when set
setEnvVariable(string $name, string $value): self // Sets environment variable so docker-compose can use variables
setEnvVariables(array $vars): self // expects array<string,string>
setLogger(Psr\Log\LoggerInterface $logger): self

// Currently callbacks apply to the whole project, not individual services.
onProgress(callable $callback, int $interval_ms = 250): self // callback signature: fn(string $configId, array $events, string $operation): void
onSuccess(callable $callback): self // fn(string $configId, string $operation): void
onError(callable $callback): self // fn(string $configId, array $errors): void

#### docker-compose configuration manipulation ####
// You can edit the parsed docker-compose.yml array directly:
setDockerComposeValues(array $values): self // merges values into docker-compose configuration

// Or use helper methods to set common values:
setProjectName(string $name): self
setContainerName(string $service_name, string $container_name): self
setServiceName(string $service_name, string $new_service_name): self
setPortMapping(string $service_name, int $container_port, int $host_port, string $protocol = 'tcp'): self
setNetwork(string $network_name, array $options = []): self
setCpus(string $service_name, float $cpus): self
setMemoryLimit(string $service_name, string $memory): self
```

### Inspecting and debugging

- `inspect()` returns the latest known service states per configuration, based on `docker compose ps --format json`. When a `$service_name` is supplied only that service is returned.
- `DockerComposeConfig::debug($path)` copies the generated compose and log files to the provided directory for troubleshooting. Temporary files live next to the original compose file to avoid path issues and are removed automatically when the config is destroyed or when `removeTmpFiles()` is called manually.

### Container state tracking

The manager keeps a `ContainerState` snapshot per configuration. It polls the Docker CLI when configurations are registered and again after every operation. A helper `getState($id)` method exposes that snapshot so automation can make decisions without running `docker` commands manually.

### Health checks & timeouts

- `start()` and `restart()` return `true` only if all services finish with a healthy status.
- The default wait time is 120 seconds but can be tweaked globally with `setDefaultHealthTimeout()` or per-call via the `$options['health_timeout']` override.
- When containers are already running and healthy, `start()` resolves immediately without touching Docker.
- `restart()` falls back to `start()` if containers were never started – so `restart()` is safe to call even on a fresh system.

### Parallel execution

Whenever you call a container-management method the registered compose projects are executed in parallel. Output from each process is streamed to a temporary log file, parsed for progress/error events, forwarded to the `onProgress` callback, and appended to debug folders when `debug()` is enabled.
