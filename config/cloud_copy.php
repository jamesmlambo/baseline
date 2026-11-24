<?php

declare(strict_types=1);

/*
 * Where `php artisan app:copy-content-to-cloud` copies laptop room content to (Laravel Cloud).
 * These are resource IDs, not secrets: the database password and bucket keys are read from the
 * Laravel Cloud CLI (vendor/bin/cloud, signed in with `cloud auth`) each time and never stored.
 */
return [
    'database_cluster' => env('CLOUD_COPY_DATABASE_CLUSTER'),
    'database' => env('CLOUD_COPY_DATABASE', 'production'),
    'public_bucket' => env('CLOUD_COPY_PUBLIC_BUCKET'),
    'public_bucket_key' => env('CLOUD_COPY_PUBLIC_BUCKET_KEY'),
];
