# docker-manager

## Installation

Install the library via Composer as usual:

```
composer require orryv/docker-manager
```

By default the manager expects the [`ext-yaml`](https://www.php.net/manual/en/book.yaml.php) extension to be available so it can call `yaml_parse_file()` and `yaml_emit_file()`. If the extension is not available you can install the optional [`symfony/yaml`](https://github.com/symfony/yaml) package instead and instruct the manager to use it:

```
composer require symfony/yaml

$manager = new \Orryv\Docker2\Manager('/path/to/project', 'docker-compose.yml', 'symfony');
```

The constructor's third argument accepts either `ext` (default) or `symfony`, allowing consumers to pick whichever YAML backend matches their environment.
