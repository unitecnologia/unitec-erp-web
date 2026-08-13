<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nfe_id',
    'empresa_id',
    'numero',
    'data_vencimento',
    'valor',
    'path_pdf_boleto',
])]
class NfeFatura extends Model
{
    protected $table = 'nfe_faturas';

    public function nfe(): BelongsTo
    {
        return $this->belongsTo(Nfe::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    protected function casts(): array
    {
        return [
            'data_vencimento' => 'date',
            'valor' => 'decimal:2',
        ];
    }
}
