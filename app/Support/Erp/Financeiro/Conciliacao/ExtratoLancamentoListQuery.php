<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

use App\Models\ExtratoLancamento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listagem paginada de linhas de extrato (C1).
 * Sem matching, agregações pesadas ou eager loads desnecessários.
 */
final class ExtratoLancamentoListQuery
{
    /**
     * @param  array{
     *   empresa_id: int,
     *   caixa_conta_id: int,
     *   periodo_de?: string|null,
     *   periodo_ate?: string|null,
     *   situacao?: string|null
     * }  $filtros
     */
    public function query(array $filtros): Builder
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $contaId = (int) ($filtros['caixa_conta_id'] ?? 0);

        if ($empresaId <= 0 || $contaId <= 0) {
            return ExtratoLancamento::query()->whereRaw('1 = 0');
        }

        $query = ExtratoLancamento::query()
            ->select([
                'id',
                'empresa_id',
                'caixa_conta_id',
                'extrato_importacao_id',
                'data_movimento',
                'tipo',
                'valor',
                'historico',
                'fitid',
                'situacao',
                'origem_tipo',
                'origem_id',
                'caixa_lancamento_id',
            ])
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId);

        $this->aplicarPeriodo($query, $filtros['periodo_de'] ?? null, $filtros['periodo_ate'] ?? null);
        $this->aplicarSituacao($query, $filtros['situacao'] ?? null);

        return $query
            ->orderByDesc('data_movimento')
            ->orderByDesc('id');
    }

    /**
     * @param  array{
     *   empresa_id: int,
     *   caixa_conta_id: int,
     *   periodo_de?: string|null,
     *   periodo_ate?: string|null,
     *   situacao?: string|null,
     *   per_page?: int,
     *   page?: int
     * }  $filtros
     */
    public function paginate(array $filtros): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($filtros['per_page'] ?? 50)));
        $page = max(1, (int) ($filtros['page'] ?? 1));

        return $this->query($filtros)->paginate($perPage, ['*'], 'page', $page);
    }

    private function aplicarPeriodo(Builder $query, ?string $de, ?string $ate): void
    {
        $de = is_string($de) ? trim($de) : '';
        $ate = is_string($ate) ? trim($ate) : '';

        if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
            $query->whereDate('data_movimento', '>=', $de);
        }

        if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
            $query->whereDate('data_movimento', '<=', $ate);
        }
    }

    private function aplicarSituacao(Builder $query, ?string $situacao): void
    {
        $situacao = strtolower(trim((string) $situacao));

        if ($situacao === '' || $situacao === 'todos') {
            return;
        }

        if (in_array($situacao, [
            ExtratoLancamento::SITUACAO_PENDENTE,
            ExtratoLancamento::SITUACAO_CONCILIADO,
            ExtratoLancamento::SITUACAO_IGNORADO,
        ], true)) {
            $query->where('situacao', $situacao);
        }
    }
}
