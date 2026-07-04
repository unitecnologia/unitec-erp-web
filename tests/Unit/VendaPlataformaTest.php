<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\Venda;
use Tests\TestCase;

class VendaPlataformaTest extends TestCase
{
    public function test_plataforma_efetiva_usa_vinculo_forca_vendas_quando_campo_esta_erp(): void
    {
        $venda = new Venda([
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);
        $venda->setRelation('forcaVendasOrder', new ForcaVendasOrder());

        $this->assertSame(Venda::PLATAFORMA_MOBILE, $venda->plataformaEfetiva());
        $this->assertSame('Mobile', $venda->plataformaLabel());
    }

    public function test_plataforma_efetiva_usa_vinculo_pdv(): void
    {
        $venda = new Venda([
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);
        $venda->setRelation('pdvVenda', new PdvVenda());

        $this->assertSame(Venda::PLATAFORMA_PDV, $venda->plataformaEfetiva());
        $this->assertSame('PDV', $venda->plataformaLabel());
    }

    public function test_plataforma_efetiva_mantem_erp_sem_vinculos(): void
    {
        $venda = new Venda([
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);
        $venda->setRelation('forcaVendasOrder', null);
        $venda->setRelation('pdvVenda', null);

        $this->assertSame(Venda::PLATAFORMA_ERP, $venda->plataformaEfetiva());
        $this->assertSame('ERP', $venda->plataformaLabel());
    }
}
