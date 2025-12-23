<?php
// Rate limiting configuration
return [
    'limits' => [
        'default' => [
            'requests' => 60,
            'period' => 60 // seconds
        ],
        'auth' => [
            'requests' => 10,
            'period' => 300 // 5 minutes for auth endpoints
        ],
        'reports' => [
            'requests' => 20,
            'period' => 300 // 5 minutes for report generation
        ]
    ],
    'storage' => [
        'driver' => 'file', // file, redis, database
        'path' => __DIR__ . '/../cache/rate_limit/'
    ]
];
?>