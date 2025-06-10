<?php

namespace Henzeb\EventHorizon\Tests;

use Henzeb\EventHorizon\EventHorizonServiceProvider;
use Laravel\Horizon\HorizonServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            HorizonServiceProvider::class,
            EventHorizonServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.redis.default', [
            'url' => null,
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0,
            'client' => 'predis',
        ]);

        $app['config']->set('database.redis.service_auth', [
            'url' => null,
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0,
            'client' => 'predis',
        ]);

        $app['config']->set('database.redis.service_billing', [
            'url' => null,
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 1,
            'client' => 'predis',
        ]);

        $app['config']->set('database.redis.horizon', [
            'url' => null,
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0,
            'client' => 'predis',
        ]);

        $app['config']->set('queue.default', 'redis');
        $app['config']->set('queue.connections.redis', [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90,
        ]);

        $app['config']->set('queue.connections.auth_service', [
            'driver' => 'redis',
            'connection' => 'service_auth',
            'queue' => 'default',
            'retry_after' => 90,
        ]);

        $app['config']->set('queue.connections.billing_service', [
            'driver' => 'redis',
            'connection' => 'service_billing',
            'queue' => 'default',
            'retry_after' => 90,
        ]);

        $app['config']->set('horizon.trim.pending', 60);
        $app['config']->set('horizon.trim.recent', 60);
        $app['config']->set('horizon.trim.completed', 60);
        $app['config']->set('horizon.trim.failed', 10080);
    }
}