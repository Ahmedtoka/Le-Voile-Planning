<?php
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90],
    ],
    'batching' => ['database' => null, 'table' => 'job_batches'],
    'failed' => ['driver' => 'database-uuids', 'database' => null, 'table' => 'failed_jobs'],
];
