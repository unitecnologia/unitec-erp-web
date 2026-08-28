<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\Person;
use App\Models\Product;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdvPedidosImportaveisApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lista_apenas_pedidos_sem_documento_fiscal(): void
    {
        $suffix = (string) random_int(100000, 999999);
        $empresa = $this->criarEmpresaTerminal($suffix);
        $ok = $this->criarPedido(numero: '9'.$suffix.'0', total: 15.5, empresa: $empresa);
        $comNfe = $this->criarPedidoComNfe(numero: '9'.$suffix.'1', total: 20, empresa: $empresa);

        $response = $this->getJson('/api/v1/pdv/pedidos?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => 'PDV'.$suffix,
            'numero' => '9'.$suffix,
        ]));

        $response->assertOk();
        $pedidos = $response->json('pedidos');
        $this->assertIsArray($pedidos);
        $ids = array_map(fn ($row) => (int) ($row['venda_id'] ?? 0), $pedidos);
        $this->assertContains((int) $ok->id, $ids);
        $this->assertNotContains((int) $comNfe->id, $ids);
    }

    public function test_lista_inclui_erp_e_mesmo_pdv_exclui_outro_pdv(): void
    {
        $suffix = (string) random_int(100000, 999999);
        $empresa = $this->criarEmpresaTerminal($suffix);
        $terminalNome = 'PDV'.$suffix;

        $erp = $this->criarPedido(numero: '7'.$suffix.'0', total: 11, empresa: $empresa);
        $mesmo = $this->criarPedido(numero: '7'.$suffix.'1', total: 12, empresa: $empresa);
        $outro = $this->criarPedido(numero: '7'.$suffix.'2', total: 13, empresa: $empresa);

        $terminalId = (int) Terminal::query()
            ->where('empresa_id', $empresa->id)
            ->where('nome', $terminalNome)
            ->value('id');

        $this->vincularPdvVenda($mesmo, $terminalNome, $terminalId > 0 ? $terminalId : null);
        $this->vincularPdvVenda($outro, 'PDV'.((int) $suffix + 1));

        $response = $this->getJson('/api/v1/pdv/pedidos?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => $terminalNome,
            'numero' => '7'.$suffix,
        ]));

        $response->assertOk();
        $ids = array_map(fn ($row) => (int) ($row['venda_id'] ?? 0), $response->json('pedidos') ?? []);
        $this->assertContains((int) $erp->id, $ids);
        $this->assertContains((int) $mesmo->id, $ids);
        $this->assertNotContains((int) $outro->id, $ids);
    }

    public function test_detalhe_retorna_itens_e_rejeita_pedido_com_nfe(): void
    {
        $suffix = (string) random_int(100000, 999999);
        $empresa = $this->criarEmpresaTerminal($suffix);
        $pedido = $this->criarPedido(numero: '8'.$suffix.'0', total: 10, empresa: $empresa);
        $comNfe = $this->criarPedidoComNfe(numero: '8'.$suffix.'1', total: 12, empresa: $empresa);

        $ok = $this->getJson('/api/v1/pdv/pedidos/'.$pedido->id.'?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => 'PDV'.$suffix,
        ]));

        $ok->assertOk()
            ->assertJsonPath('venda_id', $pedido->id)
            ->assertJsonCount(1, 'itens');

        $negado = $this->getJson('/api/v1/pdv/pedidos/'.$comNfe->id.'?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => 'PDV'.$suffix,
        ]));

        $negado->assertStatus(422)
            ->assertJsonPath('message', 'Pedido já possui documento fiscal emitido.');
    }

    public function test_detalhe_rejeita_pedido_de_outro_pdv(): void
    {
        $suffix = (string) random_int(100000, 999999);
        $empresa = $this->criarEmpresaTerminal($suffix);
        $pedido = $this->criarPedido(numero: '6'.$suffix.'0', total: 9, empresa: $empresa);
        $this->vincularPdvVenda($pedido, 'PDV'.((int) $suffix + 7));

        $response = $this->getJson('/api/v1/pdv/pedidos/'.$pedido->id.'?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => 'PDV'.$suffix,
        ]));

        $response->assertNotFound()
            ->assertJsonPath('message', 'Pedido indisponível para importação.');
    }

    public function test_empresa_inexistente_retorna_422_claro(): void
    {
        $empresaId = 900000000 + random_int(1, 99999);

        $response = $this->getJson('/api/v1/pdv/pedidos?'.http_build_query([
            'empresa_id' => $empresaId,
            'terminal' => '1',
        ]));

        $response->assertStatus(422);
        $message = (string) $response->json('message');
        $this->assertStringContainsString((string) $empresaId, $message);
        $this->assertStringContainsString('PDV_EMPRESA_ID', $message);
        $this->assertStringNotContainsString('SQLSTATE', $message);
    }

    private function criarEmpresaTerminal(string $suffix): Empresa
    {
        $empresa = Empresa::query()->create([
            'codigo' => (int) $suffix,
            'nome' => 'EMPRESA PEDIDO '.$suffix,
            'ativo' => true,
        ]);

        Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV'.$suffix,
            'numero_logico_terminal' => (int) $suffix,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
        ]);

        return $empresa;
    }

    private function criarPedido(string $numero, float $total, Empresa $empresa): Venda
    {
        $cliente = Person::query()->create([
            'codigo' => 'C'.$numero,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE PEDIDO '.$numero,
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $product = Product::query()->create([
            'codigo' => 'P'.$numero,
            'descricao' => 'PRODUTO '.$numero,
            'unidade' => 'UN',
            'preco_venda' => $total,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $attrs = [
            'numero' => $numero,
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => $total,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_ERP,
        ];

        if (Schema::hasColumn('vendas', 'empresa_id')) {
            $attrs['empresa_id'] = $empresa->id;
        }

        $venda = Venda::query()->create($attrs);

        VendaItem::query()->create([
            'venda_id' => $venda->id,
            'product_id' => $product->id,
            'quantidade' => 1,
            'valor_item' => $total,
            'total' => $total,
        ]);

        return $venda->fresh();
    }

    private function criarPedidoComNfe(string $numero, float $total, Empresa $empresa): Venda
    {
        $venda = $this->criarPedido($numero, $total, $empresa);

        Nfe::query()->create([
            'venda_id' => $venda->id,
            'empresa_id' => $empresa->id,
            'numero' => $numero,
            'serie' => '1',
            'data_emissao' => now()->toDateString(),
            'status' => Nfe::STATUS_TRANSMITIDA,
            'total' => $total,
        ]);

        return $venda;
    }

    private function vincularPdvVenda(Venda $venda, string $terminalOffline, ?int $terminalId = null): void
    {
        $user = User::factory()->create();
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'terminal_id' => $terminalId,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'venda_id' => $venda->id,
            'uuid' => (string) Str::uuid(),
            'origem' => 'pdv_offline',
            'terminal_offline' => $terminalOffline,
            'numero' => (int) ltrim((string) $venda->numero, '0') ?: 1,
            'subtotal' => $venda->total,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => $venda->total,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
            'fiscal' => false,
        ]);
    }
}
