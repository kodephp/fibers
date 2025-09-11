<?php

return [
    'default_pool' => [
        'size' => 80,
        'timeout' => 30,
        'max_retries' => 3,
        'context' => ['user_id' => null]
    ],
    'channels' => [
        'orders' => ['buffer_size' => 100],
        'logs' => ['buffer_size' => 50]
    ],
    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true
    ]
];
