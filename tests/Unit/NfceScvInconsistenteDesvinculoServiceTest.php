<?php

namespace Tests\Unit;

use App\Models\PdvVendaNfce;
use App\Support\Erp\Nfce\NfceScvInconsistenteDesvinculoService;
use ReflectionMethod;
use Tests\TestCase;

final class NfceScvInconsistenteDesvinculoServiceTest extends TestCase
{
    public function test_bloqueia_retransmissao_quando_operacao_arquivada(): void
    {
        $nfce = new PdvVendaNfce([
            'operacao' => NfceScvInconsistenteDesvinculoService::OPERACAO_ARQUIVADA,
            'status' => PdvVendaNfce::STATUS_REJEITADA,
        ]);

        $msg = NfceScvInconsistenteDesvinculoService::bloqueiaRetransmissao($nfce);

        $this->assertNotNull($msg);
        $this->assertStringContainsString('XML inconsistente importado do SCV', $msg);
    }

    public function test_nao_bloqueia_retransmissao_operacao_normal(): void
    {
        $nfce = new PdvVendaNfce([
            'operacao' => 'migracao_scv_hist',
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
        ]);

        $this->assertNull(NfceScvInconsistenteDesvinculoService::bloqueiaRetransmissao($nfce));
    }

    public function test_com_protocolo_nao_e_elegivel(): void
    {
        $nfce = new PdvVendaNfce([
            'operacao' => 'migracao_scv_hist',
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'protocolo' => '123456789012345',
            'chave' => '42261153782352000102650010000163629000006649',
            'serie' => '1',
            'numero' => 16362,
            'xml' => '<nfeProc><NFe><infNFe Id="NFe42261153782352000102650010000163629000006649"><ide><tpEmis>9</tpEmis></ide></infNFe></NFe><protNFe><infProt><nProt>1</nProt></infProt></protNFe></nfeProc>',
            'qr_code_conteudo' => '',
        ]);

        $resultado = $this->avaliar($nfce);

        $this->assertFalse($resultado['elegivel']);
        $this->assertStringContainsString('protocolo', mb_strtolower($resultado['motivo']));
    }

    public function test_contingencia_scv_inconsistente_com_cstat_217_e_elegivel(): void
    {
        $chave = '42261153782352000102650010000163629000006649';
        $nfce = new PdvVendaNfce([
            'operacao' => 'migracao_scv_hist',
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'protocolo' => null,
            'chave' => $chave,
            'serie' => '1',
            'numero' => 16362,
            'cnf' => '00000664',
            'xml' => '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe'.$chave.'"><ide><tpEmis>9</tpEmis></ide></infNFe></NFe>',
            'qr_code_conteudo' => '',
            'motivo_rejeicao' => 'Ainda não consta na SEFAZ (cStat 217).',
        ]);

        $resultado = $this->avaliar($nfce);

        $this->assertTrue($resultado['elegivel'], $resultado['motivo']);
        $this->assertStringContainsString(NfceScvInconsistenteDesvinculoService::MOTIVO, $resultado['motivo']);
    }

    public function test_inconsistencia_local_sem_prova_sefaz_nao_e_elegivel(): void
    {
        $chave9 = '42261108271081000176650010000087289000001902';
        $chave1 = '42261108271081000176650010000087281000001907';
        $nfce = new PdvVendaNfce([
            'operacao' => 'migracao_scv_hist',
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'protocolo' => null,
            'chave' => $chave9,
            'serie' => '1',
            'numero' => 8728,
            'cnf' => '00000190',
            'xml' => '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe'.$chave9.'"><ide><tpEmis>9</tpEmis></ide>'
                .'<infNFeSupl><qrCode>https://sat.sef.sc.gov.br/nfce/consulta?p='.$chave1.'|2|1|1|ABC</qrCode></infNFeSupl>'
                .'</infNFe></NFe>',
            'qr_code_conteudo' => '',
            'motivo_rejeicao' => null,
        ]);

        $resultado = $this->avaliar($nfce);

        $this->assertFalse($resultado['elegivel']);
        $this->assertStringContainsString('preservado', mb_strtolower($resultado['motivo']));
        $this->assertTrue($resultado['evidencias']['divergencia_qr_xml']['divergente']);
    }

    public function test_autorizacao_responsavel_libera_inconsistente_sem_sefaz(): void
    {
        $chave9 = '42261108271081000176650010000087289000001902';
        $chave1 = '42261108271081000176650010000087281000001907';
        $nfce = new PdvVendaNfce([
            'operacao' => 'migracao_scv_hist',
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'protocolo' => null,
            'chave' => $chave9,
            'serie' => '1',
            'numero' => 8728,
            'cnf' => '00000190',
            'xml' => '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe'.$chave9.'"><ide><tpEmis>9</tpEmis></ide>'
                .'<infNFeSupl><qrCode>https://sat.sef.sc.gov.br/nfce/consulta?p='.$chave1.'|2|1|1|ABC</qrCode></infNFeSupl>'
                .'</infNFe></NFe>',
            'qr_code_conteudo' => '',
            'motivo_rejeicao' => null,
        ]);

        $resultado = $this->avaliar($nfce, autorizacaoResponsavel: true);

        $this->assertTrue($resultado['elegivel'], $resultado['motivo']);
        $this->assertStringContainsString('autorização do responsável', mb_strtolower($resultado['motivo']));
    }

    /**
     * @return array{elegivel: bool, motivo: string, evidencias: array<string, mixed>}
     */
    private function avaliar(PdvVendaNfce $nfce, bool $autorizacaoResponsavel = false): array
    {
        $service = new NfceScvInconsistenteDesvinculoService;
        $method = new ReflectionMethod($service, 'avaliarElegibilidade');
        $method->setAccessible(true);

        /** @var array{elegivel: bool, motivo: string, evidencias: array<string, mixed>} $resultado */
        $resultado = $method->invoke($service, $nfce, $autorizacaoResponsavel);

        return $resultado;
    }
}
