<?php

namespace Tests\Support\Coverage;

use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;

final class FakeCoverageDriver extends Driver
{
    public function __construct(private readonly Filter $filter)
    {
    }

    public function nameAndVersion(): string
    {
        return 'FakeCoverage 1.0';
    }

    public function start(): void
    {
        // Intentionally left blank; we mark files as fully covered on stop().
    }

    public function stop(): RawCodeCoverageData
    {
        $data = [];
        foreach ($this->filter->files() as $file) {
            if (!is_file($file)) {
                continue;
            }
            $lines = file($file);
            if ($lines === false) {
                continue;
            }
            $coverage = [];
            foreach ($lines as $lineNumber => $line) {
                $lineIndex = $lineNumber + 1;
                $coverage[$lineIndex] = trim($line) === ''
                    ? self::LINE_NOT_EXECUTABLE
                    : self::LINE_EXECUTED;
            }
            $data[$file] = $coverage;
        }

        return RawCodeCoverageData::fromXdebugWithoutPathCoverage($data);
    }
}
