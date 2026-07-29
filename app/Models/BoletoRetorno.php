<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'id_legado', 'cadastrado_em', 'processado_em', 'arquivado_em',
    'arquivo_nome', 'arquivo_data', 'arquivo_numero', 'arquivo_local', 'arquivo_md5',
    'arquivo_qtd_titulos', 'situacao',
])]
class BoletoRetorno extends Model
{
    protected $table = 'boleto_retornos';

    public const SITUACAO_PENDENTE = 0;

    public const SITUACAO_PROCESSADO = 1;

    public const SITUACAO_ARQUIVADO = 2;

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function titulos(): HasMany
    {
        return $this->hasMany(BoletoRetornoTitulo::class, 'boleto_retorno_id');
    }

    public function situacaoLabel(): string
    {
        return match ((int) $this->situacao) {
            self::SITUACAO_PROCESSADO => 'Processado',
            self::SITUACAO_ARQUIVADO => 'Arquivado',
            default => 'Pendente',
        };
    }

    protected function casts(): array
    {
        return [
            'cadastrado_em' => 'datetime',
            'processado_em' => 'datetime',
            'arquivado_em' => 'datetime',
            'arquivo_data' => 'datetime',
        ];
    }
}
