<?php

namespace Tests\Unit;

use App\Support\Erp\CnpjLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnpjLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_merges_open_cnpj_brasil_api_and_cnpj_ws(): void
    {
        Http::fake([
            'api.opencnpj.org/*' => Http::response([
                'cnpj' => '22469772000100',
                'razao_social' => '22.469.772 ALENCAR DE OLIVEIRA',
                'nome_fantasia' => '',
                'tipo_logradouro' => 'RUA',
                'logradouro' => 'DOM DANIEL',
                'numero' => '269',
                'complemento' => 'SALA 02',
                'bairro' => 'VILA REAL',
                'cep' => '88337040',
                'uf' => 'SC',
                'municipio' => 'BALNEARIO CAMBORIU',
                'email' => 'sac@unitecnologiasc.com.br',
                'telefones' => [
                    ['ddd' => '47', 'numero' => '84002117', 'is_fax' => false],
                ],
                'opcao_simples' => 'S',
            ]),
            'brasilapi.com.br/*' => Http::response([
                'cnpj' => '22469772000100',
                'razao_social' => '22.469.772 ALENCAR DE OLIVEIRA',
                'codigo_municipio_ibge' => 4202008,
                'municipio' => 'BALNEARIO CAMBORIU',
                'uf' => 'SC',
                'opcao_pelo_simples' => true,
            ]),
            'publica.cnpj.ws/*' => Http::response([
                'razao_social' => '22.469.772 ALENCAR DE OLIVEIRA',
                'estabelecimento' => [
                    'cnpj' => '22469772000100',
                    'estado' => ['sigla' => 'SC'],
                    'cidade' => ['ibge_id' => 4202008, 'nome' => 'Balneário Camboriú'],
                    'inscricoes_estaduais' => [
                        [
                            'inscricao_estadual' => '258100168',
                            'ativo' => true,
                            'estado' => ['sigla' => 'SC'],
                        ],
                    ],
                ],
            ]),
        ]);

        $fields = app(CnpjLookupService::class)->fetch('22.469.772/0001-00');

        $this->assertSame('22.469.772/0001-00', $fields['cpf_cnpj']);
        $this->assertSame('ALENCAR DE OLIVEIRA', $fields['nome_razao']);
        $this->assertSame('88337-040', $fields['cep']);
        $this->assertSame('RUA DOM DANIEL', $fields['endereco']);
        $this->assertSame('269', $fields['numero']);
        $this->assertSame('SALA 02', $fields['complemento']);
        $this->assertSame('4202008', $fields['cidade_codigo']);
        $this->assertSame('258100168', $fields['rg_ie']);
        $this->assertSame('simples', $fields['regime_tributario']);
    }

    public function test_it_uses_cache_for_repeat_lookups(): void
    {
        Http::fake([
            'api.opencnpj.org/*' => Http::response([
                'cnpj' => '22469772000100',
                'razao_social' => 'EMPRESA TESTE LTDA',
                'logradouro' => 'RUA A',
                'numero' => '10',
                'cep' => '88337040',
                'uf' => 'SC',
                'municipio' => 'BALNEARIO CAMBORIU',
            ]),
            'brasilapi.com.br/*' => Http::response(['cnpj' => '22469772000100']),
            'publica.cnpj.ws/*' => Http::response([
                'estabelecimento' => [
                    'cnpj' => '22469772000100',
                    'estado' => ['sigla' => 'SC'],
                    'inscricoes_estaduais' => [
                        [
                            'inscricao_estadual' => '123456789',
                            'ativo' => true,
                            'estado' => ['sigla' => 'SC'],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(CnpjLookupService::class);

        $service->fetch('22469772000100');
        $service->fetch('22469772000100');

        Http::assertSentCount(3);
    }
}
