<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ContadorCloudSyncLog;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\PdvVendaNfce;
use App\Support\ContadorCloud\ContadorCloudConfig;

$empresaId = (int) ($argv[1] ?? 1);
$empresa = Empresa::query()->find($empresaId);

echo "=== Portal params (empresa {$empresaId}) ===" . PHP_EOL;

if (! $empresa) {
    echo "Empresa nao encontrada" . PHP_EOL;
    exit(1);
}

$config = ContadorCloudConfig::fromEmpresa($empresa);
echo 'habilitar=' . var_export($empresa->param_portal_contador_habilitar, true) . PHP_EOL;
echo 'url=' . ($empresa->param_portal_contador_url ?: '(vazio)') . PHP_EOL;
echo 'empresa_id_nuvem=' . ($empresa->param_portal_contador_empresa_id ?: '(vazio)') . PHP_EOL;
echo 'token=' . (filled($empresa->param_portal_contador_token) ? 'preenchido' : '(vazio)') . PHP_EOL;
echo 'enviar_vendas=' . var_export($empresa->param_portal_contador_enviar_vendas, true) . PHP_EOL;
echo 'enviar_compras=' . var_export($empresa->param_portal_contador_enviar_compras, true) . PHP_EOL;
echo 'enviar_xml=' . var_export($empresa->param_portal_contador_enviar_xml, true) . PHP_EOL;
echo 'isActive=' . var_export($config->isActive(), true) . PHP_EOL;
echo 'syncUrl=' . $config->syncUrl() . PHP_EOL;

echo PHP_EOL . '=== Sync logs (ultimos 15) ===' . PHP_EOL;
$logs = ContadorCloudSyncLog::query()->orderByDesc('id')->limit(15)->get();

if ($logs->isEmpty()) {
    echo 'Nenhum log de sincronizacao.' . PHP_EOL;
} else {
    foreach ($logs as $log) {
        echo sprintf(
            "#%d %s %s chave=%s status=%s http=%s err=%s\n",
            $log->id,
            $log->tipo_documento,
            $log->evento,
            $log->chave ?: '-',
            $log->status,
            $log->http_status ?? '-',
            substr((string) $log->error_message, 0, 150),
        );
        if (filled($log->response_body)) {
            echo '  response: ' . substr((string) $log->response_body, 0, 300) . PHP_EOL;
        }
    }
}

echo PHP_EOL . '=== Ultimas NF-e ===' . PHP_EOL;
foreach (Nfe::query()->orderByDesc('id')->limit(5)->get() as $nfe) {
    echo sprintf(
        "#%d num=%s status=%s chave=%s xml=%s created=%s\n",
        $nfe->id,
        $nfe->numero,
        $nfe->status,
        $nfe->chave ?: '-',
        filled($nfe->xml) ? 'sim' : 'nao',
        $nfe->created_at,
    );
}

echo PHP_EOL . '=== Ultimas NFC-e ===' . PHP_EOL;
foreach (PdvVendaNfce::query()->orderByDesc('id')->limit(5)->get() as $nfce) {
    echo sprintf(
        "#%d num=%s status=%s simulada=%s chave=%s xml=%s created=%s\n",
        $nfce->id,
        $nfce->numero,
        $nfce->status,
        $nfce->simulada ? 'sim' : 'nao',
        $nfce->chave ?: '-',
        filled($nfce->xml) ? 'sim' : 'nao',
        $nfce->created_at,
    );
}
