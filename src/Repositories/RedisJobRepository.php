<?php

namespace Henzeb\EventHorizon\Repositories;

use Henzeb\EventHorizon\Concerns\IsQueueConnectionAware;
use Laravel\Horizon\Repositories\RedisJobRepository as HorizonRedisJobRepository;
use Redis;

class RedisJobRepository extends HorizonRedisJobRepository
{
    use IsQueueConnectionAware;
    public function pushed($connection, $queue, $payload)
    {
        $this->withQueueConnection(
            $connection,
            fn() => parent::pushed($connection, $queue, $payload)
        );
    }
}