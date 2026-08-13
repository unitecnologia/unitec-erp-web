<?php

/**
 * Smoke test: atalhos e rotas do menu ERP (autenticado).
 * Uso: tools\php\php.exe scripts\erp-route-smoke.php
 */

use App\Models\User;
use App\Support\Erp\ErpMenu;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$kernel = $app->make(Kernel::class);

$bootstrapRequest = Request::create('http://localhost/admin', 'GET');
app()->instance('request', $bootstrapRequest);

$user = User::query()->where('name', 'USUARIO')->first()
    ?? User::query()->first();

if (! $user) {
    fwrite(STDERR, "ERRO: nenhum usuário encontrado no banco.\n");
    exit(1);
}

$paths = [];

foreach (ErpMenu::shortcuts() as $shortcut) {
    if (($shortcut['logout'] ?? false) || ($shortcut['disabled'] ?? false)) {
        continue;
    }

    if (filled($shortcut['url'] ?? null)) {
        $paths[] = [
            'group' => 'atalho',
            'label' => $shortcut['label'],
            'path' => parse_url($shortcut['url'], PHP_URL_PATH) . (parse_url($shortcut['url'], PHP_URL_QUERY) ? '?' . parse_url($shortcut['url'], PHP_URL_QUERY) : ''),
        ];
    }
}

foreach (ErpMenu::mainMenus() as $menu) {
    foreach ($menu['items'] as $item) {
        if (filled($item['url'] ?? null)) {
            $paths[] = [
                'group' => $menu['label'],
                'label' => $item['label'],
                'path' => parse_url($item['url'], PHP_URL_PATH) . (parse_url($item['url'], PHP_URL_QUERY) ? '?' . parse_url($item['url'], PHP_URL_QUERY) : ''),
            ];
        }
    }
}

$paths[] = ['group' => 'core', 'label' => 'Dashboard', 'path' => '/admin'];

$seen = [];
$unique = [];
foreach ($paths as $p) {
    if (isset($seen[$p['path']])) {
        continue;
    }
    $seen[$p['path']] = true;
    $unique[] = $p;
}

$ok = 0;
$fail = 0;
$rows = [];

foreach ($unique as $entry) {
    $path = $entry['path'];

    Auth::guard('web')->login($user);

    $session = app('session.store');
    $session->start();

    $request = Request::create($path, 'GET');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => Auth::guard('web')->user());

    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $body = $response->getContent() ?: '';
        $kernel->terminate($request, $response);

        $hasShell = str_contains($body, 'erp-shell');
        $has500 = $status >= 500;
        $hasError = str_contains($body, 'Whoops') || str_contains($body, 'Server Error');

        $pass = $status >= 200 && $status < 400 && ! $has500 && ! $hasError;

        if ($pass) {
            $ok++;
            $flag = 'OK';
        } else {
            $fail++;
            $flag = 'FAIL';
        }

        $rows[] = sprintf(
            "%-6s | %-12s | %-28s | %3d | shell:%s",
            $flag,
            $entry['group'],
            mb_substr($entry['label'], 0, 28),
            $status,
            $hasShell ? 'sim' : 'nao'
        );
    } catch (Throwable $e) {
        $fail++;
        $rows[] = sprintf(
            "%-6s | %-12s | %-28s | ERR | %s",
            'FAIL',
            $entry['group'],
            mb_substr($entry['label'], 0, 28),
            mb_substr($e->getMessage(), 0, 60)
        );
    }
}

echo "Usuario: {$user->name} (id {$user->id})\n";
echo str_repeat('-', 90) . "\n";
echo "STATUS | GRUPO        | ROTULO                       | HTTP| DETALHE\n";
echo str_repeat('-', 90) . "\n";
foreach ($rows as $row) {
    echo $row . "\n";
}
echo str_repeat('-', 90) . "\n";
echo "Total: " . count($unique) . " | OK: {$ok} | FAIL: {$fail}\n";

exit($fail > 0 ? 1 : 0);
