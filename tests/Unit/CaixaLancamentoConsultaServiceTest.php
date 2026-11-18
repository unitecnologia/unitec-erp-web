<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\CaixaLivroMovimento;
use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Financeiro\CaixaLancamentoConsultaService;
use App\Support\Erp\Financeiro\CaixaLivroAberturaDisplay;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Consulta padronizada do olho no Caixa (qualquer conta).
 * Schema mínimo: migrate :memory: ainda quebra em migration legada.
 */
class CaixaLancamentoConsultaServiceTest extends TestCase
{
    private Empresa $empresa;

    private CaixaConta $contaGeral;

    private CaixaConta $contaCaixa;

    private CaixaConta $contaPdv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
        ErpContext::clearMemo();

        $this->empresa = Empresa::query()->create([
            'razao_social' => 'Empresa Teste Caixa',
            'cnpj' => '11222333000181',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $this->empresa->id]);
        ErpContext::clearMemo();

        $this->contaGeral = CaixaConta::query()->create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'CAIXA GERAL',
            'tipo' => 'GERAL',
            'ativo' => true,
        ]);
        $this->contaCaixa = CaixaConta::query()->create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'CAIXA',
            'tipo' => 'CAIXA',
            'ativo' => true,
        ]);
        $this->contaPdv = CaixaConta::query()->create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'CAIXA PDV 1',
            'tipo' => CaixaConta::TIPO_PDV,
            'ativo' => true,
        ]);
    }

    public function test_lancamento_manual_na_conta_caixa_usa_mesma_estrutura(): void
    {
        $lanc = $this->criarLancamento($this->contaCaixa, [
            'historico' => '[MANUAL] Sangria avulsa',
            'documento' => 'MAN-1',
            'entrada' => 0,
            'saida' => 50.00,
            'plano_contas' => 'SANGRIA',
        ]);

        $data = app(CaixaLancamentoConsultaService::class)->montar($lanc);

        $this->assertFalse($data['pode_ver_itens']);
        $this->assertNull($data['pdv_venda_id']);
        $this->assertSame('50,00', $data['valor_lancamento']);
        $this->assertNull($data['total_venda']);
        $this->assertTrue($data['tem_detalhe']);
        $this->assertContainsLabel($data['detalhes'], 'Origem', 'Lançamento manual');
        $this->assertContainsLabel($data['detalhes'], 'Valor do lançamento', '50,00');
        $this->assertSame('CAIXA', $data['conta']);
    }

    public function test_abertura_livro_na_conta_geral_abre_modal_padrao(): void
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Operador Abertura',
            'email' => 'abertura@test.local',
            'password' => bcrypt('x'),
            'empresa_id' => $this->empresa->id,
        ])->save();

        $mov = CaixaLivroMovimento::query()->create([
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $this->contaGeral->id,
            'aberto_por' => $user->id,
            'aberto_em' => '2026-11-12 08:15:00',
            'troco_inicial' => 100.50,
            'status' => 'aberto',
        ]);

        $virtualId = CaixaLivroAberturaDisplay::ID_BASE_ABERTURA - (int) $mov->id;
        $data = app(CaixaLancamentoConsultaService::class)
            ->montarAberturaLivro($virtualId, (int) $this->contaGeral->id);

        $this->assertNotNull($data);
        $this->assertSame('ABERTURA', $data['documento']);
        $this->assertSame('CAIXA GERAL', $data['conta']);
        $this->assertSame('100,50', $data['valor_lancamento']);
        $this->assertFalse($data['pode_ver_itens']);
        $this->assertContainsLabel($data['detalhes'], 'Origem', 'Abertura de caixa');
    }

    public function test_venda_pdv_cartao_mostra_pedido_cliente_pagamento_fiscal_e_itens(): void
    {
        $payload = $this->criarMovimentoPdvVenda([
            'forma' => 'CARTÃO CRÉDITO',
            'valor_lancamento' => 30.00,
            'total_venda' => 100.00,
            'nfce_status' => 'autorizada',
            'nfce_numero' => 88,
        ]);

        $data = app(CaixaLancamentoConsultaService::class)->montar($payload['lancamento']);

        $this->assertTrue($data['pode_ver_itens']);
        $this->assertSame($payload['pdv_venda_id'], $data['pdv_venda_id']);
        $this->assertNull($data['venda_id']);
        $this->assertSame('30,00', $data['valor_lancamento']);
        $this->assertSame('100,00', $data['total_venda']);
        $this->assertContainsLabel($data['detalhes'], 'Origem', 'Venda PDV');
        $this->assertContainsLabel($data['detalhes'], 'Pedido', '12');
        $this->assertContainsLabel($data['detalhes'], 'Cliente', 'CLIENTE PDV');
        $this->assertContainsLabel($data['detalhes'], 'Vendedor', 'VENDEDOR TESTE');
        $this->assertTrue($this->detalheContem($data['detalhes'], 'Meio de pagamento', 'CARTÃO'));
        $this->assertTrue($this->detalheContem($data['detalhes'], 'Documento fiscal', 'NFC-E'));
        $this->assertContainsLabel($data['detalhes'], 'Valor do lançamento', '30,00');
        $this->assertContainsLabel($data['detalhes'], 'Total da venda', '100,00');
        $this->assertSame('CAIXA PDV 1', $data['conta']);
    }

    public function test_venda_pdv_pix_e_supertef_respeitam_fk_e_nao_coincidencia_de_pedido(): void
    {
        $pix = $this->criarMovimentoPdvVenda([
            'forma' => 'PIX',
            'valor_lancamento' => 45.50,
            'total_venda' => 45.50,
            'numero' => '000077',
        ]);

        // Venda comercial com o mesmo número — não pode virar vínculo.
        Venda::query()->create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000077',
            'total' => 999.00,
        ]);

        $dataPix = app(CaixaLancamentoConsultaService::class)->montar($pix['lancamento']);
        $this->assertSame($pix['pdv_venda_id'], $dataPix['pdv_venda_id']);
        $this->assertNull($dataPix['venda_id']);
        $this->assertTrue($this->detalheContem($dataPix['detalhes'], 'Meio de pagamento', 'PIX'));

        $tef = $this->criarMovimentoPdvVenda([
            'forma' => 'SUPERTEF DÉBITO',
            'valor_lancamento' => 20.00,
            'total_venda' => 20.00,
            'numero' => '000088',
            'com_item' => false,
        ]);
        $dataTef = app(CaixaLancamentoConsultaService::class)->montar($tef['lancamento']);
        $this->assertTrue($this->detalheContem($dataTef['detalhes'], 'Meio de pagamento', 'SUPERTEF'));
        $this->assertSame('20,00', $dataTef['valor_lancamento']);
    }

    public function test_abertura_sangria_e_movimento_sem_venda_na_conta_pdv(): void
    {
        $sessao = $this->criarSessaoPdv();

        $abertura = PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'abertura',
            'historico' => 'ABERTURA DE CAIXA',
            'entrada' => 80.00,
            'saida' => 0,
        ]);
        $lancAbertura = $this->lancamentoVirtualPdv($abertura, $sessao, $this->contaPdv);
        $dataAbertura = app(CaixaLancamentoConsultaService::class)->montar($lancAbertura);
        $this->assertFalse($dataAbertura['pode_ver_itens']);
        $this->assertNull($dataAbertura['pdv_venda_id']);
        $this->assertContainsLabel($dataAbertura['detalhes'], 'Origem', 'Abertura de caixa (PDV)');
        $this->assertSame('80,00', $dataAbertura['valor_lancamento']);

        $sangria = PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'sangria',
            'historico' => 'SANGRIA',
            'sangria_destino' => 'CAIXA GERAL',
            'entrada' => 0,
            'saida' => 25.00,
        ]);
        $dataSangria = app(CaixaLancamentoConsultaService::class)->montar(
            $this->lancamentoVirtualPdv($sangria, $sessao, $this->contaPdv)
        );
        $this->assertContainsLabel($dataSangria['detalhes'], 'Origem', 'Sangria de caixa (PDV)');
        $this->assertContainsLabel($dataSangria['detalhes'], 'Destino sangria', 'CAIXA GERAL');

        $manualGeral = $this->criarLancamento($this->contaGeral, [
            'historico' => '[MANUAL] Ajuste',
            'entrada' => 10,
            'saida' => 0,
        ]);
        $dataManual = app(CaixaLancamentoConsultaService::class)->montar($manualGeral);
        $this->assertSame('CAIXA GERAL', $dataManual['conta']);
        $this->assertFalse($dataManual['pode_ver_itens']);
        $this->assertContainsLabel($dataManual['detalhes'], 'Origem', 'Lançamento manual');
    }

    public function test_isola_empresa_em_abertura_e_lancamento(): void
    {
        $outra = Empresa::query()->create([
            'razao_social' => 'Outra Empresa',
            'cnpj' => '99888777000166',
            'ativo' => true,
        ]);

        $lanc = $this->criarLancamento($this->contaCaixa, [
            'empresa_id' => $outra->id,
            'historico' => '[MANUAL] Outra empresa',
            'entrada' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        app(CaixaLancamentoConsultaService::class)->montar($lanc);
    }

    public function test_nao_vincula_itens_por_texto_venda_pdv_sem_fk(): void
    {
        Venda::query()->create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000055',
            'total' => 55,
        ]);

        $lanc = $this->criarLancamento($this->contaGeral, [
            'historico' => 'VENDA PDV #000055 (PIX)',
            'documento' => '000055',
            'entrada' => 55,
            'saida' => 0,
        ]);

        $data = app(CaixaLancamentoConsultaService::class)->montar($lanc);
        $this->assertFalse($data['pode_ver_itens']);
        $this->assertNull($data['venda_id']);
        $this->assertNull($data['pdv_venda_id']);
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{lancamento: CaixaLancamento, pdv_venda_id: int}
     */
    private function criarMovimentoPdvVenda(array $opts): array
    {
        $sessao = $this->criarSessaoPdv();
        $cliente = Person::query()->create([
            'nome_razao' => 'Cliente PDV',
            'apelido_fantasia' => 'Cliente PDV',
            'pessoa_tipo' => 'F',
        ]);

        $venda = new PdvVenda;
        $venda->forceFill([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $sessao->user_id,
            'numero' => $opts['numero'] ?? '000012',
            'total' => $opts['total_venda'] ?? 100,
            'subtotal' => $opts['total_venda'] ?? 100,
            'person_id' => $cliente->id,
            'vendedor_nome' => 'Vendedor Teste',
            'forma_pagamento' => $opts['forma'] ?? 'PIX',
            'situacao' => 'fechada',
        ])->save();

        if ($opts['com_item'] ?? true) {
            PdvVendaItem::query()->create([
                'pdv_venda_id' => $venda->id,
                'codigo' => '1',
                'descricao' => 'Produto Teste',
                'quantidade' => 1,
                'preco_unitario' => $opts['total_venda'] ?? 100,
                'desconto' => 0,
                'total' => $opts['total_venda'] ?? 100,
            ]);
        }

        PdvVendaPagamento::query()->create([
            'pdv_venda_id' => $venda->id,
            'forma' => $opts['forma'] ?? 'PIX',
            'valor' => $opts['valor_lancamento'] ?? 100,
        ]);

        if (! empty($opts['nfce_status'])) {
            PdvVendaNfce::query()->create([
                'pdv_venda_id' => $venda->id,
                'empresa_id' => $this->empresa->id,
                'numero' => $opts['nfce_numero'] ?? 1,
                'serie' => 1,
                'status' => $opts['nfce_status'],
                'chave' => '35261100000000000000550010000000881234567890',
            ]);
        }

        $mov = PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'venda',
            'historico' => 'VENDA PDV #'.($opts['numero'] ?? '000012').' ('.($opts['forma'] ?? 'PIX').')',
            'forma_pagamento' => $opts['forma'] ?? 'PIX',
            'entrada' => $opts['valor_lancamento'] ?? 100,
            'saida' => 0,
            'pdv_venda_id' => $venda->id,
        ]);

        return [
            'lancamento' => $this->lancamentoVirtualPdv($mov, $sessao, $this->contaPdv),
            'pdv_venda_id' => (int) $venda->id,
        ];
    }

    private function criarSessaoPdv(): PdvCaixaSessao
    {
        $user = User::query()->first();
        if (! $user) {
            $user = new User;
            $user->forceFill([
                'name' => 'Caixa PDV',
                'email' => 'pdv@test.local',
                'password' => bcrypt('x'),
                'empresa_id' => $this->empresa->id,
            ])->save();
        }

        return PdvCaixaSessao::query()->create([
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $this->contaPdv->id,
            'user_id' => $user->id,
            'aberto_em' => now(),
            'valor_abertura' => 0,
        ]);
    }

    private function lancamentoVirtualPdv(
        PdvCaixaMovimento $mov,
        PdvCaixaSessao $sessao,
        CaixaConta $conta,
    ): CaixaLancamento {
        $lanc = new CaixaLancamento;
        $lanc->forceFill([
            'id' => -1 * (int) $mov->id,
            'codigo' => null,
            'emissao' => now()->toDateString(),
            'documento' => null,
            'historico' => $mov->historico,
            'plano_contas' => 'VENDAS',
            'caixa_conta_id' => $conta->id,
            'entrada' => $mov->entrada,
            'saida' => $mov->saida,
            'empresa_id' => $this->empresa->id,
        ]);
        $lanc->exists = true;
        $lanc->setRelation('conta', $conta);
        $lanc->setAttribute('pdv_sessao_id', $sessao->id);
        $lanc->setAttribute('pdv_operador', 'Caixa PDV');
        $lanc->setAttribute('pdv_terminal', 'TERMINAL 1');

        return $lanc;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function criarLancamento(CaixaConta $conta, array $attrs): CaixaLancamento
    {
        $lanc = CaixaLancamento::query()->create(array_merge([
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $conta->id,
            'codigo' => random_int(1000, 9999),
            'emissao' => now()->toDateString(),
            'documento' => null,
            'historico' => 'Teste',
            'plano_contas' => 'GERAL',
            'entrada' => 0,
            'saida' => 0,
        ], $attrs));

        $lanc->setRelation('conta', $conta);

        return $lanc;
    }

    /**
     * @param  list<array{label: string, value: string}>  $detalhes
     */
    private function assertContainsLabel(array $detalhes, string $label, string $valueContains): void
    {
        $this->assertTrue(
            $this->detalheContem($detalhes, $label, $valueContains),
            "Esperado detalhe [{$label}] contendo [{$valueContains}]",
        );
    }

    /**
     * @param  list<array{label: string, value: string}>  $detalhes
     */
    private function detalheContem(array $detalhes, string $label, string $valueContains): bool
    {
        foreach ($detalhes as $linha) {
            if (($linha['label'] ?? '') !== $label) {
                continue;
            }

            return str_contains(mb_strtoupper((string) ($linha['value'] ?? ''), 'UTF-8'), mb_strtoupper($valueContains, 'UTF-8'));
        }

        return false;
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('pdv_venda_nfce');
        Schema::dropIfExists('pdv_venda_pagamentos');
        Schema::dropIfExists('pdv_venda_itens');
        Schema::dropIfExists('pdv_caixa_movimentos');
        Schema::dropIfExists('pdv_caixa_sessoes');
        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('caixa_livro_movimentos');
        Schema::dropIfExists('caixa_lancamentos');
        Schema::dropIfExists('caixa_contas');
        Schema::dropIfExists('vendas');
        Schema::dropIfExists('people');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('nome_razao')->nullable();
            $table->string('apelido_fantasia')->nullable();
            $table->string('pessoa_tipo', 1)->nullable();
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('nome');
            $table->string('tipo')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->date('emissao')->nullable();
            $table->string('documento')->nullable();
            $table->string('historico')->nullable();
            $table->string('plano_contas')->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->decimal('entrada', 15, 2)->default(0);
            $table->decimal('saida', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('caixa_livro_movimentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->unsignedBigInteger('aberto_por')->nullable();
            $table->dateTime('aberto_em')->nullable();
            $table->decimal('troco_inicial', 15, 2)->default(0);
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->dateTime('aberto_em')->nullable();
            $table->dateTime('fechado_em')->nullable();
            $table->decimal('valor_abertura', 15, 2)->default(0);
            $table->decimal('valor_fechamento', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('numero')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->string('vendedor_nome')->nullable();
            $table->string('forma_pagamento')->nullable();
            $table->string('situacao')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pdv_venda_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->string('codigo')->nullable();
            $table->string('descricao')->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('preco_unitario', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pdv_venda_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->string('forma')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->string('cartao_nsu')->nullable();
            $table->string('cartao_autorizacao')->nullable();
            $table->string('cartao_maquininha')->nullable();
            $table->string('cartao_bandeira')->nullable();
            $table->string('cartao_parcela')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_venda_nfce', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->unsignedInteger('serie')->nullable();
            $table->string('status')->nullable();
            $table->string('chave')->nullable();
            $table->string('protocolo')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_caixa_movimentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->string('tipo')->nullable();
            $table->string('historico')->nullable();
            $table->string('forma_pagamento')->nullable();
            $table->string('sangria_destino')->nullable();
            $table->decimal('entrada', 15, 2)->default(0);
            $table->decimal('saida', 15, 2)->default(0);
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->timestamps();
        });

        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }
}
