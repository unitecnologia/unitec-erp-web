<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\Erp\Balanca\BalancaEtiquetaLayout;
use App\Support\Erp\Balanca\BalancaExportService;
use App\Support\Erp\Balanca\BalancaModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class BalancaExportServiceTest extends TestCase
{
    public function test_model_options_match_delphi_list(): void
    {
        $keys = array_keys(BalancaModel::options());

        $this->assertSame([
            'modFilizola',
            'modToledo',
            'modUrano',
            'modUranoS',
            'modToledoMGV5',
            'modToledoMGV6',
            'modToledoMGV7',
            'modUranoURF32',
        ], $keys);
    }

    public function test_filizola_cadtxt_layout(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '123',
            'descricao' => 'CUPIM BOVINO',
            'preco_venda' => 22.50,
            'produto_pesado' => true,
            'unidade' => 'KG',
        ]);

        $files = $service->buildFiles(BalancaModel::FILIZOLA, new Collection([$product]));

        $this->assertArrayHasKey('CADTXT.TXT', $files);
        $this->assertArrayHasKey('SETORTXT.TXT', $files);

        $line = rtrim($files['CADTXT.TXT'], "\r\n");
        // 6 codigo + 1 tipo + 22 desc + 7 preco + 3 validade = 39
        $this->assertSame(39, strlen($line));
        $this->assertSame('000123', substr($line, 0, 6));
        $this->assertSame('P', substr($line, 6, 1));
        $this->assertSame('0002250', substr($line, 29, 7));
    }

    public function test_urano_produtos_layout(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '45',
            'descricao' => 'HAMBURGER',
            'preco_venda' => 12.34,
            'produto_pesado' => true,
            'unidade' => 'KG',
        ]);

        $files = $service->buildFiles(BalancaModel::URANO_URF32, new Collection([$product]));
        $line = rtrim($files['Produtos.txt'], "\r\n");

        $this->assertSame('000045', substr($line, 0, 6));
        $this->assertSame('*', substr($line, 6, 1));
        $this->assertSame('0', substr($line, 7, 1));
        $this->assertSame('  0012,34', substr($line, 28, 9));
        $this->assertSame(43, strlen($line)); // 6+1+1+20+9+5+1
    }

    public function test_toledo_mgv_filenames(): void
    {
        $this->assertSame(['TXITENS.TXT'], BalancaModel::filenames(BalancaModel::TOLEDO));
        $this->assertSame(['TXITENS.TXT'], BalancaModel::filenames(BalancaModel::TOLEDO_MGV5));
        $this->assertSame(
            ['ITENSMGV.TXT', 'DEPTO.TXT', 'INFNUTRI.TXT'],
            BalancaModel::filenames(BalancaModel::TOLEDO_MGV6)
        );
        $this->assertSame(
            ['ITENSMGV.TXT', 'DEPTO.TXT', 'INFNUTRI.TXT'],
            BalancaModel::filenames(BalancaModel::TOLEDO_MGV7)
        );
    }

    public function test_toledo_classic_txitens_only_with_fixed_department(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '40',
            'descricao' => 'GOLDEN M.B SALMAO',
            'preco_venda' => 16.00,
            'produto_pesado' => true,
            'unidade' => 'KG',
            'grupo' => 'ACOUGUE',
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO, new Collection([$product]));

        $this->assertSame(['TXITENS.TXT'], array_keys($files));
        $line = rtrim($files['TXITENS.TXT'], "\r\n");
        $this->assertSame('01', substr($line, 0, 2)); // DD fixo — sem DEPTO
        $this->assertSame('00', substr($line, 2, 2));
        $this->assertSame('0', substr($line, 4, 1));
        $this->assertSame('000040', substr($line, 5, 6));
    }

    public function test_toledo_mgv6_itensmgv_matches_real_layout(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '40',
            'descricao' => 'GOLDEN M.B SALMAO',
            'preco_venda' => 16.00,
            'produto_pesado' => true,
            'unidade' => 'KG',
            'validade' => '2026-08-31', // 30 dias → campo 030 da balança
            'codigo_barras' => '7891234567890',
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO_MGV6, new Collection([$product]));

        Carbon::setTestNow();

        $this->assertArrayHasKey('ITENSMGV.TXT', $files);
        $this->assertArrayHasKey('DEPTO.TXT', $files);
        $this->assertArrayHasKey('INFNUTRI.TXT', $files);

        $line = rtrim($files['ITENSMGV.TXT'], "\r\n");
        // V3: 156 (até GL) + || + 88 (D3..MIDIA) = 246
        $this->assertSame(246, strlen($line));
        $this->assertSame('01', substr($line, 0, 2));
        $this->assertSame('2', substr($line, 2, 1)); // EAN-13 por peso (tem codigo_barras)
        $this->assertSame('000040', substr($line, 3, 6));
        $this->assertSame('001600', substr($line, 9, 6));
        $this->assertSame('030', substr($line, 15, 3));
        $this->assertSame(str_pad('GOLDEN M.B SALMAO', 25), substr($line, 18, 25));
        $this->assertSame(str_repeat(' ', 25), substr($line, 43, 25));
        // DV/DE = 1 (imprime validade e embalagem)
        $this->assertSame('11', substr($line, 84, 2));
        $this->assertSame('789123456789', substr($line, 138, 12));
        $this->assertSame('||', substr($line, 156, 2));

        $this->assertSame('01'.str_pad('GERAL', 12), rtrim($files['DEPTO.TXT'], "\r\n"));

        $inf = rtrim($files['INFNUTRI.TXT'], "\r\n");
        $this->assertSame(45, strlen($inf));
        $this->assertSame('N000001', substr($inf, 0, 7));
        // Sem cadastro nutricional → associação 000000 no item
        $this->assertSame('000000', substr($line, 78, 6));
    }

    public function test_toledo_mgv6_infnutri_from_product(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '40',
            'descricao' => 'GOLDEN M.B SALMAO',
            'preco_venda' => 16.00,
            'produto_pesado' => true,
            'unidade' => 'KG',
            'tem_info_nutricional' => true,
            'nutri_porcao_qtd' => 100,
            'nutri_porcao_unidade' => '0',
            'nutri_medida_inteiro' => 1,
            'nutri_medida_fracao' => '0',
            'nutri_medida_tipo' => '21', // filé(s)
            'nutri_valor_energetico' => 250.5,
            'nutri_carboidratos' => 0.0,
            'nutri_proteinas' => 22.3,
            'nutri_gorduras_totais' => 15.1,
            'nutri_gorduras_saturadas' => 4.2,
            'nutri_gorduras_trans' => 0.0,
            'nutri_fibra' => 0.0,
            'nutri_sodio' => 65.0,
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO_MGV6, new Collection([$product]));
        $line = rtrim($files['ITENSMGV.TXT'], "\r\n");
        $inf = rtrim($files['INFNUTRI.TXT'], "\r\n");

        $this->assertSame('000040', substr($line, 78, 6)); // I = PLU
        $this->assertSame(45, strlen($inf));
        $this->assertSame('N000040', substr($inf, 0, 7));
        $this->assertSame('100', substr($inf, 8, 3)); // porção
        $this->assertSame('0', substr($inf, 11, 1)); // g
        $this->assertSame('01', substr($inf, 12, 2)); // medida inteiro
        $this->assertSame('0', substr($inf, 14, 1)); // fração
        $this->assertSame('21', substr($inf, 15, 2)); // filé
        $this->assertSame('2505', substr($inf, 17, 4)); // 250,5 kcal
        $this->assertSame('0000', substr($inf, 21, 4)); // carb
        $this->assertSame('223', substr($inf, 25, 3)); // prot 22,3
        $this->assertSame('151', substr($inf, 28, 3)); // gord 15,1
        $this->assertSame('042', substr($inf, 31, 3)); // sat 4,2
        $this->assertSame('000', substr($inf, 34, 3)); // trans
        $this->assertSame('000', substr($inf, 37, 3)); // fibra
        $this->assertSame('00650', substr($inf, 40, 5)); // sódio 65,0
    }

    public function test_balanca_flag_unit_product_exports_as_unit_type(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '55',
            'descricao' => 'FRANGO INTEIRO',
            'preco_venda' => 25.90,
            'produto_pesado' => true, // flag Balança
            'unidade' => 'UN',
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO_MGV6, new Collection([$product]));
        $line = rtrim($files['ITENSMGV.TXT'], "\r\n");

        $this->assertSame('1', substr($line, 2, 1)); // tipo unidade (sem EAN-13)
        $this->assertSame('000055', substr($line, 3, 6));
        $this->assertSame(str_repeat('0', 12), substr($line, 138, 12));
    }

    public function test_unit_product_with_ean_exports_tipo_5_and_barcode(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '17',
            'descricao' => 'PRODUTO UNIDADE',
            'preco_venda' => 45.50,
            'produto_pesado' => true,
            'unidade' => 'UN',
            'codigo_barras' => '7770000000173',
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO_MGV6, new Collection([$product]));
        $line = rtrim($files['ITENSMGV.TXT'], "\r\n");

        $this->assertSame('5', substr($line, 2, 1)); // EAN-13 por unidade
        $this->assertSame('000017', substr($line, 3, 6));
        $this->assertSame('777000000017', substr($line, 138, 12));
        $this->assertSame('77000000017', substr($line, 102, 11)); // G especial
    }

    public function test_balanca_flag_kg_product_exports_as_weight_type(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '40',
            'descricao' => 'GOLDEN M.B SALMAO',
            'preco_venda' => 16.00,
            'produto_pesado' => true,
            'unidade' => 'KG',
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO_MGV6, new Collection([$product]));
        $line = rtrim($files['ITENSMGV.TXT'], "\r\n");

        $this->assertSame('0', substr($line, 2, 1)); // tipo peso
    }

    public function test_etiqueta_layout_defaults(): void
    {
        $this->assertSame(4, BalancaEtiquetaLayout::digitosForModelo(1));
        $this->assertSame(5, BalancaEtiquetaLayout::digitosForModelo(2));
        $this->assertSame(5, BalancaEtiquetaLayout::digitosForModelo(3));
        $this->assertSame(6, BalancaEtiquetaLayout::digitosForModelo(4));
        $this->assertSame(6, BalancaEtiquetaLayout::digitosForModelo(5));
        $this->assertTrue(BalancaEtiquetaLayout::isTotalPrice(3));
        $this->assertTrue(BalancaEtiquetaLayout::isTotalPrice(5));
        $this->assertFalse(BalancaEtiquetaLayout::isTotalPrice(4));
        $this->assertSame(5, BalancaEtiquetaLayout::valorLength(5));
        $this->assertSame(7, BalancaEtiquetaLayout::productKeyLength('2', 6));
        $this->assertSame('000040', BalancaEtiquetaLayout::productCodeFromBarcode('2000040002215', '2', 6, 5));
        $this->assertSame('000040', BalancaEtiquetaLayout::normalizeProductCode('40', '2', 6));
        $this->assertSame('000040', BalancaEtiquetaLayout::normalizeProductCode('2000040', '2', 6));
        $this->assertCount(5, BalancaEtiquetaLayout::diagrams());
    }

    public function test_scale_code_respects_digitos_with_file_pad(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '45',
            'descricao' => 'TESTE',
            'preco_venda' => 10,
            'produto_pesado' => true,
            'unidade' => 'KG',
        ]);

        $files = $service->buildFiles(BalancaModel::FILIZOLA, new Collection([$product]), null, 4);
        $line = rtrim($files['CADTXT.TXT'], "\r\n");

        // Arquivo Filizola mantém campo de 6; dígitos 4 apenas limitam o código útil.
        $this->assertSame('000045', substr($line, 0, 6));
    }

    public function test_plu_uses_codigo_not_id(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'id' => 999,
            'codigo' => '40',
            'descricao' => 'GOLDEN M.B SALMAO',
            'preco_venda' => 16.00,
            'produto_pesado' => true,
            'unidade' => 'KG',
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO_MGV6, new Collection([$product]));
        $line = rtrim($files['ITENSMGV.TXT'], "\r\n");

        $this->assertSame('000040', substr($line, 3, 6));
        $this->assertNotSame('000999', substr($line, 3, 6));
    }

    public function test_plu_usa_codigo_barras_curto(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'id' => 999,
            'codigo' => '40',
            'codigo_barras' => '207',
            'descricao' => 'FRANGO KG',
            'preco_venda' => 16.00,
            'produto_pesado' => true,
            'unidade' => 'KG',
        ]);

        $files = $service->buildFiles(BalancaModel::FILIZOLA, new Collection([$product]));
        $line = rtrim($files['CADTXT.TXT'], "\r\n");

        $this->assertSame('000207', substr($line, 0, 6));
    }

    public function test_ean_longo_plu_continua_codigo(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'codigo' => '40',
            'codigo_barras' => '7891234567890',
            'descricao' => 'PACOTE FRIGORIFICO',
            'preco_venda' => 16.00,
            'produto_pesado' => true,
            'unidade' => 'KG',
        ]);

        $files = $service->buildFiles(BalancaModel::TOLEDO_MGV6, new Collection([$product]));
        $line = rtrim($files['ITENSMGV.TXT'], "\r\n");

        $this->assertSame('000040', substr($line, 3, 6));
        $this->assertSame('789123456789', substr($line, 138, 12));
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function fakeProduct(array $attrs): Product
    {
        $product = new Product;
        $product->setRawAttributes($attrs, true);

        return $product;
    }
}
