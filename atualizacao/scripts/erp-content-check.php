<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
app()->instance('request', Request::create('http://localhost/admin', 'GET'));

$user = User::query()->where('name', 'USUARIO')->first();
Auth::guard('web')->login($user);
$kernel = $app->make(Kernel::class);

$checks = [
    '/admin/products' => ['erp-produtos', 'erp-list-page', 'F6'],
    '/admin/people?tipo=todos' => ['erp-pessoas', 'erp-list-page'],
    '/admin/pdv' => ['erp-pdv', 'CAIXA'],
    '/admin' => ['erp-shell', 'erp-shortcut-bar', 'erp-dash'],
];

foreach ($checks as $path => $needles) {
    Auth::guard('web')->login($user);
    $session = app('session.store');
    $session->start();

    $request = Request::create($path, 'GET');
    $request->setLaravelSession($session);

    $response = $kernel->handle($request);
    $body = $response->getContent() ?: '';
    $kernel->terminate($request, $response);

    $missing = array_values(array_filter($needles, fn (string $n): bool => ! str_contains($body, $n)));

    echo $path . ': ' . ($missing === [] ? 'OK' : 'FALTA ' . implode(', ', $missing)) . PHP_EOL;
}
