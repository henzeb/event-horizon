<?php

namespace Henzeb\EventHorizon\Concerns;

use Closure;
use Illuminate\Redis\Connections\Connection;
use Redis;

trait IsQueueConnectionAware
{
    private ?string $connection = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function withQueueConnection(?string $connection, Closure $callback): mixed
    {
        if ($connection && config()->has('queue.connections.'.$connection)) {
            $mapped = config('queue.connections.'.$connection.'.connection');
            $connection = is_string($mapped) ? $mapped : null;
        }

        if ($connection && ! config()->has('database.redis.'.$connection)) {
            $connection = null;
        }

        $this->connection = $connection;

        if ($connection) {
            $client = $this->connection()->client();

            if ($client instanceof Redis) {
                $client->setOption(Redis::OPT_PREFIX, config('horizon.prefix'));
            }
        }

        try {
            return $callback();
        } finally {
            $this->connection = null;
        }
    }

    protected function connection(): Connection
    {
        return $this->redis->connection($this->connection ?? 'horizon');
    }
}
