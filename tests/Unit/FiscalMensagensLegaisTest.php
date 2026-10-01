<?php

namespace Tests\Unit;

use App\Support\Erp\Fiscal\FiscalMensagensLegais;
use PHPUnit\Framework\TestCase;

class FiscalMensagensLegaisTest extends TestCase
{
    private FiscalMensagensLegais $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FiscalMensagensLegais;
    }

    public function test_nfe_crt_1_sem_credito_tem_simples_e_nao_tem_aproveitamento_icms(): void
    {
        $mensagens = $this->service->mensagens([
            'modelo' => '55',
            'crt' => 1,
            'p_cred_sn' => 0,
            'v_cred_icms_sn' => 0,
        ]);

        $this->assertContains(FiscalMensagensLegais::SIMPLES_ME_EPP, $mensagens);
        $this->assertContains(FiscalMensagensLegais::SIMPLES_SEM_CREDITO_IPI, $mensagens);
        $this->assertFalse($this->contemAproveitamentoIcms($mensagens));
        $this->assertNotContains(FiscalMensagensLegais::NFCE_SEM_APROVEITAMENTO_ICMS, $mensagens);
    }

    public function test_nfe_crt_1_com_credito_inclui_valor_e_aliquota(): void
    {
        $mensagens = $this->service->mensagens([
            'modelo' => '55',
            'crt' => 1,
            'p_cred_sn' => 1.25,
            'v_cred_icms_sn' => 12.34,
        ]);

        $this->assertContains(FiscalMensagensLegais::SIMPLES_ME_EPP, $mensagens);
        $this->assertContains(FiscalMensagensLegais::SIMPLES_SEM_CREDITO_IPI, $mensagens);

        $credito = null;
        foreach ($mensagens as $mensagem) {
            if (str_contains($mensagem, 'PERMITE O APROVEITAMENTO DO CRÉDITO DE ICMS')) {
                $credito = $mensagem;
                break;
            }
        }

        $this->assertNotNull($credito);
        $this->assertStringContainsString('R$ 12,34', (string) $credito);
        $this->assertStringContainsString('1,25%', (string) $credito);
        $this->assertStringContainsString('ART. 23 DA LEI COMPLEMENTAR Nº 123', (string) $credito);
    }

    public function test_nfce_crt_1_nunca_gera_aproveitamento_e_mantem_proibicao_danfe(): void
    {
        $mensagens = $this->service->mensagens([
            'modelo' => '65',
            'crt' => 1,
            'p_cred_sn' => 3.0,
            'v_cred_icms_sn' => 99.99,
        ]);

        $this->assertContains(FiscalMensagensLegais::SIMPLES_ME_EPP, $mensagens);
        $this->assertContains(FiscalMensagensLegais::SIMPLES_SEM_CREDITO_IPI, $mensagens);
        // Faixa do DANFE é independente do bloco de informações complementares.
        $this->assertNotContains(FiscalMensagensLegais::NFCE_SEM_APROVEITAMENTO_ICMS, $mensagens);
        $this->assertFalse($this->contemAproveitamentoIcms($mensagens));
        $this->assertSame(
            FiscalMensagensLegais::NFCE_SEM_APROVEITAMENTO_ICMS,
            $this->service->mensagemFormaDanfeNfce(1),
        );
    }

    public function test_danfe_nfce_faixa_fixa_presente_em_todos_os_crt(): void
    {
        foreach ([1, 2, 3, 4] as $crt) {
            $this->assertSame(
                FiscalMensagensLegais::NFCE_SEM_APROVEITAMENTO_ICMS,
                $this->service->mensagemFormaDanfeNfce($crt),
                "DANFE NFC-e deve exibir a faixa fixa para CRT {$crt}",
            );
        }
    }

    public function test_crt_3_nao_adiciona_mensagens_do_simples(): void
    {
        $mensagens = $this->service->mensagens([
            'modelo' => '55',
            'crt' => 3,
            'p_cred_sn' => 2.0,
            'v_cred_icms_sn' => 10.0,
        ]);

        $this->assertSame([], $mensagens);
    }

    public function test_crt_2_excesso_sublimite_usa_redacao_cgsn_140_e_nao_texto_crt_1(): void
    {
        $mensagensNfe = $this->service->mensagens([
            'modelo' => '55',
            'crt' => 2,
            'p_cred_sn' => 2.0,
            'v_cred_icms_sn' => 10.0,
        ]);

        $this->assertContains(FiscalMensagensLegais::SIMPLES_EXCESSO_SUBLIMITE, $mensagensNfe);
        $this->assertContains(FiscalMensagensLegais::SIMPLES_SEM_CREDITO_IPI, $mensagensNfe);
        $this->assertNotContains(FiscalMensagensLegais::SIMPLES_ME_EPP, $mensagensNfe);
        $this->assertFalse($this->contemAproveitamentoIcms($mensagensNfe));

        $mensagensNfce = $this->service->mensagens([
            'modelo' => '65',
            'crt' => 2,
        ]);

        $this->assertContains(FiscalMensagensLegais::SIMPLES_EXCESSO_SUBLIMITE, $mensagensNfce);
        $this->assertContains(FiscalMensagensLegais::SIMPLES_SEM_CREDITO_IPI, $mensagensNfce);
        $this->assertNotContains(FiscalMensagensLegais::NFCE_SEM_APROVEITAMENTO_ICMS, $mensagensNfce);
    }

    public function test_crt_4_mei_nao_usa_texto_de_me_epp_crt_1(): void
    {
        foreach (['55', '65'] as $modelo) {
            $mensagens = $this->service->mensagens([
                'modelo' => $modelo,
                'crt' => 4,
                'p_cred_sn' => 2.0,
                'v_cred_icms_sn' => 10.0,
            ]);

            $this->assertSame([], $mensagens, "CRT 4 (MEI) modelo {$modelo} não deve herdar mensagens de CRT 1");
            $this->assertNotContains(FiscalMensagensLegais::SIMPLES_ME_EPP, $mensagens);
            $this->assertNotContains(FiscalMensagensLegais::SIMPLES_EXCESSO_SUBLIMITE, $mensagens);
            $this->assertFalse($this->contemAproveitamentoIcms($mensagens));
        }
    }

    public function test_inf_cpl_preserva_texto_usuario_e_nao_permite_substituir_bloco_legal(): void
    {
        $legais = $this->service->mensagens([
            'modelo' => '55',
            'crt' => 1,
        ]);

        $usuario = 'ENTREGA NO LOCAL. '.FiscalMensagensLegais::SIMPLES_ME_EPP.' TEXTO EXTRA';

        $infCpl = $this->service->comporInfCpl($usuario, $legais, 'Trib. aprox. Total R$ 1,00. Lei 12.741/2012.');

        $this->assertStringContainsString('ENTREGA NO LOCAL', $infCpl);
        $this->assertStringContainsString('TEXTO EXTRA', $infCpl);
        $this->assertStringContainsString(FiscalMensagensLegais::SIMPLES_ME_EPP, $infCpl);
        $this->assertStringContainsString(FiscalMensagensLegais::SIMPLES_SEM_CREDITO_IPI, $infCpl);
        $this->assertStringContainsString('Lei 12.741/2012', $infCpl);

        // Duplicata digitada pelo usuário não permanece em dobro.
        $this->assertSame(
            1,
            substr_count(mb_strtoupper($infCpl, 'UTF-8'), mb_strtoupper(FiscalMensagensLegais::SIMPLES_ME_EPP, 'UTF-8')),
        );

        // Bloco legal permanece mesmo se o usuário tentar “apagar” no texto livre.
        $semLegalNoUsuario = $this->service->comporInfCpl('SOMENTE OBSERVACAO LIVRE', $legais);
        $this->assertStringContainsString(FiscalMensagensLegais::SIMPLES_ME_EPP, $semLegalNoUsuario);
        $this->assertStringContainsString('SOMENTE OBSERVACAO LIVRE', $semLegalNoUsuario);
    }

    public function test_nao_gera_credito_artificial_sem_valores(): void
    {
        $this->assertNull($this->service->mensagemCreditoIcmsSn(null, null));
        $this->assertNull($this->service->mensagemCreditoIcmsSn(1.0, 0));
        $this->assertNull($this->service->mensagemCreditoIcmsSn(0, 10.0));
    }

    public function test_resumir_credito_dos_itens_soma_valores_existentes(): void
    {
        $resumo = $this->service->resumirCreditoSnDosItens([
            ['p_cred_sn' => 1.5, 'v_cred_icms_sn' => 4.0],
            ['pCredSN' => 1.5, 'vCredICMSSN' => 6.5],
            ['csosn' => '102'],
        ]);

        $this->assertSame(1.5, $resumo['p_cred_sn']);
        $this->assertSame(10.5, $resumo['v_cred_icms_sn']);
    }

    /**
     * @param  list<string>  $mensagens
     */
    private function contemAproveitamentoIcms(array $mensagens): bool
    {
        foreach ($mensagens as $mensagem) {
            if (str_contains($mensagem, 'PERMITE O APROVEITAMENTO DO CRÉDITO DE ICMS')) {
                return true;
            }
        }

        return false;
    }
}
