<?php

return [
    'default' => [
        'name' => env('DEFAULT_NODE_NAME', 'HOME-01'),
        'code' => env('DEFAULT_NODE_CODE', 'HOME-01'),
        'provider' => strtoupper((string) env('DEPLOYMENT_PROVIDER', 'fake')),
        'provider_server_id' => env('DOKPLOY_SERVER_ID'),
        'region' => env('DEFAULT_NODE_REGION', 'local'),
        'cpu_total' => (float) env('DEFAULT_NODE_CPU_TOTAL', 8),
        'memory_total_mb' => (int) env('DEFAULT_NODE_MEMORY_TOTAL_MB', 16384),
        'disk_total_mb' => (int) env('DEFAULT_NODE_DISK_TOTAL_MB', 102400),
    ],
    'platform_reserve' => [
        'cpu' => (float) env('NODE_PLATFORM_CPU_RESERVE', 1),
        'memory_mb' => (int) env('NODE_PLATFORM_MEMORY_RESERVE_MB', 2048),
        'disk_mb' => (int) env('NODE_PLATFORM_DISK_RESERVE_MB', 10240),
    ],
    'pressure_threshold_percent' => (float) env('NODE_PRESSURE_THRESHOLD_PERCENT', 75),
];
