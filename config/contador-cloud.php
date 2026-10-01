<?php

return [
    'portal_base_url' => env('CONTADOR_CLOUD_PORTAL_BASE_URL', 'https://unitecnologiasc.com.br'),
    'health_path' => env('CONTADOR_CLOUD_HEALTH_PATH', '/api/portal/health'),
    'sync_path' => env('CONTADOR_CLOUD_SYNC_PATH', '/api/portal/documentos'),
    'pairing_request_path' => env('CONTADOR_CLOUD_PAIRING_REQUEST_PATH', '/api/portal/vinculos/solicitar'),
    'pairing_auto_path' => env('CONTADOR_CLOUD_PAIRING_AUTO_PATH', '/api/portal/vinculos/auto'),
    'pairing_status_path' => env('CONTADOR_CLOUD_PAIRING_STATUS_PATH', '/api/portal/vinculos/{id}/status'),
    'default_timeout' => (int) env('CONTADOR_CLOUD_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Segredo exclusivo do auto-vínculo (ERP ↔ Portal)
    |--------------------------------------------------------------------------
    | Mesmo valor do Replit Secret ERP_AUTO_VINCULO_SECRET no portal.
    | Enviado como Authorization: Bearer em POST /vinculos/auto.
    | Default no código para ir no ZIP de update (todos os clientes).
    | .env sobrescreve se precisar rotacionar em DEV/suporte.
    | NÃO reutilizar senha admin nem o token da empresa.
    */
    'auto_vinculo_secret' => env(
        'ERP_AUTO_VINCULO_SECRET',
        'PGWYRZ63SaQzRJvwGxofGwHvefmtrpWRMobTwOhJBBB8H4m6',
    ),
];
