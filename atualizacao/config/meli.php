<?php

return [

    // Credenciais OAuth ficam no cadastro da empresa (param_meli_*). URLs fixas da API abaixo.

    'auth_url' => env('MELI_AUTH_URL', 'https://auth.mercadolivre.com.br/authorization'),

    'token_url' => env('MELI_TOKEN_URL', 'https://api.mercadolibre.com/oauth/token'),

    'api_url' => env('MELI_API_URL', 'https://api.mercadolibre.com'),

    'oauth_state_ttl_minutes' => (int) env('MELI_OAUTH_STATE_TTL_MINUTES', 15),

];
