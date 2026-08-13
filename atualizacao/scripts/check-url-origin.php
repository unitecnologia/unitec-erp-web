<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create(
    'http://127.0.0.1:8000/admin/login',
    'GET',
    server: [
        'HTTP_HOST' => '127.0.0.1:8000',
        'SERVER_PORT' => '8000',
        'SERVER_NAME' => '127.0.0.1',
    ],
);

echo 'schemeAndHost=' . $request->getSchemeAndHttpHost() . PHP_EOL;
echo 'httpHost=' . $request->getHttpHost() . PHP_EOL;
echo 'port=' . $request->getPort() . PHP_EOL;

app()->instance('request', $request);
Illuminate\Support\Facades\URL::useOrigin($request->getSchemeAndHttpHost());
echo 'asset=' . asset('css/erp-login.css') . PHP_EOL;
