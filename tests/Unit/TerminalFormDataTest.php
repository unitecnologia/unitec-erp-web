<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\Terminais\TerminalFormOptions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\TerminalFormMergerHarness;
use Tests\TestCase;

class TerminalFormDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_persists_operacao_and_preview_in_impressora_extra(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $merged = (new TerminalFormMergerHarness)->merge([
            'nome' => 'CAIXA-1',
            'tipo_operacao_padrao' => 'nfce_transmitir',
            'preview_impressao' => true,
            'tipo_fechamento' => '0',
        ]);

        $this->assertSame($empresa->id, $merged['empresa_id']);
        $this->assertSame('CAIXA-1', $merged['nome']);
        $this->assertArrayNotHasKey('tipo_operacao_padrao', $merged);
        $this->assertArrayNotHasKey('preview_impressao', $merged);
        $this->assertSame([
            'tipo_operacao_padrao' => 'NFCE_TRANSMITIR',
            'preview_impressao' => true,
        ], $merged['impressora_extra']);
    }

    public function test_default_terminal_form_data_uses_pedido_a4(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $defaults = (new TerminalFormMergerHarness)->defaultFormData();

        $this->assertSame('0', $defaults['tipo_impressora']);
        $this->assertSame($empresa->id, $defaults['empresa_id']);
        $this->assertTrue($defaults['exibe_f3']);
        $this->assertTrue($defaults['pdv']);
    }

    public function test_it_normalizes_legacy_tipo_operacao_padrao_values(): void
    {
        $this->assertSame('nfce_transmitir', TerminalFormOptions::normalizeTipoOperacaoPadrao('NFCE'));
        $this->assertSame('pedido_nao_fiscal', TerminalFormOptions::normalizeTipoOperacaoPadrao('ORCAMENTO'));
        $this->assertSame('pedido_nao_fiscal', TerminalFormOptions::normalizeTipoOperacaoPadrao('ECF_FISCAL_FINALIZAR'));
    }

    public function test_pdv_offline_merge_does_not_write_printer_fields(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $harness = new TerminalFormMergerHarness;
        $harness->data = [
            'nome' => 'PDV1',
            'origens_dispositivo' => ['pdv_offline'],
        ];

        $merged = $harness->merge([
            'nome' => 'PDV1',
            'origens_dispositivo' => ['pdv_offline'],
            'tipo_impressora' => '0',
            'nvias' => 9,
            'modelo' => 'ELGIN',
            'porta' => 'COM2',
            'impressora_nome' => 'COM2',
        ]);

        $this->assertArrayNotHasKey('tipo_impressora', $merged);
        $this->assertArrayNotHasKey('nvias', $merged);
        $this->assertArrayNotHasKey('modelo', $merged);
        $this->assertArrayNotHasKey('porta', $merged);
        $this->assertArrayNotHasKey('impressora_nome', $merged);
        $this->assertSame('PDV1', $merged['nome']);
    }

    public function test_update_merge_does_not_write_device_identity(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $harness = new TerminalFormMergerHarness;
        $harness->isNewTerminal = false;
        $harness->data = [
            'nome' => 'PDV1',
            'origens_dispositivo' => ['pdv_offline'],
        ];

        $merged = $harness->merge([
            'nome' => 'PDV1',
            'device_uuid' => 'aeace488-e29d-4773-8436-d85106d857a0',
            'origens_dispositivo' => ['PDV_OFFLINE'],
            'categoria_licenca' => 'computador',
            'device_platform' => 'pdv-offline',
            'tipo_operacao_padrao' => 'modo_hibrido',
        ]);

        $this->assertArrayNotHasKey('device_uuid', $merged);
        $this->assertArrayNotHasKey('origens_dispositivo', $merged);
        $this->assertArrayNotHasKey('categoria_licenca', $merged);
        $this->assertArrayNotHasKey('device_platform', $merged);
        $this->assertSame('MODO_HIBRIDO', $merged['impressora_extra']['tipo_operacao_padrao'] ?? null);

        $uuid = 'aeace488-e29d-4773-8436-d85106d857a0';
        $terminal = \App\Models\Terminal::query()->create([
            ...\App\Models\Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'device_uuid' => $uuid,
            'origens_dispositivo' => ['pdv_offline'],
            'categoria_licenca' => 'computador',
        ]);

        $terminal->fill($merged);
        $terminal->save();

        $fresh = $terminal->fresh();
        $this->assertSame($uuid, $fresh->device_uuid);
        $this->assertContains('pdv_offline', $fresh->origens_dispositivo ?? []);
    }

    public function test_erp_web_merge_keeps_printer_fields(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'Empresa Teste',
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $harness = new TerminalFormMergerHarness;
        $harness->data = [
            'nome' => 'ERP',
            'origens_dispositivo' => ['erp_web'],
        ];

        $merged = $harness->merge([
            'nome' => 'ERP',
            'origens_dispositivo' => ['erp_web'],
            'tipo_impressora' => '1',
            'nvias' => 2,
            'modelo' => 'EPSON',
            'porta' => 'RAW:POS-80C',
        ]);

        $this->assertSame('1', $merged['tipo_impressora']);
        $this->assertSame(2, (int) $merged['nvias']);
        $this->assertSame('EPSON', $merged['modelo']);
        $this->assertSame('RAW:POS-80C', $merged['porta']);
        $this->assertSame('POS-80C', $merged['impressora_nome']);
    }
}
