<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extrato append-only de movimentações de estoque (saldo global do produto).
 * Não editar/excluir pela UI.
 */
class EstoqueMovimentacao extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'estoque_movimentacoes';

    public const TIPO_ENTRADA_COMPRA = 'entrada_compra';

    public const TIPO_VENDA = 'venda';

    public const TIPO_DEVOLUCAO_VENDA = 'devolucao_venda';

    public const TIPO_DEVOLUCAO_COMPRA = 'devolucao_compra';

    public const TIPO_AJUSTE = 'ajuste';

    public const TIPO_INVENTARIO = 'inventario';

    public const TIPO_TRANSFERENCIA = 'transferencia';

    public const TIPO_CANCELAMENTO_ESTORNO = 'cancelamento_estorno';

    public const TIPO_SISTEMA = 'sistema';

    /**
     * @return array<string, string>
     */
    public static function tiposLabels(): array
    {
        return [
            self::TIPO_ENTRADA_COMPRA => 'Entrada por compra',
            self::TIPO_VENDA => 'Venda',
            self::TIPO_DEVOLUCAO_VENDA => 'Devolução de venda',
            self::TIPO_DEVOLUCAO_COMPRA => 'Devolução de compra',
            self::TIPO_AJUSTE => 'Ajuste de estoque',
            self::TIPO_INVENTARIO => 'Inventário',
            self::TIPO_TRANSFERENCIA => 'Transferência',
            self::TIPO_CANCELAMENTO_ESTORNO => 'Cancelamento/estorno',
            self::TIPO_SISTEMA => 'Sistema',
        ];
    }

    public static function tipoLabel(string $tipo): string
    {
        return self::tiposLabels()[$tipo] ?? $tipo;
    }

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'estoque_id',
        'data_movimentacao',
        'tipo',
        'quantidade',
        'saldo_anterior',
        'saldo_atual',
        'origem_tipo',
        'origem_id',
        'origem_numero',
        'doc_fiscal_tipo',
        'doc_fiscal_numero',
        'usuario_id',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data_movimentacao' => 'datetime',
            'quantidade' => 'decimal:3',
            'saldo_anterior' => 'decimal:3',
            'saldo_atual' => 'decimal:3',
            'origem_id' => 'integer',
        ];
    }

    /**
     * Rótulo amigável do documento para a grade (sem ID interno).
     */
    public static function documentoLabel(?string $origemTipo, ?string $origemNumero): string
    {
        $numero = trim((string) ($origemNumero ?? ''));
        if ($numero === '') {
            return '—';
        }

        $labels = [
            'compra' => 'Compra',
            'venda' => 'Venda',
            'pdv_venda' => 'Venda',
            'nfe' => 'NF-e',
            'nfe_entrada' => 'Compra',
            'nfce' => 'NFC-e',
            'devolucao_venda' => 'Devolução de venda',
            'devolucao_compra' => 'Devolução',
            'ajuste_estoque' => 'Ajuste',
            'ordem_servico' => 'OS',
        ];

        $tipo = (string) ($origemTipo ?? '');
        $prefix = $labels[$tipo] ?? null;
        if ($prefix === null) {
            return '—';
        }

        return $prefix.' '.$numero;
    }

    /**
     * Rótulo do documento fiscal (NF-e / NFC-e) — coluna separada do documento operacional.
     */
    public static function docFiscalLabel(?string $docFiscalTipo, ?string $docFiscalNumero): string
    {
        $numero = trim((string) ($docFiscalNumero ?? ''));
        if ($numero === '') {
            return '—';
        }

        $labels = [
            'nfe' => 'NF-e',
            'nfce' => 'NFC-e',
        ];

        $tipo = (string) ($docFiscalTipo ?? '');
        $prefix = $labels[$tipo] ?? null;
        if ($prefix === null) {
            return $numero;
        }

        return $prefix.' '.$numero;
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'produto_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class, 'estoque_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
