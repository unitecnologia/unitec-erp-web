<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mercadopago' => [
        // Access token de produção da conta Mercado Pago (recebimentos Pix).
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
        // Segredo do webhook (opcional). Quando definido, validamos a assinatura.
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET', ''),
        // URL pública do webhook (opcional). Em servidor local fica vazio e a
        // confirmação acontece por polling (o app consulta o status).
        'webhook_url' => env('MERCADOPAGO_WEBHOOK_URL', ''),
        'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),
    ],

];
