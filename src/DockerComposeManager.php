<?php 

namespace Orryv;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\FileSystem\Reader;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandler;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerCollection;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerCollectionInterface;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerFactory;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerFactoryInterface;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor;
use Orryv\DockerComposeManager\Validation\DockerComposeValidator;
use Orryv\DockerComposeManager\CommandBuilder\DockerComposeCommandBuilder;
use Orryv\DockerComposeManager\DockerCompose\OutputParser as DockerComposeOutputParser;
class DockerComposeManager
{
    /**
     * Instance we use to parse .yml files.
     *  Nullable because we allow operations without docker compose arrays.
     */
    private ?YamlParserInterface $yaml_parser;
    private DockerComposeHandlerCollectionInterface $internalConfigManager;
    private ?string $executionPath = null;
    private ?string $debugDir = null;
    private CommandExecutor $commandExecutor;
    private DockerComposeHandlerFactoryInterface $handlerFactory;
    private array $tmpOutputFiles = [];
    private array $runningPids = [];
    private ?DockerComposeOutputParser $outputParser = null;


    public function __construct(
        YamlParserInterface|string $yaml_parser = 'ext-yaml',
        ?DockerComposeHandlerCollectionInterface $internalConfigManager = null,
        ?CommandExecutor $commandExecutor = null,
        ?DockerComposeHandlerFactoryInterface $handlerFactory = null,
        ?DockerComposeOutputParser $outputParser = null
    ){
        $this->yaml_parser = is_string($yaml_parser)
            ? (new YamlParserFactory())->create($yaml_parser)
            : $yaml_parser;

        $this->internalConfigManager = $internalConfigManager ?? new DockerComposeHandlerCollection();
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor();
        $this->handlerFactory = $handlerFactory ?? new DockerComposeHandlerFactory();
        $this->outputParser = $outputParser ?? new DockerComposeOutputParser();
    }

    public function __destruct()
    {
        // Clean up any temporary files created
        foreach ($this->internalConfigManager->getRegisteredIds() as $config_id) {
            $config = $this->internalConfigManager->get($config_id);
            if ($config !== null) {
                if($this->debugDir !== null) {
                    $config->copyTmpFiles($this->debugDir);
                }

                $config->removeTmpFiles();
            }

            if (array_key_exists($config_id, $this->tmpOutputFiles)) {
                $tmpOutputFile = $this->tmpOutputFiles[$config_id];

                if ($tmpOutputFile !== null && file_exists($tmpOutputFile)) {
                    if ($this->debugDir !== null) {
                        $outputCopyPath = $this->debugDir . DIRECTORY_SEPARATOR . basename($tmpOutputFile);
                        copy($tmpOutputFile, $outputCopyPath);
                    }

                    unlink($tmpOutputFile);
                }
            }
        }
    }

    public function debug(string $dir): void
    {
        $this->debugDir = $dir;
    }

    public function fromDockerComposeFile(string $id, string $file_path): DockerComposeHandler
    {
        $dockerFileContents = Reader::readFile($file_path);
        $this->executionPath = dirname($file_path);
        $dockerComposeHandler = $this->handlerFactory->create(
            $this->getYamlParser()->parse($dockerFileContents),
            $this->yaml_parser,
        );
        $this->internalConfigManager->add($id, $dockerComposeHandler);
        DockerComposeValidator::validate($this->internalConfigManager->getCurrent()->toArray()); // TODO: move to execution side

        return $dockerComposeHandler;
    }

    public function fromYamlArray(string $id, array $yaml_array, string $executionFolder): DockerComposeHandler
    {
        $this->executionPath = $executionFolder;
        DockerComposeValidator::validate($yaml_array); // TODO: move to execution side
        $dockerComposeHandler = $this->handlerFactory->create($yaml_array, $this->yaml_parser);
        $this->internalConfigManager->add($id, $dockerComposeHandler);

        return $dockerComposeHandler;
    }

