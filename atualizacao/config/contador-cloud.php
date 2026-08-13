<?php

return [
    'portal_base_url' => env('CONTADOR_CLOUD_PORTAL_BASE_URL', 'https://unitecnologiasc.com.br'),
    'health_path' => env('CONTADOR_CLOUD_HEALTH_PATH', '/api/portal/health'),
    'sync_path' => env('CONTADOR_CLOUD_SYNC_PATH', '/api/portal/documentos'),
    'pairing_request_path' => env('CONTADOR_CLOUD_PAIRING_REQUEST_PATH', '/api/portal/vinculos/solicitar'),
    'pairing_status_path' => env('CONTADOR_CLOUD_PAIRING_STATUS_PATH', '/api/portal/vinculos/{id}/status'),
    'default_timeout' => (int) env('CONTADOR_CLOUD_TIMEOUT', 30),
];
