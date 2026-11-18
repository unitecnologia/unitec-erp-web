<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'caixa_conta_id',
    'arquivo_nome',
    'arquivo_sha256',
    'formato',
    'banco_id_arquivo',
    'agencia_arquivo',
    'conta_arquivo',
    'alerta_conta_incompativel',
    'status',
    'periodo_inicio',
    'periodo_fim',
    'total_linhas',
    'total_importados',
    'total_duplicados',
    'total_erros',
    'total_ambiguos',
    'ultimo_lote_ok',
    'total_creditos',
    'total_debitos',
    'mensagem_erro',
    'imported_by_user_id',
    'imported_at',
])]
class ExtratoImportacao extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PROCESSANDO = 'processando';

    public const STATUS_CONCLUIDA = 'concluida';

    public const STATUS_ERRO = 'erro';

    public const STATUS_CANCELADA = 'cancelada';

    public const FORMATO_OFX = 'ofx';

    protected $table = 'extrato_importacoes';

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fim' => 'date',
            'total_linhas' => 'integer',
            'total_importados' => 'integer',
            'total_duplicados' => 'integer',
            'total_erros' => 'integer',
            'total_ambiguos' => 'integer',
            'ultimo_lote_ok' => 'integer',
            'total_creditos' => 'decimal:2',
            'total_debitos' => 'decimal:2',
            'alerta_conta_incompativel' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function caixaConta(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'caixa_conta_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(ExtratoLancamento::class, 'extrato_importacao_id');
    }
}
