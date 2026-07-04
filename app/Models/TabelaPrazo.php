<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forma_pagamento_id',
    'dias',
    'ordem',
])]
class TabelaPrazo extends Model
{
    protected $table = 'tabelas_prazo';

    protected function casts(): array
    {
        return [
            'forma_pagamento_id' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function getQtdParcelasAttribute(): int
    {
        return count(array_filter(explode(',', (string) $this->dias), fn ($d) => trim($d) !== ''));
    }
}
