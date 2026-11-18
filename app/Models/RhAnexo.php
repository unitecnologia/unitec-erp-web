<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RhAnexo extends Model
{
    public const CAT_DOCUMENTO = 'documento';

    public const CAT_EPI = 'epi';

    public const CAT_EXAME = 'exame';

    public const CAT_TREINAMENTO = 'treinamento';

    public const CAT_UNIFORME = 'uniforme';

    public const CAT_HOLERITE = 'holerite';

    public const CAT_OCORRENCIA = 'ocorrencia';

    public const CAT_FERIAS = 'ferias';

    public const CAT_OUTRO = 'outro';

    public const SIT_OK = 'ok';

    public const SIT_VENCENDO = 'vencendo';

    public const SIT_VENCIDO = 'vencido';

    public const SIT_SEM = 'sem_validade';

    protected $table = 'rh_anexos';

    protected $fillable = [
        'anexavel_type',
        'anexavel_id',
        'categoria',
        'titulo',
        'caminho',
        'mime',
        'tamanho',
        'emitido_em',
        'valido_ate',
        'entregue_em',
        'observacao',
        'ativo',
    ];

    public function anexavel(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * URL autenticada de download (não expõe storage público).
     */
    public function downloadUrl(): ?string
    {
        if (! filled($this->caminho) || ! $this->getKey()) {
            return null;
        }

        return route('erp.rh.anexo.download', ['anexo' => $this->getKey()]);
    }

    /**
     * @deprecated Use downloadUrl() — mantido para compatibilidade.
     */
    public function url(): ?string
    {
        return $this->downloadUrl();
    }

    public function situacaoValidade(int $diasAlerta = 30): string
    {
        if (! $this->valido_ate) {
            return self::SIT_SEM;
        }

        $hoje = Carbon::today();
        $limite = $hoje->copy()->addDays(max(1, $diasAlerta));

        if ($this->valido_ate->lt($hoje)) {
            return self::SIT_VENCIDO;
        }

        if ($this->valido_ate->lte($limite)) {
            return self::SIT_VENCENDO;
        }

        return self::SIT_OK;
    }

    public function situacaoValidadeLabel(int $diasAlerta = 30): string
    {
        return match ($this->situacaoValidade($diasAlerta)) {
            self::SIT_VENCIDO => 'Vencido',
            self::SIT_VENCENDO => 'Vencendo',
            self::SIT_OK => 'OK',
            default => '—',
        };
    }

    public function scopeDocumentos(Builder $query): Builder
    {
        return $query
            ->where('categoria', self::CAT_DOCUMENTO)
            ->where('anexavel_type', (new RhFuncionario)->getMorphClass());
    }

    public function scopeHolerites(Builder $query): Builder
    {
        return $query
            ->where('categoria', self::CAT_HOLERITE)
            ->where('anexavel_type', (new RhHolerite)->getMorphClass());
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeVencendoEm(Builder $query, int $dias = 30): Builder
    {
        $hoje = Carbon::today();
        $limite = $hoje->copy()->addDays($dias);

        return $query->ativos()
            ->whereNotNull('valido_ate')
            ->whereDate('valido_ate', '>=', $hoje)
            ->whereDate('valido_ate', '<=', $limite);
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query->ativos()
            ->whereNotNull('valido_ate')
            ->whereDate('valido_ate', '<', Carbon::today());
    }

    protected function casts(): array
    {
        return [
            'emitido_em' => 'date',
            'valido_ate' => 'date',
            'entregue_em' => 'date',
            'ativo' => 'boolean',
            'tamanho' => 'integer',
        ];
    }
}
