<?php

return [
    'app_name' => 'UNI SISTEMAS 3.0',
    'versao' => '6.4.1.37',
    'licenca' => '12/12/2026',
    'pagamento_url' => 'https://unitecnologiasistemas.com.br',

    /*
    | Atualização remota (Ajuda → Atualizar Sistema).
    |
    | UNITEC_UPDATE_DOWNLOAD_URL = link HTTPS DIRETO do ZIP (obrigatório).
    | Ex.: https://www.dropbox.com/scl/fi/.../Unitec-ERP-Update.zip?...&dl=1
    | Ex.: https://unitecnologiasistemas.com.br/updates/Unitec-ERP-Update.zip
    |
    | O ZIP deve conter a pasta unitec-erp-web/ (ou artisan na raiz).
    | Preserva .env, storage/ e tools/ na instalação local.
    */
    'update_download_url' => env('UNITEC_UPDATE_DOWNLOAD_URL'),
    'update_zip_name' => 'Unitec-ERP-Update.zip',

    /** ok | failed — status exibido no alerta de backup do dashboard. */
    'backup_last_status' => env('UNITEC_BACKUP_LAST_STATUS', 'ok'),
    'backup_last_at' => env('UNITEC_BACKUP_LAST_AT'),

    /** Quantidade demo de NF rejeitadas no dashboard (0 = só dados reais). */
    'dashboard_demo_nfe_rejeitadas' => env('UNITEC_DASHBOARD_DEMO_NFE_REJEITADAS', 3),

    /** @deprecated Use update_download_url com link HTTPS direto. */
    'update_mega_folder_url' => 'https://mega.nz/folder/fx9SxYKR#gd8_9RLC0JXqaykepo-qAw',
];
