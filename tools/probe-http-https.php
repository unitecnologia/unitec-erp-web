<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;

$empresa = Empresa::query()->find(1);
$token = $empresa->param_portal_contador_token;

foreach (['http://unitecnologiasc.com.br/api/portal/documentos', 'https://unitecnologiasc.com.br/api/portal/documentos'] as $url) {
    echo PHP_EOL . 'POST ' . $url . PHP_EOL;
    try {
        $r = Http::timeout(15)->withToken($token)->acceptJson()->asJson()->post($url, [
            'cnpj' => '22.469.772/0001-00',
            'tipo' => 'NF_EMITIDA',
            'numero' => '0',
            'dataEmissao' => date('Y-m-d'),
            'competencia' => date('Y-m'),
        ]);
        echo 'status=' . $r->status() . PHP_EOL;
        echo 'body=' . substr($r->body(), 0, 200) . PHP_EOL;
    } catch (Throwable $e) {
        echo 'error=' . $e->getMessage() . PHP_EOL;
    }
}
