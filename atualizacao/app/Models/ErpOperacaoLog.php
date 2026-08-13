<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpOperacaoLog extends Model
{
    protected $table = 'erp_operacao_logs';

    protected $fillable = [
        'ocorrido_em',
        'user_id',
        'user_nome',
        'empresa_id',
        'operacao',
        'origem',
        'documento_tipo',
        'documento_id',
        'documento_numero',
        'resultado',
        'resumo',
        'detalhes',
    ];

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'datetime',
            'detalhes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
