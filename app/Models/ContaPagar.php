<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'numero',
    'emissao',
    'produto',
    'documento',
    'fornecedor_id',
    'vencimento',
    'valor',
    'desconto',
    'juros',
    'valor_pago',
    'pago_em',
    'saldo',
])]
class ContaPagar extends Model
{
    protected $table = 'contas_pagar';

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        return str_pad((string) (($max ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    public static function calcularSaldo(float $valor, float $desconto, float $juros, float $valorPago): float
    {
        return round(max(0, $valor - $desconto + $juros - $valorPago), 2);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'fornecedor_id');
    }

    protected function casts(): array
    {
        return [
            'emissao' => 'date',
            'vencimento' => 'date',
            'pago_em' => 'date',
            'valor' => 'decimal:2',
            'desconto' => 'decimal:2',
            'juros' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ContaPagar $conta): void {
            $conta->saldo = self::calcularSaldo(
                (float) $conta->valor,
                (float) $conta->desconto,
                (float) $conta->juros,
                (float) $conta->valor_pago,
            );
        });
    }
}
