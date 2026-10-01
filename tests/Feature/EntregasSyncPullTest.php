<?php

namespace Tests\Feature;

use App\Models\Carga;
use App\Models\Empresa;
use App\Models\EntregasDevice;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EntregasSyncPullTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_pull_retorna_somente_cargas_fechadas_do_entregador_autenticado(): void
    {
        [$empresa, $user, $device, $carga] = $this->seedCargaFechada();

        // Carga de outro entregador / aberta / sem entregador — não devem aparecer.
        $outro = User::factory()->create([
            'empresa_id' => $empresa->id,
            'senha_app_forca_vendas' => 'x',
            'acesso_app_entregas' => true,
            'ativo' => true,
        ]);

        Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '2',
            'data' => now()->toDateString(),
            'entregador_user_id' => $outro->id,
            'status' => Carga::STATUS_FECHADA,
        ]);

        Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '3',
            'data' => now()->toDateString(),
            'entregador_user_id' => $user->id,
            'status' => Carga::STATUS_ABERTA,
        ]);

        Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '4',
            'data' => now()->toDateString(),
            'entregador_user_id' => null,
            'status' => Carga::STATUS_FECHADA,
        ]);

        Sanctum::actingAs($user);

        $resp = $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->getJson('/api/v1/entregas/sync/pull')
            ->assertOk();

        $cargas = $resp->json('cargas');
        $this->assertCount(1, $cargas);
        $this->assertSame((int) $carga->id, (int) $cargas[0]['id']);
        $this->assertSame('fechada', $cargas[0]['status']);
        $this->assertCount(1, $cargas[0]['pedidos']);
        $this->assertSame('CLIENTE ENTREGA', $cargas[0]['pedidos'][0]['cliente']);
        $this->assertNotEmpty($cargas[0]['pedidos'][0]['endereco']);
        $this->assertCount(1, $cargas[0]['pedidos'][0]['itens']);
        $this->assertSame('PRODUTO TESTE', $cargas[0]['pedidos'][0]['itens'][0]['descricao']);
    }

    public function test_pull_ignora_entregador_enviado_na_query(): void
    {
        [$empresa, $user, $device] = $this->seedCargaFechada();

        $intruso = User::factory()->create([
            'empresa_id' => $empresa->id,
            'senha_app_forca_vendas' => 'x',
            'acesso_app_entregas' => true,
            'ativo' => true,
        ]);

        Sanctum::actingAs($user);

        $resp = $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->getJson('/api/v1/entregas/sync/pull?entregador_user_id='.$intruso->id)
            ->assertOk();

        // Continua filtrando pelo user autenticado, não pelo query string.
        $this->assertCount(1, $resp->json('cargas'));
        $this->assertSame($user->id, (int) Carga::query()->find($resp->json('cargas.0.id'))->entregador_user_id);
    }

    /**
     * @return array{0: Empresa, 1: User, 2: EntregasDevice, 3: Carga}
     */
    private function seedCargaFechada(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '77',
            'nome' => 'EMPRESA PULL',
            'fantasia' => 'Pull Teste',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'ENTREGADOR APP',
            'senha_app_forca_vendas' => 'senha123',
            'acesso_app_entregas' => true,
            'ativo' => true,
        ]);

        $device = EntregasDevice::query()->create([
            'device_uuid' => 'ent-pull-1',
            'status' => EntregasDevice::STATUS_APROVADO,
            'approved_at' => now(),
            'empresa_id' => $empresa->id,
            'user_id' => $user->id,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-ENT-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE ENTREGA',
            'is_cliente' => true,
            'ativo' => true,
            'cpf_cnpj' => '12345678901',
            'endereco' => 'Rua das Entregas',
            'numero' => '100',
            'bairro' => 'Centro',
            'cidade_nome' => 'Cidade',
            'uf' => 'SC',
            'cep' => '88000000',
            'fone1' => '47999999999',
        ]);

        $produto = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO TESTE',
            'unidade' => 'UN',
            'preco_venda' => 10,
            'estoque' => 0,
            'ativo' => true,
        ]);

        $pedido = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '1250',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 850.00,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        VendaItem::query()->create([
            'venda_id' => $pedido->id,
            'product_id' => $produto->id,
            'quantidade' => 2,
            'valor_item' => 425,
            'total' => 850,
        ]);

        $carga = Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '15',
            'data' => now()->toDateString(),
            'entregador_user_id' => $user->id,
            'status' => Carga::STATUS_FECHADA,
            'observacao' => 'Romaneio teste',
        ]);

        $carga->pedidos()->attach($pedido->id);

        return [$empresa, $user, $device, $carga];
    }
}
