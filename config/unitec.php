<?php

return [
    'app_name' => 'UNI SISTEMAS 3.0',
    'versao' => '6.4.1.55',
    'licenca' => env('UNITEC_LICENCA_LOCAL', ''),
    // Portal de renovação — nativo (não usa .env).
    'pagamento_url' => 'https://unitecnologiasc.digital',

    /*
    | LicenÃ§a remota (gerenciador Unitec / Replit).
    | GET {base_url}/api/licenca/{cnpj}  â€” sem autenticaÃ§Ã£o
    | CNPJ na URL: com ou sem mÃ¡scara (o ERP envia sÃ³ dÃ­gitos).
    | Resposta: { "status": "ativo"|"bloqueado", "valido_ate": "AAAA-MM-DD"|null, "nome": "..." }
    | Sem UNITEC_LICENCA_API_URL a validaÃ§Ã£o remota fica desligada (dev/local).
    */
    'licenca_api' => [
        'enabled' => filter_var(env('UNITEC_LICENCA_API_ENABLED', true), FILTER_VALIDATE_BOOL),
        'base_url' => rtrim((string) env('UNITEC_LICENCA_API_URL', ''), '/'),
        'timeout' => (int) env('UNITEC_LICENCA_API_TIMEOUT', 5),
        'cache_seconds' => (int) env('UNITEC_LICENCA_API_CACHE_SECONDS', 600),
        'grace_hours' => (int) env('UNITEC_LICENCA_API_GRACE_HOURS', 24),
    ],

    /*
    | Contato exibido em Ajuda → Licença do Sistema (nativo, sem .env).
    */
    'licenca_suporte' => [
        'email' => 'sac@unitecnologiasc.com.br',
        'whatsapp' => '47996446859',
        'site' => 'https://unitecnologiasc.digital',
    ],

    /*
    | Zoom da interface no navegador (Chrome/Edge), em porcentagem.
    | Ex.: 90 = menor, 100 = normal, 110 = maior. Faixa: 50â€“200.
    | NÃ£o controla o Ctrl+/- do navegador â€” aplica escala visual do ERP.
    */
    'browser_zoom' => max(50, min(200, (int) env('UNITEC_BROWSER_ZOOM', 100))),

    /*
    | AtualizaÃ§Ã£o remota (Ajuda â†’ Atualizar Sistema).
    |
    | UNITEC_UPDATE_DOWNLOAD_URL = link HTTPS DIRETO do ZIP (recomendado no .env).
    | PadrÃ£o estÃ¡vel (GitHub Releases, canal "update"):
    |   https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip
    |
    | O ZIP deve conter a pasta unitec-erp-web/ (ou artisan na raiz).
    | Preserva .env, storage/ e tools/ na instalaÃ§Ã£o local.
    */
    'update_download_url' => env(
        'UNITEC_UPDATE_DOWNLOAD_URL',
        'https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip'
    ),
    'update_zip_name' => 'Unitec-ERP-Update.zip',

    /** ok | failed â€” status exibido no alerta de backup do dashboard. */
    'backup_last_status' => env('UNITEC_BACKUP_LAST_STATUS', 'ok'),
    'backup_last_at' => env('UNITEC_BACKUP_LAST_AT'),

    /** Quantidade demo de NF rejeitadas no dashboard (0 = sÃ³ dados reais). */
    'dashboard_demo_nfe_rejeitadas' => env('UNITEC_DASHBOARD_DEMO_NFE_REJEITADAS', 3),

    /*
    | Unitecnologia Device Service â€” agente local no PC do caixa (localhost:9330).
    | O navegador chama a API; Laravel sÃ³ gera o ESC/POS (mike42).
    */
    'device_service' => [
        'base_url' => env('UNITEC_DEVICE_SERVICE_URL', 'http://127.0.0.1:9330'),
        'api_key' => env('UNITEC_DEVICE_SERVICE_KEY', ''),
        'timeout_ms' => (int) env('UNITEC_DEVICE_SERVICE_TIMEOUT_MS', 2500),
    ],

    /*
    | QZ Tray â€” legado (nÃ£o usado no fluxo atual; Device Service substitui).
    */
    'qz' => [
        'certificate' => env('QZ_CERTIFICATE_PATH', storage_path('app/qz/digital-certificate.txt')),
        'private_key' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/qz/private-key.pem')),
    ],

    /** @deprecated Use update_download_url com link HTTPS direto. */
    'update_mega_folder_url' => 'https://mega.nz/folder/fx9SxYKR#gd8_9RLC0JXqaykepo-qAw',
];


