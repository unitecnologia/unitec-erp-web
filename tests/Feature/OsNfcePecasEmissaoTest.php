<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\Nfe;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\VendasParametro;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfce\NfceOrdemServicoService;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * F11 NFC-e Peças (Monitor OS) — fluxos com stub SEFAZ na base MySQL DEV (rollback).
 * Rodar: tools\php\php.exe vendor\bin\phpunit -c phpunit.dev-mysql.xml --filter=OsNfcePecasEmissaoTest
 */
class OsNfcePecasEmissaoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => env('DB_DATABASE', 'unitec_erp'),
            ]);
            DB::purge();
            DB::reconnect('mysql');
        }

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            $this->markTestSkipped(
                'OsNfcePecasEmissaoTest exige MySQL DEV. Driver atual: '.DB::getDriverName()
            );
        }
    }

    public function test_monta_somente_pecas_e_fecha_total_com_acrescimo_de_arredondamento(): void
    {
        [$os, $peca] = $this->osBase(
            OrdemServico::SITUACAO_FINALIZADA,
            pecaQtd: 3,
            pecaPreco: 3.3333,
            pecaTotal: 10.00,
        );

        $estoqueAntes = (float) $peca->estoque;
        $preview = (new NfceOrdemServicoService)->conferirPecas($os);
        $this->assertCount(1, $preview['itens']);
        $this->assertEqualsWithDelta(9.99, $preview['subtotal'], 0.001);
        $this->assertEqualsWithDelta(0.01, $preview['acrescimo'], 0.001);
        $this->assertEqualsWithDelta(10.00, $preview['total'], 0.001);

        $calls = (object) ['emitir' => 0];
        $service = $this->service(
            emission: $this->stubEmissionAutorizada($os, $calls),
            transmissao: $this->stubTransmissaoNunca(),
        );

        $resultado = $service->emitir($os, ['cpf' => '529.982.247-25', 'nome' => 'CONSUMIDOR TESTE']);

        $this->assertSame(1, $calls->emitir);
        $this->assertSame(NfceOrdemServicoService::STATUS_AUTORIZADA, $resultado['status']);
        $this->assertNotNull($resultado['nfce_id']);
        $this->assertNotNull($resultado['pdv_venda_id']);

        $pdv = PdvVenda::query()->with('itens', 'pagamentos')->findOrFail((int) $resultado['pdv_venda_id']);
        $this->assertSame(PdvVenda::ORIGEM_OS_NFCE_PECAS, $pdv->origem);
        $this->assertCount(1, $pdv->itens, 'NFC-e deve incluir só peças (tipo P), sem serviços.');
        $this->assertSame((int) $peca->id, (int) $pdv->itens->first()->product_id);
        $this->assertEqualsWithDelta($preview['total'], (float) $pdv->total, 0.001);
        $this->assertEqualsWithDelta($preview['subtotal'], (float) $pdv->subtotal, 0.001);
        $this->assertEqualsWithDelta($preview['acrescimo'], (float) $pdv->acrescimo, 0.001);
        $this->assertEqualsWithDelta($preview['desconto'], (float) $pdv->desconto, 0.001);
        $this->assertEqualsWithDelta(10.00, (float) $pdv->pagamentos->sum('valor'), 0.001);

        $nfce = PdvVendaNfce::query()->findOrFail((int) $resultado['nfce_id']);
        $this->assertSame((int) $os->id, (int) $nfce->ordem_servico_id);
        $this->assertSame(PdvVendaNfce::STATUS_AUTORIZADA, $nfce->status);
        $this->assertNotEmpty($nfce->chave);
        $this->assertNotEmpty($nfce->xml);

        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);
        $this->assertEqualsWithDelta($estoqueAntes, (float) $peca->fresh()->estoque, 0.001);
        $this->assertSame(0, $this->contagemMovEstoqueProduto((int) $peca->id));
        $this->assertSame(0, $this->contagemFinanceiroDaVenda((int) $pdv->id));
    }

    public function test_bloqueia_os_nao_finalizada_e_nao_chama_sefaz(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_ABERTA);
        $calls = (object) ['emitir' => 0];
        $service = $this->service(
            emission: $this->stubEmissionContador($calls),
            transmissao: $this->stubTransmissaoNunca(),
        );

        $resultado = $service->emitir($os);

        $this->assertSame(0, $calls->emitir);
        $this->assertSame(NfceOrdemServicoService::STATUS_REJEITADA, $resultado['status']);
        $this->assertStringContainsString('finalizada', mb_strtolower($resultado['mensagem']));
    }

    public function test_bloqueia_emissao_duplicada_quando_ja_autorizada(): void
    {
        [$os, , $user] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);
        $this->vincularNfce($os, $user, PdvVendaNfce::STATUS_AUTORIZADA, '88');

        $calls = (object) ['emitir' => 0];
        $service = $this->service(
            emission: $this->stubEmissionContador($calls),
            transmissao: $this->stubTransmissaoNunca(),
        );

        $resultado = $service->emitir($os->fresh(['nfcePecasEmitida', 'nfePecasEmitida', 'itens.product', 'cliente', 'empresa']));

        $this->assertSame(0, $calls->emitir);
        $this->assertSame(NfceOrdemServicoService::STATUS_REJEITADA, $resultado['status']);
        $this->assertStringContainsString('já possui NFC-e', $resultado['mensagem']);
    }

    public function test_bloqueia_quando_ja_existe_nfe_de_pecas(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);

        Nfe::query()->create([
            'empresa_id' => $os->empresa_id,
            'cliente_id' => $os->cliente_id,
            'ordem_servico_id' => $os->id,
            'numero' => (string) random_int(700000, 799999),
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => now()->toDateString(),
            'total' => 10,
            'subtotal' => 10,
            'total_itens' => 1,
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
            'cfop' => 5102,
            'finalidade' => '1',
            'movimento' => '1',
        ]);

        $calls = (object) ['emitir' => 0];
        $service = $this->service(
            emission: $this->stubEmissionContador($calls),
            transmissao: $this->stubTransmissaoNunca(),
        );

        $resultado = $service->emitir($os->fresh(['nfcePecasEmitida', 'nfePecasEmitida', 'itens.product', 'cliente', 'empresa']));

        $this->assertSame(0, $calls->emitir);
        $this->assertSame(NfceOrdemServicoService::STATUS_REJEITADA, $resultado['status']);
        $this->assertStringContainsString('NF-e das peças', $resultado['mensagem']);
    }

    public function test_rejeicao_sefaz_vincula_nfce_e_permite_retransmitir_sem_duplicar_venda(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);

        $emission = new class($os)
        {
            public int $emitirCalls = 0;

            public function __construct(private OrdemServico $os) {}

            public function emitir(PdvVenda $venda, Empresa $empresa, VendasParametro $parametros, string $operacao, mixed ...$args): PdvVendaNfce
            {
                $this->emitirCalls++;

                return PdvVendaNfce::query()->create([
                    'pdv_venda_id' => $venda->id,
                    'empresa_id' => $this->os->empresa_id,
                    'ordem_servico_id' => null,
                    'operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
                    'status' => PdvVendaNfce::STATUS_REJEITADA,
                    'simulada' => false,
                    'numero' => '101',
                    'serie' => '1',
                    'modelo' => '65',
                    'chave' => str_repeat('2', 44),
                    'xml' => '<nfce>rejeitada</nfce>',
                    'motivo_rejeicao' => 'Rejeicao 999 — teste',
                ]);
            }
        };

        $service = $this->service($emission, $this->stubTransmissaoNunca());
        $primeiro = $service->emitir($os, ['cpf' => '', 'nome' => '']);

        $this->assertSame(1, $emission->emitirCalls);
        $this->assertSame(NfceOrdemServicoService::STATUS_REJEITADA, $primeiro['status']);
        $this->assertNotNull($primeiro['nfce_id']);
        $nfceId = (int) $primeiro['nfce_id'];
        $pdvId = (int) $primeiro['pdv_venda_id'];

        $transmissao = new class($nfceId)
        {
            public int $transmitirCalls = 0;

            public function __construct(private int $nfceId) {}

            public function transmitir(PdvVendaNfce $nfce, Empresa $empresa, mixed ...$args): PdvVendaNfce
            {
                $this->transmitirCalls++;
                if ((int) $nfce->id !== $this->nfceId) {
                    throw new \RuntimeException('Retransmissão deve reusar a mesma NFC-e.');
                }

                $nfce->forceFill([
                    'status' => PdvVendaNfce::STATUS_AUTORIZADA,
                    'protocolo' => 'PROTOCOLO-TESTE',
                    'autorizada_em' => now(),
                    'motivo_rejeicao' => null,
                ])->save();

                return $nfce->fresh();
            }
        };

        $calls2 = (object) ['emitir' => 0];
        $service2 = $this->service($this->stubEmissionContador($calls2), $transmissao);
        $segundo = $service2->emitir(
            $os->fresh(['nfcePecasEmitida', 'nfePecasEmitida', 'itens.product', 'cliente', 'empresa']),
            ['cpf' => '529.982.247-25', 'nome' => 'RETRY'],
        );

        $this->assertSame(0, $calls2->emitir);
        $this->assertSame(1, $transmissao->transmitirCalls);
        $this->assertSame(NfceOrdemServicoService::STATUS_AUTORIZADA, $segundo['status']);
        $this->assertSame($nfceId, (int) $segundo['nfce_id']);
        $this->assertSame($pdvId, (int) $segundo['pdv_venda_id']);
        $this->assertSame(
            1,
            PdvVenda::query()->where('origem', PdvVenda::ORIGEM_OS_NFCE_PECAS)->whereKey($pdvId)->count()
        );
        $this->assertSame(
            1,
            PdvVendaNfce::query()->where('ordem_servico_id', $os->id)->count()
        );
    }

    public function test_falha_de_comunicacao_nao_autoriza_e_nao_altera_situacao_os(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);

        $emission = new class
        {
            public int $emitirCalls = 0;

            public function emitir(mixed ...$args): PdvVendaNfce
            {
                $this->emitirCalls++;
                throw new FiscalEngineException('Falha de comunicação com a SEFAZ', '108', 'Servico Paralisado');
            }
        };

        $service = $this->service($emission, $this->stubTransmissaoNunca());
        $resultado = $service->emitir($os);

        $this->assertSame(1, $emission->emitirCalls);
        $this->assertSame(NfceOrdemServicoService::STATUS_REJEITADA, $resultado['status']);
        $this->assertNotSame('', trim($resultado['mensagem']));
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);
    }

    public function test_cliente_cnpj_bloqueia_nfce(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);
        $os->cliente->forceFill([
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'cpf_cnpj' => '11222333000181',
        ])->save();

        $calls = (object) ['emitir' => 0];
        $service = $this->service(
            emission: $this->stubEmissionContador($calls),
            transmissao: $this->stubTransmissaoNunca(),
        );

        $motivo = $service->motivoBloqueioEmissao(
            $os->fresh(['cliente', 'itens.product', 'empresa', 'nfcePecasEmitida', 'nfePecasEmitida'])
        );

        $this->assertSame(0, $calls->emitir);
        $this->assertNotNull($motivo);
        $this->assertStringContainsString('CNPJ', (string) $motivo);
    }

    /**
     * @return array{0: OrdemServico, 1: Product, 2: User}
     */
    private function osBase(
        string $situacao,
        float $pecaQtd = 2,
        float $pecaPreco = 20,
        ?float $pecaTotal = null,
    ): array {
        $suffix = (string) random_int(800000, 899999);
        $pecaTotal ??= round($pecaQtd * $pecaPreco, 2);

        $empresa = Empresa::query()->create([
            'codigo' => $suffix,
            'nome' => 'EMPRESA OS NFCE '.$suffix,
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
            'uf' => 'SC',
            'cidade_codigo' => '4205407',
            'cnpj' => '00000000000191',
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $cliente = Person::query()->create([
            'codigo' => 'CL'.$suffix,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS NFCE '.$suffix,
            'cpf_cnpj' => '52998224725',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $peca = Product::query()->create([
            'codigo' => 'PL'.$suffix,
            'descricao' => 'PECA OS NFCE '.$suffix,
            'unidade' => 'UN',
            'preco_venda' => $pecaPreco,
            'estoque' => 50,
            'ativo' => true,
            'ncm' => '84099190',
            'cfop_interno' => '5102',
        ]);

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => (string) (910000 + random_int(1, 9999)),
            'situacao' => $situacao,
            'cliente_id' => $cliente->id,
            'total_geral' => $pecaTotal + 60,
            'total_produtos' => $pecaTotal,
            'total_servicos' => 60,
            'subtotal_pecas' => $pecaTotal,
            'subtotal_servicos' => 60,
            'faturamento_pagamentos' => [['forma' => 'DINHEIRO', 'valor' => $pecaTotal + 60]],
        ])->save();

        foreach ([
            ['P', $peca->id, $pecaQtd, $pecaPreco, $pecaTotal],
            ['S', null, 1, 60, 60],
        ] as [$tipo, $productId, $qtd, $preco, $total]) {
            $item = new OrdemServicoItem();
            $item->forceFill([
                'ordem_servico_id' => $os->id,
                'empresa_id' => $empresa->id,
                'tipo' => $tipo,
                'product_id' => $productId,
                'qtd' => $qtd,
                'preco' => $preco,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => $total,
                'discriminacao' => $tipo === 'P' ? 'PECA' : 'SERVICO',
            ])->save();
        }

        return [$os->fresh(['itens.product', 'cliente', 'empresa']), $peca, $user];
    }

    private function service(object $emission, object $transmissao): NfceOrdemServicoService
    {
        $payload = new class
        {
            public function motivosBloqueioEmissaoReal(mixed ...$args): array
            {
                return [];
            }
        };

        return new NfceOrdemServicoService($emission, $transmissao, $payload);
    }

    private function stubEmissionAutorizada(OrdemServico $os, object $calls): object
    {
        return new class($os, $calls)
        {
            public function __construct(private OrdemServico $os, private object $calls) {}

            public function emitir(PdvVenda $venda, Empresa $empresa, VendasParametro $parametros, string $operacao, mixed ...$args): PdvVendaNfce
            {
                $this->calls->emitir++;

                return PdvVendaNfce::query()->create([
                    'pdv_venda_id' => $venda->id,
                    'empresa_id' => $this->os->empresa_id,
                    'ordem_servico_id' => null,
                    'operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
                    'status' => PdvVendaNfce::STATUS_AUTORIZADA,
                    'simulada' => false,
                    'numero' => (string) random_int(1000, 9999),
                    'serie' => '1',
                    'modelo' => '65',
                    'chave' => str_repeat('9', 44),
                    'protocolo' => 'PROT-TESTE',
                    'xml' => '<nfce>autorizada</nfce>',
                    'autorizada_em' => now(),
                ]);
            }
        };
    }

    private function stubEmissionContador(object $calls): object
    {
        return new class($calls)
        {
            public function __construct(private object $calls) {}

            public function emitir(mixed ...$args): PdvVendaNfce
            {
                $this->calls->emitir++;
                throw new \RuntimeException('SEFAZ não deveria ser chamada neste cenário.');
            }
        };
    }

    private function stubTransmissaoNunca(): object
    {
        return new class
        {
            public function transmitir(mixed ...$args): PdvVendaNfce
            {
                throw new \RuntimeException('Retransmissão não deveria ser chamada neste cenário.');
            }
        };
    }

    private function vincularNfce(OrdemServico $os, User $user, string $status, string $numero): PdvVendaNfce
    {
        $pdv = PdvVenda::query()->create([
            'user_id' => $user->id,
            'person_id' => $os->cliente_id,
            'numero' => random_int(900000, 999999),
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 10,
            'forma_pagamento' => 'DINHEIRO',
            'origem' => PdvVenda::ORIGEM_OS_NFCE_PECAS,
            'situacao' => 'F',
        ]);

        return PdvVendaNfce::query()->create([
            'pdv_venda_id' => $pdv->id,
            'empresa_id' => $os->empresa_id,
            'ordem_servico_id' => $os->id,
            'operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
            'status' => $status,
            'simulada' => false,
            'numero' => $numero,
            'serie' => '1',
            'modelo' => '65',
            'chave' => str_repeat('8', 44),
            'xml' => '<nfce>existente</nfce>',
            'autorizada_em' => $status === PdvVendaNfce::STATUS_AUTORIZADA ? now() : null,
        ]);
    }

    private function contagemMovEstoqueProduto(int $productId): int
    {
        $table = (new EstoqueMovimentacao)->getTable();

        if (! Schema::hasTable($table)) {
            return 0;
        }

        $coluna = Schema::hasColumn($table, 'product_id')
            ? 'product_id'
            : (Schema::hasColumn($table, 'produto_id') ? 'produto_id' : null);

        if ($coluna === null) {
            return 0;
        }

        return (int) EstoqueMovimentacao::query()->where($coluna, $productId)->count();
    }

    private function contagemFinanceiroDaVenda(int $pdvVendaId): int
    {
        $count = 0;

        if (Schema::hasTable('caixa_lancamentos') && Schema::hasColumn('caixa_lancamentos', 'pdv_venda_id')) {
            $count += (int) DB::table('caixa_lancamentos')->where('pdv_venda_id', $pdvVendaId)->count();
        }

        if (Schema::hasTable('contas_receber') && Schema::hasColumn('contas_receber', 'pdv_venda_id')) {
            $count += (int) DB::table('contas_receber')->where('pdv_venda_id', $pdvVendaId)->count();
        }

        return $count;
    }
}
