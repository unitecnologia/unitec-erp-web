<?php

namespace Tests\Unit;

use App\Models\FiscalIbptItem;
use App\Models\Product;
use App\Support\Erp\Fiscal\IbptLookupService;
use App\Support\Erp\Fiscal\IbptTabelaPadrao;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class IbptTabelaPadraoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_tabela_vazia_recebe_ibpt_padrao_e_calcula_tributos(): void
    {
        FiscalIbptItem::query()->delete();

        $total = IbptTabelaPadrao::carregarSeVazia();

        $this->assertGreaterThan(1000, $total);
        $this->assertSame($total, FiscalIbptItem::query()->count());

        $produto = new Product(['ncm' => '40169990', 'origem' => 0]);
        $ibpt = app(IbptLookupService::class)->calcularParaProduto($produto, 100.0);

        $this->assertTrue($ibpt['encontrado']);
        $this->assertGreaterThan(0, $ibpt['v_tot_trib']);
    }

    public function test_nao_sobrescreve_tabela_importada_pelo_cliente(): void
    {
        FiscalIbptItem::query()->delete();
        FiscalIbptItem::query()->create([
            'ncm' => '99999999',
            'descricao' => 'IMPORTADA PELO CLIENTE',
            'aliq_nacional' => 1,
            'aliq_importado' => 1,
            'aliq_estadual' => 1,
            'aliq_municipal' => 0,
            'versao' => 'CLIENTE',
        ]);

        $this->assertSame(0, IbptTabelaPadrao::carregarSeVazia());
        $this->assertSame(1, FiscalIbptItem::query()->count());
        $this->assertSame('CLIENTE', FiscalIbptItem::query()->value('versao'));
    }
}
