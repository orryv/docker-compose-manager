<?php

namespace Tests\Unit;

use Orryv\DockerComposeManager\Yaml\YamlAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class YamlAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        YamlAdapter::forceDriver(null);
    }

    public function testDumpAndParseWithSymfonyDriver(): void
    {
        YamlAdapter::forceDriver(YamlAdapter::DRIVER_SYMFONY);
        $data = [
            'services' => [
                'app' => [
                    'image' => 'nginx:latest',
                ],
            ],
        ];

        $file = tempnam(sys_get_temp_dir(), 'yaml-adapter-');
        self::assertIsString($file);
        file_put_contents($file, YamlAdapter::dump($data));

        $parsed = YamlAdapter::parseFile($file);
        self::assertSame($data, $parsed);

        @unlink($file);
    }

    public function testUnsupportedDriverThrows(): void
    {
        YamlAdapter::forceDriver('missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported YAML driver: missing');

        YamlAdapter::parseFile(__FILE__);
    }
}
