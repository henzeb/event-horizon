# Event Horizon

[![Tests](https://github.com/henzeb/event-horizon/workflows/tests/badge.svg)](https://github.com/henzeb/event-horizon/actions)
[![Latest Stable Version](https://poser.pugx.org/henzeb/event-horizon/v/stable)](https://packagist.org/packages/henzeb/event-horizon)
[![License](https://poser.pugx.org/henzeb/event-horizon/license)](https://packagist.org/packages/henzeb/event-horizon)

Event Horizon fixes Laravel Horizon's job monitoring when you have multiple Redis connections. It makes sure job tracking data goes to the right place - where the job is actually processed, not where it was pushed from.

## The Problem

Horizon gets confused when you use multiple Redis connections. Here's what happens:

You push a job from connection A, but it gets processed on connection B. Horizon shows the job as "pending" on connection A's dashboard, even though it's actually running on connection B. This makes your dashboards misleading and makes it hard to see what's really happening with your jobs.

This becomes a real headache when you're trying to monitor job performance, debug issues, or just understand what's going on in your application. You end up checking multiple dashboards, seeing conflicting information, and never getting a clear picture of your job processing.

The root cause is that Horizon was built assuming you'd use one Redis connection for everything. When you have multiple connections (which is pretty common in modern apps), its monitoring system doesn't know how to handle jobs that cross connection boundaries.

## The Solution

Event Horizon fixes this by tracking jobs where they actually get processed. When you push a job to connection B, all the monitoring data (job status, tags, metrics) gets stored in connection B's Redis instance, not where you pushed it from.

This means your Horizon dashboards show the real picture - jobs appear where they're actually running, making it much easier to monitor what's happening. No more hunting across different dashboards or trying to piece together where your jobs really are.

The package works by extending Horizon's core monitoring classes to be connection-aware. It automatically detects which Redis connection a job is destined for and routes all monitoring data there. You don't need to change any of your existing code - it just works.

## Installation

```bash
composer require henzeb/event-horizon
```

That's it! Works automatically once installed.

## When You Need This

- **Multiple Redis connections**: Your app uses different Redis instances for different purposes
- **Multi-tenant applications**: Each tenant has their own Redis instance and you want isolated monitoring
- **Microservices**: Different services push jobs to different connections
- **Load balancing**: You're spreading jobs across multiple Redis instances
- **Team separation**: Different teams manage different queue connections and want their own dashboards

## Example

```php
// Configure different connections for different purposes
config([
    'queue.connections.web' => ['connection' => 'redis_web'],
    'queue.connections.reports' => ['connection' => 'redis_reports'], 
    'queue.connections.exports' => ['connection' => 'redis_exports'],
]);

// Jobs show up on the right dashboard where they're processed
Queue::connection('web')->push(new ProcessOrder($order));       // Appears on web dashboard
Queue::connection('reports')->push(new GenerateReport($data));  // Appears on reports dashboard
Queue::connection('exports')->push(new ExportData($params));    // Appears on exports dashboard
```

Before Event Horizon, all these jobs might show up on the wrong dashboards depending on where you pushed them from. After installing it, each job appears exactly where you'd expect - on the dashboard for the connection that's actually processing it.

## How It Works

Event Horizon extends three key parts of Horizon:

- **Job monitoring**: Tracks jobs in the correct Redis instance
- **Tag storage**: Stores job tags where the job is processed
- **Event handling**: Ensures monitoring events go to the right place

The package automatically replaces Horizon's default behavior with connection-aware versions. Everything happens behind the scenes - you don't need to change your existing job code or queue configuration.

## Requirements

- PHP 8.2+
- Laravel Horizon ^5.0
- All Horizon instances must use the same `horizon.prefix` configuration value

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.