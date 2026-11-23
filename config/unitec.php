<?php

return [
    'app_name' => 'UNI SISTEMAS 3.0',
    'versao' => '6.4.1.280',
    'licenca' => env('UNITEC_LICENCA_LOCAL', ''),

    /*
    | Licença remota (portal Unitec) — URL nativa abaixo.
    | Na empresa: só habilitar (timeout vem do config).
    */
    'licenca_api' => [
        'enabled' => true,
        'base_url' => 'https://unitecnologiasc.digital',
        'timeout' => 8,
        'cache_seconds' => 600,
        'grace_hours' => 24,
        // Dias corridos após o vencimento da mensalidade em que o ERP ainda libera (inclusive).
        // Ex.: vence 10/10 → liberado até 12/10; bloqueia a partir de 13/10.
        'grace_days' => 2,
    ],

    /*
    | Log de backup no portal. Token único do ERP (não é parâmetro por empresa).
    | O POST usa o CNPJ de cada empresa; a conexão e o token são os mesmos.
    */
    'portal_bkp' => [
        'token' => 'alencar@1234',
    ],

    /*
    | Consulta de veículo por placa (Verifica Online).
    | Igual para todas as instalações: não depende do cadastro de cada empresa.
    */
    'consulta_placa' => [
        'enabled' => true,
        'base_url' => 'https://api.verifica-online.com.br',
        'timeout' => 10,
        'token' => 'voa_86f6e59e302456ee17ac7f514e7d433ee972bcf6ba08b24aa921751c597c03e2',
    ],

    /*
    | Pesquisa de imagens de produto (Serper).
    | Igual para todas as instalações: não depende do cadastro de cada empresa.
    */
    'imagens_serper' => [
        'url' => 'https://google.serper.dev/images',
        'key' => 'e35fb260dfabd7d210870b966b01ed675d07f8b5',
        'timeout' => 30,
    ],

    /*
    | Consulta de parcelas de todos os clientes no Portal Financeiro.
    | O atalho só aparece com a flag ligada e o token preenchido no .env.
    */
    'portal_erp_parcelas' => [
        'enabled' => filter_var(env('PORTAL_ERP_PARCELAS', false), FILTER_VALIDATE_BOOL),
        'token' => (string) env('PORTAL_ERP_TOKEN', ''),
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
    | Compatibilidade de schema no painel: apenas verificação leve (sem migrate).
    | Migrate fica no atualizador oficial (gate pending=0) e no Reparar Sistema.
    | ERP_AUTO_MIGRATE permanece por compatibilidade de .env; o HTTP não aplica migrate.
    */
    'auto_migrate' => filter_var(env('ERP_AUTO_MIGRATE', false), FILTER_VALIDATE_BOOL),

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
    | UI Helper — processo na sessão interativa (Unitec ERP.exe --ui-helper).
    | FolderBrowserDialog para Backup quando o PHP roda sob UnitecErpServer (Session 0).
    | Porta distinta do Device Service (9330).
    */
    'ui_helper' => [
        'base_url' => env('UNITEC_UI_HELPER_URL', 'http://127.0.0.1:9331'),
        'timeout_seconds' => (int) env('UNITEC_UI_HELPER_TIMEOUT_SECONDS', 300),
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

    /*
    | Unitec OS — em local libera aparelho no register (sem F2 a cada flutter run).
    | Produção: false (autorização manual em Terminais → Aparelhos).
    */
    'os_auto_approve_devices' => filter_var(
        env('UNITEC_OS_AUTO_APPROVE', env('APP_ENV') === 'local' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOL
    ),

    /*
    | Unitec Entregas — em local libera aparelho no register.
    | Produção: false (autorização manual em Terminais → Aparelhos).
    */
    'entregas_auto_approve_devices' => filter_var(
        env('UNITEC_ENTREGAS_AUTO_APPROVE', env('APP_ENV') === 'local' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOL
    ),

    /*
    | SuperTEF no PDV (débito integrado).
    | Padrão: desligado para clientes. Em APP_ENV=local (DEV) allow_local libera
    | somente se o terminal tiver SuperTEF ativo + chave + POS + token SH.
    | Nunca republicar com enabled=true sem pedido explícito.
    */
    'supertef_pdv' => [
        'enabled' => filter_var(env('UNITEC_SUPERTEF_PDV', false), FILTER_VALIDATE_BOOL),
        'allow_local' => filter_var(env('UNITEC_SUPERTEF_PDV_ALLOW_LOCAL', true), FILTER_VALIDATE_BOOL),
        'poll_seconds' => max(4, (int) env('UNITEC_SUPERTEF_PDV_POLL_SECONDS', 4)),
    ],

    /*
    | Scope TEF / Conexão Tech (2º provedor eletrônico).
    | Padrão: desligado. sdk_available=false até documentação + SDK oficiais.
    | Nunca republicar com enabled=true sem pedido explícito e homologação.
    */
    'scope_tef_pdv' => [
        'enabled' => filter_var(env('UNITEC_SCOPE_TEF_PDV', false), FILTER_VALIDATE_BOOL),
        'allow_local' => filter_var(env('UNITEC_SCOPE_TEF_PDV_ALLOW_LOCAL', true), FILTER_VALIDATE_BOOL),
        'sdk_available' => filter_var(env('UNITEC_SCOPE_TEF_SDK_AVAILABLE', false), FILTER_VALIDATE_BOOL),
        'poll_seconds' => max(4, (int) env('UNITEC_SCOPE_TEF_PDV_POLL_SECONDS', 4)),
    ],

    /*
    | Conciliação Bancária — C2 OFX + C3 manual 1:1 + C4B sugestões PIX (serviço).
    | Candidatos / sugestões só sob demanda. Sem polling. Sem mount.
    */
    'conciliacao' => [
        'ofx_max_bytes' => max(64 * 1024, (int) env('UNITEC_OFX_MAX_BYTES', 8 * 1024 * 1024)),
        'ofx_batch_size' => max(50, min(500, (int) env('UNITEC_OFX_BATCH_SIZE', 200))),
        'ofx_extensions' => ['ofx', 'qfx'],
        'c3_date_window_days' => max(0, min(15, (int) env('UNITEC_C3_DATE_WINDOW_DAYS', 3))),
        'c3_candidates_per_page' => max(5, min(50, (int) env('UNITEC_C3_CANDIDATES_PER_PAGE', 10))),
        'c3_candidates_max' => max(10, min(100, (int) env('UNITEC_C3_CANDIDATES_MAX', 50))),
        'c4b_date_window_days' => max(0, min(15, (int) env('UNITEC_C4B_DATE_WINDOW_DAYS', 3))),
        'c4b_candidates_max' => max(5, min(50, (int) env('UNITEC_C4B_CANDIDATES_MAX', 20))),
    ],
];


