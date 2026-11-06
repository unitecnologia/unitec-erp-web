<?php

namespace App\Support\ForcaVendas;

use App\Models\ContaReceber;
use App\Models\Person;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Collection;

/**
 * Pesquisa de clientes da Tela de Venda (sugestões no F4).
 * Isolada do Livewire para não remorphar o modal a cada tecla.
 */
final class FvClienteLookupService
{
    /**
     * @return list<array{
     *   id: int,
     *   codigo: string,
     *   nome: string,
     *   cpf_cnpj: string,
     *   doc_tipo: string,
     *   limite: string,
     *   utilizado: string,
     *   vencidas: string,
     *   tem_vencidas: bool
     * }>
     */
    public function buscar(string $term, int $limit = 12): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';

        $people = Person::query()
            ->where(function ($q) use ($term, $digits): void {
                $q->where('codigo', 'like', $term.'%')
                    ->orWhere('nome_razao', 'like', '%'.$term.'%')
                    ->orWhere('apelido_fantasia', 'like', '%'.$term.'%');

                if ($digits !== '') {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome_razao')
            ->limit(max(1, min(30, $limit)))
            ->get(['id', 'codigo', 'nome_razao', 'cpf_cnpj', 'limite_credito']);

        return $this->montarSugestoes($people);
    }

    /**
     * @param  Collection<int, Person>  $people
     * @return list<array{
     *   id: int,
     *   codigo: string,
     *   nome: string,
     *   cpf_cnpj: string,
     *   doc_tipo: string,
     *   limite: string,
     *   utilizado: string,
     *   vencidas: string,
     *   tem_vencidas: bool
     * }>
     */
    public function montarSugestoes(Collection $people): array
    {
        if ($people->isEmpty()) {
            return [];
        }

        $ids = $people->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $hoje = ErpTimezone::toLocal()->toDateString();

        $abertos = ContaReceber::query()
            ->whereIn('cliente_id', $ids)
            ->where('saldo', '>', 0)
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(saldo) as total')
            ->pluck('total', 'cliente_id');

        $vencidos = ContaReceber::query()
            ->whereIn('cliente_id', $ids)
            ->where('saldo', '>', 0)
            ->whereDate('vencimento', '<', $hoje)
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(saldo) as total')
            ->pluck('total', 'cliente_id');

        return $people->map(function (Person $p) use ($abertos, $vencidos): array {
            $id = (int) $p->id;
            $limite = round((float) ($p->limite_credito ?? 0), 2);
            $utilizado = round((float) ($abertos[$id] ?? 0), 2);
            $vencidas = round((float) ($vencidos[$id] ?? 0), 2);
            $doc = (string) ($p->cpf_cnpj ?? '');
            $digits = preg_replace('/\D+/', '', $doc) ?? '';
            $docTipo = strlen($digits) === 14 ? 'cnpj' : (strlen($digits) === 11 ? 'cpf' : '');

            return [
                'id' => $id,
                'codigo' => (string) ($p->codigo ?? ''),
                'nome' => (string) ($p->nome_razao ?? ''),
                'cpf_cnpj' => $doc,
                'doc_tipo' => $docTipo,
                'limite' => ErpMoney::formatBr($limite),
                'utilizado' => ErpMoney::formatBr($utilizado),
                'vencidas' => ErpMoney::formatBr($vencidas),
                'tem_vencidas' => $vencidas > 0,
            ];
        })->values()->all();
    }
}
