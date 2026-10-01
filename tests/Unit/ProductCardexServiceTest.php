<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService;
use App\Support\Erp\ProductCardexService;
use Illuminate\Support\Str;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ProductCardexServiceTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_vendas_lista_apenas_espelho_da_retaguarda_sem_duplicar_pdv(): void
    {
        $user = User::factory()->create();
        $cliente = Person::query()->create([
            'codigo' => '9100',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE CARDEX TESTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);
        $product = Product::query()->create([
            'codigo' => 'PCDX1',
            'descricao' => 'PRODUTO CARDEX TESTE',
            'preco_venda' => 2,
            'ativo' => true,
        ]);

        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        $pdvVenda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'person_id' => $cliente->id,
            'numero' => 1,
            'subtotal' => 2,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 2,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
        ]);

        PdvVendaItem::query()->create([
            'pdv_venda_id' => $pdvVenda->id,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => 1,
            'preco_unitario' => 2,
            'total' => 2,
        ]);

        (new PdvVendaRetaguardaMirrorService())->espelhar($pdvVenda->fresh('itens'));

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertCount(1, $cardex['vendas']);
        $this->assertSame('CLIENTE CARDEX TESTE', $cardex['vendas'][0]['cliente']);
        $this->assertSame('R$ 2,00', $cardex['vendas'][0]['total']);
        $this->assertSame('R$ 2,00', $cardex['totais']['vendas']);
    }

    public function test_vendas_ignora_venda_cancelada(): void
    {
        $product = Product::query()->create([
            'codigo' => 'PCDX2',
            'descricao' => 'PRODUTO CARDEX CANCELADO',
            'preco_venda' => 5,
            'ativo' => true,
        ]);
        $cliente = $this->criarCliente('CLIENTE CANCELADO');

        $venda = Venda::query()->create([
            'numero' => '000999',
            'data' => now()->toDateString(),
            'hora' => '10:00:00',
            'cliente_id' => $cliente->id,
            'vendedor_id' => null,
            'total' => 5,
            'status' => Venda::STATUS_CANCELADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        VendaItem::query()->create([
            'venda_id' => $venda->id,
            'product_id' => $product->id,
            'quantidade' => 1,
            'valor_item' => 5,
            'total' => 5,
        ]);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame([], $cardex['vendas']);
        $this->assertSame('R$ 0,00', $cardex['totais']['vendas']);
    }

    public function test_desconto_do_item_do_pedido_aparece_na_coluna(): void
    {
        $product = $this->criarProduto('PCDX-DESC');
        $cliente = $this->criarCliente('CLIENTE DESCONTO');
        $venda = $this->criarVenda($cliente, '000066', 745.08);
        $this->criarItemVenda($venda, $product, 5, 152.01, 745.08);
        $this->vincularPedido($venda, $cliente, $product, 5, 152.01, 14.97, 745.08, descontoCabecalho: 0);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame('5,000', $cardex['vendas'][0]['quantidade']);
        $this->assertSame('R$ 152,01', $cardex['vendas'][0]['valor']);
        $this->assertSame('- R$ 14,97', $cardex['vendas'][0]['desconto_acrescimo']);
        $this->assertSame('R$ 745,08', $cardex['vendas'][0]['total']);
    }

    public function test_venda_sem_ajuste_e_desconto_so_de_cabecalho_mostram_traco(): void
    {
        $product = $this->criarProduto('PCDX-ZERO');
        $cliente = $this->criarCliente('CLIENTE SEM AJUSTE');
        $semOrigem = $this->criarVenda($cliente, '000100', 10);
        $this->criarItemVenda($semOrigem, $product, 1, 10, 10);

        $soCabecalho = $this->criarVenda($cliente, '000101', 10);
        $this->criarItemVenda($soCabecalho, $product, 1, 10, 10);
        $this->vincularPedido($soCabecalho, $cliente, $product, 1, 10, 0, 10, descontoCabecalho: 10);

        $porNumero = collect((new ProductCardexService())->forProduct($product)['vendas'])->keyBy('venda');

        $this->assertSame('—', $porNumero['100']['desconto_acrescimo']);
        $this->assertSame('—', $porNumero['101']['desconto_acrescimo']);
    }

    public function test_pdv_desconto_unitario_vira_efeito_da_linha(): void
    {
        $product = $this->criarProduto('PCDX-PDV-D');
        $cliente = $this->criarCliente('CLIENTE PDV DESC');
        $venda = $this->criarVenda($cliente, '000200', 24);
        $this->criarItemVenda($venda, $product, 3, 10, 24);
        $this->vincularPdv($venda, $product, 3, 10, 24, descontoUnitario: 2, acrescimoUnitario: 0);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame('- R$ 6,00', $cardex['vendas'][0]['desconto_acrescimo']);
    }

    public function test_pdv_acrescimo_unitario_vira_efeito_da_linha(): void
    {
        $product = $this->criarProduto('PCDX-PDV-A');
        $cliente = $this->criarCliente('CLIENTE PDV ACR');
        $venda = $this->criarVenda($cliente, '000201', 25);
        $this->criarItemVenda($venda, $product, 2, 10, 25);
        $this->vincularPdv($venda, $product, 2, 10, 25, descontoUnitario: 0, acrescimoUnitario: 2.5);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame('+ R$ 5,00', $cardex['vendas'][0]['desconto_acrescimo']);
    }

    public function test_pdv_desconto_e_acrescimo_simultaneos_aparecem_na_mesma_celula(): void
    {
        $product = $this->criarProduto('PCDX-PDV-AMBOS');
        $cliente = $this->criarCliente('CLIENTE PDV AMBOS');
        $venda = $this->criarVenda($cliente, '000202', 38);
        $this->criarItemVenda($venda, $product, 4, 10, 38);
        $this->vincularPdv($venda, $product, 4, 10, 38, descontoUnitario: 1.25, acrescimoUnitario: 0.5);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame('- R$ 5,00  + R$ 2,00', $cardex['vendas'][0]['desconto_acrescimo']);
    }

    public function test_duas_linhas_iguais_nao_duplicam_nem_cruzam_desconto(): void
    {
        $product = $this->criarProduto('PCDX-DUP');
        $cliente = $this->criarCliente('CLIENTE DUAS LINHAS');
        $venda = $this->criarVenda($cliente, '000300', 32.00);
        $this->criarItemVenda($venda, $product, 1, 10, 8.50);
        $this->criarItemVenda($venda, $product, 1, 10, 8.50);
        $this->criarItemVenda($venda, $product, 1, 10, 9.00);
        $this->criarItemVenda($venda, $product, 1, 10, 6.00);

        $pedido = Pedido::query()->create([
            'numero' => 'P'.random_int(100000, 999999),
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'subtotal' => 32,
            'desconto_valor' => 0,
            'total' => 32,
            'status' => Pedido::STATUS_FECHADO,
        ]);
        $this->criarItemPedido($pedido, $product, 1, 1, 10, 1.50, 8.50);
        $this->criarItemPedido($pedido, $product, 2, 1, 10, 1.50, 8.50);
        $this->criarItemPedido($pedido, $product, 3, 1, 10, 1.00, 9.00);
        $this->criarItemPedido($pedido, $product, 4, 1, 10, 4.00, 6.00);
        $this->criarOrdem($venda, $cliente, $pedido->id, 32);

        $porTotal = collect((new ProductCardexService())->forProduct($product)['vendas'])->groupBy('total');

        $this->assertCount(2, $porTotal['R$ 8,50']);
        $this->assertSame(
            ['- R$ 1,50', '- R$ 1,50'],
            $porTotal['R$ 8,50']->pluck('desconto_acrescimo')->all(),
        );
        $this->assertSame(['- R$ 1,00'], $porTotal['R$ 9,00']->pluck('desconto_acrescimo')->all());
        $this->assertSame(['- R$ 4,00'], $porTotal['R$ 6,00']->pluck('desconto_acrescimo')->all());
    }

    public function test_nfce_autorizada_aparece_rejeitada_nao_e_total_nao_duplica(): void
    {
        $product = $this->criarProduto('PCDX-NFCE');
        $cliente = $this->criarCliente('CLIENTE NFCE');
        $autorizada = $this->criarVenda($cliente, '000070', 152.01);
        $this->criarItemVenda($autorizada, $product, 1, 152.01, 152.01);
        $this->vincularPdv($autorizada, $product, 1, 152.01, 152.01, descontoUnitario: 0, acrescimoUnitario: 0, personId: $cliente->id);
        $pdvAutorizada = PdvVenda::query()->where('venda_id', $autorizada->id)->firstOrFail();
        $this->criarNfce($pdvAutorizada, 30, PdvVendaNfce::STATUS_AUTORIZADA);

        $rejeitada = $this->criarVenda($cliente, '000068', 152.01);
        $this->criarItemVenda($rejeitada, $product, 1, 152.01, 152.01);
        $this->vincularPdv($rejeitada, $product, 1, 152.01, 152.01, descontoUnitario: 0, acrescimoUnitario: 0, personId: $cliente->id);
        $this->criarNfce(
            PdvVenda::query()->where('venda_id', $rejeitada->id)->firstOrFail(),
            10,
            PdvVendaNfce::STATUS_REJEITADA,
        );

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertCount(1, $cardex['nfce']);
        $this->assertSame('30', (string) $cardex['nfce'][0]['numero']);
        $this->assertSame('70', $cardex['nfce'][0]['venda']);
        $this->assertSame('1,000', $cardex['nfce'][0]['quantidade']);
        $this->assertSame('R$ 152,01', $cardex['nfce'][0]['valor']);
        $this->assertSame('R$ 152,01', $cardex['nfce'][0]['total']);
        $this->assertSame('CLIENTE NFCE', $cardex['nfce'][0]['cliente']);
        $this->assertSame('R$ 304,02', $cardex['totais']['vendas']);
        $this->assertSame('R$ 152,01', $cardex['totais']['nfce']);
        $this->assertSame('(já incluída em Vendas)', $cardex['totais']['nfce_inclusa']);
        $this->assertSame('R$ 304,02', $cardex['totais']['total_vendas']);
    }

    public function test_nfce_modelo_65_nao_duplica_quando_pdv_aponta_para_a_mesma_nota(): void
    {
        $product = $this->criarProduto('PCDX-65');
        $cliente = $this->criarCliente('CLIENTE 65');
        $venda = $this->criarVenda($cliente, '000400', 10);
        $this->criarItemVenda($venda, $product, 1, 10, 10);
        $nfe = Nfe::query()->create([
            'numero' => '77',
            'modelo' => '65',
            'data_emissao' => now()->toDateString(),
            'hora_emissao' => '10:15:00',
            'cliente_id' => $cliente->id,
            'venda_id' => $venda->id,
            'total' => 10,
            'status' => Nfe::STATUS_TRANSMITIDA,
        ]);
        NfeItem::query()->create([
            'nfe_id' => $nfe->id,
            'item' => 1,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'total' => 10,
            'unidade' => 'UN',
        ]);
        $this->vincularPdv($venda, $product, 1, 10, 10, descontoUnitario: 0, acrescimoUnitario: 0, personId: $cliente->id);
        $this->criarNfce(
            PdvVenda::query()->where('venda_id', $venda->id)->firstOrFail(),
            77,
            PdvVendaNfce::STATUS_AUTORIZADA,
            $nfe->id,
        );

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertCount(1, $cardex['nfce']);
        $this->assertSame('77', (string) $cardex['nfce'][0]['numero']);
        $this->assertSame('400', $cardex['nfce'][0]['venda']);
        $this->assertSame('R$ 10,00', $cardex['totais']['nfce']);
        $this->assertSame('R$ 10,00', $cardex['totais']['vendas']);
        $this->assertSame('R$ 10,00', $cardex['totais']['total_vendas']);
    }

    public function test_nfe_direta_sem_venda_compõe_total_geral(): void
    {
        $product = $this->criarProduto('PCDX-NFE');
        $cliente = $this->criarCliente('PIZZA CAMPOS LTDA');
        $nfe = Nfe::query()->create([
            'numero' => '69',
            'modelo' => '55',
            'data_emissao' => '2026-09-29',
            'cliente_id' => $cliente->id,
            'venda_id' => null,
            'total' => 304.02,
            'status' => Nfe::STATUS_TRANSMITIDA,
        ]);
        NfeItem::query()->create([
            'nfe_id' => $nfe->id,
            'item' => 1,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => 2,
            'valor_unitario' => 152.01,
            'total' => 304.02,
            'unidade' => 'UN',
        ]);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame('—', $cardex['nfe'][0]['venda']);
        $this->assertSame('2,000', $cardex['nfe'][0]['quantidade']);
        $this->assertSame('R$ 304,02', $cardex['nfe'][0]['total']);
        $this->assertSame('—', $cardex['nfe'][0]['desconto_acrescimo']);
        $this->assertSame('R$ 0,00', $cardex['totais']['vendas']);
        $this->assertSame('R$ 304,02', $cardex['totais']['nfe']);
        $this->assertSame('R$ 0,00', $cardex['totais']['nfce']);
        $this->assertNull($cardex['totais']['nfce_inclusa']);
        $this->assertSame('R$ 304,02', $cardex['totais']['total_vendas']);
    }

    public function test_nfe_e_nfce_usam_o_desconto_gravado_no_formato_da_venda(): void
    {
        $product = $this->criarProduto('PCDX-AJ');
        $cliente = $this->criarCliente('CLIENTE AJUSTE');
        $nfe = Nfe::query()->create([
            'numero' => '80',
            'modelo' => '55',
            'data_emissao' => '2026-09-29',
            'cliente_id' => $cliente->id,
            'total' => 8,
            'status' => Nfe::STATUS_TRANSMITIDA,
        ]);
        NfeItem::query()->create([
            'nfe_id' => $nfe->id,
            'item' => 1,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'desconto' => 3,
            'outros' => 1,
            'total' => 8,
            'unidade' => 'UN',
        ]);

        $venda = $this->criarVenda($cliente, '000880', 24);
        $this->criarItemVenda($venda, $product, 3, 10, 24);
        $pdv = $this->vincularPdv($venda, $product, 3, 10, 24, descontoUnitario: 2, acrescimoUnitario: 0);
        $this->criarNfce($pdv, 81, PdvVendaNfce::STATUS_AUTORIZADA);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame('- R$ 3,00  + R$ 1,00', $cardex['nfe'][0]['desconto_acrescimo']);
        $this->assertSame('- R$ 6,00', $cardex['nfce'][0]['desconto_acrescimo']);
    }

    public function test_nfce_sem_venda_compõe_total_geral(): void
    {
        $product = $this->criarProduto('PCDX-NFCE-SOLTA');
        $cliente = $this->criarCliente('CLIENTE NFCE SOLTA');
        $user = User::factory()->create();
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);
        $pdv = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'person_id' => $cliente->id,
            'venda_id' => null,
            'numero' => random_int(1000, 9999),
            'subtotal' => 20,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 20,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
        ]);
        PdvVendaItem::query()->create([
            'pdv_venda_id' => $pdv->id,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => 1,
            'preco_unitario' => 20,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 20,
        ]);
        $this->criarNfce($pdv, 40, PdvVendaNfce::STATUS_AUTORIZADA);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame('—', $cardex['nfce'][0]['venda']);
        $this->assertSame('R$ 0,00', $cardex['totais']['vendas']);
        $this->assertSame('R$ 20,00', $cardex['totais']['nfce']);
        $this->assertNull($cardex['totais']['nfce_inclusa']);
        $this->assertSame('R$ 20,00', $cardex['totais']['total_vendas']);
    }

    private function criarProduto(string $codigo): Product
    {
        return Product::query()->create([
            'codigo' => $codigo.random_int(10, 99),
            'descricao' => 'PRODUTO '.$codigo,
            'preco_venda' => 10,
            'ativo' => true,
        ]);
    }

    private function criarCliente(string $nome): Person
    {
        return Person::query()->create([
            'codigo' => (string) random_int(100000, 999999),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => $nome,
            'is_cliente' => true,
            'ativo' => true,
        ]);
    }

    private function criarVenda(?Person $cliente, string $numero, float $total): Venda
    {
        return Venda::query()->create([
            'numero' => $numero,
            'data' => now()->toDateString(),
            'hora' => '10:00:00',
            'cliente_id' => $cliente?->id,
            'total' => $total,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);
    }

    private function criarItemVenda(Venda $venda, Product $product, float $quantidade, float $valor, float $total): VendaItem
    {
        return VendaItem::query()->create([
            'venda_id' => $venda->id,
            'product_id' => $product->id,
            'quantidade' => $quantidade,
            'valor_item' => $valor,
            'total' => $total,
        ]);
    }

    private function vincularPedido(
        Venda $venda,
        Person $cliente,
        Product $product,
        float $quantidade,
        float $preco,
        float $desconto,
        float $total,
        float $descontoCabecalho,
    ): void {
        $pedido = Pedido::query()->create([
            'numero' => 'P'.random_int(100000, 999999),
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'subtotal' => $total + $descontoCabecalho,
            'desconto_valor' => $descontoCabecalho,
            'total' => $total,
            'status' => Pedido::STATUS_FECHADO,
        ]);
        $this->criarItemPedido($pedido, $product, 1, $quantidade, $preco, $desconto, $total);
        $this->criarOrdem($venda, $cliente, $pedido->id, $total);
    }

    private function criarItemPedido(
        Pedido $pedido,
        Product $product,
        int $item,
        float $quantidade,
        float $preco,
        float $desconto,
        float $total,
    ): void {
        PedidoItem::query()->create([
            'pedido_id' => $pedido->id,
            'item' => $item,
            'product_id' => $product->id,
            'quantidade' => $quantidade,
            'preco_unitario' => $preco,
            'desconto' => $desconto,
            'total' => $total,
            'descricao' => $product->descricao,
        ]);
    }

    private function criarOrdem(Venda $venda, Person $cliente, int $pedidoId, float $total): void
    {
        ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory()->create()->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $cliente->id,
            'pedido_id' => $pedidoId,
            'venda_id' => $venda->id,
            'total' => $total,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'payload' => [],
        ]);
    }

    private function vincularPdv(
        Venda $venda,
        Product $product,
        float $quantidade,
        float $preco,
        float $total,
        float $descontoUnitario,
        float $acrescimoUnitario,
        ?int $personId = null,
    ): PdvVenda {
        $user = User::factory()->create();
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);
        $pdv = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'person_id' => $personId,
            'venda_id' => $venda->id,
            'numero' => random_int(1000, 9999),
            'subtotal' => $total,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => $total,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
        ]);
        PdvVendaItem::query()->create([
            'pdv_venda_id' => $pdv->id,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => $quantidade,
            'preco_unitario' => $preco,
            'desconto' => $descontoUnitario,
            'acrescimo' => $acrescimoUnitario,
            'total' => $total,
        ]);

        return $pdv;
    }

    private function criarNfce(PdvVenda $pdv, int $numero, string $status, ?int $nfeId = null): void
    {
        PdvVendaNfce::query()->create([
            'pdv_venda_id' => $pdv->id,
            'nfe_id' => $nfeId,
            'operacao' => 'nfce_transmitir',
            'modelo' => '65',
            'numero' => $numero,
            'status' => $status,
            'simulada' => false,
            'autorizada_em' => $status === PdvVendaNfce::STATUS_AUTORIZADA ? now() : null,
        ]);
    }
}
