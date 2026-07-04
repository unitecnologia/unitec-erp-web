<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

app()->instance('request', Request::create('http://127.0.0.1:8000/admin', 'GET', server: [
    'HTTP_HOST' => '127.0.0.1:8000',
    'SERVER_PORT' => '8000',
]));

$user = User::query()->where('name', 'USUARIO')->first();
Auth::guard('web')->login($user);
$kernel = $app->make(Kernel::class);
$session = app('session.store');
$session->start();

$request = Request::create(
    'http://127.0.0.1:8000/admin/people?tipo=todos',
    'GET',
    server: [
        'HTTP_HOST' => '127.0.0.1:8000',
        'SERVER_PORT' => '8000',
    ],
);
$request->setLaravelSession($session);

$response = $kernel->handle($request);
$body = $response->getContent() ?: '';
$kernel->terminate($request, $response);

preg_match_all('/src="(http[^"]+)"/', $body, $srcMatches);
$badScripts = array_filter($srcMatches[1] ?? [], fn (string $s): bool => (bool) preg_match('#^http://127\.0\.0\.1/#', $s));

echo 'status=' . $response->getStatusCode() . ' bytes=' . strlen($body) . PHP_EOL;
echo 'erp-pessoas=' . (int) str_contains($body, 'erp-pessoas') . PHP_EOL;
echo 'erp-list-page=' . (int) str_contains($body, 'erp-list-page') . PHP_EOL;
echo 'fi-ta-row=' . (int) str_contains($body, 'fi-ta-row') . PHP_EOL;
echo 'x-cloak=' . (int) str_contains($body, 'x-cloak') . PHP_EOL;
echo 'scripts_sem_porta=' . count($badScripts) . PHP_EOL;
echo 'first_script=' . ($srcMatches[1][0] ?? 'none') . PHP_EOL;
echo 'wire_model_live=' . substr_count($body, 'wire:model.live') . PHP_EOL;
