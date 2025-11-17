<?php

namespace Tests\Unit;

use Orryv\DockerComposeManager;
use Orryv\DockerComposeManager\Runtime\RuntimeOperationResult;
use Orryv\DockerManager\Helper;
use Orryv\DockerManager\Ports\FindNextPort;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Fakes\FakeDockerRuntime;

class DockerManagerHelperTest extends TestCase
{
    private ?DockerComposeManager $manager = null;

    protected function tearDown(): void
    {
        Helper::useDockerComposeManagerFactory(null);
        $this->manager = null;
        parent::tearDown();
    }

    public function testStartContainerConfiguresManagerAndReturnsPort(): void
    {
        $runtime = new FakeDockerRuntime();
        $composeFile = $this->createComposeFile();
        Helper::useDockerComposeManagerFactory(function () use ($runtime) {
            $this->manager = new DockerComposeManager($runtime);

            return $this->manager;
        });

        $port = Helper::startContainer(
            'demo',
            dirname($composeFile),
            basename($composeFile),
            8123,
            false,
            false,
            ['FOO' => 'BAR']
        );

        self::assertSame(8123, $port);
        self::assertNotNull($this->manager);
        $config = $this->manager->getDockerComposeConfig('demo');
        self::assertSame('8123', $config->getEnvVariables()['HOST_PORT'] ?? null);
        self::assertSame('BAR', $config->getEnvVariables()['FOO'] ?? null);
    }

    public function testRetriesWithNextPortWhenPortInUse(): void
    {
        $runtime = new FakeDockerRuntime();
        $runtime->queueResult('start', RuntimeOperationResult::failure(['demo' => ['Error: address already in use']]));
        $runtime->queueResult('start', RuntimeOperationResult::success(['demo']));
        $composeFile = $this->createComposeFile();
        Helper::useDockerComposeManagerFactory(function () use ($runtime) {
            $this->manager = new DockerComposeManager($runtime);

            return $this->manager;
        });
        $portFinder = new class extends FindNextPort {
            private array $ports = [8124, 8125];

            public function __construct()
            {
            }

            public function getAvailablePort(?int $last_failed = null): ?int
            {
                return array_shift($this->ports);
            }
        };

        $port = Helper::startContainer(
            'demo',
            dirname($composeFile),
            basename($composeFile),
            $portFinder,
            false,
            false
        );

        self::assertSame(8125, $port);
    }

    private function createComposeFile(): string
    {
        $dir = sys_get_temp_dir() . '/docker-manager-helper-' . uniqid();
        mkdir($dir, 0777, true);
        $file = $dir . '/docker-compose.yml';
        file_put_contents($file, "version: '3'\nservices:\n  web:\n    image: nginx\n");

        return $file;
    }
}
