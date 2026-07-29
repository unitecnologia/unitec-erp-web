<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\Erp\Balanca\BalancaEtiquetaLayout;
use App\Support\Erp\Balanca\BalancaExportService;
use App\Support\Erp\Balanca\BalancaModel;
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
            'prefixo_balanca' => '123',
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
            'prefixo_balanca' => '45',
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
        $this->assertSame(['TXITENS.TXT'], BalancaModel::filenames(BalancaModel::TOLEDO_MGV5));
        $this->assertSame(['ITENSMGV.TXT'], BalancaModel::filenames(BalancaModel::TOLEDO_MGV7));
    }

    public function test_etiqueta_layout_defaults(): void
    {
        $this->assertSame(4, BalancaEtiquetaLayout::digitosForModelo(1));
        $this->assertSame(5, BalancaEtiquetaLayout::digitosForModelo(2));
        $this->assertSame(5, BalancaEtiquetaLayout::digitosForModelo(3));
        $this->assertSame(6, BalancaEtiquetaLayout::digitosForModelo(4));
        $this->assertTrue(BalancaEtiquetaLayout::isTotalPrice(3));
        $this->assertFalse(BalancaEtiquetaLayout::isTotalPrice(4));
        $this->assertSame(7, BalancaEtiquetaLayout::productKeyLength('2', 6));
        $this->assertCount(4, BalancaEtiquetaLayout::diagrams());
    }

    public function test_scale_code_respects_digitos_with_file_pad(): void
    {
        $service = new BalancaExportService;
        $product = $this->fakeProduct([
            'prefixo_balanca' => '45',
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

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function fakeProduct(array $attrs): Product
    {
        $product = new Product;
        foreach ($attrs as $key => $value) {
            $product->{$key} = $value;
        }

        return $product;
    }
}
