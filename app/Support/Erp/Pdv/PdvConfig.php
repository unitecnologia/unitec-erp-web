<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PriceTable;
use App\Models\Terminal;
use App\Support\Erp\Balanca\BalancaEtiquetaLayout;
use Illuminate\Support\Facades\Auth;

final class PdvConfig
{
    public const FECHAMENTO_A4_PADRAO = '0';

    public const FECHAMENTO_A4_DETALHADO = '1';

    public const FECHAMENTO_BOBINA_DETALHADO = '2';

    public const FECHAMENTO_BOBINA_SINTETICO = '3';

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

    public function bloquearEstoqueNegativo(): bool
    {
        return (bool) ($this->empresa?->param_geral_bloquear_estoque_negativo ?? false);
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

    /**
     * Quando true, cartão entra no movimento de caixa (como se já tivesse caído).
     * Quando false, cartão vai para Contas a Receber até a operadora depositar.
     */
    public function lancarCartaoNoCaixa(): bool
    {
        return (bool) ($this->empresa?->param_geral_lancar_cartao_caixa ?? true);
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

    public function pedidoDuasVias(): bool
    {
        return (bool) ($this->empresa?->param_pdv_pedido_duas_vias ?? false);
    }

    public function checarLimiteCliente(): bool
    {
        return (bool) ($this->empresa?->param_pdv_checar_limite_cliente ?? false);
    }

    public function acrescimoMaximo(): float
    {
        return (float) ($this->empresa?->param_acrescimo_maximo ?? 0);
    }

    public function somAtivo(): bool
    {
        return (bool) ($this->empresa?->param_pdv_ativar_som ?? false);
    }

    public function nfceDescricaoCompleta(): bool
    {
        return (bool) ($this->empresa?->param_pdv_nfce_descricao_completa ?? false);
    }

    public function exibeMesas(): bool
    {
        // ConfiguraÃ§Ã£o por terminal: "Exibe â€” Mesas" (terminais.restaurante).
        return (bool) ($this->terminal?->restaurante ?? false);
    }

    public function lerPesoBalanca(): bool
    {
        return (bool) ($this->terminal?->ler_peso ?? false);
    }

    /**
     * Configuração serial da balança do terminal (Device Service / PDV).
     *
     * @return array{
     *     marca: string,
     *     port: string,
     *     baudRate: int,
     *     dataBits: int,
     *     parity: string,
     *     stopBits: string,
     *     handshake: string
     * }
     */
    public function balancaSerialSettings(): array
    {
        $terminal = $this->terminal;
        $opts = \App\Support\Erp\Terminais\TerminalFormOptions::class;

        return [
            'marca' => $opts::canonicalOption($opts::marcasBalancaSerial(), $terminal?->balanca_marca ?? ''),
            'port' => strtoupper(trim((string) ($terminal?->balanca_porta ?? ''))),
            'baudRate' => (int) ($terminal?->balanca_velocidade ?: 9600),
            'dataBits' => (int) ($terminal?->balanca_databits ?: 8),
            'parity' => $opts::canonicalOption($opts::paridadesBalanca(), $terminal?->balanca_paridade ?: 'None') ?: 'None',
            'stopBits' => trim((string) ($terminal?->balanca_stopbits ?: '1')) ?: '1',
            'handshake' => $opts::canonicalOption($opts::handshakingsBalanca(), $terminal?->balanca_handshaking ?: 'None') ?: 'None',
        ];
    }

    /**
     * Sempre ativo: etiqueta de balança é interpretada quando o produto é de balança.
     * (Coluna terminais.busca_balanca_barras mantida só por compatibilidade.)
     */
    public function buscaBalancaBarras(): bool
    {
        return true;
    }

    /**
     * TEF sem integração funcional: menu "Administrativo TEF" (Ctrl+T) sempre oculto.
     * A coluna terminais.usa_tef é preservada para compatibilidade.
     */
    public function usaTef(): bool
    {
        return false;
    }

    public function bloquearCancelamentoDocFiscal(): bool
    {
        return (bool) ($this->empresa?->param_fiscal_bloquear_cancelamento_doc ?? true);
    }

    public function motivoEstornoAutomatico(): bool
    {
        return (bool) ($this->empresa?->param_fiscal_motivo_estorno_automatico ?? false);
    }

    public function planoContaCodigo(string $tipo): ?int
    {
        // Códigos padrão do plano de contas (antes eram parâmetros da empresa).
        $codigo = match ($tipo) {
            'abertura', 'suprimento' => 14,
            'venda' => 2,
            'estorno' => 9,
            'sangria' => 11,
            'receber', 'recebimento' => 10,
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

    /** Modelo de etiqueta de balança (1–5). */
    public function modeloBalanca(): int
    {
        $modelo = $this->empresa?->param_balanca_etiqueta_modelo
            ?? BalancaEtiquetaLayout::DEFAULT_MODELO;

        return BalancaEtiquetaLayout::normalizeModelo($modelo);
    }

    /** Prefixo EAN da etiqueta de balança (tipicamente "2"). */
    public function prefixoCodBarraBalanca(): string
    {
        return BalancaEtiquetaLayout::normalizePrefixo(
            $this->empresa?->param_balanca_prefixo_barra ?? BalancaEtiquetaLayout::DEFAULT_PREFIXO
        );
    }

    /** Quantidade de dígitos do código do produto na etiqueta (4, 5 ou 6). */
    public function digitosBalanca(): int
    {
        $raw = $this->empresa?->param_balanca_digitos;

        if ($raw === null || $raw === '') {
            return BalancaEtiquetaLayout::digitosForModelo($this->modeloBalanca());
        }

        return BalancaEtiquetaLayout::normalizeDigitos($raw);
    }

    /**
     * BotÃµes de operaÃ§Ã£o no fechamento (terminais.exibe_f3 â€¦ exibe_f6).
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
        $tipo = (string) ($this->terminal?->tipo_impressora ?? '1');

        // "Gráfico" (2) descontinuado: imprime como ESC/POS.
        return $tipo === PdvPedidoReportData::TIPO_IMPRESSORA_GRAFICO
            ? PdvPedidoReportData::TIPO_IMPRESSORA_ESC_POS
            : $tipo;
    }

    /**
     * Device Service sempre ativo (agente local no PC do caixa).
     * Sem impressora RAW configurada, o PrintTarget cai no navegador.
     */
    public function usarDeviceService(): bool
    {
        return true;
    }

    /**
     * Exibe PDV no menu da retaguarda (param_geral_usar_pdv_erp).
     */
    public function usarPdvRetaguarda(): bool
    {
        return PdvErpPolicy::habilitado($this->empresa);
    }

    public function impressoraNome(): ?string
    {
        // Caminho RAW:Nome (visível na tela) prevalece sobre impressora_nome gravado antes.
        $fromPorta = \App\Support\Erp\Terminais\TerminalFormOptions::windowsPrinterFromPorta(
            $this->terminal?->porta
        );
        if ($fromPorta !== null) {
            return $fromPorta;
        }

        $nome = trim((string) ($this->terminal?->impressora_nome ?? ''));

        return $nome !== '' ? $nome : null;
    }

    public function pedidoA4(): bool
    {
        return PdvPedidoReportData::shouldUsePedidoA4($this->terminal);
    }

    /** Nº de vias do terminal (1–3): cupom de venda e comprovantes de sangria/suprimento. */
    public function viasImpressao(): int
    {
        return max(1, min(3, (int) ($this->terminal?->nvias ?: 1)));
    }

    /** Pedido: vias do terminal; o parâmetro "pedido em duas vias" garante pelo menos 2. */
    public function viasPedido(): int
    {
        return max($this->viasImpressao(), $this->pedidoDuasVias() ? 2 : 1);
    }

    /** "Perguntar Imprimir": marcado pergunta; desmarcado imprime direto, sem perguntar. */
    public function perguntarImprimir(): bool
    {
        return (bool) ($this->terminal?->imprime ?? true);
    }

    /**
     * Tipo de fechamento do terminal. Sem valor gravado mantém o comportamento anterior
     * (impressora A4 → A4 detalhado; demais → bobina detalhada).
     */
    public function tipoFechamento(): string
    {
        $tipo = trim((string) ($this->terminal?->tipo_fechamento ?? ''));

        if (in_array($tipo, ['0', '1', '2', '3'], true)) {
            return $tipo;
        }

        return $this->impressoraA4() ? self::FECHAMENTO_A4_DETALHADO : self::FECHAMENTO_BOBINA_DETALHADO;
    }

    /**
     * Gaveta: habilitada no terminal e ligada a uma impressora de bobina com nome Windows
     * (o pulso sai pela impressora via Device Service; impressora A4 não tem gaveta).
     */
    public function gavetaDisponivel(): bool
    {
        return (bool) ($this->terminal?->usa_gaveta ?? false)
            && $this->impressoraNome() !== null
            && ! $this->impressoraA4();
    }

    private function impressoraA4(): bool
    {
        return in_array($this->tipoImpressora(), [
            PdvPedidoReportData::TIPO_IMPRESSORA_PEDIDO_A4,
            PdvPedidoReportData::TIPO_IMPRESSORA_NFCE_A4,
        ], true);
    }

    private function resolveEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }
}

