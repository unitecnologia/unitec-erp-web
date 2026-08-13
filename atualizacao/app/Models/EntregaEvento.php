<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaEvento extends Model
{
    protected $table = 'entrega_eventos';

    public $timestamps = false;

    protected $fillable = [
        'entrega_id',
        'user_id',
        'de_status',
        'para_status',
        'observacao',
        'created_at',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
