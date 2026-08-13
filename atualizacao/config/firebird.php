<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebird legado (migração → ERP web)
    |--------------------------------------------------------------------------
    | Conexão via isql do Firebird instalado (compatível com SYSDBA legado).
    | Não use PDO aqui: o PHP do ERP é x64 e o servidor FB típico é x86/Legacy.
    */

    'enabled' => (bool) env('FB_ENABLED', false),

    'host' => env('FB_HOST', 'localhost'),

    'port' => (int) env('FB_PORT', 3050),

    'database' => env('FB_DATABASE', 'C:\\Sistema\\Dados\\dados.fdb'),

    'username' => env('FB_USERNAME', 'SYSDBA'),

    'password' => env('FB_PASSWORD', ''),

    'charset' => env('FB_CHARSET', 'WIN1252'),

    /*
    | Caminho do isql.exe (Firebird 3). Vazio = tenta caminhos padrão.
    */
    'isql' => env('FB_ISQL', ''),

    /*
    | Preferir arquivo local (embedded) em vez de host/porta.
    | Com o serviço Firebird + Delphi/PDV abertos, use false (TCP).
    | Embedded costuma falhar com "Wrong file for memory mapping".
    */
    'use_embedded' => (bool) env('FB_USE_EMBEDDED', false),

];
