<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Homecare runs entirely on MongoDB. The master database holds users and
    | the homecare (tenant) registry; every homecare center gets its own
    | tenant database, selected per request by the ResolveTenant middleware.
    |
    */

    'default' => env('DB_CONNECTION', 'mongodb_master'),

    'connections' => [

        'mongodb_master' => [
            'driver' => 'mongodb',
            'dsn' => env('DB_DSN'),
            'database' => env('DB_MASTER_DATABASE', 'homecare_master'),
        ],

        // The database name is replaced at runtime by TenantService::use().
        // It points at the master database until then so an unresolved
        // tenant never silently writes into a random database.
        'mongodb_tenant' => [
            'driver' => 'mongodb',
            'dsn' => env('DB_DSN'),
            'database' => env('DB_MASTER_DATABASE', 'homecare_master'),
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Optional. Only used if CACHE_STORE or QUEUE_CONNECTION is set to redis.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
