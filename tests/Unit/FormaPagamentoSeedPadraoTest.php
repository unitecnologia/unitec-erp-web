<?php

namespace Tests\Unit;

use App\Models\FormaPagamento;
use App\Models\TabelaPrazo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class FormaPagamentoSeedPadraoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_instalacao_nova_nasce_com_cinco_formas_espelho_dev(): void
    {
        $formas = FormaPagamento::query()->orderBy('codigo')->get();

        $this->assertCount(5, $formas);
        $this->assertSame(
            ['DINHEIRO', 'PIX', 'POS DEBITO', 'POS CREDITO', 'BOLETO'],
            $formas->pluck('descricao')->all()
        );
        $this->assertFalse($formas->contains(fn (FormaPagamento $f): bool => in_array($f->descricao, ['DEPOSITO', 'TEF', 'TROCA'], true)));

        $esperado = [
            1 => [
                'descricao' => 'DINHEIRO',
                'tipo' => 'dinheiro',
                'tipo_movimento' => 'caixa',
                'atalho' => 'A',
                'aparece_contas_receber' => false,
                'disponivel_mobile' => true,
                'aparece_venda' => true,
                'conta_destino_id' => null,
                'gerar_qrcode_pdv' => false,
                'nfce' => false,
                'usa_tef' => false,
                'usa_super_tef' => false,
                'max_parcelas' => 1,
                'intervalo_parcelas' => 30,
            ],
            2 => [
                'descricao' => 'PIX',
                'tipo' => 'pix',
                'tipo_movimento' => 'caixa',
                'atalho' => 'P',
                'aparece_contas_receber' => true,
                'disponivel_mobile' => true,
                'aparece_venda' => true,
                'conta_destino_id' => null,
                'gerar_qrcode_pdv' => false,
            ],
            3 => [
                'descricao' => 'POS DEBITO',
                'tipo' => 'cartao_debito',
                'tipo_movimento' => 'caixa',
                'atalho' => null,
                'aparece_contas_receber' => false,
                'disponivel_mobile' => true,
            ],
            4 => [
                'descricao' => 'POS CREDITO',
                'tipo' => 'cartao_credito',
                'tipo_movimento' => 'caixa',
                'atalho' => null,
                'aparece_contas_receber' => true,
                'disponivel_mobile' => true,
            ],
            5 => [
                'descricao' => 'BOLETO',
                'tipo' => 'boleto',
                'tipo_movimento' => 'contas_receber',
                'atalho' => 'K',
                'aparece_contas_receber' => true,
                'disponivel_mobile' => true,
                'parcelas' => ['30,60,90'],
            ],
        ];

        foreach ($esperado as $codigo => $campos) {
            $forma = $formas->firstWhere('codigo', $codigo);
            $this->assertNotNull($forma, "Forma codigo {$codigo} ausente");

            foreach ($campos as $campo => $valor) {
                $this->assertSame(
                    $valor,
                    $forma->{$campo},
                    "codigo {$codigo} campo {$campo}"
                );
            }
        }

        $tabela = TabelaPrazo::query()
            ->where('forma_pagamento_id', $formas->firstWhere('codigo', 5)->id)
            ->orderBy('ordem')
            ->get(['dias', 'ordem']);

        $this->assertCount(1, $tabela);
        $this->assertSame('30,60,90', $tabela[0]->dias);
        $this->assertSame(1, (int) $tabela[0]->ordem);
    }

    public function test_migrate_em_base_ja_migrada_nao_altera_formas(): void
    {
        $antes = FormaPagamento::query()
            ->orderBy('id')
            ->get()
            ->map(fn (FormaPagamento $f): array => $f->getAttributes())
            ->all();

        $exit = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $exit);

        $depois = FormaPagamento::query()
            ->orderBy('id')
            ->get()
            ->map(fn (FormaPagamento $f): array => $f->getAttributes())
            ->all();

        $this->assertSame($antes, $depois);
        $this->assertSame(0, DB::table('migrations')->where('migration', 'like', '%formas_pagamento%')->where('batch', '>', 999999)->count());
    }
}
