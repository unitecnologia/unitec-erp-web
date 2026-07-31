<?php

return [

    'client_id' => env('MELI_CLIENT_ID'),

    'client_secret' => env('MELI_CLIENT_SECRET'),

    'redirect_uri' => env('MELI_REDIRECT_URI'),

    // Hub Unitec: OAuth/webhook central para clientes sem domínio próprio.
    'hub_url' => env('MELI_HUB_URL', 'https://unitecnologiasc.com.br'),

    'hub_redirect_uri' => env('MELI_HUB_REDIRECT_URI', 'https://unitecnologiasc.com.br/meli/hub/oauth/callback'),

    // true = este servidor É o hub Unitec (OAuth local).
    'is_hub' => filter_var(env('MELI_IS_HUB', false), FILTER_VALIDATE_BOOL),

    'auth_url' => env('MELI_AUTH_URL', 'https://auth.mercadolivre.com.br/authorization'),

    'token_url' => env('MELI_TOKEN_URL', 'https://api.mercadolibre.com/oauth/token'),

    'api_url' => env('MELI_API_URL', 'https://api.mercadolibre.com'),

    'oauth_state_ttl_minutes' => (int) env('MELI_OAUTH_STATE_TTL_MINUTES', 15),

];
