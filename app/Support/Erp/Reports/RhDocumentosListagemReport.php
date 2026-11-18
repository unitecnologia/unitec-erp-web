<?php

namespace App\Support\Erp\Reports;

use App\Models\RhAnexo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhDocumentosListagemReport
{
    public const MAX_ROWS = 500;

    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'id' => 'Cód.',
            'funcionario' => 'Funcionário',
            'titulo' => 'Documento',
            'emitido_em' => 'Emitido',
            'valido_ate' => 'Válido até',
            'situacao' => 'Situação',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(self::columnDefinitions());
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'ativos' => 'Ativos',
            'inativos' => 'Inativos',
            'todos' => 'Todos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validadeLabels(): array
    {
        return [
            'todos' => 'Todas',
            'vencendo' => 'Vencendo (30 dias)',
            'vencidos' => 'Vencidos',
        ];
    }

    public static function cellValue(RhAnexo $anexo, string $column): string
    {
        return match ($column) {
            'id' => (string) $anexo->getKey(),
            'funcionario' => trim(($anexo->anexavel?->codigo ?? '').' '.($anexo->anexavel?->nome ?? '')) ?: '—',
            'titulo' => (string) ($anexo->titulo ?: '—'),
            'emitido_em' => $anexo->emitido_em?->format('d/m/Y') ?? '—',
            'valido_ate' => $anexo->valido_ate?->format('d/m/Y') ?? '—',
            'situacao' => $anexo->situacaoValidadeLabel(),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'ativos');
        if (! in_array($status, ['ativos', 'inativos', 'todos'], true)) {
            $status = 'ativos';
        }

        $validade = (string) $request->query('validade', 'todos');
        if (! in_array($validade, ['todos', 'vencendo', 'vencidos'], true)) {
            $validade = 'todos';
        }

        $query = RhAnexo::query()
            ->documentos()
            ->with(['anexavel:id,codigo,nome']);

        $query = match ($status) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        if ($validade === 'vencendo') {
            $hoje = now()->toDateString();
            $limite = now()->addDays(30)->toDateString();
            $query->whereNotNull('valido_ate')
                ->whereDate('valido_ate', '>=', $hoje)
                ->whereDate('valido_ate', '<=', $limite);
        } elseif ($validade === 'vencidos') {
            $query->whereNotNull('valido_ate')
                ->whereDate('valido_ate', '<', now()->toDateString());
        }

        $campo = (string) $request->query('campo', 'titulo');
        $term = mb_strtoupper(trim((string) $request->query('q', '')), 'UTF-8');

        if ($term !== '') {
            if ($campo === 'id' && is_numeric($term)) {
                $query->whereKey((int) $term);
            } elseif ($campo === 'funcionario') {
                $like = '%'.$term.'%';
                $query->whereHas('anexavel', function (Builder $q) use ($like): void {
                    $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
                });
            } else {
                $query->where('titulo', 'like', '%'.$term.'%');
            }
        }

        return $query->orderByDesc('id')->limit(self::MAX_ROWS);
    }
}
