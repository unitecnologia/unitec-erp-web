<?php

namespace App\Support\Erp;

use App\Models\Person;

/**
 * Busca padrão Uni (listagens): clientes ativos por código, nome, fantasia ou CPF/CNPJ.
 */
class ClienteLookupService
{
    /**
     * @return array<int, array{id: int, codigo: string, nome: string, fantasia: string, cpf_cnpj: string}>
     */
    public function buscar(string $term, int $limit = 50): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        if (mb_strlen($term) > 80) {
            $term = mb_substr($term, 0, 80);
        }

        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true)
            ->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%'.$digits.'%';
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        [$digitsLike]
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like)
                        ->orWhere('id', (int) $term);
                }
            });

        return $query
            ->orderBy('nome_razao')
            ->limit(max(1, min(100, $limit)))
            ->get(['id', 'codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'])
            ->map(static fn (Person $person): array => [
                'id' => (int) $person->id,
                'codigo' => (string) ($person->codigo ?? ''),
                'nome' => mb_strtoupper((string) $person->nome_razao, 'UTF-8'),
                'fantasia' => mb_strtoupper((string) ($person->apelido_fantasia ?? ''), 'UTF-8'),
                'cpf_cnpj' => (string) ($person->cpf_cnpj ?? ''),
            ])
            ->all();
    }

    /**
     * Cidades distintas apenas de clientes ativos.
     *
     * @return array<int, array{cidade: string, uf: string}>
     */
    public function buscarCidades(string $term, int $limit = 50): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        if (mb_strlen($term) > 80) {
            $term = mb_substr($term, 0, 80);
        }

        $like = '%'.$term.'%';

        $rows = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true)
            ->whereNotNull('cidade_nome')
            ->where('cidade_nome', '!=', '')
            ->where('cidade_nome', 'like', $like)
            ->selectRaw('MAX(cidade_nome) as cidade_nome, MAX(uf) as uf')
            ->groupByRaw('UPPER(TRIM(cidade_nome)), UPPER(TRIM(COALESCE(uf, "")))')
            ->orderByRaw('UPPER(TRIM(cidade_nome))')
            ->limit(max(1, min(100, $limit)))
            ->get();

        return $rows
            ->map(static function ($row): array {
                return [
                    'cidade' => mb_strtoupper(trim((string) ($row->cidade_nome ?? '')), 'UTF-8'),
                    'uf' => mb_strtoupper(trim((string) ($row->uf ?? '')), 'UTF-8'),
                ];
            })
            ->filter(static fn (array $row): bool => $row['cidade'] !== '')
            ->values()
            ->all();
    }
}
