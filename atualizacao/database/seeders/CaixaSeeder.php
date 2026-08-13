<?php

namespace Database\Seeders;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use Illuminate\Database\Seeder;

class CaixaSeeder extends Seeder
{
    public function run(): void
    {
        $conta = CaixaConta::query()->updateOrCreate(
            ['nome' => CaixaConta::NOME_CAIXA_GERAL],
            [
                'codigo' => 1,
                'tipo' => CaixaConta::TIPO_SUBCAIXA,
                'situacao' => CaixaConta::SITUACAO_ABERTO,
                'ativo' => true,
                'sistema' => true,
            ],
        );

        $samples = [
            [
                'codigo' => 1,
                'emissao' => now()->subDay(),
                'documento' => 'CX.1',
                'historico' => 'SALDO INICIAL DO CAIXA',
                'plano_contas' => 'AJUSTE DE SALDO',
                'entrada' => 0,
                'saida' => 1.00,
            ],
            [
                'codigo' => 2,
                'emissao' => now(),
                'documento' => 'CX.2',
                'historico' => 'FECHAMENTO DO CX:CAIXA-USUARIO-' . now()->format('d/m/Y H:i:s'),
                'plano_contas' => 'VENDAS DE MERCADORIA',
                'entrada' => 534.24,
                'saida' => 0,
            ],
        ];

        foreach ($samples as $sample) {
            CaixaLancamento::query()->updateOrCreate(
                ['codigo' => $sample['codigo']],
                [
                    ...$sample,
                    'caixa_conta_id' => $conta->id,
                ],
            );
        }
    }
}
