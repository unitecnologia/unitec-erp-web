<?php

namespace Tests\Unit;

use App\Models\ForcaVendasClienteImport;
use App\Models\Person;
use App\Models\User;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ForcaVendasCustomerPushTest extends TestCase
{
    use DatabaseTransactions;

    public function test_push_cria_cliente_e_retorna_person_id(): void
    {
        $user = User::query()->first();
        $this->assertNotNull($user);

        $uuid = '11111111-2222-4333-8444-555555555555';

        $results = (new ForcaVendasSyncService())->applyCustomersPush([
            [
                'uuid' => $uuid,
                'local_id' => -123456,
                'nome_razao' => 'CLIENTE APP TESTE',
                'cpf_cnpj' => '12.345.678/0001-99',
                'cidade_nome' => 'BLUMENAU',
                'uf' => 'SC',
            ],
        ], $user);

        $this->assertSame('importado', $results[0]['status'] ?? null);
        $this->assertSame(-123456, $results[0]['local_id'] ?? null);
        $this->assertNotNull($results[0]['person_id'] ?? null);

        $person = Person::query()->find($results[0]['person_id']);
        $this->assertNotNull($person);
        $this->assertTrue($person->is_cliente);
        $this->assertSame('CLIENTE APP TESTE', $person->nome_razao);

        $this->assertTrue(
            ForcaVendasClienteImport::query()->where('uuid', $uuid)->exists()
        );
    }
}
