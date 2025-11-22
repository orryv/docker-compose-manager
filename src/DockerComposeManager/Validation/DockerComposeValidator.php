<?php 

namespace Orryv\DockerComposeManager\Validation;

use Orryv\DockerComposeManager\Exceptions\YamlException;

class DockerComposeValidator
{
    public static function validate(array $data): void
    {
        if (isset($data['version'])) {
            throw new YamlException('The "version" key is deprecated in Docker Compose files, remove it.');
        }

        // Basic validation: check for required keys in a Docker Compose file
        if (!isset($data['services']) || !is_array($data['services']) || empty($data['services'])) {
            throw new YamlException('The Docker Compose file must define at least one service under the "services" key.');
        }

        foreach ($data['services'] as $serviceName => $serviceConfig) {
            //services:
//   storyteller:
//     container_name
            if (!is_array($serviceConfig)) {
                throw new YamlException("The service '$serviceName' must have a valid configuration array.");
            }

            if(!isset($serviceConfig['container_name']) || empty($serviceConfig['container_name'])) {
                throw new YamlException("The service '$serviceName' must have a 'container_name' defined in the yaml file or array so we can keep track of the container state: (['service']['$serviceName']['container_name'])");
            }

        }
    }
}