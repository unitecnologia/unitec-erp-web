<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token de carga do mini-PDV offline
    |--------------------------------------------------------------------------
    | Segredo compartilhado (Bearer) que cada caixa usa para baixar a carga
    | (produtos, clientes, formas de pagamento e configuração fiscal). Gere um
    | valor forte e configure o mesmo token no .env do PDV (PDV_CENTRAL_TOKEN).
    */
    'token' => env('PDV_CARGA_TOKEN'),

    // Empresa padrão usada quando o PDV não informa empresa_id na requisição.
    'default_empresa_id' => env('PDV_CARGA_EMPRESA_ID'),

    // Usuário do ERP ao qual as vendas offline importadas ficam vinculadas
    // (user_id/valor_abertura da sessão de caixa de importação). Se vazio, usa
    // o primeiro usuário da empresa ou o primeiro usuário do sistema.
    'import_user_id' => env('PDV_CARGA_IMPORT_USER_ID'),

    // Efeitos colaterais gerados ao importar cada venda offline (além do
    // registro da venda + baixa de estoque + NFC-e). Todos idempotentes pelo
    // uuid da venda (só rodam na primeira importação).
    'retorno_gerar_financeiro' => env('PDV_CARGA_RETORNO_FINANCEIRO', true), // contas a receber (a prazo/cartão/cheque/boleto/crediário)
    'retorno_gerar_caixa' => env('PDV_CARGA_RETORNO_CAIXA', true),           // movimento no caixa (pdv_caixa_movimentos)
    'retorno_gerar_espelho' => env('PDV_CARGA_RETORNO_ESPELHO', true),       // espelho no ledger central (vendas/venda_itens)
];
