<?php

/**
 * Inclui o plano de contas padrão (códigos 1.01 a 2.15).
 * Não apaga nem altera contas que já existam com o mesmo código.
 *
 * No DEV:
 *   tools\php\php.exe scripts\incluir-planos-contas.php
 *
 * No cliente (copie este arquivo para C:\UNITECNOLOGIA_WEB\scripts\):
 *   C:\UNITECNOLOGIA_WEB\tools\php\php.exe scripts\incluir-planos-contas.php
 */

use App\Models\PlanoConta;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<array{0: int, 1: string, 2: string, 3: string}> */
$contas = [
    [101, '1.01', 'VENDAS DE MERCADORIAS', 'C'],
    [102, '1.02', 'PRESTAÇÃO DE SERVIÇOS', 'C'],
    [103, '1.03', 'RECEBIMENTO DE CLIENTES', 'C'],
    [104, '1.04', 'JUROS RECEBIDOS', 'C'],
    [105, '1.05', 'OUTRAS RECEITAS', 'C'],
    [201, '2.01', 'COMPRA DE MERCADORIAS', 'D'],
    [202, '2.02', 'FORNECEDORES', 'D'],
    [203, '2.03', 'ALUGUEL', 'D'],
    [204, '2.04', 'ENERGIA ELÉTRICA', 'D'],
    [205, '2.05', 'ÁGUA', 'D'],
    [206, '2.06', 'TELEFONE / INTERNET', 'D'],
    [207, '2.07', 'SALÁRIOS / PRÓ-LABORE', 'D'],
    [208, '2.08', 'IMPOSTOS / TAXAS', 'D'],
    [209, '2.09', 'TARIFAS BANCÁRIAS', 'D'],
    [210, '2.10', 'FRETES / TRANSPORTES', 'D'],
    [211, '2.11', 'MANUTENÇÃO / REPAROS', 'D'],
    [212, '2.12', 'MATERIAL DE USO / CONSUMO', 'D'],
    [213, '2.13', 'MARKETING / PUBLICIDADE', 'D'],
    [214, '2.14', 'DESPESAS ADMINISTRATIVAS', 'D'],
    [215, '2.15', 'OUTRAS DESPESAS', 'D'],
];

if (! Schema::hasTable('planos_contas')) {
    fwrite(STDERR, "Tabela planos_contas não existe neste banco.\n");
    exit(1);
}

$criadas = 0;
$existentes = 0;

foreach ($contas as [$codigo, $contaCompleta, $descricao, $dc]) {
    $jaExiste = PlanoConta::query()->where('codigo', $codigo)->exists();

    if ($jaExiste) {
        $existentes++;
        echo "já existe {$contaCompleta} {$descricao}\n";
        continue;
    }

    PlanoConta::query()->create([
        'codigo' => $codigo,
        'conta_completa' => $contaCompleta,
        'descricao' => $descricao,
        'dc' => $dc,
        'nivel' => 2,
        'ativo' => true,
    ]);

    $criadas++;
    echo "incluído {$contaCompleta} {$descricao}\n";
}

$vinculos = [
    'param_plano_conta_venda_id' => 101,
    'param_plano_conta_compra_id' => 201,
    'param_plano_conta_taxa_cartao_id' => 209,
];

if (Schema::hasTable('empresas')) {
    foreach ($vinculos as $coluna => $codigo) {
        if (! Schema::hasColumn('empresas', $coluna)) {
            continue;
        }

        $planoId = PlanoConta::query()->where('codigo', $codigo)->where('ativo', true)->value('id');

        if (! $planoId) {
            continue;
        }

        $atualizadas = DB::table('empresas')->whereNull($coluna)->update([$coluna => $planoId]);
        echo "empresas.{$coluna}: {$atualizadas} empresa(s) vinculada(s) ao código {$codigo}\n";
    }
}

echo "pronto: criadas={$criadas} já_existiam={$existentes}\n";
