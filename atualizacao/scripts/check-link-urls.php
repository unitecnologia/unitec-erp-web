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

$paths = ['/admin', '/admin/products', '/admin/pdv'];

foreach ($paths as $path) {
    Auth::guard('web')->login($user);

    $request = Request::create(
        'http://127.0.0.1:8000' . $path,
        'GET',
        server: [
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => '8000',
            'HTTPS' => 'off',
        ],
    );
    $request->setLaravelSession($session);

    $response = $kernel->handle($request);
    $body = $response->getContent() ?: '';
    $kernel->terminate($request, $response);

    preg_match_all('/href="(http[^"]+)"/', $body, $hrefs);
    preg_match_all('/src="(http[^"]+)"/', $body, $srcs);

    echo '=== ' . $path . ' ===' . PHP_EOL;
    echo 'status=' . $response->getStatusCode() . PHP_EOL;

    foreach (array_slice($hrefs[1] ?? [], 0, 5) as $href) {
        echo 'href: ' . $href . PHP_EOL;
    }

    $badScripts = array_filter($srcs[1] ?? [], fn (string $s): bool => str_starts_with($s, 'http://127.0.0.1/'));
    echo 'scripts_sem_porta=' . count($badScripts) . PHP_EOL;
    echo 'link_produtos=' . (int) preg_match('/href="http:\/\/127\.0\.0\.1:8000\/admin\/products"/', $body) . PHP_EOL;
    echo 'link_pdv=' . (int) preg_match('/href="http:\/\/127\.0\.0\.1:8000\/admin\/pdv"/', $body) . PHP_EOL;
    echo 'wire_init=' . (int) str_contains($body, 'wire:init') . PHP_EOL;
    echo 'erp_shell_html=' . (int) str_contains($body, 'erp-shell') . PHP_EOL;
    echo PHP_EOL;
}
