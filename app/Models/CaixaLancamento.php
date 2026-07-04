<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'codigo',
    'emissao',
    'documento',
    'historico',
    'plano_contas',
    'caixa_conta_id',
    'entrada',
    'saida',
])]
class CaixaLancamento extends Model
{
    protected $table = 'caixa_lancamentos';

    public static function nextCodigo(): int
    {
        return ((int) static::query()->max('codigo')) + 1;
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'caixa_conta_id');
    }

    protected function casts(): array
    {
        return [
            'emissao' => 'date',
            'entrada' => 'decimal:2',
            'saida' => 'decimal:2',
        ];
    }
}
