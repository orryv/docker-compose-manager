<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

/**
 * Small utility that turns raw docker compose output into structured events and
 * extracts error strings for easier reporting.
 */
class DockerOutputParser
{
    /**
     * @return array{events:array<int,array<string,string>>,errors:array<int,string>,lines:array<int,string>}
     */
    /**
     * Split compose logs into normalized events and error lines.
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\r?\n/', trim($content)) ?: [];
        $events = [];
        $errors = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (stripos($line, 'error') !== false) {
                $errors[] = $line;
            }
            if (preg_match('/Container\s+(?P<name>[\w\-\.]+)\s+(?P<status>Started|Starting|Stopped|Healthy|Unhealthy)/i', $line, $matches)) {
                $events[] = [
                    'container' => $matches['name'],
                    'status' => strtolower($matches['status']),
                    'raw' => $line,
                ];
            }
        }

        return [
            'events' => $events,
            'errors' => $errors,
            'lines' => $lines,
        ];
    }
}
