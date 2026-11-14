<?php

namespace Henzeb\EventHorizon\Repositories;

use Henzeb\EventHorizon\Concerns\IsQueueConnectionAware;
use Laravel\Horizon\Repositories\RedisTagRepository as HorizonRedisTagRepository;

class RedisTagRepository extends HorizonRedisTagRepository
{
    use IsQueueConnectionAware;

    /**
     * @param  array<mixed>  $tags
     * @return array<mixed>
     */
    public function monitored(array $tags, ?string $connectionName = null): array
    {
        return $this->withQueueConnection($connectionName, fn () => parent::monitored($tags));
    }

    /**
     * @param  array<mixed>  $tags
     */
    public function add($id, array $tags, ?string $connectionName = null)
    {
        $this->withQueueConnection(
            $connectionName,
            fn () => parent::add($id, $tags)
        );
    }
}
