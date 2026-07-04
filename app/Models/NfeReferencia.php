<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nfe_id',
    'referencia',
])]
class NfeReferencia extends Model
{
    protected $table = 'nfe_referencias';

    public function nfe(): BelongsTo
    {
        return $this->belongsTo(Nfe::class);
    }
}
