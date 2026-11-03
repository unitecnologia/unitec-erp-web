<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'caixa_livro_movimento_id',
    'tipo',
    'user_id',
    'payload',
    'created_at',
])]
class CaixaLivroMovimentoEvento extends Model
{
    public const UPDATED_AT = null;

    public const TIPO_ABRIR = 'abrir';

    public const TIPO_FECHAR = 'fechar';

    public const TIPO_REABRIR = 'reabrir';

    public const TIPO_IMPRIMIR = 'imprimir';

    protected $table = 'caixa_livro_movimento_eventos';

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(CaixaLivroMovimento::class, 'caixa_livro_movimento_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
