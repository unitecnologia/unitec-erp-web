<?php

return [
    'health_path' => env('CONTADOR_CLOUD_HEALTH_PATH', '/api/v1/health'),
    'sync_path' => env('CONTADOR_CLOUD_SYNC_PATH', '/api/v1/sync/documentos'),
    'default_timeout' => (int) env('CONTADOR_CLOUD_TIMEOUT', 30),
];
