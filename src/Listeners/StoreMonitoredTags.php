<?php

namespace Henzeb\EventHorizon\Listeners;

use Laravel\Horizon\Listeners\StoreMonitoredTags as HorizonStoreMonitoredTags;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\Contracts\TagRepository;

class StoreMonitoredTags extends HorizonStoreMonitoredTags
{
    public function __construct(TagRepository $tags)
    {
        parent::__construct($tags);
    }

    public function handle(JobPushed $event): void
    {
        if (! $event->payload->tags()) {
            return;
        }

        $monitoring = $this->tags->monitored($event->payload->tags(), $event->connectionName);

        if (! empty($monitoring)) {
            $this->tags->add($event->payload->id(), $monitoring, $event->connectionName);
        }
    }
}