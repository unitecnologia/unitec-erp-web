<?php

return [
    'app_name' => 'UNI SISTEMAS 3.0',
    'versao' => '6.4.1.177',
    'licenca' => env('UNITEC_LICENCA_LOCAL', ''),
    // Portal de renova├º├úo ÔÇö nativo (n├úo usa .env).
    'pagamento_url' => 'https://unitecnologiasc.digital',

    /*
    | Licen├ºa remota (portal Unitec) ÔÇö URL nativa abaixo.
    | Na empresa: s├│ habilitar + timeout (sem coluna de URL ÔÇö evita row size no MySQL).
    */
    'licenca_api' => [
        'enabled' => true,
        'base_url' => 'https://unitecnologiasc.digital',
        'timeout' => 8,
        'cache_seconds' => 600,
        'grace_hours' => 24,
    ],

    /*
    | Contato exibido em Ajuda ÔåÆ Licen├ºa do Sistema (nativo, sem .env).
    */
    'licenca_suporte' => [
        'email' => 'sac@unitecnologiasc.com.br',
        'whatsapp' => '47984002117',
        'site' => 'https://unitecnologiasc.digital',
    ],

    /*
    | Zoom da interface no navegador (Chrome/Edge), em porcentagem.
    | Ex.: 90 = menor, 100 = normal, 110 = maior. Faixa: 50ÔÇô200.
    */
    'browser_zoom' => max(50, min(200, (int) env('UNITEC_BROWSER_ZOOM', 100))),

    /*
    | Ao abrir o ERP: se o schema estiver atr├ís do c├│digo (ex. restore de dump
    | antigo), aplica apenas `php artisan migrate --force`. Nunca migrate:fresh.
    | ERP_AUTO_MIGRATE=false ÔåÆ s├│ detecta e avisa, sem alterar o banco.
    */
    'auto_migrate' => filter_var(env('ERP_AUTO_MIGRATE', true), FILTER_VALIDATE_BOOL),

    /*
    | Atualiza├º├úo remota de produ├º├úo (UnitecErpServer).
    |
    | UNITEC_UPDATE_DOWNLOAD_URL = link HTTPS DIRETO do ZIP (recomendado no .env).
    | Padr├úo est├ível (GitHub Releases, canal "update"):
    |   https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip
    |
    | O ZIP deve conter a pasta unitec-erp-web/ (ou artisan na raiz).
    | Servi├ºo baixa o ZIP, extrai em atualizacao/; login pergunta Sim/N├úo.
    */
    'update_download_url' => env(
        'UNITEC_UPDATE_DOWNLOAD_URL',
        'https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip'
    ),
    'update_zip_name' => 'Unitec-ERP-Update.zip',

    /** ok | failed ├óÔé¼ÔÇØ status exibido no alerta de backup do dashboard. */
    'backup_last_status' => env('UNITEC_BACKUP_LAST_STATUS', 'ok'),
    'backup_last_at' => env('UNITEC_BACKUP_LAST_AT'),

    /** Quantidade demo de NF rejeitadas no dashboard (0 = s├â┬│ dados reais). */
    'dashboard_demo_nfe_rejeitadas' => env('UNITEC_DASHBOARD_DEMO_NFE_REJEITADAS', 3),

    /*
    | Unitecnologia Device Service ├óÔé¼ÔÇØ agente local no PC do caixa (localhost:9330).
    | O navegador chama a API; Laravel s├â┬│ gera o ESC/POS (mike42).
    */
    'device_service' => [
        'base_url' => env('UNITEC_DEVICE_SERVICE_URL', 'http://127.0.0.1:9330'),
        'api_key' => env('UNITEC_DEVICE_SERVICE_KEY', ''),
        'timeout_ms' => (int) env('UNITEC_DEVICE_SERVICE_TIMEOUT_MS', 2500),
    ],

    /*
    | QZ Tray ├óÔé¼ÔÇØ legado (n├â┬úo usado no fluxo atual; Device Service substitui).
    */
    'qz' => [
        'certificate' => env('QZ_CERTIFICATE_PATH', storage_path('app/qz/digital-certificate.txt')),
        'private_key' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/qz/private-key.pem')),
    ],

    /*
    | Cloudflare Tunnel ÔÇö provisionamento de subdom├¡nio (Acesso remoto).
    | Padr├úo Unitec (unierp.uk). Empresa (param_cf_*) e formul├írio sobrescrevem.
    | Token tamb├®m pode ir no .env (CLOUDFLARE_API_TOKEN).
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
    | Runtime web HTTP: FrankenPHP obrigatório (DEV :8000 e produção :8765).
    | Sem fallback para php -S / artisan serve.
    | ERP_LIST_SYNC_POLL=false desliga poll automático nas listas ERP (benchmark / performance).
    */
    'web_server' => env('UNITEC_WEB_SERVER', 'frankenphp'),
    'frankenphp_threads' => max(2, (int) env('FRANKENPHP_NUM_THREADS', 8)),
    'erp_list_sync_poll_enabled' => filter_var(env('ERP_LIST_SYNC_POLL', true), FILTER_VALIDATE_BOOL),
];


