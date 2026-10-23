<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests;

use Lahatre\ResponseSelection\ResponseSelectionServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * A bare Laravel application with the engine only, on an in-memory SQLite database.
 */
abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ResponseSelectionServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}
