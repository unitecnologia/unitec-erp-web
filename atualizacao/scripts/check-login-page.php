<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$server = [
    'HTTP_HOST' => '127.0.0.1:8000',
    'SERVER_PORT' => '8000',
];

app()->instance('request', Request::create('http://127.0.0.1:8000/admin/login', 'GET', server: $server));

$kernel = $app->make(Kernel::class);
$session = app('session.store');
$session->start();

$request = Request::create('http://127.0.0.1:8000/admin/login', 'GET', server: $server);
$request->setLaravelSession($session);

$response = $kernel->handle($request);
$body = $response->getContent() ?: '';
$kernel->terminate($request, $response);

preg_match_all('/src="(http[^"]+)"/', $body, $srcMatches);
$badScripts = array_filter($srcMatches[1] ?? [], fn (string $s): bool => (bool) preg_match('#^http://127\.0\.0\.1/#', $s));

echo 'login status=' . $response->getStatusCode() . ' bytes=' . strlen($body) . PHP_EOL;
echo 'empresa_field=' . (int) str_contains($body, 'empresa_id') . PHP_EOL;
echo 'livewire=' . (int) str_contains($body, 'livewire') . PHP_EOL;
echo 'erp-shell_css=' . (int) str_contains($body, 'erp-shell.css') . PHP_EOL;
echo 'erp-login_css=' . (int) str_contains($body, 'erp-login.css') . PHP_EOL;
echo 'scripts_sem_porta=' . count($badScripts) . PHP_EOL;
echo 'page_boot_script=' . (int) str_contains($body, 'showPage') . PHP_EOL;
