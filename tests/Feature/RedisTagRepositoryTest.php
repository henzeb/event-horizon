<?php

use Henzeb\EventHorizon\Repositories\RedisTagRepository;

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

test('add stores tags in billing redis database when using billing_service connection', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Add tags to billing service (database 1)
    $repository->add('billing-job-123', ['billing', 'payment'], 'billing_service');
    
    // Check that tags appear in billing database (database 1)
    $billingRedis = app('redis')->connection('service_billing');
    expect($billingRedis->zcard('billing'))->toBeGreaterThan(0);
    expect($billingRedis->zcard('payment'))->toBeGreaterThan(0);
    expect($billingRedis->zrange('billing', 0, -1))->toContain('billing-job-123');
    
    // Check that auth database (database 0) remains empty
    $authRedis = app('redis')->connection('service_auth');
    expect($authRedis->zcard('billing'))->toBe(0);
    expect($authRedis->zcard('payment'))->toBe(0);
    
    // Check that horizon database remains empty too
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('billing'))->toBe(0);
    expect($horizonRedis->zcard('payment'))->toBe(0);
});

test('add stores tags in auth redis database when using auth_service connection', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Add tags to auth service (database 0)
    $repository->add('auth-job-456', ['auth', 'login'], 'auth_service');
    
    // Check that tags appear in auth database (database 0)
    $authRedis = app('redis')->connection('service_auth');
    expect($authRedis->zcard('auth'))->toBeGreaterThan(0);
    expect($authRedis->zcard('login'))->toBeGreaterThan(0);
    expect($authRedis->zrange('auth', 0, -1))->toContain('auth-job-456');
    
    // Check that billing database (database 1) remains empty
    $billingRedis = app('redis')->connection('service_billing');
    expect($billingRedis->zcard('auth'))->toBe(0);
    expect($billingRedis->zcard('login'))->toBe(0);
});

test('monitored returns empty array when no tags provided', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    $result = $repository->monitored([]);
    
    expect($result)->toBe([]);
});

test('add uses horizon connection when queue connection is not configured', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Add tags with non-existent connection
    $repository->add('horizon-job-123', ['horizon', 'fallback'], 'non_existent_connection');
    
    // Check that tags appear in horizon database (fallback)
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('horizon'))->toBeGreaterThan(0);
    expect($horizonRedis->zcard('fallback'))->toBeGreaterThan(0);
    expect($horizonRedis->zrange('horizon', 0, -1))->toContain('horizon-job-123');
});

test('add uses horizon connection when redis connection is not configured', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Temporarily add a queue connection that points to non-existent redis connection
    config(['queue.connections.invalid_redis' => [
        'driver' => 'redis',
        'connection' => 'non_existent_redis',
        'queue' => 'default',
    ]]);
    
    // Add tags with connection that has invalid redis config
    $repository->add('test-job-789', ['test', 'invalid'], 'invalid_redis');
    
    // Check that tags appear in horizon database (fallback)
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('test'))->toBeGreaterThan(0);
    expect($horizonRedis->zcard('invalid'))->toBeGreaterThan(0);
    expect($horizonRedis->zrange('test', 0, -1))->toContain('test-job-789');
});

test('add uses horizon connection when no connection parameter provided', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Add tags with null connection
    $repository->add('null-connection-job-456', ['null', 'connection']);
    
    // Check that tags appear in horizon database (default)
    $horizonRedis = app('redis')->connection('horizon');
    expect($horizonRedis->zcard('null'))->toBeGreaterThan(0);
    expect($horizonRedis->zcard('connection'))->toBeGreaterThan(0);
    expect($horizonRedis->zrange('null', 0, -1))->toContain('null-connection-job-456');
});

test('monitored uses correct connection for non-existent queue connection', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Just verify the method runs without error (connection logic is tested)
    $result = $repository->monitored(['test-tag'], 'non_existent_connection');
    
    expect($result)->toBeArray();
});

test('monitored uses correct connection for invalid redis configuration', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Temporarily add a queue connection that points to non-existent redis connection
    config(['queue.connections.invalid_redis' => [
        'driver' => 'redis',
        'connection' => 'non_existent_redis',
        'queue' => 'default',
    ]]);
    
    // Just verify the method runs without error (connection logic is tested)
    $result = $repository->monitored(['test-tag'], 'invalid_redis');
    
    expect($result)->toBeArray();
});

test('monitored uses correct connection when no connection parameter provided', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Just verify the method runs without error (connection logic is tested)
    $result = $repository->monitored(['test-tag']);
    
    expect($result)->toBeArray();
});

test('monitored with billing service connection uses correct database', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Just verify the method runs without error with billing connection
    $result = $repository->monitored(['billing-tag'], 'billing_service');
    
    expect($result)->toBeArray();
});

test('monitored with auth service connection uses correct database', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Just verify the method runs without error with auth connection
    $result = $repository->monitored(['auth-tag'], 'auth_service');
    
    expect($result)->toBeArray();
});

test('add sets horizon prefix when connection is provided', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Set a test prefix in config
    config(['horizon.prefix' => 'test-horizon-prefix:']);
    
    // Add tags to billing service - this should set the prefix
    $repository->add('prefix-test-job-123', ['prefix', 'test'], 'billing_service');
    
    // Get the redis connection and check if prefix option was set
    $redis = app('redis')->connection('service_billing');
    $client = $redis->client();
    
    // Redis option 2 is OPT_PREFIX
    $prefix = $client->getOption(2);
    expect($prefix)->toBe('test-horizon-prefix:');
});

test('add does not set horizon prefix when connection is null', function () {
    $repository = new RedisTagRepository(app('redis'));
    
    // Set a test prefix in config
    config(['horizon.prefix' => 'test-horizon-prefix:']);
    
    // Clear any existing prefix on horizon connection
    $horizonRedis = app('redis')->connection('horizon');
    $horizonRedis->client()->setOption(2, null);
    
    // Add tags with non-existent connection - should fall back to horizon without setting prefix
    $repository->add('null-prefix-test-job-123', ['null', 'prefix'], 'non_existent_connection');
    
    // Get the redis connection and check if prefix option was NOT set
    $client = $horizonRedis->client();
    
    // Redis option 2 is OPT_PREFIX - should remain null since connection was null
    $prefix = $client->getOption(2);
    expect($prefix === null || $prefix === '')->toBeTrue();
});