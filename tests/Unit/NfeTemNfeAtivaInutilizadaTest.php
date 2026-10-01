<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use Illuminate\Support\Str;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfeTemNfeAtivaInutilizadaTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_sem_nfe_nao_bloqueia(): void
    {
        $venda = $this->seedVenda();
        $serv = new NfeVendaMercadoriaService();

        $this->assertFalse($serv->temNfeAtiva($venda));
        $this->assertNull($serv->motivoInaptoParaNfe($venda));
    }

    public function test_aberta_bloqueia(): void
    {
        $venda = $this->seedVenda();
        $this->criarNfe($venda, Nfe::STATUS_ABERTA);

        $serv = new NfeVendaMercadoriaService();
        $this->assertTrue($serv->temNfeAtiva($venda));
        $this->assertStringContainsString('já possui NF-e', (string) $serv->motivoInaptoParaNfe($venda));
    }

    public function test_transmitida_bloqueia(): void
    {
        $venda = $this->seedVenda();
        $this->criarNfe($venda, Nfe::STATUS_TRANSMITIDA);

        $this->assertTrue((new NfeVendaMercadoriaService())->temNfeAtiva($venda));
    }

    public function test_contingencia_bloqueia(): void
    {
        $venda = $this->seedVenda();
        $this->criarNfe($venda, Nfe::STATUS_CONTINGENCIA);

        $this->assertTrue((new NfeVendaMercadoriaService())->temNfeAtiva($venda));
    }

    public function test_cancelada_nao_bloqueia(): void
    {
        $venda = $this->seedVenda();
        $this->criarNfe($venda, Nfe::STATUS_CANCELADA);

        $serv = new NfeVendaMercadoriaService();
        $this->assertFalse($serv->temNfeAtiva($venda));
        $this->assertNull($serv->motivoInaptoParaNfe($venda));
    }

    public function test_inutilizada_nao_bloqueia(): void
    {
        $venda = $this->seedVenda();
        $this->criarNfe($venda, Nfe::STATUS_INUTILIZADA);

        $serv = new NfeVendaMercadoriaService();
        $this->assertFalse($serv->temNfeAtiva($venda));
        $this->assertNull($serv->motivoInaptoParaNfe($venda));
    }

    public function test_inutilizada_antiga_mais_aberta_nova_bloqueia(): void
    {
        $venda = $this->seedVenda();
        $this->criarNfe($venda, Nfe::STATUS_INUTILIZADA, numero: '1');
        $this->criarNfe($venda, Nfe::STATUS_ABERTA, numero: '2');

        $serv = new NfeVendaMercadoriaService();
        $this->assertTrue($serv->temNfeAtiva($venda));
        $this->assertStringContainsString('já possui NF-e', (string) $serv->motivoInaptoParaNfe($venda));
    }

    public function test_duplicidade_e_denegada_continuam_bloqueando(): void
    {
        $vendaDup = $this->seedVenda();
        $this->criarNfe($vendaDup, Nfe::STATUS_DUPLICIDADE);
        $this->assertTrue((new NfeVendaMercadoriaService())->temNfeAtiva($vendaDup));

        $vendaDen = $this->seedVenda();
        $this->criarNfe($vendaDen, Nfe::STATUS_DENEGADA);
        $this->assertTrue((new NfeVendaMercadoriaService())->temNfeAtiva($vendaDen));
    }

    private function seedVenda(): Venda
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMP TEM NFE '.Str::random(4),
            'ativo' => true,
            'uf' => 'SC',
        ]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'ativo' => true]);
        $cliente = Person::query()->create([
            'codigo' => 'C-'.Str::random(5),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Tem Nfe',
            'cpf_cnpj' => '52998224725',
            'is_cliente' => true,
            'ativo' => true,
            'endereco' => 'Rua A',
            'numero' => '1',
            'bairro' => 'Centro',
            'cep' => '88010000',
            'uf' => 'SC',
            'cidade_nome' => 'Florianópolis',
            'cidade_codigo' => '4205407',
        ]);
        $produto = Product::query()->create([
            'codigo' => 'P-'.Str::random(4),
            'descricao' => 'Produto',
            'preco_venda' => 10,
            'estoque' => 10,
            'ativo' => true,
        ]);

        $venda = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => (string) random_int(40000, 49999),
            'data' => now()->toDateString(),
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);
        VendaItem::query()->create([
            'venda_id' => $venda->id,
            'product_id' => $produto->id,
            'quantidade' => 1,
            'valor_item' => 10,
            'total' => 10,
        ]);
        ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $cliente->id,
            'venda_id' => $venda->id,
            'total' => 10,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'payload' => [],
        ]);

        return $venda->fresh(['itens.product', 'cliente', 'forcaVendasOrder']);
    }

    private function criarNfe(Venda $venda, string $status, string $numero = '1'): Nfe
    {
        return Nfe::query()->create([
            'empresa_id' => $venda->empresa_id,
            'numero' => $numero,
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => now()->toDateString(),
            'cliente_id' => $venda->cliente_id,
            'venda_id' => $venda->id,
            'total' => 10,
            'status' => $status,
            'situacao' => Nfe::statusToSituacao($status),
        ]);
    }
}
