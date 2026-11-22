<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\Fiscal\NfceContingenciaQrRecuperacaoService;
use Tests\TestCase;

/**
 * Recuperação de QR em contingência: elegibilidade e trava de assinatura.
 */
class NfceContingenciaQrRecuperacaoServiceTest extends TestCase
{
    public function test_autorizada_nao_e_elegivel(): void
    {
        $svc = new NfceContingenciaQrRecuperacaoService();
        $nfce = new PdvVendaNfce([
            'id' => 1,
            'numero' => 1,
            'serie' => '1',
            'chave' => '42261153782352000102650010000167149000005027',
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'simulada' => false,
            'xml' => '<NFe></NFe>',
        ]);

        $r = $svc->corrigirUma(
            $nfce,
            new Empresa(['id' => 1]),
            new VendasParametro(['empresa_id' => 1]),
            sys_get_temp_dir(),
            aplicar: false,
        );

        $this->assertFalse($r['ok']);
        $this->assertFalse($r['pronto_transmitir']);
        $this->assertStringContainsString('autorizada', mb_strtolower($r['mensagem']));
    }

    public function test_chave_tpemis_1_nao_e_elegivel(): void
    {
        $svc = new NfceContingenciaQrRecuperacaoService();
        // tpEmis na posição 34 = '1'
        $chave = '42261153782352000102650010000167141000005021';
        $nfce = new PdvVendaNfce([
            'id' => 2,
            'numero' => 16714,
            'serie' => '1',
            'chave' => $chave,
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'simulada' => false,
            'xml' => '<NFe><infNFe Id="NFe'.$chave.'"><ide><tpEmis>1</tpEmis></ide></infNFe></NFe>',
        ]);

        $r = $svc->corrigirUma(
            $nfce,
            new Empresa(['id' => 1]),
            new VendasParametro(['empresa_id' => 1]),
            sys_get_temp_dir(),
            aplicar: false,
        );

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('tpEmis', $r['mensagem']);
    }

    public function test_wiring_servico_existe_e_preserva_trava(): void
    {
        $src = file_get_contents(app_path('Support/Fiscal/NfceContingenciaQrRecuperacaoService.php'));
        $trava = file_get_contents(app_path('Support/Fiscal/NfceContingenciaConsistencia.php'));

        $this->assertIsString($src);
        $this->assertStringContainsString('NfceContingenciaConsistencia::motivo', $src);
        $this->assertStringContainsString('assertSignatureIsValid', $src);
        $this->assertStringContainsString('pronto_transmitir', $src);
        $this->assertStringContainsString('consultarChave', $src);
        $this->assertStringNotContainsString('autorizarNfceAssinada', $src);

        $this->assertIsString($trava);
        $this->assertStringContainsString('o QR Code gravado não corresponde à chave', $trava);
    }
}
