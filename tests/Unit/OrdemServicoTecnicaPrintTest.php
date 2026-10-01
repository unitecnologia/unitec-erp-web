<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Support\Erp\Os\OrdemServicoReportService;
use Tests\TestCase;

class OrdemServicoTecnicaPrintTest extends TestCase
{
    public function test_os_tecnica_esconde_valores_e_mostra_espacos_de_execucao(): void
    {
        $html = view('reports.partials.ordem-servico-document-body', $this->documentData(tecnica: true))->render();

        $this->assertStringContainsString('OS TÉCNICA', $html);
        $this->assertStringContainsString('Dados do cliente', $html);
        $this->assertStringContainsString('CLIENTE TESTE', $html);
        $this->assertStringNotContainsString('CPF / CNPJ', $html);
        $this->assertStringNotContainsString('Telefone', $html);
        $this->assertStringNotContainsString('E-mail', $html);
        $this->assertStringNotContainsString('Endereço', $html);
        $this->assertStringNotContainsString('000.000.000-00', $html);
        $this->assertStringNotContainsString('(47)99999-0000', $html);
        $this->assertStringContainsString('Técnico responsável', $html);
        $this->assertStringContainsString('JOAO MECANICO', $html);
        $this->assertStringContainsString('Equipamento', $html);
        $this->assertStringContainsString('Problema relatado pelo cliente', $html);
        $this->assertStringContainsString('Barulho no motor', $html);
        $this->assertStringContainsString('Observações', $html);
        $this->assertStringContainsString('os-doc--tecnica', $html);
        $this->assertStringContainsString('Diagnóstico / Serviço executado', $html);
        $this->assertSame(7, substr_count($html, '<tr><td>&nbsp;</td></tr>'));
        $this->assertStringContainsString('Peças / Serviços adicionais', $html);
        $this->assertStringContainsString('Troca de óleo', $html);
        $this->assertStringContainsString('Filtro', $html);
        $this->assertStringContainsString('>1,000<', $html);

        $this->assertStringNotContainsString('ORDEM DE SERVIÇO', $html);
        $this->assertStringNotContainsString('Unitário', $html);
        $this->assertStringNotContainsString('Total da OS', $html);
        $this->assertStringNotContainsString('Meio de pagamento', $html);
        $this->assertStringNotContainsString('Registro fotográfico', $html);
        $this->assertStringNotContainsString('Assinatura do cliente', $html);
        $this->assertStringNotContainsString('Serviços prestados', $html);
        $this->assertStringNotContainsString('R$ 50,00', $html);
        $this->assertStringNotContainsString('R$ 30,00', $html);
        $this->assertStringNotContainsString('R$ 80,00', $html);
    }

    public function test_os_completa_continua_com_valores(): void
    {
        $html = view('reports.partials.ordem-servico-document-body', $this->documentData(tecnica: false))->render();

        $this->assertStringContainsString('ORDEM DE SERVIÇO', $html);
        $this->assertStringContainsString('CPF / CNPJ', $html);
        $this->assertStringContainsString('Telefone', $html);
        $this->assertStringContainsString('000.000.000-00', $html);
        $this->assertStringContainsString('(47)99999-0000', $html);
        $this->assertStringContainsString('Unitário', $html);
        $this->assertStringContainsString('Total da OS', $html);
        $this->assertStringContainsString('Meio de pagamento', $html);
        $this->assertStringContainsString('Serviços prestados', $html);
        $this->assertStringContainsString('Registro fotográfico', $html);
        $this->assertStringContainsString('Assinatura do cliente', $html);
        $this->assertStringNotContainsString('os-doc--tecnica', $html);
        $this->assertStringNotContainsString('Diagnóstico / Serviço executado', $html);
        $this->assertStringNotContainsString('Peças / Serviços adicionais', $html);
    }

    public function test_viewer_html_compila_para_completa_e_tecnica(): void
    {
        foreach ([false, true] as $tecnica) {
            $data = $this->documentData($tecnica);
            $data['ordem']->id = 52;

            $html = view('reports.ordem-servico', $data)->render();

            $this->assertStringContainsString('pdf=1', $html);
            if ($tecnica) {
                $this->assertStringContainsString('tecnica=1', $html);
                $this->assertStringContainsString('OS Técnica', $html);
            } else {
                $this->assertStringNotContainsString('tecnica=1', $html);
                $this->assertStringContainsString('Ordem de Serviço', $html);
            }
        }
    }

    public function test_mensagens_de_envio_da_os_tecnica(): void
    {
        $report = new OrdemServicoReportService;

        $this->assertSame('OS TECNICA N.52', $report->defaultTecnicaEmailSubject('52'));
        $this->assertSame('OS Técnica nº 52', $report->defaultTecnicaEmailMessage('52'));

        $ordem = new OrdemServico([
            'nome' => 'Cliente Teste',
            'documento' => '000.000.000-00',
            'fone1' => '(47)99999-0000',
            'descricao' => 'FORD',
            'modelo' => 'KA',
            'placa' => 'ABC1D23',
        ]);

        $this->assertSame(
            "OS Técnica nº 52\n\nCliente: Cliente Teste\nEquipamento: FORD KA\nPlaca: ABC1D23",
            $report->defaultTecnicaEmailMessage('52', $ordem),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(bool $tecnica): array
    {
        $ordem = new OrdemServico([
            'nome' => 'Cliente Teste',
            'documento' => '000.000.000-00',
            'fone1' => '(47)99999-0000',
            'problema' => 'Barulho no motor',
            'observacoes' => 'Cliente aguarda contato',
            'laudo' => 'Laudo interno',
        ]);

        return [
            'ordem' => $ordem,
            'empresa' => null,
            'numero' => '52',
            'statusLabel' => 'Aberta',
            'empresaEndereco' => '',
            'logoDataUri' => null,
            'clienteEmail' => '',
            'clienteEndereco' => '',
            'clienteDocumento' => (string) $ordem->documento,
            'clienteTelefone' => (string) $ordem->fone1,
            'equipamentoLinhas' => [[
                ['label' => 'Equipamento / Marca', 'value' => 'FORD'],
            ]],
            'servicos' => [[
                'codigo' => 'S1',
                'descricao' => 'Troca de óleo',
                'qtd' => '1,000',
                'unitario' => 'R$ 50,00',
                'desconto' => '',
                'acrescimo' => '',
                'total' => 'R$ 50,00',
            ]],
            'pecas' => [[
                'codigo' => 'P1',
                'descricao' => 'Filtro',
                'ean' => '789',
                'qtd' => '1,000',
                'unitario' => 'R$ 30,00',
                'desconto' => '',
                'acrescimo' => '',
                'total' => 'R$ 30,00',
            ]],
            'totais' => [
                'servicos' => 'R$ 50,00',
                'produtos' => 'R$ 30,00',
                'geral' => 'R$ 80,00',
            ],
            'pagamentos' => [[
                'forma' => 'Dinheiro',
                'valor' => 'R$ 80,00',
                'parcelas' => [],
            ]],
            'abertura' => '28/09/2026 08:00',
            'conclusao' => '',
            'tecnico' => 'Joao Mecanico',
            'fotos' => [[
                'data_uri' => 'data:image/png;base64,AAA',
            ]],
            'assinatura' => [
                'data_uri' => 'data:image/png;base64,BBB',
                'em' => '28/09/2026 09:00',
            ],
            'printedAt' => now(),
            'autoPrint' => false,
            'embedded' => true,
            'tecnica' => $tecnica,
        ];
    }
}
