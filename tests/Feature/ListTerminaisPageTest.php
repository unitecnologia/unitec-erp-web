<?php

namespace Tests\Feature;

use App\Filament\Resources\TerminalResource\Pages\ListTerminais;
use App\Models\Empresa;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ListTerminaisPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function actingAsErpUser(): User
    {
        $empresa = Empresa::query()->create([
            'nome' => 'UNITECHNOLOGIA SISTEMAS',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $this->actingAs($user);

        return $user;
    }

    public function test_it_saves_terminal_form_and_updates_session(): void
    {
        $this->actingAsErpUser();

        $empresaId = (int) session('erp_empresa_id');

        $terminal = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresaId),
            'empresa_id' => $empresaId,
            'nome' => 'DESKTOP-TEST',
            'eh_caixa' => false,
        ]);

        Livewire::test(ListTerminais::class)
            ->call('selectTerminalRecord', $terminal->id)
            ->set('data.eh_caixa', true)
            ->set('data.exibe_f5', false)
            ->set('data.tipo_operacao_padrao', 'modo_hibrido')
            ->call('saveTerminalForm')
            ->assertNotified();

        $fresh = $terminal->fresh();

        $this->assertTrue($fresh->eh_caixa);
        $this->assertFalse($fresh->exibe_f5);
        $this->assertSame('MODO_HIBRIDO', $fresh->impressora_extra['tipo_operacao_padrao'] ?? null);
        $this->assertSame($terminal->id, session('erp.terminal_id'));
    }

    public function test_it_deletes_terminal_and_selects_next(): void
    {
        $this->actingAsErpUser();

        $empresaId = (int) session('erp_empresa_id');

        $first = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresaId),
            'empresa_id' => $empresaId,
            'nome' => 'TERMINAL-A',
        ]);

        $second = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresaId),
            'empresa_id' => $empresaId,
            'nome' => 'TERMINAL-B',
        ]);

        session(['erp.terminal_id' => $first->id]);

        Livewire::test(ListTerminais::class)
            ->call('selectTerminalRecord', $first->id)
            ->call('deleteTerminal')
            ->assertNotified();

        $this->assertDatabaseMissing('terminais', ['id' => $first->id]);
        $this->assertDatabaseHas('terminais', ['id' => $second->id]);
        $this->assertNull(session('erp.terminal_id'));
    }

    public function test_it_switches_tabs(): void
    {
        $this->actingAsErpUser();

        Livewire::test(ListTerminais::class)
            ->assertSet('activeTerminalTab', 'configuracoes')
            ->call('selectTerminalTab', 'balanca')
            ->assertSet('activeTerminalTab', 'balanca')
            ->call('selectTerminalTab', 'invalid')
            ->assertSet('activeTerminalTab', 'balanca');
    }
}
