<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Models\Person;
use App\Support\Erp\Os\OrdemServicoReportService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use Tests\TestCase;

class OrdemServicoEnvioMensagemTest extends TestCase
{
    public function test_os_tecnica_monta_identificacao_sem_dados_sensiveis(): void
    {
        $ordem = $this->ordemCompleta();
        $report = new OrdemServicoReportService;
        $mensagem = $report->defaultTecnicaEmailMessage('52', $ordem);

        $this->assertSame(
            "OS Técnica nº 52\n\nCliente: Cliente Teste\nEquipamento: FORD KA\nPlaca: ABC1D23",
            $mensagem,
        );
        $this->assertStringNotContainsString('000.000.000-00', $mensagem);
        $this->assertStringNotContainsString('(47)99999-0000', $mensagem);
        $this->assertStringNotContainsString('cliente@teste.com', $mensagem);
        $this->assertStringNotContainsString('Rua das Oficinas', $mensagem);
        $this->assertSame(
            $mensagem."\n\n".WhatsAppMessageHelper::SYSTEM_FOOTER,
            WhatsAppMessageHelper::withSystemFooter($mensagem),
        );
    }

    public function test_os_cliente_monta_identificacao_sem_dados_sensiveis(): void
    {
        $ordem = $this->ordemCompleta();
        $report = new OrdemServicoReportService;
        $mensagem = $report->defaultEmailMessage('52', [], $ordem);

        $this->assertSame(
            "Ordem de Serviço nº 52\n\nCliente: Cliente Teste\nEquipamento: FORD KA\nPlaca: ABC1D23",
            $mensagem,
        );
        $this->assertStringNotContainsString('000.000.000-00', $mensagem);
        $this->assertStringNotContainsString('(47)99999-0000', $mensagem);
        $this->assertStringNotContainsString('cliente@teste.com', $mensagem);
        $this->assertStringNotContainsString('Rua das Oficinas', $mensagem);
        $this->assertSame(
            $mensagem."\n\n".WhatsAppMessageHelper::SYSTEM_FOOTER,
            WhatsAppMessageHelper::withSystemFooter($mensagem),
        );
    }

    public function test_modelo_vazio_mostra_somente_equipamento(): void
    {
        $ordem = new OrdemServico([
            'nome' => 'Cliente Teste',
            'descricao' => 'FORD',
            'modelo' => '',
            'placa' => 'ABC1D23',
        ]);

        $this->assertSame(
            "OS Técnica nº 52\n\nCliente: Cliente Teste\nEquipamento: FORD\nPlaca: ABC1D23",
            (new OrdemServicoReportService)->defaultTecnicaEmailMessage('52', $ordem),
        );
    }

    public function test_placa_vazia_omite_a_linha(): void
    {
        $ordem = new OrdemServico([
            'nome' => 'Cliente Teste',
            'descricao' => 'FORD',
            'modelo' => 'KA',
            'placa' => '',
            'placa_veiculo' => '',
        ]);

        $this->assertSame(
            "Ordem de Serviço nº 52\n\nCliente: Cliente Teste\nEquipamento: FORD KA",
            (new OrdemServicoReportService)->defaultEmailMessage('52', [], $ordem),
        );
        $this->assertStringNotContainsString('Placa:', (new OrdemServicoReportService)->defaultEmailMessage('52', [], $ordem));
    }

    public function test_usa_nome_da_os_sem_consultar_cadastro_do_cliente(): void
    {
        $ordem = new OrdemServico([
            'nome' => 'Snapshot da OS',
            'cliente_id' => 99,
            'descricao' => 'HONDA',
            'modelo' => 'CG',
            'placa' => 'XYZ9A88',
        ]);

        $this->assertFalse($ordem->relationLoaded('cliente'));
        $this->assertSame(
            "OS Técnica nº 10\n\nCliente: Snapshot da OS\nEquipamento: HONDA CG\nPlaca: XYZ9A88",
            (new OrdemServicoReportService)->defaultTecnicaEmailMessage('10', $ordem),
        );
        $this->assertFalse($ordem->relationLoaded('cliente'));
    }

    public function test_usa_cliente_ja_carregado_quando_nome_da_os_esta_vazio(): void
    {
        $ordem = new OrdemServico([
            'nome' => '',
            'descricao' => 'YAMAHA',
            'modelo' => 'FZ',
            'placa' => 'DEF2G34',
        ]);
        $ordem->setRelation('cliente', new Person([
            'nome_razao' => 'Cliente Cadastro',
            'cpf_cnpj' => '111.111.111-11',
            'email' => 'cadastro@teste.com',
        ]));

        $this->assertSame(
            "Ordem de Serviço nº 7\n\nCliente: Cliente Cadastro\nEquipamento: YAMAHA FZ\nPlaca: DEF2G34",
            (new OrdemServicoReportService)->defaultEmailMessage('7', [], $ordem),
        );
    }

    public function test_usa_campos_de_veiculo_quando_os_campos_simples_estao_vazios(): void
    {
        $ordem = new OrdemServico([
            'nome' => 'Cliente Teste',
            'descricao' => '',
            'marca' => '',
            'modelo' => '',
            'placa' => '',
            'marca_veiculo' => 'VW',
            'modelo_veiculo' => 'GOL',
            'placa_veiculo' => 'GHI3J45',
        ]);

        $this->assertSame(
            "OS Técnica nº 3\n\nCliente: Cliente Teste\nEquipamento: VW GOL\nPlaca: GHI3J45",
            (new OrdemServicoReportService)->defaultTecnicaEmailMessage('3', $ordem),
        );
    }

    private function ordemCompleta(): OrdemServico
    {
        $ordem = new OrdemServico([
            'nome' => 'Cliente Teste',
            'documento' => '000.000.000-00',
            'fone1' => '(47)99999-0000',
            'endereco' => 'Rua das Oficinas',
            'descricao' => 'FORD',
            'modelo' => 'KA',
            'placa' => 'ABC1D23',
        ]);
        $ordem->setRelation('cliente', new Person([
            'nome_razao' => 'Nao Deve Aparecer Se Nome Da Os Existe',
            'cpf_cnpj' => '000.000.000-00',
            'email' => 'cliente@teste.com',
        ]));

        return $ordem;
    }
}
