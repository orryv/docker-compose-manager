<?php

namespace Orryv\DockerComposeManager\Yaml;

use RuntimeException;

/**
 * Lightweight adapter that hides whether YAML parsing/dumping is performed by
 * ext-yaml or Symfony's YAML component. Consumers can rely on a single API and
 * allow whichever dependency is available to be used at runtime.
 */
class YamlAdapter
{
    public const DRIVER_EXT = 'ext';
    public const DRIVER_SYMFONY = 'symfony';

    private static ?string $forcedDriver = null;

    /**
     * Override the auto-detected driver (used mainly inside tests).
     *
     * @param string|null $driver Use self::DRIVER_EXT or self::DRIVER_SYMFONY.
     */
    public static function forceDriver(?string $driver): void
    {
        self::$forcedDriver = $driver;
    }

    /**
     * Parse a YAML file into an associative array using whichever driver is available.
     *
     * @param string $path
     *
     * @return array<string,mixed>
     */
    public static function parseFile(string $path): array
    {
        $driver = self::resolveDriver();
        switch ($driver) {
            case self::DRIVER_EXT:
                if (!function_exists('yaml_parse_file')) {
                    throw new RuntimeException('ext-yaml is not available. Install it or use the Symfony YAML component.');
                }
                /** @disregard P1010 yaml_parse_file comes from optional ext-yaml */
                $parsed = yaml_parse_file($path);
                break;
            case self::DRIVER_SYMFONY:
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    throw new RuntimeException('symfony/yaml is not installed. Install it or enable ext-yaml.');
                }
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                $parsed = \Symfony\Component\Yaml\Yaml::parseFile($path);
                break;
            default:
                throw new RuntimeException('Unsupported YAML driver: ' . $driver);
        }

        if ($parsed === null) {
            return [];
        }

        if (!is_array($parsed)) {
            throw new RuntimeException('Docker compose file could not be parsed into an array.');
        }

        return $parsed;
    }

    /**
     * Dump an array back to YAML using the detected driver.
     *
     * @param array<string,mixed> $data
     *
     * @return string YAML string ready to be written to disk.
     */
    public static function dump(array $data, int $inline = 6, int $indent = 2): string
    {
        $driver = self::resolveDriver();
        switch ($driver) {
            case self::DRIVER_EXT:
                if (!function_exists('yaml_emit')) {
                    throw new RuntimeException('ext-yaml is not available. Install it or use the Symfony YAML component.');
                }
                /** @disregard P1010 yaml_emit comes from optional ext-yaml */
                $yaml = yaml_emit($data, YAML_UTF8_ENCODING, YAML_LN_BREAK);
                if (!is_string($yaml)) {
                    throw new RuntimeException('Failed to dump YAML using ext-yaml.');
                }

                return $yaml;
            case self::DRIVER_SYMFONY:
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    throw new RuntimeException('symfony/yaml is not installed. Install it or enable ext-yaml.');
                }
                /** @disregard P1009 Symfony\\Component\Yaml\Yaml is an optional dependency */
                return \Symfony\Component\Yaml\Yaml::dump($data, $inline, $indent);
        }

        throw new RuntimeException('Unsupported YAML driver: ' . $driver);
    }

    /**
     * Determine which YAML driver should be used (ext first, fallback to Symfony).
     *
     * @return string One of the DRIVER_* constants.
     */
    private static function resolveDriver(): string
    {
        if (self::$forcedDriver !== null) {
            return self::$forcedDriver;
        }

        if (function_exists('yaml_parse_file')) {
            return self::DRIVER_EXT;
        }
        /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            return self::DRIVER_SYMFONY;
        }

        throw new RuntimeException('No YAML parser available. Install ext-yaml or symfony/yaml.');
    }
}
