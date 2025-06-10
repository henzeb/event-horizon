<?php

use Henzeb\EventHorizon\Repositories\RedisJobRepository;
use Laravel\Horizon\JobPayload;

beforeEach(function () {
    // Clear all redis databases before each test
    foreach (['service_auth', 'service_billing', 'horizon'] as $connection) {
        app('redis')->connection($connection)->flushdb();
    }
});

afterEach(function () {
    // Clean up all redis databases after each test
    foreach (['service_auth', 'service_billing', 'horizon'] as $connection) {
        app('redis')->connection($connection)->flushdb();
    }
});

test('pushed stores job in billing redis database when using billing_service connection', function () {
    $repository = new RedisJobRepository(app('redis'));
    
    $payloadData = json_encode([
        'uuid' => 'billing-job-uuid',
        'displayName' => 'BillingJob',
        'job' => 'BillingJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'billing-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Push job to billing service (database 1)
    $repository->pushed('billing_service', 'default', $payload);
    
    // Check that job appears in billing database (database 1)
    $billingRedis = app('redis')->connection('service_billing');
    expect($billingRedis->zcard('recent_jobs'))->toBeGreaterThan(0);
    expect($billingRedis->zcard('pending_jobs'))->toBeGreaterThan(0);
    
    // Check that auth database (database 0) remains empty
    $authRedis = app('redis')->connection('service_auth');
    expect($authRedis->zcard('recent_jobs'))->toBe(0);
    expect($authRedis->zcard('pending_jobs'))->toBe(0);
    
    // Check that horizon database remains empty too
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('recent_jobs'))->toBe(0);
    expect($horizonRedis->zcard('pending_jobs'))->toBe(0);
});

test('pushed stores job in auth redis database when using auth_service connection', function () {
    $repository = new RedisJobRepository(app('redis'));
    
    $payloadData = json_encode([
        'uuid' => 'auth-job-uuid',
        'displayName' => 'AuthJob',
        'job' => 'AuthJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'auth-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Push job to auth service (database 0)
    $repository->pushed('auth_service', 'default', $payload);
    
    // Check that job appears in auth database (database 0)
    $authRedis = app('redis')->connection('service_auth');
    expect($authRedis->zcard('recent_jobs'))->toBeGreaterThan(0);
    expect($authRedis->zcard('pending_jobs'))->toBeGreaterThan(0);
    
    // Check that billing database (database 1) remains empty
    $billingRedis = app('redis')->connection('service_billing');
    expect($billingRedis->zcard('recent_jobs'))->toBe(0);
    expect($billingRedis->zcard('pending_jobs'))->toBe(0);
});

test('pushed uses horizon connection when queue connection is not configured', function () {
    $repository = new RedisJobRepository(app('redis'));
    
    $payloadData = json_encode([
        'uuid' => 'horizon-job-uuid',
        'displayName' => 'HorizonJob',
        'job' => 'HorizonJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'horizon-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Push job with non-existent connection
    $repository->pushed('non_existent_connection', 'default', $payload);
    
    // Check that job appears in horizon database (fallback)
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('recent_jobs'))->toBeGreaterThan(0);
    expect($horizonRedis->zcard('pending_jobs'))->toBeGreaterThan(0);
});

test('pushed uses horizon connection when redis connection is not configured', function () {
    $repository = new RedisJobRepository(app('redis'));
    
    $payloadData = json_encode([
        'uuid' => 'test-job-uuid',
        'displayName' => 'TestJob',
        'job' => 'TestJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'test-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Temporarily add a queue connection that points to non-existent redis connection
    config(['queue.connections.invalid_redis' => [
        'driver' => 'redis',
        'connection' => 'non_existent_redis',
        'queue' => 'default',
    ]]);
    
    // Push job with connection that has invalid redis config
    $repository->pushed('invalid_redis', 'default', $payload);
    
    // Check that job appears in horizon database (fallback)
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('recent_jobs'))->toBeGreaterThan(0);
    expect($horizonRedis->zcard('pending_jobs'))->toBeGreaterThan(0);
});

test('pushed uses null connection when no connection parameter provided', function () {
    $repository = new RedisJobRepository(app('redis'));
    
    $payloadData = json_encode([
        'uuid' => 'null-connection-job-uuid',
        'displayName' => 'NullConnectionJob',
        'job' => 'NullConnectionJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'null-connection-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Push job with null connection
    $repository->pushed(null, 'default', $payload);
    
    // Check that job appears in horizon database (default)
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('recent_jobs'))->toBeGreaterThan(0);
    expect($horizonRedis->zcard('pending_jobs'))->toBeGreaterThan(0);
});

test('pushed sets horizon prefix when connection is provided', function () {
    $repository = new RedisJobRepository(app('redis'));
    
    // Set a test prefix in config
    config(['horizon.prefix' => 'test-horizon-prefix:']);
    
    $payloadData = json_encode([
        'uuid' => 'prefix-test-job-uuid',
        'displayName' => 'PrefixTestJob',
        'job' => 'PrefixTestJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'prefix-test-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Push job to billing service - this should set the prefix
    $repository->pushed('billing_service', 'default', $payload);
    
    // Get the redis connection and check if prefix option was set
    $redis = app('redis')->connection('service_billing');
    $client = $redis->client();
    
    // Redis option 2 is OPT_PREFIX
    $prefix = $client->getOption(2);
    expect($prefix)->toBe('test-horizon-prefix:');
});

test('pushed does not set horizon prefix when connection is null', function () {
    $repository = new RedisJobRepository(app('redis'));
    
    // Set a test prefix in config
    config(['horizon.prefix' => 'test-horizon-prefix:']);
    
    $payloadData = json_encode([
        'uuid' => 'null-prefix-test-job-uuid',
        'displayName' => 'NullPrefixTestJob',
        'job' => 'NullPrefixTestJob',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => ['test' => 'null-prefix-test-data']
    ]);
    
    $payload = new JobPayload($payloadData);
    
    // Clear any existing prefix on horizon connection
    $horizonRedis = app('redis')->connection('horizon');
    $horizonRedis->client()->setOption(2, null);
    
    // Push job with non-existent connection - should fall back to horizon without setting prefix
    $repository->pushed('non_existent_connection', 'default', $payload);
    
    // Get the redis connection and check if prefix option was NOT set
    $client = $horizonRedis->client();
    
    // Redis option 2 is OPT_PREFIX - should remain null since connection was null
    $prefix = $client->getOption(2);
    expect($prefix === null || $prefix === '')->toBeTrue();
});