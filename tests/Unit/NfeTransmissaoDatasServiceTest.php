<?php

namespace Tests\Unit;

use App\Models\Nfe;
use App\Support\Erp\ErpTimezone;
use App\Support\Fiscal\NfeTransmissaoDatasService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Valida o cálculo de datas da 1ª transmissão sem migrar/gravar no banco.
 */
class NfeTransmissaoDatasServiceTest extends TestCase
{
    private NfeTransmissaoDatasService $svc;

    private Carbon $agora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new NfeTransmissaoDatasService();
        $this->agora = Carbon::parse('2026-10-21 15:42:17', ErpTimezone::DEFAULT);
    }

    public function test_primeira_transmissao_atualiza_emissao_e_saida_para_hoje(): void
    {
        $nfe = $this->nfe([
            'data_emissao' => '2026-10-19',
            'hora_emissao' => '08:30:00',
            'data_saida' => '2026-10-19',
            'hora_saida' => '08:30:00',
        ]);

        $attrs = $this->svc->calcularAttrs($nfe, $this->agora);

        $this->assertSame([
            'data_emissao' => '2026-10-21',
            'hora_emissao' => '15:42:17',
            'data_saida' => '2026-10-21',
            'hora_saida' => '15:42:17',
        ], $attrs);
    }

    public function test_mesma_data_ainda_atualiza_hora_para_agora(): void
    {
        $nfe = $this->nfe([
            'data_emissao' => '2026-10-21',
            'hora_emissao' => '01:00:00',
            'data_saida' => '2026-10-21',
            'hora_saida' => '01:00:00',
        ]);

        $attrs = $this->svc->calcularAttrs($nfe, $this->agora);

        $this->assertSame('2026-10-21', $attrs['data_emissao'] ?? null);
        $this->assertSame('15:42:17', $attrs['hora_emissao'] ?? null);
        $this->assertSame('2026-10-21', $attrs['data_saida'] ?? null);
        $this->assertSame('15:42:17', $attrs['hora_saida'] ?? null);
    }

    public function test_preserva_saida_programada_para_data_futura(): void
    {
        $nfe = $this->nfe([
            'data_emissao' => '2026-10-19',
            'hora_emissao' => '09:00:00',
            'data_saida' => '2026-10-23',
            'hora_saida' => '14:00:00',
        ]);

        $attrs = $this->svc->calcularAttrs($nfe, $this->agora);

        $this->assertSame('2026-10-21', $attrs['data_emissao'] ?? null);
        $this->assertSame('15:42:17', $attrs['hora_emissao'] ?? null);
        $this->assertArrayNotHasKey('data_saida', $attrs ?? []);
        $this->assertArrayNotHasKey('hora_saida', $attrs ?? []);
    }

    public function test_saida_programada_no_passado_e_realinhada_com_emissao(): void
    {
        $nfe = $this->nfe([
            'data_emissao' => '2026-10-18',
            'data_saida' => '2026-10-19',
            'hora_emissao' => '10:00:00',
            'hora_saida' => '16:00:00',
        ]);

        $attrs = $this->svc->calcularAttrs($nfe, $this->agora);

        $this->assertSame([
            'data_emissao' => '2026-10-21',
            'hora_emissao' => '15:42:17',
            'data_saida' => '2026-10-21',
            'hora_saida' => '15:42:17',
        ], $attrs);
    }

    public function test_nao_altera_quando_ja_tem_chave_retransmissao(): void
    {
        $nfe = $this->nfe([
            'data_emissao' => '2026-10-19',
            'chave' => str_repeat('1', 44),
        ]);

        $this->assertFalse($this->svc->deveAtualizar($nfe));
        $this->assertNull($this->svc->calcularAttrs($nfe, $this->agora));
    }

    public function test_nao_altera_quando_tem_protocolo_ou_xml(): void
    {
        $comProtocolo = $this->nfe(['protocolo' => '123456']);
        $comXml = $this->nfe(['xml' => '<NFe/>']);
        $transmitida = $this->nfe(['status' => Nfe::STATUS_TRANSMITIDA]);

        $this->assertFalse($this->svc->deveAtualizar($comProtocolo));
        $this->assertFalse($this->svc->deveAtualizar($comXml));
        $this->assertFalse($this->svc->deveAtualizar($transmitida));
    }

    public function test_nao_altera_modelo_diferente_de_55(): void
    {
        $nfe = $this->nfe(['modelo' => '65']);

        $this->assertFalse($this->svc->deveAtualizar($nfe));
        $this->assertNull($this->svc->calcularAttrs($nfe, $this->agora));
    }

    public function test_nao_altera_contingencia_tpemis(): void
    {
        $nfe = $this->nfe(['tipo_emissao' => '9']);

        $this->assertFalse($this->svc->deveAtualizar($nfe));
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function nfe(array $attrs = []): Nfe
    {
        $nfe = new Nfe();
        $nfe->forceFill(array_merge([
            'modelo' => '55',
            'status' => Nfe::STATUS_ABERTA,
            'situacao' => Nfe::SITUACAO_ABERTA,
            'tipo_emissao' => '1',
            'data_emissao' => '2026-10-19',
            'data_saida' => '2026-10-19',
            'hora_emissao' => null,
            'hora_saida' => null,
            'chave' => null,
            'xml' => null,
            'protocolo' => null,
        ], $attrs));

        return $nfe;
    }
}
