<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PriceTable;
use App\Models\Terminal;
use Illuminate\Support\Facades\Auth;

final class PdvConfig
{
    private ?Empresa $empresa = null;

    private ?Terminal $terminal = null;

    public function __construct(?Empresa $empresa = null, ?Terminal $terminal = null)
    {
        $this->empresa = $empresa ?? $this->resolveEmpresa();
        $this->terminal = $terminal ?? TerminalResolver::make()->current();
    }

    public static function make(?Empresa $empresa = null, ?Terminal $terminal = null): self
    {
        return new self($empresa, $terminal);
    }

    public function empresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function terminal(): ?Terminal
    {
        return $this->terminal;
    }

    public function pesquisaPartesDescricao(): bool
    {
        return (bool) ($this->empresa?->param_pdv_pesquisa_partes_descricao ?? false);
    }

    public function exibirEstoqueNegativo(): bool
    {
        return (bool) ($this->empresa?->param_pdv_exibir_estoque_negativo ?? true);
    }

    public function bloquearEstoqueNegativo(): bool
    {
        return (bool) ($this->empresa?->param_geral_bloquear_estoque_negativo ?? false);
    }

    public function bloquearPreco(): bool
    {
        return (bool) ($this->empresa?->param_pdv_bloquear_preco ?? false);
    }

    public function caixaRapido(): bool
    {
        $fromEmpresa = (bool) ($this->empresa?->param_pdv_caixa_rapido ?? false);

        if ($this->terminal === null) {
            return $fromEmpresa;
        }

        return $fromEmpresa || (bool) $this->terminal->pesquisa_rapida;
    }

    public function permitirDescontoItem(): bool
    {
        return (bool) ($this->empresa?->param_pdv_permitir_desconto_item ?? true);
    }

    public function descontoProdPromocao(): bool
    {
        return (bool) ($this->empresa?->param_geral_desconto_prod_promocao ?? false);
    }

    public function exibirResumoCaixa(): bool
    {
        return (bool) ($this->empresa?->param_pdv_exibir_resumo_caixa ?? true);
    }

    public function pagamentoPadraoDinheiro(): bool
    {
        return (bool) ($this->empresa?->param_pdv_pagamento_padrao_dinheiro ?? false);
    }

    public function pedirAutorizacaoExcluir(): bool
    {
        return (bool) ($this->empresa?->param_pdv_pedir_autorizacao_excluir ?? false);
    }

    public function descontoMaximo(): float
    {
        return (float) ($this->empresa?->param_desconto_maximo ?? 0);
    }

    public function rateioPessoaPdv(): bool
    {
        return (bool) ($this->empresa?->param_geral_rateio_pessoa_pdv ?? true);
    }

    public function habilitarDescontoVenda(): bool
    {
        return (bool) ($this->empresa?->param_pdv_habilitar_desconto ?? false);
    }

    public function habilitarAcrescimoVenda(): bool
    {
        return (bool) ($this->empresa?->param_pdv_habilitar_acrescimo ?? false);
    }

    public function habilitarTabelaPreco(): bool
    {
        return (bool) ($this->empresa?->param_pdv_habilitar_tabela_preco ?? false);
    }

    public function tempoBloqueioPdvMin(): int
    {
        return (int) ($this->empresa?->param_tempo_bloqueio_pdv_min ?? 0);
    }

    public function pedidoDuasVias(): bool
    {
        return (bool) ($this->empresa?->param_pdv_pedido_duas_vias ?? false);
    }

    public function checarLimiteCliente(): bool
    {
        return (bool) ($this->empresa?->param_pdv_checar_limite_cliente ?? false);
    }

    public function bloquearInatividade(): bool
    {
        return (bool) ($this->empresa?->param_pdv_bloquear_inatividade ?? false);
    }

    public function prazoMaxNotaCliente(): int
    {
        return (int) ($this->empresa?->param_prazo_max_nota_cliente ?? 0);
    }

