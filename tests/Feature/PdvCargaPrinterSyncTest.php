<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Terminal;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PdvCargaPrinterSyncTest extends TestCase
{
    use DatabaseTransactions;

    public function test_carga_atualiza_impressora_do_pdv_offline(): void
    {
        $suffix = (string) random_int(100000, 999999);

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA TESTE '.$suffix,
            'ativo' => true,
        ]);

        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV'.$suffix,
            'numero_logico_terminal' => (int) $suffix,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
            'tipo_impressora' => '0',
            'nvias' => 1,
            'modelo' => 'ELGIN',
            'porta' => 'COM2',
            'impressora_nome' => null,
        ]);

        $this->getJson('/api/v1/pdv/licenca?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => 'PDV'.$suffix,
            'tipo_impressora' => '1',
            'nvias' => 2,
            'modelo' => 'EPSON',
            'porta' => 'RAW:POS-80C',
            'impressora_nome' => 'POS-80C',
        ]))->assertOk();

        $terminal->refresh();
        $this->assertSame('1', (string) $terminal->tipo_impressora);
        $this->assertSame(2, (int) $terminal->nvias);
        $this->assertSame('EPSON', $terminal->modelo);
        $this->assertSame('RAW:POS-80C', $terminal->porta);
        $this->assertSame('POS-80C', $terminal->impressora_nome);
    }

    public function test_carga_sem_tipo_impressora_nao_altera_cadastro(): void
    {
        $suffix = (string) random_int(100000, 999999);

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA TESTE '.$suffix,
            'ativo' => true,
        ]);

        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV'.$suffix,
            'numero_logico_terminal' => (int) $suffix,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
            'tipo_impressora' => '0',
            'porta' => 'COM2',
        ]);

        $this->getJson('/api/v1/pdv/licenca?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => 'PDV'.$suffix,
        ]))->assertOk();

        $terminal->refresh();
        $this->assertSame('0', (string) $terminal->tipo_impressora);
        $this->assertSame('COM2', $terminal->porta);
    }

    public function test_carga_aceita_pedido_a4_tipo_zero(): void
    {
        $suffix = (string) random_int(100000, 999999);

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA TESTE '.$suffix,
            'ativo' => true,
        ]);

        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV'.$suffix,
            'numero_logico_terminal' => (int) $suffix,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
            'tipo_impressora' => '1',
            'porta' => 'COM1',
        ]);

        $this->getJson('/api/v1/pdv/licenca?'.http_build_query([
            'empresa_id' => $empresa->id,
            'terminal' => 'PDV'.$suffix,
            'tipo_impressora' => '0',
            'nvias' => 1,
            'modelo' => 'ELGIN',
            'porta' => 'RAW:LaserA4',
        ]))->assertOk();

        $terminal->refresh();
        $this->assertSame('0', (string) $terminal->tipo_impressora);
        $this->assertSame('RAW:LaserA4', $terminal->porta);
        $this->assertSame('LaserA4', $terminal->impressora_nome);
    }
}
