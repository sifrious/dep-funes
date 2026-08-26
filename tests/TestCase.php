<?php

declare(strict_types=1);

namespace Sifrious\Funes\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sifrious\Funes\FunesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FunesServiceProvider::class];
    }
}
