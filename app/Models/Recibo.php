<?php

namespace App\Models;

use App\Support\Erp\ValorPorExtenso;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'codigo',
    'emissao',
    'valor',
    'extenso',
    'recebi_de',
    'referente_a',
])]
class Recibo extends Model
{
    protected $table = 'recibos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codigo' => 'integer',
            'emissao' => 'date',
            'valor' => 'decimal:2',
        ];
    }

    public static function nextCodigo(): int
    {
        return (int) (static::query()->max('codigo') ?? 0) + 1;
    }

    public function ensureExtenso(): string
    {
        if (filled($this->extenso)) {
            return (string) $this->extenso;
        }

        return ValorPorExtenso::fromMoney($this->valor);
    }

    public function valorFormatado(): string
    {
        return number_format((float) $this->valor, 2, ',', '.');
    }
}
