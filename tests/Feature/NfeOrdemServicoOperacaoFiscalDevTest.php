<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\OperacaoFiscal;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\Nfe\NfeOrdemServicoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F10 NF-e Peças × Operação Fiscal real no MySQL DEV (sem SEFAZ, rollback).
 * Rodar: tools\php\php.exe vendor\bin\phpunit -c phpunit.dev-mysql.xml --filter=NfeOrdemServicoOperacaoFiscalDevTest
 */
class NfeOrdemServicoOperacaoFiscalDevTest extends TestCase
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
            $this->markTestSkipped('Exige MySQL DEV.');
        }
    }

    public function test_os_108_sc_usa_cfop_estadual_da_operacao_fiscal(): void
    {
        $os = OrdemServico::query()->with(['itens.product', 'cliente', 'empresa'])->find(108);
        if ($os === null) {
            $this->markTestSkipped('OS 108 ausente no DEV.');
        }

        $empresa = $os->empresa ?? Empresa::query()->find($os->empresa_id);
        $this->assertNotNull($empresa);
        $this->assertSame('SC', strtoupper(trim((string) $empresa->uf)));
        $this->assertSame('SC', strtoupper(trim((string) ($os->cliente?->uf ?? ''))));

        $op = OperacaoFiscal::forEmpresa((int) $empresa->id);
        $cfopEsperado = (int) ($op->cfopVendaMercadoria(false) ?? 0);
        $this->assertGreaterThan(0, $cfopEsperado);

        $payload = (new NfeOrdemServicoService)->montarPayload($os);

        $this->assertCount(8, $payload['rows']);
        foreach ($payload['rows'] as $row) {
            $this->assertSame((string) $cfopEsperado, (string) $row['cfop']);
        }
        $this->assertStringStartsWith((string) $cfopEsperado, $payload['natureza_operacao']);
        $this->assertSame(2484.90, round(array_sum(array_map(
            static fn (array $r): float => round($r['quantidade'] * $r['valor_unitario'], 2),
            $payload['rows'],
        )), 2));
    }

    public function test_interestadual_usa_cfop_configurado_e_nao_o_estadual(): void
    {
        $suffix = (string) random_int(700000, 799999);

        $empresa = Empresa::query()->create([
            'codigo' => $suffix,
            'nome' => 'EMP NFE OS '.$suffix,
            'uf' => 'SC',
            'cidade_codigo' => '4205407',
            'cnpj' => '00000000000191',
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
        ]);

        OperacaoFiscal::query()->updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'cfop_venda_mercadoria_estadual' => 5102,
                'cfop_venda_mercadoria_interestadual' => 6108,
            ]
        );

        $clienteSc = Person::query()->create([
            'codigo' => 'SC'.$suffix,
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'nome_razao' => 'CLI SC '.$suffix,
            'cpf_cnpj' => '11222333000181',
            'uf' => 'SC',
            'is_cliente' => true,
            'ativo' => true,
        ]);
        $clientePr = Person::query()->create([
            'codigo' => 'PR'.$suffix,
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'nome_razao' => 'CLI PR '.$suffix,
            'cpf_cnpj' => '00000000000191',
            'uf' => 'PR',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $peca = Product::query()->create([
            'codigo' => 'NP'.$suffix,
            'descricao' => 'PECA NFE '.$suffix,
            'unidade' => 'UN',
            'preco_venda' => 100,
            'estoque' => 10,
            'ativo' => true,
        ]);

        $osSc = $this->criarOs($empresa, $clienteSc, $peca, 100);
        $osPr = $this->criarOs($empresa, $clientePr, $peca, 100);

        $svc = new NfeOrdemServicoService;
        $payloadSc = $svc->montarPayload($osSc->fresh(['itens.product', 'cliente', 'empresa']));
        $payloadPr = $svc->montarPayload($osPr->fresh(['itens.product', 'cliente', 'empresa']));

        $this->assertSame(['5102'], array_values(array_unique(array_column($payloadSc['rows'], 'cfop'))));
        $this->assertStringStartsWith('5102', $payloadSc['natureza_operacao']);

        $this->assertSame(['6108'], array_values(array_unique(array_column($payloadPr['rows'], 'cfop'))));
        $this->assertStringStartsWith('6108', $payloadPr['natureza_operacao']);
        $this->assertCount(1, $payloadPr['rows']);
    }

    private function criarOs(Empresa $empresa, Person $cliente, Product $peca, float $total): OrdemServico
    {
        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => (string) random_int(920000, 929999),
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'total_produtos' => $total,
            'total_servicos' => 50,
            'total_geral' => $total + 50,
            'subtotal_pecas' => $total,
            'subtotal_servicos' => 50,
        ])->save();

        foreach ([
            ['P', $peca->id, 1, $total, $total],
            ['S', null, 1, 50, 50],
        ] as [$tipo, $productId, $qtd, $preco, $tot]) {
            $item = new OrdemServicoItem();
            $item->forceFill([
                'ordem_servico_id' => $os->id,
                'empresa_id' => $empresa->id,
                'tipo' => $tipo,
                'product_id' => $productId,
                'qtd' => $qtd,
                'preco' => $preco,
                'desconto' => 0,
                'total' => $tot,
                'discriminacao' => $tipo === 'P' ? 'PECA' : 'SERVICO',
            ])->save();
        }

        return $os;
    }
}
