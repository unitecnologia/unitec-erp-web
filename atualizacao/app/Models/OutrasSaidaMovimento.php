<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'numero',
    'situacao',
    'tipo_movimento',
    'data',
    'hora',
    'estoque_id',
    'fornecedor_id',
    'fornecedor_nome',
    'observacoes',
    'total',
    'usuario_id',
])]
class OutrasSaidaMovimento extends Model
{
    protected $table = 'outras_saidas_movimentos';

    public const SITUACAO_ABERTA = 'aberta';
    public const SITUACAO_FINALIZADA = 'finalizada';
    public const SITUACAO_CANCELADA = 'cancelada';

    public static function nextNumero(?int $empresaId = null): string
    {
        $query = static::query();

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $max = $query->pluck('numero')
            ->map(fn (?string $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
            ->max();

        return str_pad((string) (($max ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OutrasSaidaMovimentoItem::class);
    }

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'fornecedor_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'total' => 'decimal:2',
        ];
    }
}
