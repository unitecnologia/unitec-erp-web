<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pix Ailos
    |--------------------------------------------------------------------------
    | Produção: use somente Secrets do ambiente (nunca commit / front / logs):
    |   AILOS_PRODUCTION_CLIENT_ID
    |   AILOS_PRODUCTION_CLIENT_SECRET
    |   AILOS_PRODUCTION_PIX_KEY
    |   AILOS_PRODUCTION_PFX_BASE64
    |   AILOS_PRODUCTION_PFX_PASSWORD
    |
    | Se AILOS_PRODUCTION_PFX_* estiver vazio, o ERP usa o certificado A1 (.pfx)
    | já cadastrado para NF-e (mesmo arquivo / senha), sem gravar .pfx no Git.
    |
    | Homologação: AILOS_* ou campos da empresa (API PIX).
    */
    'timeout' => (int) env('AILOS_TIMEOUT', 20),
    'expiration_seconds' => (int) env('AILOS_EXPIRATION_SECONDS', 86400),
    'token_scopes' => env(
        'AILOS_TOKEN_SCOPES',
        'cob.read cob.write cobv.read cobv.write pix.read pix.write '
        .'webhook.read webhook.write qrcode.read qrcode.write '
        .'payloadlocation.write payloadlocation.read lotecobv.write lotecobv.read',
    ),
    'environments' => [
        'homologation' => [
            'base_url' => env(
                'AILOS_HOMOLOGATION_BASE_URL',
                'https://pixcobranca-h.ailos.coop.br/qa/ailos/pix-cobranca/api/v1',
            ),
            'client_id' => env('AILOS_CLIENT_ID'),
            'client_secret' => env('AILOS_CLIENT_SECRET'),
            'pix_key' => env('AILOS_PIX_KEY'),
            'pfx_path' => env('AILOS_PFX_PATH'),
            'pfx_base64' => env('AILOS_PFX_BASE64'),
            'pfx_password' => env('AILOS_PFX_PASSWORD'),
        ],
        'production' => [
            'base_url' => env(
                'AILOS_PRODUCTION_BASE_URL',
                'https://pixcobranca.ailos.coop.br/ailos/pix-cobranca/api/v1',
            ),
            'client_id' => env('AILOS_PRODUCTION_CLIENT_ID'),
            'client_secret' => env('AILOS_PRODUCTION_CLIENT_SECRET'),
            'pix_key' => env('AILOS_PRODUCTION_PIX_KEY'),
            'pfx_path' => env('AILOS_PRODUCTION_PFX_PATH'),
            'pfx_base64' => env('AILOS_PRODUCTION_PFX_BASE64'),
            'pfx_password' => env('AILOS_PRODUCTION_PFX_PASSWORD'),
        ],
    ],
];