    /**
     * Registers a callback to receive progress updates during container start (non async methods).
     */
    public function onProgress(callable $callback): void
    {
        // TODO
    }

    /**
     * Starts containers defined in the Docker Compose configurations. Returns true if all containers started successfully.
     */
    public function start(string|array|null $id = null, string|array|null $serviceNames = null, bool $rebuildContainers = false): bool
    {
        $executionResults = [];
        foreach($this->buildStartCommands($id, $serviceNames, $rebuildContainers) as $config_id => $commandData) {
            $executionResults[$config_id] = $this->commandExecutor->executeAsync(
                $commandData['command'],
                $this->executionPath,
                $commandData['tmp_identifier']
            );

            if ($executionResults[$config_id]['pid'] !== null) {
                $this->runningPids[$commandData['id']] = $executionResults[$config_id]['pid'];
            }

            $this->tmpOutputFiles[$commandData['id']] = $executionResults[$config_id]['output_file'];
        }

        // now parse outputs
        do{
            $scriptExecutionEnded = true;
            foreach($executionResults as $id => $result) {
                $outputFile = $result['output_file'];
                $parseData = $this->outputParser->parse($id, $outputFile, $this->internalConfigManager->get($id));

                print_r($parseData);

                if(!$parseData['script_ended']) {
                    $scriptExecutionEnded = false;
                    usleep(250000); // wait 0.25s before re-checking
                }
            }
        } while (!$scriptExecutionEnded);

        // check if successful
        $allSuccessful = true;
        foreach($parseData['success']['containers'] as $id => $result) {
            if(!$result) {
                $allSuccessful = false;
                break;
            }
        }

        return $allSuccessful;
    }

    public function startAsync()
    {
        // TODO
    }

    /**
     * Get progress for async methods (startAsync, restartAsync, etc).
     */
    public function getProgress(string|array|null $id = null, string|array|null $serviceNames = null): array
    {
        // TODO
        return [];
    }

    public function getRunningPids(): array
    {
        return $this->runningPids;
    }

    public function getTmpOutputFiles(): array
    {
        return $this->tmpOutputFiles;
    }

    ######################
    ## Internal methods ##
    ######################

    private function buildStartCommands(
        string|array|null $id = null,
        string|array|null $serviceNames = null,
        bool $rebuildContainers = false
    ): array {
        if($this->executionPath === null) {
            throw new DockerComposeManagerException('Can only start containers when using fromDockerComposeFile() or fromYamlArray().');
        }

        $ids = $this->normalizeInternalIds($id);

        $commands = [];
        foreach($ids as $config_id) {
            $dockerComposeHandler = $this->internalConfigManager->get($config_id);
            $dockerComposeHandler->saveTmpDockerComposeFile($this->executionPath);
            $command = (new DockerComposeCommandBuilder($dockerComposeHandler))->start($serviceNames, $rebuildContainers);
            $commands[$config_id] = [
                'id' => $config_id,
                'command' => $command,
                'handler' => $dockerComposeHandler,
                'tmp_identifier' => $this->deriveTmpIdentifier($dockerComposeHandler->getTmpFilePath()),
            ];
        }

        return $commands;
    }

    private function deriveTmpIdentifier(?string $tmpFilePath): ?string
    {
        if ($tmpFilePath === null) {
            return null;
        }

        $fileName = basename($tmpFilePath);

        if (preg_match('/docker-compose-tmp-([^.]+)\.yml/', $fileName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function normalizeInternalIds(string|array|null $id = null): array
    {
        if (is_string($id)) {
            return [$id];
        } elseif (is_array($id)) {
            return $id;
        } else {
            return $this->internalConfigManager->getRegisteredIds();
        }
    }

    private function getYamlParser(): YamlParserInterface
    {
        if ($this->yaml_parser === null) {
            throw new YamlParserException(
                'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
            );
        }

        return $this->yaml_parser;
    }
}