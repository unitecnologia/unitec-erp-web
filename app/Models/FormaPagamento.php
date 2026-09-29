<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'descricao',
    'conta_destino_id',
    'tipo',
    'taxa_cartao',
    'prazo_cartao',
    'max_parcelas',
    'intervalo_parcelas',
    'modo_prazo',
    'atalho',
    'tipo_movimento',
    'usa_tef',
    'usa_super_tef',
    'aparece_venda',
    'aparece_contas_receber',
    'nfce',
    'disponivel_mobile',
    'gerar_qrcode_pdv',
    'parcelas',
    'ativo',
])]
class FormaPagamento extends Model
{
    public const MODO_PRAZO_FINANCEIRO = 'financeiro';

    public const MODO_PRAZO_TABELA = 'tabela';

    protected $table = 'formas_pagamento';

    protected function casts(): array
    {
        return [
            'codigo' => 'integer',
            'conta_destino_id' => 'integer',
            'taxa_cartao' => 'decimal:2',
            'prazo_cartao' => 'integer',
            'max_parcelas' => 'integer',
            'intervalo_parcelas' => 'integer',
            'usa_tef' => 'boolean',
            'usa_super_tef' => 'boolean',
            'aparece_venda' => 'boolean',
            'aparece_contas_receber' => 'boolean',
            'nfce' => 'boolean',
            'disponivel_mobile' => 'boolean',
            'gerar_qrcode_pdv' => 'boolean',
            'parcelas' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function contaDestino(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'conta_destino_id');
    }

    public function tabelasPrazo(): HasMany
    {
        return $this->hasMany(TabelaPrazo::class)->orderBy('ordem');
    }

    /**
     * @return array<string, string>
     */
    public static function modoPrazoLabels(): array
    {
        return [
            self::MODO_PRAZO_FINANCEIRO => 'Financeiro',
            self::MODO_PRAZO_TABELA => 'Tabela de Prazo',
        ];
    }

    /**
     * Tooltips (title) das opções de modo_prazo.
     *
     * @return array<string, string>
     */
    public static function modoPrazoHints(): array
    {
        return [
            self::MODO_PRAZO_FINANCEIRO => 'Gera automaticamente os vencimentos conforme o número máximo de parcelas e o intervalo configurado. Ex.: 3 parcelas / 30 dias = 30, 60 e 90 dias.',
            self::MODO_PRAZO_TABELA => 'Utiliza os prazos cadastrados na tabela, permitindo condições específicas como 14, 28 e 42 dias.',
        ];
    }

    public static function normalizeModoPrazo(?string $modo): string
    {
        $m = mb_strtolower(trim((string) $modo), 'UTF-8');

        return array_key_exists($m, self::modoPrazoLabels())
            ? $m
            : self::MODO_PRAZO_TABELA;
    }

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            'dinheiro' => 'Dinheiro',
            'pix' => 'PIX',
            'cartao_debito' => 'Cartão de Débito',
            'cartao_credito' => 'Cartão de Crédito',
            'deposito' => 'Depósito',
            'tef' => 'TEF',
            'cheque' => 'Cheque',
            'boleto' => 'Boleto',
            'crediario' => 'Crediário',
            'troca' => 'Troca',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tipoMovimentoLabels(): array
    {
        return [
            'caixa' => 'Caixa',
            'contas_receber' => 'Contas à Receber',
            'credito_cliente' => 'Crédito Cliente',
            'troca' => 'Troca',
            'deposito' => 'Depósito',
            'nenhum' => 'Nenhum',
        ];
    }

    /**
     * Ajuda discreta (tooltip) por tipo de movimento.
     *
     * @return array<string, string>
     */
    public static function tipoMovimentoHints(): array
    {
        return [
            'caixa' => 'Movimenta o caixa resolvido pelo sistema (ex.: caixa do vendedor/usuário).',
            'contas_receber' => 'Gera título financeiro em Contas a Receber.',
            'credito_cliente' => 'Gera crédito para o cliente (sem Caixa nem Contas a Receber).',
            'troca' => 'Fluxo de troca (sem lançamento financeiro).',
            'deposito' => 'Fluxo de depósito no Livro Caixa.',
            'nenhum' => 'Não gera movimento financeiro (nem Caixa nem Contas a Receber).',
        ];
    }

    /**
     * Pré-definição ao mudar o Tipo na tela Formas de Pagamento (somente criação).
     */
    public static function defaultTipoMovimento(?string $tipo): string
    {
        return match (mb_strtolower(trim((string) $tipo), 'UTF-8')) {
            'dinheiro', 'pix', 'cartao_debito', 'tef' => 'caixa',
            'cartao_credito', 'cheque', 'boleto', 'crediario' => 'contas_receber',
            'deposito' => 'deposito',
            'troca' => 'troca',
            default => 'nenhum',
        };
    }

    /**
     * Visibilidade de campos do modal por Tipo (somente UI; não altera persistência).
     *
     * @return array{
     *     taxa_cartao: bool,
     *     prazo_cartao: bool,
     *     max_parcelas: bool,
     *     intervalo_parcelas: bool,
     *     modo_prazo: bool,
     *     tabelas_prazo: bool,
     *     bandeiras: bool,
     *     usa_tef: bool,
     *     usa_super_tef: bool,
     *     gerar_qrcode_pdv: bool,
     *     conta_destino: bool,
     *     nfce: bool,
     *     aparece_contas_receber: bool
     * }
     */
    public static function uiCamposPorTipo(?string $tipo): array
    {
        $tipo = mb_strtolower(trim((string) $tipo), 'UTF-8');

        $isCartao = in_array($tipo, ['cartao_credito', 'cartao_debito', 'tef'], true);
        $isCarne = in_array($tipo, ['boleto', 'cheque', 'crediario'], true);
        $isParcelavel = $isCartao || $isCarne;
        $isPix = $tipo === 'pix';

        return [
            // Conta destino / movimento: válidos para qualquer tipo (destino financeiro).
            'conta_destino' => true,
            // Cartão / TEF (canhoto PDV) — fora de modo_prazo.
            'taxa_cartao' => $isCartao,
            'prazo_cartao' => $isCartao,
            'usa_tef' => $isCartao,
            'usa_super_tef' => $isCartao,
            'bandeiras' => $isCartao,
            // Parcelamento: cartão + carnê (boleto/cheque/crediário).
            'max_parcelas' => $isParcelavel,
            'intervalo_parcelas' => $isParcelavel,
            // modo_prazo só no carnê (mutuamente exclusivo Financeiro × Tabela).
            'modo_prazo' => $isCarne,
            'tabelas_prazo' => $isParcelavel,
            // QR Code PDV: exclusivo PIX (PdvFinalizarPagamentosHelper::isFormaPixGerarQrcodePdv).
            'gerar_qrcode_pdv' => $isPix,
            // Flags gerais sem vínculo exclusivo a um tipo no código.
            'nfce' => true,
            'aparece_contas_receber' => true,
        ];
    }
}
