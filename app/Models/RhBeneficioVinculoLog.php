<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhBeneficioVinculoLog extends Model
{
    public $timestamps = false;

    protected $table = 'rh_beneficio_vinculo_logs';

    protected $fillable = [
        'vinculo_id',
        'user_id',
        'acao',
        'antes',
        'depois',
        'motivo',
        'created_at',
    ];

    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(RhBeneficioVinculo::class, 'vinculo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'depois' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
