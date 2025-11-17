<?php

namespace Tests\Unit;

use Orryv\DockerComposeManager;
use Orryv\DockerComposeManager\Runtime\RuntimeOperationResult;
use Orryv\DockerComposeManager\State\ContainerState;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Fakes\FakeDockerRuntime;

class DockerComposeManagerTest extends TestCase
{
    private function createComposeFile(): string
    {
        $dir = sys_get_temp_dir() . '/docker-compose-manager-tests-' . uniqid();
        mkdir($dir, 0777, true);
        $file = $dir . '/docker-compose.yml';
        file_put_contents($file, "version: '3'\nservices:\n  web:\n    image: nginx\n");

        return $file;
    }

    public function testStartRunsRegisteredConfigs(): void
    {
        $runtime = new FakeDockerRuntime();
        $runtime->queueResult('start', RuntimeOperationResult::success(['main']));

        $manager = new DockerComposeManager($runtime);
        $manager->fromDockerComposeFile('main', $this->createComposeFile());

        self::assertTrue($manager->start());
        self::assertSame('start', $runtime->calls[1]['operation']);
    }

    public function testStartReturnsEarlyWhenAlreadyRunning(): void
    {
        $runtime = new FakeDockerRuntime();
        $manager = new DockerComposeManager($runtime);
        $manager->fromDockerComposeFile('main', $this->createComposeFile());
        $runtime->setState('main', ContainerState::running('main', ['web' => 'healthy']));
        $manager->refreshStates('main');

        self::assertTrue($manager->start());
        self::assertCount(0, array_filter($runtime->calls, fn ($call) => $call['operation'] === 'start'));
    }

    public function testRestartFallsBackToStartWhenStopped(): void
    {
        $runtime = new FakeDockerRuntime();
        $runtime->queueResult('restart', RuntimeOperationResult::success(['main']));
        $manager = new DockerComposeManager($runtime);
        $manager->fromDockerComposeFile('main', $this->createComposeFile());

        self::assertTrue($manager->restart());
    }

    public function testGetDockerComposeConfig(): void
    {
        $runtime = new FakeDockerRuntime();
        $manager = new DockerComposeManager($runtime);
        $manager->fromDockerComposeFile('main', $this->createComposeFile());
        $config = $manager->getDockerComposeConfig('main');

        self::assertSame('main', $config->getId());
    }

    public function testErrorsAreCollected(): void
    {
        $runtime = new FakeDockerRuntime();
        $runtime->queueResult('start', RuntimeOperationResult::failure(['main' => ['Boom']]));
        $manager = new DockerComposeManager($runtime);
        $manager->fromDockerComposeFile('main', $this->createComposeFile());

        self::assertFalse($manager->start());
        self::assertSame(['main' => ['Boom']], $manager->getErrors());
    }

    public function testListVolumesAndImages(): void
    {
        $runtime = new FakeDockerRuntime();
        $manager = new DockerComposeManager($runtime);
        $manager->fromDockerComposeFile('main', $this->createComposeFile());

        $manager->listVolumes();
        $manager->listImages();
        $calls = array_column($runtime->calls, 'operation');
        self::assertContains('listVolumes', $calls);
        self::assertContains('listImages', $calls);
    }
}
