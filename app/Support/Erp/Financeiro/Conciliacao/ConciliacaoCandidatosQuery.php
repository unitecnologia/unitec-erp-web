<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

use App\Models\CaixaLancamento;
use App\Models\ExtratoLancamento;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Candidatos do Livro Caixa para conciliação manual 1:1 (C3).
 * Consulta só sob demanda — nunca no mount da listagem.
 */
final class ConciliacaoCandidatosQuery
{
    public function janelaDias(): int
    {
        return (int) config('unitec.conciliacao.c3_date_window_days', 3);
    }

    public function perPage(): int
    {
        return (int) config('unitec.conciliacao.c3_candidates_per_page', 10);
    }

    public function maxResults(): int
    {
        return (int) config('unitec.conciliacao.c3_candidates_max', 50);
    }

    /**
     * @return LengthAwarePaginator<int, CaixaLancamento>
     */
    public function paginate(ExtratoLancamento $extrato, int $page = 1): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = $this->perPage();
        $max = $this->maxResults();

        // Limite duro de candidatos (hardware fraco); paginação só dentro desse teto.
        $rows = $this->query($extrato)->limit($max)->get();
        $total = $rows->count();
        $slice = $rows->forPage($page, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => '/', 'pageName' => 'page'],
        );
    }

    /**
     * @return Builder<CaixaLancamento>
     */
    public function query(ExtratoLancamento $extrato): Builder
    {
        if (! Schema::hasTable('caixa_lancamentos')) {
            return CaixaLancamento::query()->whereRaw('1 = 0');
        }

        $empresaId = (int) $extrato->empresa_id;
        $contaId = (int) $extrato->caixa_conta_id;
        $valor = round((float) $extrato->valor, 2);
        $tipo = (string) $extrato->tipo;
        $data = $extrato->data_movimento instanceof Carbon
            ? $extrato->data_movimento->toDateString()
            : (string) $extrato->data_movimento;

        if ($empresaId <= 0 || $contaId <= 0 || $valor <= 0 || $data === '') {
            return CaixaLancamento::query()->whereRaw('1 = 0');
        }

        if (! in_array($tipo, [ExtratoLancamento::TIPO_CREDITO, ExtratoLancamento::TIPO_DEBITO], true)) {
            return CaixaLancamento::query()->whereRaw('1 = 0');
        }

        $janela = $this->janelaDias();
        $de = Carbon::parse($data)->subDays($janela)->toDateString();
        $ate = Carbon::parse($data)->addDays($janela)->toDateString();

        $query = CaixaLancamento::query()
            ->select([
                'id',
                'empresa_id',
                'codigo',
                'emissao',
                'documento',
                'historico',
                'caixa_conta_id',
                'entrada',
                'saida',
            ])
            ->where('caixa_conta_id', $contaId)
            ->where(function (Builder $q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)
                    ->orWhereNull('empresa_id');
            })
            ->whereDate('emissao', '>=', $de)
            ->whereDate('emissao', '<=', $ate);

        if ($tipo === ExtratoLancamento::TIPO_CREDITO) {
            $query->where('entrada', $valor)->where('saida', 0);
        } else {
            $query->where('saida', $valor)->where('entrada', 0);
        }

        // PIX com txId/endToEndId no OFX: só candidatos com o mesmo id no histórico.
        // FITID não entra nesta lista.
        $idsPix = PixIdentificadorOfx::extrairIdentificadoresDoExtrato($extrato);
        if ($idsPix !== []) {
            $query->where(function (Builder $q) use ($idsPix): void {
                foreach ($idsPix as $id) {
                    $q->orWhere('historico', 'like', '%'.$id.'%');
                }
            });
        }

        // Exclui lançamentos já vinculados a algum extrato.
        if (Schema::hasTable('extrato_lancamentos')
            && Schema::hasColumn('extrato_lancamentos', 'caixa_lancamento_id')
        ) {
            $query->whereNotExists(function ($sub) use ($empresaId): void {
                $sub->select(DB::raw(1))
                    ->from('extrato_lancamentos as el')
                    ->whereColumn('el.caixa_lancamento_id', 'caixa_lancamentos.id')
                    ->where('el.empresa_id', $empresaId)
                    ->whereNotNull('el.caixa_lancamento_id');
            });
        }

        // Ordena pelo mais próximo da data do extrato (sem N+1).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $query->orderByRaw('ABS(julianday(emissao) - julianday(?))', [$data]);
        } else {
            $query->orderByRaw('ABS(DATEDIFF(emissao, ?))', [$data]);
        }

        return $query->orderBy('id');
    }
}
