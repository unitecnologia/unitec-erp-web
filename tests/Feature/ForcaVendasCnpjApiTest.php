<?php

namespace Tests\Feature;

use App\Models\ForcaVendasDevice;
use App\Models\User;
use App\Support\Erp\CnpjLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ForcaVendasCnpjApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_cnpj_retorna_campos_para_o_app(): void
    {
        $device = ForcaVendasDevice::query()->create([
            'device_uuid' => 'test-device-uuid',
            'device_name' => 'Teste',
            'status' => ForcaVendasDevice::STATUS_APROVADO,
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $mock = Mockery::mock(CnpjLookupService::class);
        $mock->shouldReceive('fetch')
            ->once()
            ->with('22469772000100')
            ->andReturn([
                'cpf_cnpj' => '22.469.772/0001-00',
                'nome_razao' => 'EMPRESA TESTE LTDA',
                'apelido_fantasia' => 'EMPRESA TESTE',
                'cep' => '88337-040',
                'endereco' => 'RUA TESTE',
                'numero' => '100',
                'bairro' => 'CENTRO',
                'cidade_nome' => 'BALNEARIO CAMBORIU',
                'uf' => 'SC',
                'email' => 'contato@teste.com',
                'fone1' => '(47) 99999-9999',
                'rg_ie' => '123456789',
            ]);

        $this->app->instance(CnpjLookupService::class, $mock);

        $response = $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/cnpj/22469772000100');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.nome_razao', 'EMPRESA TESTE LTDA')
            ->assertJsonPath('data.cidade_nome', 'BALNEARIO CAMBORIU')
            ->assertJsonMissingPath('data.rg_ie');
    }
}
