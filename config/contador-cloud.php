<?php

/*
|--------------------------------------------------------------------------
| Portal do Contador — integração GLOBAL (todas as instalações Unitec ERP)
|--------------------------------------------------------------------------
| URL, paths e autenticação de auto-vínculo são fixos no código.
| O cliente só habilita, escolhe o contador e as opções de envio.
| Cada empresa guarda o próprio token retornado pelo portal.
|
| O Bearer de auto-vínculo fica só no backend (nunca na UI nem em logs).
| Risco conhecido: o segredo global pode ser extraído de uma instalação.
| Não usar .env nem o Portal de Licenças / relay intermediário.
*/

return [
    'portal_base_url' => 'https://unitecnologiasc.com.br',
    'health_path' => '/api/portal/health',
    'sync_path' => '/api/portal/documentos',
    'pairing_auto_path' => '/api/portal/vinculos/auto',
    // Legados (diagnóstico interno) — o fluxo normal usa apenas /vinculos/auto.
    'pairing_request_path' => '/api/portal/vinculos/solicitar',
    'pairing_status_path' => '/api/portal/vinculos/{id}/status',
    'default_timeout' => 30,

    /*
    | Segredo Bearer exclusivo ERP ↔ Portal (POST /vinculos/auto).
    | Não reutilizar senha admin nem o token por empresa de /documentos.
    */
    'auto_vinculo_secret' => 'PGWYRZ63SaQzRJvwGxofGwHvefmtrpWRMobTwOhJBBB8H4m6',
];
