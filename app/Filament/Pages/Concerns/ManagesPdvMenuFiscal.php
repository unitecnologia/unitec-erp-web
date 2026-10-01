<?php

namespace App\Filament\Pages\Concerns;

/**
 * Menu Fiscal do PDV — somente estado visual.
 * Não gera arquivo, não exporta XML e não consulta venda, estoque ou documento.
 */
trait ManagesPdvMenuFiscal
{
    public ?string $menuFiscalAviso = null;

    public string $menuFiscalXmlDe = '';

    public string $menuFiscalXmlAte = '';

    /** nfce|nfe — só o select da tela. */
    public string $menuFiscalXmlTipo = 'nfce';

    public string $menuFiscalXmlDestino = '';

    public string $menuFiscalRegistrosDe = '';

    public string $menuFiscalRegistrosAte = '';

    /** total|parcial — só o rádio da tela. */
    public string $menuFiscalRegistrosEstoque = 'total';

    public string $menuFiscalRegistrosBusca = '';

    public string $menuFiscalDavDe = '';

    public string $menuFiscalDavAte = '';

    /** todos|aberto|emitido_dfe|baixado — só o filtro da tela. */
    public string $menuFiscalDavSituacao = 'todos';

    public function openMenuFiscal(): void
    {
        $this->menuFiscalAviso = null;
        $this->activeModal = 'menu_fiscal';
        $this->dispatch('erp-pdv-modal-opened', modal: 'menu_fiscal');
    }

    public function selectMenuFiscalOption(string $option): void
    {
        $option = trim($option);
        $this->menuFiscalAviso = null;

        if ($option === 'sair') {
            $this->closePdvModal();

            return;
        }

        if ($option === 'identificacao') {
            $this->activeModal = 'menu_fiscal_identificacao';
            $this->dispatch('erp-pdv-modal-opened', modal: 'menu_fiscal_identificacao');

            return;
        }

        if ($option === 'exportacao_xml') {
            $this->menuFiscalXmlDe = '';
            $this->menuFiscalXmlAte = '';
            $this->menuFiscalXmlTipo = 'nfce';
            $this->menuFiscalXmlDestino = '';
            $this->activeModal = 'menu_fiscal_exportacao_xml';
            $this->dispatch('erp-pdv-modal-opened', modal: 'menu_fiscal_exportacao_xml');

            return;
        }

        if ($option === 'registros') {
            $this->menuFiscalRegistrosDe = '';
            $this->menuFiscalRegistrosAte = '';
            $this->menuFiscalRegistrosEstoque = 'total';
            $this->menuFiscalRegistrosBusca = '';
            $this->activeModal = 'menu_fiscal_registros';
            $this->dispatch('erp-pdv-modal-opened', modal: 'menu_fiscal_registros');

            return;
        }

        if ($option === 'dav') {
            $this->menuFiscalDavDe = '';
            $this->menuFiscalDavAte = '';
            $this->menuFiscalDavSituacao = 'todos';
            $this->activeModal = 'menu_fiscal_dav';
            $this->dispatch('erp-pdv-modal-opened', modal: 'menu_fiscal_dav');

            return;
        }

        $this->menuFiscalAviso = 'Opção inválida.';
    }

    public function closeMenuFiscalIdentificacao(): void
    {
        $this->menuFiscalAviso = null;
        $this->activeModal = 'menu_fiscal';
    }

    public function closeMenuFiscalExportacaoXml(): void
    {
        $this->menuFiscalAviso = null;
        $this->activeModal = 'menu_fiscal';
    }

    public function closeMenuFiscalRegistros(): void
    {
        $this->menuFiscalAviso = null;
        $this->activeModal = 'menu_fiscal';
    }

    public function closeMenuFiscalDav(): void
    {
        $this->menuFiscalAviso = null;
        $this->activeModal = 'menu_fiscal';
    }

    public function avisarMenuFiscalPreparacao(): void
    {
        $this->menuFiscalAviso = 'Função em preparação.';
    }

    /**
     * Textos fixos da tela. Não lê banco, certificado nem log de atualização.
     *
     * @return array<string, string>
     */
    public function menuFiscalIdentificacao(): array
    {
        return [
            'cnpj' => '22.469.772/0001-00',
            'razao_social' => 'Unitecnologia Sistemas LTDA',
            'endereco' => 'Rua Dom Daniel, 269, Sala 02 – Vila Real, Balneário Camboriú/SC',
            'telefone' => '(47) 98400-2117',
            'responsavel_tecnico' => 'Unitecnologia Sistemas',
            'nome_comercial' => 'Unitec PDV',
            'versao' => '1.1.0.049',
            'data_ultima_atualizacao' => '—',
            'arquitetura_banco_valor' => 'Local',
            'arquitetura_banco_detalhe' => 'SQLite 3.49.2 · database.sqlite',
            'arquitetura_execucao' => 'PAF-NFC-e Local',
            'arquitetura_execucao_detalhe' => 'FrankenPHP · PHP 8.4.12 · Windows 64 bits',
            'credenciamento_numero' => '12630540001638',
            'credenciamento_inicio' => '29/09/2026',
            'credenciamento_situacao' => 'Ativo',
            'credenciamento_responsavel' => '12606800001511',
        ];
    }
}
