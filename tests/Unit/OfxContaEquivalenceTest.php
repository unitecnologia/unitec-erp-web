<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Support\Erp\Financeiro\Conciliacao\OfxContaEquivalence;
use App\Support\Erp\Financeiro\Conciliacao\OfxParseResult;
use App\Support\Erp\Financeiro\Conciliacao\OfxParsedTransaction;
use Tests\TestCase;

class OfxContaEquivalenceTest extends TestCase
{
    public function test_ailos_acctid_composto_equivale_cadastro_com_dv_zero(): void
    {
        $conta = $this->fakeConta([
            'banco_codigo' => '085',
            'agencia' => '0109',
            'agencia_dv' => '0',
            'conta_numero' => '48346',
            'conta_dv' => '0',
        ]);

        $parsed = $this->parsed(
            bankId: '085',
            branchId: null,
            accountId: '010900483460',
        );

        $result = (new OfxContaEquivalence)->avaliar($conta, $parsed);

        $this->assertSame('ok', $result['status']);
    }

    public function test_ailos_equivale_mesmo_sem_dv_no_cadastro_com_aviso(): void
    {
        $conta = $this->fakeConta([
            'banco_codigo' => '085',
            'agencia' => '0109',
            'agencia_dv' => '',
            'conta_numero' => '48346',
            'conta_dv' => '',
        ]);

        $parsed = $this->parsed('085', null, '010900483460');
        $result = (new OfxContaEquivalence)->avaliar($conta, $parsed);

        $this->assertSame('ok', $result['status']);
        $this->assertStringContainsString('DV', $result['mensagem']);
    }

    public function test_ailos_bloqueia_conta_diferente(): void
    {
        $conta = $this->fakeConta([
            'banco_codigo' => '085',
            'agencia' => '0109',
            'agencia_dv' => '0',
            'conta_numero' => '48346',
            'conta_dv' => '0',
        ]);

        $parsed = $this->parsed('085', null, '010900499990');
        $result = (new OfxContaEquivalence)->avaliar($conta, $parsed);

        $this->assertSame('incompativel', $result['status']);
    }

    public function test_outros_bancos_comparam_acctid_direto(): void
    {
        $conta = $this->fakeConta([
            'banco_codigo' => '001',
            'agencia' => '1234',
            'conta_numero' => '98765',
            'conta_dv' => '1',
        ]);

        $ok = (new OfxContaEquivalence)->avaliar($conta, $this->parsed('001', '1234', '98765'));
        $this->assertSame('ok', $ok['status']);

        $bad = (new OfxContaEquivalence)->avaliar($conta, $this->parsed('001', '1234', '11111'));
        $this->assertSame('incompativel', $bad['status']);
    }

    public function test_ofx_sem_conta_fica_incompleto(): void
    {
        $conta = $this->fakeConta([
            'banco_codigo' => '085',
            'agencia' => '0109',
            'conta_numero' => '48346',
        ]);

        $result = (new OfxContaEquivalence)->avaliar($conta, $this->parsed('085', null, null));
        $this->assertSame('dados_incompletos', $result['status']);
    }

    /**
     * @param  array<string, string>  $attrs
     */
    private function fakeConta(array $attrs): CaixaConta
    {
        $conta = new CaixaConta;
        foreach ($attrs as $k => $v) {
            $conta->{$k} = $v;
        }

        return $conta;
    }

    private function parsed(?string $bankId, ?string $branchId, ?string $accountId): OfxParseResult
    {
        return new OfxParseResult(
            encoding: 'UTF-8',
            dialect: 'sgml',
            bankId: $bankId,
            branchId: $branchId,
            accountId: $accountId,
            periodoInicio: null,
            periodoFim: null,
            transactions: [
                new OfxParsedTransaction(
                    dataMovimento: '2026-11-10',
                    dataPosted: '2026-11-10',
                    tipo: 'credito',
                    valor: '1.00',
                    historico: 'X',
                    memo: null,
                    payee: null,
                    checkNum: null,
                    fitid: 'T1',
                    refNum: null,
                    sequenciaArquivo: 1,
                    ambiguo: false,
                ),
            ],
        );
    }
}
