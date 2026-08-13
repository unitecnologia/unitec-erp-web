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

if (! $user) {
    $user = User::query()->first();
}

if (! $user) {
    fwrite(STDERR, "No user found\n");
    exit(1);
}

Auth::guard('web')->login($user);
$kernel = $app->make(Kernel::class);
$session = app('session.store');
$session->start();

$request = Request::create(
    'http://127.0.0.1:8000/admin/vendas',
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

echo 'status=' . $response->getStatusCode() . ' bytes=' . strlen($body) . PHP_EOL;
echo 'erp-vendas-page=' . (int) str_contains($body, 'erp-vendas-page') . PHP_EOL;
echo 'erp-vendas.css=' . (int) str_contains($body, 'erp-vendas.css') . PHP_EOL;

if (preg_match('/erp-vendas\.css\?v=([^"\']+)/', $body, $m)) {
    echo 'css_version=' . $m[1] . PHP_EOL;
}

if (preg_match('/<thead[^>]*>(.*?)<\/thead>/s', $body, $thead)) {
    preg_match_all('/class="([^"]*fi-ta-header-cell[^"]*)"/', $thead[1], $headerClasses);
    echo 'header_cells=' . count($headerClasses[1]) . PHP_EOL;

    foreach ($headerClasses[1] as $index => $class) {
        echo '  th_' . ($index + 1) . '=' . $class . PHP_EOL;
    }
}

if (preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', $body, $tbody)) {
    if (preg_match('/<tr[^>]*class="[^"]*fi-ta-row[^"]*"[^>]*>(.*?)<\/tr>/s', $tbody[1], $row)) {
        preg_match_all('/class="([^"]*fi-ta-cell[^"]*)"/', $row[1], $cellClasses);
        echo 'first_row_cells=' . count($cellClasses[1]) . PHP_EOL;

        foreach ($cellClasses[1] as $index => $class) {
            echo '  td_' . ($index + 1) . '=' . $class . PHP_EOL;
        }
    }
}

preg_match_all('/fi-ta-cell-ver-itens|fi-ta-cell-cliente[^"\']+|fi-ta-header-cell-ver-itens|fi-ta-header-cell-cliente[^"\']+/', $body, $named);
echo 'named_classes=' . implode(', ', array_unique($named[0] ?? [])) . PHP_EOL;

if (preg_match('/<table[^>]*class="[^"]*fi-ta-table[^"]*"[^>]*>/', $body, $tableTag)) {
    echo 'table_tag=' . $tableTag[0] . PHP_EOL;
}

if (preg_match('/<thead[^>]*>(.*?)<\/thead>/s', $body, $thead)) {
    preg_match_all('/<th\b[^>]*class="([^"]*fi-ta-header-cell fi-ta-header-cell-[^"]*)"/', $thead[1], $headerClasses);
    echo 'real_header_cells=' . count($headerClasses[1]) . PHP_EOL;

    foreach ($headerClasses[1] as $index => $class) {
        echo '  th_' . ($index + 1) . '=' . $class . PHP_EOL;
    }
}

if (preg_match('/erp-vendas\.css\?v=(\d+)/', $body, $cssVer)) {
    $path = public_path('css/erp-vendas.css');
    echo 'css_filemtime=' . filemtime($path) . ' match=' . ((int) ($cssVer[1] == filemtime($path))) . PHP_EOL;
}

$cssContents = file_get_contents(public_path('css/erp-vendas.css'));
echo 'css_has_cliente_selector=' . (int) str_contains($cssContents, 'cliente\\.nome-razao') . PHP_EOL;
echo 'css_has_width100_cliente=' . (int) (bool) preg_match('/fi-ta-cell-cliente\\\\\.nome-razao[\s\S]{0,120}width:\s*100%/', $cssContents) . PHP_EOL;
echo 'css_has_width_auto_cliente=' . (int) (bool) preg_match('/fi-ta-cell-cliente\\\\\.nome-razao[\s\S]{0,120}width:\s*auto/', $cssContents) . PHP_EOL;
echo 'fi-growable_cliente=' . (int) str_contains($body, 'fi-ta-header-cell-cliente.nome-razao fi-growable') . PHP_EOL;
if (preg_match('/<th[^>]*fi-ta-header-cell-ver-itens[^>]*>/', $body, $verItensTh)) {
    echo 'ver_itens_th=' . $verItensTh[0] . PHP_EOL;
}
