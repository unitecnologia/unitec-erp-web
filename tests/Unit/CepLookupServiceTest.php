<?php

namespace Tests\Unit;

use App\Support\Erp\CepLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CepLookupServiceTest extends TestCase
{
    public function test_viacep_success_returns_contract_with_ibge(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'cep' => '88015-420',
                'logradouro' => 'Rua Deodoro',
                'bairro' => 'Centro',
                'localidade' => 'Florianópolis',
                'uf' => 'SC',
                'ibge' => '4205407',
            ]),
            'cep.awesomeapi.com.br/*' => Http::response(['should' => 'not be called'], 500),
        ]);

        $fields = app(CepLookupService::class)->lookup('88015-420');

        $this->assertSame([
            'cep' => '88015-420',
            'endereco' => 'RUA DEODORO',
            'bairro' => 'CENTRO',
            'cidade_nome' => 'FLORIANÓPOLIS',
            'uf' => 'SC',
            'cidade_codigo' => '4205407',
        ], $fields);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'viacep.com.br'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'awesomeapi.com.br'));
    }

    public function test_viacep_erro_true_does_not_call_fallback(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'erro' => true,
            ]),
            'cep.awesomeapi.com.br/*' => Http::response([
                'address' => 'Praça da Sé',
                'district' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
                'city_ibge' => '3550308',
            ]),
        ]);

        try {
            app(CepLookupService::class)->lookup('00000000');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertSame('CEP não encontrado.', $exception->getMessage());
        }

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'awesomeapi.com.br'));
    }

    public function test_viacep_connection_failure_uses_awesomeapi(): void
    {
        Http::fake([
            'viacep.com.br/*' => function () {
                throw new ConnectionException('cURL error 28: Connection timed out');
            },
            'cep.awesomeapi.com.br/*' => Http::response([
                'cep' => '01001000',
                'address' => 'Praça da Sé',
                'district' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
                'city_ibge' => '3550308',
            ]),
        ]);

        $fields = app(CepLookupService::class)->lookup('01001000');

        $this->assertSame([
            'cep' => '01001-000',
            'endereco' => 'PRAÇA DA SÉ',
            'bairro' => 'SÉ',
            'cidade_nome' => 'SÃO PAULO',
            'uf' => 'SP',
            'cidade_codigo' => '3550308',
        ], $fields);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'awesomeapi.com.br'));
    }

    public function test_viacep_http_500_uses_awesomeapi(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response('error', 500),
            'cep.awesomeapi.com.br/*' => Http::response([
                'address' => 'Rua XV de Novembro',
                'district' => 'Centro',
                'city' => 'Curitiba',
                'state' => 'PR',
                'city_ibge' => '4106902',
            ]),
        ]);

        $fields = app(CepLookupService::class)->lookup('80020000');

        $this->assertSame('RUA XV DE NOVEMBRO', $fields['endereco']);
        $this->assertSame('CURITIBA', $fields['cidade_nome']);
        $this->assertSame('PR', $fields['uf']);
        $this->assertSame('4106902', $fields['cidade_codigo']);
        $this->assertSame('80020-000', $fields['cep']);
    }

    public function test_viacep_success_without_ibge_does_not_call_fallback(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'cep' => '88015-420',
                'logradouro' => 'Rua Deodoro',
                'bairro' => 'Centro',
                'localidade' => 'Florianópolis',
                'uf' => 'SC',
                'ibge' => '',
            ]),
            'cep.awesomeapi.com.br/*' => Http::response([
                'city_ibge' => '4205407',
            ]),
        ]);

        try {
            app(CepLookupService::class)->lookup('88015420');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'CEP encontrado, mas o código IBGE do município não foi retornado.',
                $exception->getMessage(),
            );
        }

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'awesomeapi.com.br'));
    }

    public function test_incomplete_cep_does_not_call_http(): void
    {
        Http::fake();

        try {
            app(CepLookupService::class)->lookup('88015');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertSame('Informe um CEP completo com 8 dígitos.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_both_providers_connection_failure_keeps_connection_message(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 60: SSL certificate problem');
        });

        try {
            app(CepLookupService::class)->lookup('88015420');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Não foi possível consultar o CEP. Verifique a conexão e tente novamente.',
                $exception->getMessage(),
            );
        }
    }
}
