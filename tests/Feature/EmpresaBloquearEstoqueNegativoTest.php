<?php

namespace Tests\Feature;

use App\Filament\Resources\EmpresaResource\Pages\EditEmpresa;
use App\Models\Empresa;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class EmpresaBloquearEstoqueNegativoTest extends TestCase
{
    use DatabaseTransactions;

    protected function actingAsErpAdmin(): User
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA ESTOQUE TESTE',
            'fantasia' => 'EMPRESA ESTOQUE TESTE',
            'razao_social' => 'EMPRESA ESTOQUE TESTE LTDA',
            'ativo' => true,
            'param_geral_bloquear_estoque_negativo' => false,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $this->actingAs($user);

        return $user;
    }

    public function test_toggle_marca_flag_quando_nao_ha_estoque_negativo(): void
    {
        $this->actingAsErpAdmin();
        $empresaId = (int) session('erp_empresa_id');

        Product::query()->where('estoque', '<', 0)->update(['estoque' => 0]);

        Livewire::test(EditEmpresa::class, ['record' => $empresaId])
            ->assertSet('data.param_geral_bloquear_estoque_negativo', false)
            ->call('toggleBloquearEstoqueNegativo')
            ->assertSet('data.param_geral_bloquear_estoque_negativo', true)
            ->assertSet('zerarEstoqueNegativoModalOpen', false);
    }

    public function test_toggle_desmarca_flag(): void
    {
        $this->actingAsErpAdmin();
        $empresaId = (int) session('erp_empresa_id');

        Livewire::test(EditEmpresa::class, ['record' => $empresaId])
            ->set('data.param_geral_bloquear_estoque_negativo', true)
            ->call('toggleBloquearEstoqueNegativo')
            ->assertSet('data.param_geral_bloquear_estoque_negativo', false);
    }

    public function test_toggle_abre_modal_quando_ha_estoque_negativo(): void
    {
        $this->actingAsErpAdmin();
        $empresaId = (int) session('erp_empresa_id');

        Product::query()->create([
            'codigo' => 'NEG001',
            'descricao' => 'PRODUTO NEGATIVO TESTE',
            'preco_venda' => 10,
            'preco_custo' => 5,
            'estoque' => -1,
            'ativo' => true,
        ]);

        Livewire::test(EditEmpresa::class, ['record' => $empresaId])
            ->assertSet('data.param_geral_bloquear_estoque_negativo', false)
            ->call('toggleBloquearEstoqueNegativo')
            ->assertSet('data.param_geral_bloquear_estoque_negativo', false)
            ->assertSet('zerarEstoqueNegativoModalOpen', true)
            ->assertSet('zerarEstoqueNegativoModalCount', 1);
    }
}
