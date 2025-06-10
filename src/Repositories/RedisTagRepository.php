<?php

namespace Henzeb\EventHorizon\Repositories;

use Henzeb\EventHorizon\Concerns\IsQueueConnectionAware;
use Laravel\Horizon\Repositories\RedisTagRepository as HorizonRedisTagRepository;

class RedisTagRepository extends HorizonRedisTagRepository
{
    use IsQueueConnectionAware;

    public function monitored(array $tags, $connectionName = null): array
    {
        return $this->withQueueConnection($connectionName, fn() => parent::monitored($tags));
    }

    public function add($id, array $tags, $connectionName = null)
    {
        $this->withQueueConnection(
            $connectionName,
            fn() => parent::add($id, $tags)
        );
    }
}