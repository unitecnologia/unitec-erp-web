<?php

namespace Tests\Feature;

use App\Filament\Pages\PdvPage;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMenu;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvErpPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PdvErpRetaguardaFlagTest extends TestCase
{
    use DatabaseTransactions;

    public function test_parametro_nasce_marcado_por_padrao(): void
    {
        $this->assertTrue(EmpresaParametros::permissionFields()['param_geral_usar_pdv_erp']['default']);
        $this->assertTrue(EmpresaParametros::defaultFormValues()['param_geral_usar_pdv_erp']);

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV PADRAO',
            'ativo' => true,
        ]);

        $this->assertTrue((bool) $empresa->fresh()->param_geral_usar_pdv_erp);
        $this->assertTrue(PdvErpPolicy::habilitado($empresa->fresh()));
    }

    public function test_valor_nulo_permanece_habilitado(): void
    {
        $empresa = new Empresa(['nome' => 'SEM COLUNA']);

        $this->assertTrue(PdvErpPolicy::habilitado($empresa));
        $this->assertTrue(PdvErpPolicy::habilitado(null));
    }

    public function test_flag_desmarcada_esconde_icone_menu_e_bloqueia_pagina(): void
    {
        $this->actingAsErpAdmin(usarPdvErp: false);

        $this->assertFalse(PdvErpPolicy::habilitado());
        $this->assertFalse($this->shortcutHasPdv());
        $this->assertFalse($this->vendasMenuHasPdv());
        $this->assertFalse(PdvPage::canAccess());
        $this->assertFalse(PdvConfig::make(ErpContext::currentEmpresa())->usarPdvRetaguarda());
    }

    public function test_flag_marcada_libera_pdv_na_retaguarda_inclusive_admin(): void
    {
        $this->actingAsErpAdmin(usarPdvErp: true);

        $this->assertTrue(PdvErpPolicy::habilitado());
        $this->assertTrue($this->shortcutHasPdv());
        $this->assertTrue($this->vendasMenuHasPdv());
        $this->assertTrue(PdvPage::canAccess());
        $this->assertTrue(PdvConfig::make(ErpContext::currentEmpresa())->usarPdvRetaguarda());
    }

    protected function actingAsErpAdmin(bool $usarPdvErp): User
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV ERP TESTE',
            'fantasia' => 'EMPRESA PDV ERP TESTE',
            'razao_social' => 'EMPRESA PDV ERP TESTE LTDA',
            'ativo' => true,
            'param_geral_usar_pdv_erp' => $usarPdvErp,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();

        $this->actingAs($user);

        return $user;
    }

    protected function shortcutHasPdv(): bool
    {
        foreach (ErpMenu::shortcuts() as $item) {
            if (($item['key'] ?? '') === 'pdv') {
                return true;
            }
        }

        return false;
    }

    protected function vendasMenuHasPdv(): bool
    {
        foreach (ErpMenu::allMenus() as $menu) {
            if (($menu['label'] ?? '') !== 'Vendas') {
                continue;
            }

            foreach ($menu['items'] ?? [] as $item) {
                if (($item['label'] ?? '') === 'PDV') {
                    return true;
                }
            }
        }

        return false;
    }
}
