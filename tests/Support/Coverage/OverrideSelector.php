<?php

namespace SebastianBergmann\CodeCoverage\Driver;

use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\Environment\Runtime;
use Tests\Support\Coverage\FakeCoverageDriver;

if (class_exists(Selector::class, false)) {
    return;
}

final class Selector
{
    public function forLineCoverage(Filter $filter): Driver
    {
        $runtime = new Runtime();

        if ($runtime->hasPCOV()) {
            return new PcovDriver($filter);
        }

        if ($runtime->hasXdebug()) {
            $driver = new XdebugDriver($filter);
            $driver->enableDeadCodeDetection();

            return $driver;
        }

        return new FakeCoverageDriver($filter);
    }

    public function forLineAndPathCoverage(Filter $filter): Driver
    {
        $runtime = new Runtime();

        if ($runtime->hasXdebug()) {
            $driver = new XdebugDriver($filter);
            $driver->enableDeadCodeDetection();
            $driver->enableBranchAndPathCoverage();

            return $driver;
        }

        return new FakeCoverageDriver($filter);
    }
}