    public function acrescimoMaximo(): float
    {
        return (float) ($this->empresa?->param_acrescimo_maximo ?? 0);
    }

    public function exibirF3Vendedor(): bool
    {
        // Controlado exclusivamente pelo parâmetro da empresa.
        // (terminais.exibe_f3 é da Contingência NFCe, conceito distinto.)
        return (bool) ($this->empresa?->param_pdv_exibir_f3_vendedor ?? false);
    }

    public function exibirF4BuscaAvancada(): bool
    {
        // Controlado exclusivamente pelo parâmetro da empresa.
        // (terminais.exibe_f4 é "Transmitir NFCe", conceito distinto.)
        return (bool) ($this->empresa?->param_pdv_exibir_f4_busca_avancada ?? false);
    }

    public function somAtivo(): bool
    {
        return (bool) ($this->empresa?->param_pdv_ativar_som ?? false);
    }

    public function exibeMesas(): bool
    {
        // Configuração por terminal: "Exibe — Mesas" (terminais.restaurante).
        return (bool) ($this->terminal?->restaurante ?? false);
    }

    public function lerPesoBalanca(): bool
    {
        return (bool) ($this->terminal?->ler_peso ?? false);
    }

    public function buscaBalancaBarras(): bool
    {
        if ($this->terminal !== null) {
            return (bool) $this->terminal->busca_balanca_barras;
        }

        return true;
    }

    public function usaTef(): bool
    {
        return (bool) ($this->terminal?->usa_tef ?? false);
    }

    public function usarPdvRetaguarda(): bool
    {
        return (bool) ($this->empresa?->param_geral_usar_pdv_retaguarda ?? true);
    }

    public function bloquearCancelamentoDocFiscal(): bool
    {
        return (bool) ($this->empresa?->param_fiscal_bloquear_cancelamento_doc ?? true);
    }

    public function planoContaCodigo(string $tipo): ?int
    {
        $codigo = match ($tipo) {
            'abertura', 'suprimento' => $this->empresa?->param_plano_abertura_caixa,
            'venda' => $this->empresa?->param_plano_venda,
            'estorno' => $this->empresa?->param_plano_devolucao,
            'sangria' => $this->empresa?->param_plano_sangria,
            'receber', 'recebimento' => $this->empresa?->param_plano_ficha_cliente,
            default => null,
        };

        $codigo = (int) ($codigo ?? 0);

        return $codigo > 0 ? $codigo : null;
    }

    public function priceTableId(): ?int
    {
        if (! $this->habilitarTabelaPreco()) {
            return null;
        }

        $sessionId = session('erp.pdv.price_table_id');

        if (filled($sessionId)) {
            return (int) $sessionId;
        }

        return PriceTable::query()
            ->where('ativo', true)
            ->where('codigo', '1')
            ->value('id');
    }

    /** Modelo de etiqueta de balança (1, 2, 3 ou 4 — padrão Delphi). */
    public function modeloBalanca(): int
    {
        $modelo = (int) ($this->empresa?->param_pdv_modelo_balanca ?? 4);

        return in_array($modelo, [1, 2, 3, 4], true) ? $modelo : 4;
    }

    /**
     * Botões de operação no fechamento (terminais.exibe_f3 … exibe_f6).
     *
     * @return list<array{key: string, atalho: string, label: string, fiscal: bool, primary: bool}>
     */
    public function finalizarOperacaoBotoes(): array
    {
        return PdvFinalizarOperacao::botoes($this->terminal);
    }

    public function finalizarOperacaoUnica(): ?string
    {
        return PdvFinalizarOperacao::operacaoUnica($this->terminal);
    }

    public function tipoImpressora(): string
    {
        return (string) ($this->terminal?->tipo_impressora ?? '1');
    }

    public function pedidoA4(): bool
    {
        return PdvPedidoReportData::shouldUsePedidoA4($this->terminal);
    }

    private function resolveEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }
}
