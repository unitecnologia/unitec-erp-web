<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ForcaVendas\SyncController;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use Illuminate\Http\Request;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ForcaVendasCustomerEmailUpdateTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_atualiza_somente_email_e_preserva_o_restante_da_ficha(): void
    {
        [$user, $cliente] = $this->clienteNaCarteira('antes@cliente.com');
        $antes = $this->ficha($cliente);

        $results = app(ForcaVendasSyncService::class)->applyCustomerEmailUpdates([
            [
                'uuid' => 'email-1',
                'person_id' => $cliente->id,
                'email' => ' novo@cliente.com ',
                'fone1' => 'NAO-GRAVAR',
                'nome_razao' => 'NAO-GRAVAR',
                'celular1' => 'NAO-GRAVAR',
                'whatsapp' => 'NAO-GRAVAR',
            ],
        ], $user);

        $this->assertSame('importado', $results[0]['status']);
        $cliente->refresh();
        $this->assertSame('novo@cliente.com', $cliente->email);
        $this->assertSame($antes, $this->ficha($cliente));
    }

    public function test_limpar_email_grava_nulo_sem_mexer_no_telefone(): void
    {
        [$user, $cliente] = $this->clienteNaCarteira('antes@cliente.com');
        $antes = $this->ficha($cliente);

        $results = app(ForcaVendasSyncService::class)->applyCustomerEmailUpdates([
            [
                'uuid' => 'email-vazio',
                'person_id' => $cliente->id,
                'email' => '   ',
            ],
        ], $user);

        $this->assertSame('importado', $results[0]['status']);
        $cliente->refresh();
        $this->assertNull($cliente->email);
        $this->assertSame($antes, $this->ficha($cliente));
    }

    public function test_email_invalido_nao_salva(): void
    {
        [$user, $cliente] = $this->clienteNaCarteira('antes@cliente.com');

        $results = app(ForcaVendasSyncService::class)->applyCustomerEmailUpdates([
            [
                'uuid' => 'email-ruim',
                'person_id' => $cliente->id,
                'email' => 'sem-arroba',
            ],
        ], $user);

        $this->assertSame('erro', $results[0]['status']);
        $this->assertSame('antes@cliente.com', $cliente->fresh()->email);
        $this->assertSame('4733334444', $cliente->fresh()->fone1);
    }

    public function test_cliente_fora_da_carteira_nao_e_alterado(): void
    {
        $vendedor = Vendedor::query()->create([
            'codigo' => 'V-EMAIL',
            'nome' => 'VENDEDOR',
            'ativo' => true,
        ]);
        $outro = Vendedor::query()->create([
            'codigo' => 'V-OUTRO',
            'nome' => 'OUTRO',
            'ativo' => true,
        ]);
        $user = User::factory()->create(['vendedor_id' => $vendedor->id]);
        $cliente = $this->person([
            'vendedor_fv_id' => $outro->id,
            'email' => 'antes@cliente.com',
        ]);

        $results = app(ForcaVendasSyncService::class)->applyCustomerEmailUpdates([
            [
                'uuid' => 'email-fora',
                'person_id' => $cliente->id,
                'email' => 'novo@cliente.com',
            ],
        ], $user);

        $this->assertSame('erro', $results[0]['status']);
        $this->assertSame('antes@cliente.com', $cliente->fresh()->email);
    }

    public function test_push_atual_aceita_atualizacao_de_email_sem_endpoint_novo(): void
    {
        [$user, $cliente] = $this->clienteNaCarteira('antes@cliente.com');
        $antes = $this->ficha($cliente);

        $request = Request::create('/api/v1/forca-vendas/sync/push', 'POST', [
            'customer_updates' => [[
                'uuid' => 'email-push',
                'person_id' => $cliente->id,
                'email' => 'chegou@cliente.com',
                'fone1' => 'NAO-GRAVAR',
                'nome_razao' => 'NAO-GRAVAR',
            ]],
        ]);
        $request->setUserResolver(fn () => $user);

        $payload = app(SyncController::class)->push($request)->getData(true);

        $this->assertSame('importado', $payload['customer_update_results'][0]['status']);
        $cliente->refresh();
        $this->assertSame('chegou@cliente.com', $cliente->email);
        $this->assertSame($antes, $this->ficha($cliente));
    }

    /**
     * @return array{0: User, 1: Person}
     */
    private function clienteNaCarteira(string $email): array
    {
        $vendedor = Vendedor::query()->create([
            'codigo' => 'V-'.substr(md5($email.microtime()), 0, 8),
            'nome' => 'VENDEDOR',
            'ativo' => true,
        ]);
        $user = User::factory()->create(['vendedor_id' => $vendedor->id]);
        $cliente = $this->person([
            'vendedor_fv_id' => $vendedor->id,
            'email' => $email,
        ]);

        return [$user, $cliente];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function person(array $extra): Person
    {
        return Person::query()->create(array_merge([
            'codigo' => 'C-'.substr(md5(json_encode($extra).microtime()), 0, 8),
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'nome_razao' => 'CLIENTE EMAIL',
            'apelido_fantasia' => 'FANTASIA',
            'cpf_cnpj' => '11222333000181',
            'endereco' => 'RUA A',
            'numero' => '10',
            'bairro' => 'CENTRO',
            'cidade_nome' => 'BLUMENAU',
            'uf' => 'SC',
            'cep' => '89010000',
            'email2' => 'financeiro@cliente.com',
            'fone1' => '4733334444',
            'celular1' => '47988887777',
            'whatsapp' => '47988887777',
            'limite_credito' => 1500.5,
            'is_cliente' => true,
            'ativo' => true,
        ], $extra));
    }

    /**
     * @return array<string, mixed>
     */
    private function ficha(Person $person): array
    {
        return [
            'nome_razao' => $person->nome_razao,
            'apelido_fantasia' => $person->apelido_fantasia,
            'cpf_cnpj' => $person->cpf_cnpj,
            'endereco' => $person->endereco,
            'numero' => $person->numero,
            'bairro' => $person->bairro,
            'cidade_nome' => $person->cidade_nome,
            'uf' => $person->uf,
            'cep' => $person->cep,
            'email2' => $person->email2,
            'fone1' => $person->fone1,
            'celular1' => $person->celular1,
            'whatsapp' => $person->whatsapp,
            'limite_credito' => (float) $person->limite_credito,
            'vendedor_fv_id' => (int) $person->vendedor_fv_id,
            'ativo' => (bool) $person->ativo,
            'is_cliente' => (bool) $person->is_cliente,
        ];
    }
}
