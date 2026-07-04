<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'person_id',
    'local_id',
    'device_uuid',
    'user_id',
    'empresa_id',
    'received_at',
])]
class ForcaVendasClienteImport extends Model
{
    protected $table = 'forca_vendas_cliente_imports';

    protected function casts(): array
    {
        return [
            'local_id' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
