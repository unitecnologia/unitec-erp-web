<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'terminal_id',
    'acquirer_cnpj',
    'caixa_conta_id',
    'periodo_de',
    'periodo_ate',
    'data_prevista_liquidacao',
    'valor_bruto',
    'valor_taxas',
    'taxa_origem',
    'valor_liquido',
    'situacao',
    'ofx_situacao',
    'identificador_externo',
    'origem',
    'observacao',
    'created_by_user_id',
    'updated_by_user_id',
])]
class TefLiquidacaoLote extends Model
{
    public const SIT_PREPARACAO = 'preparacao';

    public const SIT_AGUARDANDO = 'aguardando';

    public const SIT_CONFERIDO = 'conferido';

    public const SIT_CANCELADO = 'cancelado';

    public const OFX_PENDENTE = 'pendente';

    public const OFX_PARCIAL = 'parcial';

    public const OFX_INTEGRAL = 'integral';

    public const TAXA_NAO_INFORMADA = 'nao_informada';

    public const TAXA_INFORMADA = 'informada';

    protected $table = 'tef_liquidacao_lotes';

    protected function casts(): array
    {
        return [
            'periodo_de' => 'date',
            'periodo_ate' => 'date',
            'data_prevista_liquidacao' => 'date',
            'valor_bruto' => 'decimal:2',
            'valor_taxas' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    public function caixaConta(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'caixa_conta_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(TefLiquidacaoLoteItem::class, 'lote_id');
    }

    public function vinculosOfx(): HasMany
    {
        return $this->hasMany(TefLiquidacaoLoteExtrato::class, 'lote_id');
    }

    /** Lotes que ainda “ocupam” intenções (não podem duplicar). */
    public function isAtivo(): bool
    {
        return in_array($this->situacao, [
            self::SIT_PREPARACAO,
            self::SIT_AGUARDANDO,
            self::SIT_CONFERIDO,
        ], true);
    }

    public static function situacaoLabel(string $situacao): string
    {
        return match ($situacao) {
            self::SIT_PREPARACAO => 'Em preparação',
            self::SIT_AGUARDANDO => 'Aguardando liquidação',
            self::SIT_CONFERIDO => 'Conferido',
            self::SIT_CANCELADO => 'Cancelado',
            default => strtoupper($situacao),
        };
    }

    public static function ofxSituacaoLabel(string $situacao): string
    {
        return match ($situacao) {
            self::OFX_PENDENTE => 'OFX pendente',
            self::OFX_PARCIAL => 'OFX parcial',
            self::OFX_INTEGRAL => 'OFX integral',
            default => strtoupper($situacao),
        };
    }
}
