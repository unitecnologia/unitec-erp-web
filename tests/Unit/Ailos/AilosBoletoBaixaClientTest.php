<?php

declare(strict_types=1);

namespace Tests\Unit\Ailos;

use App\Models\Boleto;
use App\Models\Empresa;
use App\Services\Ailos\AilosCobrancaAuth;
use App\Services\Ailos\AilosCobrancaClient;
use App\Support\Erp\Boleto\Api\Drivers\AilosBoletoDriver;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class AilosBoletoBaixaClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_baixar_boletos_lote_envia_delete_com_body(): void
    {
        Http::fake([
            'https://api.example.test/ailos/cobranca/api/v1/boletos/lote' => Http::response([
                'ticket' => 'TK-BAIXA-1',
            ], 200),
        ]);

        $client = new AilosCobrancaClient($this->authMock());
        $empresa = $this->empresa();

        $json = $client->baixarBoletosLote($empresa, [[
            'numeroConvenio' => 999,
            'numeroBoleto' => 123456,
        ]]);

        self::assertSame('TK-BAIXA-1', $json['ticket']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/ailos/cobranca/api/v1/boletos/lote')
                && ($request['boletos'][0]['numeroConvenio'] ?? null) === 999
                && ($request['boletos'][0]['numeroBoleto'] ?? null) === 123456;
        });
    }

    public function test_consultar_instrucao_lote(): void
    {
        Http::fake([
            'https://api.example.test/ailos/cobranca/api/v1/instrucoes/lote/*' => Http::response([
                'status' => 'sucesso',
            ], 200),
        ]);

        $client = new AilosCobrancaClient($this->authMock());
        $json = $client->consultarInstrucaoLote($this->empresa(), 'TK-1');

        self::assertSame('sucesso', $json['status']);
    }

    public function test_servico_marca_boleto_baixado_apos_api(): void
    {
        Http::fake([
            'https://api.example.test/ailos/cobranca/api/v1/boletos/lote' => Http::response([
                'ticket' => 'TK-OK',
            ], 200),
            'https://api.example.test/ailos/cobranca/api/v1/instrucoes/lote/TK-OK' => Http::response([
                'status' => 'concluido',
            ], 200),
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
            'conta_receber_id' => 10,
            'nosso_numero' => '778899',
            'id_externo' => '778899',
            'status' => Boleto::STATUS_ABERTO,
        ]);
        $boleto->id = 55;

        $service = new AilosBoletoDriver(new AilosCobrancaClient($this->authMock()));
        $ok = $service->baixar($boleto, $this->empresa());

        self::assertTrue($ok);
        self::assertTrue($boleto->saved);
        self::assertSame(Boleto::STATUS_BAIXADO, $boleto->status);
    }

    public function test_alterar_vencimento_lote_envia_put_com_body(): void
    {
        Http::fake([
            'https://api.example.test/ailos/cobranca/api/v1/boletos/vencimento/lote' => Http::response([
                'ticket' => 'TK-VENC-1',
            ], 200),
        ]);

        $client = new AilosCobrancaClient($this->authMock());
        $json = $client->alterarVencimentoLote($this->empresa(), [[
            'numeroConvenio' => 999,
            'numeroBoleto' => 123456,
            'vencimento' => ['dataVencimento' => '2026-10-01T12:00:00.000Z'],
        ]]);

        self::assertSame('TK-VENC-1', $json['ticket']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/ailos/cobranca/api/v1/boletos/vencimento/lote')
                && ($request['boletos'][0]['numeroConvenio'] ?? null) === 999
                && ($request['boletos'][0]['vencimento']['dataVencimento'] ?? null) === '2026-10-01T12:00:00.000Z';
        });
    }

    public function test_driver_atualiza_vencimento_local_apos_api(): void
    {
        Http::fake([
            'https://api.example.test/ailos/cobranca/api/v1/boletos/vencimento/lote' => Http::response([
                'ticket' => 'TK-V',
            ], 200),
            'https://api.example.test/ailos/cobranca/api/v1/instrucoes/lote/TK-V' => Http::response([
                'status' => 'concluido',
            ], 200),
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
            'conta_receber_id' => 10,
            'nosso_numero' => '778899',
            'status' => Boleto::STATUS_ABERTO,
            'vencimento' => '2026-09-01',
        ]);
        $boleto->id = 56;

        $driver = new AilosBoletoDriver(new AilosCobrancaClient($this->authMock()));
        $driver->alterarVencimento($boleto, \Carbon\Carbon::parse('2026-10-15'), $this->empresa());

        self::assertTrue($boleto->saved);
        self::assertSame('2026-10-15', $boleto->vencimento instanceof \Carbon\CarbonInterface
            ? $boleto->vencimento->toDateString()
            : (string) $boleto->vencimento);
    }

    private function authMock(): AilosCobrancaAuth
    {
        $auth = Mockery::mock(AilosCobrancaAuth::class);
        $auth->shouldReceive('credentialsForCobranca')->andReturn([
            'access_token' => 'token-teste',
            'jwt' => 'jwt-teste',
        ]);
        $auth->shouldReceive('hostForEmpresa')->andReturn('https://api.example.test');

        return $auth;
    }

    private function empresa(): Empresa
    {
        $empresa = new Empresa([
            'param_boleto_convenio' => '999',
            'param_boleto_habilitar' => true,
            'param_boleto_banco' => '085',
        ]);
        $empresa->id = 1;

        return $empresa;
    }
}
