<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfeCartaCorrecao extends Model
{
    protected $table = 'nfe_cartas_correcao';

    protected $fillable = [
        'nfe_id',
        'sequencia',
        'correcao',
        'protocolo',
        'xml',
    ];

    public function nfe(): BelongsTo
    {
        return $this->belongsTo(Nfe::class);
    }
}
