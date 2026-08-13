<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportadoraMotorista extends Model
{
    protected $fillable = [
        'transportadora_id',
        'nome',
        'cpf',
        'ordem',
    ];

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }
}
