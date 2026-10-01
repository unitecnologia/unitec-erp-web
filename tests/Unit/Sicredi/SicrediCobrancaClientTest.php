<?php

declare(strict_types=1);

namespace Tests\Unit\Sicredi;

use App\Models\Boleto;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\Person;
use App\Services\Sicredi\SicrediCobrancaAuth;
use App\Services\Sicredi\SicrediCobrancaClient;
use App\Services\Sicredi\SicrediBoletoEmissionService;
use App\Support\Erp\Boleto\Api\Drivers\SicrediBoletoDriver;
use App\Support\Erp\EmpresaParametros;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SicrediCobrancaClientTest extends TestCase
{
    public function test_token_password_grant(): void
    {
        Http::fake([
            'https://api-parceiro.sicredi.com.br/sb/auth/openapi/token' => Http::response([
                'access_token' => 'atk-1',
                'refresh_token' => 'rtk-1',
                'expires_in' => 300,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $auth = new SicrediCobrancaAuth;
        $creds = $auth->credentialsForCobranca($this->empresa());

        self::assertSame('atk-1', $creds['access_token']);
        self::assertSame('portal-key', $creds['x_api_key']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/auth/openapi/token')
                && $request->header('x-api-key')[0] === 'portal-key'
                && ($request['grant_type'] ?? null) === 'password'
                && ($request['username'] ?? null) === '123456789'
                && ($request['password'] ?? null) === 'senha-acesso'
                && ($request['scope'] ?? null) === 'cobranca';
        });
    }

    public function test_token_usa_client_id_quando_sem_dev_app_key(): void
    {
        Http::fake([
            'https://api-parceiro.sicredi.com.br/sb/auth/openapi/token' => Http::response([
                'access_token' => 'atk-2',
                'expires_in' => 300,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $auth = new SicrediCobrancaAuth;
        $creds = $auth->credentialsForCobranca($this->empresa([
            'param_boleto_dev_app_key' => '',
            'param_boleto_client_id' => '1deded5c-457a-414d-a361-7a4393cf5abb',
        ]));

        self::assertSame('atk-2', $creds['access_token']);
        self::assertSame('1deded5c-457a-414d-a361-7a4393cf5abb', $creds['x_api_key']);

        Http::assertSent(function ($request): bool {
            return $request->header('x-api-key')[0] === '1deded5c-457a-414d-a361-7a4393cf5abb';
        });
    }

    public function test_baixar_envia_patch(): void
    {
        Http::fake([
            'https://api-parceiro.sicredi.com.br/sb/auth/openapi/token' => Http::response([
                'access_token' => 'atk-1',
                'expires_in' => 300,
            ], 200),
            'https://api-parceiro.sicredi.com.br/sb/cobranca/boleto/v1/boletos/*/baixa' => Http::response([
                'statusComando' => 'MOVIMENTO_ENVIADO',
            ], 202),
        ]);

        $client = new SicrediCobrancaClient(new SicrediCobrancaAuth);
        $json = $client->baixarBoleto($this->empresa(), '251006142');

        self::assertSame('MOVIMENTO_ENVIADO', $json['statusComando']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/boletos/251006142/baixa');
        });
    }

    public function test_alterar_vencimento_envia_patch_com_data(): void
    {
        Http::fake([
            'https://api-parceiro.sicredi.com.br/sb/auth/openapi/token' => Http::response([
                'access_token' => 'atk-1',
                'expires_in' => 300,
            ], 200),
            'https://api-parceiro.sicredi.com.br/sb/cobranca/boleto/v1/boletos/*/data-vencimento' => Http::response([
                'ok' => true,
            ], 202),
        ]);

        $client = new SicrediCobrancaClient(new SicrediCobrancaAuth);
        $client->alterarVencimento($this->empresa(), '251006142', '2026-10-15');

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/data-vencimento')
                && ($request['dataVencimento'] ?? null) === '2026-10-15';
        });
    }

    public function test_driver_baixa_marca_boleto(): void
    {
        Http::fake([
            'https://api-parceiro.sicredi.com.br/sb/auth/openapi/token' => Http::response([
                'access_token' => 'atk-1',
                'expires_in' => 300,
            ], 200),
            'https://api-parceiro.sicredi.com.br/sb/cobranca/boleto/v1/boletos/*/baixa' => Http::response([], 202),
        ]);

        $boleto = new class extends Boleto
        {
            public bool $saved = false;

            public function save(array $options = []): bool
            {
                $this->saved = true;

                return true;
            }
        };
        $boleto->forceFill([
            'empresa_id' => 1,
            'nosso_numero' => '251006142',
            'status' => Boleto::STATUS_ABERTO,
        ]);
        $boleto->id = 77;

        $driver = new SicrediBoletoDriver(new SicrediCobrancaClient(new SicrediCobrancaAuth));
        self::assertTrue($driver->baixar($boleto, $this->empresa()));
        self::assertTrue($boleto->saved);
        self::assertSame(Boleto::STATUS_BAIXADO, $boleto->status);
    }

    public function test_payload_protesto_xor_negativacao(): void
    {
        $service = new SicrediBoletoEmissionService(
            new SicrediCobrancaClient(new SicrediCobrancaAuth),
            new SicrediCobrancaAuth,
        );

        $conta = $this->contaReceber();

        $empresaProtesto = $this->empresa([
            'param_boleto_pos_vencimento' => EmpresaParametros::BOLETO_POS_VENCIMENTO_PROTESTO,
            'param_boleto_protesto_dias' => '10',
        ]);
        $payloadProtesto = $service->buildPayloadForTests($empresaProtesto, $conta);
        self::assertSame(10, $payloadProtesto['diasProtestoAuto']);
        self::assertArrayNotHasKey('diasNegativacaoAuto', $payloadProtesto);

        $empresaNeg = $this->empresa([
            'param_boleto_pos_vencimento' => EmpresaParametros::BOLETO_POS_VENCIMENTO_NEGATIVACAO,
            'param_boleto_protesto_dias' => '5',
        ]);
        $payloadNeg = $service->buildPayloadForTests($empresaNeg, $conta);
        self::assertSame(5, $payloadNeg['diasNegativacaoAuto']);
        self::assertArrayNotHasKey('diasProtestoAuto', $payloadNeg);

        $empresaNenhuma = $this->empresa([
            'param_boleto_pos_vencimento' => EmpresaParametros::BOLETO_POS_VENCIMENTO_NENHUMA,
            'param_boleto_protesto_dias' => '5',
        ]);
        $payloadNenhuma = $service->buildPayloadForTests($empresaNenhuma, $conta);
        self::assertArrayNotHasKey('diasProtestoAuto', $payloadNenhuma);
        self::assertArrayNotHasKey('diasNegativacaoAuto', $payloadNenhuma);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function empresa(array $extra = []): Empresa
    {
        $empresa = new Empresa(array_merge([
            'param_boleto_habilitar' => true,
            'param_boleto_banco' => EmpresaParametros::BOLETO_BANCO_SICREDI,
            'param_boleto_ambiente' => 'homologacao',
            'param_boleto_dev_app_key' => 'portal-key',
            'param_boleto_agencia' => '6789',
            'param_boleto_agencia_dv' => '03',
            'param_boleto_beneficiario_codigo' => '12345',
            'param_boleto_senha_api' => 'senha-acesso',
            'param_boleto_pos_vencimento' => 'nenhuma',
        ], $extra));
        $empresa->id = 1;

        return $empresa;
    }

    private function contaReceber(): ContaReceber
    {
        $cliente = new Person([
            'nome_razao' => 'Cliente Teste',
            'cpf_cnpj' => '02738306006',
            'cep' => '91250000',
            'endereco' => 'Rua Teste',
            'cidade_nome' => 'Porto Alegre',
            'uf' => 'RS',
            'pessoa_tipo' => 'fisica',
        ]);
        $cliente->id = 9;

        $conta = new ContaReceber([
            'numero' => '100',
            'valor' => 50,
            'valor_recebido' => 0,
            'vencimento' => '2026-10-01',
            'emissao' => '2026-09-01',
        ]);
        $conta->id = 20;
        $conta->setRelation('cliente', $cliente);

        // saldo is often accessor; force attributes used by emission
        $conta->forceFill([
            'valor' => 50,
            'desconto' => 0,
            'juros' => 0,
            'valor_recebido' => 0,
        ]);

        return $conta;
    }
}
