<?php

declare(strict_types=1);

namespace Sifrious\Funes\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sifrious\Funes\FunesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [FunesServiceProvider::class];
    }
}
