<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
app()->instance('request', Request::create('http://localhost/admin', 'GET'));

$user = User::query()->where('name', 'USUARIO')->first();
Auth::login($user);

$checks = [
    '/admin/products?view=seriais' => ['Nº SÉRIE', 'Seriais'],
    '/admin/reports/produtos-estoque' => ['LISTAGEM DE PRODUTOS', 'CUSTO COMPRA', 'report'],
];

foreach ($checks as $path => [$needle, $label]) {
    Auth::login($user);
    $session = app('session.store');
    $session->start();

    $request = Request::create($path, 'GET');
    $request->setLaravelSession($session);

    $response = $kernel->handle($request);
    $body = $response->getContent() ?: '';
    $kernel->terminate($request, $response);

    $ok = str_contains($body, $needle);

    echo $path . ': ' . ($ok ? 'OK' : 'FALTA ' . $label) . PHP_EOL;
}
