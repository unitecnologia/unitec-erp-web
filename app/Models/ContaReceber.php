<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'numero',
    'emissao',
    'historico',
    'documento',
    'cliente_id',
    'vencimento',
    'valor',
    'desconto',
    'juros',
    'valor_recebido',
    'recebido_em',
    'saldo',
    'forma',
])]
class ContaReceber extends Model
{
    public const FORMA_CARTEIRA = 'carteira';

    public const FORMA_CHEQUE = 'cheque';

    public const FORMA_CARTAO = 'cartao';

    public const FORMA_BOLETO = 'boleto';

    public const FORMA_PIX = 'pix';

    protected $table = 'contas_receber';

    /**
     * @return array<string, string>
     */
    public static function formaLabels(): array
    {
        return [
            self::FORMA_CARTEIRA => 'Carteira',
            self::FORMA_CHEQUE => 'Cheques',
            self::FORMA_CARTAO => 'Cartão',
            self::FORMA_BOLETO => 'Boleto',
            self::FORMA_PIX => 'Pix',
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

    public static function calcularSaldo(float $valor, float $desconto, float $juros, float $valorRecebido): float
    {
        return round(max(0, $valor - $desconto + $juros - $valorRecebido), 2);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    protected function casts(): array
    {
        return [
            'emissao' => 'date',
            'vencimento' => 'date',
            'recebido_em' => 'date',
            'valor' => 'decimal:2',
            'desconto' => 'decimal:2',
            'juros' => 'decimal:2',
            'valor_recebido' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ContaReceber $conta): void {
            $conta->saldo = self::calcularSaldo(
                (float) $conta->valor,
                (float) $conta->desconto,
                (float) $conta->juros,
                (float) $conta->valor_recebido,
            );
        });
    }
}
