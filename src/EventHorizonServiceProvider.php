<?php

namespace Henzeb\EventHorizon;

use Henzeb\EventHorizon\Listeners\StoreMonitoredTags;
use Henzeb\EventHorizon\Repositories\RedisJobRepository;
use Henzeb\EventHorizon\Repositories\RedisTagRepository;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;


class EventHorizonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->booted(function () {
            $this->app->bind(JobRepository::class, RedisJobRepository::class);
            $this->app->bind(TagRepository::class, RedisTagRepository::class);
            $this->app->bind(StoreMonitoredTags::class, StoreMonitoredTags::class);
        });
    }

    public function boot(): void
    {
        //
    }
}