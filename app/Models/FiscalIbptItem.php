<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalIbptItem extends Model
{
    protected $table = 'fiscal_ibpt_itens';

    protected $fillable = [
        'ncm',
        'ex_tipi',
        'tipo',
        'descricao',
        'aliq_nacional',
        'aliq_importado',
        'aliq_estadual',
        'aliq_municipal',
        'vigencia_inicio',
        'vigencia_fim',
        'chave',
        'versao',
        'fonte',
    ];

    protected function casts(): array
    {
        return [
            'aliq_nacional' => 'decimal:2',
            'aliq_importado' => 'decimal:2',
            'aliq_estadual' => 'decimal:2',
            'aliq_municipal' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }
}
