<?php

use Henzeb\EventHorizon\Listeners\StoreMonitoredTags;
use Henzeb\EventHorizon\Repositories\RedisTagRepository;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\JobPayload;

test('listener stores monitored tags in billing redis database when job pushed to billing_service', function () {
    // Clear all databases
    app('redis')->connection('service_auth')->flushdb();
    app('redis')->connection('service_billing')->flushdb();
    app('redis')->connection('horizon')->flushdb();
    
    // Create a mock tag repository that monitors specific tags
    $tagRepository = new class(app('redis')) extends RedisTagRepository {
        public function monitoring(): array
        {
            return ['billing', 'payment', 'invoice'];
        }
    };
    
    $listener = new StoreMonitoredTags($tagRepository);
    
    // Create a job payload with tags
    $payloadData = json_encode([
        'uuid' => 'billing-job-uuid',
        'displayName' => 'BillingJob',
        'job' => 'BillingJob',
        'maxTries' => 3,
        'timeout' => 60,
        'tags' => ['billing', 'payment', 'unmonitored-tag'],
        'data' => ['test' => 'billing-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Create job pushed event with billing_service connection
    $event = (new JobPushed($payloadData))->connection('billing_service')->queue('default');
    
    // Handle the event
    $listener->handle($event);
    
    // Check that monitored tags appear in billing database (database 1)
    $billingRedis = app('redis')->connection('service_billing');
    expect($billingRedis->zcard('billing'))->toBeGreaterThan(0);
    expect($billingRedis->zcard('payment'))->toBeGreaterThan(0);
    expect($billingRedis->zrange('billing', 0, -1))->toContain('billing-job-uuid');
    expect($billingRedis->zrange('payment', 0, -1))->toContain('billing-job-uuid');
    
    // Check that unmonitored tags are not stored
    expect($billingRedis->zcard('unmonitored-tag'))->toBe(0);
    
    // Check that auth database (database 0) remains empty
    $authRedis = app('redis')->connection('service_auth');
    expect($authRedis->zcard('billing'))->toBe(0);
    expect($authRedis->zcard('payment'))->toBe(0);
    
    // Cleanup
    $billingRedis->flushdb();
});

test('listener stores monitored tags in auth redis database when job pushed to auth_service', function () {
    // Clear all databases
    app('redis')->connection('service_auth')->flushdb();
    app('redis')->connection('service_billing')->flushdb();
    app('redis')->connection('horizon')->flushdb();
    
    // Create a mock tag repository that monitors specific tags
    $tagRepository = new class(app('redis')) extends RedisTagRepository {
        public function monitoring(): array
        {
            return ['auth', 'login', 'user'];
        }
    };
    
    $listener = new StoreMonitoredTags($tagRepository);
    
    // Create a job payload with tags
    $payloadData = json_encode([
        'uuid' => 'auth-job-uuid',
        'displayName' => 'AuthJob',
        'job' => 'AuthJob',
        'maxTries' => 3,
        'timeout' => 60,
        'tags' => ['auth', 'login', 'unmonitored-tag'],
        'data' => ['test' => 'auth-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Create job pushed event with auth_service connection
    $event = (new JobPushed($payloadData))->connection('auth_service')->queue('default');
    
    // Handle the event
    $listener->handle($event);
    
    // Check that monitored tags appear in auth database (database 0)
    $authRedis = app('redis')->connection('service_auth');
    expect($authRedis->zcard('auth'))->toBeGreaterThan(0);
    expect($authRedis->zcard('login'))->toBeGreaterThan(0);
    expect($authRedis->zrange('auth', 0, -1))->toContain('auth-job-uuid');
    expect($authRedis->zrange('login', 0, -1))->toContain('auth-job-uuid');
    
    // Check that unmonitored tags are not stored
    expect($authRedis->zcard('unmonitored-tag'))->toBe(0);
    
    // Check that billing database (database 1) remains empty
    $billingRedis = app('redis')->connection('service_billing');
    expect($billingRedis->zcard('auth'))->toBe(0);
    expect($billingRedis->zcard('login'))->toBe(0);
    
    // Cleanup
    $authRedis->flushdb();
});

test('listener does nothing when job has no tags', function () {
    // Clear all databases
    app('redis')->connection('service_auth')->flushdb();
    app('redis')->connection('service_billing')->flushdb();
    app('redis')->connection('horizon')->flushdb();
    
    $tagRepository = new RedisTagRepository(app('redis'));
    $listener = new StoreMonitoredTags($tagRepository);
    
    // Create a job payload without tags
    $payloadData = json_encode([
        'uuid' => 'no-tags-job-uuid',
        'displayName' => 'NoTagsJob',
        'job' => 'NoTagsJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'data']
        // No tags array
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Create job pushed event
    $event = (new JobPushed($payloadData))->connection('billing_service')->queue('default');
    
    // Handle the event - should do nothing
    $listener->handle($event);
    
    // Check that no tags were stored in any database
    $billingRedis = app('redis')->connection('service_billing');
    $authRedis = app('redis')->connection('service_auth');
    $horizonRedis = app('redis')->connection('horizon');
    
    // Should all be empty since no tags to process
    expect($billingRedis->keys('*'))->toBe([]);
    expect($authRedis->keys('*'))->toBe([]);
    expect($horizonRedis->keys('*'))->toBe([]);
});

test('listener does nothing when no tags are monitored', function () {
    // Clear all databases
    app('redis')->connection('service_auth')->flushdb();
    app('redis')->connection('service_billing')->flushdb();
    app('redis')->connection('horizon')->flushdb();
    
    // Create a tag repository that monitors no tags
    $tagRepository = new class(app('redis')) extends RedisTagRepository {
        public function monitoring(): array
        {
            return []; // No monitored tags
        }
    };
    
    $listener = new StoreMonitoredTags($tagRepository);
    
    // Create a job payload with tags that aren't monitored
    $payloadData = json_encode([
        'uuid' => 'unmonitored-job-uuid',
        'displayName' => 'UnmonitoredJob', 
        'job' => 'UnmonitoredJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'data'],
        'tags' => ['unmonitored1', 'unmonitored2']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Create job pushed event
    $event = (new JobPushed($payloadData))->connection('billing_service')->queue('default');
    
    // Handle the event - should do nothing since no tags are monitored
    $listener->handle($event);
    
    // Check that no tags were stored in any database
    $billingRedis = app('redis')->connection('service_billing');
    $authRedis = app('redis')->connection('service_auth');
    $horizonRedis = app('redis')->connection('horizon');
    
    expect($billingRedis->keys('*'))->toBe([]);
    expect($authRedis->keys('*'))->toBe([]);
    expect($horizonRedis->keys('*'))->toBe([]);
});