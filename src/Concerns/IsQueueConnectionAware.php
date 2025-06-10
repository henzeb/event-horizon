<?php

namespace Henzeb\EventHorizon\Concerns;

trait IsQueueConnectionAware
{
    private $connection = null;

    protected function withQueueConnection($connection, $callback): mixed
    {
        if ($connection && config()->has('queue.connections.'.$connection)) {
            $connection = config('queue.connections.'.$connection.'.connection');
        }

        if ($connection && !config()->has('database.redis.'.$connection)) {
            $connection = null;
        }

        $this->connection = $connection;

        if ($connection) {
            $this->connection()->client()->setOption(2, config('horizon.prefix'));
        }

        try {
            return $callback();
        } finally {
            $this->connection = null;
        }
    }

    protected function connection()
    {
        return $this->redis->connection($this->connection ?? 'horizon');
    }
}