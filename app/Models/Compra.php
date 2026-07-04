<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'numero',
    'data_emissao',
    'data_entrada',
    'numero_nota',
    'fornecedor_id',
    'chave_nfe',
    'total',
    'status',
])]
class Compra extends Model
{
    public const STATUS_ABERTA = 'aberta';

    public const STATUS_FECHADA = 'fechada';

    public const STATUS_CANCELADA = 'cancelada';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ABERTA => 'Aberta',
            self::STATUS_FECHADA => 'Fechada',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        return str_pad((string) (($max ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'fornecedor_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CompraItem::class);
    }

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_entrada' => 'date',
            'total' => 'decimal:2',
        ];
    }
}
