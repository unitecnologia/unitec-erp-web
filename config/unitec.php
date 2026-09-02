<?php

return [
    'app_name' => 'UNI SISTEMAS 3.0',
    'versao' => '6.4.1.175',
    'licenca' => env('UNITEC_LICENCA_LOCAL', ''),
    // Portal de renovaÃ§Ã£o â€” nativo (nÃ£o usa .env).
    'pagamento_url' => 'https://unitecnologiasc.digital',

    /*
    | LicenÃ§a remota (portal Unitec) â€” URL nativa abaixo.
    | Na empresa: sÃ³ habilitar + timeout (sem coluna de URL â€” evita row size no MySQL).
    */
    'licenca_api' => [
        'enabled' => true,
        'base_url' => 'https://unitecnologiasc.digital',
        'timeout' => 8,
        'cache_seconds' => 600,
        'grace_hours' => 24,
    ],

    /*
    | Contato exibido em Ajuda â†’ LicenÃ§a do Sistema (nativo, sem .env).
    */
    'licenca_suporte' => [
        'email' => 'sac@unitecnologiasc.com.br',
        'whatsapp' => '47984002117',
        'site' => 'https://unitecnologiasc.digital',
    ],

    /*
    | Zoom da interface no navegador (Chrome/Edge), em porcentagem.
    | Ex.: 90 = menor, 100 = normal, 110 = maior. Faixa: 50â€“200.
    */
    'browser_zoom' => max(50, min(200, (int) env('UNITEC_BROWSER_ZOOM', 100))),

    /*
    | Ao abrir o ERP: se o schema estiver atrÃ¡s do cÃ³digo (ex. restore de dump
    | antigo), aplica apenas `php artisan migrate --force`. Nunca migrate:fresh.
    | ERP_AUTO_MIGRATE=false â†’ sÃ³ detecta e avisa, sem alterar o banco.
    */
    'auto_migrate' => filter_var(env('ERP_AUTO_MIGRATE', true), FILTER_VALIDATE_BOOL),

    /*
    | AtualizaÃ§Ã£o remota de produÃ§Ã£o (UnitecErpServer).
    |
    | UNITEC_UPDATE_DOWNLOAD_URL = link HTTPS DIRETO do ZIP (recomendado no .env).
    | PadrÃ£o estÃ¡vel (GitHub Releases, canal "update"):
    |   https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip
    |
    | O ZIP deve conter a pasta unitec-erp-web/ (ou artisan na raiz).
    | ServiÃ§o baixa o ZIP, extrai em atualizacao/; login pergunta Sim/NÃ£o.
    */
    'update_download_url' => env(
        'UNITEC_UPDATE_DOWNLOAD_URL',
        'https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip'
    ),
    'update_zip_name' => 'Unitec-ERP-Update.zip',

    /** ok | failed Ã¢â‚¬â€ status exibido no alerta de backup do dashboard. */
    'backup_last_status' => env('UNITEC_BACKUP_LAST_STATUS', 'ok'),
    'backup_last_at' => env('UNITEC_BACKUP_LAST_AT'),

    /** Quantidade demo de NF rejeitadas no dashboard (0 = sÃƒÂ³ dados reais). */
    'dashboard_demo_nfe_rejeitadas' => env('UNITEC_DASHBOARD_DEMO_NFE_REJEITADAS', 3),

    /*
    | Unitecnologia Device Service Ã¢â‚¬â€ agente local no PC do caixa (localhost:9330).
    | O navegador chama a API; Laravel sÃƒÂ³ gera o ESC/POS (mike42).
    */
    'device_service' => [
        'base_url' => env('UNITEC_DEVICE_SERVICE_URL', 'http://127.0.0.1:9330'),
        'api_key' => env('UNITEC_DEVICE_SERVICE_KEY', ''),
        'timeout_ms' => (int) env('UNITEC_DEVICE_SERVICE_TIMEOUT_MS', 2500),
    ],

    /*
    | QZ Tray Ã¢â‚¬â€ legado (nÃƒÂ£o usado no fluxo atual; Device Service substitui).
    */
    'qz' => [
        'certificate' => env('QZ_CERTIFICATE_PATH', storage_path('app/qz/digital-certificate.txt')),
        'private_key' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/qz/private-key.pem')),
    ],

    /*
    | Cloudflare Tunnel â€” provisionamento de subdomÃ­nio (Acesso remoto).
    | PadrÃ£o Unitec (unierp.uk). Empresa (param_cf_*) e formulÃ¡rio sobrescrevem.
    | Token tambÃ©m pode ir no .env (CLOUDFLARE_API_TOKEN).
    */
    'cloudflare' => [
        // Token somente via .env (CLOUDFLARE_API_TOKEN).
        'api_token' => env('CLOUDFLARE_API_TOKEN', ''),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID') ?: '28103ae19943f8c0654a17b56e75b5da',
        'zone_id' => env('CLOUDFLARE_ZONE_ID') ?: 'a68a06560133f1b620e063cd0b0113ff',
        'base_domain' => env('CLOUDFLARE_BASE_DOMAIN') ?: 'unierp.uk',
        'local_service' => env('CLOUDFLARE_LOCAL_SERVICE', 'http://127.0.0.1:8765'),
        'program_data_dir' => env('CLOUDFLARE_PROGRAM_DATA_DIR', 'C:\\ProgramData\\Unitec\\cloudflared'),
    ],

    /*
    | Runtime web: frankenphp (padrÃ£o quando tools/frankenphp existe) ou php (php -S fallback).
    | ERP_LIST_SYNC_POLL=false desliga poll automÃ¡tico nas listas ERP (benchmark / performance).
    */
    'web_server' => env('UNITEC_WEB_SERVER', 'frankenphp'),
    'frankenphp_threads' => max(2, (int) env('FRANKENPHP_NUM_THREADS', 8)),
    'erp_list_sync_poll_enabled' => filter_var(env('ERP_LIST_SYNC_POLL', true), FILTER_VALIDATE_BOOL),
];


