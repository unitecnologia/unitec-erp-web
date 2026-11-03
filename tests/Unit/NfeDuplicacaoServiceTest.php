<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\Nfe;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\NfeReferencia;
use App\Models\Person;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendasParametro;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfe\NfeDuplicacaoService;
use DomainException;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfeDuplicacaoServiceTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_duplica_nfe_transmitida_com_itens_e_parcelas_sem_alterar_origem(): void
    {
        [$empresa, $cliente, $produto, $params] = $this->seedBase(numeroNfe: 100);

        $origem = $this->criarNfeCompleta(
            empresa: $empresa,
            cliente: $cliente,
            produto: $produto,
            status: Nfe::STATUS_TRANSMITIDA,
            numero: '50',
            comReferencia: true,
            comVenda: true,
        );

        $origemId = (int) $origem->id;
        $origemUpdatedAt = optional($origem->updated_at)?->toDateTimeString();
        $origemChave = (string) $origem->chave;
        $origemProtocolo = (string) $origem->protocolo;
        $origemXml = (string) ($origem->xml ?? '');
        $origemTotal = (string) $origem->total;
        $origemVendaId = (int) ($origem->venda_id ?: 0);
        $origemItensCount = $origem->itens->count();
        $origemFaturasCount = $origem->faturas->count();
        $origemRefsCount = $origem->referencias->count();
        $movBefore = Schema::hasTable('estoque_movimentacoes')
            ? EstoqueMovimentacao::query()->count()
            : 0;
        $contaReceberBefore = Schema::hasTable('contas_receber')
            ? \App\Models\ContaReceber::query()->count()
            : 0;

        $nova = app(NfeDuplicacaoService::class)->duplicar($origem, (int) $empresa->id);

        $this->assertNotSame((int) $origem->id, (int) $nova->id);
        $this->assertSame(Nfe::STATUS_ABERTA, $nova->status);
        $this->assertSame(Nfe::SITUACAO_ABERTA, $nova->situacao);
        $this->assertSame('100', (string) $nova->numero);
        $this->assertSame('1', (string) $nova->serie);
        $this->assertSame('55', (string) $nova->modelo);
        $this->assertSame(ErpTimezone::today(), $nova->data_emissao?->format('Y-m-d'));
        $this->assertSame(ErpTimezone::today(), $nova->data_saida?->format('Y-m-d'));
        $this->assertNull($nova->chave);
        $this->assertNull($nova->protocolo);
        $this->assertNull($nova->xml);
        $this->assertNull($nova->cnf);
        $this->assertNull($nova->venda_id);
        $this->assertNull($nova->chave_nfe_referenciada);
        $this->assertFalse((bool) $nova->estoque_baixado);

        $this->assertSame((int) $origem->cliente_id, (int) $nova->cliente_id);
        $this->assertSame((string) $origem->forma_pgto, (string) $nova->forma_pgto);
        $this->assertSame((string) $origem->meio_pgto, (string) $nova->meio_pgto);
        $this->assertSame((string) $origem->total, (string) $nova->total);
        $this->assertSame((string) $origem->subtotal, (string) $nova->subtotal);
        $this->assertSame((string) $origem->desconto, (string) $nova->desconto);
        $this->assertSame((string) $origem->frete, (string) $nova->frete);
        $this->assertSame((string) $origem->obs_contribuinte, (string) $nova->obs_contribuinte);

        $this->assertCount(2, $nova->itens);
        $origemItens = $origem->itens()->orderBy('item')->get();
        $novosItens = $nova->itens()->orderBy('item')->get();
        foreach ($origemItens as $i => $itemOrigem) {
            $itemNovo = $novosItens[$i];
            $this->assertNotSame((int) $itemOrigem->id, (int) $itemNovo->id);
            $this->assertSame((int) $itemOrigem->product_id, (int) $itemNovo->product_id);
            $this->assertSame((string) $itemOrigem->descricao, (string) $itemNovo->descricao);
            $this->assertSame((string) $itemOrigem->quantidade, (string) $itemNovo->quantidade);
            $this->assertSame((string) $itemOrigem->valor_unitario, (string) $itemNovo->valor_unitario);
            $this->assertSame((string) $itemOrigem->desconto, (string) $itemNovo->desconto);
            $this->assertSame((string) $itemOrigem->total, (string) $itemNovo->total);
            $this->assertSame((string) $itemOrigem->cfop, (string) $itemNovo->cfop);
            $this->assertSame((string) $itemOrigem->cst, (string) $itemNovo->cst);
            $this->assertSame((string) $itemOrigem->csosn, (string) $itemNovo->csosn);
            $this->assertSame((string) $itemOrigem->valor_icms, (string) $itemNovo->valor_icms);
            $this->assertSame((string) $itemOrigem->info_adicionais, (string) $itemNovo->info_adicionais);
        }

        $this->assertCount(2, $nova->faturas);
        $this->assertSame(
            $origem->faturas->pluck('valor')->map(fn ($v) => (string) $v)->all(),
            $nova->faturas->pluck('valor')->map(fn ($v) => (string) $v)->all(),
        );
        $this->assertSame(0, $nova->referencias()->count());

        $origemFresh = $origem->fresh(['itens', 'faturas', 'referencias']);
        $this->assertNotNull($origemFresh);
        $this->assertSame($origemId, (int) $origemFresh->id);
        $this->assertSame($origemUpdatedAt, optional($origemFresh->updated_at)?->toDateTimeString());
        $this->assertSame(Nfe::STATUS_TRANSMITIDA, $origemFresh->status);
        $this->assertSame($origemChave, (string) $origemFresh->chave);
        $this->assertSame($origemProtocolo, (string) $origemFresh->protocolo);
        $this->assertSame($origemXml, (string) ($origemFresh->xml ?? ''));
        $this->assertSame($origemTotal, (string) $origemFresh->total);
        $this->assertSame($origemVendaId, (int) ($origemFresh->venda_id ?: 0));
        $this->assertSame($origemItensCount, $origemFresh->itens->count());
        $this->assertSame($origemFaturasCount, $origemFresh->faturas->count());
        $this->assertSame($origemRefsCount, $origemFresh->referencias->count());

        $this->assertSame(101, (int) $params->fresh()->numero_nfe);

        if (Schema::hasTable('estoque_movimentacoes')) {
            $this->assertSame($movBefore, EstoqueMovimentacao::query()->count());
        }
        if (Schema::hasTable('contas_receber')) {
            $this->assertSame($contaReceberBefore, \App\Models\ContaReceber::query()->count());
        }
    }

    public function test_permite_aberta_e_cancelada_e_bloqueia_inutilizada(): void
    {
        [$empresa, $cliente, $produto] = $this->seedBase(numeroNfe: 10);

        $aberta = $this->criarNfeCompleta($empresa, $cliente, $produto, Nfe::STATUS_ABERTA, '1');
        $cancelada = $this->criarNfeCompleta($empresa, $cliente, $produto, Nfe::STATUS_CANCELADA, '2');
        $inutilizada = $this->criarNfeCompleta($empresa, $cliente, $produto, Nfe::STATUS_INUTILIZADA, '3');

        $service = app(NfeDuplicacaoService::class);

        $novaAberta = $service->duplicar($aberta, (int) $empresa->id);
        $this->assertSame(Nfe::STATUS_ABERTA, $novaAberta->status);

        $novaCancelada = $service->duplicar($cancelada, (int) $empresa->id);
        $this->assertSame(Nfe::STATUS_ABERTA, $novaCancelada->status);

        $this->expectException(DomainException::class);
        $service->duplicar($inutilizada, (int) $empresa->id);
    }

    public function test_bloqueia_duplicacao_entre_empresas(): void
    {
        [$empresa, $cliente, $produto] = $this->seedBase(numeroNfe: 5);
        $outra = Empresa::query()->create([
            'nome' => 'OUTRA EMPRESA',
            'razao_social' => 'OUTRA EMPRESA LTDA',
            'ativo' => true,
            'uf' => 'SC',
        ]);

        $origem = $this->criarNfeCompleta($empresa, $cliente, $produto, Nfe::STATUS_TRANSMITIDA, '8');

        $this->expectException(DomainException::class);
        app(NfeDuplicacaoService::class)->duplicar($origem, (int) $outra->id);
    }

    public function test_nao_atualiza_preco_pelo_cadastro_do_produto(): void
    {
        [$empresa, $cliente, $produto] = $this->seedBase(numeroNfe: 20);

        $origem = $this->criarNfeCompleta($empresa, $cliente, $produto, Nfe::STATUS_TRANSMITIDA, '9');
        $produto->update(['preco_venda' => 999.99]);

        $nova = app(NfeDuplicacaoService::class)->duplicar($origem, (int) $empresa->id);
        $item = $nova->itens->first();

        $this->assertSame('15.5000', (string) $item->valor_unitario);
        $this->assertNotEquals('999.99', (string) $item->valor_unitario);
    }

    /**
     * @return array{0: Empresa, 1: Person, 2: Product, 3: VendasParametro}
     */
    private function seedBase(int $numeroNfe): array
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA DUP',
            'razao_social' => 'EMPRESA DUP LTDA',
            'ativo' => true,
            'uf' => 'SC',
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-DUP',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Duplicacao',
            'cpf_cnpj' => '52998224725',
            'is_cliente' => true,
            'ativo' => true,
            'uf' => 'SC',
        ]);

        $produto = Product::query()->create([
            'codigo' => 'P-DUP',
            'descricao' => 'Produto Duplicacao',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $params = VendasParametro::forEmpresa((int) $empresa->id);
        $params->forceFill([
            'serie_nfe' => 1,
            'numero_nfe' => $numeroNfe,
            'ambiente' => VendasParametro::AMBIENTE_HOMOLOGACAO,
        ])->save();

        return [$empresa, $cliente, $produto, $params->fresh()];
    }

    private function criarNfeCompleta(
        Empresa $empresa,
        Person $cliente,
        Product $produto,
        string $status,
        string $numero,
        bool $comReferencia = false,
        bool $comVenda = false,
    ): Nfe {
        $vendaId = null;
        if ($comVenda) {
            $vendaId = Venda::query()->create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente->id,
                'numero' => (string) random_int(70000, 79999),
                'data' => now()->toDateString(),
                'total' => 40,
                'status' => Venda::STATUS_ABERTO,
                'tipo' => Venda::TIPO_PEDIDO,
            ])->id;
        }

        $nfe = Nfe::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => $numero,
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => '2025-01-10',
            'data_saida' => '2025-01-10',
            'cliente_id' => $cliente->id,
            'venda_id' => $vendaId,
            'chave' => $status === Nfe::STATUS_TRANSMITIDA
                ? 'CHAVEORIGEM123456789012345678901234567890123'
                : null,
            'protocolo' => $status === Nfe::STATUS_TRANSMITIDA ? 'PROT-ORIGEM' : null,
            'xml' => $status === Nfe::STATUS_TRANSMITIDA ? '<nfe/>' : null,
            'cnf' => $status === Nfe::STATUS_TRANSMITIDA ? '12345678' : null,
            'chave_nfe_referenciada' => $comReferencia
                ? '12345678901234567890123456789012345678901234'
                : null,
            'subtotal' => 40,
            'desconto' => 1.5,
            'frete' => 2.5,
            'total' => 41,
            'total_itens' => 2,
            'base_icms' => 40,
            'total_icms' => 4.8,
            'status' => $status,
            'situacao' => Nfe::statusToSituacao($status),
            'estoque_baixado' => $status === Nfe::STATUS_TRANSMITIDA,
            'cfop' => 5102,
            'natureza_operacao' => '5102 - VENDA',
            'finalidade' => '1',
            'movimento' => '1',
            'consumidor_final' => '1',
            'forma_pgto' => 'a_prazo',
            'meio_pgto' => 'boleto',
            'tipo_frete' => '1',
            'obs_fisco' => 'Obs fisco origem',
            'obs_contribuinte' => 'Obs contribuinte origem',
        ]);

        NfeItem::query()->create([
            'nfe_id' => $nfe->id,
            'item' => 1,
            'product_id' => $produto->id,
            'descricao' => $produto->descricao,
            'info_adicionais' => 'Info item 1',
            'quantidade' => 2,
            'valor_unitario' => 15.5,
            'desconto' => 1,
            'frete' => 1.25,
            'total' => 30,
            'cfop' => 5102,
            'unidade' => 'UN',
            'ncm' => '84713012',
            'origem' => 0,
            'cst' => '00',
            'csosn' => '102',
            'base_icms' => 30,
            'aliq_icms' => 12,
            'valor_icms' => 3.6,
            'situacao' => Nfe::statusToSituacao($status),
        ]);

        NfeItem::query()->create([
            'nfe_id' => $nfe->id,
            'item' => 2,
            'product_id' => $produto->id,
            'descricao' => $produto->descricao.' B',
            'info_adicionais' => 'Info item 2',
            'quantidade' => 1,
            'valor_unitario' => 10,
            'desconto' => 0.5,
            'frete' => 1.25,
            'total' => 10,
            'cfop' => 5102,
            'unidade' => 'UN',
            'ncm' => '84713012',
            'origem' => 0,
            'cst' => '00',
            'csosn' => '102',
            'base_icms' => 10,
            'aliq_icms' => 12,
            'valor_icms' => 1.2,
            'situacao' => Nfe::statusToSituacao($status),
        ]);

        NfeFatura::query()->create([
            'nfe_id' => $nfe->id,
            'empresa_id' => $empresa->id,
            'numero' => '001',
            'data_vencimento' => '2025-02-10',
            'valor' => 20.5,
        ]);
        NfeFatura::query()->create([
            'nfe_id' => $nfe->id,
            'empresa_id' => $empresa->id,
            'numero' => '002',
            'data_vencimento' => '2025-03-10',
            'valor' => 20.5,
        ]);

        if ($comReferencia) {
            NfeReferencia::query()->create([
                'nfe_id' => $nfe->id,
                'referencia' => '12345678901234567890123456789012345678901234',
            ]);
        }

        return $nfe->fresh(['itens', 'faturas', 'referencias']) ?? $nfe;
    }
}
