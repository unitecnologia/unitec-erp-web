<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Terminal;
use App\Support\Erp\Pdv\TerminalResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class TerminalResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_resolves_or_creates_terminal_by_machine_name(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $resolver = TerminalResolver::make();
        $machineName = $resolver->resolveMachineName();

        $first = $resolver->resolveOrCreateDefault($empresa->id);
        $second = $resolver->resolveOrCreateDefault($empresa->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($machineName, $first->nome);
        $this->assertSame($empresa->id, $first->empresa_id);
        $this->assertSame(1, Terminal::query()
            ->where('empresa_id', $empresa->id)
            ->where('nome', $machineName)
            ->count());
    }

    public function test_it_remembers_terminal_in_session(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        $terminal = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'CAIXA-TEST',
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        TerminalResolver::make()->remember($terminal);

        $this->assertSame($terminal->id, session('erp.terminal_id'));
        $this->assertSame('CAIXA-TEST', session('erp.terminal_nome'));
        $this->assertSame($terminal->id, TerminalResolver::make()->current()?->id);
    }

    public function test_it_forgets_terminal_session(): void
    {
        session([
            'erp.terminal_id' => 99,
            'erp.terminal_nome' => 'X',
        ]);

        TerminalResolver::make()->forget();

        $this->assertNull(session('erp.terminal_id'));
        $this->assertNull(session('erp.terminal_nome'));
    }

    public function test_it_updates_ip_on_touch(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        $terminal = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'CAIXA-IP',
            'ip' => '10.0.0.1',
        ]);

        $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.50',
        ]));

        $updated = TerminalResolver::make()->touchMachineMetadata($terminal);

        $this->assertSame('192.168.1.50', $updated->fresh()->ip);
    }
}
