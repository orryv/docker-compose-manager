<?php

namespace Tests\Unit;

use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerComposeManager\State\ContainerState;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DockerComposeConfigTest extends TestCase
{
    public function testSetEnvVariables(): void
    {
        $config = new DockerComposeConfig('id', __DIR__);
        $config->setEnvVariable('FOO', 'BAR');
        $config->setEnvVariables(['BAR' => 'BAZ']);

        $vars = $config->getEnvVariables();
        self::assertSame('BAR', $vars['FOO']);
        self::assertSame('BAZ', $vars['BAR']);
    }

    public function testDockerComposeManipulation(): void
    {
        $config = new DockerComposeConfig('id', __DIR__, ['services' => ['web' => ['image' => 'nginx']]]);
        $config->setDockerComposeValues(['services' => ['web' => ['environment' => ['A' => '1']]]]);
        $config->setProjectName('project');
        $config->setContainerName('web', 'web-1');
        $config->setPortMapping('web', 80, 8080);
        $config->setNetwork('custom');
        $config->setServiceName('web', 'frontend');
        $config->setCpus('frontend', 1.5);
        $config->setMemoryLimit('frontend', '512M');

        $values = $config->getDockerComposeArray();
        self::assertSame('project', $values['name']);
        self::assertSame('web-1', $values['services']['frontend']['container_name']);
        self::assertSame('8080:80/tcp', $values['services']['frontend']['ports'][0]);
        self::assertArrayHasKey('custom', $values['networks']);
        self::assertSame(1.5, $values['services']['frontend']['deploy']['resources']['limits']['cpus']);
        self::assertSame('512M', $values['services']['frontend']['deploy']['resources']['limits']['memory']);
    }

    public function testCallbacksAndLogger(): void
    {
        $config = new DockerComposeConfig('id', __DIR__);
        $config->onProgress(fn () => null);
        $config->onSuccess(fn () => null);
        $config->onError(fn () => null);
        $config->setLogger(new NullLogger());

        self::assertNotNull($config->getProgressCallback());
        self::assertNotNull($config->getSuccessCallback());
        self::assertNotNull($config->getErrorCallback());
    }

    public function testStateAndDebugging(): void
    {
        $tmpDir = sys_get_temp_dir() . '/docker-compose-manager-tests-' . uniqid();
        mkdir($tmpDir, 0777, true);

        $config = new DockerComposeConfig('id', $tmpDir, ['services' => []]);
        $config->debug($tmpDir . '/debug');
        $config->setState(ContainerState::running('id', ['web' => 'healthy']));
        $file = $config->createTemporaryComposeFile();
        self::assertFileExists($file);
        $config->removeTmpFiles();
        self::assertFileDoesNotExist($file);
    }
}
