<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = $argv[1] ?? '/admin/products';
$host = $argv[2] ?? 'http://127.0.0.1:8000';

app()->instance('request', Request::create($host . '/admin', 'GET'));

$user = User::query()->where('name', 'USUARIO')->first();
Auth::guard('web')->login($user);
$kernel = $app->make(Kernel::class);

$session = app('session.store');
$session->start();

$request = Request::create($path, 'GET', server: ['HTTP_HOST' => parse_url($host, PHP_URL_HOST) ?: '127.0.0.1']);
$request->setLaravelSession($session);

$response = $kernel->handle($request);
$body = $response->getContent() ?: '';
$kernel->terminate($request, $response);

echo 'path=' . $path . PHP_EOL;
echo 'status=' . $response->getStatusCode() . ' bytes=' . strlen($body) . PHP_EOL;
echo 'erp-shell=' . (int) str_contains($body, 'erp-shell') . PHP_EOL;
echo 'erp-produtos=' . (int) str_contains($body, 'erp-produtos') . PHP_EOL;
echo 'x-cloak=' . (int) str_contains($body, 'x-cloak') . PHP_EOL;

if (preg_match_all('/<script[^>]+src="([^"]+)"/', $body, $matches)) {
    echo 'scripts:' . PHP_EOL;
    foreach (array_slice($matches[1], 0, 8) as $src) {
        echo '  ' . $src . PHP_EOL;
    }
}
