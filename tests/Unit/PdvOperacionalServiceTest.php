<?php

namespace Tests\Unit;

use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Person;
use App\Models\User;
use App\Support\Erp\Pdv\PdvCaixaMovimentoService;
use App\Support\Erp\Pdv\PdvVendaFinanceiroService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PdvOperacionalServiceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array{empresa: Empresa, user: User, sessao: PdvCaixaSessao}
     */
    private function criarContextoCaixa(): array
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV TESTE',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
        ]);

        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'valor_abertura' => 100,
            'aberto_em' => now(),
        ]);

        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'abertura',
            'historico' => 'ABERTURA DE CAIXA',
            'entrada' => 100,
            'saida' => 0,
        ]);

        return compact('empresa', 'user', 'sessao');
    }

    public function test_saldo_dinheiro_considera_apenas_parcela_em_dinheiro_em_pagamento_misto(): void
    {
        ['sessao' => $sessao] = $this->criarContextoCaixa();

        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $sessao->user_id,
            'numero' => 1,
            'subtotal' => 200,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 200,
            'forma_pagamento' => 'MISTO',
            'troco' => 0,
            'dinheiro' => 80,
            'situacao' => 'F',
        ]);

        $service = new PdvCaixaMovimentoService();
        $service->registrarEntradasVenda($sessao->id, $venda, [
            ['forma' => 'DINHEIRO', 'valor' => 80],
            ['forma' => 'CARTAO', 'valor' => 120],
        ]);

        $sessao->refresh();

        $this->assertSame(180.0, $sessao->saldoDinheiro());
    }

    public function test_saldo_dinheiro_desconta_troco_apenas_da_parcela_em_dinheiro(): void
    {
        ['sessao' => $sessao] = $this->criarContextoCaixa();

        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $sessao->user_id,
            'numero' => 2,
            'subtotal' => 200,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 200,
            'forma_pagamento' => 'MISTO',
            'troco' => 30,
            'dinheiro' => 180,
            'situacao' => 'F',
        ]);

        $service = new PdvCaixaMovimentoService();
        $service->registrarEntradasVenda($sessao->id, $venda, [
            ['forma' => 'DINHEIRO', 'valor' => 180],
            ['forma' => 'CARTAO', 'valor' => 50],
        ], 30);

        $sessao->refresh();

        $this->assertSame(250.0, $sessao->saldoDinheiro());
    }

    public function test_estorno_reverte_contas_receber_nao_baixadas(): void
    {
        ['sessao' => $sessao] = $this->criarContextoCaixa();

        $cliente = Person::query()->create([
            'codigo' => Person::nextCodigo(),
            'pessoa_tipo' => 'F',
            'nome_razao' => 'CLIENTE PDV',
        ]);

        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $sessao->user_id,
            'person_id' => $cliente->id,
            'numero' => 3,
            'subtotal' => 150,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 150,
            'forma_pagamento' => 'CREDIARIO',
            'troco' => 0,
            'dinheiro' => 0,
            'situacao' => 'F',
        ]);

        $financeiro = new PdvVendaFinanceiroService();
        $financeiro->gerarContasReceber($venda, $cliente->id, [
            ['forma' => 'CREDIARIO', 'valor' => '150,00'],
        ]);

        $this->assertSame(1, ContaReceber::query()->where('documento', 'PDV-000003')->count());

        $erro = $financeiro->estornarContasReceber($venda);

        $this->assertNull($erro);
        $this->assertSame(0, ContaReceber::query()->where('documento', 'PDV-000003')->count());
    }

    public function test_estorno_bloqueia_quando_conta_receber_ja_foi_baixada(): void
    {
        ['sessao' => $sessao] = $this->criarContextoCaixa();

        $cliente = Person::query()->create([
            'codigo' => Person::nextCodigo(),
            'pessoa_tipo' => 'F',
            'nome_razao' => 'CLIENTE BAIXADO',
        ]);

        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $sessao->user_id,
            'person_id' => $cliente->id,
            'numero' => 4,
            'subtotal' => 90,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 90,
            'forma_pagamento' => 'CHEQUE',
            'troco' => 0,
            'dinheiro' => 0,
            'situacao' => 'F',
        ]);

        $financeiro = new PdvVendaFinanceiroService();
        $financeiro->gerarContasReceber($venda, $cliente->id, [
            ['forma' => 'CHEQUE', 'valor' => '90,00'],
        ]);

        ContaReceber::query()
            ->where('documento', 'PDV-000004')
            ->update(['valor_recebido' => 90]);

        $erro = $financeiro->estornarContasReceber($venda);

        $this->assertNotNull($erro);
        $this->assertSame(1, ContaReceber::query()->where('documento', 'PDV-000004')->count());
    }

    public function test_estorno_caixa_reverte_movimentos_por_forma(): void
    {
        ['sessao' => $sessao] = $this->criarContextoCaixa();

        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $sessao->user_id,
            'numero' => 5,
            'subtotal' => 200,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 200,
            'forma_pagamento' => 'MISTO',
            'troco' => 0,
            'dinheiro' => 80,
            'situacao' => 'F',
        ]);

        PdvVendaPagamento::query()->create([
            'pdv_venda_id' => $venda->id,
            'forma' => 'DINHEIRO',
            'valor' => 80,
        ]);

        PdvVendaPagamento::query()->create([
            'pdv_venda_id' => $venda->id,
            'forma' => 'CARTAO',
            'valor' => 120,
        ]);

        $service = new PdvCaixaMovimentoService();
        $service->registrarEntradasVenda($sessao->id, $venda, [
            ['forma' => 'DINHEIRO', 'valor' => 80],
            ['forma' => 'CARTAO', 'valor' => 120],
        ]);

        $service->registrarSaidasEstornoFromModel(
            $sessao->id,
            $venda,
            $venda->pagamentos()->get(),
        );

        $sessao->refresh();

        $this->assertSame(100.0, $sessao->saldoDinheiro());
        $this->assertSame(100.0, $sessao->saldoTotal());
    }
}
