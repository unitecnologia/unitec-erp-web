<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Models\RhFuncionario;
use App\Models\Vendedor;
use App\Support\Erp\Os\OrdemServicoTecnicoWhatsApp;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use Tests\TestCase;

class OrdemServicoTecnicoWhatsAppTest extends TestCase
{
    public function test_preenche_whatsapp_do_funcionario_vinculado_ao_atendente(): void
    {
        $ordem = new OrdemServico(['atendente_id' => 7]);
        $atendente = new Vendedor(['nome' => 'Mecanico']);
        $atendente->setRelation('rhFuncionario', new RhFuncionario([
            'vendedor_id' => 7,
            'whatsapp' => '(47)99644-9859',
        ]));
        $ordem->setRelation('atendente', $atendente);

        $this->assertSame('(47)99644-9859', OrdemServicoTecnicoWhatsApp::raw($ordem));
        $this->assertSame(
            WhatsAppPhone::formatDisplay('(47)99644-9859'),
            OrdemServicoTecnicoWhatsApp::display($ordem),
        );
        $this->assertSame('5547996449859', WhatsAppPhone::normalize(OrdemServicoTecnicoWhatsApp::display($ordem)));
    }

    public function test_campo_fica_vazio_quando_funcionario_nao_tem_whatsapp(): void
    {
        $ordem = new OrdemServico(['atendente_id' => 7]);
        $atendente = new Vendedor(['nome' => 'Mecanico']);
        $atendente->setRelation('rhFuncionario', new RhFuncionario([
            'vendedor_id' => 7,
            'whatsapp' => '',
        ]));
        $ordem->setRelation('atendente', $atendente);

        $this->assertSame('', OrdemServicoTecnicoWhatsApp::raw($ordem));
        $this->assertSame('', OrdemServicoTecnicoWhatsApp::display($ordem));
    }

    public function test_campo_fica_vazio_sem_tecnico_responsavel(): void
    {
        $ordem = new OrdemServico(['atendente_id' => null]);

        $this->assertSame('', OrdemServicoTecnicoWhatsApp::display($ordem));
    }
}
