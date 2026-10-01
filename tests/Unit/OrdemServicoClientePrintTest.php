<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Models\Person;
use App\Support\Erp\Os\OrdemServicoReportData;
use Tests\TestCase;

class OrdemServicoClientePrintTest extends TestCase
{
    public function test_snapshot_prevalece_e_cadastro_completa_o_que_faltar(): void
    {
        $ordem = new OrdemServico([
            'nome' => 'Cliente Snapshot',
            'documento' => '',
            'fone1' => '',
            'fone2' => '',
            'endereco' => 'Rua das Flores',
            'bairro' => 'Centro',
            'cidade' => 'Blumenau',
            'uf' => 'SC',
        ]);
        $ordem->setRelation('cliente', new Person([
            'nome_razao' => 'Outro Nome Cadastro',
            'cpf_cnpj' => '12.345.678/0001-99',
            'fone1' => '4733334444',
            'celular1' => '47999990000',
            'email' => 'cliente@exemplo.com',
            'endereco' => 'Av Outra',
            'numero' => '10',
            'complemento' => 'Sala 2',
            'bairro' => 'Velha',
            'cidade_nome' => 'Gaspar',
            'uf' => 'SC',
            'cep' => '89000-000',
        ]));

        $dados = OrdemServicoReportData::clienteExibicao($ordem);

        $this->assertSame('12.345.678/0001-99', $dados['documento']);
        $this->assertSame('4733334444', $dados['telefone']);
        $this->assertSame('cliente@exemplo.com', $dados['email']);
        $this->assertStringContainsString('RUA DAS FLORES', $dados['endereco']);
        $this->assertStringContainsString('Nº 10', $dados['endereco']);
        $this->assertStringContainsString('SALA 2', $dados['endereco']);
        $this->assertStringContainsString('CENTRO', $dados['endereco']);
        $this->assertStringContainsString('BLUMENAU/SC', $dados['endereco']);
        $this->assertStringContainsString('CEP 89000-000', $dados['endereco']);
        $this->assertStringNotContainsString('AV OUTRA', $dados['endereco']);
        $this->assertSame('Cliente Snapshot', $ordem->clienteNome());
    }

    public function test_snapshot_de_documento_e_telefone_nao_e_substituido(): void
    {
        $ordem = new OrdemServico([
            'documento' => '111.222.333-44',
            'fone1' => '47988887777',
            'endereco' => '',
            'bairro' => '',
            'cidade' => '',
            'uf' => '',
        ]);
        $ordem->setRelation('cliente', new Person([
            'cpf_cnpj' => '00.000.000/0001-00',
            'fone1' => '4700000000',
            'email' => '',
            'email2' => 'backup@exemplo.com',
            'endereco' => 'Rua Cadastro',
            'numero' => '50',
            'bairro' => 'Itoupava',
            'cidade_nome' => 'Blumenau',
            'uf' => 'SC',
            'cep' => '89012-345',
        ]));

        $dados = OrdemServicoReportData::clienteExibicao($ordem);

        $this->assertSame('111.222.333-44', $dados['documento']);
        $this->assertSame('47988887777', $dados['telefone']);
        $this->assertSame('backup@exemplo.com', $dados['email']);
        $this->assertStringContainsString('RUA CADASTRO', $dados['endereco']);
        $this->assertStringContainsString('Nº 50', $dados['endereco']);
        $this->assertStringContainsString('ITOUPAVA', $dados['endereco']);
        $this->assertStringContainsString('CEP 89012-345', $dados['endereco']);
    }

    public function test_telefone_cai_para_celular_do_cadastro(): void
    {
        $ordem = new OrdemServico(['documento' => '', 'fone1' => '', 'fone2' => '']);
        $ordem->setRelation('cliente', new Person([
            'fone1' => '',
            'celular1' => '47911112222',
            'email' => 'a@b.com',
        ]));

        $this->assertSame('47911112222', OrdemServicoReportData::clienteExibicao($ordem)['telefone']);
    }

    public function test_os_tecnica_mostra_somente_o_nome_do_cliente(): void
    {
        $ordem = new OrdemServico([
            'nome' => 'Maria Silva',
            'documento' => '',
            'fone1' => '',
            'endereco' => 'Rua A',
            'bairro' => 'Centro',
            'cidade' => 'Blumenau',
            'uf' => 'SC',
        ]);
        $ordem->setRelation('cliente', new Person([
            'cpf_cnpj' => '123.456.789-00',
            'celular1' => '47988880000',
            'email' => 'maria@exemplo.com',
            'numero' => '100',
            'complemento' => 'Apto 1',
            'cep' => '89010-000',
        ]));
        $cliente = OrdemServicoReportData::clienteExibicao($ordem);

        $base = [
            'ordem' => $ordem,
            'empresa' => null,
            'numero' => '1',
            'statusLabel' => 'Aberta',
            'empresaEndereco' => '',
            'logoDataUri' => null,
            'clienteEmail' => $cliente['email'],
            'clienteEndereco' => $cliente['endereco'],
            'clienteDocumento' => $cliente['documento'],
            'clienteTelefone' => $cliente['telefone'],
            'equipamentoLinhas' => [],
            'servicos' => [],
            'pecas' => [],
            'totais' => ['servicos' => '', 'produtos' => '', 'geral' => ''],
            'pagamentos' => [],
            'abertura' => '',
            'conclusao' => '',
            'tecnico' => '',
            'fotos' => [],
            'assinatura' => null,
            'printedAt' => now(),
            'autoPrint' => false,
            'embedded' => true,
        ];

        $completa = view('reports.partials.ordem-servico-document-body', $base + ['tecnica' => false])->render();
        $tecnica = view('reports.partials.ordem-servico-document-body', $base + ['tecnica' => true])->render();

        foreach (['MARIA SILVA', '123.456.789-00', '47988880000', 'maria@exemplo.com', 'RUA A', 'Nº 100', 'APTO 1', 'CEP 89010-000'] as $trecho) {
            $this->assertStringContainsString($trecho, $completa);
        }

        $this->assertStringContainsString('MARIA SILVA', $tecnica);
        $this->assertStringNotContainsString('123.456.789-00', $tecnica);
        $this->assertStringNotContainsString('47988880000', $tecnica);
        $this->assertStringNotContainsString('maria@exemplo.com', $tecnica);
        $this->assertStringNotContainsString('RUA A', $tecnica);
        $this->assertStringNotContainsString('CPF / CNPJ', $tecnica);
        $this->assertStringNotContainsString('Telefone', $tecnica);
        $this->assertStringNotContainsString('E-mail', $tecnica);
        $this->assertStringNotContainsString('Endereço', $tecnica);
    }
}
