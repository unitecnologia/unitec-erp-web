<?php

/**
 * Worker CLI: tenta faturar uma OS (corrida de concorrência nos testes DEV).
 * Saída JSON em uma linha: {"ok":bool,"error":?string,"pid":int}
 *
 * Uso:
 *   php tests/Support/os-faturar-race-worker.php --os=1 --forma=2 --valor=100.00 --empresa=3 --user=4 --ready=path
 */

declare(strict_types=1);

use App\Models\OrdemServico;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Os\OsFaturamentoService;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$opts = getopt('', ['os:', 'forma:', 'valor:', 'empresa:', 'user:', 'ready:']);
$osId = (int) ($opts['os'] ?? 0);
$formaId = (int) ($opts['forma'] ?? 0);
$valor = (string) ($opts['valor'] ?? '0');
$empresaId = (int) ($opts['empresa'] ?? 0);
$userId = (int) ($opts['user'] ?? 0);
$readyFile = (string) ($opts['ready'] ?? '');

$emit = static function (bool $ok, ?string $error = null): void {
    echo json_encode([
        'ok' => $ok,
        'error' => $error,
        'pid' => getmypid(),
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;
};

if ($osId <= 0 || $formaId <= 0 || $empresaId <= 0 || $userId <= 0) {
    $emit(false, 'parametros invalidos');
    exit(2);
}

// Barreira: espera o pai sinalizar que os dois workers já subiram.
if ($readyFile !== '') {
    $deadline = microtime(true) + 15.0;
    while (! is_file($readyFile) && microtime(true) < $deadline) {
        usleep(20_000);
    }
    if (! is_file($readyFile)) {
        $emit(false, 'timeout na barreira ready');
        exit(3);
    }
}

try {
    $user = User::query()->find($userId);
    if (! $user instanceof User) {
        $emit(false, 'usuario nao encontrado');
        exit(4);
    }

    Auth::login($user);
    session(['erp_empresa_id' => $empresaId]);
    ErpContext::clearMemo();

    $os = OrdemServico::query()->with(['itens.product', 'cliente'])->find($osId);
    if (! $os instanceof OrdemServico) {
        $emit(false, 'os nao encontrada');
        exit(5);
    }

    app(OsFaturamentoService::class)->faturar($os, [
        ['id' => $formaId, 'valor' => $valor],
    ]);

    $emit(true);
    exit(0);
} catch (Throwable $e) {
    $emit(false, $e->getMessage());
    exit(1);
}
