<?php
return [
    'exports' => [
        'chunk_size' => 1000,
        'csv' => ['use_bom' => true],
    ],
    'imports' => [
        'read_only' => true,
        'ignore_empty' => true,
        'heading_row' => ['formatter' => 'none'],
        'csv' => ['input_encoding' => 'UTF-8'],
    ],
    'temporary_files' => [
        'local_path' => storage_path('framework/cache/laravel-excel'),
    ],
];
