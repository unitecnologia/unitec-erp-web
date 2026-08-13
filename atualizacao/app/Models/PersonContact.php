<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonContact extends Model
{
    protected $fillable = [
        'person_id',
        'contato_em',
        'data_retorno',
        'pessoa',
        'motivo',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'contato_em' => 'datetime',
            'data_retorno' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
