<?php

namespace Tests\Unit;

use App\Models\FormaPagamento;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\Product;
use App\Support\Fiscal\NfceRecuperarConferenciaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Conferência F4 Recuperar: produtos + meios de pagamento.
 * Valores comerciais e pagamentos (valor/forma) permanecem intactos.
 */
class NfceRecuperarConferenciaServiceTest extends TestCase
{

    public function test_autorizada_nao_executa_nem_altera(): void
    {
        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'simulada' => false,
            'xml' => '<NFe></NFe>',
        ]);

        $r = (new NfceRecuperarConferenciaService())->conferir($nfce, aplicar: true);

        $this->assertFalse($r->executada);
        $this->assertFalse($r->aplicouAtualizacoes);
        $this->assertStringContainsString('definitivo', $r->motivoPula);
    }

    public function test_cancelada_e_denegada_preservam_documento(): void
    {
        $svc = new NfceRecuperarConferenciaService();

        foreach ([PdvVendaNfce::STATUS_CANCELADA, PdvVendaNfce::STATUS_DENEGADA] as $status) {
            $r = $svc->conferir(new PdvVendaNfce([
                'status' => $status,
                'simulada' => false,
            ]), aplicar: true);

            $this->assertFalse($r->executada, $status);
            $this->assertSame(0, $r->atualizacoesAplicadas);
        }
    }

    public function test_contingencia_somente_leitura_preserva_xml_conceito(): void
    {
        $product = new Product([
            'id' => 900001,
            'descricao' => 'PRODUTO NOVO',
            'ncm' => '61091000',
            'cest' => '',
            'cfop_interno' => '5102',
            'csosn' => '102',
            'origem' => 0,
        ]);
        $product->syncOriginal();

        $item = new PdvVendaItem([
            'id' => 1,
            'product_id' => 900001,
            'descricao' => 'PRODUTO VELHO',
            'quantidade' => 2,
            'preco_unitario' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 20,
        ]);
        $item->setRelation('product', $product);

        $venda = new PdvVenda(['id' => 1, 'total' => 20]);
        $venda->setRelation('itens', collect([$item]));
        $venda->setRelation('pagamentos', collect());

        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'simulada' => false,
            'xml' => null,
        ]);
        $nfce->setRelation('pdvVenda', $venda);

        $r = (new NfceRecuperarConferenciaService())->conferirEAtualizar($nfce);

        $this->assertTrue($r->executada);
        $this->assertFalse($r->aplicouAtualizacoes);
        $this->assertStringContainsString('Contingência', $r->motivoPula);
        $this->assertTrue(collect($r->diferencas)->contains(
            fn (array $d): bool => ($d['campo'] ?? '') === 'descricao' && ($d['acao'] ?? '') === 'info'
        ));
    }

    public function test_rejeitada_detecta_descricao_e_ncm_sem_alterar_valores_comerciais(): void
    {
        $xml = <<<'XML'
<NFe xmlns="http://www.portalfiscal.inf.br/nfe">
  <infNFe>
    <det nItem="1">
      <prod>
        <cProd>1</cProd>
        <xProd>PRODUTO VELHO</xProd>
        <NCM>61091000</NCM>
        <CFOP>5102</CFOP>
        <qCom>2.0000</qCom>
        <vUnCom>10.00</vUnCom>
        <vProd>20.00</vProd>
      </prod>
      <imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS></imposto>
    </det>
    <pag><detPag><tPag>01</tPag><vPag>20.00</vPag></detPag></pag>
  </infNFe>
</NFe>
XML;

        $product = new Product([
            'id' => 900002,
            'descricao' => 'PRODUTO ATUALIZADO',
            'ncm' => '61099000',
            'cest' => '2805800',
            'cfop_interno' => '5102',
            'csosn' => '102',
            'origem' => 0,
        ]);
        $product->syncOriginal();

        $item = new PdvVendaItem([
            'id' => 42,
            'product_id' => 900002,
            'descricao' => 'PRODUTO VELHO',
            'quantidade' => 2,
            'preco_unitario' => 10,
            'desconto' => 1.5,
            'acrescimo' => 0.5,
            'total' => 19,
        ]);
        $item->setRelation('product', $product);

        $pag = new PdvVendaPagamento([
            'id' => 7,
            'forma' => 'DINHEIRO',
            'valor' => 19,
        ]);

        $venda = new PdvVenda(['id' => 2, 'total' => 19]);
        $venda->setRelation('itens', collect([$item]));
        $venda->setRelation('pagamentos', collect([$pag]));

        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'simulada' => false,
            'xml' => $xml,
        ]);
        $nfce->setRelation('pdvVenda', $venda);

        $snapItem = [
            'quantidade' => (string) $item->quantidade,
            'preco_unitario' => (string) $item->preco_unitario,
            'desconto' => (string) $item->desconto,
            'acrescimo' => (string) $item->acrescimo,
            'total' => (string) $item->total,
        ];
        $snapPag = [
            'forma' => (string) $pag->forma,
            'valor' => (string) $pag->valor,
        ];

        $r = (new NfceRecuperarConferenciaService())->conferir($nfce, aplicar: false);

        $this->assertTrue($r->executada);
        $this->assertFalse($r->aplicouAtualizacoes);

        $campos = collect($r->diferencas)->pluck('campo')->all();
        $this->assertContains('descricao', $campos);
        $this->assertContains('NCM', $campos);

        $desc = collect($r->diferencas)->firstWhere('campo', 'descricao');
        $this->assertSame('aplicavel', $desc['acao']);
        $this->assertSame('PRODUTO VELHO', $desc['antes']);
        $this->assertSame('PRODUTO ATUALIZADO', $desc['depois']);

        $ncm = collect($r->diferencas)->firstWhere('campo', 'NCM');
        $this->assertSame('info', $ncm['acao']);
        $this->assertSame('61091000', $ncm['antes']);
        $this->assertSame('61099000', $ncm['depois']);

        // Memória local intacta (sem apply); garante contrato comercial.
        $this->assertSame($snapItem['quantidade'], (string) $item->quantidade);
        $this->assertSame($snapItem['preco_unitario'], (string) $item->preco_unitario);
        $this->assertSame($snapItem['desconto'], (string) $item->desconto);
        $this->assertSame($snapItem['acrescimo'], (string) $item->acrescimo);
        $this->assertSame($snapItem['total'], (string) $item->total);
        $this->assertSame($snapPag['forma'], (string) $pag->forma);
        $this->assertSame($snapPag['valor'], (string) $pag->valor);
        $this->assertSame('PRODUTO VELHO', (string) $item->descricao);
    }

    public function test_pagamento_tpag_divergente_do_tipo_exige_decisao_sem_trocar_forma(): void
    {
        // Cadastro tipo=pix (tPag 20) × descrição da venda sem "PIX" (heurística 99).
        $forma = new FormaPagamento([
            'id' => 55,
            'codigo' => 55,
            'descricao' => 'RECEBIMENTO LOJA XYZ',
            'tipo' => 'pix',
            'tipo_movimento' => 'caixa',
            'ativo' => true,
            'nfce' => true,
        ]);

        $pag = new PdvVendaPagamento([
            'id' => 99,
            'forma' => 'RECEBIMENTO LOJA XYZ',
            'valor' => 50.00,
        ]);

        $venda = new PdvVenda(['id' => 3, 'total' => 50]);
        $venda->setRelation('itens', collect());
        $venda->setRelation('pagamentos', collect([$pag]));

        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_PENDENTE,
            'simulada' => false,
            'xml' => null,
        ]);
        $nfce->setRelation('pdvVenda', $venda);

        $svc = new NfceRecuperarConferenciaServiceComFormasFixas($forma);
        $r = $svc->conferir($nfce, aplicar: false);

        $this->assertTrue($r->executada);
        $this->assertGreaterThan(0, $r->divergenciasPendentes);
        $this->assertTrue(collect($r->diferencas)->contains(
            fn (array $d): bool => ($d['campo'] ?? '') === 'tPag' && ($d['acao'] ?? '') === 'pendente'
        ));
        $this->assertSame('RECEBIMENTO LOJA XYZ', (string) $pag->forma);
        $this->assertSame('50.00', number_format((float) $pag->valor, 2, '.', ''));
    }

    public function test_apply_atualiza_so_descricao_e_preserva_valores_no_banco(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Persistência da conferência exige MySQL DEV (phpunit padrão usa SQLite :memory:).');
        }

        $suffix = (string) random_int(100000, 999999);

        $productId = (int) DB::table('products')->insertGetId([
            'codigo' => 'NFC-CONF-'.$suffix,
            'descricao' => 'DESC NOVA '.$suffix,
            'ncm' => '61091000',
            'cfop_interno' => '5102',
            'csosn' => '102',
            'origem' => 0,
            'preco_venda' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vendaId = (int) DB::table('pdv_vendas')->insertGetId([
            'numero' => (int) ('9'.$suffix),
            'subtotal' => 20,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 20,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = (int) DB::table('pdv_venda_itens')->insertGetId([
            'pdv_venda_id' => $vendaId,
            'product_id' => $productId,
            'codigo' => 'NFC-CONF-'.$suffix,
            'descricao' => 'DESC ANTIGA '.$suffix,
            'unidade' => 'UN',
            'quantidade' => 2,
            'preco_unitario' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pagId = (int) DB::table('pdv_venda_pagamentos')->insertGetId([
            'pdv_venda_id' => $vendaId,
            'forma' => 'DINHEIRO',
            'valor' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nfceId = (int) DB::table('pdv_venda_nfce')->insertGetId([
            'pdv_venda_id' => $vendaId,
            'empresa_id' => (int) (DB::table('empresas')->orderBy('id')->value('id') ?: 1),
            'operacao' => 'nfce_transmitir',
            'modelo' => '65',
            'serie' => '1',
            'numero' => (int) ('8'.$suffix),
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'simulada' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nfce = PdvVendaNfce::query()->findOrFail($nfceId);

        $r = (new NfceRecuperarConferenciaService())->conferirEAtualizar($nfce);

        $this->assertTrue($r->executada);
        $this->assertTrue($r->aplicouAtualizacoes);
        $this->assertGreaterThanOrEqual(1, $r->atualizacoesAplicadas);

        $item = DB::table('pdv_venda_itens')->where('id', $itemId)->first();
        $pag = DB::table('pdv_venda_pagamentos')->where('id', $pagId)->first();
        $venda = DB::table('pdv_vendas')->where('id', $vendaId)->first();

        $this->assertSame('DESC NOVA '.$suffix, (string) $item->descricao);
        $this->assertEquals(2, (float) $item->quantidade);
        $this->assertEquals(10, (float) $item->preco_unitario);
        $this->assertEquals(0, (float) $item->desconto);
        $this->assertEquals(0, (float) $item->acrescimo);
        $this->assertEquals(20, (float) $item->total);
        $this->assertSame('DINHEIRO', (string) $pag->forma);
        $this->assertEquals(20, (float) $pag->valor);
        $this->assertEquals(20, (float) $venda->total);
        $this->assertSame(PdvVendaNfce::STATUS_REJEITADA, (string) DB::table('pdv_venda_nfce')->where('id', $nfceId)->value('status'));
    }

    public function test_duplicidade_somente_leitura_apos_sefaz_sem_gravar(): void
    {
        $product = new Product([
            'id' => 900010,
            'descricao' => 'NOVA',
            'ncm' => '61091000',
            'cfop_interno' => '5102',
            'csosn' => '102',
            'origem' => 0,
        ]);
        $product->syncOriginal();

        $item = new PdvVendaItem([
            'id' => 11,
            'product_id' => 900010,
            'descricao' => 'VELHA',
            'quantidade' => 1,
            'preco_unitario' => 9.9,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 9.9,
        ]);
        $item->setRelation('product', $product);

        $venda = new PdvVenda(['id' => 11, 'total' => 9.9]);
        $venda->setRelation('itens', collect([$item]));
        $venda->setRelation('pagamentos', collect());

        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_DUPLICIDADE,
            'simulada' => false,
            'xml' => null,
        ]);
        $nfce->setRelation('pdvVenda', $venda);

        $r = (new NfceRecuperarConferenciaService())->conferirEAtualizar($nfce);

        $this->assertTrue($r->executada);
        $this->assertFalse($r->aplicouAtualizacoes);
        $this->assertStringContainsString('Duplicidade', $r->motivoPula);
        $this->assertStringContainsString('autorizado', mb_strtolower($r->motivoPula));
        $this->assertSame('VELHA', (string) $item->descricao);
        $this->assertEqualsWithDelta(9.9, (float) $item->preco_unitario, 0.001);
    }

    public function test_resumo_separa_corrigido_e_intervencao(): void
    {
        $product = new Product([
            'id' => 900011,
            'descricao' => 'DESC OK',
            'ncm' => '61099000',
            'cest' => '2805800',
            'cfop_interno' => '5102',
            'csosn' => '900',
            'origem' => 0,
        ]);
        $product->syncOriginal();

        $xml = <<<'XML'
<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe>
<det nItem="1"><prod><xProd>DESC VELHA</xProd><NCM>61091000</NCM><CEST>2805700</CEST><CFOP>5102</CFOP></prod>
<imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS></imposto></det>
</infNFe></NFe>
XML;

        $item = new PdvVendaItem([
            'id' => 12,
            'product_id' => 900011,
            'descricao' => 'DESC VELHA',
            'quantidade' => 1,
            'preco_unitario' => 1,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 1,
        ]);
        $item->setRelation('product', $product);

        $venda = new PdvVenda(['id' => 12, 'total' => 1]);
        $venda->setRelation('itens', collect([$item]));
        $venda->setRelation('pagamentos', collect());

        $nfce = new PdvVendaNfce([
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'simulada' => false,
            'xml' => $xml,
        ]);
        $nfce->setRelation('pdvVenda', $venda);

        $r = (new NfceRecuperarConferenciaService())->conferir($nfce, aplicar: false);
        $resumo = $r->resumoNotificacao();

        $this->assertStringContainsString('CORRIGIDO', $resumo);
        $this->assertStringContainsString('EXIGE INTERVENÇÃO', $resumo);
        $this->assertTrue($r->exigeIntervencao());
        $this->assertStringContainsString('F5', $resumo);
    }

    public function test_descricao_corrigida_e_fonte_do_xprod_no_builder(): void
    {
        $builder = file_get_contents(app_path('Support/Fiscal/PdvNfceFiscalPayloadBuilder.php'));
        $this->assertIsString($builder);
        $this->assertStringContainsString('$descricao = (string) $item->descricao;', $builder);
        $this->assertStringContainsString('descricao: $descricao,', $builder);
        $this->assertStringContainsString('ncm: (string) ($product?->ncm', $builder);
        $this->assertStringContainsString('cest: CestCodigo::normalizar', $builder);
    }

    public function test_wiring_recuperar_chama_conferencia(): void
    {
        $src = file_get_contents(app_path(
            'Filament/Resources/NfceResource/Pages/Concerns/ManagesNfceFiscalActions.php'
        ));

        $this->assertIsString($src);
        $this->assertStringContainsString('NfceRecuperarConferenciaService', $src);
        $this->assertStringContainsString('conferirEAtualizar', $src);
        $this->assertStringContainsString('resumoNotificacao', $src);
        $this->assertStringContainsString('exigeIntervencao', $src);
        $this->assertStringContainsString('tentarResolverDuplicidade', $src);

        $posRecuperar = strpos($src, 'public function recuperarNfce(): void');
        $posDup = strpos($src, 'tentarResolverDuplicidade', $posRecuperar ?: 0);
        $posConferencia = strpos($src, 'conferirEAtualizar', $posRecuperar ?: 0);
        $this->assertNotFalse($posRecuperar);
        $this->assertNotFalse($posDup);
        $this->assertNotFalse($posConferencia);
        $this->assertGreaterThan($posRecuperar, $posDup);
        $this->assertGreaterThan($posDup, $posConferencia);
    }
}

/** Stub: injeta forma de pagamento sem consultar DB. */
class NfceRecuperarConferenciaServiceComFormasFixas extends NfceRecuperarConferenciaService
{
    public function __construct(private FormaPagamento $formaCadastro) {}

    protected function carregarFormasPagamento(\Illuminate\Support\Collection $pagamentos): \Illuminate\Support\Collection
    {
        $key = mb_strtoupper(trim((string) $this->formaCadastro->descricao), 'UTF-8');

        return collect([$key => $this->formaCadastro]);
    }
}
